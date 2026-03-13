<?php
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$fp = $data['fp'] ?? null;

if (!$fp) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "error" => "Missing fp"
    ]);
    exit;
}

$storeFile = __DIR__ . '/fp_map.json';
$map = [];

if (file_exists($storeFile)) {
    $json = file_get_contents($storeFile);
    $map = $json ? json_decode($json, true) : [];

    if (!is_array($map)) {
        $map = [];
    }
}

if (isset($map[$fp])) {
    $uid = $map[$fp];
    $is_new = false;
} else {
    $uid = 'u_' . bin2hex(random_bytes(6));
    $map[$fp] = $uid;
    $is_new = true;

    file_put_contents(
        $storeFile,
        json_encode($map, JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

setcookie('uid', $uid, [
    'expires' => time() + 60 * 60 * 24 * 365,
    'path' => '/',
    'secure' => true,
    'httponly' => false,
    'samesite' => 'Lax',
]);

echo json_encode([
    "ok" => true,
    "is_new" => $is_new,
    "uid" => $uid,
    "fp" => $fp
]);
