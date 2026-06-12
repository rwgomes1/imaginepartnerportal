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

// Handle IP additions/removals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add IP to blacklist/whitelist
    if (!empty($_POST['new_ip']) && isset($_POST['list_type'])) {
        $stmt = $pdo->prepare("INSERT INTO firewall_ip_list (ip_address, list_type, reason) 
                               VALUES (:ip, :lt, :reason)");
        $stmt->execute([
            ':ip'     => trim($_POST['new_ip']),
            ':lt'     => $_POST['list_type'],
            ':reason' => $_POST['reason'] ?? ''
        ]);
        $message = "Added IP to {$_POST['list_type']}.";
    }

    // Remove IP
    if (isset($_POST['remove_ip_id'])) {
        $stmt = $pdo->prepare("DELETE FROM firewall_ip_list WHERE id = :id");
        $stmt->execute([':id' => (int)$_POST['remove_ip_id']]);
        $message = "Removed IP from list.";
    }
}

// Fetch IP lists
$stmt = $pdo->query("SELECT * FROM firewall_ip_list ORDER BY created_at DESC");
$ipList = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<main class="main-content">
    <div class="container">
        <h2>IP Whitelist / Blacklist</h2>
        
        <?php if (!empty($message)): ?>
            <div style="background:#f2f2f2; padding:10px; margin-bottom:10px; border:1px solid #ccc;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card" style="text-align:left;">
            <h3>Manage IPs</h3>
            <?php if (empty($ipList)): ?>
                <p>No IPs listed yet.</p>
            <?php else: ?>
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f5f5f5;">
                            <th style="border:1px solid #ddd; padding:8px;">ID</th>
                            <th style="border:1px solid #ddd; padding:8px;">IP Address</th>
                            <th style="border:1px solid #ddd; padding:8px;">Type</th>
                            <th style="border:1px solid #ddd; padding:8px;">Reason</th>
                            <th style="border:1px solid #ddd; padding:8px;">Created</th>
                            <th style="border:1px solid #ddd; padding:8px;">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ipList as $ipRow): ?>
                            <tr>
                                <td style="border:1px solid #ddd; padding:8px;"><?php echo $ipRow['id']; ?></td>
                                <td style="border:1px solid #ddd; padding:8px;"><?php echo htmlspecialchars($ipRow['ip_address']); ?></td>
                                <td style="border:1px solid #ddd; padding:8px;"><?php echo htmlspecialchars($ipRow['list_type']); ?></td>
                                <td style="border:1px solid #ddd; padding:8px;"><?php echo htmlspecialchars($ipRow['reason'] ?? ''); ?></td>
                                <td style="border:1px solid #ddd; padding:8px;"><?php echo $ipRow['created_at']; ?></td>
                                <td style="border:1px solid #ddd; padding:8px;">
                                    <form method="POST" onsubmit="return confirm('Remove this IP from list?');">
                                        <input type="hidden" name="remove_ip_id" value="<?php echo $ipRow['id']; ?>">
                                        <button type="submit" class="button" style="background-color:#c00;">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h4 style="margin-top:20px;">Add IP to Blacklist or Whitelist</h4>
            <form method="POST">
                <label>IP Address:</label>
                <input type="text" name="new_ip" required>
                
                <label>Type:</label>
                <select name="list_type" required>
                    <option value="blacklist">Blacklist</option>
                    <option value="whitelist">Whitelist</option>
                </select>
                
                <label>Reason (optional):</label>
                <input type="text" name="reason">
                
                <button type="submit" class="button" style="max-width:200px;">Add IP</button>
            </form>
        </div>
    </div>
</main>
<?php include '../../includes/footer.php'; ?>
