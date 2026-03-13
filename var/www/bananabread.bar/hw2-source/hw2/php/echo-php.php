<?php
header("Content-Type: application/json; charset=utf-8");
date_default_timezone_set("America/Los_Angeles");

$method = $_SERVER["REQUEST_METHOD"] ?? "UNKNOWN";
$ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
$ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
$host = gethostname();

$raw = file_get_contents("php://input");
$ct = $_SERVER["CONTENT_TYPE"] ?? "";

$data = null;
if (stripos($ct, "application/json") !== false) {
  $data = json_decode($raw, true);
} else {
  $data = $_REQUEST;
}

$out = [
  "language" => "PHP",
  "endpoint" => "echo-php",
  "method" => $method,
  "content_type" => $ct,
  "hostname" => $host,
  "time" => date("c"),
  "ip" => $ip,
  "user_agent" => $ua,
  "received" => $data,
  "raw_body" => $raw,
];
echo json_encode($out, JSON_PRETTY_PRINT);
