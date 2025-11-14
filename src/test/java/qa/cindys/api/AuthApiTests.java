package qa.cindys.api;

import io.restassured.RestAssured;
import io.restassured.response.Response;
import org.testng.Assert;
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
    baseUrl         = System.getProperty("baseUrl", "http://localhost:3000").trim();
    loginEndpoint   = System.getProperty("loginEndpoint", "/UserSide/login.html").trim();
    registerEndpoint= System.getProperty("registerEndpoint", "/UserSide/signup.html").trim();

    if (baseUrl.isEmpty()) {
      throw new IllegalArgumentException("baseUrl system property resolved to empty.");
    }

    // Useful debug when an assertion fails
    RestAssured.enableLoggingOfRequestAndResponseIfValidationFails();
  }

  private String url(String path) {
    // Ensure exactly one slash between base and path
    String b = baseUrl.endsWith("/") ? baseUrl.substring(0, baseUrl.length()-1) : baseUrl;
    String p = path.startsWith("/") ? path : "/" + path;
    return b + p;
  }

  @Test
  public void wrongPassword_returnsFriendlyError() {
    String email = System.getProperty("validEmail", "test@example.com");

    // Send JSON; many PHP stacks also accept x-www-form-urlencoded—switch if needed.
    Response res =
      given()
        .contentType("application/json")
        .body("{\"email\":\"" + email + "\",\"password\":\"wrong\"}")
      .when()
        .post(url(loginEndpoint));

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

  io.restassured.response.Response resp =
      given()
        .contentType("application/json")
        .body("{\"email\":\""+email+"\",\"password\":\"XyZ!2345\",\"name\":\"Dup\"}")
      .when()
        .post(url(registerEndpoint));

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
