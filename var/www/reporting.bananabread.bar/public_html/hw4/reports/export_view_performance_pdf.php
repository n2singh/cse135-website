<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../vendor/autoload.php";

require_login();
require_role(["super_admin", "analyst"]);

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    // Read filters
    $selectedPage = $_POST["page"] ?? ($_GET["page"] ?? "");
    $range = $_POST["range"] ?? ($_GET["range"] ?? "30d");
    $chartFile = $_POST["chart_file"] ?? "";
    $chartImage = "";
    $tmpChartPath = "";

    if ($chartFile !== "") {
        $tmpChartPath = __DIR__ . "/../exports/tmp_charts/" . basename($chartFile);

        if (is_file($tmpChartPath) && is_readable($tmpChartPath)) {
            $imageData = file_get_contents($tmpChartPath);
            if ($imageData !== false) {
                $chartImage = "data:image/jpeg;base64," . base64_encode($imageData);
            }
        }
    }

    // Validate page against known pages
    $pageStmt = $pdo->query("
        SELECT DISTINCT page
        FROM events
        WHERE page IS NOT NULL
          AND page <> ''
        ORDER BY page
    ");
    $pages = array_column($pageStmt->fetchAll(PDO::FETCH_ASSOC), "page");

    if ($selectedPage !== "" && !in_array($selectedPage, $pages, true)) {
        $selectedPage = "";
    }

    // Validate range
    $rangeMap = [
        "1d"  => "1 DAY",
        "7d"  => "7 DAY",
        "30d" => "30 DAY",
    ];
    if (!isset($rangeMap[$range])) {
        $range = "30d";
    }
    $rangeSql = $rangeMap[$range];

    // Query same filtered data as live report
    $sql = "
        SELECT event_type, COUNT(*) AS c
        FROM events
        WHERE event_type IS NOT NULL
          AND event_type <> ''
          AND event_ts IS NOT NULL
          AND event_ts >= DATE_SUB(NOW(), INTERVAL {$rangeSql})
    ";

    $params = [];
    if ($selectedPage !== "") {
        $sql .= " AND page = ? ";
        $params[] = $selectedPage;
    }

    $sql .= "
        GROUP BY event_type
        ORDER BY c DESC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build derived values
    $totalEvents = 0;
    $topEventType = null;
    $topEventCount = 0;
    $secondEventType = null;
    $secondEventCount = 0;

    foreach ($rows as $index => $row) {
        $count = (int)($row["c"] ?? 0);
        $totalEvents += $count;

        if ($index === 0) {
            $topEventType = $row["event_type"] ?? "(null)";
            $topEventCount = $count;
        } elseif ($index === 1) {
            $secondEventType = $row["event_type"] ?? "(null)";
            $secondEventCount = $count;
        }
    }

    $distinctShown = count($rows);
    $avgPerType = $distinctShown > 0 ? round($totalEvents / $distinctShown, 1) : 0;
    $topShare = $totalEvents > 0 ? round(($topEventCount / $totalEvents) * 100, 1) : 0;

    $rangeLabel = match ($range) {
        "1d" => "Last 1 Day",
        "7d" => "Last 7 Days",
        default => "Last 30 Days",
    };

    $scopeLabel = $selectedPage === "" ? "All Pages" : $selectedPage;
    $generatedAt = date("Y-m-d H:i:s");

    // Optional CSS file
    $cssPath = __DIR__ . "/../assets/site.css";
    $css = file_exists($cssPath) ? file_get_contents($cssPath) : "";

    ob_start();
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            <?= $css ?>

            @page {
                size: A4 portrait;
                margin: 22px;
            }

            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
            }

            .container {
                max-width: none;
                padding: 0;
            }

            .card {
                margin-bottom: 18px;
                padding: 14px;
                border: 1px solid #ccc;
                border-radius: 8px;
                page-break-inside: avoid;
            }

            h1, h2, h3 {
                margin: 0 0 10px;
            }

            .small,
            .muted {
                color: #666;
                font-size: 11px;
            }

            .stats-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 8px;
            }

            .stats-table td {
                width: 50%;
                border: 1px solid #ddd;
                padding: 8px;
                vertical-align: top;
            }

            .stat-label {
                font-size: 11px;
                color: #666;
                margin-bottom: 4px;
            }

            .stat-value {
                font-size: 13px;
                font-weight: bold;
            }

            .chart-wrap {
                text-align: center;
            }

            .chart-wrap img {
                width: 100%;
                max-width: 700px;
                height: auto;
                border: 1px solid #ddd;
            }

            .table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }

            .table th,
            .table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
                font-size: 11px;
            }

            .table th {
                background: #f3f3f3;
            }

            .analysis p {
                margin: 0 0 10px;
                line-height: 1.5;
            }
        </style>
    </head>
    <body>
        <div class="container">

            <div class="card">
                <h1>Performance Report</h1>
                <p class="small">Generated at <?= htmlspecialchars($generatedAt) ?></p>
                <p class="small">
                    This PDF export summarizes the most common event types for the selected page and time range.
                </p>
            </div>

            <div class="card">
                <h2>Report Summary</h2>
                <table class="stats-table">
                    <tr>
                        <td>
                            <div class="stat-label">Scope</div>
                            <div class="stat-value"><?= htmlspecialchars($scopeLabel) ?></div>
                        </td>
                        <td>
                            <div class="stat-label">Range</div>
                            <div class="stat-value"><?= htmlspecialchars($rangeLabel) ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="stat-label">Event Types Shown</div>
                            <div class="stat-value"><?= (int)$distinctShown ?></div>
                        </td>
                        <td>
                            <div class="stat-label">Total Count in Report</div>
                            <div class="stat-value"><?= (int)$totalEvents ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="stat-label">Top Event Type</div>
                            <div class="stat-value"><?= htmlspecialchars($topEventType ?? "N/A") ?></div>
                        </td>
                        <td>
                            <div class="stat-label">Top Event Share</div>
                            <div class="stat-value"><?= htmlspecialchars((string)$topShare) ?>%</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="stat-label">Average Count per Type</div>
                            <div class="stat-value"><?= htmlspecialchars((string)$avgPerType) ?></div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2>Event Type Chart</h2>
                <?php if ($chartImage !== ""): ?>
                    <div class="chart-wrap">
                        <img src="<?= htmlspecialchars($chartImage) ?>" alt="Performance chart">
                    </div>
                <?php else: ?>
                    <p class="small">Chart preview was not available during export.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Performance Data Table</h2>

                <?php if (count($rows) === 0): ?>
                    <p>No event data is available for this selection.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Event Type</th>
                                <th>Count</th>
                                <th>Share of Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $index => $row): ?>
                                <?php
                                    $count = (int)$row["c"];
                                    $share = $totalEvents > 0 ? round(($count / $totalEvents) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($row["event_type"] ?? "(null)") ?></td>
                                    <td><?= $count ?></td>
                                    <td><?= $share ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total</th>
                                <th><?= (int)$totalEvents ?></th>
                                <th>100%</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card analysis">
                <h2>Analysis</h2>

                <?php if (count($rows) === 0): ?>
                    <p>
                        There is not enough data to generate a useful performance summary for this page and range yet.
                        Try selecting a different page or widening the time range.
                    </p>
                <?php else: ?>
                    <p>
                        This report summarizes the most common recorded event types for the selected page and time window.
                        It helps show which interactions are occurring most frequently instead of focusing on individual sessions.
                    </p>

                    <p>
                        For this selection, the most common event type was
                        <strong><?= htmlspecialchars($topEventType ?? "N/A") ?></strong>
                        with <strong><?= (int)$topEventCount ?></strong> events,
                        representing <strong><?= htmlspecialchars((string)$topShare) ?>%</strong> of the total events shown in this report.
                        <?php if ($secondEventType !== null): ?>
                            The second most common event type was
                            <strong><?= htmlspecialchars($secondEventType) ?></strong>
                            with <strong><?= (int)$secondEventCount ?></strong> events.
                        <?php endif; ?>
                    </p>

                    <p>
                        The chart provides a quick visual comparison of event frequency, while the table confirms the exact counts and percentages.
                        Together, these sections make it easier to understand which actions dominate activity on the selected page.
                    </p>

                    <p>
                        This summary can help distinguish whether a page is driven more by passive interactions,
                        such as scrolling or mouse movement, or by direct actions like clicks and key events.
                    </p>
                <?php endif; ?>
            </div>

        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();

    $exportsDir = __DIR__ . "/../exports";
    if (!is_dir($exportsDir) && !mkdir($exportsDir, 0755, true)) {
        throw new Exception("Could not create exports directory: " . $exportsDir);
    }

    if (!is_writable($exportsDir)) {
        throw new Exception("Exports directory is not writable: " . $exportsDir);
    }

    $filename = "view-performance-report-" . date("Ymd-His") . ".pdf";
    $fullPath = $exportsDir . "/" . $filename;

    if (file_put_contents($fullPath, $dompdf->output()) === false) {
        throw new Exception("Could not save PDF to: " . $fullPath);
    }

    if ($tmpChartPath !== "" && is_file($tmpChartPath)) {
        @unlink($tmpChartPath);
    }

    $publicUrl = "/hw4/exports/" . rawurlencode($filename);

    header("Location: view-performance.php?pdf=" . urlencode($publicUrl) . "&page=" . urlencode($selectedPage) . "&range=" . urlencode($range));
    exit;

} catch (Exception $e) {
    echo "Export failed: " . htmlspecialchars($e->getMessage());
    exit;
}
