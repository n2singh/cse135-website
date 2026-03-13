<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_login();

// Aggregate behavior-style data by page
$stmt = $pdo->query("
    SELECT page, COUNT(*) AS c
    FROM events
    GROUP BY page
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
    <title>Saved Behavior Report</title>
    <link rel="icon" href="/assets/bananabread.ico">
    <link rel="stylesheet" href="/hw4/assets/site.css" />
</head>
<body>
<?php include __DIR__ . "/../includes/header.php"; ?>

<div class="container">
    <div class="card">
        <h1>Saved Behavior Report</h1>
        <p class="small">
            This saved report summarizes user behavior by page activity. It is available to viewers,
            analysts, and super admins.
        </p>

        <p>
            <a class="btn" href="export_view_behavior_pdf.php">Export PDF</a>
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

        <h2>Top Pages by Event Volume</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Event Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row["page"] ?? "(unknown)") ?></td>
                        <td><?= htmlspecialchars((string)$row["c"]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            <h2>Analyst Comment</h2>
            <p>
                This report highlights which pages receive the most tracked activity. It provides a
                simplified behavioral snapshot for viewers while keeping raw backend reporting pages
                restricted to analysts and super admins.
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>
