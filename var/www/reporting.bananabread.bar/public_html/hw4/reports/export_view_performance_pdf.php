<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_login();
require_once __DIR__ . "/../vendor/autoload.php";

use Dompdf\Dompdf;

try {
    $stmt = $pdo->query("
        SELECT event_type, COUNT(*) AS c
        FROM events
        GROUP BY event_type
        ORDER BY c DESC
        LIMIT 10
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cssPath = __DIR__ . "/../assets/style.css";
    $css = file_exists($cssPath) ? file_get_contents($cssPath) : "";

    $generatedAt = date("Y-m-d H:i:s");

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
                margin: 18px;
            }

            .container {
                max-width: none;
                padding: 0;
            }

            .card {
                margin: 0;
            }

            .table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 12px;
            }

            .table th,
            .table td {
                font-size: 11px;
                word-break: break-word;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <h1>Saved Performance Report</h1>
                <p class="small">Generated at <?= htmlspecialchars($generatedAt) ?></p>
                <p class="small">
                    This saved report summarizes performance-related analytics. It is available to viewers,
                    analysts, and super admins.
                </p>

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
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();

    $exportsDir = __DIR__ . "/../exports";
    if (!is_dir($exportsDir)) {
        mkdir($exportsDir, 0755, true);
    }

    if (!is_dir($exportsDir)) {
        throw new Exception("Exports directory does not exist: " . $exportsDir);
    }

    if (!is_writable($exportsDir)) {
        throw new Exception("Exports directory is not writable: " . $exportsDir);
    }

    $filename = "view-performance-report-" . date("Ymd-His") . ".pdf";
    $fullPath = $exportsDir . "/" . $filename;

    if (file_put_contents($fullPath, $dompdf->output()) === false) {
        throw new Exception("Could not save PDF to: " . $fullPath);
    }

    $publicUrl = "/hw4/exports/" . rawurlencode($filename);

    header("Location: view-performance.php?pdf=" . urlencode($publicUrl));
    exit;
} catch (Exception $e) {
    echo "Export failed: " . htmlspecialchars($e->getMessage());
    exit;
}
