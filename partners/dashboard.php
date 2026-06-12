<?php
session_start();
require_once '../includes/config.php'; // Updated configuration file path

// Check if the user is logged in and is a partner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'partner') {
    header('Location: ../login.php');
    exit;
}

// Create PDO connection
$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Retrieve partner_id from session or from the users table if not already set
$partner_id = isset($_SESSION['partner_id']) ? $_SESSION['partner_id'] : null;
if (!$partner_id) {
    $stmt = $pdo->prepare("SELECT partner_id FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['partner_id'])) {
        $partner_id = $row['partner_id'];
        $_SESSION['partner_id'] = $partner_id;
    } else {
        die("Partner information not found.");
    }
}

/*
 * 1) Handle partner document upload (drag & drop).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'partner_upload') {
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        // Create partner-specific folder if needed
        $target_dir = __DIR__ . '/../uploads/' . $partner_id . '/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        // Sanitize filename
        $originalName = basename($_FILES['document']['name']);
        $newName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $target_file = $target_dir . $newName;

        if (move_uploaded_file($_FILES['document']['tmp_name'], $target_file)) {
            // Store file info in DB
            $relativePath = '/uploads/' . $partner_id . '/' . $newName;
            $docStmt = $pdo->prepare("
                INSERT INTO partner_documents (partner_id, file_name, file_path)
                VALUES (:partner_id, :file_name, :file_path)
            ");
            $docStmt->execute([
                ':partner_id' => $partner_id,
                ':file_name'  => $originalName,
                ':file_path'  => $relativePath
            ]);
        }
    }
    // Refresh the page to show updated docs
    header('Location: dashboard.php');
    exit;
}

/*
 * 2) Fetch documents for display.
 */
$docStmt = $pdo->prepare("SELECT * FROM partner_documents WHERE partner_id = :pid ORDER BY id DESC");
$docStmt->execute([':pid' => $partner_id]);
$documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve company name for the partner
$stmt = $pdo->prepare("SELECT company_name FROM partners WHERE id = :id");
$stmt->execute([':id' => $partner_id]);
$partner = $stmt->fetch(PDO::FETCH_ASSOC);
$companyName = $partner ? $partner['company_name'] : '';

// Retrieve last login timestamp from the database for the logged-in partner
$stmt = $pdo->prepare("SELECT last_login FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$lastLogin = $userData && !empty($userData['last_login']) ? $userData['last_login'] : 'Not Available';
$lastLoginFormatted = ($lastLogin !== 'Not Available') ? date("M d, Y H:i:s", strtotime($lastLogin)) : $lastLogin;

// Retrieve login IP address
$loginIP = $_SERVER['REMOTE_ADDR'];

// --- Sorting for Submitted Leads Section ---
$allowedSortColumns = ['company_name', 'submitted_by', 'lead_status', 'submitted_at'];
$sortColumn = (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns)) ? $_GET['sort'] : 'submitted_at';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
$orderBy = "$sortColumn $order";

// Retrieve submitted leads for this partner with sorting applied
$stmt = $pdo->prepare("SELECT * FROM partner_leads WHERE partner_id = :partner_id ORDER BY $orderBy");
$stmt->execute([':partner_id' => $partner_id]);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve upcoming tasks (not completed) for this partner, ordered by due date ascending
$taskStmt = $pdo->prepare("SELECT * FROM partner_tasks WHERE partner_id = :partner_id AND completed = 0 ORDER BY due_by ASC");
$taskStmt->execute([':partner_id' => $partner_id]);
$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

// Include unified header & sidebar
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="container">
        <style>
            /* Additional styling for full-width cards, drag & drop, etc. */
            .card.full-width {
                width: 100%;
                margin-top: 20px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            table th, table td {
                border: 1px solid #ccc;
                padding: 8px;
                text-align: left;
            }
            table th {
                background-color: #f2f2f2;
            }
            .sorting-link {
                text-decoration: none;
                margin-left: 5px;
                font-size: 0.8em;
                color: #007BFF;
            }
            /* Progress bar styling */
            .progress-bar {
                width: 100%;
                background-color: #e0e0e0;
                border-radius: 10px;
                height: 20px;
                margin-top: 10px;
                margin-bottom: 10px;
                overflow: hidden;
            }
            .progress {
                background-color: #5367FF;
                height: 100%;
                border-radius: 10px;
                transition: width 0.4s ease;
            }
            /* Document table style */
            .document-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
                margin-top: 10px;
            }
            .document-table th,
            .document-table td {
                padding: 8px;
                border-bottom: 1px solid #ddd;
            }
            .document-table th {
                background-color: #f2f2f2;
            }
            /* Drag & Drop styling */
            #drop-zone {
                border: 2px dashed #bbb;
                border-radius: 5px;
                padding: 20px;
                text-align: center;
                color: #bbb;
                cursor: pointer;
            }
            #drop-zone:hover {
                background-color: #f7f7f7;
            }
        </style>

        <!-- Welcome Section with Company Name, Last Login, and IP -->
        <div class="card">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
            <?php if ($companyName): ?>
                <p>Company: <?php echo htmlspecialchars($companyName); ?></p>
            <?php endif; ?>
            <p>Last Logged In: <?php echo htmlspecialchars($lastLoginFormatted); ?></p>
            <p>Logged In from: <?php echo htmlspecialchars($loginIP); ?></p>
        </div>

        <!-- Progress Tracker Card -->
        <div class="card" style="margin-bottom: 20px;">
            <h3>Progress Tracker</h3>
            <?php
            // The same 18 tasks used on the admin side
            $allTasks = [
                "Review referral/reseller agreements",
                "Sign NDAs and clarify expectations",
                "Finalize contracts with partners",
                "Complete contracts and NDAs",
                "Obtain Partner Qualification Guideline approval",
                "Review technical integration documentation",
                "IT VSAQ approval and kickoff meeting",
                "Introduction to the Partner Portal",
                "High-level review of integration documentation",
                "Development build and testing phase",
                "Full integration ready for deployment",
                "Testing with pilot clients",
                "Demo and sales training videos provided",
                "Obtain optional certifications (ICP, ICEE)",
                "Transition to Relationship Officer",
                "Maintain successful partnership",
                "Add additional VAPs and integrations",
                "Expand client base and enrich opportunities"
            ];
            $totalTasks = count($allTasks);

            // Query how many tasks are completed + which tasks are completed
            $inClause = implode(',', array_fill(0, $totalTasks, '?'));
            $query = "
                SELECT task
                FROM partner_tasks
                WHERE partner_id = ?
                  AND completed = 1
                  AND task IN ($inClause)
            ";
            $stmt = $pdo->prepare($query);
            $executeParams = [$partner_id];
            foreach ($allTasks as $t) {
                $executeParams[] = $t;
            }
            $stmt->execute($executeParams);
            $completedRows = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Convert to a set for quick lookup
            $completedSet = [];
            foreach ($completedRows as $taskName) {
                $completedSet[$taskName] = true;
            }
            $completedCount = count($completedRows);
            $progressPercent = ($totalTasks > 0) ? round(($completedCount / $totalTasks) * 100) : 0;
            ?>
            <p style="margin: 5px 0; font-size: 1.0em;">
                Completed <?php echo $completedCount; ?> of <?php echo $totalTasks; ?> (<?php echo $progressPercent; ?>%).
            </p>
            <div class="progress-bar">
                <div class="progress" style="width: <?php echo $progressPercent; ?>%;"></div>
            </div>
            <!-- List of all tasks with checkmark if completed -->
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach ($allTasks as $taskName): ?>
                    <li style="display: flex; justify-content: space-between; align-items: center; margin: 8px 0;">
                        <span><?php echo htmlspecialchars($taskName); ?></span>
                        <?php if (isset($completedSet[$taskName])): ?>
                            <span style="color: green; font-weight: bold;">✔</span>
                        <?php else: ?>
                            <span style="color: #ccc;">—</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Upcoming Deadlines Card -->
            <div class="card">
                <h3>Upcoming Deadlines</h3>
                <?php if (count($tasks) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Due By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['task']); ?></td>
                                    <td><?php echo htmlspecialchars($task['due_by']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No upcoming deadlines.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Certification, Resources, and Support Links -->
        <div class="card-grid centered-cards">
            <div class="card">
                <h3>Certification</h3>
                <p>Continue your certifications</p>
                <a href="certifications.php" class="button">Go to Certifications</a>
            </div>
            <div class="card">
                <h3>Resources</h3>
                <p>Access training materials</p>
                <a href="resources/index.php" class="button">View Resources</a>
            </div>
            <div class="card">
                <h3>Support</h3>
                <p>Get help and support</p>
                <a href="support.php" class="button">Contact Support</a>
            </div>
        </div>

        <!-- Full Width Upload Document Card (drag & drop) -->
        <div class="card full-width" style="text-align: left; padding:20px;">
            <h3>Upload Document</h3>
            <form method="POST" enctype="multipart/form-data" id="uploadForm" style="margin-top: 10px;">
                <input type="hidden" name="action" value="partner_upload">
                <div id="drop-zone" style="margin-bottom:10px;">
                    Drag &amp; Drop File Here or Click to Browse
                </div>
                <input type="file" name="document" id="doc_file" style="display: none;" required>
                <div id="chosen-file-name" style="margin-top:10px; font-size:14px; color:#666;"></div>
                <button type="submit" class="button" style="margin-top:10px;">Upload</button>
            </form>
        </div>

        <!-- Full Width Documents Card -->
        <div class="card full-width" style="text-align: left; padding:20px;">
            <h3>Documents</h3>
            <?php if (!empty($documents)): ?>
                <table class="document-table">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Download</th>
                            <th>Reviewed</th>
                            <th>Approved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                       target="_blank" class="button" style="padding:5px 10px;">
                                       Download
                                    </a>
                                </td>
                                <td>
                                    <?php if ($doc['reviewed_by']): ?>
                                        <span style="color: green; font-weight: bold;">Yes</span>
                                    <?php else: ?>
                                        <span style="color: red;">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($doc['approved_by']): ?>
                                        <span style="color: green; font-weight: bold;">Yes</span>
                                    <?php else: ?>
                                        <span style="color: red;">No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No documents uploaded yet.</p>
            <?php endif; ?>
        </div>

        <!-- Submitted Leads Card (Full Width) - No "Submit New Lead" button -->
        <div class="card full-width">
            <h3>Your Submitted Leads</h3>
            <?php if (count($leads) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>
                                Company Name
                                <a href="dashboard.php?sort=company_name&order=asc" class="sorting-link">▲</a>
                                <a href="dashboard.php?sort=company_name&order=desc" class="sorting-link">▼</a>
                            </th>
                            <th>
                                Rep Who Submitted
                                <a href="dashboard.php?sort=submitted_by&order=asc" class="sorting-link">▲</a>
                                <a href="dashboard.php?sort=submitted_by&order=desc" class="sorting-link">▼</a>
                            </th>
                            <th>
                                Status
                                <a href="dashboard.php?sort=lead_status&order=asc" class="sorting-link">▲</a>
                                <a href="dashboard.php?sort=lead_status&order=desc" class="sorting-link">▼</a>
                            </th>
                            <th>
                                When Submitted
                                <a href="dashboard.php?sort=submitted_at&order=asc" class="sorting-link">▲</a>
                                <a href="dashboard.php?sort=submitted_at&order=desc" class="sorting-link">▼</a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lead['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($lead['submitted_by']); ?></td>
                                <td><?php echo htmlspecialchars($lead['lead_status']); ?></td>
                                <td><?php echo htmlspecialchars($lead['submitted_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No leads submitted yet.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<script>
// Drag-and-drop functionality for the upload document card
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('doc_file');
const chosenFileName = document.getElementById('chosen-file-name');

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.style.backgroundColor = '#f7f7f7';
});
dropZone.addEventListener('dragleave', () => {
    dropZone.style.backgroundColor = '';
});
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.style.backgroundColor = '';
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        chosenFileName.textContent = e.dataTransfer.files[0].name;
    }
});

fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
        chosenFileName.textContent = fileInput.files[0].name;
    } else {
        chosenFileName.textContent = '';
    }
});
</script>
