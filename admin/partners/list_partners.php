<?php
session_start();
require_once '../../includes/config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$errorMsg = '';
$partners = [];

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Build the query, now including partner_type
    $sql = "
        SELECT id, company_name, created_at, approved_at, approved_by, partner_type
        FROM partners
    ";
    if ($search !== '') {
        $sql .= " WHERE company_name LIKE :search";
    }
    $sql .= " ORDER BY company_name ASC";

    $stmt = $pdo->prepare($sql);
    if ($search !== '') {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
    $stmt->execute();
    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errorMsg = 'Database error: ' . htmlspecialchars($e->getMessage());
}

// Group partners by first letter
$grouped = [];
if (!empty($partners)) {
    foreach ($partners as $p) {
        $letter = strtoupper(substr($p['company_name'], 0, 1));
        $grouped[$letter][] = $p;
    }
    ksort($grouped);
}

// Include unified header & sidebar
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Partner List</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: auto;
        }
        .card {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        /* Search Form */
        .search-form {
            margin-bottom: 20px;
            text-align: center;
        }
        .search-form input[type="text"] {
            width: 70%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 5px;
        }
        .search-form button {
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            background: #0056b3;
            color: #fff;
            cursor: pointer;
        }
        .search-form button:hover {
            background-color: #003f8c;
        }

        /* Alphabet group */
        .alphabet-group {
            margin-bottom: 30px;
        }
        .alphabet-group h3 {
            text-align: left;
            margin-bottom: 10px;
            font-size: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            color: #333;
        }

        /* Partner Table */
        .partner-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* ensures uniform column sizes */
        }
        .partner-table thead th {
            background-color: #f9f9f9;
            font-weight: bold;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .partner-table tbody td {
            padding: 10px;
            border: 1px solid #eee;
        }
        .partner-table tbody tr:nth-child(even) {
            background-color: #fcfcfc;
        }

        /*
         Force each column to a specific width (sum <= 100%):
         1) Company        = 25%
         2) Date Applied   = 15%
         3) Date Approved  = 15%
         4) Approved By    = 20%
         5) Partner Type   = 25%
        */
        .partner-table th:nth-child(1),
        .partner-table td:nth-child(1) {
            width: 25%;
        }
        .partner-table th:nth-child(2),
        .partner-table td:nth-child(2) {
            width: 15%;
        }
        .partner-table th:nth-child(3),
        .partner-table td:nth-child(3) {
            width: 15%;
        }
        .partner-table th:nth-child(4),
        .partner-table td:nth-child(4) {
            width: 20%;
        }
        .partner-table th:nth-child(5),
        .partner-table td:nth-child(5) {
            width: 25%;
        }

        .partner-link {
            color: #007bff;
            text-decoration: none;
        }
        .partner-link:hover {
            text-decoration: underline;
        }
        .date-cell {
            font-size: 14px;
            color: #666;
            white-space: nowrap; /* keep date on one line */
        }
        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>Partner List</h2>

        <!-- Optional DB error message -->
        <?php if ($errorMsg): ?>
            <p class="error-message"><?php echo $errorMsg; ?></p>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="search-form">
            <form method="GET" action="list_partners.php">
                <input type="text" name="q" placeholder="Search for a partner..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if (empty($partners) && !$errorMsg): ?>
            <p style="text-align:center;">No partners found.</p>
        <?php else: ?>
            <?php foreach ($grouped as $letter => $list): ?>
                <div class="alphabet-group">
                    <h3><?php echo $letter; ?></h3>
                    <table class="partner-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Date Applied</th>
                                <th>Date Approved</th>
                                <th>Approved By</th>
                                <th>Partner Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list as $p): ?>
                                <?php
                                // Determine row style based on partner_type
                                $rowStyle = '';
                                if (!empty($p['partner_type'])) {
                                    if ($p['partner_type'] === 'Preferred Partner') {
                                        // Light green background
                                        $rowStyle = ' style="background-color: #c4f6c4;"';
                                    } elseif ($p['partner_type'] === 'Certified Partner') {
                                        // Light blue background
                                        $rowStyle = ' style="background-color: #b3e5fc;"'; 
                                        // or #00aeef is bright; choose a lighter shade or a pastel version
                                    }
                                }
                                ?>
                                <tr<?php echo $rowStyle; ?>>
                                    <!-- Company Name -->
                                    <td>
                                        <a href="view_partner.php?id=<?php echo $p['id']; ?>"
                                           class="partner-link">
                                           <?php echo htmlspecialchars($p['company_name']); ?>
                                        </a>
                                    </td>
                                    <!-- Date Applied (created_at) -->
                                    <td class="date-cell">
                                        <?php 
                                        if (!empty($p['created_at'])) {
                                            echo date("M d, Y", strtotime($p['created_at']));
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </td>
                                    <!-- Date Approved (approved_at) -->
                                    <td class="date-cell">
                                        <?php 
                                        if (!empty($p['approved_at'])) {
                                            echo date("M d, Y", strtotime($p['approved_at']));
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </td>
                                    <!-- Approved By (approved_by) -->
                                    <td class="date-cell">
                                        <?php 
                                        echo !empty($p['approved_by']) ? htmlspecialchars($p['approved_by']) : "N/A";
                                        ?>
                                    </td>
                                    <!-- Partner Type -->
                                    <td>
                                        <?php
                                        echo !empty($p['partner_type'])
                                            ? htmlspecialchars($p['partner_type'])
                                            : "Approved Partner"; // fallback if blank
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
</body>
</html>
