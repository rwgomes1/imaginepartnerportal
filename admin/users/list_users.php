<?php
session_start();
require_once '../../includes/config.php';

// Only allow SuperAdmins and Admins to view the user management page,
// but only SuperAdmins can delete users.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin')) {
    header('Location: /login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Handle deletion if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    // Only SuperAdmins can delete users
    if ($_SESSION['role'] !== 'superadmin') {
        die("You do not have permission to delete users.");
    }
    
    $deleteUserId = intval($_POST['user_id']);
    $adminPassword = $_POST['admin_password'] ?? '';

    // Verify current admin's password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $currentAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentAdmin || !password_verify($adminPassword, $currentAdmin['password_hash'])) {
        $error = "Admin password incorrect. Deletion aborted.";
    } else {
        // Delete the user record
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $deleteUserId]);
        $success = "User deleted successfully.";
    }
}

// Retrieve all users along with their associated partner (if any)
$stmt = $pdo->query("
    SELECT u.*, p.company_name 
    FROM users u 
    LEFT JOIN partners p ON u.partner_id = p.id 
    ORDER BY u.name ASC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group users alphabetically by name
$grouped = [];
foreach ($users as $user) {
    $letter = strtoupper(substr($user['name'], 0, 1));
    $grouped[$letter][] = $user;
}
ksort($grouped);
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?> 
<!-- Remove the line above if you do NOT want the sidebar here -->

<main class="main-content">
    <div class="container">
        <h2>User Management</h2>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p style="color:green;"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <?php foreach ($grouped as $letter => $usersInGroup): ?>
            <div class="group-header" 
                 style="background-color: #eee; font-weight: bold; padding: 5px; margin-top: 20px;">
                <?php echo $letter; ?>
            </div>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed;">
                <!-- Define 6 columns each ~16.66% wide to keep uniform widths -->
                <colgroup>
                    <col style="width:16.66%;">
                    <col style="width:16.66%;">
                    <col style="width:16.66%;">
                    <col style="width:16.66%;">
                    <col style="width:16.66%;">
                    <col style="width:16.66%;">
                </colgroup>
                <thead>
                    <tr>
                        <th style="padding:8px; border:1px solid #ddd; background-color:#f9f9f9;">Name</th>
                        <th style="padding:8px; border:1px solid #ddd; background-color:#f9f9f9;">Partner</th>
                        <th style="padding:8px; border:1px solid #ddd; background-color:#f9f9f9;">Permissions</th>
                        <th style="padding:8px; border:1px solid #ddd; background-color:#f9f9f9;">Last Logged In</th>
                        <th style="padding:8px; border:1px solid #ddd; background-color:#f9f9f9;">Edit</th>
                        <th style="padding:8px; border:1px solid #ddd; background-color:#f9f9f9;">
                            <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                Delete
                            <?php endif; ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersInGroup as $usr): ?>
                        <tr>
                            <!-- Name -->
                            <td style="padding:8px; border:1px solid #ddd; word-wrap:break-word;">
                                <?php echo htmlspecialchars($usr['name']); ?>
                            </td>
                            <!-- Partner -->
                            <td style="padding:8px; border:1px solid #ddd; word-wrap:break-word;">
                                <?php echo htmlspecialchars($usr['company_name'] ?: 'N/A'); ?>
                            </td>
                            <!-- Permissions (role) -->
                            <td style="padding:8px; border:1px solid #ddd; word-wrap:break-word;">
                                <?php echo htmlspecialchars($usr['role']); ?>
                            </td>
                            <!-- Last Logged In -->
                            <td style="padding:8px; border:1px solid #ddd; word-wrap:break-word;">
                                <?php 
                                echo (!empty($usr['last_login'])) 
                                    ? date("M d, Y H:i:s", strtotime($usr['last_login'])) 
                                    : 'N/A'; 
                                ?>
                            </td>
                            <!-- Edit button -->
                            <td style="padding:8px; border:1px solid #ddd; word-wrap:break-word;">
                                <a href="/admin/users/edit_user.php?user_id=<?php echo $usr['id']; ?>"
                                   style="padding:5px 10px; margin-right:5px; text-decoration:none; background-color:#0056b3; color:#fff; border-radius:4px;">
                                   Edit
                                </a>
                            </td>
                            <!-- Delete (only if superadmin) -->
                            <td style="padding:8px; border:1px solid #ddd; word-wrap:break-word;">
                                <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirmDeletion(<?php echo $usr['id']; ?>);">
                                        <input type="hidden" name="user_id" value="<?php echo $usr['id']; ?>">
                                        <input type="hidden" name="admin_password" id="adminPasswordInput_<?php echo $usr['id']; ?>" value="">
                                        <input type="hidden" name="delete_user" value="1">
                                        <button type="submit" 
                                                style="padding:5px 10px; text-decoration:none; background-color:#c00; color:#fff; border-radius:4px; border:none; cursor:pointer;">
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<script>
function confirmDeletion(userId) {
    var adminPassword = prompt("Enter your admin password to confirm deletion:");
    if (adminPassword === null || adminPassword === "") {
        return false;
    }
    document.getElementById("adminPasswordInput_" + userId).value = adminPassword;
    return true;
}
</script>
