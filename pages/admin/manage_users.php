<?php
// Admin - Manage Users
require_once __DIR__ . "/../../bootstrap.php";
require_once __DIR__ . "/admin_check.php"; // Ensure user is admin

$db = getDbConnection();
$users = [];
$error_message = null;

// Pagination parameters
$page = filter_input(INPUT_GET, "page", FILTER_VALIDATE_INT, ["options" => ["default" => 1, "min_range" => 1]]);
$limit = 20; // Users per page
$offset = ($page - 1) * $limit;

// Search and Filter parameters
$search_term = filter_input(INPUT_GET, "search", FILTER_SANITIZE_STRING);
$filter_type = filter_input(INPUT_GET, "type", FILTER_SANITIZE_STRING);
$filter_status = filter_input(INPUT_GET, "status", FILTER_SANITIZE_STRING);

$where_clauses = [];
$params = [];

if (!empty($search_term)) {
    $where_clauses[] = "(email LIKE :search OR first_name LIKE :search OR last_name LIKE :search)";
    $params[":search"] = "%" . $search_term . "%";
}
if (!empty($filter_type)) {
    $where_clauses[] = "user_type = :type";
    $params[":type"] = $filter_type;
}
if (!empty($filter_status)) {
    $where_clauses[] = "account_status = :status";
    $params[":status"] = $filter_status;
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

$total_users = 0;

if ($db) {
    try {
        // Get total count for pagination
        $total_stmt = $db->prepare("SELECT COUNT(*) FROM users" . $where_sql);
        $total_stmt->execute($params);
        $total_users = $total_stmt->fetchColumn();

        // Fetch users for the current page
        $stmt = $db->prepare("SELECT user_id, email, user_type, first_name, last_name, registration_date, account_status FROM users" . $where_sql . " ORDER BY registration_date DESC LIMIT :limit OFFSET :offset");
        // Bind WHERE params
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        // Bind LIMIT and OFFSET
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);

        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Admin manage users error: " . $e->getMessage());
        $error_message = "Error fetching users: " . $e->getMessage();
    }
} else {
    $error_message = "Database connection error.";
}

$total_pages = ceil($total_users / $limit);

// Include Admin Header
$page_title = "Manage Users";
require_once __DIR__ . "/../../includes/header.php"; // Using main header for now

?>

<div class="container">
    <h1 class="page-title">Manage Users</h1>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/improved/pages/admin/">Admin Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Manage Users</li>
        </ol>
    </nav>

    <?php display_message(); ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-content">
            <form method="get" action="" class="form-inline">
                <div class="form-group mr-2">
                    <label for="search" class="sr-only">Search</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search_term ?? "); ?>">
                </div>
                <div class="form-group mr-2">
                    <label for="type" class="sr-only">User Type</label>
                    <select name="type" id="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="client" <?php echo ($filter_type === "client" ? "selected" : ""); ?>>Client</option>
                        <option value="freelancer" <?php echo ($filter_type === "freelancer" ? "selected" : ""); ?>>Freelancer</option>
                    <option value="admin" <?php echo ($filter_type === "admin" ? "selected" : ""); ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group mr-2">
                    <label for="status" class="sr-only">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo ($filter_status === "active" ? "selected" : ""); ?>>Active</option>
                        <option value="inactive" <?php echo ($filter_status === "inactive" ? "selected" : ""); ?>>Inactive</option>
                        <option value="suspended" <?php echo ($filter_status === "suspended" ? "selected" : ""); ?>>Suspended</option>
                    </select>
                </div>
                <button type="submit" class="btn">Filter</button>
                <a href="/improved/pages/admin/manage_users.php" class="btn btn-secondary ml-2">Clear</a>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-content">
            <div class="table-responsive">
                <table class="table simple-table table-hover">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No users found matching your criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user["user_id"]; ?></td>
                                <td><?php echo htmlspecialchars($user["first_name"] . " " . $user["last_name"]); ?></td>
                                <td><?php echo htmlspecialchars($user["email"]); ?></td>
                                <td><span class="badge badge-secondary"><?php echo ucfirst($user["user_type"]); ?></span></td>
                                <td><span class="badge <?php echo get_status_badge_class($user["account_status"]); ?>"><?php echo ucfirst($user["account_status"]); ?></span></td>
                                <td><?php echo format_date($user["registration_date"], "Y-m-d"); ?></td>
                                <td>
                                    <a href="/improved/pages/profile/view.php?id=<?php echo $user["user_id"]; ?>" class="btn btn-xs btn-info" title="View Profile"><i class="fas fa-eye"></i></a>
                                    <a href="/improved/pages/admin/edit_user.php?id=<?php echo $user["user_id"]; ?>" class="btn btn-xs btn-primary" title="Edit User"><i class="fas fa-edit"></i></a>
                                    <?php if ($user["account_status"] === "active"): ?>
                                        <a href="/improved/pages/admin/user_actions.php?action=suspend&id=<?php echo $user["user_id"]; ?>" class="btn btn-xs btn-warning" title="Suspend User" onclick="return confirm("Are you sure you want to suspend this user?");"><i class="fas fa-ban"></i></a>
                                    <?php elseif ($user["account_status"] === "suspended" || $user["account_status"] === "inactive"): ?>
                                        <a href="/improved/pages/admin/user_actions.php?action=activate&id=<?php echo $user["user_id"]; ?>" class="btn btn-xs btn-success" title="Activate User" onclick="return confirm("Are you sure you want to activate this user?");"><i class="fas fa-check-circle"></i></a>
                                    <?php endif; ?>
                                    <?php // Add delete button cautiously - maybe only for inactive/test accounts ?>
                                    <a href="/improved/pages/admin/user_actions.php?action=delete&id=<?php echo $user["user_id"]; ?>" class="btn btn-xs btn-danger" title="Delete User" onclick="return confirm("ARE YOU SURE you want to PERMANENTLY DELETE this user? This action cannot be undone.");"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term ?? "); ?>&type=<?php echo urlencode($filter_type ?? "); ?>&status=<?php echo urlencode($filter_status ?? "); ?>">Previous</a></li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? "active" : ""; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term ?? "); ?>&type=<?php echo urlencode($filter_type ?? "); ?>&status=<?php echo urlencode($filter_status ?? "); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term ?? "); ?>&type=<?php echo urlencode($filter_type ?? "); ?>&status=<?php echo urlencode($filter_status ?? "); ?>">Next</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include Footer
require_once __DIR__ . "/../../includes/footer.php";
?>

