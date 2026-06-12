<?php
session_start();
require_once '../../includes/config.php';

// Ensure superadmin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'superadmin') {
    header('Location: ../../login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$message = '';

// Handle block/whitelist from logs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Export to CSV
    if (isset($_POST['export_csv'])) {
        // Build CSV from firewall_logs
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=firewall_logs.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID','Event Type','IP','URL','Referer','User Agent','User ID','Details','Created']);
        
        $stmt = $pdo->query("SELECT * FROM firewall_logs ORDER BY id DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'] ?? '',
                $row['event_type'] ?? '',
                $row['ip_address'] ?? '',
                $row['request_url'] ?? '',
                $row['referer'] ?? '',
                $row['user_agent'] ?? '',
                $row['user_id'] ?? '',
                $row['details'] ?? '',
                $row['created_at'] ?? ''
            ]);
        }
        fclose($output);
        exit;
    }

    // 2) Block or Whitelist an IP from the logs page
    if (isset($_POST['log_ip']) && isset($_POST['action_type'])) {
        $ip = trim($_POST['log_ip'] ?? '');
        $actionType = $_POST['action_type'] ?? ''; // 'block' or 'whitelist'
        if (!empty($ip)) {
            if ($actionType === 'block') {
                // Insert into blacklist
                $stmt = $pdo->prepare("INSERT INTO firewall_ip_list (ip_address, list_type) 
                                       VALUES (:ip, 'blacklist')
                                       ON DUPLICATE KEY UPDATE list_type='blacklist'");
                $stmt->execute([':ip' => $ip]);
                $message = "IP $ip has been added to the blacklist.";
            } elseif ($actionType === 'whitelist') {
                // Insert into whitelist
                $stmt = $pdo->prepare("INSERT INTO firewall_ip_list (ip_address, list_type) 
                                       VALUES (:ip, 'whitelist')
                                       ON DUPLICATE KEY UPDATE list_type='whitelist'");
                $stmt->execute([':ip' => $ip]);
                $message = "IP $ip has been added to the whitelist.";
            }
        }
    }
}

// Fetch logs
$stmt = $pdo->query("SELECT * FROM firewall_logs ORDER BY id DESC LIMIT 200"); // limit 200 for example
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<main class="main-content">
  <div class="container">
    <h2>Firewall Logs</h2>
    <?php if (!empty($message)): ?>
      <div style="background:#f2f2f2; padding:10px; margin-bottom:10px; border:1px solid #ccc;">
        <?php echo htmlspecialchars($message ?? ''); ?>
      </div>
    <?php endif; ?>

    <div class="card" style="text-align:left;">
      <h3>Export Logs</h3>
      <form method="POST" style="margin-bottom:15px;">
        <button type="submit" name="export_csv" class="button" style="max-width:200px;">
          Export to CSV
        </button>
      </form>
    </div>

    <!-- Logs Listing -->
    <div class="card" style="text-align:left;">
      <h3>Recent Firewall Events</h3>
      <?php if (empty($logs)): ?>
        <p>No logs found.</p>
      <?php else: ?>
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="background:#f5f5f5;">
              <th style="border:1px solid #ddd; padding:8px;">Block</th>
              <th style="border:1px solid #ddd; padding:8px;">Event</th>
              <th style="border:1px solid #ddd; padding:8px;">IP</th>
              <th style="border:1px solid #ddd; padding:8px;">URL</th>
              <th style="border:1px solid #ddd; padding:8px;">Referer</th>
              <th style="border:1px solid #ddd; padding:8px;">User Agent</th>
              <th style="border:1px solid #ddd; padding:8px;">Details</th>
              <th style="border:1px solid #ddd; padding:8px;">Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <!-- Block or Whitelist Buttons -->
              <td style="border:1px solid #ddd; padding:8px; vertical-align:middle;">
                <form method="POST" style="margin:0; display:inline;">
                  <input type="hidden" name="log_ip" value="<?php echo htmlspecialchars($log['ip_address'] ?? ''); ?>">
                  <input type="hidden" name="action_type" value="block">
                  <button type="submit" class="button" 
                          style="background-color:#c00; width:auto; padding:5px 10px;">
                    Block
                  </button>
                </form>
                <form method="POST" style="margin:0; display:inline;">
                  <input type="hidden" name="log_ip" value="<?php echo htmlspecialchars($log['ip_address'] ?? ''); ?>">
                  <input type="hidden" name="action_type" value="whitelist">
                  <button type="submit" class="button" 
                          style="background-color:#0056b3; width:auto; padding:5px 10px; margin-top:5px;">
                    Whitelist
                  </button>
                </form>
              </td>

              <!-- Event Type -->
              <td style="border:1px solid #ddd; padding:8px;"><?php echo htmlspecialchars($log['event_type'] ?? ''); ?></td>
              <!-- IP -->
              <td style="border:1px solid #ddd; padding:8px;"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
              <!-- Request URL -->
              <td style="border:1px solid #ddd; padding:8px;">
                <div style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                  <?php echo htmlspecialchars($log['request_url'] ?? ''); ?>
                </div>
              </td>
              <!-- Referer -->
              <td style="border:1px solid #ddd; padding:8px;">
                <div style="max-width:150px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                  <?php echo htmlspecialchars($log['referer'] ?? ''); ?>
                </div>
              </td>
              <!-- User Agent -->
              <td style="border:1px solid #ddd; padding:8px;">
                <div style="max-width:150px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                  <?php echo htmlspecialchars($log['user_agent'] ?? ''); ?>
                </div>
              </td>
              <!-- Details -->
              <td style="border:1px solid #ddd; padding:8px;">
                <button type="button" class="button" style="background-color:#666; width:auto; padding:5px 10px;" 
                        onclick="alert('<?php echo addslashes($log['details'] ?? ''); ?>');">
                  Show
                </button>
              </td>
              <!-- Created At -->
              <td style="border:1px solid #ddd; padding:8px;">
                <?php echo htmlspecialchars($log['created_at'] ?? ''); ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include '../../includes/footer.php'; ?>
