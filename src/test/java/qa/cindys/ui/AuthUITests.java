package qa.cindys.ui;

import org.openqa.selenium.*;
import org.openqa.selenium.support.ui.ExpectedConditions;
import org.openqa.selenium.support.ui.WebDriverWait;
import org.testng.Assert;
import org.testng.SkipException;
import org.testng.annotations.Test;

import java.time.Duration;

public class AuthUITests extends BaseUi {

  private WebDriverWait uiWait(long seconds) {
    return new WebDriverWait(driver, Duration.ofSeconds(seconds));
  }

  private void openLoginPage() {
    try {
      driver.get(loginUrl());
    } catch (WebDriverException ex) {
      if (isConnectionRefused(ex)) {
        throw new SkipException("Skipping UI test because login page is unreachable: " + loginUrl(), ex);
      }
      throw ex;
    }
    waitForDocumentReady(Duration.ofSeconds(12));
    uiWait(12).until(ExpectedConditions.visibilityOfElementLocated(email()));
  }

  // Match your actual DOM (id="email", id="password", form button[type=submit], #errorMessage, etc.)
  private By email()       { return By.cssSelector("#email, [data-testid='email']"); }
  private By password()    { return By.cssSelector("#password, [data-testid='password']"); }
  private By signInBtn()   { return By.cssSelector("form button[type='submit'], [data-testid='sign-in']"); }
  private By errorBanner() { return By.cssSelector("#errorMessage, [data-testid='error'], .error-message"); }
  private By successBanner() { return By.cssSelector("#successMessage, [data-testid='success'], .success-message"); }

  // Forgot Password modal pieces present in your login.html
  private By forgotLink()     { return By.cssSelector("#forgotPasswordLink, a[href*='forgot']"); }
  private By forgotModal()    { return By.id("forgotPasswordModal"); }
  private By forgotEmail()    { return By.id("forgotEmail"); }
  private By sendResetCode()  { return By.id("sendResetCode"); }
  private By forgotFeedback() { return By.id("forgotFeedback"); }

  // Optional: if your menu has auth links block
  // private By authLinks()     { return By.cssSelector(".auth-links,[data-testid='auth-links']"); }

  /** 1) Missing credentials shows an error (custom banner OR native HTML5 bubble) */
  @Test(priority = 1)
  public void missingCredentials_validationShown() {
    openLoginPage();

    WebElement email = driver.findElement(email());
    WebElement pw    = driver.findElement(password());
    WebElement btn   = driver.findElement(signInBtn());

    // Make sure fields are empty
    try { email.clear(); } catch (Exception ignored) {}
    try { pw.clear(); }    catch (Exception ignored) {}

    btn.click();

    // Wait until either: custom error banner is visible OR form is invalid per HTML5 constraints
    Boolean surfaced = uiWait(10).until(d -> {
      // 1) custom banner?
      try {
        WebElement b = d.findElement(errorBanner());
        if (b.isDisplayed()) return true;
      } catch (NoSuchElementException ignored) {}

      // 2) native HTML5 validation?
      try {
        JavascriptExecutor js = (JavascriptExecutor) d;
        Boolean formValid = (Boolean) js.executeScript("return arguments[0].form.checkValidity();", email);
        Boolean emailMissing = (Boolean) js.executeScript("return arguments[0].validity.valueMissing;", email);
        Boolean pwMissing    = (Boolean) js.executeScript("return arguments[0].validity.valueMissing;", pw);
        if (Boolean.FALSE.equals(formValid) && (Boolean.TRUE.equals(emailMissing) || Boolean.TRUE.equals(pwMissing))) {
          // Ask browser to show its tooltip (not assertable visually, but message is readable)
          js.executeScript("arguments[0].reportValidity(); arguments[1].reportValidity();", email, pw);
          return true;
        }
      } catch (Exception ignored) {}

      return false;
    });

    Assert.assertTrue(Boolean.TRUE.equals(surfaced), "Expected validation to surface somehow.");

    // Capture native messages if present (may be empty depending on browser/locale)
    JavascriptExecutor js = (JavascriptExecutor) driver;
    String emailMsg = String.valueOf(js.executeScript("return arguments[0].validationMessage || '';", email)).trim();
    String pwMsg    = String.valueOf(js.executeScript("return arguments[0].validationMessage || '';", pw)).trim();

    boolean bannerVisible = !driver.findElements(errorBanner()).isEmpty()
                            && driver.findElement(errorBanner()).isDisplayed();
    boolean nativeShown = !emailMsg.isEmpty() || !pwMsg.isEmpty();

    Assert.assertTrue(bannerVisible || nativeShown,
        "Expected custom error banner or native validation message. " +
        "emailMsg='" + emailMsg + "', pwMsg='" + pwMsg + "'.");
  }


  /** 2) Wrong password surfaces a friendly error (we accept any Firebase-ish message) */
  @Test(priority = 2)
  public void wrongPassword_showsFriendlyError() {
    openLoginPage();
    driver.findElement(email()).sendKeys(VALID_EMAIL);
    driver.findElement(password()).sendKeys("WrongPass!23");
    driver.findElement(signInBtn()).click();

    String msg = uiWait(12).until(ExpectedConditions.visibilityOfElementLocated(errorBanner())).getText().toLowerCase();
    Assert.assertTrue(msg.contains("invalid") || msg.contains("wrong") || msg.contains("firebase"),
        "Expected friendly wrong-password / invalid-credential message, got: " + msg);
  }

  /** 3) Successful login leaves the login page (and ideally hits MENU) */
  @Test(priority = 3)
  public void loginSuccess_redirectsToMenu() {
    openLoginPage();
    WebElement emailInput = driver.findElement(email());
    WebElement pwInput = driver.findElement(password());
    WebElement btn = driver.findElement(signInBtn());

    emailInput.clear(); emailInput.sendKeys(VALID_EMAIL);
    pwInput.clear();    pwInput.sendKeys(VALID_PASSWORD);
    btn.click();

    // Wait until URL no longer looks like login
    uiWait(20).until(ExpectedConditions.not(ExpectedConditions.urlContains("login")));
    String cur = driver.getCurrentUrl();
    Assert.assertTrue(cur.contains("/PRODUCT/MENU.php") || !cur.toLowerCase().contains("login"),
        "Expected to land on menu or at least leave login; current: " + cur);
  }

  /** 5) Forgot password flow — modal appears and we see feedback after sending */
  @Test(priority = 5)
  public void resetPassword_sendsEmail_orShowsFeedback() {
    openLoginPage();
    driver.findElement(forgotLink()).click();

    // Modal should appear
    uiWait(10).until(ExpectedConditions.visibilityOfElementLocated(forgotModal()));

    // Fill email and click "Send Reset Code"
    WebElement input = driver.findElement(forgotEmail());
    input.clear();
    input.sendKeys(VALID_EMAIL);
    driver.findElement(sendResetCode()).click();

    boolean surfaced = uiWait(15).until(d ->
        hasVisibleText(forgotFeedback()) ||
        hasVisibleText(successBanner()) ||
        hasVisibleText(errorBanner()));

    Assert.assertTrue(surfaced, "Expected some feedback after attempting password reset");

    String modalFeedback = textIfAny(forgotFeedback());
    String toastSuccess  = textIfAny(successBanner());
    String toastError    = textIfAny(errorBanner());

    Assert.assertTrue(!modalFeedback.isEmpty() || !toastSuccess.isEmpty() || !toastError.isEmpty(),
        String.format("Expected feedback text. modalFeedback='%s', toastSuccess='%s', toastError='%s'",
            modalFeedback, toastSuccess, toastError));
  }

  @Test(priority = 8)
  public void authLinksHiddenAfterLogin_profileShown() {
    // 1) Go to login and sign in
    openLoginPage();

    driver.findElement(email()).clear();
    driver.findElement(email()).sendKeys(VALID_EMAIL);
    driver.findElement(password()).clear();
    driver.findElement(password()).sendKeys(VALID_PASSWORD);
    driver.findElement(signInBtn()).click();

    // 2) Wait to leave login (SPA or redirect). If we know the menu URL, prefer it.
    final String base   = System.getProperty("baseUrl", "http://localhost:3000");
    final String menuPg = System.getProperty("menuPage", "/UserSide/PRODUCT/MENU.php");
    final String menuUrl = base + menuPg;

    uiWait(15).until(d ->
        !d.getCurrentUrl().toLowerCase().contains("login") ||
         d.getCurrentUrl().contains(menuPg));

    // If we didn't land on menu but are authenticated, navigate there explicitly.
    if (!driver.getCurrentUrl().contains(menuPg)) {
      driver.navigate().to(menuUrl);
    }
    uiWait(10).until(d -> ((JavascriptExecutor) d)
        .executeScript("return document.readyState").equals("complete"));

    // 3) Define candidates
    By[] authLinkCandidates = new By[] {
        By.cssSelector(".auth-links"),
        By.cssSelector("a[href*='login']"),
        By.cssSelector("a[href*='signup'], a[href*='register']"),
        By.xpath("//a[contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'sign in')]"),
        By.xpath("//a[contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'login')]"),
        By.xpath("//a[contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'create') and contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'account')]")
    };

    By[] profileCandidates = new By[] {
        By.cssSelector("[data-testid='profile'], [data-testid='user'], .profile, .user, .dropdown-toggle, [id*='profile'], [class*='profile']"),
        By.cssSelector("[data-testid='logout'], a[href*='logout'], button[href*='logout']"),
        By.xpath("//a[contains(.,'Logout') or contains(.,'Log out')]"),
        By.xpath("//a[contains(.,'Profile') or contains(.,'Account')]")
    };

    // 4) Wait until either we see a profile/logout OR the auth links disappear
    uiWait(10).until(d -> isAnyVisible(profileCandidates) || !isAnyVisible(authLinkCandidates));

    // 5) Assertions: auth links hidden, profile present
    Assert.assertFalse(isAnyVisible(authLinkCandidates), "Auth links should be hidden after login.");
    Assert.assertTrue(isAnyVisible(profileCandidates), "Profile / Logout should be visible after login.");
  }

  /* --- helpers (drop these anywhere in the class) --- */
  private boolean isConnectionRefused(Throwable error) {
    Throwable current = error;
    while (current != null) {
      String msg = current.getMessage();
      if (msg != null && msg.toLowerCase().contains("err_connection_refused")) {
        return true;
      }
      current = current.getCause();
    }
    return false;
  }

  private boolean isAnyVisible(By... selectors) {
    for (By by : selectors) {
      for (WebElement el : driver.findElements(by)) {
        try {
          if (el.isDisplayed() && el.getSize().getHeight() > 0 && el.getSize().getWidth() > 0) {
            // Also check computed style 'display' via JS when possible
            String display = (String) ((JavascriptExecutor) driver).executeScript(
                "return window.getComputedStyle(arguments[0]).display;", el);
            if (!"none".equalsIgnoreCase(display)) return true;
          }
        } catch (StaleElementReferenceException ignored) {}
      }
    }
    return false;
  }

  private boolean hasVisibleText(By locator) {
    for (WebElement el : driver.findElements(locator)) {
      try {
        if (el.isDisplayed() && !el.getText().trim().isEmpty()) {
          return true;
        }
      } catch (StaleElementReferenceException ignored) {}
    }
    return false;
  }

  private String textIfAny(By locator) {
    for (WebElement el : driver.findElements(locator)) {
      try {
        if (el.isDisplayed()) {
          return el.getText().trim();
        }
      } catch (StaleElementReferenceException ignored) {}
    }
    return "";
  }
}
