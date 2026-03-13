<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_login();

// Aggregate the same performance-style data used in charts.php
$stmt = $pdo->query("
    SELECT event_type, COUNT(*) AS c
    FROM events
    GROUP BY event_type
    ORDER BY c DESC
    LIMIT 10
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdfUrl = $_GET["pdf"] ?? "";
$pdfError = $_GET["error"] ?? "";
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Saved Performance Report</title>
    <link rel="icon" href="/assets/bananabread.ico">
    <link rel="stylesheet" href="/hw4/assets/site.css" />
</head>
<body>
<?php include __DIR__ . "/../includes/header.php"; ?>

<div class="container">
    <div class="card">
        <h1>Saved Performance Report</h1>
        <p class="small">
            This saved report summarizes performance-related analytics. It is available to viewers,
            analysts, and super admins.
        </p>

        <p>
            <a class="btn" href="export_view_performance_pdf.php">Export PDF</a>
        </p>

        <?php if ($pdfUrl !== ""): ?>
            <p class="small">
                PDF created:
                <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener">Open report</a>
            </p>
        <?php endif; ?>

        <?php if ($pdfError !== ""): ?>
            <p class="small">Export failed: <?= htmlspecialchars($pdfError) ?></p>
        <?php endif; ?>

        <h2>Top Event Types</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Event Type</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row["event_type"] ?? "(null)") ?></td>
                        <td><?= htmlspecialchars((string)$row["c"]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            <h2>Analyst Comment</h2>
            <p>
                The system is collecting event activity successfully. The most frequent event types
                indicate which user interactions occur most often across the test site. This saved
                report gives viewers a simple summary without exposing the full backend dashboard.
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>
