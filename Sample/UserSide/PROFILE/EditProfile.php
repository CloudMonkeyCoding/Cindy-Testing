<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile - Cindy's Bakeshop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles.css" />
  <style>
    body.profile-view {
      display: flex;
      flex-direction: column;
    }

    .profile-hero {
      background: linear-gradient(135deg, rgba(139, 69, 19, 0.92), rgba(240, 165, 0, 0.85));
      border-radius: 32px;
      padding: clamp(2.5rem, 5vw, 4rem);
      color: #fff;
      margin-bottom: 3rem;
      box-shadow: 0 30px 60px rgba(139, 69, 19, 0.25);
    }

    .profile-hero .hero-content {
      display: flex;
      flex-wrap: wrap;
      gap: 2.5rem;
      align-items: center;
    }

    .avatar-wrapper {
      position: relative;
      width: 160px;
      height: 160px;
    }

    .avatar-wrapper img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 40px;
      border: 4px solid rgba(255, 255, 255, 0.4);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
    }

    .upload-badge {
      position: absolute;
      bottom: 12px;
      right: 12px;
      background: rgba(255, 255, 255, 0.85);
      color: var(--primary-brown);
      padding: 0.55rem 1.1rem;
      border-radius: var(--radius-pill);
      font-weight: 600;
      font-size: 0.85rem;
      box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
      cursor: pointer;
    }

    #profilePicInput {
      display: none;
    }

    .hero-text {
      flex: 1 1 260px;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .hero-text h1 {
      font-size: clamp(2rem, 4vw, 2.8rem);
      font-weight: 700;
      line-height: 1.2;
    }

    .hero-text p {
      font-size: 1.05rem;
      opacity: 0.85;
      max-width: 420px;
    }

    .hero-actions {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .profile-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 2.5rem;
    }

    .profile-card {
      background: rgba(255, 255, 255, 0.92);
      border-radius: 32px;
      padding: clamp(2rem, 3vw, 2.8rem);
      box-shadow: var(--shadow-soft);
      border: 1px solid rgba(139, 69, 19, 0.12);
      display: flex;
      flex-direction: column;
      gap: 1.8rem;
    }

    .profile-card h2 {
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--primary-brown);
    }

    .profile-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1.2rem;
    }

    .profile-stat {
      background: rgba(139, 69, 19, 0.08);
      border-radius: 20px;
      padding: 1rem 1.2rem;
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
    }

    .profile-stat span {
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .profile-stat strong {
      font-size: 1.4rem;
      color: var(--primary-brown);
    }

    .profile-form {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.2rem 1.5rem;
    }

    .profile-form label {
      display: block;
      font-weight: 600;
      color: var(--primary-brown);
      margin-bottom: 0.45rem;
    }

    .profile-form input {
      width: 100%;
      border-radius: 16px;
      border: 1px solid rgba(139, 69, 19, 0.15);
      padding: 0.75rem 1rem;
      font-size: 0.95rem;
      background: rgba(255, 255, 255, 0.9);
      font-family: inherit;
    }

    .form-actions {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-top: 1rem;
    }

    .form-actions button,
    .form-actions a {
      padding: 0.85rem 1.8rem;
      border-radius: var(--radius-pill);
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .form-actions button {
      background: linear-gradient(135deg, var(--primary-brown), var(--primary-brown-dark));
      color: #fff;
      border: none;
    }

    .form-actions a {
      background: rgba(139, 69, 19, 0.1);
      color: var(--primary-brown);
    }

    #serverMessage {
      font-weight: 600;
    }

    @media (max-width: 768px) {
      .profile-hero {
        padding: 2.4rem 1.8rem;
      }

      .avatar-wrapper {
        width: 120px;
        height: 120px;
      }
    }
  </style>
</head>
<body class="profile-view">
  <?php include __DIR__ . '/../topbar.php'; ?>
  <main class="page-container">
    <section class="profile-hero">
      <div class="hero-content">
        <div class="avatar-wrapper">
          <img id="profilePic" src="../../../Images/logo.png" alt="Profile picture" />
          <label for="profilePicInput" class="upload-badge">Update photo</label>
          <input type="file" id="profilePicInput" accept="image/*" />
        </div>
        <div class="hero-text">
          <span class="tag-pill">Cindy's community member</span>
          <h1 id="displayName">Welcome back!</h1>
          <p id="displayEmail">Sign in to sync your favorites and orders.</p>
          <div class="hero-actions">
            <a class="pill-button secondary" href="../PURCHASES/MyPurchase.php">View orders</a>
            <a class="pill-button secondary" href="../FAVORITE/my favorite.php">Favorite treats</a>
          </div>
        </div>
      </div>
    </section>

    <div class="profile-layout">
      <section class="profile-card">
        <h2>Personal information</h2>
        <div class="profile-grid">
          <div class="profile-stat">
            <span>Member name</span>
            <strong id="statName">—</strong>
          </div>
          <div class="profile-stat">
            <span>Email address</span>
            <strong id="statEmail">—</strong>
          </div>
          <div class="profile-stat">
            <span>Latest order</span>
            <strong id="statOrders">Keep exploring</strong>
          </div>
        </div>

        <form id="editProfileForm" class="profile-form">
          <div>
            <label for="firstName">First name</label>
            <input type="text" id="firstName" placeholder="First name" required />
          </div>
          <div>
            <label for="lastName">Last name</label>
            <input type="text" id="lastName" placeholder="Last name" required />
          </div>
          <div>
            <label for="email">Email</label>
            <input type="email" id="email" placeholder="Email" required readonly />
          </div>
          <div>
            <label for="addressStreet">Street</label>
            <input type="text" id="addressStreet" placeholder="Street" />
          </div>
          <div>
            <label for="addressBarangay">Barangay</label>
            <input type="text" id="addressBarangay" placeholder="Barangay" />
          </div>
          <div>
            <label for="addressCity">City / Municipality</label>
            <input type="text" id="addressCity" placeholder="City or municipality" />
          </div>
          <div>
            <label for="addressProvince">Province</label>
            <input type="text" id="addressProvince" placeholder="Province" />
          </div>
          <div class="form-actions" style="grid-column: 1 / -1;">
            <button type="submit">Save changes</button>
          </div>
          <div id="serverMessage" style="grid-column: 1 / -1;"></div>
        </form>
      </section>
    </div>
  </main>

  <script type="module">
    import { getAuth, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";
    import "../firebase-init.js";

    const auth = getAuth();
    const profilePic = document.getElementById('profilePic');
    const profilePicInput = document.getElementById('profilePicInput');
    const firstNameField = document.getElementById('firstName');
    const lastNameField = document.getElementById('lastName');
    const emailField = document.getElementById('email');
    const serverMessage = document.getElementById('serverMessage');
    const addressStreetField = document.getElementById('addressStreet');
    const addressBarangayField = document.getElementById('addressBarangay');
    const addressCityField = document.getElementById('addressCity');
    const addressProvinceField = document.getElementById('addressProvince');
    const displayName = document.getElementById('displayName');
    const displayEmail = document.getElementById('displayEmail');
    const statName = document.getElementById('statName');
    const statEmail = document.getElementById('statEmail');
    onAuthStateChanged(auth, user => {
      if (user) {
        emailField.value = user.email;
        displayEmail.textContent = user.email;
        statEmail.textContent = user.email;
        fetch(`../../PHP/user_api.php?action=get_profile&email=${encodeURIComponent(user.email)}`)
          .then(res => res.json())
          .then(data => {
            if (data.first_name) {
              firstNameField.value = data.first_name;
            }
            if (data.last_name) {
              lastNameField.value = data.last_name;
            }
            const fullName = `${data.first_name || ''} ${data.last_name || ''}`.trim();
            if (fullName) {
              displayName.textContent = `Hello, ${fullName}!`;
              statName.textContent = fullName;
            }
            if (data.face_image_path) {
              profilePic.src = data.face_image_path;
            }
            addressStreetField.value = data.address_street || '';
            addressBarangayField.value = data.address_barangay || '';
            addressCityField.value = data.address_city || '';
            addressProvinceField.value = data.address_province || '';
          });
      }
    });

    document.getElementById('editProfileForm').addEventListener('submit', function (e) {
      e.preventDefault();
      serverMessage.textContent = '';

      const profilePicFile = profilePicInput.files[0];
      if (profilePicFile && profilePicFile.size > 5 * 1024 * 1024) {
        serverMessage.textContent = 'Profile picture must be 5MB or less.';
        serverMessage.style.color = 'crimson';
        return;
      }

      const formData = new FormData();
      formData.append('first_name', firstNameField.value.trim());
      formData.append('last_name', lastNameField.value.trim());
      formData.append('email', emailField.value.trim());
      formData.append('address_street', addressStreetField.value.trim());
      formData.append('address_barangay', addressBarangayField.value.trim());
      formData.append('address_city', addressCityField.value.trim());
      formData.append('address_province', addressProvinceField.value.trim());
      if (profilePicFile) {
        formData.append('profile_picture', profilePicFile);
      }

      fetch('../../PHP/user_api.php?action=update_profile', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data.error) {
            serverMessage.textContent = data.error;
            serverMessage.style.color = 'crimson';
            return;
          }
          serverMessage.textContent = data.message || 'Profile updated successfully!';
          serverMessage.style.color = '#2d8659';
          if (data.face_image_path) {
            profilePic.src = data.face_image_path;
          }
          const fullName = `${firstNameField.value.trim()} ${lastNameField.value.trim()}`.trim();
          if (fullName) {
            displayName.textContent = `Hello, ${fullName}!`;
            statName.textContent = fullName;
          }
          profilePicInput.value = '';
        })
        .catch(() => {
          serverMessage.textContent = 'An error occurred while updating your profile.';
          serverMessage.style.color = 'crimson';
        });
    });
  </script>
</body>
</html>
