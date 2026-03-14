<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth.php";

require_login();
require_role(["super_admin", "analyst"]);

header("Content-Type: application/json");

try {
    if (!isset($_FILES["chart_image"]) || $_FILES["chart_image"]["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("No chart image uploaded.");
    }

    $tmpDir = __DIR__ . "/../exports/tmp_charts";
    if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true)) {
        throw new Exception("Could not create temp chart directory.");
    }

    if (!is_writable($tmpDir)) {
        throw new Exception("Temp chart directory is not writable.");
    }

    $filename = "chart_" . bin2hex(random_bytes(8)) . ".jpg";
    $targetPath = $tmpDir . "/" . $filename;

    if (!move_uploaded_file($_FILES["chart_image"]["tmp_name"], $targetPath)) {
        throw new Exception("Failed to save uploaded chart image.");
    }

    echo json_encode([
        "success" => true,
        "filename" => $filename
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
    exit;
}
