<?php
session_start();
require_once '../../includes/config.php';

// Check if the user is logged in and has appropriate permissions (admin or superadmin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header('Location: /login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Fetch all partners with status "Pending Application"
$stmt = $pdo->prepare("
    SELECT id, company_name, main_contact, application_status
    FROM partners
    WHERE application_status = 'Pending Application'
    ORDER BY company_name ASC
");
$stmt->execute();
$pendingPartners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include unified header and sidebar
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Pending Partners</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .card {
            margin-bottom: 20px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table th, table td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }
        table th {
            background-color: #f9f9f9;
        }
        .button {
            padding: 8px 15px;
            background-color: #0056b3;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
        }
        .button:hover {
            background-color: #003f8c;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>Pending Partners</h2>
        <?php if (count($pendingPartners) === 0): ?>
            <p>No pending partner applications found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Main Contact</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingPartners as $partner): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($partner['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($partner['main_contact']); ?></td>
                            <td><?php echo htmlspecialchars($partner['application_status']); ?></td>
                            <td>
                                <a href="pending_details.php?partner_id=<?php echo $partner['id']; ?>" class="button">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
</body>
</html>
