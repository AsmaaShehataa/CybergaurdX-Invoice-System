CyberGuardX Invoice Management System - Complete Documentation
https://img.shields.io/badge/CyberGuardX-Invoice%2520System-blue
https://img.shields.io/badge/PHP-7.4%252B-777BB4
https://img.shields.io/badge/MySQL-8.0-4479A1
https://img.shields.io/badge/License-MIT-green

📋 Table of Contents
System Overview

Features

Prerequisites

Installation Guide

Database Setup

Configuration

User Roles & Permissions

File Structure

Usage Guide

API Reference

Troubleshooting

Security Features

Contributing

License

🚀 System Overview
CyberGuardX Invoice System is a professional, Egyptian VAT-compliant invoice management solution designed for businesses needing robust invoicing capabilities with multi-user support. The system features role-based access control, real-time calculations, PDF generation, and comprehensive reporting.

Core Technology Stack:

Backend: PHP 7.4+ with MySQLi

Frontend: Bootstrap 5, JavaScript (Vanilla)

Authentication: PHP Sessions with password hashing

PDF Generation: html2pdf.js

Database: MySQL 8.0+

✨ Features
🎯 Core Features
✅ Multi-user Role System (Admin/Sales)

✅ Professional Invoice Creation with Egyptian VAT (14%)

✅ Real-time Calculations (subtotal, discount, VAT, balance)

✅ PDF Generation & Printing optimized for A4

✅ Client Management with auto-fill capabilities

✅ Invoice Status Tracking (Draft → Sent → Paid → Cancelled)

👥 User Management
✅ Admin Panel: Full user CRUD operations

✅ Sales Rep Isolation: Users only see their own invoices

✅ Active/Inactive User Toggle

✅ Secure Password Hashing (bcrypt)

✅ Self-protection: Admins cannot delete themselves

📊 Invoice Management
✅ Dynamic Item Rows (add/remove)

✅ Charge/Deduction Items (positive/negative amounts)

✅ Discount Types: Percentage or Fixed Amount

✅ Payment Tracking with balance calculation

✅ Advanced Filtering by date, status, sales rep

✅ CSV Export (Admin only)

🔒 Security
✅ SQL Injection Prevention (Prepared Statements)

✅ XSS Protection (htmlspecialchars())

✅ Session Security (HttpOnly, Secure cookies)

✅ Role-based Access Control

✅ Input Validation & Sanitization

📋 Prerequisites
System Requirements
Web Server: Apache 2.4+ or Nginx

PHP: 7.4 or higher

MySQL: 8.0 or higher

Extensions: mysqli, session, mbstring, gd (for PDF)

Browser: Chrome 80+, Firefox 75+, Safari 13+

PHP Extensions Required
# Ubuntu/Debian
sudo apt-get install php-mysql php-session php-mbstring php-gd

# macOS (Homebrew)
brew install php@7.4
brew services start php@7.4

# Windows (XAMPP/WAMP)
# Enable extensions in php.ini:
extension=mysqli
extension=session
extension=mbstring
extension=gd


Installation Guide
Step 1: Clone/Download the Project

# Clone repository
git clone https://github.com/yourusername/cyberguardx-invoice-system.git
cd cyberguardx-invoice-system

# OR download ZIP and extract
# Place in your web server directory:
# - Linux/Mac: /var/www/html/
# - Windows: C:\xampp\htdocs\
# - MAMP: /Applications/MAMP/htdocs/

# Linux/Mac
chmod 755 -R ./
chmod 777 /tmp  # Session directory for macOS

Step 2: Set Permissions
# Create uploads directory if needed
mkdir -p uploads/invoices
chmod 777 uploads/invoices

Step 3: Configure Web Server
Apache (.htaccess)

# Create .htaccess in root directory
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?/$1 [L]

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"


User Roles & Permissions
Administrator (role: 'admin')
Full System Access:

✅ Create/edit/delete all invoices

✅ Manage all clients

✅ Create/edit/delete users

✅ View all sales reports

✅ Export data to CSV

✅ Database administration

✅ Filter invoices by sales representative



Sales Representative (role: 'sales')
Limited Access:

✅ Create new invoices

✅ View ONLY their own invoices

✅ Edit ONLY their own invoices

✅ Manage clients they created

❌ Cannot delete invoices

❌ Cannot view other sales reps' invoices

❌ Cannot access user management

❌ Cannot view reports

❌ Cannot export data

📁 File Structure
text
cyberguardx-invoice-system/
├── index.php                      # Login page
├── dashboard.php                  # Main dashboard
├── create-invoice.php             # Invoice creation form
├── save-invoice.php               # Invoice saving backend
├── invoices.php                   # Invoice listing & management
├── view-invoice.php               # Invoice preview/PDF
├── edit-invoice.php               # Invoice editing
├── update-status.php              # Status updates
├── users.php                      # User management (Admin only)
├── create-user.php                # Create user (Admin only)
├── edit-user.php                  # Edit user (Admin only)
├── logout.php                     # Logout handler
├── check-users.php                # Database check utility
├── .htaccess                      # Apache configuration
├── logo.png                       # Company logo (optional)
│
├── includes/                      # Core system files
│   ├── config.php                 # Database configuration
│   └── auth.php                   # Authentication helpers
│
├── uploads/                       # Upload directory (optional)
│   └── invoices/                  # Generated invoices storage
│
└── README.md                      # This file
📖 Usage Guide
1. First-Time Setup
Access the system: Open http://localhost/cyberguardx-invoice-system/



Create sales users: Dashboard → Manage Users → Create New User

Provide credentials to sales team

2. Creating an Invoice
Step-by-Step:

Login → Click "Create Invoice"

Client Information:

Enter client name, email, phone, address

Bill-to information auto-fills from client info

Add Items:

Click "+ Add Item" for each product/service

Choose: Charge (+) or Deduction (-)

Enter description, quantity, unit price

Apply Discount (optional):

Select: None, Percentage %, or Fixed Amount

Enter discount value

Payment Information:

Enter any payment already received

Review Summary:

System calculates: Subtotal → Discount → Net → VAT (14%) → Total → Balance

Generate Invoice:

Click "Generate Invoice & Save to Database"

System saves and redirects to PDF view

3. Managing Invoices
For Sales Users:
View only their invoices

Filter by status (Draft, Sent, Paid, Cancelled)

Change status: Draft → Sent → Paid

Download PDF or Print

For Administrators:
View ALL invoices

Filter by sales representative

Export to CSV

Delete invoices

View performance statistics

4. User Management (Admin Only)
Creating a new user:

Dashboard → Manage Users → Create New User

Fill: Username, Password, Full Name, Role

System automatically hashes password

Editing users:

Change full name, role, status

Reset passwords

Cannot delete yourself (safety feature)

🔌 API Reference (Internal Endpoints)
Authentication Endpoints
Endpoint	Method	Description	Access
/index.php	GET	Login page	Public
/index.php	POST	Login authentication	Public
/logout.php	GET	Logout & destroy session	Authenticated
Invoice Endpoints
Endpoint	Method	Description	Parameters
/save-invoice.php	POST	Save new invoice	Form data (see below)
/update-status.php	GET	Update invoice status	id, status
/view-invoice.php	GET	View invoice PDF	id
User Management Endpoints (Admin Only)
Endpoint	Method	Description
/users.php?delete_id=X	GET	Delete user
/users.php?toggle_active=X	GET	Toggle user active status
/create-user.php	POST	Create new user
/edit-user.php	POST	Update user
save-invoice.php POST Parameters
javascript
{
  client_name: "string",
  client_email: "string",
  client_phone: "string",
  client_address: "string",
  bill_name: "string",
  bill_email: "string",
  bill_phone: "string",
  offer_type: "none|percent|fixed",
  offer_value: "float",
  payment_amount: "float",
  items: "JSON array of invoice items",
  subtotal: "float",
  discount: "float",
  net: "float",
  vat: "float",
  total: "float",
  balance: "float"
}
🔧 Troubleshooting
Common Issues & Solutions
1. "Session not working" on macOS
php
// Add to top of each PHP file:
ini_set('session.save_path', '/tmp');
session_start();
2. "Connection failed" to MySQL
bash
# Check MySQL service
sudo systemctl status mysql  # Linux
brew services list          # macOS

# Verify credentials in config.php
# Test connection manually:


<!-- Check browser console for errors -->
<!-- Ensure html2pdf.js is loaded -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
5. "ENUM" error when saving invoices
php
// The system has a fix in cleanForEnum() function
// If you see ENUM errors, ensure offer_type is one of:
// 'none', 'percent', 'fixed'
Debug Mode
Enable debug mode in config.php:

php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');
🔒 Security Features
Implemented Security Measures
Password Hashing: password_hash() with bcrypt

SQL Injection Prevention: Prepared statements throughout

XSS Protection: htmlspecialchars() on all outputs

CSRF Protection: Consider adding tokens for production

Session Security: HttpOnly, Secure cookies

Input Validation: Server-side validation on all forms

Role-based Access: Strict permission checks

File Upload Security: Validation (if implemented)

Production Security Checklist
php
// 1. Change default credentials
define('DB_PASS', 'StrongPassword123!@#');

// 2. Enable HTTPS
// 3. Set secure session cookies
// 4. Disable error reporting in production
// 5. Regular database backups
// 6. Implement rate limiting
// 7. Add CSRF tokens to forms
// 8. Set up firewall (WAF)
📈 Performance Optimization
Database Optimization
sql
-- Add indexes for better performance
CREATE INDEX idx_invoices_user_id ON invoices(user_id);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_invoices_created_at ON invoices(created_at);
CREATE INDEX idx_invoice_items_invoice_id ON invoice_items(invoice_id);
PHP Optimization
php
// Enable OpCache in php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
Frontend Optimization
Minify CSS/JavaScript in production

Use CDN for Bootstrap and Font Awesome

Implement browser caching

🤝 Contributing
Development Setup
bash
# 1. Fork the repository
# 2. Clone your fork
git clone https://github.com/your-username/cyberguardx-invoice-system.git

# 3. Create feature branch
git checkout -b feature/new-feature

# 4. Make changes and test
# 5. Commit changes
git commit -m "Add new feature"

# 6. Push to branch
git push origin feature/new-feature


