<?php
header("Content-Type: text/html; charset=utf-8");
date_default_timezone_set("America/Los_Angeles");

$ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
$now = date("r");
$team = "Naina, Dante, Hisham";
?>
<!doctype html>
<html>
<head>
  <script src="https://cdn.logr-in.com/LogRocket.min.js" crossorigin="anonymous"></script>
  <script>window.LogRocket && window.LogRocket.init('c2uo5x/bananabreadbar');</script>
  <meta charset="utf-8"><title>Hello HTML (PHP)</title>
</head>
<body>
  <h1>Hello from <?php echo htmlspecialchars($team); ?>!</h1>
  <p>Language: <b>PHP</b></p>
  <p>Generated at: <b><?php echo htmlspecialchars($now); ?></b></p>
  <p>Your IP address is: <b><?php echo htmlspecialchars($ip); ?></b></p>
</body>
</html>
