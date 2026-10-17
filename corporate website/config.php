<?php
// ─── Database Configuration ───────────────────────────────────────────────
define('DB_HOST', 'localhost');       // usually localhost on shared hosts
define('DB_PORT', 3306);
define('DB_NAME', 'sapienstech');   // your database name
define('DB_USER', 'sapien');   // your database username
define('DB_PASS', 'L2yeWr_hV3zTEkze');   // your database password
define('DB_CHARSET', 'utf8mb4');

// ─── CORS: set this to your actual domain ─────────────────────────────────
define('ALLOWED_ORIGIN', 'localhost/promo4');

// ─── SQL to create the leads table (run once) ─────────────────────────────
// CREATE TABLE leads (
//   id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
//   name        VARCHAR(120)  NOT NULL,
//   phone       VARCHAR(30)   NOT NULL,
//   source      VARCHAR(60)   NOT NULL DEFAULT 'modal',   -- 'modal' or 'footer'
//   created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
