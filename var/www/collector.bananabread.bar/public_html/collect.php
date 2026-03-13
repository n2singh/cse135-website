<?php
// ===============================
// CORS HEADERS (robust preflight)
// ===============================

$allowed_origin = "https://test.bananabread.bar";

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $allowed_origin) {
  header("Access-Control-Allow-Origin: $origin");
  header("Vary: Origin");
}

// Allow POST + OPTIONS
header("Access-Control-Allow-Methods: POST, OPTIONS");

// IMPORTANT: echo back whatever headers the browser requests in preflight
$req_headers = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
if ($req_headers !== '') {
  header("Access-Control-Allow-Headers: $req_headers");
} else {
  header("Access-Control-Allow-Headers: Content-Type, Accept");
}

header("Access-Control-Max-Age: 86400");

// Handle preflight
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
  http_response_code(204);
  exit;
}

// Only allow POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  exit;
}

// ===============================
// READ & VALIDATE JSON
// ===============================

$raw = file_get_contents("php://input");
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    exit;
}

$page = $payload["page"] ?? "";
$session_id = $payload["session_id"] ?? "";
$events = $payload["events"] ?? [];

if ($page === "" || $session_id === "" || !is_array($events)) {
    http_response_code(400);
    exit;
}

// ===============================
// HELPER: ISO 8601 -> MySQL DATETIME(3)
// ===============================
function iso8601_to_mysql_datetime3($iso) {
    if (!$iso || !is_string($iso)) {
        return null;
    }

    try {
        // Handles "Z" and timezone offsets correctly
        $dt = new DateTime($iso);

        // Format for MySQL DATETIME(3): "YYYY-MM-DD HH:MM:SS.mmm"
        // PHP 'v' gives milliseconds (000-999)
        return $dt->format('Y-m-d H:i:s.v');
    } catch (Exception $e) {
        return null;
    }
}

// ===============================
// CONNECT TO DATABASE
// ===============================

$cfg = require "/var/www/collector.bananabread.bar/db_config.php";

try {
    $pdo = new PDO(
        $cfg["dsn"],
        $cfg["user"],
        $cfg["pass"],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $stmt = $pdo->prepare("
        INSERT INTO events (session_id, page, event_type, event_ts, data)
        VALUES (:session_id, :page, :event_type, :event_ts, :data)
    ");

    // ===============================
    // LOOP THROUGH EVENTS
    // ===============================

    foreach ($events as $ev) {
        $eventType = $ev["eventType"] ?? "";
        $ts_iso = $ev["ts"] ?? "";

        if ($eventType === "" || $ts_iso === "") {
            continue;
        }

        $event_ts = iso8601_to_mysql_datetime3($ts_iso);
        if ($event_ts === null) {
            // fallback: server time (still DATETIME(3))
            $event_ts = (new DateTime())->format('Y-m-d H:i:s.v');
        }

        $dataJson = null;
        if (array_key_exists("data", $ev)) {
            // Ensure object/array is stored as JSON; if encoding fails, store NULL
            $encoded = json_encode($ev["data"], JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $dataJson = $encoded;
            }
        }

        try {
            $stmt->execute([
                ":session_id" => $session_id,
                ":page" => $page,
                ":event_type" => $eventType,
                ":event_ts" => $event_ts,
                ":data" => $dataJson
            ]);
        } catch (Exception $e) {
            // Log and continue inserting other events
            error_log("collect.php DB insert error: " . $e->getMessage());
        }
    }

    http_response_code(204);
    exit;

} catch (Exception $e) {
    error_log("collect.php fatal error: " . $e->getMessage());
    http_response_code(500);
    exit;
}
