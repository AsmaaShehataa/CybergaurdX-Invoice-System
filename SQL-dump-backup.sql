-- 1. Create the database
CREATE DATABASE IF NOT EXISTS CyberguardX_invoice_system;
CREATE USER IF NOT EXISTS 'CyberguardX'@'localhost' IDENTIFIED BY 'admin2026';
GRANT ALL PRIVILEGES ON CyberguardX_invoice_system . * TO 'CyberguardX'@'localhost';
FLUSH PRIVILEGES;
GRANT SELECT ON  `performance_schema`.* TO 'CyberguardX'@'localhost';

FLUSH PRIVILEGES;


USE CyberguardX_invoice_system;


-- Users table (sales staff)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255), -- Hashed!
    full_name VARCHAR(100),
    role ENUM('sales', 'admin') DEFAULT 'sales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Clients table
CREATE TABLE clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200),
    email VARCHAR(200),
    phone VARCHAR(50),
    address TEXT,
    tax_number VARCHAR(100),
    created_by INT, -- Which user added this client
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Invoices table
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50) UNIQUE, -- e.g., INV-2024-001
    user_id INT, -- Who created it
    client_id INT, -- Which client
    issue_date DATE,
    due_date DATE,
    subtotal DECIMAL(10,2),
    vat DECIMAL(10,2),
    total DECIMAL(10,2),
    status ENUM('draft', 'sent', 'paid', 'cancelled') DEFAULT 'draft',
    notes TEXT,
    terms_conditions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- Invoice items table
CREATE TABLE invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT,
    description TEXT,
    quantity DECIMAL(10,2),
    unit_price DECIMAL(10,2),
    total DECIMAL(10,2),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);