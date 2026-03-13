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
        SELECT page, COUNT(*) AS c
        FROM events
        GROUP BY page
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
                <h1>Saved Behavior Report</h1>
                <p class="small">Generated at <?= htmlspecialchars($generatedAt) ?></p>
                <p class="small">
                    This saved report summarizes user behavior by page activity. It is available to viewers,
                    analysts, and super admins.
                </p>

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

    $filename = "view-behavior-report-" . date("Ymd-His") . ".pdf";
    $fullPath = $exportsDir . "/" . $filename;

    if (file_put_contents($fullPath, $dompdf->output()) === false) {
        throw new Exception("Could not save PDF to: " . $fullPath);
    }

    $publicUrl = "/hw4/exports/" . rawurlencode($filename);

    header("Location: view-behavior.php?pdf=" . urlencode($publicUrl));
    exit;
} catch (Exception $e) {
    echo "Export failed: " . htmlspecialchars($e->getMessage());
    exit;
}
