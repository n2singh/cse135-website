<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
require_once __DIR__ . "/includes/auth.php";
require_login();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>HW4 Home</title>
    <link rel="icon" href="/assets/bananabread.ico">
    <link rel="stylesheet" href="/hw4/assets/site.css" />
</head>
<body>
    <?php include __DIR__ . "/includes/header.php"; ?>

    <div class="container">
        <div class="card">
            <h1>Backend Analystics and Reports</h1>

            <?php if ($_SESSION["user"]["role"] === "super_admin"): ?>
                <p class="small">You have full access to all analytics pages and saved reports.</p>
                <ul>
                    <li><a href="/hw4/reports/charts.php">Performance Chart (HW4)</a></li>
                    <li><a href="/hw4/reports/table.php">Behavior Data Table (HW4)</a></li>
                    <li><a href="/hw4/reports/view-performance.php">Performance Report</a></li>
		            <li><a href="/hw4/reports/heatmap.php">Behavior Report</a></li>
		            <li><a href="/hw4/reports/event-scatter.php">Engagement Report</a></li>
		            <li><a href="/hw4/reports/saved.php">Saved Reports</a></li>
                </ul>

            <?php elseif ($_SESSION["user"]["role"] === "analyst"): ?>
                <p class="small">You can access your assigned analytics sections and saved reports.</p>
                <ul>
                    <li><a href="/hw4/reports/charts.php">Performance Chart (HW4)</a></li>
                    <li><a href="/hw4/reports/table.php">Behavior Data Table (HW4)</a></li>
                    <li><a href="/hw4/reports/view-behavior.php">Performance Report</a></li>
                    <li><a href="/hw4/reports/heatmap.php">Behavior Report</a></li>
                    <li><a href="/hw4/reports/event-scatter.php">Engagement Report</a></li>
                    <li><a href="/hw4/reports/saved.php">Saved Reports</a></li>
                </ul>

            <?php else: ?>
                <p class="small">You are logged in as a viewer. You can only access to saved reports.</p>
                <ul>
                    <li><a href="/hw4/reports/saved.php">View Saved Reports</a></li>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . "/includes/footer.php"; ?>
</body>
</html>
