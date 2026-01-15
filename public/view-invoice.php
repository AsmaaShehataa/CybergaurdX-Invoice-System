<?php
// view-invoice.php - FIXED FOR NEW PROJECT STRUCTURE
require_once dirname(__DIR__) . '/includes/session-config.php';
startAppSession();
require_once dirname(__DIR__) . '/includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get invoice ID from URL
$invoice_id = $_GET['id'] ?? 0;

if (!$invoice_id) {
    die("Invoice ID is required. <a href='dashboard.php'>Go to Dashboard</a>");
}

// Get invoice data from database
$stmt = $conn->prepare("
    SELECT i.*, u.full_name as sales_person, c.name as client_name, 
           c.email as client_email, c.phone as client_phone, 
           c.address as client_address, c.tax_number as client_tax
    FROM invoices i
    JOIN users u ON i.user_id = u.id
    JOIN clients c ON i.client_id = c.id
    WHERE i.id = ? AND (i.user_id = ? OR ? = 'admin')
");

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$stmt->bind_param("iis", $invoice_id, $user_id, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invoice not found or you don't have permission to view it. <a href='dashboard.php'>Go to Dashboard</a>");
}

$invoice = $result->fetch_assoc();

// Get invoice items
$items_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
$items_stmt->bind_param("i", $invoice_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);

// Get bill info from session or use client info
$bill_name = isset($_SESSION['last_bill_info']['name']) ? $_SESSION['last_bill_info']['name'] : $invoice['client_name'];
$bill_email = isset($_SESSION['last_bill_info']['email']) ? $_SESSION['last_bill_info']['email'] : $invoice['client_email'];
$bill_phone = isset($_SESSION['last_bill_info']['phone']) ? $_SESSION['last_bill_info']['phone'] : $invoice['client_phone'];

// Check permissions for edit/delete
$is_admin = ($_SESSION['role'] === 'admin');
$can_edit = ($is_admin || $invoice['user_id'] == $user_id);
$can_delete = $is_admin;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberGuardX - Official Invoice</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  /* SCREEN STYLES */
  body {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
    color: #2d3748;
    min-height: 100vh;
    padding: 20px;
  }
  
  .invoice-container {
    max-width: 820px;
    margin: 30px auto;
    background: white;
    padding: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.06);
    position: relative;
  }
  
  .invoice-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2c3e50 0%, #3498db 50%, #2c3e50 100%);
  }
  
  .invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 25px;
    margin-bottom: 30px;
    border-bottom: 3px solid #e9ecef;
  }
  
  .company-info {
    flex: 1;
  }
  
  .company-info h1 {
    color: #2c3e50;
    margin: 0 0 5px 0;
    font-size: 36px;
    font-weight: 800;
    letter-spacing: -0.5px;
    text-transform: uppercase;
  }
  
  .company-info .h5 {
    color: #3498db;
    font-size: 18px;
    margin: 0 0 20px 0;
    font-weight: 600;
  }
  
  .company-address {
    color: #4a5568;
    font-size: 13px;
    line-height: 1.6;
    margin: 12px 0;
    padding-left: 15px;
    border-left: 3px solid #e9ecef;
  }
  
  .company-contact {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin: 10px 0;
  }
  
  .contact-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #4a5568;
    font-size: 12.5px;
  }
  
  .vat-id {
    display: inline-block;
    background: #f8f9fa;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11.5px;
    font-weight: 600;
    color: #2c3e50;
    border: 1px solid #e9ecef;
    margin-top: 5px;
  }
  
  .invoice-meta {
    text-align: right;
    padding-left: 30px;
    border-left: 2px solid #e9ecef;
  }
  
  .invoice-meta h3 {
    color: #2c3e50;
    margin: 0 0 20px 0;
    font-size: 26px;
    font-weight: 700;
  }
  
  .invoice-meta p {
    margin: 8px 0;
    font-size: 13.5px;
    color: #4a5568;
  }
  
  .invoice-meta strong {
    color: #2c3e50;
    min-width: 100px;
    display: inline-block;
    font-weight: 600;
  }
  
  /* Bill To Section */
  .bill-to {
    background: #f8fafc;
    padding: 22px 28px;
    border-radius: 10px;
    margin-bottom: 30px;
    border: 1px solid #e2e8f0;
    border-left: 5px solid #3498db;
  }
  
  .bill-to h5 {
    color: #2c3e50;
    font-weight: 700;
    margin-bottom: 15px;
    font-size: 17px;
  }
  
  .bill-to p {
    margin: 7px 0;
    color: #4a5568;
    font-size: 14px;
  }
  
  /* Items Table */
  .items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
  }
  
  .items-table thead {
    background: #2c3e50;
  }
  
  .items-table th {
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
  }
  
  .items-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #edf2f7;
    font-size: 13.5px;
    color: #4a5568;
  }
  
  .items-table tbody tr:nth-child(even) {
    background: #fafbfd;
  }
  
  /* SIMPLE STAMP SECTION */
  .stamp-section {
    float: left;
    margin-top: 30px;
    text-align: center;
    clear: both;
  }
  
  .stamp-logo-container {
    width: 140px;
    height: 140px;
    border: 3px solid #2c3e50;
    padding: 10px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    overflow: hidden;
  }
  
  .stamp-logo {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
  }
  
  .stamp-label {
    display: block;
    margin-top: 8px;
    font-size: 11px;
    color: #666;
  }
  
  /* Summary Section */
  .summary-section {
    float: right;
    width: 280px;
    margin-top: 30px;
    background: #f8fafc;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    clear: right;
  }
  
  .summary-table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .summary-table td {
    padding: 10px 0;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13.5px;
    color: #4a5568;
  }
  
  .summary-table tr:last-child td {
    border-bottom: 2px solid #2c3e50;
    font-weight: 700;
    color: #2c3e50;
    font-size: 15px;
  }
  
  .summary-table td:first-child {
    padding-right: 15px;
    color: #2c3e50;
  }
  
  .summary-table td:last-child {
    text-align: right;
    font-weight: 600;
    color: #2c3e50;
  }
  
  /* Signature Section */
  .signature-section {
    clear: both;
    margin-top: 30px;
    padding: 20px 0;
    border-top: 2px dashed #3498db;
  }
  
  .signature-container {
    display: flex;
    justify-content: space-between;
    gap: 30px;
  }
  
  .signature-box {
    flex: 1;
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #e9ecef;
    min-height: 90px;
  }
  
  .signature-line {
    height: 2px;
    background: #2c3e50;
    margin: 15px 0 10px;
  }
  
  .signature-label {
    font-size: 12px;
    font-weight: 700;
    color: #2c3e50;
  }
  
  .signature-date {
    font-size: 11px;
    color: #666;
    margin-top: 5px;
  }
  
  /* Terms & Conditions */
  .terms-section {
    clear: both;
    margin-top: 30px;
    padding-top: 15px;
    border-top: 2px solid #e9ecef;
  }
  
  .terms-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
  }
  
  .terms-column {
    padding: 15px;
    background: #f8fafc;
    border-radius: 8px;
    min-height: 180px;
  }
  
  .terms-column.english {
    border-left: 4px solid #3498db;
  }
  
  .terms-column.arabic {
    border-right: 4px solid #3498db;
    direction: rtl;
    text-align: right;
  }
  
  .terms-column h6 {
    font-weight: 700;
    margin-bottom: 10px;
    font-size: 14px;
    color: #2c3e50;
  }
  
  .terms-column p {
    margin: 0;
    font-size: 11px;
    line-height: 1.6;
    color: #4a5568;
  }
  
  /* Footer */
  .footer-note {
    clear: both;
    text-align: center;
    margin-top: 25px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
    font-size: 12px;
    color: #718096;
  }
  
  /* Action Buttons */
  .action-buttons {
    text-align: center;
    margin-top: 40px;
    padding-top: 25px;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
  }
  
  .action-buttons button {
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  
  .action-buttons .btn-primary {
    background: #2c3e50;
    color: white;
  }
  
  .action-buttons .btn-secondary {
    background: #718096;
    color: white;
  }
  
  .action-buttons .btn-warning {
    background: #f59e0b;
    color: white;
  }
  
  .action-buttons .btn-danger {
    background: #ef4444;
    color: white;
  }
  
  .action-buttons .btn-outline-primary {
    background: transparent;
    border: 2px solid #2c3e50;
    color: #2c3e50;
  }
  
  .action-buttons .btn-outline-secondary {
    background: transparent;
    border: 2px solid #718096;
    color: #718096;
  }

  /* PRINT OPTIMIZATION - ONE PAGE FIX */
  @media print {
    @page {
        size: A4;
        margin: 8mm !important;
    }
    
    body {
        padding: 0 !important;
        background: white !important;
    }
    
    .container {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    body * {
        visibility: hidden;
    }
    
    #invoice, #invoice * {
        visibility: visible;
    }
    
    #invoice {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 0;
        box-shadow: none;
        border: none;
        background: white;
    }
    
    .no-print, .action-buttons {
        display: none !important;
    }
    
    /* Print-specific adjustments */
    .invoice-container {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 10px 15px !important;
        box-shadow: none !important;
        border: none !important;
    }
    
    /* Header section */
    .company-info h1 {
        font-size: 20px !important;
        margin-bottom: 2px !important;
    }
    
    .company-info .h5 {
        font-size: 12px !important;
        margin-bottom: 8px !important;
    }
    
    .company-info p {
        font-size: 9px !important;
        line-height: 1.1 !important;
    }
    
    .company-address {
        font-size: 8px !important;
        margin: 5px 0 !important;
        line-height: 1.1 !important;
        padding-left: 8px !important;
    }
    
    .contact-item {
        font-size: 8px !important;
        gap: 3px !important;
    }
    
    .vat-id {
        font-size: 9px !important;
        padding: 2px 6px !important;
    }
    
    .invoice-meta h3 {
        font-size: 18px !important;
        margin-bottom: 12px !important;
    }
    
    .invoice-meta p {
        font-size: 9px !important;
        margin: 4px 0 !important;
    }
    
    /* Bill To section */
    .bill-to {
        padding: 10px 15px !important;
        margin-bottom: 15px !important;
    }
    
    .bill-to h5 {
        font-size: 12px !important;
        margin-bottom: 8px !important;
    }
    
    .bill-to p {
        font-size: 10px !important;
        margin: 4px 0 !important;
    }
    
    /* Items Table */
    .items-table {
        margin-bottom: 15px !important;
    }
    
    .items-table th,
    .items-table td {
        padding: 6px 8px !important;
        font-size: 10px !important;
    }
    
    /* Stamp Section */
    .stamp-section {
        margin-top: 15px !important;
        float: left;
        text-align: center;
        clear: both;
    }
    
    .stamp-logo-container {
        width: 100px !important;
        height: 100px !important;
        padding: 5px !important;
        border: 2px solid #000 !important;
        overflow: hidden !important;
    }
    
    .stamp-logo {
        max-width: 100% !important;
        max-height: 100% !important;
        width: auto !important;
        height: auto !important;
        object-fit: contain !important;
    }
    
    .stamp-label {
        font-size: 9px !important;
        margin-top: 4px !important;
    }
    
    /* Summary Section */
    .summary-section {
        width: 220px !important;
        margin-top: 15px !important;
        padding: 10px !important;
        float: right;
        background: #f8fafc !important;
        border-radius: 8px !important;
        border: 1px solid #e2e8f0 !important;
        clear: right;
    }
    
    .summary-table td {
        padding: 4px 0 !important;
        font-size: 10px !important;
    }
    
    /* Signature section */
    .signature-section {
        margin-top: 15px !important;
        padding: 8px 0 !important;
        border-top: 1px solid #ccc !important;
    }
    
    .signature-container {
        gap: 10px !important;
    }
    
    .signature-box {
        padding: 5px !important;
        min-height: 70px !important;
    }
    
    .signature-line {
        margin: 12px 0 6px !important;
    }
    
    .signature-label {
        font-size: 8px !important;
    }
    
    .signature-date {
        font-size: 7px !important;
    }
    
    /* Terms & Conditions */
    .terms-section {
        clear: both;
        margin-top: 15px !important;
        padding-top: 8px !important;
        border-top: 1px solid #ccc !important;
    }
    
    .terms-grid {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 8px !important;
    }
    
    .terms-column {
        padding: 6px !important;
        min-height: 140px !important;
        font-size: 6.5px !important;
    }
    
    .terms-column h6 {
        font-size: 8px !important;
        margin-bottom: 4px !important;
    }
    
    .terms-column p {
        font-size: 6.5px !important;
        line-height: 1.1 !important;
    }
    
    /* Footer */
    .footer-note {
        margin-top: 15px !important;
        padding-top: 8px !important;
        font-size: 7px !important;
    }
  }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>

<body class="bg-light">
  <div class="container py-4">
    <!-- Header with Back Button -->
    <div class="mb-4 p-3 bg-dark text-white rounded">
      <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0">CyberGuardX Invoice Preview</h4>
        <div>
          <a href="dashboard.php" class="text-white me-3">← Dashboard</a>
          <a href="create-invoice.php" class="text-white">➕ New Invoice</a>
        </div>
      </div>
      <p class="mb-0 mt-2">Logged in as: <?php echo htmlspecialchars($_SESSION['full_name']); ?> | Created by: <?php echo htmlspecialchars($invoice['sales_person']); ?></p>
    </div>
    
    <div class="invoice-container" id="invoice">
      <!-- Header Section -->
      <div class="invoice-header">
        <div class="company-info">
          <h1>CYBERGUARDX</h1>
          <div class="h5">Integrated Solutions LLC</div>
          
          <div class="company-address">
            <div><strong>Cairo Office:</strong> 42 El-Bahr Street, Sheraton, Cairo, Egypt</div>
            <div><strong>Alexandria Office:</strong> 16 Mahmoud Fargaly, San Stafano, Alexandria, Egypt</div>
          </div>
          
          <div class="company-contact">
            <div class="contact-item">
              <span>Mobile: +20 15 59907901</span>
            </div>
            <div class="contact-item">
              <span>Mobile: +20 15 59907968</span>
            </div>
            <div class="contact-item">
              <span>Email: info@cyberguardx.org</span>
            </div>
          </div>
          
          <div class="vat-id">VAT ID: 772-165-114</div>
        </div>
        
        <div class="invoice-meta">
          <h3>TAX INVOICE</h3>
          <p><strong>Invoice No:</strong> <span id="invNo"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span></p>
          <p><strong>Date:</strong> <span id="invDate"><?php echo date('F j, Y', strtotime($invoice['issue_date'])); ?></span></p>
          <p><strong>Due Date:</strong> <span id="dueDate"><?php echo date('F j, Y', strtotime($invoice['due_date'])); ?></span></p>
        </div>
      </div>

      <!-- Bill To Section -->
      <div class="bill-to">
        <h5>BILL TO:</h5>
        <p><strong id="bName"><?php echo htmlspecialchars($bill_name); ?></strong></p>
        <p>Email: <span id="bEmail"><?php echo htmlspecialchars($bill_email); ?></span></p>
        <p>Phone: <span id="bPhone"><?php echo htmlspecialchars($bill_phone); ?></span></p>
        <?php if (!empty($invoice['client_tax'])): ?>
        <p>Tax Number: <span id="bTax"><?php echo htmlspecialchars($invoice['client_tax']); ?></span></p>
        <?php endif; ?>
        <?php if (!empty($invoice['client_address'])): ?>
        <p>Address: <span id="bAddress"><?php echo htmlspecialchars($invoice['client_address']); ?></span></p>
        <?php endif; ?>
      </div>

      <!-- Items Table -->
      <div class="items-table-section">
        <table class="items-table">
          <thead>
            <tr>
              <th width="50%">ITEM DESCRIPTION</th>
              <th width="15%">QTY</th>
              <th width="15%">UNIT PRICE</th>
              <th width="20%">TOTAL (EGP)</th>
            </tr>
          </thead>
          <tbody id="itemsBody">
            <?php foreach ($items as $item): ?>
            <tr>
              <td><?php echo htmlspecialchars($item['description']); ?></td>
              <td><?php echo number_format($item['quantity'], 2); ?></td>
              <td><?php echo number_format($item['unit_price'], 2); ?> EGP</td>
              <td><?php echo number_format($item['total'], 2); ?> EGP</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" style="text-align: right; font-size: 12px; color: #666;">
                Total Items: <?php echo count($items); ?>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- SIMPLE STAMP SECTION (Just logo in a box) -->
      <div class="stamp-section">
        <div class="stamp-logo-container">
          <?php 
          // Logo is in /public/assets/images/logo.png
          $logo_path = __DIR__ . '/assets/images/logo.png';
          if (file_exists($logo_path)): 
          ?>
            <img src="assets/images/logo.png" alt="CyberGuardX Logo" class="stamp-logo">
          <?php else: ?>
            <div style="font-size: 14px; font-weight: bold; color: #2c3e50;">
              CYBERGUARDX<br>
              <small style="font-size: 10px;">Logo not found at: <?php echo htmlspecialchars($logo_path); ?></small>
            </div>
          <?php endif; ?>
        </div>
        <span class="stamp-label">Official Company Stamp</span>
      </div>

      <!-- Summary Section -->
      <div class="summary-section">
        <table class="summary-table">
          <tr>
            <td>Subtotal:</td>
            <td><?php echo number_format($invoice['subtotal'], 2); ?> EGP</td>
          </tr>
          <?php if ($invoice['discount'] > 0): ?>
          <tr>
            <td>Discount:</td>
            <td>- <?php echo number_format($invoice['discount'], 2); ?> EGP</td>
          </tr>
          <tr>
            <td>Net Amount:</td>
            <td><?php echo number_format($invoice['net'], 2); ?> EGP</td>
          </tr>
          <?php endif; ?>
          <?php if ($invoice['vat'] > 0): ?>
          <tr>
            <td>VAT (14%):</td>
            <td><?php echo number_format($invoice['vat'], 2); ?> EGP</td>
          </tr>
          <?php endif; ?>
          <tr>
            <td><strong>Total Amount:</strong></td>
            <td><strong><?php echo number_format($invoice['total'], 2); ?> EGP</strong></td>
          </tr>
          <?php if ($invoice['payment_amount'] > 0): ?>
          <tr>
            <td>Payment Made:</td>
            <td>- <?php echo number_format($invoice['payment_amount'], 2); ?> EGP</td>
          </tr>
          <tr>
            <td><strong>Balance Due:</strong></td>
            <td><strong><?php echo number_format($invoice['balance'], 2); ?> EGP</strong></td>
          </tr>
          <?php endif; ?>
        </table>
      </div>

      <!-- Customer Signature Section -->
      <div class="signature-section">
        <div class="signature-container">
          <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-label">Customer Signature</div>
            <div class="signature-date">Date: ______________</div>
          </div>
          <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-label">Company Authorized Signature</div>
            <div class="signature-date">Date: ______________</div>
          </div>
        </div>
      </div>

      <!-- Terms & Conditions -->
      <div class="terms-section">
        <div class="terms-grid">
          <div class="terms-column english">
            <h6>Terms & Conditions:</h6>
            <p>
              1. Course start dates will be scheduled within 15–45 days from booking.<br>
              2. Date changes require 12 days prior notice.<br>
              3. Certificates require 75% attendance.<br>
              4. Instructor may change in force majeure cases.<br>
              5. Non-payment may prevent attendance.<br>
              6. Missed sessions may be compensated.<br>
              7. Refunds within 48 hours minus 20%.<br>
              8. Postponement up to 60 days with 50% payment.<br>
              9. No refunds after postponement.<br>
              10. No refunds after course start notification.
            </p>
          </div>
          
          <div class="terms-column arabic">
            <h6>الشروط والأحكام:</h6>
            <p>
              1. يتم تحديد مواعيد بداية الدورة خلال 15 إلى 45 يومًا من تاريخ الحجز.<br>
              2. يجوز تغيير موعد الدورة بشرط التقديم قبل البداية بـ 12 يومًا.<br>
              3. يشترط حضور 75% للحصول على الشهادة.<br>
              4. يحق للشركة تغيير المحاضر في الحالات القهرية.<br>
              5. عدم السداد يمنع الحضور.<br>
              6. يمكن تعويض المحاضرات حسب الإمكانيات.<br>
              7. الاسترداد خلال 48 ساعة بعد خصم 20%.<br>
              8. يجوز التأجيل حتى 60 يومًا عند سداد 50%.<br>
              9. لا يُسمح بالاسترداد بعد التأجيل.<br>
              10. لا يُسمح بالاسترداد بعد إخطار موعد البدء.
            </p>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="footer-note">
        <p>Thank you for your business! | CYBERGUARDX Integrated Solutions LLC</p>
        <p style="font-size: 11px; color: #999;">This is an official tax invoice | VAT included where applicable</p>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons no-print">
      <button class="btn btn-primary" onclick="downloadPDF()">
        📥 Download PDF
      </button>
      <button class="btn btn-secondary" onclick="window.print()">
        🖨️ Print Invoice
      </button>
      
      <?php if ($can_edit): ?>
      <button class="btn btn-warning" onclick="window.location.href='edit-invoice.php?id=<?php echo $invoice_id; ?>'">
        ✏️ Edit Invoice
      </button>
      <?php endif; ?>
      
      <?php if ($can_delete): ?>
      <button class="btn btn-danger" onclick="confirmDelete(<?php echo $invoice_id; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>')">
        🗑️ Delete Invoice
      </button>
      <?php endif; ?>
      
      <button class="btn btn-outline-primary" onclick="window.location.href='create-invoice.php'">
        ➕ Create New Invoice
      </button>
      <button class="btn btn-outline-secondary" onclick="window.location.href='dashboard.php'">
        🏠 Dashboard
      </button>
    </div>
  </div>

  <script>
  function downloadPDF() {
    const element = document.getElementById('invoice');
    const invoiceNo = document.getElementById('invNo').textContent;
    const filename = `CyberGuardX_Invoice_${invoiceNo}.pdf`;
    
    const opt = {
      margin: [5, 5, 5, 5],
      filename: filename,
      image: { 
        type: 'jpeg', 
        quality: 0.98 
      },
      html2canvas: { 
        scale: 2,
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff'
      },
      jsPDF: { 
        unit: 'mm', 
        format: 'a4', 
        orientation: 'portrait' 
      }
    };
    
    const originalButton = document.querySelector('.btn-primary');
    const originalText = originalButton.innerHTML;
    originalButton.innerHTML = '⏳ Generating PDF...';
    originalButton.disabled = true;
    
    setTimeout(() => {
      html2pdf().set(opt).from(element).save().then(() => {
        originalButton.innerHTML = originalText;
        originalButton.disabled = false;
      }).catch(error => {
        console.error("PDF generation failed:", error);
        alert("PDF generation failed. Please try the print option instead.");
        originalButton.innerHTML = originalText;
        originalButton.disabled = false;
      });
    }, 500);
  }
  
  function confirmDelete(invoiceId, invoiceNumber) {
    if (confirm(`Are you sure you want to delete invoice ${invoiceNumber}?\n\nYou'll be taken to a confirmation page.`)) {
      window.location.href = `delete-invoice.php?id=${invoiceId}`;
    }
  }
  </script>
</body>
</html>