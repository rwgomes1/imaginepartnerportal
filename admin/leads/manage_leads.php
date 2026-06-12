<?php
session_start();
require_once '../../includes/config.php';

// Check if the user is logged in as an admin or superadmin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header('Location: ../../login.php');
    exit;
}

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch all partners for the filter dropdown
$partnersStmt = $pdo->query("SELECT id, company_name FROM partners ORDER BY company_name ASC");
$allPartners = $partnersStmt->fetchAll(PDO::FETCH_ASSOC);

// Determine which partner to filter by
$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;
$leads = [];
$pendingCount = 0;

if ($partner_id) {
    // Fetch leads for the selected partner
    $stmt = $pdo->prepare("
        SELECT * 
        FROM partner_leads 
        WHERE partner_id = :partner_id 
        ORDER BY submitted_at DESC
    ");
    $stmt->execute([':partner_id' => $partner_id]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count pending leads for display
    $pendingStmt = $pdo->prepare("
        SELECT COUNT(*) AS pending_count 
        FROM partner_leads 
        WHERE partner_id = :partner_id 
          AND lead_status = 'Pending'
    ");
    $pendingStmt->execute([':partner_id' => $partner_id]);
    $pendingData = $pendingStmt->fetch(PDO::FETCH_ASSOC);
    $pendingCount = $pendingData ? $pendingData['pending_count'] : 0;
}

// Handle status update for a lead
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lead_id'], $_POST['new_status'])) {
    $lead_id = intval($_POST['lead_id']);
    $new_status = $_POST['new_status'];
    $allowedStatuses = ['Under Review','Pending','Rejected','Sold'];
    if (in_array($new_status, $allowedStatuses)) {
        $updateStmt = $pdo->prepare("
            UPDATE partner_leads 
            SET lead_status = :new_status 
            WHERE id = :lead_id
        ");
        $updateStmt->execute([':new_status' => $new_status, ':lead_id' => $lead_id]);
        header("Location: manage_leads.php?partner_id=" . $partner_id);
        exit;
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="container">
        <h2 style="margin-bottom:20px;">Manage Leads</h2>
        
        <!-- Partner Filter Card -->
        <div class="card" style="text-align:left; margin-bottom:20px;">
            <h3 style="margin-bottom:10px;">Select Partner</h3>
            <form method="GET" action="manage_leads.php" style="display:flex; align-items:center; gap:10px;">
                <select id="partner_id" name="partner_id" onchange="this.form.submit()" style="flex:0 0 250px;">
                    <option value="">-- Select Partner --</option>
                    <?php foreach ($allPartners as $partner): ?>
                        <option value="<?php echo $partner['id']; ?>" 
                            <?php if ($partner['id'] == $partner_id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($partner['company_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <?php if ($partner_id): ?>
            <!-- Leads Summary Card -->
            <div class="card" style="text-align:left; margin-bottom:20px;">
                <h3 style="margin-bottom:10px;">
                    Leads for 
                    <?php 
                    foreach ($allPartners as $p) {
                        if ($p['id'] == $partner_id) {
                            echo htmlspecialchars($p['company_name']);
                            break;
                        }
                    }
                    ?>
                </h3>
                <p>Pending Leads: <strong><?php echo $pendingCount; ?></strong></p>
            </div>
            
            <?php if (count($leads) > 0): ?>
                <!-- Display each lead in a card grid -->
                <div class="card-grid" style="
                    display: grid; 
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
                    gap: 20px;
                ">
                    <?php foreach ($leads as $lead): ?>
                        <div class="card" style="text-align:left; padding:15px;">
                            <h3 style="margin-top:0;"><?php echo htmlspecialchars($lead['company_name']); ?></h3>
                            <div class="lead-field" style="margin-bottom:5px;">
                                <strong>Company Contact:</strong> 
                                <?php echo htmlspecialchars($lead['company_contact']); ?>
                            </div>
                            <div class="lead-field" style="margin-bottom:5px;">
                                <strong>Email:</strong> 
                                <?php echo htmlspecialchars($lead['email']); ?>
                            </div>
                            <div class="lead-field" style="margin-bottom:5px;">
                                <strong>Phone Number:</strong> 
                                <?php echo htmlspecialchars($lead['phone_number']); ?>
                            </div>
                            <div class="lead-field" style="margin-bottom:5px;">
                                <strong>HQ Address:</strong> 
                                <?php echo htmlspecialchars($lead['hq_address']); ?>
                            </div>
                            <div class="lead-field" style="margin-bottom:5px;">
                                <strong>HQ City:</strong> 
                                <?php echo htmlspecialchars($lead['hq_city']); ?>
                            </div>
                            <div class="lead-field" style="margin-bottom:5px;">
                                <strong>HQ State:</strong> 
                                <?php echo htmlspecialchars($lead['hq_state']); ?>
                            </div>
                            <div class="lead-field" style="margin-bottom:5px;">
                                <strong>HQ Zip:</strong> 
                                <?php echo htmlspecialchars($lead['hq_zip']); ?>
                            </div>
                            <div class="lead-field" style="margin-bottom:5px;">
                                <strong>Submitted By:</strong> 
                                <?php echo htmlspecialchars($lead['submitted_by']); ?>
                            </div>
                            <div class="lead-field" style="margin-bottom:5px;">
                                <strong>Submitted At:</strong> 
                                <?php echo htmlspecialchars($lead['submitted_at']); ?>
                            </div>
                            <div class="lead-field" style="margin-bottom:5px;">
                                <strong>Lead Status:</strong> 
                                <?php echo htmlspecialchars($lead['lead_status']); ?>
                            </div>
                            
                            <!-- Additional fields if present -->
                            <?php if (!empty($lead['rep_contact_info'])): ?>
                                <div class="lead-field" style="margin-bottom:5px;">
                                    <strong>Rep Contact Info:</strong> 
                                    <?php echo htmlspecialchars($lead['rep_contact_info']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($lead['lead_type'])): ?>
                                <div class="lead-field" style="margin-bottom:5px;">
                                    <strong>Lead Type:</strong> 
                                    <?php echo htmlspecialchars($lead['lead_type']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($lead['estimated_revenue'])): ?>
                                <div class="lead-field" style="margin-bottom:5px;">
                                    <strong>Estimated Revenue:</strong> 
                                    <?php echo htmlspecialchars($lead['estimated_revenue']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Status update dropdown -->
                            <div class="status-update" style="margin-top:10px;">
                                <form method="POST">
                                    <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                                    <label for="new_status_<?php echo $lead['id']; ?>" style="font-weight:bold;">
                                        Update Status:
                                    </label>
                                    <select id="new_status_<?php echo $lead['id']; ?>" 
                                            name="new_status" 
                                            onchange="this.form.submit()" 
                                            style="margin-left:5px;">
                                        <?php 
                                        $allowedStatuses = ['Under Review','Pending','Rejected','Sold'];
                                        foreach ($allowedStatuses as $status) {
                                            $selected = ($lead['lead_status'] === $status) ? 'selected' : '';
                                            echo "<option value='{$status}' {$selected}>{$status}</option>";
                                        }
                                        ?>
                                    </select>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No leads found for this partner.</p>
            <?php endif; ?>
        <?php else: ?>
            <p>Please select a partner to view their leads.</p>
        <?php endif; ?>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
