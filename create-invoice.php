<?php
// create-invoice.php
ini_set('session.save_path', '/tmp');
session_start();
require_once 'includes/config.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberGuardX – Invoice System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  .item-type-positive { background-color: #f8fff8; }
  .item-type-negative { background-color: #fff8f8; }
  .item-type-positive .rowTotal { color: #28a745; }
  .item-type-negative .rowTotal { color: #dc3545; }
  
  /* Header styling */
  .header-container {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: white;
    padding: 15px 0;
    margin-bottom: 20px;
    border-radius: 8px;
  }
  .back-link {
    color: white;
    text-decoration: none;
    font-weight: bold;
  }
  .user-info {
    font-size: 14px;
    opacity: 0.9;
  }
</style>
</head>

<body class="p-4">

<!-- Header with Back Button -->
<div class="header-container">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h3>CyberGuardX Invoice Creation</h3>
        <p class="user-info mb-0">Logged in as: <?php echo htmlspecialchars($_SESSION['full_name']); ?> | Role: <?php echo htmlspecialchars($_SESSION['role']); ?></p>
      </div>
      <div>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
      </div>
    </div>
  </div>
</div>

<div class="container">

  <h3 class="mb-4">Invoice Creation</h3>

  <!-- CLIENT INFORMATION -->
  <div class="card mb-4">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0">Client Information</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Company/Client Name *</label>
          <input type="text" id="clientName" class="form-control" placeholder="Enter client name" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Email Address *</label>
          <input type="email" id="clientEmail" class="form-control" placeholder="client@example.com" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Phone Number</label>
          <input type="text" id="clientPhone" class="form-control" placeholder="+20 XXX XXX XXXX">
        </div>
        <div class="col-12 mb-3">
          <label class="form-label">Address</label>
          <textarea id="clientAddress" class="form-control" rows="2" placeholder="Full address"></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- BILL TO -->
  <h5>Bill To</h5>
  <div class="row mb-4">
    <div class="col-md-4">
      <label>Name *</label>
      <input type="text" id="billName" class="form-control" placeholder="Client name for invoice" required>
    </div>
    <div class="col-md-4">
      <label>Email *</label>
      <input type="email" id="billEmail" class="form-control" placeholder="client@example.com" required>
    </div>
    <div class="col-md-4">
      <label>Phone</label>
      <input type="text" id="billPhone" class="form-control" placeholder="+20 XXX XXX XXXX">
    </div>
  </div>

  <!-- ITEMS -->
  <h5>Items</h5>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th>Type</th>
        <th>Item Description</th>
        <th>QTY</th>
        <th>Unit Price (EGP)</th>
        <th>Total (EGP)</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="items">
      <!-- Rows added by JavaScript -->
    </tbody>
  </table>

  <button class="btn btn-secondary mb-3" onclick="addRow()">+ Add Item</button>

  <!-- OFFER -->
  <h5>Offer</h5>
  <div class="row mb-4">
    <div class="col-md-4">
      <select id="offerType" class="form-control" onchange="calculateTotals()">
        <option value="none">No Offer</option>
        <option value="percent">Percentage %</option>
        <option value="fixed">Fixed Amount</option>
      </select>
    </div>
    <div class="col-md-4">
      <input type="number" id="offerValue" class="form-control" value="0" min="0" onchange="calculateTotals()" placeholder="Offer value">
    </div>
  </div>

  <!-- PAYMENT SECTION -->
  <h5>Payment</h5>
  <div class="row mb-4">
    <div class="col-md-4">
      <label>Payment Amount Paid (EGP)</label>
      <input type="number" id="paymentAmount" class="form-control" value="0" min="0" step="0.01" onchange="calculateTotals()">
      <small class="text-muted">Enter amount already paid by customer</small>
    </div>
  </div>

  <!-- SUMMARY -->
  <h5>Summary</h5>
  <table class="table">
    <tr><th>Subtotal</th><td><span id="subtotal">0</span> EGP</td></tr>
    <tr><th>Discount</th><td><span id="discount">0</span> EGP</td></tr>
    <tr><th>Net Amount</th><td><span id="net">0</span> EGP</td></tr>
    <tr><th>VAT 14%</th><td><span id="vat">0</span> EGP</td></tr>
    <tr class="fw-bold"><th>Total</th><td><span id="total">0</span> EGP</td></tr>
    <tr><th>Payment Amount</th><td><span id="paymentDisplay">0</span> EGP</td></tr>
    <tr class="fw-bold"><th>Balance Amount</th><td><span id="balance">0</span> EGP</td></tr>
  </table>

  <!-- ACTION BUTTONS -->
  <div class="text-center mb-5">
    <button class="btn btn-lg btn-danger me-3" onclick="clearForm()">
      Clear Form
    </button>
    <button class="btn btn-lg btn-primary" onclick="generateInvoice()">
      🧾 Generate Invoice & Save to Database
    </button>
  </div>

</div>

<script>
// Global variables
let itemsArray = [];
let itemCounter = 0;
const vatRate = 0.14; // 14% VAT for Egypt

// Initialize form
document.addEventListener('DOMContentLoaded', function() {
    addRow(); // Add first empty row
    calculateTotals();
    
    // Auto-fill bill info from client info (REAL-TIME)
    const clientNameInput = document.getElementById('clientName');
    const clientEmailInput = document.getElementById('clientEmail');
    const clientPhoneInput = document.getElementById('clientPhone');
    
    const billNameInput = document.getElementById('billName');
    const billEmailInput = document.getElementById('billEmail');
    const billPhoneInput = document.getElementById('billPhone');
    
    // Function to sync fields
    function syncClientToBill() {
        if (billNameInput.value === '' && clientNameInput.value !== '') {
            billNameInput.value = clientNameInput.value;
        }
        if (billEmailInput.value === '' && clientEmailInput.value !== '') {
            billEmailInput.value = clientEmailInput.value;
        }
        if (billPhoneInput.value === '' && clientPhoneInput.value !== '') {
            billPhoneInput.value = clientPhoneInput.value;
        }
    }
    
    // Sync on input
    clientNameInput.addEventListener('input', syncClientToBill);
    clientEmailInput.addEventListener('input', syncClientToBill);
    clientPhoneInput.addEventListener('input', syncClientToBill);
    
    // Also sync when page loads if client info exists
    syncClientToBill();
});

// Add new item row
function addRow() {
    itemCounter++;
    const tbody = document.getElementById('items');
    const row = document.createElement('tr');
    row.id = 'itemRow' + itemCounter;
    row.className = 'item-type-positive';
    
    row.innerHTML = `
        <td>
            <select class="form-control form-control-sm item-type" onchange="updateItemType(this, ${itemCounter})">
                <option value="positive">Charge (+)</option>
                <option value="negative">Deduction (-)</option>
            </select>
        </td>
        <td><input class="form-control item" placeholder="Item description" onchange="updateItem(${itemCounter})"></td>
        <td><input type="number" class="form-control qty" value="1" min="0.01" step="0.01" onchange="updateItem(${itemCounter})"></td>
        <td><input type="number" class="form-control price" placeholder="0.00" min="0" step="0.01" onchange="updateItem(${itemCounter})"></td>
        <td class="rowTotal">0.00</td>
        <td><button class="btn btn-sm btn-danger" type="button" onclick="removeRow(this, ${itemCounter})">×</button></td>
    `;
    
    tbody.appendChild(row);
    
    // Add to items array
    itemsArray.push({
        id: itemCounter,
        type: 'positive',
        description: '',
        quantity: 1,
        unitPrice: 0,
        total: 0
    });
    
    calculateTotals();
}

// Update item type (positive/negative)
function updateItemType(select, id) {
    const row = select.closest('tr');
    const isPositive = select.value === 'positive';
    
    if (isPositive) {
        row.className = 'item-type-positive';
    } else {
        row.className = 'item-type-negative';
    }
    
    // Update array
    const itemIndex = itemsArray.findIndex(item => item.id === id);
    if (itemIndex > -1) {
        itemsArray[itemIndex].type = select.value;
    }
    
    calculateTotals();
}

// Update item in array
function updateItem(id) {
    const row = document.getElementById('itemRow' + id);
    if (!row) return;
    
    const desc = row.querySelector('.item').value;
    const qty = parseFloat(row.querySelector('.qty').value) || 0;
    const price = parseFloat(row.querySelector('.price').value) || 0;
    const type = row.querySelector('.item-type').value;
    const total = qty * price * (type === 'negative' ? -1 : 1);
    
    // Update display
    row.querySelector('.rowTotal').textContent = total.toFixed(2);
    
    // Update array
    const itemIndex = itemsArray.findIndex(item => item.id === id);
    if (itemIndex > -1) {
        itemsArray[itemIndex] = {
            id: id,
            type: type,
            description: desc,
            quantity: qty,
            unitPrice: price,
            total: total
        };
    }
    
    calculateTotals();
}

// Remove item row
function removeRow(button, id) {
    const row = button.closest('tr');
    row.remove();
    
    // Remove from array
    itemsArray = itemsArray.filter(item => item.id !== id);
    calculateTotals();
}

// Calculate all totals
function calculateTotals() {
    let subtotal = 0;
    
    // Calculate subtotal from items
    itemsArray.forEach(item => {
        subtotal += item.total;
    });
    
    // Apply discount
    const offerType = document.getElementById('offerType').value;
    const offerValue = parseFloat(document.getElementById('offerValue').value) || 0;
    let discount = 0;
    
    if (offerType === 'percent' && offerValue > 0) {
        discount = subtotal * (offerValue / 100);
    } else if (offerType === 'fixed' && offerValue > 0) {
        discount = Math.min(offerValue, subtotal);
    }
    
    const net = subtotal - discount;
    const vat = net * vatRate;
    const total = net + vat;
    
    // Payment
    const payment = parseFloat(document.getElementById('paymentAmount').value) || 0;
    const balance = total - payment;
    
    // Update display
    document.getElementById('subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('discount').textContent = discount.toFixed(2);
    document.getElementById('net').textContent = net.toFixed(2);
    document.getElementById('vat').textContent = vat.toFixed(2);
    document.getElementById('total').textContent = total.toFixed(2);
    document.getElementById('paymentDisplay').textContent = payment.toFixed(2);
    document.getElementById('balance').textContent = balance.toFixed(2);
}

// Clear form
function clearForm() {
    if (confirm('Are you sure you want to clear the entire form?')) {
        // Reset client info
        document.getElementById('clientName').value = '';
        document.getElementById('clientEmail').value = '';
        document.getElementById('clientPhone').value = '';
        document.getElementById('clientAddress').value = '';
        
        // Reset bill info
        document.getElementById('billName').value = '';
        document.getElementById('billEmail').value = '';
        document.getElementById('billPhone').value = '';
        
        // Reset items
        document.getElementById('items').innerHTML = '';
        itemsArray = [];
        itemCounter = 0;
        addRow(); // Add first row back
        
        // Reset offer and payment
        document.getElementById('offerType').value = 'none';
        document.getElementById('offerValue').value = '0';
        document.getElementById('paymentAmount').value = '0';
        
        calculateTotals();
    }
}

// Validate form
function validateForm() {
    const clientName = document.getElementById('clientName').value.trim();
    if (!clientName) {
        alert('Please enter client name');
        document.getElementById('clientName').focus();
        return false;
    }
    
    const clientEmail = document.getElementById('clientEmail').value.trim();
    if (!clientEmail) {
        alert('Please enter client email');
        document.getElementById('clientEmail').focus();
        return false;
    }
    
    const billName = document.getElementById('billName').value.trim();
    if (!billName) {
        alert('Please enter bill to name');
        document.getElementById('billName').focus();
        return false;
    }
    
    const billEmail = document.getElementById('billEmail').value.trim();
    if (!billEmail) {
        alert('Please enter bill to email');
        document.getElementById('billEmail').focus();
        return false;
    }
    
    if (itemsArray.length === 0) {
        alert('Please add at least one invoice item');
        return false;
    }
    
    // Validate items
    for (const item of itemsArray) {
        if (!item.description.trim()) {
            alert('Please enter description for all items');
            return false;
        }
        if (item.quantity <= 0) {
            alert('Please enter valid quantity for all items');
            return false;
        }
        if (item.unitPrice <= 0) {
            alert('Please enter valid unit price for all items');
            return false;
        }
    }
    
    return true;
}

// Generate invoice - Send to PHP
async function generateInvoice() {
    // Validate form
    if (!validateForm()) {
        return;
    }
    
    // Show loading
    const btn = document.querySelector('.btn-primary');
    const originalText = btn.textContent;
    btn.textContent = 'Saving to Database...';
    btn.disabled = true;
    
    // Collect form data
    const invoiceData = {
        clientName: document.getElementById('clientName').value.trim(),
        clientAddress: document.getElementById('clientAddress').value.trim(),
        clientEmail: document.getElementById('clientEmail').value.trim(),
        clientPhone: document.getElementById('clientPhone').value.trim(),
        billName: document.getElementById('billName').value.trim(),
        billEmail: document.getElementById('billEmail').value.trim(),
        billPhone: document.getElementById('billPhone').value.trim(),
        offerType: document.getElementById('offerType').value,
        offerValue: parseFloat(document.getElementById('offerValue').value) || 0,
        paymentAmount: parseFloat(document.getElementById('paymentAmount').value) || 0,
        items: itemsArray,
        subtotal: parseFloat(document.getElementById('subtotal').textContent),
        discount: parseFloat(document.getElementById('discount').textContent),
        net: parseFloat(document.getElementById('net').textContent),
        vat: parseFloat(document.getElementById('vat').textContent),
        total: parseFloat(document.getElementById('total').textContent),
        balance: parseFloat(document.getElementById('balance').textContent)
    };
    
    try {
        // Send to PHP
        const response = await fetch('save-invoice.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                client_name: invoiceData.clientName,
                client_address: invoiceData.clientAddress,
                client_email: invoiceData.clientEmail,
                client_phone: invoiceData.clientPhone,
                bill_name: invoiceData.billName,
                bill_email: invoiceData.billEmail,
                bill_phone: invoiceData.billPhone,
                offer_type: invoiceData.offerType,
                offer_value: invoiceData.offerValue,
                payment_amount: invoiceData.paymentAmount,
                items: JSON.stringify(invoiceData.items),
                subtotal: invoiceData.subtotal,
                discount: invoiceData.discount,
                net: invoiceData.net,
                vat: invoiceData.vat,
                total: invoiceData.total,
                balance: invoiceData.balance
            })
        });
        
        if (response.redirected) {
            // Redirected by PHP
            window.location.href = response.url;
        } else if (response.ok) {
            const result = await response.text();
            if (result.includes('Location:')) {
                // Extract redirect URL
                const match = result.match(/Location:\s*(.+)/i);
                if (match) {
                    window.location.href = match[1].trim();
                }
            } else if (result.includes('Error')) {
                alert('Error: ' + result);
            } else {
                // Success message
                alert('Invoice saved successfully!');
                window.location.href = 'dashboard.php';
            }
        } else {
            const error = await response.text();
            alert('Error saving invoice: ' + error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Network error. Please check your connection and try again.');
    } finally {
        // Restore button
        btn.textContent = originalText;
        btn.disabled = false;
    }
}
</script>

</body>
</html>