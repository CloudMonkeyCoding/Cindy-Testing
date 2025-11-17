package qa.cindys.api;

import io.restassured.RestAssured;
import io.restassured.response.Response;
import java.util.Map;
import org.testng.Assert;
import org.testng.SkipException;
import org.testng.annotations.BeforeClass;
import org.testng.annotations.Test;

import static io.restassured.RestAssured.given;

public class CartAndFavoriteApiTests {

  private String baseUrl;
  private String cartEndpoint;
  private String favoriteEndpoint;

  @BeforeClass
  public void setup() {
    baseUrl = orDefault(System.getProperty("baseUrl"), "http://localhost:3000");
    cartEndpoint = orDefault(System.getProperty("cartEndpoint"), "/PHP/cart_api.php");
    favoriteEndpoint = orDefault(System.getProperty("favoriteEndpoint"), "/PHP/favorite_api.php");

    if (baseUrl.isEmpty()) {
      throw new IllegalArgumentException("baseUrl system property resolved to empty.");
    }

    RestAssured.enableLoggingOfRequestAndResponseIfValidationFails();
  }

  private static String orDefault(String value, String fallback) {
    if (value == null) return fallback;
    String trimmed = value.trim();
    return trimmed.isEmpty() ? fallback : trimmed;
  }

  private String url(String path) {
    String b = baseUrl.endsWith("/") ? baseUrl.substring(0, baseUrl.length() - 1) : baseUrl;
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

  private Response getOrSkip(String endpoint, Map<String, ?> queryParams, String scenario) {
    try {
      return given().queryParams(queryParams).when().get(url(endpoint));
    } catch (RuntimeException ex) {
      if (isConnectionIssue(ex)) {
        throw new SkipException(
            "Skipping API test (" + scenario + ") because " + url(endpoint) + " is unreachable. " +
                "Start the site locally or override -DbaseUrl/cartEndpoint/favoriteEndpoint to point at the running backend.",
            ex);
      }
      throw ex;
    }
  }

  private Response postOrSkip(String endpoint, Map<String, ?> formParams, String scenario) {
    try {
      return given().formParams(formParams).when().post(url(endpoint));
    } catch (RuntimeException ex) {
      if (isConnectionIssue(ex)) {
        throw new SkipException(
            "Skipping API test (" + scenario + ") because " + url(endpoint) + " is unreachable. " +
                "Start the site locally or override -DbaseUrl/cartEndpoint/favoriteEndpoint to point at the running backend.",
            ex);
      }
      throw ex;
    }
  }

  @Test
  public void favoriteList_noUser_returnsEmptyCollection() {
    Response res = getOrSkip(favoriteEndpoint, Map.of("action", "list"), "favorite list without user");

    Assert.assertEquals(res.statusCode(), 200, "favorite_api.php?action=list should return HTTP 200");

    String body = res.asString().trim();
    Assert.assertTrue(body.equals("[]") || body.equals("{}") || body.isEmpty(),
        "Expected an empty collection when no user context is provided, got: " + body);
  }

  @Test
  public void favoriteInvalidAction_returnsBadRequest() {
    Response res = getOrSkip(favoriteEndpoint, Map.of("action", "unknown_action"), "favorite invalid action");

    Assert.assertEquals(res.statusCode(), 400, "Invalid action should yield HTTP 400");
    Assert.assertTrue(res.asString().toLowerCase().contains("invalid action"),
        "Response should mention invalid action");
  }

  @Test
  public void cartAdd_zeroQuantity_rejectedWith400() {
    Response res = postOrSkip(
        cartEndpoint,
        Map.of("action", "add", "quantity", 0),
        "cart add with zero quantity");

    Assert.assertEquals(res.statusCode(), 400, "Quantity validation should return HTTP 400");
    Assert.assertTrue(res.asString().toLowerCase().contains("quantity must be greater than zero"),
        "Response should explain quantity requirement");
  }
}
