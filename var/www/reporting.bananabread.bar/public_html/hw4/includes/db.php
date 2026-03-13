<?php
// hw4/includes/db.php
// Reuse the same DB config used by reporting/api/static.php
$cfg = require "/var/www/collector.bananabread.bar/db_config.php";

try {
  $pdo = new PDO(
    $cfg["dsn"],
    $cfg["user"],
    $cfg["pass"],
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  echo "Database connection failed.";
  exit;
}
