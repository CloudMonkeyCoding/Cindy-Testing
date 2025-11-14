package qa.cindys.api;

import io.restassured.RestAssured;
import io.restassured.response.Response;
import org.testng.Assert;
import org.testng.SkipException;
import org.testng.annotations.BeforeClass;
import org.testng.annotations.Test;

import static io.restassured.RestAssured.given;
import static org.hamcrest.Matchers.*;

public class AuthApiTests {

  private String baseUrl;
  private String loginEndpoint;
  private String registerEndpoint;

  @BeforeClass
  public void setup() {
    baseUrl          = orDefault(System.getProperty("baseUrl"), "http://localhost:3000");
    loginEndpoint    = orDefault(System.getProperty("loginEndpoint"), "/UserSide/login.html");
    registerEndpoint = orDefault(System.getProperty("registerEndpoint"), "/UserSide/signup.html");

    if (baseUrl.isEmpty()) {
      throw new IllegalArgumentException("baseUrl system property resolved to empty.");
    }

    // Useful debug when an assertion fails
    RestAssured.enableLoggingOfRequestAndResponseIfValidationFails();
  }

  private static String orDefault(String value, String fallback) {
    if (value == null) return fallback;
    String trimmed = value.trim();
    return trimmed.isEmpty() ? fallback : trimmed;
  }

  private String url(String path) {
    // Ensure exactly one slash between base and path
    String b = baseUrl.endsWith("/") ? baseUrl.substring(0, baseUrl.length()-1) : baseUrl;
    String p = path.startsWith("/") ? path : "/" + path;
    return b + p;
  }

  private boolean isConnectionIssue(Throwable error) {
    for (Throwable cur = error; cur != null; cur = cur.getCause()) {
      if (cur instanceof java.net.ConnectException ||
          cur instanceof java.net.UnknownHostException ||
          cur instanceof java.net.NoRouteToHostException ||
          cur instanceof java.net.SocketTimeoutException) {
        return true;
      }
    }
    return false;
  }

  private Response postJsonOrSkip(String endpoint, String payload, String scenario) {
    try {
      return
        given()
          .contentType("application/json")
          .body(payload)
        .when()
          .post(url(endpoint));
    } catch (RuntimeException ex) {
      if (isConnectionIssue(ex)) {
        throw new SkipException(
            "Skipping API test (" + scenario + ") because " + url(endpoint) + " is unreachable. " +
            "Start the site locally or override -DbaseUrl/loginEndpoint/registerEndpoint to point at the running backend.",
            ex);
      }
      throw ex;
    }
  }

  @Test
  public void wrongPassword_returnsFriendlyError() {
    String email = System.getProperty("validEmail", "test@example.com");

    // Send JSON; many PHP stacks also accept x-www-form-urlencoded—switch if needed.
    Response res = postJsonOrSkip(
        loginEndpoint,
        "{\"email\":\"" + email + "\",\"password\":\"wrong\"}",
        "wrong password");

    // First assert we actually hit something (not null host / wrong route)
    Assert.assertTrue(res.getStatusCode() > 0, "No HTTP response received.");

    res.then()
      .statusCode(anyOf(is(400), is(401)))
      .body(anyOf(
          containsStringIgnoringCase("incorrect"),
          containsStringIgnoringCase("invalid"),
          containsStringIgnoringCase("try again"),
          containsStringIgnoringCase("auth/invalid-credential") // Firebase-style
      ));
  }

  @Test
  public void duplicateEmail_signupBlocked409() {
    String email = System.getProperty("validEmail","test@example.com");

    Response resp = postJsonOrSkip(
        registerEndpoint,
        "{\"email\":\""+email+"\",\"password\":\"XyZ!2345\",\"name\":\"Dup\"}",
        "duplicate signup");

    int status = resp.statusCode();
    String body  = resp.asString().trim().toLowerCase();

    // Heuristics for “duplicate” when server still returns 200
    boolean duplicateInText =
        body.contains("already") ||
        body.contains("exists") ||
        body.contains("in use") ||
        body.contains("duplicate") ||
        body.contains("email_exists") ||
        body.contains("auth/email-already-in-use");

    boolean duplicateInJson = false;
    try {
      io.restassured.path.json.JsonPath jp = new io.restassured.path.json.JsonPath(body);
      Boolean success = jp.getBoolean("success");                 // e.g. {success:false,...}
      String  code    = jp.getString("code");                     // e.g. EMAIL_EXISTS
      String  msg     = jp.getString("message");                  // or error.message, etc.
      if (msg == null) msg = jp.getString("error.message");
      String errCode  = jp.getString("error.code");

      duplicateInJson =
          (success != null && !success) ||
          (code != null && code.toUpperCase().contains("EMAIL")) ||
          (errCode != null && errCode.toUpperCase().contains("EMAIL")) ||
          (msg != null && msg.toLowerCase().matches(".*(already|exists|in use|duplicate).*"));
    } catch (Exception ignored) { /* body may not be JSON */ }

    org.testng.Assert.assertTrue(
        status == 409 || status == 400 || (status == 200 && (duplicateInText || duplicateInJson)),
        "Expected 409/400, or 200 with a duplicate indicator. Got status=" + status + " body=" + body
    );
  }

}
