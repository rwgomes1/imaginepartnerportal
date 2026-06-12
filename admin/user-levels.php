<?php
session_start();
require_once '../includes/config.php';

// Only SuperAdmins can access this page
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Define permission keys and labels
$permissions = [
    'can_create_new_partner'      => 'Create New Partner',
    'can_modify_existing_partner' => 'Modify Existing Partner',
    'can_create_lead'             => 'Create Lead',
    'can_assign_leads'            => 'Assign Leads',
    'can_manage_leads'            => 'Manage Leads',
    'can_view_resource_library'   => 'View Resource Library',
    'can_add_to_resource_library' => 'Add Items to Resource Library',
];

// Roles to manage (the five roles as specified)
$roles = ['SuperAdmin', 'Admin', 'Representative', 'Partner', 'Partner Manager'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permissions'])) {
    $submitted = $_POST['permissions']; // Format: [role => [permission_key => '1']]
    
    // Loop through each role and update permissions in the DB
    foreach ($roles as $role) {
        // Prepare an update statement
        $stmt = $pdo->prepare("UPDATE user_levels SET 
            can_create_new_partner = :can_create_new_partner,
            can_modify_existing_partner = :can_modify_existing_partner,
            can_create_lead = :can_create_lead,
            can_assign_leads = :can_assign_leads,
            can_manage_leads = :can_manage_leads,
            can_view_resource_library = :can_view_resource_library,
            can_add_to_resource_library = :can_add_to_resource_library
            WHERE role = :role");

        // For each permission, check if it is set; default to 0 otherwise.
        $stmt->execute([
            ':can_create_new_partner'      => isset($submitted[$role]['can_create_new_partner']) ? 1 : 0,
            ':can_modify_existing_partner' => isset($submitted[$role]['can_modify_existing_partner']) ? 1 : 0,
            ':can_create_lead'             => isset($submitted[$role]['can_create_lead']) ? 1 : 0,
            ':can_assign_leads'            => isset($submitted[$role]['can_assign_leads']) ? 1 : 0,
            ':can_manage_leads'            => isset($submitted[$role]['can_manage_leads']) ? 1 : 0,
            ':can_view_resource_library'   => isset($submitted[$role]['can_view_resource_library']) ? 1 : 0,
            ':can_add_to_resource_library' => isset($submitted[$role]['can_add_to_resource_library']) ? 1 : 0,
            ':role'                        => $role,
        ]);
    }
    $message = "User level permissions updated successfully.";
}

// Fetch the current permissions for each role from the DB
$permissionsData = [];
foreach ($roles as $role) {
    $stmt = $pdo->prepare("SELECT * FROM user_levels WHERE role = :role");
    $stmt->execute([':role' => $role]);
    $permissionsData[$role] = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
    <div class="container">
        <h2>User Level Permissions</h2>
        <?php if (isset($message)) : ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <table border="1" cellspacing="0" cellpadding="10" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Role</th>
                        <?php foreach ($permissions as $key => $label) : ?>
                            <th><?php echo htmlspecialchars($label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role) : 
                        $data = isset($permissionsData[$role]) ? $permissionsData[$role] : [];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($role); ?></td>
                            <?php foreach ($permissions as $key => $label) : ?>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="permissions[<?php echo htmlspecialchars($role); ?>][<?php echo htmlspecialchars($key); ?>]" value="1"
                                    <?php echo (isset($data[$key]) && $data[$key] == 1) ? 'checked' : ''; ?>>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
            <input type="submit" value="Update Permissions" style="padding:10px 20px; font-size:16px;">
        </form>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
