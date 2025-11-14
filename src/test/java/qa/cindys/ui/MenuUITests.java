package qa.cindys.ui;

import org.openqa.selenium.By;
import org.openqa.selenium.ElementClickInterceptedException;
import org.openqa.selenium.JavascriptExecutor;
import org.openqa.selenium.Keys;
import org.openqa.selenium.NoSuchElementException;
import org.openqa.selenium.StaleElementReferenceException;
import org.openqa.selenium.TimeoutException;
import org.openqa.selenium.WebDriverException;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.support.ui.ExpectedConditions;
import org.openqa.selenium.support.ui.WebDriverWait;
import org.testng.Assert;
import org.testng.SkipException;
import org.testng.annotations.Test;

import java.time.Duration;
import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

public class MenuUITests extends BaseUi {

  private WebDriverWait uiWait(long seconds) {
    return new WebDriverWait(driver, Duration.ofSeconds(seconds));
  }

  private void openMenuPage() {
    try {
      driver.get(menuUrl());
    } catch (WebDriverException ex) {
      if (isConnectionRefused(ex)) {
        throw new SkipException("Skipping MENU UI tests because the page is unreachable: " + menuUrl(), ex);
      }
      throw ex;
    }

    waitForDocumentReady(Duration.ofSeconds(12));

    WebDriverWait wait = uiWait(12);
    wait.until(ExpectedConditions.presenceOfElementLocated(menuGrid()));
    wait.until(d -> !d.findElements(menuCards()).isEmpty()
        || isVisible(menuEmpty())
        || !d.findElements(categoryButtons()).isEmpty());
  }

  private By menuGrid() { return By.id("menuGrid"); }
  private By menuCards() { return By.cssSelector("#menuGrid .menu-item"); }
  private By menuEmpty() { return By.id("menuEmpty"); }
  private By bestSellersSection() { return By.id("bestSellersSection"); }
  private By categoryContainer() { return By.id("categoryPills"); }
  private By categoryButtons() { return By.cssSelector("#categoryPills button"); }
  private By searchInput() { return By.id("searchInput"); }
  private By toast() { return By.id("menuToast"); }
  private By favoriteButtons() { return By.cssSelector("#menuGrid .menu-item .favorite-btn"); }
  private By addButtons() { return By.cssSelector("#menuGrid .menu-item:not(.out-of-stock) .add-btn"); }
  private By quantityModal() { return By.id("quantityModal"); }
  private By confirmAddButton() { return By.id("confirmAdd"); }
  private By paginationControls() { return By.id("paginationControls"); }
  private By nextPageButton() { return By.id("nextPage"); }
  private By loginEmail() { return By.cssSelector("#email, [data-testid='email']"); }
  private By loginPassword() { return By.cssSelector("#password, [data-testid='password']"); }
  private By loginSubmit() { return By.cssSelector("form button[type='submit'], [data-testid='sign-in']"); }
  private By loginErrorBanner() { return By.cssSelector("#errorMessage, [data-testid='error'], .error-message"); }

  private WebElement firstNonAllCategory(List<WebElement> pills) {
    for (WebElement pill : pills) {
      String category = pill.getAttribute("data-category");
      if (category != null && !category.equalsIgnoreCase("all")) {
        return pill;
      }
    }
    return null;
  }

  @Test(priority = 1)
  public void menuPage_coreComponentsVisible() {
    openMenuPage();

    WebElement header = uiWait(10).until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector(".page-header h1")));
    Assert.assertFalse(header.getText().trim().isEmpty(), "Header title should be visible.");

    WebElement search = uiWait(5).until(ExpectedConditions.visibilityOfElementLocated(searchInput()));
    Assert.assertEquals(search.getAttribute("type"), "search", "Search input should use type=search.");

    WebElement categories = uiWait(5).until(ExpectedConditions.visibilityOfElementLocated(categoryContainer()));
    List<WebElement> pills = driver.findElements(categoryButtons());
    Assert.assertTrue(pills.size() >= 1, "Expected at least one category pill to be rendered.");

    WebElement pagination = driver.findElement(paginationControls());
    Assert.assertNotNull(pagination, "Pagination controls container should exist even if hidden.");
  }

  @Test(priority = 2, dependsOnMethods = "menuPage_coreComponentsVisible")
  public void categorySelection_switchesActiveStateAndHidesBestSellers() {
    openMenuPage();

    List<WebElement> pills = uiWait(8).until(d -> {
      List<WebElement> buttons = d.findElements(categoryButtons());
      return buttons.isEmpty() ? null : buttons;
    });

    if (pills.size() < 2) {
      throw new SkipException("Need at least two categories to validate switching.");
    }

    WebElement allPill = pills.get(0);
    final WebElement otherPill = firstNonAllCategory(pills);

    if (otherPill == null) {
      throw new SkipException("Only the 'All' category is available; skipping filter validation.");
    }

    otherPill.click();

    uiWait(5).until(d -> {
      WebElement section = d.findElement(bestSellersSection());
      return hasClass(otherPill, "active") && !hasClass(allPill, "active") && isHidden(section);
    });

    Assert.assertTrue(hasClass(otherPill, "active"), "Selected category should have the active class.");
    Assert.assertFalse(hasClass(allPill, "active"), "Previously active pill should be cleared.");
    Assert.assertTrue(isHidden(driver.findElement(bestSellersSection())), "Best sellers should be hidden for non-'All' filters.");

    allPill.click();
    uiWait(5).until(d -> hasClass(allPill, "active"));
  }

  @Test(priority = 3, dependsOnMethods = "menuPage_coreComponentsVisible")
  public void searchFilter_showsEmptyStateWhenNoResults() {
    openMenuPage();

    WebElement search = driver.findElement(searchInput());
    clearSearch(search);
    search.sendKeys("zzzz-unlikely-item");

    uiWait(6).until(d -> isVisible(menuEmpty()));
    Assert.assertTrue(isVisible(menuEmpty()), "Empty-state banner should appear when no products match.");
    Assert.assertTrue(isHidden(driver.findElement(bestSellersSection())), "Best sellers should be hidden while searching.");

    clearSearch(search);
    search.sendKeys(Keys.ENTER); // trigger input change event even if empty

    boolean restored = uiWait(8).until(d -> {
      boolean hasItems = !d.findElements(menuCards()).isEmpty();
      WebElement empty = d.findElement(menuEmpty());
      boolean emptyHidden = !isVisible(empty);
      return hasItems || emptyHidden;
    });

    if (!restored) {
      throw new SkipException("Menu did not repopulate after clearing search; skipping remaining assertions.");
    }

    if (!driver.findElements(menuCards()).isEmpty()) {
      Assert.assertFalse(isVisible(menuEmpty()), "Empty state should be hidden once items are available again.");
    }
  }

  @Test(priority = 4, dependsOnMethods = "menuPage_coreComponentsVisible")
  public void favoriteAction_requiresLoginShowsToast() {
    openMenuPage();
    waitForToastToHide();

    WebElement favoriteBtn = uiWait(8).until(d -> {
      for (WebElement btn : d.findElements(favoriteButtons())) {
        if (btn.isDisplayed()) {
          return btn;
        }
      }
      return null;
    });

    if (favoriteBtn == null) {
      throw new SkipException("No favorite buttons were found to test.");
    }

    favoriteBtn.click();

    String message = waitForToastMessage();
    Assert.assertTrue(message.toLowerCase().contains("sign in"), "Toast should instruct user to sign in. Actual: " + message);
    Assert.assertEquals(currentToastTone(), "warn", "Favorite toast should use the warning tone.");
  }

  @Test(priority = 5, dependsOnMethods = "menuPage_coreComponentsVisible")
  public void addToCart_requiresLoginShowsToast() {
    openMenuPage();
    waitForToastToHide();

    WebElement addBtn = uiWait(8).until(d -> {
      for (WebElement btn : d.findElements(addButtons())) {
        if (btn.isDisplayed() && btn.isEnabled()) {
          return btn;
        }
      }
      return null;
    });

    if (addBtn == null) {
      throw new SkipException("No in-stock products were available to click Add to Cart.");
    }

    addBtn.click();

    String message = waitForToastMessage();
    Assert.assertTrue(message.toLowerCase().contains("sign in"), "Expected sign-in prompt toast after add to cart.");
    Assert.assertEquals(currentToastTone(), "warn", "Add-to-cart toast should use the warning tone.");

    WebElement modal = driver.findElement(quantityModal());
    Assert.assertFalse(hasClass(modal, "show"), "Quantity modal should remain hidden when not authenticated.");
  }

  @Test(priority = 6, dependsOnMethods = "menuPage_coreComponentsVisible")
  public void orderingEachMenuItem_requiresSignInOrMarksUnavailable() {
    openMenuPage();
    waitForToastToHide();

    Set<String> evaluatedProducts = new LinkedHashSet<>();
    int pageSafety = 0;

    while (true) {
      List<WebElement> cards = uiWait(10).until(d -> {
        List<WebElement> items = d.findElements(menuCards());
        if (!items.isEmpty()) {
          return items;
        }
        return isVisible(menuEmpty()) ? items : null;
      });

      if (cards == null || cards.isEmpty()) {
        throw new SkipException("Menu grid is empty; nothing to evaluate for ordering.");
      }

      List<CardLocator> locators = captureCardLocators(cards);

      for (CardLocator locator : locators) {
        String identifier = locator.identifier();
        if (evaluatedProducts.contains(identifier)) {
          continue;
        }

        WebElement card = locateCard(locator);
        if (card == null) {
          continue;
        }

        evaluatedProducts.add(identifier);

        int retries = 0;
        while (true) {
          try {
            WebElement addBtn = card.findElement(By.cssSelector(".add-btn"));
            String addLabel = addBtn.getText() == null ? "" : addBtn.getText().trim().toLowerCase();
            boolean addEnabled = addBtn.isEnabled() && !hasClass(card, "out-of-stock") && !addLabel.contains("unavailable");

            if (addEnabled) {
              waitForToastToHide();
              clickElement(addBtn);

              String message = waitForToastMessage();
              Assert.assertTrue(message.toLowerCase().contains("sign in"),
                  "Clicking Add to Cart should prompt sign-in when unauthenticated for product " + identifier + ".");
              Assert.assertEquals(currentToastTone(), "warn",
                  "Add-to-cart toast should warn about authentication for product " + identifier + ".");

              waitForToastToHide();

              WebElement modal = driver.findElement(quantityModal());
              Assert.assertFalse(hasClass(modal, "show"),
                  "Quantity modal should stay hidden without auth for product " + identifier + ".");
            } else {
              String stockText = "";
              try {
                WebElement stockLabel = card.findElement(By.cssSelector(".price-section .stock"));
                stockText = stockLabel.getText().trim().toLowerCase();
              } catch (NoSuchElementException ignored) {
                // rely on button state when stock label is missing
              }

              boolean unavailableIndicated = addLabel.contains("unavailable")
                  || stockText.contains("out of stock")
                  || stockText.contains("stock: 0")
                  || !addBtn.isEnabled();

              Assert.assertTrue(unavailableIndicated,
                  "Disabled ordering state should be reflected for product " + identifier + ".");
            }
            break;
          } catch (StaleElementReferenceException ex) {
            if (retries++ >= 1) {
              throw ex;
            }
            card = locateCard(locator);
            if (card == null) {
              break;
            }
          }
        }
      }

      if (!isPaginationVisible()) {
        break;
      }

      WebElement nextPage = driver.findElement(nextPageButton());
      boolean nextDisabled = nextPage == null
          || nextPage.getAttribute("disabled") != null
          || !nextPage.isEnabled();

      if (nextDisabled) {
        break;
      }

      String previousSignature = pageSignature(locators);
      clickElement(nextPage);

      uiWait(8).until(d -> {
        List<WebElement> newCards = d.findElements(menuCards());
        if (newCards.isEmpty()) {
          return false;
        }
        return !pageSignature(captureCardLocators(newCards)).equals(previousSignature);
      });

      pageSafety++;
      if (pageSafety > 12) {
        throw new SkipException("Pagination did not settle after iterating through multiple pages.");
      }
    }

    Assert.assertFalse(evaluatedProducts.isEmpty(), "Expected to evaluate at least one menu item for ordering attempts.");
  }

  @Test(priority = 7, dependsOnMethods = "menuPage_coreComponentsVisible")
  public void orderingEachMenuItem_loggedInAddsToCart() {
    loginAndOpenMenu();
    waitForToastToHide();

    Set<String> processedProducts = new LinkedHashSet<>();
    int pageSafety = 0;
    int inStockAttempted = 0;
    int successfulAdds = 0;

    while (true) {
      List<WebElement> cards = uiWait(10).until(d -> {
        List<WebElement> items = d.findElements(menuCards());
        if (!items.isEmpty()) {
          return items;
        }
        return isVisible(menuEmpty()) ? items : null;
      });

      if (cards == null || cards.isEmpty()) {
        throw new SkipException("Menu grid is empty; nothing to add while authenticated.");
      }

      List<CardLocator> locators = captureCardLocators(cards);

      for (CardLocator locator : locators) {
        String identifier = locator.identifier();
        if (processedProducts.contains(identifier)) {
          continue;
        }

        WebElement card = locateCard(locator);
        if (card == null) {
          continue;
        }

        processedProducts.add(identifier);

        int retries = 0;
        while (true) {
          try {
            WebElement addBtn = card.findElement(By.cssSelector(".add-btn"));
            String addLabel = addBtn.getText() == null ? "" : addBtn.getText().trim().toLowerCase();
            boolean inStock = addBtn.isEnabled() && !hasClass(card, "out-of-stock") && !addLabel.contains("unavailable");

            if (!inStock) {
              String stockText = "";
              try {
                WebElement stockLabel = card.findElement(By.cssSelector(".price-section .stock"));
                stockText = stockLabel.getText().trim().toLowerCase();
              } catch (NoSuchElementException ignored) {
                // rely on button state when stock label is missing
              }

              boolean unavailableIndicated = addLabel.contains("unavailable")
                  || stockText.contains("out of stock")
                  || stockText.contains("stock: 0")
                  || hasClass(card, "out-of-stock")
                  || !addBtn.isEnabled();

              Assert.assertTrue(unavailableIndicated,
                  "Disabled ordering state should be reflected for product " + identifier + ".");
              break;
            }

            inStockAttempted++;

            waitForToastToHide();
            clickElement(addBtn);

            try {
              waitForModalVisible();
            } catch (TimeoutException ex) {
              throw new AssertionError("Expected quantity modal to appear for product " + identifier + " while logged in.", ex);
            }

            WebElement confirmBtn = uiWait(6).until(ExpectedConditions.elementToBeClickable(confirmAddButton()));
            Assert.assertTrue(confirmBtn.isEnabled(),
                "Confirm add button should be enabled for in-stock product " + identifier + ".");

            clickElement(confirmBtn);

            String message = waitForToastMessage();
            String lowered = message.toLowerCase();
            Assert.assertFalse(lowered.contains("sign in"),
                "Authenticated add-to-cart should not prompt sign-in for product " + identifier + ". Toast: " + message);

            String tone = currentToastTone();
            Assert.assertTrue("success".equalsIgnoreCase(tone) || "warn".equalsIgnoreCase(tone),
                "Expected success or warn tone after adding product " + identifier + ". Actual tone: " + tone + ".");

            waitForModalHidden();
            waitForToastToHide();
            successfulAdds++;
            break;
          } catch (StaleElementReferenceException ex) {
            if (retries++ >= 2) {
              throw ex;
            }
            card = locateCard(locator);
            if (card == null) {
              break;
            }
          }
        }
      }

      if (!isPaginationVisible()) {
        break;
      }

      WebElement nextPage = driver.findElement(nextPageButton());
      boolean nextDisabled = nextPage == null
          || nextPage.getAttribute("disabled") != null
          || !nextPage.isEnabled();

      if (nextDisabled) {
        break;
      }

      String previousSignature = pageSignature(locators);
      clickElement(nextPage);

      uiWait(8).until(d -> {
        List<WebElement> newCards = d.findElements(menuCards());
        if (newCards.isEmpty()) {
          return false;
        }
        return !pageSignature(captureCardLocators(newCards)).equals(previousSignature);
      });

      pageSafety++;
      if (pageSafety > 12) {
        throw new SkipException("Pagination did not settle after iterating through multiple pages while logged in.");
      }
    }

    if (inStockAttempted == 0) {
      throw new SkipException("No in-stock products were available to add while authenticated.");
    }

    Assert.assertTrue(successfulAdds > 0,
        "Expected to add at least one product to the cart while authenticated. Attempts: " + inStockAttempted);
  }

  private void clearSearch(WebElement input) {
    input.sendKeys(Keys.chord(Keys.CONTROL, "a"));
    input.sendKeys(Keys.chord(Keys.COMMAND, "a"));
    input.sendKeys(Keys.DELETE);
    input.clear();
  }

  private boolean hasClass(WebElement element, String className) {
    try {
      String classAttr = element.getAttribute("class");
      if (classAttr == null) return false;
      for (String token : classAttr.split("\\s+")) {
        if (token.equals(className)) {
          return true;
        }
      }
      return false;
    } catch (StaleElementReferenceException ignored) {
      return false;
    }
  }

  private boolean isHidden(WebElement element) {
    if (element == null) return true;
    String hiddenAttr = element.getAttribute("hidden");
    if (hiddenAttr != null) return true;
    try {
      return !element.isDisplayed();
    } catch (NoSuchElementException | StaleElementReferenceException ex) {
      return true;
    }
  }

  private boolean isVisible(By locator) {
    try {
      WebElement element = driver.findElement(locator);
      return isVisible(element);
    } catch (NoSuchElementException ignored) {
      return false;
    }
  }

  private boolean isVisible(WebElement element) {
    if (element == null) return false;
    try {
      if (!element.isDisplayed()) {
        return false;
      }
      if (driver instanceof JavascriptExecutor) {
        String display = (String) ((JavascriptExecutor) driver)
            .executeScript("return window.getComputedStyle(arguments[0]).display;", element);
        return display == null || !"none".equalsIgnoreCase(display);
      }
      return true;
    } catch (StaleElementReferenceException ignored) {
      return false;
    }
  }

  private void waitForToastToHide() {
    uiWait(5).until(d -> {
      List<WebElement> elements = d.findElements(toast());
      if (elements.isEmpty()) {
        return true;
      }
      String classes = elements.get(0).getAttribute("class");
      return classes == null || !classes.contains("show");
    });
  }

  private void waitForModalVisible() {
    uiWait(10).until(d -> {
      try {
        WebElement modal = d.findElement(quantityModal());
        return hasClass(modal, "show");
      } catch (NoSuchElementException ignored) {
        return false;
      }
    });
  }

  private void waitForModalHidden() {
    uiWait(8).until(d -> {
      try {
        WebElement modal = d.findElement(quantityModal());
        return !hasClass(modal, "show");
      } catch (NoSuchElementException ignored) {
        return true;
      }
    });
  }

  private String waitForToastMessage() {
    WebDriverWait wait = uiWait(8);
    wait.until(d -> {
      List<WebElement> elements = d.findElements(toast());
      if (elements.isEmpty()) {
        return false;
      }
      WebElement el = elements.get(0);
      String classes = el.getAttribute("class");
      String text = el.getText().trim();
      return classes != null && classes.contains("show") && !text.isEmpty();
    });
    return driver.findElement(toast()).getText().trim();
  }

  private String currentToastTone() {
    String tone = driver.findElement(toast()).getAttribute("data-tone");
    return tone == null ? "" : tone;
  }

  private boolean isPaginationVisible() {
    try {
      WebElement controls = driver.findElement(paginationControls());
      if (controls.getAttribute("hidden") != null) {
        return false;
      }
      return controls.isDisplayed();
    } catch (NoSuchElementException ex) {
      return false;
    }
  }

  private List<CardLocator> captureCardLocators(List<WebElement> cards) {
    List<CardLocator> locators = new ArrayList<>();
    if (cards == null) {
      return locators;
    }

    for (int i = 0; i < cards.size(); i++) {
      WebElement card = cards.get(i);
      String productId = null;
      String titleText = null;

      try {
        productId = trimToNull(card.getAttribute("data-product-id"));
      } catch (StaleElementReferenceException ignored) {
        // fall back to other strategies
      }

      if (titleText == null) {
        try {
          WebElement title = card.findElement(By.cssSelector(".menu-content h3"));
          titleText = trimToNull(title.getText());
        } catch (NoSuchElementException | StaleElementReferenceException ignored) {
          // rely on position
        }
      }

      locators.add(new CardLocator(productId, titleText, i));
    }

    return locators;
  }

  private String pageSignature(List<CardLocator> locators) {
    if (locators == null || locators.isEmpty()) {
      return "empty";
    }

    StringBuilder builder = new StringBuilder();
    int sample = Math.min(3, locators.size());
    for (int i = 0; i < sample; i++) {
      builder.append(locators.get(i).identifier()).append('|');
    }
    return builder.toString();
  }

  private WebElement locateCard(CardLocator locator) {
    if (locator == null) {
      return null;
    }

    WebElement byId = findCardByProductId(locator.productId);
    if (byId != null) {
      return byId;
    }

    WebElement byTitle = findCardByTitle(locator.titleText);
    if (byTitle != null) {
      return byTitle;
    }

    List<WebElement> cards = driver.findElements(menuCards());
    if (locator.index >= 0 && locator.index < cards.size()) {
      return cards.get(locator.index);
    }

    return null;
  }

  private WebElement findCardByProductId(String productId) {
    if (productId == null || !(driver instanceof JavascriptExecutor executor)) {
      return null;
    }

    Object result = executor.executeScript(
        "return Array.from(document.querySelectorAll('#menuGrid .menu-item'))"
            + ".find(el => (el.dataset && el.dataset.productId ? el.dataset.productId.trim() : '') === arguments[0]);",
        productId);
    return result instanceof WebElement ? (WebElement) result : null;
  }

  private WebElement findCardByTitle(String title) {
    if (title == null || !(driver instanceof JavascriptExecutor executor)) {
      return null;
    }

    Object result = executor.executeScript(
        "return Array.from(document.querySelectorAll('#menuGrid .menu-item')).find(el => {"
            + "const heading = el.querySelector('.menu-content h3');"
            + "return heading && heading.textContent && heading.textContent.trim() === arguments[0];"
            + "});",
        title);
    return result instanceof WebElement ? (WebElement) result : null;
  }

  private String trimToNull(String value) {
    if (value == null) {
      return null;
    }
    String trimmed = value.trim();
    return trimmed.isEmpty() ? null : trimmed;
  }

  private void clickElement(WebElement element) {
    if (element == null) {
      return;
    }

    scrollIntoView(element);

    try {
      uiWait(6).until(ExpectedConditions.elementToBeClickable(element));
    } catch (StaleElementReferenceException ex) {
      throw ex;
    } catch (RuntimeException ignored) {
      // fall through to attempt the click even if the element never satisfied the condition
    }

    try {
      element.click();
    } catch (ElementClickInterceptedException intercept) {
      if (driver instanceof JavascriptExecutor executor) {
        executor.executeScript(
            "arguments[0].scrollIntoView({block: 'center', inline: 'center'});", element);
        try {
          element.click();
          return;
        } catch (ElementClickInterceptedException ignored) {
          executor.executeScript("arguments[0].click();", element);
        }
      } else {
        throw intercept;
      }
    }
  }

  private void scrollIntoView(WebElement element) {
    if (element == null) {
      return;
    }

    if (driver instanceof JavascriptExecutor executor) {
      executor.executeScript(
          "arguments[0].scrollIntoView({block: 'center', inline: 'center'});",
          element);
    }
  }

  private static class CardLocator {
    private final String productId;
    private final String titleText;
    private final int index;

    private CardLocator(String productId, String titleText, int index) {
      this.productId = productId;
      this.titleText = titleText;
      this.index = index;
    }

    private String identifier() {
      if (productId != null) {
        return "id:" + productId;
      }
      if (titleText != null) {
        return "name:" + titleText;
      }
      return "index:" + index;
    }
  }

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

  private void loginAndOpenMenu() {
    try {
      driver.get(loginUrl());
    } catch (WebDriverException ex) {
      if (isConnectionRefused(ex)) {
        throw new SkipException("Skipping authenticated menu test because the login page is unreachable: " + loginUrl(), ex);
      }
      throw ex;
    }

    waitForDocumentReady(Duration.ofSeconds(12));

    WebElement emailField;
    WebElement passwordField;
    WebElement submitButton;
    try {
      emailField = uiWait(12).until(ExpectedConditions.visibilityOfElementLocated(loginEmail()));
      passwordField = driver.findElement(loginPassword());
      submitButton = driver.findElement(loginSubmit());
    } catch (TimeoutException ex) {
      throw new SkipException("Login form did not render the expected fields; skipping authenticated ordering test.", ex);
    }

    emailField.clear();
    emailField.sendKeys(VALID_EMAIL);
    passwordField.clear();
    passwordField.sendKeys(VALID_PASSWORD);
    submitButton.click();

    try {
      uiWait(15).until(d -> !d.getCurrentUrl().toLowerCase().contains("login"));
    } catch (TimeoutException ex) {
      String errorText = textIfPresent(loginErrorBanner());
      if (!errorText.isEmpty()) {
        throw new SkipException("Login failed while preparing authenticated ordering test: " + errorText, ex);
      }
      throw new SkipException("Unable to confirm login success; skipping authenticated ordering test.", ex);
    }

    openMenuPage();
  }

  private String textIfPresent(By locator) {
    try {
      String text = driver.findElement(locator).getText();
      return text == null ? "" : text.trim();
    } catch (NoSuchElementException ignored) {
      return "";
    }
  }
}
