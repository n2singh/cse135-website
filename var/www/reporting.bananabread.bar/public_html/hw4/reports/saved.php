<?php
require_once __DIR__ . "/../includes/auth.php";

require_login();
require_role(["super_admin", "analyst", "viewer"]);

function get_latest_export(string $pattern): ?array {
    $exportDir = realpath(__DIR__ . "/../exports");

    if ($exportDir === false) {
        return null;
    }

    $files = glob($exportDir . "/" . $pattern);
    if (!$files || count($files) === 0) {
        return null;
    }

    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    $latestPath = $files[0];
    $latestName = basename($latestPath);

    return [
        "name" => $latestName,
        "url"  => "/hw4/exports/" . rawurlencode($latestName),
        "time" => date("Y-m-d h:i A", filemtime($latestPath)),
    ];
}

$performanceExport = get_latest_export("view-performance-report-*.pdf");
$behaviorExport    = get_latest_export("heatmap-report-*.pdf");
$engagementExport  = get_latest_export("event-scatter-report-*.pdf");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Saved Reports</title>
    <link rel="icon" href="/assets/bananabread.ico">
    <link rel="stylesheet" href="/hw4/assets/site.css" />
    <style>
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
            margin-top: 18px;
        }

        .report-card {
            background: #121212;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 18px;
        }

        .report-card h2 {
            margin-top: 0;
            margin-bottom: 10px;
        }

        .report-meta {
            color: #aaa;
            font-size: 0.95rem;
            margin-bottom: 12px;
        }

        .report-actions {
            margin-top: 14px;
        }

        .empty-state {
            color: #bbb;
        }
    </style>
</head>
<body>
<?php include __DIR__ . "/../includes/header.php"; ?>

<div class="container">
    <div class="card">
        <h1>Saved Reports</h1>
        <p class="small">
            This page shows the most recent exported PDF for each report type.
        </p>
    </div>

    <div class="reports-grid">
        <div class="report-card">
            <h2>Performance Report</h2>
            <p class="small">
                View the most recent exported Performance Report PDF.
            </p>

            <?php if ($performanceExport): ?>
                <div class="report-meta">
                    <div><strong>Latest file:</strong> <?= htmlspecialchars($performanceExport["name"]) ?></div>
                    <div><strong>Exported:</strong> <?= htmlspecialchars($performanceExport["time"]) ?></div>
                </div>

                <div class="report-actions">
                    <a class="btn" href="<?= htmlspecialchars($performanceExport["url"]) ?>" target="_blank" rel="noopener">
                        Open Latest PDF
                    </a>
                </div>
            <?php else: ?>
                <p class="empty-state">No Performance Report exports found yet.</p>
            <?php endif; ?>
        </div>

        <div class="report-card">
            <h2>Behavioral Report</h2>
            <p class="small">
                View the most recent exported Behavioral Report PDF.
            </p>

            <?php if ($behaviorExport): ?>
                <div class="report-meta">
                    <div><strong>Latest file:</strong> <?= htmlspecialchars($behaviorExport["name"]) ?></div>
                    <div><strong>Exported:</strong> <?= htmlspecialchars($behaviorExport["time"]) ?></div>
                </div>

                <div class="report-actions">
                    <a class="btn" href="<?= htmlspecialchars($behaviorExport["url"]) ?>" target="_blank" rel="noopener">
                        Open Latest PDF
                    </a>
                </div>
            <?php else: ?>
                <p class="empty-state">No Behavioral Report exports found yet.</p>
            <?php endif; ?>
        </div>

        <div class="report-card">
            <h2>Engagement Report</h2>
            <p class="small">
                View the most recent exported Engagement Report PDF.
            </p>

            <?php if ($engagementExport): ?>
                <div class="report-meta">
                    <div><strong>Latest file:</strong> <?= htmlspecialchars($engagementExport["name"]) ?></div>
                    <div><strong>Exported:</strong> <?= htmlspecialchars($engagementExport["time"]) ?></div>
                </div>

                <div class="report-actions">
                    <a class="btn" href="<?= htmlspecialchars($engagementExport["url"]) ?>" target="_blank" rel="noopener">
                        Open Latest PDF
                    </a>
                </div>
            <?php else: ?>
                <p class="empty-state">No Engagement Report exports found yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>
