package qa.cindys.ui;

import io.github.bonigarcia.wdm.WebDriverManager;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.chrome.ChromeOptions;
import org.testng.annotations.AfterClass;
import org.testng.annotations.BeforeClass;

import java.time.Duration;

public class BaseUi {
  protected WebDriver driver;

  // Treat blank/undefined -D props as "unset" so we can safely fallback to defaults.
  protected static String propOrDefault(String key, String dflt) {
    String v = System.getProperty(key);
    return (v == null || v.trim().isEmpty()) ? dflt : v.trim();
  }

  // Defaults (work even when Surefire injects empty properties)
  protected final String BASE_URL        = propOrDefault("baseUrl",  "http://localhost:3000");
  protected final String LOGIN_PATH      = propOrDefault("loginPage","/UserSide/login.html");
  protected final String MENU_PATH       = propOrDefault("menuPage", "/UserSide/PRODUCT/MENU.php");

  // Test data (override with -DvalidEmail=... -DvalidPassword=... if you want)
  protected final String VALID_EMAIL       = propOrDefault("validEmail", "ejquancey@gmail.com");
  protected final String VALID_PASSWORD    = propOrDefault("validPassword","pass-123");
  protected final String UNREGISTERED_EMAIL= propOrDefault("unregisteredEmail","nope@example.com");

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
    // Keep it standard; if you need headed/ headless flips, change here.
    driver = new ChromeDriver(opts);
    driver.manage().timeouts().implicitlyWait(Duration.ofSeconds(0)); // explicit waits in tests
    driver.manage().timeouts().pageLoadTimeout(Duration.ofSeconds(60));
    driver.manage().window().maximize();
  }

  @AfterClass(alwaysRun = true)
  public void tearDown() {
    if (driver != null) driver.quit();
  }
}
