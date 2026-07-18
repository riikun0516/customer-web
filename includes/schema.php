<?php
/**
 * DBスキーマ定義
 * setup.php（新規セットアップ）と migrate.php（既存環境への追加適用）の両方から使う。
 * 全て CREATE TABLE IF NOT EXISTS のため、何度実行しても安全。
 */

function schema_statements() {
  return [
<<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    role ENUM('admin','general') NOT NULL DEFAULT 'general',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    ,
<<<SQL
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    company VARCHAR(150),
    email VARCHAR(150),
    phone VARCHAR(50),
    address VARCHAR(255),
    memo TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    ,
<<<SQL
CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    status ENUM('未着手','進行中','保留','完了') NOT NULL DEFAULT '未着手',
    assigned_user_id INT,
    amount DECIMAL(12,2) NULL,
    description TEXT,
    due_date DATE NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    ,
<<<SQL
CREATE TABLE IF NOT EXISTS case_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    user_id INT,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    ,
<<<SQL
CREATE TABLE IF NOT EXISTS company_settings (
    id TINYINT PRIMARY KEY DEFAULT 1,
    company_name VARCHAR(150) NOT NULL DEFAULT '',
    logo_path VARCHAR(255) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT '',
    address VARCHAR(255) DEFAULT '',
    tel VARCHAR(50) DEFAULT '',
    email VARCHAR(150) DEFAULT '',
    registration_number VARCHAR(50) DEFAULT '',
    bank_name VARCHAR(100) DEFAULT '',
    branch_name VARCHAR(100) DEFAULT '',
    account_type VARCHAR(20) DEFAULT '普通',
    account_number VARCHAR(50) DEFAULT '',
    account_holder VARCHAR(100) DEFAULT '',
    default_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    invoice_note TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_company_settings_single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    ,
<<<SQL
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) UNIQUE,
    customer_id INT NOT NULL,
    case_id INT NULL,
    status ENUM('未送付','送付済み','支払済み') NOT NULL DEFAULT '未送付',
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    ,
<<<SQL
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    ,
<<<SQL
CREATE TABLE IF NOT EXISTS receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(30) UNIQUE,
    customer_id INT NOT NULL,
    case_id INT NULL,
    issue_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    description VARCHAR(255) DEFAULT '',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    ,
<<<SQL
CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(30) UNIQUE,
    customer_id INT NOT NULL,
    case_id INT NULL,
    status ENUM('下書き','送付済み','受注','失注') NOT NULL DEFAULT '下書き',
    issue_date DATE NOT NULL,
    valid_until DATE NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    ,
<<<SQL
CREATE TABLE IF NOT EXISTS quote_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
  ];
}

/**
 * 既存環境向けのカラム追加（ALTER TABLE）。
 * CREATE TABLE IF NOT EXISTS では既存テーブルに新しい列を追加できないため、
 * こちらは 1文ずつ実行し、「列が既に存在する」エラー(42S21)だけは無視する。
 */
function migration_statements() {
  return [
    "ALTER TABLE cases ADD COLUMN amount DECIMAL(12,2) NULL AFTER assigned_user_id",
    "ALTER TABLE company_settings ADD COLUMN logo_path VARCHAR(255) DEFAULT NULL AFTER company_name",
  ];
}

/**
 * migration_statements() を安全に実行する（列が既にあればスキップ）
 */
function run_migrations($pdo) {
  $applied = [];
  $skipped = [];
  foreach (migration_statements() as $stmt) {
    try {
      $pdo->exec($stmt);
      $applied[] = $stmt;
    } catch (PDOException $ex) {
      // 42S21 = Duplicate column name（既に適用済み）
      if ($ex->getCode() === '42S21' || strpos($ex->getMessage(), 'Duplicate column') !== false) {
        $skipped[] = $stmt;
      } else {
        throw $ex;
      }
    }
  }
  return ['applied' => $applied, 'skipped' => $skipped];
}
