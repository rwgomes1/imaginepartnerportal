<?php
session_start();
require_once '../../includes/config.php';

// Ensure superadmin or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header('Location: ../../login.php');
    exit;
}

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("DB Connection Failed: " . $e->getMessage());
}

// -------------------------------------------------------------------
// 1) Handle form submissions (Block IP, Export CSV)
// -------------------------------------------------------------------
$message = '';

// If POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1a) Block IP
    if (isset($_POST['action']) && $_POST['action'] === 'block_ip') {
        $ipToBlock = trim($_POST['block_ip'] ?? '');
        if ($ipToBlock !== '') {
            // Insert/Update in firewall_ip_list as 'blacklist'
            $stmtBlock = $pdo->prepare("
                INSERT INTO firewall_ip_list (ip_address, list_type)
                VALUES (:ip, 'blacklist')
                ON DUPLICATE KEY UPDATE list_type = 'blacklist'
            ");
            $stmtBlock->execute([':ip' => $ipToBlock]);
            $message = "IP {$ipToBlock} has been blocked.";
        }
    }

    // 1b) Export IP logs to CSV
    if (isset($_POST['export_ip_logs'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ip_access_logs.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['IP Address','Country','Referrer','Page','Date/Time']);

        // Query all IP logs (or you could filter by event_type, etc.)
        $expStmt = $pdo->query("
            SELECT ip_address, country_code, referer, request_url, created_at
            FROM firewall_logs
            ORDER BY created_at DESC
        ");

        while ($row = $expStmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['ip_address'],
                $row['country_code'] ?: 'Unknown',
                $row['referer'] ?: 'No referrer',
                $row['request_url'] ?: 'Unknown page',
                $row['created_at']
            ]);
        }

        fclose($output);
        exit; // Stop further output
    }
}

// -------------------------------------------------------------------
// 2) Fetch blocked attempts by country for the map
// -------------------------------------------------------------------
$countryData = [];
$stmt = $pdo->query("
    SELECT country_code, COUNT(*) AS block_count
    FROM firewall_logs
    WHERE event_type = 'BLOCK'
      AND country_code IS NOT NULL
      AND country_code <> ''
    GROUP BY country_code
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $countryData[] = [
        'id'    => strtoupper($row['country_code']), // amCharts expects uppercase ISO codes
        'value' => (int)$row['block_count']
    ];
}

// -------------------------------------------------------------------
// 3) Last 5 blocked IP addresses
// -------------------------------------------------------------------
$lastBlockedIPs = [];
$stmt = $pdo->query("
    SELECT ip_address, country_code, details, created_at
    FROM firewall_logs
    WHERE event_type = 'BLOCK'
    ORDER BY created_at DESC
    LIMIT 5
");
$lastBlockedIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------------------------------------------
// 4) Last 10 user actions (join users to get user_name)
// -------------------------------------------------------------------
$userActions = [];
$stmt = $pdo->query("
    SELECT f.event_type, f.ip_address, f.user_id, f.created_at, f.details,
           u.name AS user_name
    FROM firewall_logs f
    LEFT JOIN users u ON f.user_id = u.id
    WHERE f.event_type IN (
        'LEAD_CREATED','PARTNER_CREATED','LOGIN_SUCCESS','LOGIN_FAIL','OTHER_ACTION'
    )
    ORDER BY f.created_at DESC
    LIMIT 10
");
$userActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------------------------------------------
// 5) Paginated list of most recent IP addresses
// -------------------------------------------------------------------
// We'll show 10 per page, with pagination links at the bottom
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Count total logs
$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM firewall_logs")->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

// Query the logs
$stmtIps = $pdo->prepare("
    SELECT ip_address, country_code, referer, request_url, created_at
    FROM firewall_logs
    ORDER BY created_at DESC
    LIMIT :offset, :limit
");
$stmtIps->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtIps->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtIps->execute();
$ipAccessList = $stmtIps->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="container">
        <h2 style="margin-bottom:20px;">Firewall Dashboard</h2>

        <?php if (!empty($message)): ?>
            <div class="card" style="padding:10px; margin-bottom:20px;">
                <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <!-- World Map Card -->
        <div class="card" style="padding:20px; margin-bottom:20px; text-align:left;">
            <h3>Blocked Attempts by Country</h3>
            <p style="margin-bottom:15px;">
                The map below highlights countries that have attempted to access the site and were blocked.
                Darker shades of red indicate more blocked attempts.
            </p>
            
            <!-- Container for amCharts map -->
            <div id="chartdiv" style="width:100%; height:500px;"></div>
        </div>
        
        <!-- Last 5 Blocked IPs -->
        <div class="card" style="padding:20px; margin-bottom:20px; text-align:left;">
            <h3>Last 5 Blocked IP Addresses</h3>
            <?php if (count($lastBlockedIPs) === 0): ?>
                <p>No blocked IP addresses found.</p>
            <?php else: ?>
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f5f5f5;">
                            <th style="border:1px solid #ccc; padding:8px;">IP Address</th>
                            <th style="border:1px solid #ccc; padding:8px;">Country</th>
                            <th style="border:1px solid #ccc; padding:8px;">Details</th>
                            <th style="border:1px solid #ccc; padding:8px;">Date/Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lastBlockedIPs as $ipRow): ?>
                            <tr>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($ipRow['ip_address']); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($ipRow['country_code'] ?: 'Unknown'); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($ipRow['details']); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($ipRow['created_at']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Last 10 User Actions -->
        <div class="card" style="padding:20px; margin-bottom:20px; text-align:left;">
            <h3>Last 10 User Actions</h3>
            <?php if (count($userActions) === 0): ?>
                <p>No user actions found.</p>
            <?php else: ?>
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f5f5f5;">
                            <th style="border:1px solid #ccc; padding:8px;">Event Type</th>
                            <th style="border:1px solid #ccc; padding:8px;">IP Address</th>
                            <th style="border:1px solid #ccc; padding:8px;">User Name</th>
                            <th style="border:1px solid #ccc; padding:8px;">Details</th>
                            <th style="border:1px solid #ccc; padding:8px;">Date/Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userActions as $action): ?>
                            <tr>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($action['event_type']); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($action['ip_address']); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($action['user_name'] ?: 'Unknown'); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($action['details']); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($action['created_at']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Most Recent IP Addresses (with pagination, block, and CSV export) -->
        <div class="card" style="padding:20px; margin-bottom:20px; text-align:left;">
            <h3>Most Recent IP Addresses</h3>
            <form method="POST" style="margin-bottom:15px;">
                <button type="submit" name="export_ip_logs" class="button" style="max-width:200px;">
                    Export to CSV
                </button>
            </form>

            <?php if (count($ipAccessList) === 0): ?>
                <p>No IP addresses found.</p>
            <?php else: ?>
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f5f5f5;">
                            <th style="border:1px solid #ccc; padding:8px;">IP Address</th>
                            <th style="border:1px solid #ccc; padding:8px;">Country</th>
                            <th style="border:1px solid #ccc; padding:8px;">Referrer</th>
                            <th style="border:1px solid #ccc; padding:8px;">Page</th>
                            <th style="border:1px solid #ccc; padding:8px;">Date/Time</th>
                            <th style="border:1px solid #ccc; padding:8px;">Block</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ipAccessList as $ipRow): ?>
                            <tr>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($ipRow['ip_address']); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($ipRow['country_code'] ?: 'Unknown'); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($ipRow['referer'] ?: 'No referrer'); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($ipRow['request_url'] ?: 'Unknown page'); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <?php echo htmlspecialchars($ipRow['created_at']); ?>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px;">
                                    <form method="POST" style="margin:0; display:inline;">
                                        <input type="hidden" name="action" value="block_ip">
                                        <input type="hidden" name="block_ip" value="<?php echo htmlspecialchars($ipRow['ip_address']); ?>">
                                        <button type="submit" class="button" 
                                                style="background-color:#c00; color:#fff; padding:5px 10px;">
                                            Block
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination Links -->
                <div style="margin-top:10px; text-align:center;">
                    <?php
                    // Basic pagination
                    if ($page > 1) {
                        echo '<a href="?page='.($page-1).'" style="margin-right:10px;">&laquo; Previous</a>';
                    }
                    echo "Page {$page} of {$totalPages}";
                    if ($page < $totalPages) {
                        echo '<a href="?page='.($page+1).'" style="margin-left:10px;">Next &raquo;</a>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- amCharts 5 Scripts (CDN) -->
<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/map.js"></script>
<script src="https://cdn.amcharts.com/lib/5/geodata/worldLow.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

<script>
// Prepare the data from PHP for amCharts
var countryData = <?php echo json_encode($countryData, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
// Example format: [ { "id": "US", "value": 10 }, { "id": "CN", "value": 3 }, ... ]

am5.ready(function() {

  // Create root and chart
  var root = am5.Root.new("chartdiv");
  root.setThemes([ am5themes_Animated.new(root) ]);

  // Create the map chart
  var chart = root.container.children.push(
    am5map.MapChart.new(root, {
      panX: "none",
      panY: "none",
      wheelX: "none",
      wheelY: "none",
      projection: am5map.geoMercator()
    })
  );

  // Create polygon series for the world map
  var polygonSeries = chart.series.push(
    am5map.MapPolygonSeries.new(root, {
      geoJSON: am5geodata_worldLow
    })
  );

  // Create a heat rule so that countries with higher "value" appear darker
  polygonSeries.set("heatRules", [{
    target: polygonSeries.mapPolygons.template,
    dataField: "value",
    min: am5.color(0xffdddd),
    max: am5.color(0xff0000),
    key: "fill"
  }]);

  // Load data into the polygon series
  polygonSeries.data.setAll(countryData);

  polygonSeries.mapPolygons.template.setAll({
    tooltipText: "{name}: {value}",
    interactive: true
  });

  // Animate on load
  polygonSeries.appear(1000, 100);
  chart.appear(1000, 100);

}); // end am5.ready()
</script>

<?php include '../../includes/footer.php'; ?>
