<?php
header("Content-Type: application/json; charset=utf-8");
date_default_timezone_set("America/Los_Angeles");

$team = ["Naina", "Dante", "Hisham"];
$out = [
  "message" => "Hello from the team!",
  "team" => $team,
  "language" => "PHP",
  "generated_at" => date("c"),
  "ip" => $_SERVER["REMOTE_ADDR"] ?? "unknown",
];
echo json_encode($out, JSON_PRETTY_PRINT);
