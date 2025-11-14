import '../firebase-init.js';
import { getAuth, onAuthStateChanged } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';

const header = document.getElementById('mainHeader');
if (header) {
  const navToggle = header.querySelector('#navToggle');
  const navContainer = header.querySelector('nav');
  const navLinks = header.querySelectorAll('#mainNav a');
  const profileToggle = header.querySelector('#profileToggle');
  const profileDropdown = header.querySelector('.profile-dropdown');
  const authLinks = header.querySelector('#authLinks');
  const profileAvatar = header.querySelector('#profileAvatar');
  const profileName = header.querySelector('#profileName');
  const profileEmail = header.querySelector('#profileEmail');
  const cartBadge = header.querySelector('#cartBadge');
  const defaultAvatar = header.dataset.imagesBase ? `${header.dataset.imagesBase}logo.png` : '';
  const apiBase = header.dataset.apiBase || '';
  const userPrefix = header.dataset.userPrefix || '';

  if (navToggle && navContainer) {
    navToggle.addEventListener('click', () => {
      const isOpen = navContainer.classList.toggle('active');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    const closeNav = () => {
      if (navContainer.classList.contains('active')) {
        navContainer.classList.remove('active');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    };

    navLinks.forEach(link => {
      link.addEventListener('click', closeNav);
    });

    // Close nav when auth links are clicked
    if (authLinks) {
      const authLinkElements = authLinks.querySelectorAll('.auth-link');
      authLinkElements.forEach(link => {
        link.addEventListener('click', closeNav);
      });
    }
  }

  if (profileToggle && profileDropdown) {
    profileToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      const isOpen = profileDropdown.classList.toggle('show');
      profileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
      if (!profileDropdown.contains(event.target)) {
        profileDropdown.classList.remove('show');
        profileToggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  window.addEventListener('scroll', () => {
    if (window.scrollY > 24) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  });

  // Update cart count
  const updateCartCount = async () => {
    try {
      const response = await fetch(`${apiBase}cart_api.php?action=get_count`);
      if (response.ok) {
        const data = await response.json();
        const count = parseInt(data.count) || 0;
        if (cartBadge) {
          if (count > 0) {
            cartBadge.textContent = count > 99 ? '99+' : count;
            cartBadge.style.display = 'flex';
          } else {
            cartBadge.style.display = 'none';
          }
        }
      }
    } catch (error) {
      console.error('Failed to fetch cart count', error);
    }
  };

  // Update cart count on page load
  updateCartCount();

  // Update cart count periodically (every 30 seconds)
  setInterval(updateCartCount, 30000);

  // Listen for custom cart update events
  window.addEventListener('cartUpdated', updateCartCount);

  const updateAuthVisibility = (isAuthenticated) => {
    if (authLinks) {
      authLinks.classList.toggle('hidden', isAuthenticated);
    }
    if (profileDropdown) {
      profileDropdown.classList.toggle('hidden', !isAuthenticated);
      if (!isAuthenticated) {
        profileDropdown.classList.remove('show');
      }
    }
    if (profileToggle) {
      profileToggle.disabled = !isAuthenticated;
      profileToggle.setAttribute('aria-expanded', 'false');
      profileToggle.setAttribute('tabindex', isAuthenticated ? '0' : '-1');
      if (isAuthenticated) {
        profileToggle.removeAttribute('aria-hidden');
      } else {
        profileToggle.setAttribute('aria-hidden', 'true');
      }
    }
  };

  updateAuthVisibility(false);

  const auth = getAuth();
  onAuthStateChanged(auth, async (user) => {
    if (!profileAvatar || !profileName || !profileEmail) {
      return;
    }

    if (user) {
      updateAuthVisibility(true);
      const email = user.email || '';
      profileEmail.textContent = email;
      profileName.textContent = user.displayName || 'Customer';

      try {
        const response = await fetch(`${apiBase}user_api.php?action=get_profile&email=${encodeURIComponent(email)}`);
        if (response.ok) {
          const data = await response.json();
          if (data.first_name || data.last_name) {
            const first = data.first_name ? data.first_name.trim() : '';
            const last = data.last_name ? data.last_name.trim() : '';
            const combined = `${first} ${last}`.trim();
            if (combined) {
              profileName.textContent = combined;
            }
          }
          if (data.face_image_path) {
            profileAvatar.src = data.face_image_path;
          } else if (defaultAvatar) {
            profileAvatar.src = defaultAvatar;
          }
        } else if (defaultAvatar) {
          profileAvatar.src = defaultAvatar;
        }
      } catch (error) {
        console.error('Failed to fetch profile info', error);
        if (defaultAvatar) {
          profileAvatar.src = defaultAvatar;
        }
      }
    } else {
      updateAuthVisibility(false);
      profileName.textContent = 'Guest';
      profileEmail.textContent = 'Sign in';
      if (defaultAvatar) {
        profileAvatar.src = defaultAvatar;
      }
    }
  });
}
