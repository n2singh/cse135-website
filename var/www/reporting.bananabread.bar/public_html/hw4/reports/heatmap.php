<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_login();
require_role(["super_admin","analyst"]);

// 1) Get pages that actually have click events
$pageStmt = $pdo->query("
  SELECT DISTINCT page
  FROM events
  WHERE event_type = 'click'
    AND page IS NOT NULL
    AND page <> ''
  ORDER BY page
");
$pages = array_column($pageStmt->fetchAll(PDO::FETCH_ASSOC), "page");

// Pick selected page, default to first available page
$selectedPage = $_GET["page"] ?? ($pages[0] ?? "");
if ($selectedPage !== "" && !in_array($selectedPage, $pages, true)) {
  $selectedPage = $pages[0] ?? "";
}

// 2) Pull click rows for just that page
$rows = [];
if ($selectedPage !== "") {
  $stmt = $pdo->prepare("
    SELECT id, event_ts, data
    FROM events
    WHERE event_type = 'click'
      AND page = ?
    ORDER BY id DESC
    LIMIT 5000
  ");
  $stmt->execute([$selectedPage]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 3) First pass: extract raw click positions + source dimensions
$rawPoints = [];
$sourceWidth = 0;
$sourceHeight = 0;

foreach ($rows as $row) {
  $d = json_decode($row["data"] ?? "", true);
  if (!is_array($d)) {
    continue;
  }

  $x = $d["docX"] ?? $d["pageX"] ?? $d["x"] ?? $d["clientX"] ?? null;
  $y = $d["docY"] ?? $d["pageY"] ?? $d["y"] ?? $d["clientY"] ?? null;

  $w = $d["docWidth"] ?? $d["pageWidth"] ?? $d["documentWidth"] ?? $d["innerWidth"] ?? $d["viewportWidth"] ?? null;
  $h = $d["docHeight"] ?? $d["pageHeight"] ?? $d["documentHeight"] ?? $d["innerHeight"] ?? $d["viewportHeight"] ?? null;

  if (!isset($d["docX"]) && !isset($d["pageX"]) && isset($d["clientX"], $d["scrollX"])) {
    $x = $d["clientX"] + $d["scrollX"];
  }
  if (!isset($d["docY"]) && !isset($d["pageY"]) && isset($d["clientY"], $d["scrollY"])) {
    $y = $d["clientY"] + $d["scrollY"];
  }

  if (!is_numeric($x) || !is_numeric($y)) {
    continue;
  }

  $x = (float)$x;
  $y = (float)$y;

  if (is_numeric($w) && $w > $sourceWidth) {
    $sourceWidth = (float)$w;
  }
  if (is_numeric($h) && $h > $sourceHeight) {
    $sourceHeight = (float)$h;
  }

  $rawPoints[] = ["x" => $x, "y" => $y];
}

// Fallback dimensions if event payload did not include them
if ($sourceWidth <= 0) {
  foreach ($rawPoints as $p) {
    if ($p["x"] > $sourceWidth) {
      $sourceWidth = $p["x"];
    }
  }
}
if ($sourceHeight <= 0) {
  foreach ($rawPoints as $p) {
    if ($p["y"] > $sourceHeight) {
      $sourceHeight = $p["y"];
    }
  }
}

if ($sourceWidth <= 0) $sourceWidth = 1200;
if ($sourceHeight <= 0) $sourceHeight = 800;

// 4) Normalize to a fixed preview width, dynamic height
$previewWidth = 1200;
$previewHeight = (int)round(($sourceHeight / max(1, $sourceWidth)) * $previewWidth);

// Keep the preview from getting absurdly tall or tiny
if ($previewHeight < 600) $previewHeight = 600;
if ($previewHeight > 2200) $previewHeight = 2200;

// 5) Bin points together so repeated clicks strengthen the hotspot
$binSize = 14;
$bins = [];
$totalClicks = 0;

foreach ($rawPoints as $p) {
  $nx = (int)round(($p["x"] / $sourceWidth) * $previewWidth);
  $ny = (int)round(($p["y"] / $sourceHeight) * $previewHeight);

  $nx = max(0, min($previewWidth - 1, $nx));
  $ny = max(0, min($previewHeight - 1, $ny));

  $bx = (int)(floor($nx / $binSize) * $binSize);
  $by = (int)(floor($ny / $binSize) * $binSize);

  $key = $bx . ":" . $by;
  if (!isset($bins[$key])) {
    $bins[$key] = [
      "x" => $bx,
      "y" => $by,
      "value" => 0
    ];
  }

  $bins[$key]["value"]++;
  $totalClicks++;
}

$points = array_values($bins);
$maxValue = 1;
foreach ($points as $p) {
  if ($p["value"] > $maxValue) {
    $maxValue = $p["value"];
  }
}

// Sort hotspot cells for numeric table
$rankedPoints = $points;
usort($rankedPoints, function ($a, $b) {
  return $b["value"] <=> $a["value"];
});
$topHotspots = array_slice($rankedPoints, 0, 20);

$pdfUrl = $_GET["pdf"] ?? "";
$pdfError = $_GET["error"] ?? "";
$exportHref = "export_heatmap_pdf.php?page=" . rawurlencode($selectedPage);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Click Heatmap</title>
  <link rel="icon" href="/assets/bananabread.ico"/>
  <link rel="stylesheet" href="/hw4/assets/site.css" />
  <style>
    .controls {
      display: flex;
      gap: 12px;
      align-items: end;
      flex-wrap: wrap;
    }

    .controls .field {
      min-width: 320px;
    }

    select {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: 1px solid #444;
      background: #f1e4ba;
      color: #66585b;
    }

    .heatmap-wrap {
      overflow: auto;
      border: 1px solid #333;
      border-radius: 12px;
      background: #f1e4ba;
      padding: 12px;
    }

    .heatmap-stage {
      position: relative;
      width: <?= (int)$previewWidth ?>px;
      height: <?= (int)$previewHeight ?>px;
      margin: 0 auto;
      background-color: #fafafa;
      background-image:
        linear-gradient(to right, rgba(0,0,0,0.06) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px);
      background-size: 40px 40px;
      border: 1px solid #ccc;
      border-radius: 10px;
      overflow: hidden;
    }

    .heatmap-layer {
      position: absolute;
      inset: 0;
    }

    .stage-label {
      position: absolute;
      z-index: 5;
      font-size: 12px;
      color: #eee;
      background: rgba(255,255,255,0.8);
      padding: 4px 6px;
      border-radius: 6px;
    }

    .stage-label.tl { top: 8px; left: 8px; }
    .stage-label.br { right: 8px; bottom: 8px; }

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

    .url-box {
      word-break: break-word;
    }

    .explanation {
      margin-top: 12px;
      line-height: 1.5;
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
      <h1>Click Heatmap</h1>
      <p class="small">
        This view plots click concentration for one page at a time.
      </p>

      <form method="GET" action="/hw4/reports/heatmap.php" class="controls">
        <div class="field">
          <label for="page">Page</label>
          <select id="page" name="page" onchange="this.form.submit()">
            <?php foreach ($pages as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>" <?= $p === $selectedPage ? "selected" : "" ?>>
                <?= htmlspecialchars($p) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <button class="btn" type="submit">Load Heatmap</button>
        </div>

        <?php if ($selectedPage !== ""): ?>
          <div>
            <a class="btn" href="<?= htmlspecialchars($exportHref) ?>">Export PDF</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <div class="stats">
        <div class="stat">
          <div class="muted">Selected Page</div>
          <div class="url-box"><?= $selectedPage !== "" ? htmlspecialchars($selectedPage) : "No click pages found" ?></div>
        </div>

        <div class="stat">
          <div class="muted">Raw Click Events</div>
          <div><?= (int)$totalClicks ?></div>
        </div>

        <div class="stat">
          <div class="muted">Heatmap Cells</div>
          <div><?= count($points) ?></div>
        </div>

        <div class="stat">
          <div class="muted">Normalized Preview</div>
          <div><?= (int)$previewWidth ?> × <?= (int)$previewHeight ?></div>
        </div>
      </div>

      <p class="explanation">
        This heatmap represents where clicks are most concentrated on the selected page. Brighter and stronger
        hotspots indicate areas where more clicks were grouped together, while lighter areas indicate fewer clicks.
        The numeric table below summarizes the strongest hotspot cells after the click positions were normalized and binned.
      </p>
    </div>

    <div class="card">
      <?php if ($selectedPage === ""): ?>
        <p>No pages with click events were found.</p>
      <?php elseif (count($points) === 0): ?>
        <p>
          Click events were found for this page, but no usable coordinates were extracted from the
          <code>data</code> JSON. Your click payload should include coordinates such as
          <code>docX/docY</code>, <code>pageX/pageY</code>, or at least <code>clientX/clientY</code>.
        </p>
      <?php else: ?>
        <div class="heatmap-wrap">
          <div class="heatmap-stage">
            <div class="stage-label tl">top-left</div>
            <div class="stage-label br">bottom-right</div>
            <div id="heatmap-layer" class="heatmap-layer"></div>
          </div>
        </div>

        <p class="small" style="margin-top:12px;">
          Best accuracy comes from storing full-page click coordinates and page dimensions in each click event.
          If only viewport-relative coordinates were stored, this heatmap represents the viewport rather than the full document.
        </p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Hotspot Data Table</h2>
      <?php if (count($topHotspots) === 0): ?>
        <p>No numeric hotspot data is available for the selected page.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Normalized X</th>
              <th>Normalized Y</th>
              <th>Clicks in Cell</th>
              <th>Relative Intensity</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topHotspots as $index => $p): ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td><?= (int)$p["x"] ?></td>
                <td><?= (int)$p["y"] ?></td>
                <td><?= (int)$p["value"] ?></td>
                <td><?= round(((int)$p["value"] / max(1, $maxValue)) * 100, 1) ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/heatmapjs@2.0.2/heatmap.min.js"></script>
  <script>
    const points = <?= json_encode($points, JSON_UNESCAPED_SLASHES) ?>;
    const maxValue = <?= (int)$maxValue ?>;

    if (points.length > 0) {
      const heatmap = h337.create({
        container: document.getElementById("heatmap-layer"),
        radius: 35,
        blur: 0.85,
        maxOpacity: 0.70,
        minOpacity: 0.05
      });

      heatmap.setData({
        max: maxValue,
        data: points
      });
    }
  </script>

  <?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>
