<?php
// Admin Dashboard
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

// Fetch dashboard statistics
$stats = [
    "total_users" => 0,
    "active_freelancers" => 0,
    "active_clients" => 0,
    "open_projects" => 0,
    "total_proposals" => 0,
    "pending_reviews" => 0 // Example stat
];

if ($db) {
    try {
        $stats["total_users"] = $db->query("SELECT COUNT(*) FROM users WHERE user_type IN ("client", "freelancer")")->fetchColumn();
        $stats["active_freelancers"] = $db->query("SELECT COUNT(*) FROM users WHERE user_type = "freelancer" AND account_status = "active"")->fetchColumn();
        $stats["active_clients"] = $db->query("SELECT COUNT(*) FROM users WHERE user_type = "client" AND account_status = "active"")->fetchColumn();
        $stats["open_projects"] = $db->query("SELECT COUNT(*) FROM projects WHERE status = "open"")->fetchColumn();
        $stats["total_proposals"] = $db->query("SELECT COUNT(*) FROM proposals")->fetchColumn();
        // Add more queries for other stats as needed
    } catch (PDOException $e) {
        error_log("Admin dashboard stats error: " . $e->getMessage());
        $error_message = "Error fetching dashboard statistics.";
    }
}

// Include Admin Header (Consider creating a specific admin header)
$page_title = "Admin Dashboard";
require_once __DIR__ . "/../../includes/header.php"; // Using main header for now

?>

<div class="container mt-4">
    <div class="admin-header mb-4">
        <h1>Admin Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION["admin_first_name"] ?? "Admin"); ?>!</p>
    </div>

    <?php display_message(); ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Dashboard Stats -->
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="card text-center">
            <div class="card-content">
                <h2><?php echo $stats["total_users"]; ?></h2>
                <p>Total Users</p>
            </div>
        </div>
        <div class="card text-center">
            <div class="card-content">
                <h2><?php echo $stats["active_freelancers"]; ?></h2>
                <p>Active Freelancers</p>
            </div>
        </div>
        <div class="card text-center">
            <div class="card-content">
                <h2><?php echo $stats["active_clients"]; ?></h2>
                <p>Active Clients</p>
            </div>
        </div>
        <div class="card text-center">
            <div class="card-content">
                <h2><?php echo $stats["open_projects"]; ?></h2>
                <p>Open Projects</p>
            </div>
        </div>
        <div class="card text-center">
            <div class="card-content">
                <h2><?php echo $stats["total_proposals"]; ?></h2>
                <p>Total Proposals</p>
            </div>
        </div>
        <!-- Add more stat cards as needed -->
    </div>

    <!-- Quick Actions / Links -->
    <div class="card">
        <div class="card-content">
            <h3>Management Sections</h3>
            <ul class="list-unstyled">
                <li class="mb-2"><a href="/pages/admin/manage_users.php" class="btn btn-secondary">Manage Users</a></li>
                <li class="mb-2"><a href="/pages/admin/manage_projects.php" class="btn btn-secondary">Manage Projects</a></li>
                <!-- Add links to other admin sections like settings, reports, etc. -->
            </ul>
        </div>
    </div>

    <!-- Consider adding recent activity logs or pending items here -->

</div>

<?php
// Include Admin Footer (Consider creating a specific admin footer)
require_once __DIR__ . "/../../includes/footer.php"; // Using main footer for now
?>

