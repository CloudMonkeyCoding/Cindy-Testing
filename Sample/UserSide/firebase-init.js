import { initializeApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
import { getAuth, onAuthStateChanged, signOut } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";

const INACTIVITY_LIMIT = 15 * 60 * 1000; // 15 minutes
let inactivityTimer;

function resetInactivityTimer(auth) {
  clearTimeout(inactivityTimer);
  inactivityTimer = setTimeout(async () => {
    try {
      await signOut(auth);
    } catch (err) {
      console.error('Auto logout failed:', err);
    } finally {
      window.location.href = '../login.html';
    }
  }, INACTIVITY_LIMIT);
}

function startInactivityTimer(auth) {
  ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(event => {
    document.addEventListener(event, () => resetInactivityTimer(auth));
  });
  resetInactivityTimer(auth);
}

try {
  if (!getApps().length) {
    const configUrl = new URL('../../PHP/firebase_config.php', import.meta.url);
    const response = await fetch(configUrl, { credentials: 'same-origin' });
    if (!response.ok) {
      throw new Error(`Unable to load Firebase configuration: ${response.status}`);
    }
    initializeApp(await response.json());
  }
  const auth = getAuth();
  window.auth = auth;
  window.getAuth = getAuth;
  onAuthStateChanged(auth, user => {
    if (user) {
      console.log('Logged in as:', user.email);
      startInactivityTimer(auth);
    } else {
      console.log('Not logged in');
    }
  });
} catch (err) {
  console.error('Firebase init failed:', err);
}
