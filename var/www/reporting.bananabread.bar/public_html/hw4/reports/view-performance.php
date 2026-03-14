<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";

require_login();
require_role(["super_admin", "analyst"]);

// Load distinct pages for dropdown
$pageStmt = $pdo->query("
    SELECT DISTINCT page
    FROM events
    WHERE page IS NOT NULL
      AND page <> ''
    ORDER BY page
");
$pages = array_column($pageStmt->fetchAll(PDO::FETCH_ASSOC), "page");

// Validate selected page
$selectedPage = $_GET["page"] ?? "";
if ($selectedPage !== "" && !in_array($selectedPage, $pages, true)) {
    $selectedPage = "";
}

// Range filter
$range = $_GET["range"] ?? "30d";
$rangeMap = [
    "1d"  => "1 DAY",
    "7d"  => "7 DAY",
    "30d" => "30 DAY",
];
if (!isset($rangeMap[$range])) {
    $range = "30d";
}
$rangeSql = $rangeMap[$range];

// Build query
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

// Build chart/table data
$labels = [];
$values = [];
$totalEvents = 0;
$topEventType = null;
$topEventCount = 0;
$secondEventType = null;
$secondEventCount = 0;

foreach ($rows as $index => $row) {
    $eventType = $row["event_type"] ?? "(null)";
    $count = (int)$row["c"];

    $labels[] = $eventType;
    $values[] = $count;
    $totalEvents += $count;

    if ($index === 0) {
        $topEventType = $eventType;
        $topEventCount = $count;
    }

    if ($index === 1) {
        $secondEventType = $eventType;
        $secondEventCount = $count;
    }
}

$distinctShown = count($rows);
$avgPerType = $distinctShown > 0 ? round($totalEvents / $distinctShown, 1) : 0;
$topShare = $totalEvents > 0 ? round(($topEventCount / $totalEvents) * 100, 1) : 0;

$pdfUrl = $_GET["pdf"] ?? "";
$pdfError = $_GET["error"] ?? "";

function range_label(string $range): string {
    return match ($range) {
        "1d" => "Last 1 Day",
        "7d" => "Last 7 Days",
        default => "Last 30 Days",
    };
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Performance Report</title>
    <link rel="icon" href="/assets/bananabread.ico">
    <link rel="stylesheet" href="/hw4/assets/site.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .controls {
            display: flex;
            gap: 12px;
            align-items: end;
            flex-wrap: wrap;
        }

        .controls .field {
            min-width: 260px;
        }

        select {
            width: 100%;
            padding: 10px;
            border-radius: 10px;
            border: 1px solid #444;
            background: #f1e4ba;
            color: #66585b;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }

        .stat {
            background: #f1e4ba;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 12px;
        }

        .muted {
            color: #66585b;
            font-size: 0.95rem;
        }

        .chart-wrap {
            position: relative;
            min-height: 460px;
            overflow: auto;
            border: 1px solid #333;
            border-radius: 12px;
            background: #f1e4ba;
            padding: 12px;
        }

        .chart-stage {
            position: relative;
            min-height: 420px;
        }

        .table-wrap {
            overflow: auto;
            border: 1px solid #333;
            border-radius: 10px;
            background: #f1e4ba;
        }

        .explanation {
            margin-top: 12px;
            line-height: 1.5;
        }

        .explanation p {
            margin: 0 0 12px;
        }

        .explanation p:last-child {
            margin-bottom: 0;
        }

        .url-box {
            word-break: break-word;
        }

        .success-box {
            border: 1px solid #2e7d32;
            background: rgba(46, 125, 50, 0.12);
            border-radius: 10px;
            padding: 12px;
        }

        .success-box p {
            margin: 0 0 8px;
        }

        .success-box p:last-child {
            margin-bottom: 0;
        }

        .error-box {
            border: 1px solid #b71c1c;
            background: rgba(183, 28, 28, 0.10);
            border-radius: 10px;
            padding: 12px;
        }

        .error-box p {
            margin: 0 0 8px;
        }

        .error-box p:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
<?php include __DIR__ . "/../includes/header.php"; ?>

<div class="container">

    <?php if ($pdfUrl !== ""): ?>
        <div class="card success-box">
            <p><strong>PDF exported successfully.</strong></p>
            <p>
                <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener">Open exported PDF</a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($pdfError !== ""): ?>
        <div class="card error-box">
            <p><strong>Export failed.</strong></p>
            <p><?= htmlspecialchars($pdfError) ?></p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h1>Performance Report</h1>
        <p class="small">
            This report shows the most common event types for the selected page and time range.
        </p>

        <form method="GET" action="/hw4/reports/view-performance.php" class="controls" id="performanceControlsForm">
            <div class="field">
                <label for="page">Page</label>
                <select id="page" name="page" onchange="this.form.submit()">
                    <option value="" <?= $selectedPage === "" ? "selected" : "" ?>>All Pages</option>
                    <?php foreach ($pages as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= $selectedPage === $p ? "selected" : "" ?>>
                            <?= htmlspecialchars($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="range">Range</label>
                <select id="range" name="range" onchange="this.form.submit()">
                    <option value="1d" <?= $range === "1d" ? "selected" : "" ?>>Last 1 Day</option>
                    <option value="7d" <?= $range === "7d" ? "selected" : "" ?>>Last 7 Days</option>
                    <option value="30d" <?= $range === "30d" ? "selected" : "" ?>>Last 30 Days</option>
                </select>
            </div>

            <div>
                <button class="btn" type="submit">Load Report</button>
            </div>

            <div>
                <button class="btn" type="submit" form="exportPerformanceForm" id="exportPdfBtn">Export PDF</button>
            </div>
        </form>

        <form method="POST" action="/hw4/reports/export_view_performance_pdf.php" id="exportPerformanceForm">
            <input type="hidden" name="page" value="<?= htmlspecialchars($selectedPage) ?>">
            <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
            <input type="hidden" name="chart_file" id="chart_file">
        </form>
    </div>

    <div class="card">
        <div class="stats">
            <div class="stat">
                <div class="muted">Scope</div>
                <div class="url-box"><?= $selectedPage === "" ? "All Pages" : htmlspecialchars($selectedPage) ?></div>
            </div>

            <div class="stat">
                <div class="muted">Range</div>
                <div><?= htmlspecialchars(range_label($range)) ?></div>
            </div>

            <div class="stat">
                <div class="muted">Event Types Shown</div>
                <div><?= (int)$distinctShown ?></div>
            </div>

            <div class="stat">
                <div class="muted">Total Count in Report</div>
                <div><?= (int)$totalEvents ?></div>
            </div>

            <div class="stat">
                <div class="muted">Top Event Type</div>
                <div><?= htmlspecialchars($topEventType ?? "N/A") ?></div>
            </div>

            <div class="stat">
                <div class="muted">Top Event Share</div>
                <div><?= htmlspecialchars((string)$topShare) ?>%</div>
            </div>
        </div>

        <p class="explanation">
            This view summarizes the most common event types recorded on the selected page during the chosen
            time window. The chart highlights relative frequency at a glance, while the table below shows the
            exact counts and percentage share for each event type in the report.
        </p>
    </div>

    <div class="card">
        <h2>Event Type Chart</h2>
        <p class="small">
            This chart shows the top recorded event types for the current selection.
        </p>

        <?php if (count($labels) === 0): ?>
            <p>No event data is available for this selection.</p>
        <?php else: ?>
            <div class="chart-wrap">
                <div class="chart-stage">
                    <canvas id="eventTypeChart"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Performance Data Table</h2>
        <p class="small">
            This table contains the exact counts used in the chart above.
        </p>

        <?php if (count($rows) === 0): ?>
            <p>No table data is available for this selection.</p>
        <?php else: ?>
            <div class="table-wrap">
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
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
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

<?php if (count($labels) > 0): ?>
<script>
    const labels = <?= json_encode($labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const values = <?= json_encode($values, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    let performanceChart = null;

    const canvas = document.getElementById("eventTypeChart");
    if (canvas) {
        const ctx = canvas.getContext("2d");

        performanceChart = new Chart(ctx, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [{
                    label: "Count",
                    data: values,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            font: {
                                size: 14
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: "Event Type",
                            font: {
                                size: 14
                            }
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            maxRotation: 0,
                            minRotation: 0
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: "Count",
                            font: {
                                size: 14
                            }
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    }

    const exportForm = document.getElementById("exportPerformanceForm");

    if (exportForm) {
        exportForm.addEventListener("submit", async function (event) {
            event.preventDefault();

            const chartCanvas = document.getElementById("eventTypeChart");
            const chartFileInput = document.getElementById("chart_file");

            if (!chartCanvas || !chartFileInput || !performanceChart) {
                exportForm.submit();
                return;
            }

            try {
                const exportCanvas = document.createElement("canvas");
                exportCanvas.width = 1600;
                exportCanvas.height = 900;

                const exportCtx = exportCanvas.getContext("2d");

                const exportChart = new Chart(exportCtx, {
                    type: "bar",
                    data: {
                        labels: labels,
                        datasets: [{
                            label: "Count",
                            data: values,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: false,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: {
                                display: true,
                                labels: {
                                    font: {
                                        size: 24
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: "Event Type",
                                    font: {
                                        size: 24
                                    }
                                },
                                ticks: {
                                    font: {
                                        size: 20
                                    },
                                    maxRotation: 0,
                                    minRotation: 0
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: "Count",
                                    font: {
                                        size: 24
                                    }
                                },
                                ticks: {
                                    font: {
                                        size: 20
                                    }
                                }
                            }
                        }
                    }
                });

                await new Promise(resolve => setTimeout(resolve, 300));

                exportCanvas.toBlob(async function (blob) {
                    exportChart.destroy();

                    if (!blob) {
                        exportForm.submit();
                        return;
                    }

                    try {
                        const formData = new FormData();
                        formData.append("chart_image", blob, "performance-chart.jpg");

                        const response = await fetch("/hw4/reports/save_chart_image.php", {
                            method: "POST",
                            body: formData
                        });

                        const result = await response.json();

                        if (!response.ok || !result.success) {
                            alert("Could not prepare chart for PDF export.");
                            return;
                        }

                        chartFileInput.value = result.filename;
                        exportForm.submit();
                    } catch (err) {
                        console.error(err);
                        alert("Chart upload failed before export.");
                    }
                }, "image/jpeg", 0.92);

            } catch (err) {
                console.error(err);
                alert("Failed to build export chart.");
            }
        });
    }
</script>
<?php endif; ?>

<?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>
