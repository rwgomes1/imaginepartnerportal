<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in and has appropriate permissions (admin or superadmin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header('Location: /login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Handle all POST actions (tasks, document upload, review, approve, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Add a new task
    if (isset($_POST['action']) && $_POST['action'] === 'add_task') {
        $task = htmlspecialchars(trim($_POST['task'] ?? ''));
        $due_by = $_POST['due_by'] ?? '';
        $partner_id_form = intval($_POST['partner_id'] ?? 0);

        if (!empty($task) && !empty($due_by) && $partner_id_form) {
            $insertTaskStmt = $pdo->prepare("
                INSERT INTO partner_tasks (partner_id, task, due_by)
                VALUES (:partner_id, :task, :due_by)
            ");
            $insertTaskStmt->execute([
                ':partner_id' => $partner_id_form,
                ':task'       => $task,
                ':due_by'     => $due_by
            ]);
        }
        header("Location: view_partner.php?id=" . $partner_id_form);
        exit;
    }

    // 2. Mark task as completed
    if (isset($_POST['action']) && $_POST['action'] === 'complete_task') {
        $task_id = intval($_POST['task_id'] ?? 0);
        $partner_id_form = intval($_POST['partner_id'] ?? 0);
        $updateTaskStmt = $pdo->prepare("UPDATE partner_tasks SET completed = 1 WHERE id = :task_id");
        $updateTaskStmt->execute([':task_id' => $task_id]);
        header("Location: view_partner.php?id=" . $partner_id_form);
        exit;
    }

    // 3. Handle document upload (drag-and-drop or browse)
    if (isset($_FILES['document']) && isset($_POST['partner_id'])) {
        $partner_id_form = intval($_POST['partner_id']);
        
        if ($_FILES['document']['error'] === UPLOAD_ERR_OK) {
            // Create partner-specific folder if needed
            $target_dir = __DIR__ . '/../../uploads/' . $partner_id_form . '/';
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            // Sanitize filename
            $originalName = basename($_FILES['document']['name']);
            $newName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
            $target_file = $target_dir . $newName;

            if (move_uploaded_file($_FILES['document']['tmp_name'], $target_file)) {
                // Store file info in DB
                $relativePath = '/uploads/' . $partner_id_form . '/' . $newName;
                $docStmt = $pdo->prepare("
                    INSERT INTO partner_documents (partner_id, file_name, file_path)
                    VALUES (:partner_id, :file_name, :file_path)
                ");
                $docStmt->execute([
                    ':partner_id' => $partner_id_form,
                    ':file_name'  => $originalName,
                    ':file_path'  => $relativePath
                ]);
            }
        }
        header("Location: view_partner.php?id=" . $partner_id_form);
        exit;
    }

    // 4. Document Review/Approve
    if (isset($_POST['doc_id']) && isset($_POST['action']) && in_array($_POST['action'], ['review','approve'])) {
        $doc_id = intval($_POST['doc_id'] ?? 0);
        $partner_id_form = intval($_POST['partner_id'] ?? 0);

        if ($_POST['action'] === 'review') {
            $reviewStmt = $pdo->prepare("
                UPDATE partner_documents
                SET reviewed_by = :reviewed_by, reviewed_at = NOW()
                WHERE id = :doc_id
            ");
            $reviewStmt->execute([
                ':reviewed_by' => $_SESSION['user_id'],
                ':doc_id'      => $doc_id
            ]);
        }

        if ($_POST['action'] === 'approve') {
            $approveStmt = $pdo->prepare("
                UPDATE partner_documents
                SET approved_by = :approved_by, approved_at = NOW()
                WHERE id = :doc_id
            ");
            $approveStmt->execute([
                ':approved_by' => $_SESSION['user_id'],
                ':doc_id'      => $doc_id
            ]);
        }
        header("Location: view_partner.php?id=" . $partner_id_form);
        exit;
    }

    // 5. Document Deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete_document') {
        $doc_id = intval($_POST['doc_id'] ?? 0);
        $partner_id_form = intval($_POST['partner_id'] ?? 0);

        // Fetch the doc info to delete from server
        $fetchDocStmt = $pdo->prepare("SELECT * FROM partner_documents WHERE id = :doc_id");
        $fetchDocStmt->execute([':doc_id' => $doc_id]);
        $doc = $fetchDocStmt->fetch(PDO::FETCH_ASSOC);

        if ($doc && intval($doc['partner_id']) === $partner_id_form) {
            // Remove the file from the server
            $filePath = __DIR__ . '/../../' . ltrim($doc['file_path'], '/');
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            // Remove record from DB
            $deleteDocStmt = $pdo->prepare("DELETE FROM partner_documents WHERE id = :doc_id");
            $deleteDocStmt->execute([':doc_id' => $doc_id]);
        }
        header("Location: view_partner.php?id=" . $partner_id_form);
        exit;
    }
}

// Fetch partner info, documents, and tasks
$partner = null;
$documents = [];
$tasks = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $partner_id = $_GET['id'];
    try {
        // Partner details
        $stmt = $pdo->prepare('SELECT * FROM partners WHERE id = :id');
        $stmt->execute([':id' => $partner_id]);
        $partner = $stmt->fetch(PDO::FETCH_ASSOC);

        // Documents
        $docStmt = $pdo->prepare('SELECT * FROM partner_documents WHERE partner_id = :partner_id');
        $docStmt->execute([':partner_id' => $partner_id]);
        $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        // Incomplete tasks
        $taskStmt = $pdo->prepare("
            SELECT * FROM partner_tasks
            WHERE partner_id = :partner_id
              AND completed = 0
            ORDER BY due_by ASC
        ");
        $taskStmt->execute([':partner_id' => $partner_id]);
        $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $partner = null;
        $documents = [];
        $tasks = [];
    }
}

// Redirect if partner not found
if (!$partner) {
    header('Location: /admin/dashboard.php');
    exit;
}

// Include unified header and sidebar
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($partner['company_name'] ?? ''); ?> - View Partner</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        /* Basic card styling */
        .card {
            margin-bottom: 20px;
        }

        /* Document upload area */
        #drop-zone {
            border: 2px dashed #bbb;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            color: #bbb;
            cursor: pointer;
            margin-top: 10px;
        }
        /* The file name displayed after selection */
        #chosen-file-name {
            margin-top: 5px;
            font-size: 14px;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Documents grid: 4 columns => doc link, reviewed, approved, delete */
        .documents-grid-header, .documents-grid-row {
            display: grid;
            grid-template-columns: 2fr auto auto auto;
            gap: 10px;
            align-items: center;
        }
        .documents-grid-header {
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .documents-grid-row {
            border-bottom: 1px solid #f0f0f0;
            padding: 8px 0;
        }
        .doc-filename {
            /* Truncate with ellipsis */
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        /* Delete link style */
        .delete-link {
            color: #c00;
            text-decoration: none;
        }
        .delete-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Partner Name Card -->
        <div class="card partner-name-card">
            <h2><?php echo htmlspecialchars($partner['company_name'] ?? ''); ?></h2>
        </div>

        <!-- Company Information Card -->
        <div class="card" style="text-align:left;">
            <h3>Company Information</h3>
            <p><strong>Company Legal Name:</strong> <?php echo htmlspecialchars($partner['company_legal_name'] ?? ''); ?></p>
            <p><strong>DBA / FKA:</strong> <?php echo htmlspecialchars($partner['dba_fka'] ?? ''); ?></p>
            <p><strong>HQ Address:</strong> <?php echo htmlspecialchars($partner['company_hq_address'] ?? ''); ?></p>
            <p><strong>City:</strong> <?php echo htmlspecialchars($partner['company_hq_city'] ?? ''); ?></p>
            <p><strong>State:</strong> <?php echo htmlspecialchars($partner['company_hq_state'] ?? ''); ?></p>
            <p><strong>Zip Code:</strong> <?php echo htmlspecialchars($partner['company_hq_zip_code'] ?? ''); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($partner['company_phone_number'] ?? ''); ?></p>
            <p><strong>EIN:</strong> <?php echo htmlspecialchars($partner['ein_number'] ?? ''); ?></p>
        </div>

        <!-- Contact Info: 3 separate cards in a grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px;">
            <!-- Main Contact Card -->
            <div class="card" style="text-align:left;">
                <h3>Main Contact</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($partner['main_contact_name'] ?? ''); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($partner['main_contact_email'] ?? ''); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($partner['main_contact_phone'] ?? ''); ?></p>
            </div>
            <!-- Finance Contact Card -->
            <div class="card" style="text-align:left;">
                <h3>Finance Contact</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($partner['finance_contact_name'] ?? ''); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($partner['finance_contact_email'] ?? ''); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($partner['finance_contact_phone_number'] ?? ''); ?></p>
            </div>
            <!-- Technical Contact Card -->
            <div class="card" style="text-align:left;">
                <h3>Technical Contact</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($partner['technical_contact_name'] ?? ''); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($partner['technical_contact_email'] ?? ''); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($partner['technical_contact_phone_number'] ?? ''); ?></p>
            </div>
        </div>

        <!-- Centered Button Row for Actions -->
        <div class="card" style="text-align: center;">
            <div style="display:flex; justify-content:center; gap:20px; margin-top:20px;">
                <a href="/admin/users/create_partner_user.php?partner_id=<?php echo $partner['id']; ?>" class="button">Add Partner User</a>
                <a href="/admin/partners/edit_partner.php?id=<?php echo $partner['id']; ?>" class="button">Edit Partner</a>
                <a href="/admin/leads/manage_leads.php?partner_id=<?php echo $partner['id']; ?>" class="button">Manage Leads</a>
            </div>
        </div>

        <!-- Document Upload & Documents Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap:20px;">
            <!-- Upload Document Section -->
            <div class="card" style="text-align: left;">
                <h3>Upload Document</h3>
                <form method="POST" enctype="multipart/form-data" id="uploadForm" style="margin-top: 10px;">
                    <input type="hidden" name="partner_id" value="<?php echo $partner['id']; ?>">
                    <div id="drop-zone">Drag &amp; Drop File Here or Click to Browse</div>
                    <input type="file" name="document" id="doc_file" style="display: none;" required>
                    <!-- Display chosen file name here -->
                    <div id="chosen-file-name"></div>
                    <button type="submit" class="button" style="margin-top: 10px;">Upload</button>
                </form>
            </div>

            <!-- Documents Section (Grid layout, 4 columns => doc link, reviewed, approved, delete) -->
            <div class="card" style="text-align: left;">
                <h3>Documents</h3>
                <?php if (!empty($documents)): ?>
                    <div class="documents-grid-header" 
                         style="
                             display: grid; 
                             grid-template-columns: 2fr auto auto auto; 
                             gap: 10px; 
                             align-items: center; 
                             font-weight: bold; 
                             border-bottom: 1px solid #ddd; 
                             padding-bottom: 5px;
                         ">
                        <div>Document</div>
                        <div>Reviewed</div>
                        <div>Approved</div>
                        <div>Delete</div>
                    </div>

                    <?php foreach ($documents as $doc): ?>
                        <div class="documents-grid-row" 
                             style="
                                 display: grid; 
                                 grid-template-columns: 2fr auto auto auto; 
                                 gap: 10px; 
                                 align-items: flex-start; 
                                 border-bottom: 1px solid #f0f0f0; 
                                 padding: 8px 0;
                             ">
                            <!-- Column 1: truncated doc name as clickable link -->
                            <div class="doc-filename" 
                                 style="
                                     overflow: hidden; 
                                     white-space: nowrap; 
                                     text-overflow: ellipsis; 
                                     max-width: 200px;
                                 "
                                 title="<?php echo htmlspecialchars($doc['file_name'] ?? ''); ?>">
                                <a href="<?php echo htmlspecialchars($doc['file_path'] ?? ''); ?>" 
                                   target="_blank" 
                                   style="color: #007bff; text-decoration: none;">
                                   <?php echo htmlspecialchars($doc['file_name'] ?? ''); ?>
                                </a>
                            </div>

                            <!-- Column 2: Reviewed checkbox + timestamp below -->
                            <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                    <input type="hidden" name="action" value="review">
                                    <input type="hidden" name="partner_id" value="<?php echo $partner['id']; ?>">
                                    <input 
                                        type="checkbox" 
                                        onchange="this.form.submit()" 
                                        <?php echo $doc['reviewed_by'] ? 'checked' : ''; ?>
                                    >
                                </form>
                                <?php if ($doc['reviewed_by']): ?>
                                    <span style="font-size:12px; color:#666; margin-top:2px;">
                                        Reviewed by <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>
                                        <br>on <?php echo htmlspecialchars($doc['reviewed_at'] ?? ''); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Column 3: Approved checkbox + timestamp below -->
                            <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="partner_id" value="<?php echo $partner['id']; ?>">
                                    <input 
                                        type="checkbox" 
                                        onchange="this.form.submit()" 
                                        <?php echo $doc['approved_by'] ? 'checked' : ''; ?>
                                    >
                                </form>
                                <?php if ($doc['approved_by']): ?>
                                    <span style="font-size:12px; color:#666; margin-top:2px;">
                                        Approved by <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>
                                        <br>on <?php echo htmlspecialchars($doc['approved_at'] ?? ''); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Column 4: Delete link -->
                            <div>
                                <form method="POST" style="margin:0;" 
                                      onsubmit="return confirm('Are you sure you want to delete this document?');">
                                    <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                    <input type="hidden" name="action" value="delete_document">
                                    <input type="hidden" name="partner_id" value="<?php echo $partner['id']; ?>">
                                    <a href="javascript:void(0);" 
                                       style="color:#c00; text-decoration:none;"
                                       onclick="this.closest('form').submit(); return false;">
                                       Delete
                                    </a>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No documents uploaded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Manage Tasks Section -->
        <div class="card">
            <h3>Manage Tasks</h3>
            <!-- Compact task-addition form in a single row -->
            <form class="task-form" method="POST" 
                  style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 20px;">
                <input type="hidden" name="action" value="add_task">
                <input type="hidden" name="partner_id" value="<?php echo $partner['id']; ?>">

                <input 
                    type="text" 
                    name="task" 
                    placeholder="Enter Task" 
                    required 
                    style="flex: 2; min-width: 200px; height: 40px; padding: 0 10px; font-size: 14px;"
                >
                <input 
                    type="date" 
                    name="due_by" 
                    required 
                    style="flex: 1; min-width: 140px; height: 40px; padding: 0 10px; font-size: 14px;"
                >
                <button 
                    type="submit" 
                    class="button" 
                    style="flex: 0 0 auto; height: 40px; padding: 0 20px; font-size: 14px;"
                >
                    Add Task
                </button>
            </form>

            <?php
            // Fetch pending tasks again for display
            $taskStmt = $pdo->prepare("
                SELECT * FROM partner_tasks
                WHERE partner_id = :partner_id
                  AND completed = 0
                ORDER BY due_by ASC
            ");
            $taskStmt->execute([':partner_id' => $partner['id']]);
            $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <?php if (count($tasks) > 0): ?>
                <table class="task-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Task</th>
                            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Due By</th>
                            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td style="text-align: left; padding: 8px; border-bottom: 1px solid #f0f0f0;">
                                    <?php echo htmlspecialchars($task['task'] ?? ''); ?>
                                </td>
                                <td style="text-align: left; padding: 8px; border-bottom: 1px solid #f0f0f0;">
                                    <?php echo htmlspecialchars($task['due_by'] ?? ''); ?>
                                </td>
                                <td style="text-align: left; padding: 8px; border-bottom: 1px solid #f0f0f0;">
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="complete_task">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="partner_id" value="<?php echo $partner['id']; ?>">
                                        <input type="checkbox" onchange="this.form.submit()">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No upcoming tasks.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        // Show chosen file name after selection
        const fileInput = document.getElementById('doc_file');
        const chosenFileName = document.getElementById('chosen-file-name');

        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                chosenFileName.textContent = fileInput.files[0].name;
            } else {
                chosenFileName.textContent = '';
            }
        });

        // Drag-and-Drop functionality
        const dropZone = document.getElementById('drop-zone');
        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.backgroundColor = '#eef';
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
    </script>
</body>
</html>
