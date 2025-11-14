package qa.cindys.ui;

import org.openqa.selenium.By;
import org.openqa.selenium.JavascriptExecutor;
import org.openqa.selenium.Keys;
import org.openqa.selenium.NoSuchElementException;
import org.openqa.selenium.StaleElementReferenceException;
import org.openqa.selenium.WebDriverException;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.support.ui.ExpectedConditions;
import org.openqa.selenium.support.ui.WebDriverWait;
import org.testng.Assert;
import org.testng.SkipException;
import org.testng.annotations.Test;

import java.time.Duration;
import java.util.List;

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
  private By paginationControls() { return By.id("paginationControls"); }

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
    WebElement otherPill = null;
    for (WebElement pill : pills) {
      String category = pill.getAttribute("data-category");
      if (category != null && !category.equalsIgnoreCase("all")) {
        otherPill = pill;
        break;
      }
    }

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
}
