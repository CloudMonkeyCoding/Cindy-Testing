const headerEl = document.getElementById('mainHeader');
const apiBase = headerEl && headerEl.dataset && headerEl.dataset.apiBase
  ? headerEl.dataset.apiBase
  : '/PHP/';
const userPrefix = headerEl && headerEl.dataset && headerEl.dataset.userPrefix
  ? headerEl.dataset.userPrefix
  : '';

function buildApiUrl(path) {
  if (!apiBase.endsWith('/')) {
    return `${apiBase}/${path.replace(/^\//, '')}`;
  }
  return `${apiBase}${path.replace(/^\//, '')}`;
}

async function addToCart() {
  const qtyEl = document.getElementById("qty");
  const qty = qtyEl ? parseInt(qtyEl.value, 10) || 1 : 1;
  if (typeof window.maxStock !== 'undefined' && qty > window.maxStock) {
    alert(`Only ${window.maxStock} left in stock.`);
    return false;
  }
  const productId = new URLSearchParams(window.location.search).get("id");
  if (!productId) {
    alert("Missing product id.");
    return false;
  }
  try {
    // Ensure we have user info
    let email = null;
    try {
      const auth = window.getAuth ? window.getAuth() : null;
      email = auth && auth.currentUser ? auth.currentUser.email : null;
    } catch (e) {
      console.error("Auth unavailable", e);
    }

    const listUrl = email
      ? `${buildApiUrl('cart_api.php')}?action=list&email=${encodeURIComponent(email)}`
      : `${buildApiUrl('cart_api.php')}?action=list`;
    const listResp = await fetch(listUrl);
    const listType = listResp.headers.get('Content-Type') || '';
    const listText = await listResp.text();
    if (!listResp.ok || !listType.includes('application/json')) {
      console.error('Invalid cart list response', listText.slice(0, 200));
      alert('Error retrieving cart.');
      return false;
    }
    let listData;
    try {
      listData = JSON.parse(listText);
    } catch (e) {
      console.error('Invalid cart list response', listText.slice(0, 200));
      alert('Error retrieving cart.');
      return false;
    }
    const cartId = listData.cart_id;
    localStorage.setItem("cart_id", cartId);
    const existing = listData.items.find(
      (item) => String(item.Product_ID) === String(productId)
    );

    const params = new URLSearchParams();
    let action = "add";
    let newQty = qty;
    if (existing) {
      newQty = parseInt(existing.Quantity, 10) + qty;
      if (typeof window.maxStock !== 'undefined' && newQty > window.maxStock) {
        alert(`Only ${window.maxStock} left in stock.`);
        return false;
      }
      action = "update";
      params.set("cart_item_id", existing.Cart_Item_ID);
      params.set("quantity", newQty);
    } else {
      params.set("cart_id", cartId);
      params.set("product_id", productId);
      params.set("quantity", qty);
    }
    if (email) params.set("email", email);

    const response = await fetch(`${buildApiUrl('cart_api.php')}?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params.toString()
    });
    const respType = response.headers.get('Content-Type') || '';
    const respText = await response.text();
    if (!response.ok || !respType.includes('application/json')) {
      console.error('Invalid cart response', respText.slice(0, 200));
      alert('Error adding item to cart.');
      return false;
    }
    let result;
    try {
      result = JSON.parse(respText);
    } catch (e) {
      console.error('Invalid cart response', respText.slice(0, 200));
      alert('Error adding item to cart.');
      return false;
    }
    if ((action === "add" && result.cart_item_id) || (action === "update" && result.updated)) {
      alert(action === "add" ? "Item added to cart!" : "Cart quantity updated!");
      return true;
    } else {
      alert("Failed to add item to cart.");
      return false;
    }
  } catch (error) {
    console.error(error);
    alert("Error adding item to cart.");
    return false;
  }
}

async function buyNow() {
  const success = await addToCart();
  if (success) {
    window.location.href = `${userPrefix}CART/cart_checkout_page.php`;
  }
}
