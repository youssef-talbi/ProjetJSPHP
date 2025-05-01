<?php
// Admin Manage Projects Page
require_once __DIR__ . "/../../bootstrap.php";

// Check if admin is logged in
if (!is_admin_logged_in()) {
    redirect("/pages/admin/admin_auth.php?error=login_required");
    exit;
}

// Database connection
$db = getDbConnection();
if (!$db) {
    $error_message = "Database connection error.";
    // Display error or handle appropriately
}

// --- Filtering & Sorting Logic ---
$filter_status = isset($_GET["status"]) ? filter_var($_GET["status"], FILTER_SANITIZE_STRING) : null;
$filter_category = isset($_GET["category"]) ? filter_input(INPUT_GET, "category", FILTER_VALIDATE_INT) : null;
$filter_client = isset($_GET["client"]) ? filter_input(INPUT_GET, "client", FILTER_VALIDATE_INT) : null;
$filter_search = isset($_GET["search"]) ? filter_var($_GET["search"], FILTER_SANITIZE_STRING) : null;
$sort_order = isset($_GET["sort"]) ? filter_var($_GET["sort"], FILTER_SANITIZE_STRING) : "created_desc";

// --- Pagination Logic ---
$page = isset($_GET["page"]) ? filter_input(INPUT_GET, "page", FILTER_VALIDATE_INT, ["options" => ["default" => 1, "min_range" => 1]]) : 1;
$limit = 15; // Projects per page
$offset = ($page - 1) * $limit;

// --- Build SQL Query ---
$sql = "SELECT p.project_id, p.title, p.status, p.creation_date, p.deadline, 
               c.category_name, 
               u.user_id as client_id, u.first_name as client_first_name, u.last_name as client_last_name
        FROM projects p
        JOIN categories c ON p.category_id = c.category_id
        JOIN users u ON p.client_id = u.user_id
        WHERE 1=1"; // Start with a true condition
$params = [];

// Add filters
if ($filter_status && in_array($filter_status, ["open", "in_progress", "completed", "closed", "cancelled"])) {
    $sql .= " AND p.status = :status";
    $params[":status"] = $filter_status;
}
if ($filter_category) {
    $sql .= " AND p.category_id = :category_id";
    $params[":category_id"] = $filter_category;
}
if ($filter_client) {
    $sql .= " AND p.client_id = :client_id";
    $params[":client_id"] = $filter_client;
}
if ($filter_search) {
    $sql .= " AND (p.title LIKE :search OR p.description LIKE :search)";
    $params[":search"] = "%" . $filter_search . "%";
}

// --- Count Total Projects for Pagination ---
$count_sql = "SELECT COUNT(*) 
              FROM projects p
              JOIN categories c ON p.category_id = c.category_id
              JOIN users u ON p.client_id = u.user_id
              WHERE 1=1";
$count_params = [];
if ($filter_status && in_array($filter_status, ["open", "in_progress", "completed", "closed", "cancelled"])) {
    $count_sql .= " AND p.status = :status";
    $count_params[":status"] = $filter_status;
}
if ($filter_category) {
    $count_sql .= " AND p.category_id = :category_id";
    $count_params[":category_id"] = $filter_category;
}
if ($filter_client) {
    $count_sql .= " AND p.client_id = :client_id";
    $count_params[":client_id"] = $filter_client;
}
if ($filter_search) {
    $count_sql .= " AND (p.title LIKE :search OR p.description LIKE :search)";
    $count_params[":search"] = "%" . $filter_search . "%";
}

$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($count_params);
$total_projects = $count_stmt->fetchColumn();
$total_pages = ceil($total_projects / $limit);

// --- Add Sorting ---
switch ($sort_order) {
    case "title_asc":
        $sql .= " ORDER BY p.title ASC";
        break;
    case "title_desc":
        $sql .= " ORDER BY p.title DESC";
        break;
    case "client_asc":
        $sql .= " ORDER BY u.first_name ASC, u.last_name ASC";
        break;
    case "status_asc":
        $sql .= " ORDER BY p.status ASC, p.creation_date DESC";
        break;
    case "created_asc":
        $sql .= " ORDER BY p.creation_date ASC";
        break;
    case "created_desc":
    default:
        $sql .= " ORDER BY p.creation_date DESC";
        break;
}

// --- Add Pagination Limit ---
$sql .= " LIMIT :limit OFFSET :offset";
$params[":limit"] = $limit;
$params[":offset"] = $offset;

// --- Fetch Projects ---
$stmt = $db->prepare($sql);
// Bind parameters correctly
foreach ($params as $key => &$val) {
    if ($key === ":limit" || $key === ":offset" || $key === ":category_id" || $key === ":client_id") {
        $stmt->bindParam($key, $val, PDO::PARAM_INT);
    } else {
        $stmt->bindParam($key, $val);
    }
}
unset($val);

$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for filter dropdown
$category_stmt = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
$filter_categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

// Include Admin Header
$page_title = "Manage Projects";
require_once __DIR__ . "/../../includes/header.php"; // Using main header for now

// Generate CSRF token for actions
$action_token = generate_form_token("admin_project_action_token");

?>

<div class="container mt-4">
    <div class="admin-header mb-4">
        <h1>Manage Projects</h1>
        <a href="/pages/admin/dashboard.php" class="btn btn-sm btn-secondary">Back to Dashboard</a>
    </div>

    <?php display_message(); ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Filtering Form -->
    <div class="card mb-4">
        <div class="card-content">
            <form action="" method="get" class="flex flex-wrap gap-20 items-end">
                <div class="form-group" style="flex-grow: 1;">
                    <label for="search">Search Projects</label>
                    <input type="text" id="search" name="search" placeholder="Title, Description..." value="<?php echo htmlspecialchars($filter_search ?? "); ?>">
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="" <?php echo !$filter_category ? "selected" : ""; ?>>All Categories</option>
                        <?php foreach ($filter_categories as $cat): ?>
                            <option value="<?php echo $cat["category_id"]; ?>" <?php echo $filter_category == $cat["category_id"] ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($cat["category_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="" <?php echo !$filter_status ? "selected" : ""; ?>>All Statuses</option>
                        <option value="open" <?php echo $filter_status === "open" ? "selected" : ""; ?>>Open</option>
                        <option value="in_progress" <?php echo $filter_status === "in_progress" ? "selected" : ""; ?>>In Progress</option>
                        <option value="completed" <?php echo $filter_status === "completed" ? "selected" : ""; ?>>Completed</option>
                        <option value="closed" <?php echo $filter_status === "closed" ? "selected" : ""; ?>>Closed</option>
                        <option value="cancelled" <?php echo $filter_status === "cancelled" ? "selected" : ""; ?>>Cancelled</option>
                    </select>
                </div>
                <!-- Add Client Filter if needed -->
                <div class="form-group">
                    <button type="submit" class="btn">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Project Table -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Client</th>
                    <th>Category</th>
                    <th>Created</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="8" class="text-center">No projects found matching your criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?php echo $project["project_id"]; ?></td>
                            <td><?php echo htmlspecialchars($project["title"]); ?></td>
                            <td><a href="/pages/profile/view.php?id=<?php echo $project["client_id"]; ?>" target="_blank"><?php echo htmlspecialchars($project["client_first_name"] . " " . $project["client_last_name"]); ?></a></td>
                            <td><?php echo htmlspecialchars($project["category_name"]); ?></td>
                            <td><?php echo date("Y-m-d", strtotime($project["creation_date"])); ?></td>
                            <td><?php echo $project["deadline"] ? date("Y-m-d", strtotime($project["deadline"])) : "N/A"; ?></td>
                            <td>
                                <span class="badge badge-<?php echo get_project_status_badge_class($project["status"]); ?>">
                                    <?php echo ucfirst($project["status"]); ?>
                                </span>
                            </td>
                            <td>
                                <form action="/pages/admin/project_actions.php" method="post" style="display: inline-block; margin-right: 5px;">
                                    <input type="hidden" name="token" value="<?php echo $action_token; ?>">
                                    <input type="hidden" name="project_id" value="<?php echo $project["project_id"]; ?>">
                                    <?php if ($project["status"] === "open" || $project["status"] === "in_progress"): ?>
                                        <input type="hidden" name="action" value="close">
                                        <button type="submit" class="btn btn-warning btn-sm" title="Close Project" onclick="return confirm("Are you sure you want to close this project?");">Close</button>
                                    <?php endif; ?>
                                    <!-- Add Delete Action -->
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete Project" onclick="return confirm("ARE YOU SURE you want to PERMANENTLY DELETE this project and all related data (proposals, etc.)? This cannot be undone.");">Delete</button>
                                </form>
                                <a href="/pages/projects/view.php?id=<?php echo $project["project_id"]; ?>" class="btn btn-info btn-sm" title="View Project" target="_blank">View</a>
                                <!-- Add Edit button if needed -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-center mt-4">
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ["page" => $page - 1])); ?>" class="btn btn-sm">&laquo; Previous</a>
            <?php endif; ?>

            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            if ($start_page > 1) echo "<span class=\"pagination-ellipsis\">...</span>";
            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ["page" => $i])); ?>" class="btn btn-sm <?php echo $i == $page ? "active" : ""; ?>"><?php echo $i; ?></a>
            <?php endfor; 
            if ($end_page < $total_pages) echo "<span class=\"pagination-ellipsis\">...</span>";
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ["page" => $page + 1])); ?>" class="btn btn-sm">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
// Include Admin Footer
require_once __DIR__ . "/../../includes/footer.php"; // Using main footer for now

// Helper function for project status badge class
function get_project_status_badge_class($status) {
    switch ($status) {
        case "open": return "success";
        case "in_progress": return "primary";
        case "completed": return "info";
        case "closed": return "secondary";
        case "cancelled": return "danger";
        default: return "secondary";
    }
}
?>

