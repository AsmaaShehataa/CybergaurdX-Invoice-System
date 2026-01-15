const VAT_RATE = 0.14;

// Initialize calculation on page load
document.addEventListener('DOMContentLoaded', () => {
  calculate();
  attachInputListeners();
});

function calculate() {
  let subtotal = 0;

  document.querySelectorAll("#items tr").forEach(row => {
    const qty = Number(row.querySelector(".qty").value || 0);
    const price = Number(row.querySelector(".price").value || 0);
    const itemType = row.querySelector(".item-type").value;
    
    let total = qty * price;
    
    // If it's a deduction/negative item, make total negative
    if (itemType === "negative") {
      total = -Math.abs(total);
    }
    
    // Update display - show with minus sign for negative items
    row.querySelector(".rowTotal").innerText = total.toFixed(2);
    row.querySelector(".rowTotal").innerHTML = total.toFixed(2) + 
      (total < 0 ? ' <span class="badge bg-danger">Deduction</span>' : '');
    
    subtotal += total;
  });

  let discount = 0;
  const offerType = document.getElementById("offerType").value;
  const offerValue = Number(document.getElementById("offerValue").value || 0);

  if (offerType === "percent") {
    discount = Math.abs(subtotal) * (offerValue / 100);
  } else if (offerType === "fixed") {
    discount = offerValue;
  }

  // Ensure discount doesn't exceed subtotal
  if (discount > Math.abs(subtotal)) discount = Math.abs(subtotal);
  if (discount < 0) discount = 0;

  const net = subtotal - discount;
  const vat = Math.max(0, net) * VAT_RATE; 
  const total = net + vat;
  
  // Get payment amount
  const paymentAmount = Number(document.getElementById("paymentAmount").value || 0);
  
  // Calculate balance (can't be negative)
  let balance = total - paymentAmount;
  if (balance < 0) balance = 0;

  // Update display
  document.getElementById("subtotal").innerText = subtotal.toFixed(2);
  document.getElementById("discount").innerText = discount.toFixed(2);
  document.getElementById("net").innerText = net.toFixed(2);
  document.getElementById("vat").innerText = vat.toFixed(2);
  document.getElementById("total").innerText = total.toFixed(2);
  document.getElementById("paymentDisplay").innerText = paymentAmount.toFixed(2);
  document.getElementById("balance").innerText = balance.toFixed(2);
}

function updateItemType(select) {
  const row = select.closest('tr');
  const type = select.value;
  
  // Update row styling
  row.className = type === 'positive' ? 'item-type-positive' : 'item-type-negative';
  
  // Update quantity/price validation
  const qtyInput = row.querySelector('.qty');
  const priceInput = row.querySelector('.price');
  
  if (type === 'negative') {

    qtyInput.min = "0.01";
    priceInput.min = "0";
  } else {
    qtyInput.min = "0.01";
    priceInput.min = "0";
  }
  
  calculate();
}

function addRow() {
  const row = document.createElement('tr');
  row.className = 'item-type-positive';
  row.innerHTML = `
    <td>
      <select class="form-control form-control-sm item-type" onchange="updateItemType(this)">
        <option value="positive">Charge (+)</option>
        <option value="negative">Deduction (-)</option>
      </select>
    </td>
    <td><input class="form-control item" placeholder="Item description"></td>
    <td><input type="number" class="form-control qty" value="1" min="0.01" step="0.01"></td>
    <td><input type="number" class="form-control price" placeholder="0.00" min="0" step="0.01"></td>
    <td class="rowTotal">0.00</td>
    <td><button class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button></td>
  `;
  document.getElementById("items").appendChild(row);
  attachRowInputListeners(row);
  calculate();
}

function removeRow(button) {
  const row = button.closest('tr');
  if (document.querySelectorAll("#items tr").length > 1) {
    row.remove();
    calculate();
  } else {
    alert("At least one item row is required");
  }
}

function attachRowInputListeners(row) {
  const inputs = row.querySelectorAll('input');
  inputs.forEach(input => {
    input.addEventListener('input', calculate);
  });
}

function attachInputListeners() {
  // Attach to all existing inputs
  document.querySelectorAll("#items input").forEach(input => {
    input.addEventListener('input', calculate);
  });
  
  // Attach to offer inputs
  document.getElementById("offerType").addEventListener('change', calculate);
  document.getElementById("offerValue").addEventListener('input', calculate);
  
  // Attach to payment input
  document.getElementById("paymentAmount").addEventListener('input', calculate);
}

function validateForm() {
  const billName = document.getElementById("billName").value.trim();
  if (!billName) {
    alert("Please enter customer name");
    return false;
  }
  
  let hasValidItems = false;
  document.querySelectorAll("#items tr").forEach(row => {
    const qty = Number(row.querySelector(".qty").value || 0);
    const price = Number(row.querySelector(".price").value || 0);
    if (qty > 0 && price >= 0) {
      const itemName = row.querySelector(".item").value.trim();
      if (itemName) {
        hasValidItems = true;
      }
    }
  });
  
  if (!hasValidItems) {
    alert("Please add at least one valid item with description, quantity, and price");
    return false;
  }
  
  // Validate offer value
  const offerType = document.getElementById("offerType").value;
  const offerValue = Number(document.getElementById("offerValue").value || 0);
  
  if (offerType === "percent" && (offerValue < 0 || offerValue > 100)) {
    alert("Percentage discount must be between 0 and 100");
    return false;
  }
  
  if (offerType === "fixed" && offerValue < 0) {
    alert("Fixed discount cannot be negative");
    return false;
  }
  
  // Validate payment amount
  const paymentAmount = Number(document.getElementById("paymentAmount").value || 0);
  if (paymentAmount < 0) {
    alert("Payment amount cannot be negative");
    return false;
  }
  
  return true;
}

function generateInvoice() {
  if (!validateForm()) return;

  const items = [];
  let hasValidItems = false;

  document.querySelectorAll("#items tr").forEach(row => {
    const name = row.querySelector(".item").value.trim();
    const qty = row.querySelector(".qty").value;
    const price = row.querySelector(".price").value;
    const total = row.querySelector(".rowTotal").innerText;
    const type = row.querySelector(".item-type").value;

    if (name && qty > 0 && price >= 0) {
      items.push({
        name: name,
        qty: qty,
        price: price,
        total: total,
        type: type  // Add type to the data
      });
      hasValidItems = true;
    }
  });

  if (!hasValidItems) {
    alert("Please add at least one valid item");
    return;
  }

  // Format date for invoice
  const now = new Date();
  const invoiceDate = now.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
  
  // Generate invoice number
  const invoiceNumber = "CGX-" + now.getFullYear() + "-" + 
    String(now.getMonth() + 1).padStart(2, '0') + "-" +
    String(now.getDate()).padStart(2, '0') + "-" +
    String(Math.floor(Math.random() * 1000)).padStart(3, '0');

  const data = {
    invoiceNo: invoiceNumber,
    date: invoiceDate,

    billTo: {
      name: document.getElementById("billName").value.trim(),
      email: document.getElementById("billEmail").value.trim(),
      phone: document.getElementById("billPhone").value.trim()
    },

    items: items,
    subtotal: document.getElementById("subtotal").innerText,
    discount: document.getElementById("discount").innerText,
    net: document.getElementById("net").innerText,
    vat: document.getElementById("vat").innerText,
    total: document.getElementById("total").innerText,
    paymentAmount: document.getElementById("paymentDisplay").innerText,
    balance: document.getElementById("balance").innerText,
    
    // Add metadata
    generatedAt: now.toISOString(),
    offerType: document.getElementById("offerType").value,
    offerValue: document.getElementById("offerValue").value
  };

  localStorage.setItem("invoiceData", JSON.stringify(data));
  window.open("invoice.html", "_blank");
}

// Attach event listeners
document.addEventListener("input", function(e) {
  if (e.target.matches('#items input, #offerType, #offerValue, #paymentAmount')) {
    calculate();
  }
});