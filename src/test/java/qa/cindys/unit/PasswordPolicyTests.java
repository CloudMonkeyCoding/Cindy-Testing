package qa.cindys.unit;

import org.testng.Assert;
import org.testng.annotations.Test;

public class PasswordPolicyTests {

  private boolean isStrong(String pw){
    return pw != null &&
           pw.length() >= 8 &&
           pw.matches(".*[a-z].*") &&
           pw.matches(".*[A-Z].*") &&
           pw.matches(".*\\d.*") &&
           pw.matches(".*[^A-Za-z0-9].*");
  }

  @Test
  public void rejectsWeak() {
    Assert.assertFalse(isStrong("abc12"));
    Assert.assertFalse(isStrong("lowercaseonly"));
  }

  @Test
  public void acceptsStrong() {
    Assert.assertTrue(isStrong("ValidPass!23"));
  }
}
