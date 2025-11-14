<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Order Details - Cindy's Bakeshop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles.css" />
  <style>
    body.invoice-view {
      display: flex;
      flex-direction: column;
    }

    .invoice-wrapper {
      background: rgba(255, 255, 255, 0.92);
      border-radius: 32px;
      padding: clamp(2.5rem, 4vw, 3.4rem);
      box-shadow: var(--shadow-soft);
      border: 1px solid rgba(139, 69, 19, 0.12);
      margin-top: 2rem;
      display: grid;
      gap: 2rem;
    }

    .invoice-wrapper.pdf-export {
      border: none;
      box-shadow: none;
      background: #fff;
      padding: 1.8rem;
      gap: 1.5rem;
      font-size: 0.92rem;
    }

    .invoice-wrapper.pdf-export .invoice-header h1 {
      font-size: 2rem;
    }

    .invoice-wrapper.pdf-export .invoice-meta {
      font-size: 0.85rem;
      gap: 0.45rem;
    }

    .invoice-wrapper.pdf-export .detail-card {
      padding: 0.85rem 1rem;
      gap: 0.25rem;
    }

    .invoice-wrapper.pdf-export .detail-card span {
      font-size: 0.78rem;
    }

    .invoice-wrapper.pdf-export .detail-card strong {
      font-size: 0.95rem;
    }

    .invoice-header {
      display: flex;
      flex-wrap: wrap;
      gap: 1.5rem;
      justify-content: space-between;
    }

    .invoice-header h1 {
      font-size: clamp(2rem, 3vw, 2.6rem);
      font-weight: 700;
      color: var(--primary-brown);
    }

    .invoice-wrapper.pdf-export .invoice-header p {
      font-size: 0.85rem;
    }

    .invoice-meta {
      display: grid;
      gap: 0.6rem;
      font-size: 0.95rem;
    }

    .invoice-meta span {
      display: block;
      color: var(--text-muted);
    }

    .details-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .detail-card {
      background: rgba(139, 69, 19, 0.08);
      border-radius: 20px;
      padding: 1.1rem 1.3rem;
      display: grid;
      gap: 0.3rem;
    }

    .detail-card span {
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .detail-card strong {
      font-size: 1.05rem;
      color: var(--primary-brown);
    }

    .detail-card .multiline {
      white-space: pre-wrap;
      word-break: break-word;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 18px 36px rgba(139, 69, 19, 0.12);
    }

    .invoice-wrapper.pdf-export table {
      box-shadow: none;
      border-radius: 0;
      font-size: 0.9rem;
    }

    #invoice-items-section {
      display: grid;
      gap: 1rem;
    }

    .invoice-wrapper.pdf-export #invoice-items-section {
      gap: 0.8rem;
    }

    thead {
      background: linear-gradient(135deg, var(--primary-brown), var(--primary-brown-dark));
      color: #fff;
    }

    th, td {
      padding: 1rem 1.2rem;
      text-align: left;
    }

    .invoice-wrapper.pdf-export th,
    .invoice-wrapper.pdf-export td {
      padding: 0.7rem 0.9rem;
    }

    tbody tr:nth-child(odd) {
      background: rgba(139, 69, 19, 0.05);
    }

    tbody tr:nth-child(even) {
      background: rgba(255, 255, 255, 0.9);
    }

    .total-row {
      font-weight: 700;
      text-align: right;
      padding: 1rem 1.2rem;
      font-size: 1.15rem;
      color: var(--primary-brown);
    }

    .invoice-wrapper.pdf-export .total-row {
      padding: 0.7rem 0.9rem;
      font-size: 1rem;
    }

    .action-row {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .action-row button,
    .action-row a {
      padding: 0.85rem 1.8rem;
      border-radius: var(--radius-pill);
      font-weight: 600;
      border: none;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      cursor: pointer;
    }

    .action-row button {
      background: linear-gradient(135deg, var(--primary-brown), var(--primary-brown-dark));
      color: #fff;
    }

    .action-row a {
      background: rgba(139, 69, 19, 0.12);
      color: var(--primary-brown);
    }
  </style>
</head>
<body class="invoice-view">
  <?php include __DIR__ . '/../topbar.php'; ?>

  <main class="page-container">
    <div class="invoice-wrapper" id="invoice">
      <div class="invoice-header">
        <div>
          <h1>Order invoice</h1>
          <p style="color: var(--text-muted);">Thank you for supporting our kitchen—here are the details of your order.</p>
        </div>
        <div class="invoice-meta">
          <div><strong>Order ID:</strong> <span id="orderId">—</span></div>
          <div><strong>Order date:</strong> <span id="date">—</span></div>
        </div>
      </div>

      <div class="details-grid">
        <div class="detail-card">
          <span>Customer name</span>
          <strong id="name">—</strong>
        </div>
        <div class="detail-card">
          <span>Delivery address</span>
          <strong id="address">—</strong>
        </div>
        <div class="detail-card">
          <span>Payment method</span>
          <strong id="mop">—</strong>
        </div>
        <div class="detail-card">
          <span>Payment status</span>
          <strong id="paymentStatus">—</strong>
        </div>
        <div class="detail-card" id="specialInstructionsCard" style="display:none;">
          <span>Special instructions</span>
          <strong id="specialInstructions" class="multiline">—</strong>
        </div>
      </div>

      <section id="invoice-items-section">
        <table id="invoice-items-table">
          <thead>
            <tr>
              <th>Item</th>
              <th style="text-align:right;">Subtotal</th>
            </tr>
          </thead>
          <tbody id="items-list"></tbody>
        </table>

        <div class="total-row">Total: ₱<span id="total">0.00</span></div>
      </section>

      <div class="action-row no-print">
        <a href="../PRODUCT/MENU.php">← Back to menu</a>
        <button id="download-btn">⬇ Download PDF</button>
      </div>
    </div>
  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script>
    const params = new URLSearchParams(window.location.search);
    const orderId = params.get('order_id');
    const invoiceEl = document.getElementById('invoice');

    const currencyFormatter = new Intl.NumberFormat('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });

    function formatCurrency(value) {
      return currencyFormatter.format(Number(value) || 0);
    }

    const dateFormatter = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });

    function formatOrderDate(value) {
      if (!value) {
        return '—';
      }
      const parsed = new Date(value);
      if (Number.isNaN(parsed.getTime())) {
        return value;
      }
      return dateFormatter.format(parsed);
    }

    function populateInvoice(data) {
      const order = data.order || {};
      const user = data.user || {};
      const transaction = data.transaction || {};
      document.getElementById('orderId').textContent = order.Order_ID || '—';
      document.getElementById('name').textContent = user.Name || '—';
      document.getElementById('address').textContent = user.Address || '—';
      document.getElementById('mop').textContent = transaction.Payment_Method || '—';
      document.getElementById('paymentStatus').textContent = transaction.Payment_Status || '—';
      document.getElementById('date').textContent = formatOrderDate(order.Order_Date);

      const instructionsCard = document.getElementById('specialInstructionsCard');
      const instructionsValue = document.getElementById('specialInstructions');
      const instructions = (order.Special_Instructions || '').trim();
      if (instructions) {
        instructionsValue.textContent = instructions;
        instructionsCard.style.display = '';
      } else {
        instructionsCard.style.display = 'none';
        instructionsValue.textContent = '—';
      }

      const tbody = document.getElementById('items-list');
      tbody.innerHTML = '';
      let total = 0;
      (data.items || []).forEach(item => {
        const subtotal = parseFloat(item.Subtotal) || 0;
        total += subtotal;
        const row = document.createElement('tr');
        row.innerHTML = `<td>${item.Name} ×${item.Quantity}</td><td style="text-align:right;">₱${formatCurrency(subtotal)}</td>`;
        tbody.appendChild(row);
      });
      document.getElementById('total').textContent = formatCurrency(total);
    }

    if (orderId) {
      fetch(`../../PHP/order_api.php?action=view&order_id=${encodeURIComponent(orderId)}`)
        .then(res => res.json())
        .then(data => {
          if (!data.order) {
            invoiceEl.innerHTML = '<h2 style="text-align:center;">No order found</h2>';
            return;
          }
          populateInvoice(data);
        })
        .catch(() => {
          invoiceEl.innerHTML = '<h2 style="text-align:center;">Unable to load order details.</h2>';
        });
    } else {
      invoiceEl.innerHTML = '<h2 style="text-align:center;">No order found</h2>';
    }

    document.getElementById('download-btn').addEventListener('click', () => {
      invoiceEl.classList.add('pdf-export');

      const opt = {
        margin: 0.35,
        filename: 'CindysBakeshop_Invoice.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
          scale: 1.8,
          scrollY: 0,
          windowWidth: invoiceEl.scrollWidth,
          windowHeight: invoiceEl.scrollHeight,
          ignoreElements: element => element.classList && element.classList.contains('no-print')
        },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
      };
      const worker = html2pdf().set(opt).from(invoiceEl);
      worker.save().finally(() => {
        invoiceEl.classList.remove('pdf-export');
      });
    });
  </script>
  <script type="module" src="../firebase-init.js"></script>
</body>
</html>
