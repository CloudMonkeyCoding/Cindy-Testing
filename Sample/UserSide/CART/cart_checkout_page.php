<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cart & Checkout - Cindy's Bakeshop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles.css" />
  <style>
    body.checkout-view {
      display: flex;
      flex-direction: column;
    }

    .page-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 2rem;
      padding-top: 100px;
      width: 100%;
    }

    .checkout-hero {
      margin-bottom: 2rem;
      display: grid;
      gap: 1rem;
    }

    .checkout-hero h1 {
      font-size: clamp(2rem, 4vw, 2.8rem);
      font-weight: 700;
    }

    .checkout-hero p {
      color: var(--text-muted);
      max-width: 560px;
      font-size: 1rem;
    }

    .checkout-wrapper {
      display: grid;
      grid-template-columns: minmax(0, 2fr) minmax(0, 1.2fr);
      gap: 2rem;
      align-items: flex-start;
    }

    .glass-card {
      background: rgba(255, 255, 255, 0.92);
      border-radius: 28px;
      box-shadow: var(--shadow-soft);
      border: 1px solid rgba(139, 69, 19, 0.12);
      padding: 2rem;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }

    .card-header h2 {
      font-size: 1.6rem;
      font-weight: 700;
    }

    .delivery-area-note {
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-top: 0.4rem;
    }

    .cart-list {
      display: flex;
      flex-direction: column;
      gap: 1.2rem;
    }

    .cart-item {
      display: grid;
      grid-template-columns: auto 1fr auto;
      gap: 1rem;
      align-items: center;
      padding: 1.25rem;
      border-radius: 22px;
      background: rgba(255, 255, 255, 0.65);
      border: 1px solid rgba(139, 69, 19, 0.08);
    }

    .cart-item .item-check {
      width: 18px;
      height: 18px;
      accent-color: var(--primary-brown);
    }

    .cart-item img {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 20px;
      box-shadow: 0 12px 22px rgba(139, 69, 19, 0.15);
    }

    .cart-item-left {
      display: grid;
      grid-template-columns: auto 80px 1fr;
      gap: 1rem;
      align-items: center;
    }

    .item-details b {
      font-size: 1.1rem;
    }

    .item-details span {
      display: block;
      font-size: 0.9rem;
      color: var(--text-muted);
      margin-top: 0.25rem;
    }

    .item-actions {
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }

    .qty-btn {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: rgba(139, 69, 19, 0.12);
      color: var(--primary-brown);
      font-size: 1.2rem;
      font-weight: 600;
    }

    .qty-btn:disabled {
      background: rgba(139, 69, 19, 0.05);
      color: rgba(139, 69, 19, 0.35);
      cursor: not-allowed;
      box-shadow: none;
    }

    .qty-input {
      width: 64px;
      text-align: center;
      font-weight: 600;
      border-radius: 12px;
      border: 1px solid rgba(139, 69, 19, 0.16);
      padding: 0.45rem 0.25rem;
      font-size: 0.95rem;
      background: #fff;
      color: #2c2c2c;
    }

    .qty-input:focus {
      outline: none;
      border-color: rgba(74, 144, 226, 0.7);
      box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.15);
    }

    .qty-input::-webkit-outer-spin-button,
    .qty-input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    .qty-input[type="number"] {
      -moz-appearance: textfield;
    }

    .edit-btn,
    .remove-btn {
      width: 38px;
      height: 38px;
      border-radius: 12px;
      background: rgba(139, 69, 19, 0.08);
      font-size: 1.1rem;
    }

    .edit-note {
      margin-top: 0.75rem;
    }

    .edit-note input {
      width: 100%;
      border-radius: 12px;
      border: 1px solid rgba(139, 69, 19, 0.12);
      padding: 0.6rem 0.8rem;
      font-size: 0.9rem;
    }

    .cart-footer {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding-top: 1rem;
      border-top: 1px solid rgba(139, 69, 19, 0.1);
    }

    .check-all {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      font-weight: 600;
    }

    .cart-totals {
      display: flex;
      flex-direction: column;
      gap: 0.3rem;
      font-weight: 600;
    }

    .primary-btn {
      padding: 0.85rem 1.8rem;
      border-radius: var(--radius-pill);
      background: linear-gradient(135deg, var(--primary-brown), var(--primary-brown-dark));
      color: #fff;
      font-weight: 600;
      box-shadow: 0 16px 32px rgba(139, 69, 19, 0.2);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.65rem;
      transition: opacity 0.2s ease, background 0.2s ease;
    }

    .primary-btn:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      box-shadow: none;
    }

    .primary-btn .btn-spinner {
      display: none;
      width: 1.1rem;
      height: 1.1rem;
      border-radius: 50%;
      border: 3px solid rgba(255, 255, 255, 0.35);
      border-top-color: #fff;
      animation: spin 0.75s linear infinite;
    }

    .primary-btn.is-loading .btn-spinner {
      display: inline-block;
    }

    .gcash-btn {
      background: linear-gradient(135deg, #0d6efd, #0a58ca);
      box-shadow: 0 16px 32px rgba(13, 110, 253, 0.35);
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    .secondary-btn {
      padding: 0.8rem 1.6rem;
      border-radius: var(--radius-pill);
      background: rgba(139, 69, 19, 0.12);
      color: var(--primary-brown);
      font-weight: 600;
    }

    .summary-card {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .summary-item {
      display: flex;
      justify-content: space-between;
      font-weight: 500;
      padding: 0.6rem 0;
      border-bottom: 1px dashed rgba(139, 69, 19, 0.1);
    }

    .summary-item:last-child {
      border-bottom: none;
    }

    .checkout-form {
      display: flex;
      flex-direction: column;
      gap: 1.1rem;
    }

    .checkout-form label {
      font-weight: 600;
      font-size: 0.95rem;
    }

    .checkout-form input,
    .checkout-form textarea,
    .checkout-form select {
      width: 100%;
      border-radius: 14px;
      border: 1px solid rgba(139, 69, 19, 0.15);
      padding: 0.75rem 1rem;
      font-size: 0.95rem;
      background: rgba(255, 255, 255, 0.9);
      font-family: inherit;
      resize: vertical;
    }

    .checkout-form textarea {
      min-height: 120px;
    }

    .input-wrapper {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .address-section {
      display: none;
    }

    .address-section.is-visible {
      display: block;
    }

    .address-wrapper {
      flex-direction: column;
      align-items: stretch;
    }

    .address-grid {
      display: grid;
      gap: 0.75rem;
      width: 100%;
    }

    .address-grid .wide {
      grid-column: 1 / -1;
    }

    .address-grid .readonly-field {
      background: rgba(139, 69, 19, 0.08);
      color: var(--text-muted);
      cursor: not-allowed;
    }

    .address-grid .readonly-field:focus {
      box-shadow: none;
      outline: none;
    }

    .address-wrapper .done-btn {
      align-self: flex-end;
    }

    @media (min-width: 640px) {
      .address-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    .field-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.4rem;
    }

    .edit-field-btn,
    .done-btn {
      background: none;
      border: none;
      color: var(--primary-brown);
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
    }

    .done-btn {
      display: none;
    }

    .optional-hint {
      font-size: 0.8rem;
      color: var(--text-muted);
      font-weight: 500;
    }

    .confirmation {
      margin-top: 1rem;
      font-weight: 600;
      color: var(--primary-brown);
    }

    @media (max-width: 1100px) {
      .checkout-wrapper {
        grid-template-columns: 1fr;
      }

      #checkout-section {
        order: 2;
      }
    }

    @media (max-width: 768px) {
      .page-container {
        padding: 1.5rem 1rem;
        padding-top: 90px;
      }

      .checkout-wrapper {
        gap: 1.5rem;
      }

      .cart-item {
        grid-template-columns: 1fr;
      }

      .cart-item-left {
        grid-template-columns: auto 1fr;
      }

      .item-actions {
        justify-content: flex-end;
      }

      body:not(.checkout-active) #checkout-section {
        display: none;
      }

      body.checkout-active #cart-section {
        display: none;
      }

      body.checkout-active main {
        padding-top: 120px;
      }
    }

    @media (max-width: 480px) {
      .page-container {
        padding: 1rem 0.75rem;
        padding-top: 85px;
      }

      .glass-card {
        padding: 1.5rem;
        border-radius: 20px;
      }
    }
  </style>
</head>
<body class="checkout-view">
  <?php include __DIR__ . '/../topbar.php'; ?>
  <main class="page-container">
    <section class="checkout-hero">
      <h1>Your cart is almost ready to bake!</h1>
      <p>Review your selections, adjust quantities, and confirm the sweetest delivery details. We'll take care of the rest.</p>
    </section>

    <div class="checkout-wrapper">
      <section class="glass-card" id="cart-section">
        <div class="card-header">
          <h2>Cart Overview</h2>
          <span class="tag-pill" id="cartStatus">Ready to checkout</span>
        </div>
        <div class="cart-list" id="cart-items"></div>
        <div class="cart-footer">
          <label class="check-all">
            <input type="checkbox" onclick="toggleAll(this)" checked>
            Select all
          </label>
          <div class="cart-totals">
            <span class="total-items">Items: 0</span>
            <span class="total-price">Total Price: ₱0.00</span>
          </div>
          <button class="primary-btn" onclick="goToCheckout()">Proceed to checkout</button>
        </div>
      </section>

      <section class="glass-card" id="checkout-section" style="display: none;">
        <div class="card-header">
          <h2>Delivery details</h2>
          <button class="secondary-btn" type="button" onclick="goBack()">Back to cart</button>
        </div>

        <div class="summary-card" id="checkout-items"></div>

        <form class="checkout-form" onsubmit="placeOrder(event)">
          <div>
            <div class="field-header">
              <label for="name">Full Name</label>
              <button type="button" id="edit-name" class="edit-field-btn">Edit</button>
            </div>
            <div class="input-wrapper">
              <input type="text" id="name" required readonly />
              <button type="button" id="done-name" class="done-btn">Done</button>
            </div>
          </div>

          <div>
            <label for="order-type">Delivery or Pick Up</label>
            <select id="order-type" required>
              <option value="">-- Select --</option>
              <option value="Delivery">Delivery</option>
              <option value="Pick up">Pick up</option>
            </select>
          </div>

          <div id="address-section" class="address-section" aria-hidden="true">
            <div class="field-header">
              <label for="address-street">Delivery Address</label>
              <button type="button" id="edit-address" class="edit-field-btn">Edit</button>
            </div>
            <p class="delivery-area-note">Delivery service is currently limited to Hagonoy, Bulacan.</p>
            <div class="input-wrapper address-wrapper">
              <div class="address-grid">
                <input type="text" id="address-street" class="wide" placeholder="House No., Street" required readonly />
                <input type="text" id="address-barangay" class="wide" placeholder="Barangay" required readonly />
                <input type="text" id="address-city" class="readonly-field" placeholder="City / Municipality" required readonly />
                <input type="text" id="address-province" class="readonly-field" placeholder="Province" required readonly />
              </div>
              <button type="button" id="done-address" class="done-btn">Done</button>
            </div>
          </div>

          <div>
            <label for="mop">Mode of Payment</label>
            <select id="mop" required disabled>
              <option value="">-- Select --</option>
            </select>
          </div>

          <div>
            <div class="field-header">
              <label for="special-instructions">Special instructions</label>
              <span class="optional-hint">Optional</span>
            </div>
            <textarea id="special-instructions" placeholder="Let us know about delivery notes or allergy information." maxlength="500"></textarea>
          </div>

          <button type="submit" class="primary-btn" id="place-order-btn">
            <span class="btn-spinner" aria-hidden="true"></span>
            <span class="btn-label">Place Order</span>
          </button>
        </form>
        <div class="confirmation" id="confirmationMsg"></div>
      </section>
    </div>
  </main>

  <script type="module">
    import { getAuth, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";
    import "../firebase-init.js";

    const cartContainer = document.getElementById('cart-items');
    const checkoutItemsContainer = document.getElementById('checkout-items');
    const masterCheckbox = document.querySelector('.check-all input[type="checkbox"]');
    const header = document.getElementById('mainHeader');
    const rootPrefix = header?.dataset.rootPrefix || '../../../';
    const imagesBase = header?.dataset.imagesBase || `${rootPrefix}Images/`;
    const userPrefix = header?.dataset.userPrefix || `${rootPrefix}UserSide/`;
    const uploadsBase = `${rootPrefix}adminSide/products/uploads/`;
    const apiBase = header?.dataset.apiBase || `${rootPrefix}PHP/`;
    const fallbackImage = `${imagesBase}logo.png`;
    const categoryDirMap = new Map([
      ['bread', 'bread'],
      ['breads', 'bread'],
      ['cake', 'cakes'],
      ['cakes', 'cakes']
    ]);
    const categoryAliasMap = new Map([
      ['pastry', 'bread'],
      ['pastries', 'bread']
    ]);
    const legacyCategoryDirMap = new Map([
      ['pastry', 'pastry'],
      ['pastries', 'pastry']
    ]);
    const totalPriceLabel = document.querySelector('.total-price');
    const totalItemsLabel = document.querySelector('.total-items');
    const placeOrderButton = document.getElementById('place-order-btn');
    const placeOrderButtonLabel = placeOrderButton?.querySelector('.btn-label');
    const defaultPlaceOrderText = 'Place Order';
    const gcashPlaceOrderText = 'Pay with GCash';
    const defaultProcessingText = 'Placing your order...';
    const gcashProcessingText = 'Processing GCash payment...';
    if (placeOrderButton) {
      placeOrderButton.setAttribute('aria-busy', 'false');
    }
    let cartId = null;
    let checkoutData = [];
    let userEmail = null;
    const nameField = document.getElementById('name');
    const addressStreetField = document.getElementById('address-street');
    const addressBarangayField = document.getElementById('address-barangay');
    const addressCityField = document.getElementById('address-city');
    const addressProvinceField = document.getElementById('address-province');
    const editableAddressFields = [addressStreetField, addressBarangayField];
    const lockedAddressFields = [addressCityField, addressProvinceField];
    const allAddressFields = [...editableAddressFields, ...lockedAddressFields];
    const nameEditBtn = document.getElementById('edit-name');
    const addrEditBtn = document.getElementById('edit-address');
    const nameDoneBtn = document.getElementById('done-name');
    const addrDoneBtn = document.getElementById('done-address');
    const orderTypeSelect = document.getElementById('order-type');
    const mopSelect = document.getElementById('mop');
    const addressSection = document.getElementById('address-section');
    const specialInstructionsField = document.getElementById('special-instructions');
    const cartStatus = document.getElementById('cartStatus');

    const SERVICE_CITY = 'Hagonoy';
    const SERVICE_PROVINCE = 'Bulacan';

    function enforceLockedAddressParts() {
      lockedAddressFields.forEach(field => {
        field.readOnly = true;
      });

      if (addressCityField.value.trim().toLowerCase() !== SERVICE_CITY.toLowerCase()) {
        addressCityField.value = SERVICE_CITY;
      }

      if (addressProvinceField.value.trim().toLowerCase() !== SERVICE_PROVINCE.toLowerCase()) {
        addressProvinceField.value = SERVICE_PROVINCE;
      }
    }

    function getAddressParts() {
      return {
        street: addressStreetField.value.trim(),
        barangay: addressBarangayField.value.trim(),
        city: addressCityField.value.trim(),
        province: addressProvinceField.value.trim(),
      };
    }

    function composeAddress() {
      const parts = getAddressParts();
      return [parts.street, parts.barangay, parts.city, parts.province]
        .filter(part => part)
        .join(', ');
    }

    function parseAddressString(addressString) {
      if (typeof addressString !== 'string') {
        return { street: '', barangay: '', city: '', province: '' };
      }

      const parts = addressString
        .split(/\r?\n|,/)
        .map(part => part.trim())
        .filter(part => part.length > 0);

      const result = { street: '', barangay: '', city: '', province: '' };

      if (parts.length >= 4) {
        [result.street, result.barangay, result.city, result.province] = parts;
      } else if (parts.length === 3) {
        [result.street, result.city, result.province] = parts;
      } else if (parts.length === 2) {
        [result.street, result.city] = parts;
      } else if (parts.length === 1) {
        [result.street] = parts;
      }

      return result;
    }

    function setAddressFieldsReadOnly(isReadOnly) {
      editableAddressFields.forEach(field => {
        field.readOnly = isReadOnly;
      });
      enforceLockedAddressParts();
    }

    function isDeliveryAreaValid() {
      const { city, province } = getAddressParts();
      if (!city || !province) {
        return false;
      }

      return city.toLowerCase() === 'hagonoy' && province.toLowerCase() === 'bulacan';
    }

    function isGcashSelected() {
      return (mopSelect?.value || '').trim().toLowerCase() === 'gcash';
    }

    function isPlaceOrderLoading() {
      return placeOrderButton?.classList.contains('is-loading');
    }

    function updatePlaceOrderButtonAppearance() {
      if (!placeOrderButton) {
        return;
      }

      const gcashSelected = isGcashSelected();
      placeOrderButton.classList.toggle('gcash-btn', gcashSelected);

      if (placeOrderButtonLabel && !isPlaceOrderLoading()) {
        placeOrderButtonLabel.textContent = gcashSelected ? gcashPlaceOrderText : defaultPlaceOrderText;
      }
    }

    function updateMopOptions() {
      const orderType = orderTypeSelect.value;

      mopSelect.innerHTML = '<option value="">-- Select --</option>';
      mopSelect.value = '';
      mopSelect.disabled = true;

      if (orderType === 'Delivery') {
        mopSelect.innerHTML = '<option value="GCash">GCash</option>';
        mopSelect.value = 'GCash';
        updatePlaceOrderButtonAppearance();
        return;
      }

      if (orderType === 'Pick up') {
        mopSelect.innerHTML += '<option value="Cash on Pick Up">Cash on Pick Up</option>';
        mopSelect.innerHTML += '<option value="GCash">GCash</option>';
        mopSelect.disabled = false;
      }

      updatePlaceOrderButtonAppearance();
    }

    function updateAddressVisibility() {
      if (!addressSection) {
        return;
      }

      const isDelivery = orderTypeSelect.value === 'Delivery';
      addressSection.classList.toggle('is-visible', isDelivery);
      addressSection.setAttribute('aria-hidden', isDelivery ? 'false' : 'true');

      allAddressFields.forEach(field => {
        field.required = isDelivery;
        field.toggleAttribute('required', isDelivery);
      });

      if (!isDelivery) {
        setAddressFieldsReadOnly(true);
        addrDoneBtn.style.display = 'none';
      }
    }

    function syncOrderTypeUI() {
      updateMopOptions();
      updateAddressVisibility();
    }

    function updateDeliveryAvailability(showAlert = false) {
      const deliveryOption = orderTypeSelect.querySelector('option[value="Delivery"]');
      const deliveryAllowed = isDeliveryAreaValid();

      if (deliveryOption) {
        deliveryOption.disabled = !deliveryAllowed;
      }

      if (!deliveryAllowed && orderTypeSelect.value === 'Delivery') {
        orderTypeSelect.value = '';
        syncOrderTypeUI();
        if (showAlert) {
          alert('We currently deliver only within Hagonoy, Bulacan. Please choose Pick up for other areas.');
        }
      }

      return deliveryAllowed;
    }

    function resolveImagePath(imagePath, category) {
      if (!imagePath) {
        return fallbackImage;
      }

      const trimmed = String(imagePath).trim();
      if (trimmed === '') {
        return fallbackImage;
      }

      const normalised = trimmed.replace(/\\/g, '/');
      const lowerNormalised = normalised.toLowerCase();

      if (/^https?:\/\//i.test(normalised) || lowerNormalised.startsWith('data:')) {
        return normalised;
      }

      if (normalised.startsWith('/')) {
        return rootPrefix + normalised.replace(/^\/+/, '');
      }

      const cleaned = normalised
        .replace(/^(\.\/)+/, '')
        .replace(/^(\.\.\/)+/, '');
      const lowerCleaned = cleaned.toLowerCase();

      if (lowerCleaned.startsWith('images/')) {
        return rootPrefix + cleaned.replace(/^\/+/, '');
      }

      if (lowerCleaned.includes('adminside/products/uploads/')) {
        return rootPrefix + cleaned.replace(/^\/+/, '');
      }

      if (lowerCleaned.startsWith('uploads/') || lowerCleaned.startsWith('products/uploads/')) {
        const fromUploads = cleaned.split('/').pop();
        return fromUploads ? uploadsBase + fromUploads : fallbackImage;
      }

      const fileName = cleaned.split('/').pop() || '';
      if (!fileName) {
        return fallbackImage;
      }

      if (fileName.toLowerCase().startsWith('prod_')) {
        return uploadsBase + fileName;
      }

      if (fileName.toLowerCase() === 'logo.png') {
        return fallbackImage;
      }

      const categoryKey = typeof category === 'string' ? category.trim().toLowerCase() : '';
      if (categoryKey) {
        const normalizedCategory = categoryAliasMap.get(categoryKey) || categoryKey;
        const mappedDir = categoryDirMap.get(normalizedCategory);
        if (mappedDir) {
          return `${imagesBase}${mappedDir}/${fileName}`;
        }
        const legacyDir = legacyCategoryDirMap.get(categoryKey);
        if (legacyDir) {
          return `${imagesBase}${legacyDir}/${fileName}`;
        }
      }

      return `${imagesBase}${fileName}`;
    }

    orderTypeSelect.addEventListener('change', () => {
      if (orderTypeSelect.value === 'Delivery') {
        const canDeliver = updateDeliveryAvailability(true);
        if (!canDeliver) {
          syncOrderTypeUI();
          return;
        }
      }

      syncOrderTypeUI();
    });

    if (mopSelect) {
      mopSelect.addEventListener('change', updatePlaceOrderButtonAppearance);
    }

    nameEditBtn.addEventListener('click', () => {
      nameField.readOnly = false;
      nameDoneBtn.style.display = 'inline-flex';
      nameField.focus();
    });

    nameDoneBtn.addEventListener('click', () => {
      nameField.readOnly = true;
      nameDoneBtn.style.display = 'none';
      saveProfile();
    });

    addrEditBtn.addEventListener('click', () => {
      setAddressFieldsReadOnly(false);
      addrDoneBtn.style.display = 'inline-flex';
      addressStreetField.focus();
    });

    addrDoneBtn.addEventListener('click', () => {
      setAddressFieldsReadOnly(true);
      addrDoneBtn.style.display = 'none';
      saveProfile();

      const parts = getAddressParts();
      const hasAddress = Object.values(parts).some(value => value.length > 0);
      if (hasAddress && !isDeliveryAreaValid()) {
        alert('We currently deliver only within Hagonoy, Bulacan. Orders to other areas are available for pick up only.');
      }

      updateDeliveryAvailability();
    });

    enforceLockedAddressParts();
    updateDeliveryAvailability();
    syncOrderTypeUI();
    updatePlaceOrderButtonAppearance();

    const auth = getAuth();
    onAuthStateChanged(auth, user => {
      if (user) {
        userEmail = user.email;
        loadCart();
        loadProfile();
      }
    });

    async function loadProfile() {
      if (!userEmail) return;
      const res = await fetch(`${apiBase}user_api.php?action=get_profile&email=${encodeURIComponent(userEmail)}`);
      const data = await res.json();
      nameField.value = data.name || '';

      const street = data.address_street || data.addressStreet || '';
      const barangay = data.address_barangay || data.addressBarangay || '';
      const city = data.address_city || data.addressCity || '';
      const province = data.address_province || data.addressProvince || '';

      if (street || barangay || city || province) {
        addressStreetField.value = street;
        addressBarangayField.value = barangay;
        addressCityField.value = city;
        addressProvinceField.value = province;
      } else {
        const parsedAddress = parseAddressString(data.address || '');
        addressStreetField.value = parsedAddress.street;
        addressBarangayField.value = parsedAddress.barangay;
        addressCityField.value = parsedAddress.city;
        addressProvinceField.value = parsedAddress.province;
      }

      enforceLockedAddressParts();
      updateDeliveryAvailability();
    }

    async function loadCart() {
      if (!userEmail) return;
      const res = await fetch(`${apiBase}cart_api.php?action=list&email=${encodeURIComponent(userEmail)}`);
      const data = await res.json();
      cartId = data.cart_id;
      const cart = data.items;
      cartContainer.innerHTML = '';

      if (!cart || cart.length === 0) {
        cartContainer.innerHTML = '<p class="empty-note">Your cart is empty.</p>';
        totalItemsLabel.textContent = 'Items: 0';
        totalPriceLabel.textContent = 'Total Price: ₱0.00';
        cartStatus.textContent = 'Add items to continue';
        return;
      }

      cartStatus.textContent = 'Ready to checkout';

      cart.forEach(item => {
        const div = document.createElement('div');
        div.className = 'cart-item';
        div.setAttribute('data-id', item.Cart_Item_ID);
        div.setAttribute('data-product', item.Product_ID);
        div.setAttribute('data-price', item.Price);
        div.setAttribute('data-stock', item.Stock_Quantity);
        const imageSrc = resolveImagePath(item.Image_Path, item.Category);
        div.innerHTML = `
          <div class="cart-item-left">
            <input type="checkbox" class="item-check" checked>
            <img src="${imageSrc}" alt="${item.Name}">
            <div class="item-details">
              <b>${item.Name}</b>
              <span>₱${parseFloat(item.Price).toFixed(2)}</span>
              <div class="edit-note" style="display: none;">
                <input type="text" placeholder="Add note (e.g. No icing)">
              </div>
            </div>
          </div>
          <div class="item-actions">
            <button class="qty-btn decrease-btn" type="button">-</button>
            <input
              class="qty-input"
              type="number"
              value="${item.Quantity}"
              min="1"
              step="1"
              inputmode="numeric"
              aria-label="Quantity for ${item.Name}"
            />
            <button class="qty-btn increase-btn" type="button">+</button>
            <button class="edit-btn" type="button">✏️</button>
            <button class="remove-btn" type="button">🗑️</button>
          </div>
        `;
        cartContainer.appendChild(div);

        const decreaseButton = div.querySelector('.decrease-btn');
        const increaseButton = div.querySelector('.increase-btn');
        const qtyInput = div.querySelector('.qty-input');

        if (qtyInput) {
          const initialQty = parseInt(qtyInput.value, 10);
          qtyInput.dataset.lastValue = Number.isNaN(initialQty) ? '0' : String(initialQty);
          qtyInput.addEventListener('change', () => commitQtyInputValue(qtyInput));
          qtyInput.addEventListener('input', () => updateQuantityControls(div));
        }

        decreaseButton.addEventListener('click', e => decreaseQty(e.target));
        increaseButton.addEventListener('click', e => increaseQty(e.target));
        div.querySelector('.edit-btn').addEventListener('click', e => toggleEdit(e.target));
        div.querySelector('.remove-btn').addEventListener('click', e => removeItem(e.target));

        updateQuantityControls(div);
      });

      document.querySelectorAll('.item-check').forEach(cb => {
        cb.addEventListener('change', updateTotal);
      });

      updateTotal();
    }

    function saveProfile() {
      if (!userEmail) return;
      enforceLockedAddressParts();
      const parts = getAddressParts();
      const addressValue = composeAddress();
      updateDeliveryAvailability();
      const body = new URLSearchParams({
        email: userEmail,
        name: nameField.value,
        address: addressValue,
        address_street: parts.street,
        address_barangay: parts.barangay,
        address_city: parts.city,
        address_province: parts.province,
      });

      fetch(`${apiBase}user_api.php?action=set_profile`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body.toString()
      });
    }

    function formatCurrency(amount) {
      return amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }

    function setPlaceOrderLoading(isLoading) {
      if (!placeOrderButton) {
        return;
      }

      placeOrderButton.classList.toggle('is-loading', isLoading);
      placeOrderButton.disabled = isLoading;
      placeOrderButton.setAttribute('aria-busy', isLoading ? 'true' : 'false');

      if (placeOrderButtonLabel) {
        if (isLoading) {
          placeOrderButtonLabel.textContent = isGcashSelected() ? gcashProcessingText : defaultProcessingText;
        } else {
          updatePlaceOrderButtonAppearance();
        }
      }
    }

    function collectSelectedCartItems() {
      const items = [];
      document.querySelectorAll('.cart-item').forEach(item => {
        const checkbox = item.querySelector('.item-check');
        if (!checkbox.checked) {
          return;
        }

        const qtyInput = item.querySelector('.qty-input');
        let qty = parseInt(qtyInput?.value, 10);
        if (Number.isNaN(qty)) {
          qty = 0;
        }

        const priceAttr = parseFloat(item.getAttribute('data-price'));
        const price = Number.isNaN(priceAttr) ? 0 : priceAttr;
        const stockAttr = item.getAttribute('data-stock');
        const stock = stockAttr !== null ? parseInt(stockAttr, 10) : NaN;
        const name = item.querySelector('.item-details b')?.textContent || '';
        const productId = item.getAttribute('data-product');

        items.push({
          element: item,
          qty,
          price,
          stock,
          name,
          productId
        });
      });
      return items;
    }

    function syncCheckoutSummary({ items = null, enforceStock = false } = {}) {
      const selectedItems = items || collectSelectedCartItems();
      if (!checkoutItemsContainer) {
        return { hasItem: selectedItems.some(item => item.qty > 0) };
      }

      checkoutItemsContainer.innerHTML = '';
      checkoutData = [];
      let hasItem = false;

      selectedItems.forEach(data => {
        let { qty } = data;
        const stock = data.stock;

        if (enforceStock && !Number.isNaN(stock) && qty > stock) {
          if (stock <= 0) {
            alert(`${data.name} is out of stock and has been removed from your cart.`);
            const increaseBtn = data.element.querySelector('.increase-btn');
            if (increaseBtn) {
              saveQty(increaseBtn, 0);
            }
            data.qty = 0;
            return;
          }

          alert(`${data.name} quantity reduced to available stock of ${stock}.`);
          qty = stock;
          const qtyInput = data.element.querySelector('.qty-input');
          if (qtyInput) {
            qtyInput.value = stock;
          }
          const increaseBtn = data.element.querySelector('.increase-btn');
          if (increaseBtn) {
            saveQty(increaseBtn, stock);
          }
          updateQuantityControls(data.element);
        }

        if (qty <= 0) {
          return;
        }

        hasItem = true;
        data.qty = qty;
        const total = data.price * qty;
        const div = document.createElement('div');
        div.classList.add('summary-item');
        div.innerHTML = `<span>${data.name} ×${qty}</span><span>₱${formatCurrency(total)}</span>`;
        checkoutItemsContainer.appendChild(div);
        checkoutData.push({ product_id: data.productId, quantity: qty });
      });

      return { hasItem };
    }

    function updateTotal(options = {}) {
      const { enforceStockInSummary = false } = options;
      const selectedItems = collectSelectedCartItems();
      const summaryResult = syncCheckoutSummary({ items: selectedItems, enforceStock: enforceStockInSummary });

      let total = 0;
      let itemCount = 0;

      selectedItems.forEach(({ price, qty }) => {
        if (qty > 0) {
          total += price * qty;
          itemCount += qty;
        }
      });

      totalPriceLabel.textContent = 'Total Price: ₱' + formatCurrency(total);
      totalItemsLabel.textContent = 'Items: ' + itemCount;

      checkMasterToggle();
      return summaryResult;
    }

    function updateQuantityControls(item) {
      const decreaseBtn = item.querySelector('.decrease-btn');
      const increaseBtn = item.querySelector('.increase-btn');
      const qtyInput = item.querySelector('.qty-input');
      if (!qtyInput) {
        decreaseBtn.disabled = true;
        increaseBtn.disabled = true;
        return;
      }
      const parsedQty = parseInt(qtyInput?.value, 10);
      const qty = Number.isNaN(parsedQty) ? 0 : parsedQty;
      const stockAttr = item.getAttribute('data-stock');
      const stock = stockAttr !== null ? parseInt(stockAttr, 10) : NaN;

      decreaseBtn.disabled = qty <= 1;

      if (!Number.isNaN(stock)) {
        if (stock <= 0) {
          increaseBtn.disabled = true;
        } else {
          increaseBtn.disabled = qty >= stock;
        }
      } else {
        increaseBtn.disabled = false;
      }
    }

    function commitQtyInputValue(input) {
      if (!input) return;

      const item = input.closest('.cart-item');
      if (!item) return;

      const stockAttr = item.getAttribute('data-stock');
      const stock = stockAttr !== null ? parseInt(stockAttr, 10) : NaN;
      const rawValue = parseInt(input.value, 10);
      let newQty = Number.isNaN(rawValue) ? 1 : rawValue;

      if (newQty < 1) {
        newQty = 1;
      }

      if (!Number.isNaN(stock) && stock > 0 && newQty > stock) {
        newQty = stock;
      }

      input.value = newQty;

      const lastValue = parseInt(input.dataset.lastValue || '', 10);
      if (Number.isNaN(lastValue) || lastValue !== newQty) {
        saveQty(input, newQty);
        updateTotal();
      }

      updateQuantityControls(item);
    }

    function increaseQty(button) {
      const item = button.closest('.cart-item');
      const qtyInput = button.previousElementSibling;
      let qty = parseInt(qtyInput.value, 10);
      if (Number.isNaN(qty)) {
        qty = 0;
      }
      const stockAttr = item.getAttribute('data-stock');
      const stock = stockAttr !== null ? parseInt(stockAttr, 10) : NaN;

      if (!Number.isNaN(stock) && stock > 0 && qty >= stock) {
        updateQuantityControls(item);
        return;
      }

      const newQty = qty + 1;
      qtyInput.value = newQty;
      saveQty(button, qty + 1);
      updateTotal();
      updateQuantityControls(item);
    }

    function decreaseQty(button) {
      const item = button.closest('.cart-item');
      const qtyInput = button.nextElementSibling;
      let qty = parseInt(qtyInput.value, 10);
      if (Number.isNaN(qty)) {
        qty = 0;
      }
      if (qty > 1) {
        const newQty = qty - 1;
        qtyInput.value = newQty;
        saveQty(button, newQty);
        updateTotal();
        updateQuantityControls(item);
      } else {
        updateQuantityControls(item);
      }
    }

    function saveQty(sourceElement, newQty) {
      const item = sourceElement.closest('.cart-item');
      const id = item.getAttribute('data-id');
      fetch(`${apiBase}cart_api.php?action=update`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `cart_item_id=${id}&quantity=${newQty}`
      }).then(() => {
        const qtyInput = item.querySelector('.qty-input');
        if (qtyInput) {
          qtyInput.dataset.lastValue = String(newQty);
        }
        if (newQty <= 0) {
          item.remove();
        }
        updateTotal();
      });
    }

    window.toggleAll = function(masterCheckbox) {
      const checkboxes = document.querySelectorAll('.item-check');
      checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
      updateTotal();
    }

    function checkMasterToggle() {
      const checkboxes = document.querySelectorAll('.item-check');
      const allChecked = Array.from(checkboxes).every(cb => cb.checked);
      masterCheckbox.checked = allChecked;
    }

    function removeItem(button) {
      const item = button.closest('.cart-item');
      const id = item.getAttribute('data-id');
      fetch(`${apiBase}cart_api.php?action=remove`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `cart_item_id=${id}`
      }).then(() => loadCart());
    }

    function toggleEdit(button) {
      const item = button.closest('.cart-item');
      const note = item.querySelector('.edit-note');
      note.style.display = note.style.display === 'none' ? 'block' : 'none';
    }

    window.goToCheckout = function() {
      const summaryResult = updateTotal({ enforceStockInSummary: true }) || { hasItem: false };

      if (!summaryResult.hasItem) {
        alert('Please select at least one item to check out.');
        return;
      }

      document.getElementById('checkout-section').style.display = 'flex';
      document.body.classList.add('checkout-active');
      window.scrollTo({ top: document.getElementById('checkout-section').offsetTop - 120, behavior: 'smooth' });
    }

    window.goBack = function() {
      document.body.classList.remove('checkout-active');
      document.getElementById('checkout-section').style.display = 'none';
      window.scrollTo({ top: document.getElementById('cart-section').offsetTop - 120, behavior: 'smooth' });
    }

    function logOrderDebug(message, details) {
      const timestamp = new Date().toISOString();
      if (typeof details !== 'undefined') {
        console.error(`[Order Debug - ${timestamp}] ${message}`, details);
      } else {
        console.error(`[Order Debug - ${timestamp}] ${message}`);
      }
    }

    function buildOrderError(message, details) {
      const error = new Error(message);
      if (details) {
        error.debugDetails = details;
      }
      error.userFacingMessage = message;
      return error;
    }

    async function placeOrder(e) {
      e.preventDefault();
      const name = document.getElementById('name').value;
      const address = composeAddress();
      const orderType = document.getElementById('order-type').value;
      const mop = document.getElementById('mop').value;
      const specialInstructions = specialInstructionsField.value.trim();
      const confirmation = document.getElementById('confirmationMsg');

      if (confirmation) {
        confirmation.textContent = '';
        confirmation.classList.remove('error');
      }

      if (orderType === 'Delivery' && !isDeliveryAreaValid()) {
        alert('Delivery is only available within Hagonoy, Bulacan. Please choose Pick up for orders outside this area.');
        updateDeliveryAvailability(true);
        return;
      }

      setPlaceOrderLoading(true);

      try {
        logOrderDebug('Attempting to load latest cart before checkout.', { email: userEmail, checkoutCount: checkoutData.length });
        const res = await fetch(`${apiBase}cart_api.php?action=list&email=${encodeURIComponent(userEmail)}`);
        const cartType = res.headers.get('Content-Type') || '';
        const cartText = await res.text();
        if (!res.ok || !cartType.includes('application/json')) {
          logOrderDebug('Cart API responded with an unexpected payload.', { status: res.status, statusText: res.statusText, contentType: cartType, rawResponse: cartText });
          throw buildOrderError('Unable to verify your cart. Please refresh the page and try again.');
        }
        let latest;
        try {
          latest = JSON.parse(cartText);
        } catch (error) {
          logOrderDebug('Failed to parse cart API response.', { rawResponse: cartText, parseError: error.message });
          throw buildOrderError('We could not confirm the contents of your cart. Please refresh and try again.');
        }

        if (!latest.items || latest.items.length === 0) {
          alert('Your cart is empty.');
          return;
        }

        if (!userEmail) {
          throw buildOrderError('Please sign in again to place your order.');
        }

        if (!checkoutData.length) {
          throw buildOrderError('Please reselect your items before checking out.');
        }

        const payload = new URLSearchParams({
          cart_id: latest.cart_id,
          name,
          address,
          order_type: orderType,
          mop,
          special_instructions: specialInstructions,
          email: userEmail,
          items: JSON.stringify(checkoutData),
        });

        logOrderDebug('Submitting order request.', { cartId: latest.cart_id, itemCount: checkoutData.length, orderType, mop });
        const orderRes = await fetch(`${apiBase}order_api.php?action=create`, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: payload.toString(),
        });

        const orderText = await orderRes.text();
        const orderTypeHeader = orderRes.headers.get('Content-Type') || '';
        let orderData = null;
        if (orderText && orderTypeHeader.includes('application/json')) {
          try {
            orderData = JSON.parse(orderText);
          } catch (parseError) {
            logOrderDebug('Failed to parse order API response.', {
              rawResponse: orderText,
              status: orderRes.status,
              statusText: orderRes.statusText,
              parseError: parseError.message,
            });
            throw buildOrderError('Our server returned unreadable data. Please try placing your order again in a moment.');
          }
        } else if (orderText) {
          logOrderDebug('Order API returned non-JSON response.', {
            rawResponse: orderText,
            status: orderRes.status,
            statusText: orderRes.statusText,
            contentType: orderTypeHeader,
          });
        }

        if (!orderRes.ok) {
          const errorMessage = (orderData && (orderData.error || orderData.message)) || (orderText ? orderText.trim() : '');
          const responseDetails = {
            status: orderRes.status,
            statusText: orderRes.statusText,
            rawResponse: orderText,
          };
          logOrderDebug('Order API indicated failure status.', responseDetails);
          throw buildOrderError(errorMessage || 'The server had trouble placing your order. Please try again shortly.', responseDetails);
        }

        if (!orderData || orderData.error) {
          const responseDetails = {
            rawResponse: orderText,
            status: orderRes.status,
            statusText: orderRes.statusText,
          };
          logOrderDebug('Order API returned an application error.', { response: orderData, ...responseDetails });
          throw buildOrderError((orderData && orderData.error) || 'Failed to place order.', responseDetails);
        }

        await loadCart();

        const orderId = orderData.order_id;
        if (orderId) {
          const invoiceUrl = `${userPrefix}INVOICE/orderDetails.php?order_id=${encodeURIComponent(orderId)}`;
          window.location.href = invoiceUrl;
          return;
        }

        if (confirmation) {
          confirmation.classList.remove('error');
          confirmation.textContent = 'Order placed successfully!';
        }
        document.body.classList.remove('checkout-active');
        document.getElementById('checkout-section').style.display = 'none';
      } catch (error) {
        logOrderDebug('Caught error while placing order.', { message: error.message, stack: error.stack, details: error.debugDetails });
        if (confirmation) {
          confirmation.textContent = error.userFacingMessage || error.message || 'Failed to place order.';
          confirmation.classList.add('error');
        }
      } finally {
        setPlaceOrderLoading(false);
      }
    }

    window.placeOrder = placeOrder;
  </script>
</body>
</html>
