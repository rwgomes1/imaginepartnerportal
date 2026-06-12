<?php
session_start();
require_once '../../includes/config.php';

// Ensure admin or superadmin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header('Location: ../../login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$user_id = $_SESSION['user_id']; // who is doing the changes

// Handle Category Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_category') {
    $cat_name = trim($_POST['category_name']);
    $cat_desc = trim($_POST['category_desc']);
    if (!empty($cat_name)) {
        $stmt = $pdo->prepare("INSERT INTO resource_categories (category_name, description, created_by, updated_by) 
                               VALUES (:name, :desc, :user, :user)");
        $stmt->execute([
            ':name' => $cat_name,
            ':desc' => $cat_desc,
            ':user' => $user_id
        ]);
    }
    header('Location: manage_resources.php');
    exit;
}

// Handle Category Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    $cat_id   = (int)$_POST['cat_id'];
    $cat_name = trim($_POST['category_name']);
    $cat_desc = trim($_POST['category_desc']);
    if ($cat_id > 0 && !empty($cat_name)) {
        $stmt = $pdo->prepare("UPDATE resource_categories 
                               SET category_name = :name, description = :desc, updated_by = :user, updated_at = NOW()
                               WHERE id = :cat_id");
        $stmt->execute([
            ':name'   => $cat_name,
            ':desc'   => $cat_desc,
            ':user'   => $user_id,
            ':cat_id' => $cat_id
        ]);
    }
    header('Location: manage_resources.php');
    exit;
}

// Handle Category Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    $cat_id = (int)$_POST['cat_id'];
    if ($cat_id > 0) {
        // Deleting category also deletes documents (due to ON DELETE CASCADE)
        $stmt = $pdo->prepare("DELETE FROM resource_categories WHERE id = :cat_id");
        $stmt->execute([':cat_id' => $cat_id]);
    }
    header('Location: manage_resources.php');
    exit;
}

// Handle Document Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_document') {
    $cat_id       = (int)$_POST['category_id'];
    $display_name = trim($_POST['display_name']);
    $doc_desc     = trim($_POST['doc_desc']);
    
    if ($cat_id > 0 && !empty($display_name) && isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        // 1) Physically store in /admin/resources/uploads/<cat_id>
        $catFolder = __DIR__ . '/uploads/' . $cat_id . '/';
        if (!is_dir($catFolder)) {
            mkdir($catFolder, 0777, true);
        }

        // 2) Sanitize filename
        $originalName = basename($_FILES['doc_file']['name']);
        $sanitized = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        
        // 3) Check for collisions; rename automatically
        $finalPath = $catFolder . $sanitized;
        $fileInfo = pathinfo($sanitized);
        $baseName = $fileInfo['filename'];
        $ext = isset($fileInfo['extension']) ? ('.' . $fileInfo['extension']) : '';
        
        $counter = 1;
        while (file_exists($finalPath)) {
            $newFileName = $baseName . '(' . $counter . ')' . $ext;
            $finalPath = $catFolder . $newFileName;
            $counter++;
        }

        // 4) Move file physically
        if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $finalPath)) {
            // 5) Store in DB with URL path = /admin/resources/uploads/<cat_id>/<filename>
            $relativePath = '/admin/resources/uploads/' . $cat_id . '/' . basename($finalPath);

            $stmt = $pdo->prepare("INSERT INTO resource_documents 
                (category_id, display_name, file_name, file_path, description, uploaded_by, updated_by) 
                VALUES (:cat_id, :disp, :fname, :fpath, :desc, :uploader, :uploader)");
            $stmt->execute([
                ':cat_id'   => $cat_id,
                ':disp'     => $display_name,
                ':fname'    => basename($finalPath),
                ':fpath'    => $relativePath,
                ':desc'     => $doc_desc,
                ':uploader' => $user_id
            ]);
        }
    }
    header('Location: manage_resources.php');
    exit;
}

// Handle Document Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_document') {
    $doc_id       = (int)$_POST['doc_id'];
    $display_name = trim($_POST['display_name']);
    $doc_desc     = trim($_POST['doc_desc']);
    if ($doc_id > 0 && !empty($display_name)) {
        $stmt = $pdo->prepare("UPDATE resource_documents 
                               SET display_name = :disp, description = :desc, updated_by = :usr, updated_at = NOW()
                               WHERE id = :doc_id");
        $stmt->execute([
            ':disp'   => $display_name,
            ':desc'   => $doc_desc,
            ':usr'    => $user_id,
            ':doc_id' => $doc_id
        ]);
    }
    header('Location: manage_resources.php');
    exit;
}

// Handle Document Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
    $doc_id = (int)$_POST['doc_id'];
    if ($doc_id > 0) {
        // fetch doc info to remove file
        $stmt = $pdo->prepare("SELECT category_id, file_name FROM resource_documents WHERE id = :doc_id");
        $stmt->execute([':doc_id' => $doc_id]);
        $docRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($docRow) {
            // remove from DB
            $delStmt = $pdo->prepare("DELETE FROM resource_documents WHERE id = :doc_id");
            $delStmt->execute([':doc_id' => $doc_id]);

            // remove file from server
            $catFolder = __DIR__ . '/uploads/' . $docRow['category_id'] . '/';
            $filePath  = $catFolder . $docRow['file_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
    header('Location: manage_resources.php');
    exit;
}

// Fetch categories & docs for display
$catStmt = $pdo->query("SELECT * FROM resource_categories ORDER BY category_name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$catDocs = [];
foreach ($categories as $cat) {
    $cID = $cat['id'];
    $docStmt = $pdo->prepare("SELECT * FROM resource_documents 
                              WHERE category_id = :cid 
                              ORDER BY display_name ASC");
    $docStmt->execute([':cid' => $cID]);
    $catDocs[$cID] = $docStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="container">
        <h2>Manage Resource Library</h2>
        
        <!-- Section 1: Create Category -->
        <div class="card">
            <h3>Create New Category</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_category">
                <label for="category_name">Category Name:</label>
                <input type="text" id="category_name" name="category_name" required>
                
                <label for="category_desc">Category Description (optional):</label>
                <input type="text" id="category_desc" name="category_desc">
                
                <button type="submit" class="button" style="margin-top: 10px;">Add Category</button>
            </form>
        </div>

        <!-- Section 2: Upload Document -->
        <div class="card">
            <h3>Upload Document</h3>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload_document">
                
                <label for="category_id">Select Category:</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Choose Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="display_name">Display Name (Link Text):</label>
                <input type="text" id="display_name" name="display_name" required>
                
                <label for="doc_desc">Document Description (optional):</label>
                <input type="text" id="doc_desc" name="doc_desc">
                
                <!-- Drag and Drop or Browse -->
                <div id="drop-zone" style="border:2px dashed #bbb; border-radius:5px; padding:20px; text-align:center; color:#bbb; margin-top:10px; cursor:pointer;">
                    Drag &amp; Drop File Here or Click to Browse
                </div>
                <input type="file" id="doc_file" name="doc_file" style="display: none;" required>
                
                <button type="submit" class="button" style="margin-top: 10px;">Upload Document</button>
            </form>
        </div>

        <!-- Section 3: Existing Categories & Documents (Admin View) -->
        <div class="card">
            <h3>Current Resource Library</h3>
            <?php if (count($categories) === 0): ?>
                <p>No categories yet.</p>
            <?php else: ?>
                <div class="resource-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <?php foreach ($categories as $cat): ?>
                        <?php 
                        $cID = $cat['id'];
                        $docs = $catDocs[$cID] ?? [];
                        ?>
                        <div class="category-card" style="background-color:#f9f9f9; border:1px solid #ccc; border-radius:8px; padding:15px; text-align:center;">
                            <h4 class="cat-name" style="margin:0; font-size:1.1rem; font-weight:bold;">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </h4>
                            <?php if (!empty($cat['description'])): ?>
                                <p class="cat-desc" style="margin:5px 0 10px; font-size:0.9rem; color:#666;">
                                    <?php echo htmlspecialchars($cat['description']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="cat-actions" style="margin-bottom:10px;">
                                <!-- Edit Category link -->
                                <a href="javascript:void(0);" 
                                   onclick="showEditCategory(<?php echo $cat['id']; ?>,
                                         '<?php echo addslashes($cat['category_name']); ?>',
                                         '<?php echo addslashes($cat['description']); ?>')"
                                   style="color:#007bff; text-decoration:none; margin:0 10px; font-size:0.9rem;">
                                   Edit
                                </a>
                                
                                <!-- Delete Category form styled as link -->
                                <form method="POST" style="display:inline;" 
                                      onsubmit="return confirm('Delete this category and all documents?');">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                    <a href="javascript:void(0);" 
                                       onclick="this.closest('form').submit(); return false;"
                                       style="color:#007bff; text-decoration:none; margin:0 10px; font-size:0.9rem;">
                                       Delete
                                    </a>
                                </form>
                            </div>
                            
                            <hr class="cat-divider" style="margin:10px 0;">
                            
                            <!-- Documents List -->
                            <?php if (count($docs) === 0): ?>
                                <p class="no-docs" style="font-style:italic; color:#888;">
                                    No documents in this category.
                                </p>
                            <?php else: ?>
                                <?php foreach ($docs as $doc): ?>
                                    <div class="doc-row-4col" style="display:grid; grid-template-columns:1fr 1fr auto auto; align-items:center; margin-bottom:8px; gap:10px;">
                                        <!-- Column 1: doc display name (e.g. "Daniel") -->
                                        <div class="doc-desc-col" style="text-align:left; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;">
                                            <?php echo htmlspecialchars($doc['display_name']); ?>
                                        </div>
                                        <!-- Column 2: file name as link -->
                                        <div class="doc-file-col" style="text-align:left; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;">
                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>"
                                               target="_blank"
                                               title="<?php echo htmlspecialchars($doc['file_name']); ?>"
                                               style="color:#007bff; text-decoration:none;">
                                               <?php echo htmlspecialchars($doc['file_name']); ?>
                                            </a>
                                        </div>
                                        <!-- Column 3: Edit link -->
                                        <div class="doc-edit-col" style="text-align:center;">
                                            <a href="javascript:void(0);"
                                               onclick="showEditDoc(<?php echo $doc['id']; ?>,
                                                   '<?php echo addslashes($doc['display_name']); ?>',
                                                   '<?php echo addslashes($doc['description']); ?>')"
                                               style="color:#007bff; text-decoration:none; font-size:0.9rem;">
                                               Edit
                                            </a>
                                        </div>
                                        <!-- Column 4: Delete link -->
                                        <div class="doc-delete-col" style="text-align:center;">
                                            <form method="POST" style="display:inline;"
                                                  onsubmit="return confirm('Delete this document?');">
                                                <input type="hidden" name="action" value="delete_document">
                                                <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                                <a href="javascript:void(0);"
                                                   onclick="this.closest('form').submit(); return false;"
                                                   style="color:#007bff; text-decoration:none; font-size:0.9rem;">
                                                   Delete
                                                </a>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<script>
// Drag-and-drop
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('doc_file');

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
    }
});

// Category edit
function showEditCategory(id, name, desc) {
    document.getElementById('edit_cat_id').value = id;
    document.getElementById('edit_cat_name').value = name;
    document.getElementById('edit_cat_desc').value = desc;
    document.getElementById('editCategoryForm').style.display = 'block';
    document.getElementById('editDocForm').style.display = 'none';
}
// Doc edit
function showEditDoc(id, disp, desc) {
    document.getElementById('edit_doc_id').value = id;
    document.getElementById('edit_display_name').value = disp;
    document.getElementById('edit_doc_desc').value = desc;
    document.getElementById('editDocForm').style.display = 'block';
    document.getElementById('editCategoryForm').style.display = 'none';
}
</script>

<!-- Hidden forms for editing category/doc -->
<form method="POST" id="editCategoryForm" style="display:none; position:absolute; background:#fff; border:1px solid #ccc; padding:10px; border-radius:4px;">
    <input type="hidden" name="action" value="edit_category">
    <input type="hidden" name="cat_id" id="edit_cat_id">
    <label for="edit_cat_name">Category Name:</label>
    <input type="text" id="edit_cat_name" name="category_name">
    <label for="edit_cat_desc">Description:</label>
    <input type="text" id="edit_cat_desc" name="category_desc">
    <button type="submit">Save Category</button>
</form>

<form method="POST" id="editDocForm" style="display:none; position:absolute; background:#fff; border:1px solid #ccc; padding:10px; border-radius:4px;">
    <input type="hidden" name="action" value="edit_document">
    <input type="hidden" name="doc_id" id="edit_doc_id">
    <label for="edit_display_name">Display Name:</label>
    <input type="text" id="edit_display_name" name="display_name">
    <label for="edit_doc_desc">Description:</label>
    <input type="text" id="edit_doc_desc" name="doc_desc">
    <button type="submit">Save Document</button>
</form>
