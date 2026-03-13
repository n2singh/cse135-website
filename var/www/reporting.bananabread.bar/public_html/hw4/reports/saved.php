<?php
require_once __DIR__ . "/../includes/auth.php";
require_login();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Saved Reports</title>
    <link rel="stylesheet" href="/hw4/assets/site.css" />
    <link rel="icon" href="/assets/bananabread.ico">
</head>
<body>
<?php include __DIR__ . "/../includes/header.php"; ?>

<div class="container">
    <div class="card">
        <h1>Saved Reports</h1>
        <p>This page contains saved report views available to all logged-in users.</p>

        <ul>
            <li><a href="/hw4/reports/view-performance.php">Saved Performance Report</a></li>
            <li><a href="/hw4/reports/view-behavior.php">Saved Behavior Report</a></li>
        </ul>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>
