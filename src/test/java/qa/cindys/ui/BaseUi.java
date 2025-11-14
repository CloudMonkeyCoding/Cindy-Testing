package qa.cindys.ui;

import io.github.bonigarcia.wdm.WebDriverManager;
import org.openqa.selenium.JavascriptExecutor;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.chrome.ChromeOptions;
import org.testng.annotations.AfterClass;
import org.testng.annotations.BeforeClass;
import org.openqa.selenium.support.ui.WebDriverWait;

import java.time.Duration;

public class BaseUi {
  protected WebDriver driver;

  // Treat blank/undefined -D props as "unset" so we can safely fallback to defaults.
  protected static String propOrDefault(String key, String dflt) {
    String v = System.getProperty(key);
    return (v == null || v.trim().isEmpty()) ? dflt : v.trim();
  }

  protected static boolean boolProp(String key, boolean dflt) {
    String raw = System.getProperty(key);
    if (raw == null || raw.trim().isEmpty()) return dflt;
    return Boolean.parseBoolean(raw.trim());
  }

  // Defaults (work even when Surefire injects empty properties)
  protected final String BASE_URL        = propOrDefault("baseUrl",  "http://localhost:3000");
  protected final String LOGIN_PATH      = propOrDefault("loginPage","/UserSide/login.html");
  protected final String MENU_PATH       = propOrDefault("menuPage", "/UserSide/PRODUCT/MENU.php");

  // Test data (override with -DvalidEmail=... -DvalidPassword=... if you want)
  protected final String VALID_EMAIL       = propOrDefault("validEmail", "ejquancey@gmail.com");
  protected final String VALID_PASSWORD    = propOrDefault("validPassword","pass-123");
  protected final String UNREGISTERED_EMAIL= propOrDefault("unregisteredEmail","nope@example.com");
  protected final boolean HEADLESS_MODE    = boolProp("headless", true);
  protected final String CHROME_BINARY     = propOrDefault("chromeBinary", "");
  protected final String EXTRA_CHROME_ARGS = propOrDefault("chromeArgs", "");

  protected String loginUrl() { return toAbsolute(LOGIN_PATH); }
  protected String menuUrl()  { return toAbsolute(MENU_PATH); }
  protected final String signup = System.getProperty("signupPage", "/UserSide/signup.html");
  protected String effectiveSignupUrl() { return BASE_URL + signup; }

  protected String toAbsolute(String maybePath) {
    if (maybePath == null || maybePath.isBlank()) return BASE_URL;
    String m = maybePath.trim();
    if (m.startsWith("http://") || m.startsWith("https://")) return m;
    // ensure single slash between base and path
    boolean baseEnds = BASE_URL.endsWith("/");
    boolean pathStarts = m.startsWith("/");
    String join = (baseEnds || pathStarts) ? "" : "/";
    String normalized = (baseEnds && pathStarts) ? m.substring(1) : m;
    return BASE_URL + join + normalized;
  }

  @BeforeClass
  public void setUp() {
    WebDriverManager.chromedriver().setup();
    ChromeOptions opts = new ChromeOptions();
    opts.setAcceptInsecureCerts(true);
    opts.addArguments("--disable-gpu");
    opts.addArguments("--no-sandbox");
    opts.addArguments("--disable-dev-shm-usage");
    opts.addArguments("--remote-allow-origins=*");
    opts.addArguments("--window-size=1440,900");

    if (!CHROME_BINARY.isBlank()) {
      opts.setBinary(CHROME_BINARY);
    }

    if (HEADLESS_MODE) {
      opts.addArguments("--headless=new");
    }

    if (!EXTRA_CHROME_ARGS.isBlank()) {
      for (String arg : EXTRA_CHROME_ARGS.split(",")) {
        String trimmed = arg.trim();
        if (!trimmed.isEmpty()) {
          opts.addArguments(trimmed);
        }
      }
    }

    driver = new ChromeDriver(opts);
    driver.manage().timeouts().implicitlyWait(Duration.ofSeconds(0)); // explicit waits in tests
    driver.manage().timeouts().pageLoadTimeout(Duration.ofSeconds(60));
    driver.manage().window().maximize();
  }

  @AfterClass(alwaysRun = true)
  public void tearDown() {
    if (driver != null) driver.quit();
  }

  protected void waitForDocumentReady(Duration timeout) {
    new WebDriverWait(driver, timeout).until(d ->
        ((JavascriptExecutor) d).executeScript("return document.readyState").equals("complete"));
  }
}
