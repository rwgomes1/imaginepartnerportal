<?php
session_start();
require_once '../../includes/config.php';

// Check if partner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'partner') {
    header('Location: ../../login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Handle search
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch categories
$catStmt = $pdo->query("SELECT id, category_name, description FROM resource_categories ORDER BY category_name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Build a structure to store docs by category
$catDocs = [];
foreach ($categories as $cat) {
    $catDocs[$cat['id']] = [];
}

// If searching, find documents that match
if ($searchTerm !== '') {
    $searchLike = "%$searchTerm%";
    
    // Find docs that match category_name, display_name, or doc description
    $docSearchStmt = $pdo->prepare("
        SELECT d.*, c.category_name, c.description
        FROM resource_documents d
        JOIN resource_categories c ON c.id = d.category_id
        WHERE (c.category_name LIKE :term
               OR d.display_name LIKE :term
               OR d.description LIKE :term)
        ORDER BY c.category_name ASC, d.display_name ASC
    ");
    $docSearchStmt->execute([':term' => $searchLike]);
    $matchingDocs = $docSearchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Rebuild catDocs with only matching docs
    foreach ($matchingDocs as $md) {
        $cID = $md['category_id'];
        if (!isset($catDocs[$cID])) {
            $catDocs[$cID] = [];
        }
        $catDocs[$cID][] = $md;
    }
} else {
    // No search => fetch all docs
    foreach ($categories as $cat) {
        $cID = $cat['id'];
        $docStmt = $pdo->prepare("
            SELECT *
            FROM resource_documents
            WHERE category_id = :cid
            ORDER BY display_name ASC
        ");
        $docStmt->execute([':cid' => $cID]);
        $catDocs[$cID] = $docStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Include your unified header & sidebar
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
    <div class="container">
        <style>
            h2 {
                margin-top: 20px;
                text-align: center;
            }
            .search-bar {
                margin: 20px 0;
                text-align: center;
            }
            .search-bar input[type="text"] {
                width: 300px;
                padding: 8px;
                font-size: 14px;
            }
            .search-bar button {
                padding: 8px 15px;
                font-size: 14px;
                margin-left: 5px;
            }
            /* The main grid for categories: 2 columns */
            .resource-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }
            /* Each category card */
            .resource-card {
                background-color: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 15px;
            }
            /* Centered category name & desc */
            .resource-card .cat-name {
                text-align: center;
                font-weight: bold;
                font-size: 1.1rem;
                margin: 0;
            }
            .resource-card .cat-desc {
                text-align: center;
                font-size: 0.9rem;
                color: #666;
                margin: 5px 0 10px 0;
            }
            .resource-card hr {
                margin: 10px 0;
            }
            /* The doc headings (File, Date Uploaded) */
            .doc-header {
                display: grid;
                grid-template-columns: 1fr auto;
                font-weight: bold;
                margin-bottom: 8px;
            }
            /* Each doc row: 2 columns => left=File link, right=Date */
            .doc-row-2col {
                display: grid;
                grid-template-columns: 1fr auto;
                align-items: center;
                margin-bottom: 5px;
                gap: 10px;
            }
            /* The file link: handle long text with ellipsis */
            .doc-file-col {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .doc-file-col a {
                color: #007bff;
                text-decoration: none;
            }
            .doc-file-col a:hover {
                text-decoration: underline;
            }
            .doc-date-col {
                color: #666;
                font-size: 0.9rem;
                text-align: right;
                min-width: 70px;
            }
            /* If no results for search, style that message */
            .no-results {
                text-align: center;
                font-style: italic;
                color: #888;
            }
        </style>

        <h2>Resource Library</h2>

        <!-- Search bar with Clear button -->
        <div class="search-bar">
            <form method="GET">
                <input type="text" name="search" placeholder="Search resources..." 
                       value="<?php echo htmlspecialchars($searchTerm); ?>">
                <button type="submit">Search</button>
                <button type="button" onclick="window.location.href='index.php'">Clear</button>
            </form>
        </div>

        <?php
        // Calculate total docs found (for the "no results" message if searching)
        $totalDocsFound = 0;
        foreach ($catDocs as $docs) {
            $totalDocsFound += count($docs);
        }
        if ($searchTerm !== '' && $totalDocsFound === 0) {
            echo "<p class='no-results'>No matching documents found for '" 
                 . htmlspecialchars($searchTerm) . "'.</p>";
        }
        ?>

        <div class="resource-grid">
            <?php foreach ($categories as $cat): ?>
                <?php
                $cID = $cat['id'];
                $docs = $catDocs[$cID];
                if (count($docs) === 0) {
                    // Hide categories with no docs
                    continue;
                }
                ?>
                <div class="resource-card">
                    <!-- Category Name & Desc, centered -->
                    <h3 class="cat-name"><?php echo htmlspecialchars($cat['category_name']); ?></h3>
                    <?php if (!empty($cat['description'])): ?>
                        <p class="cat-desc"><?php echo htmlspecialchars($cat['description']); ?></p>
                    <?php endif; ?>
                    
                    <hr>
                    <!-- Headings for File & Date -->
                    <div class="doc-header">
                        <span>File</span>
                        <span>Date Uploaded</span>
                    </div>

                    <!-- Each doc in 2 columns -->
                    <?php foreach ($docs as $doc): ?>
                        <?php
                        // Use updated_at if available; otherwise uploaded_at
                        $dateUploaded = $doc['updated_at'] ?: $doc['uploaded_at'];
                        $dateUploaded = $dateUploaded ? date("Y-m-d", strtotime($dateUploaded)) : '';
                        
                        // Adjust the file path: force link to /admin/resources/uploads/<cat_id>/<filename>
                        $filePath = $doc['file_path'];
                        if (strpos($filePath, '/admin/resources/uploads/') !== 0) {
                            // If filePath doesn't already start with '/admin/resources/uploads/', 
                            // you might prepend it or handle differently if your file structure is unique
                            $filePath = '/admin/resources/' . ltrim($filePath, '/');
                        }
                        ?>
                        <div class="doc-row-2col">
                            <div class="doc-file-col">
                                <a href="<?php echo htmlspecialchars($filePath); ?>" 
                                   target="_blank" 
                                   title="<?php echo htmlspecialchars($doc['display_name']); ?>">
                                    <?php echo htmlspecialchars($doc['display_name']); ?>
                                </a>
                            </div>
                            <div class="doc-date-col">
                                <?php echo $dateUploaded; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
