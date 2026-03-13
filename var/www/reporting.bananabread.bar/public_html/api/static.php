<?php
// reporting/api/static.php
// REST endpoint starter: uses collector_db.events as the "static" dataset for Part 5 demo.

header("Content-Type: application/json; charset=utf-8");

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

// Parse ID from /api/static.php/{id} if present
$path = $_SERVER["REQUEST_URI"] ?? "";
// Example: /api/static.php/123
$id = null;
if (preg_match('#/api/static\.php/(\d+)$#', $path, $m)) {
  $id = intval($m[1]);
}

// ---- DB connect (reuse your collector db config) ----
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
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["error" => "DB connect failed"]);
  exit;
}

// ---- Helpers ----
function read_json_body() {
  $raw = file_get_contents("php://input");
  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}

// ---- ROUTES ----
// GET /api/static.php          => all rows (limit 200)
// GET /api/static.php/{id}     => one row
// POST /api/static.php         => insert a new row
// PUT /api/static.php/{id}     => update an existing row
// DELETE /api/static.php/{id}  => delete a row

if ($method === "GET") {
  if ($id === null) {
    $stmt = $pdo->query("SELECT id, session_id, event_type, page, event_ts, data FROM events ORDER BY id DESC LIMIT 200");
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
    exit;
  } else {
    $stmt = $pdo->prepare("SELECT id, session_id, event_type, page, event_ts, data FROM events WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $row = $stmt->fetch();
    if (!$row) {
      http_response_code(404);
      echo json_encode(["error" => "Not found"]);
      exit;
    }
    echo json_encode($row);
    exit;
  }
}

if ($method === "POST") {
  if ($id !== null) {
    http_response_code(400);
    echo json_encode(["error" => "Do not include id in POST"]);
    exit;
  }

  $body = read_json_body();
  if (!$body) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit;
  }

  // Minimal required fields; you can post any test row
  $session_id = $body["session_id"] ?? "";
  $event_type = $body["event_type"] ?? "";
  $page = $body["page"] ?? "";
  $event_ts = $body["event_ts"] ?? null; // "YYYY-MM-DD HH:MM:SS.mmm"
  $data = $body["data"] ?? null;

  if ($session_id === "" || $event_type === "" || $page === "") {
    http_response_code(400);
    echo json_encode(["error" => "Missing session_id/event_type/page"]);
    exit;
  }

  if ($event_ts === null) {
    // default now(3)
    $event_ts = (new DateTime())->format("Y-m-d H:i:s.v");
  }

  $dataJson = ($data === null) ? null : json_encode($data, JSON_UNESCAPED_SLASHES);

  $stmt = $pdo->prepare("
    INSERT INTO events (session_id, page, event_type, event_ts, data)
    VALUES (:sid, :page, :etype, :ets, :data)
  ");
  $stmt->execute([
    ":sid" => $session_id,
    ":page" => $page,
    ":etype" => $event_type,
    ":ets" => $event_ts,
    ":data" => $dataJson
  ]);

  http_response_code(201);
  echo json_encode(["ok" => true, "id" => intval($pdo->lastInsertId())]);
  exit;
}

if ($method === "PUT") {
  if ($id === null) {
    http_response_code(400);
    echo json_encode(["error" => "PUT requires /{id}"]);
    exit;
  }

  $body = read_json_body();
  if (!$body) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit;
  }

  // Allow updating these fields (simple)
  $event_type = $body["event_type"] ?? null;
  $page = $body["page"] ?? null;
  $data = array_key_exists("data", $body) ? $body["data"] : null;

  $fields = [];
  $params = [":id" => $id];

  if ($event_type !== null) { $fields[] = "event_type = :etype"; $params[":etype"] = $event_type; }
  if ($page !== null) { $fields[] = "page = :page"; $params[":page"] = $page; }
  if (array_key_exists("data", $body)) {
    $fields[] = "data = :data";
    $params[":data"] = ($data === null) ? null : json_encode($data, JSON_UNESCAPED_SLASHES);
  }

  if (count($fields) === 0) {
    http_response_code(400);
    echo json_encode(["error" => "Nothing to update"]);
    exit;
  }

  $sql = "UPDATE events SET " . implode(", ", $fields) . " WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  echo json_encode(["ok" => true]);
  exit;
}

if ($method === "DELETE") {
  if ($id === null) {
    http_response_code(400);
    echo json_encode(["error" => "DELETE requires /{id}"]);
    exit;
  }

  $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
  $stmt->execute([":id" => $id]);

  echo json_encode(["ok" => true]);
  exit;
}

// If method not supported:
http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
