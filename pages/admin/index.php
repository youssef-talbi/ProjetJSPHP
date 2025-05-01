<?php
// Admin Dashboard
global $admin_user_name;
require_once __DIR__ . "/../../bootstrap.php";
require_once __DIR__ . "/admin_check.php"; // Ensure user is admin

$db = getDbConnection();
$stats = [
    "total_users" => 0,
    "total_projects" => 0,
    "total_contracts" => 0,
    "total_transactions" => 0,
    "pending_withdrawals" => 0, // Example stat
    "open_disputes" => 0 // Example stat
];

if ($db) {
    try {
        // Fetch basic stats (use COUNT(*) for efficiency)
        $stats["total_users"] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats["total_projects"] = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
        $stats["total_contracts"] = $db->query("SELECT COUNT(*) FROM contracts")->fetchColumn();
        $stats["total_transactions"] = $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();

        // Example: Fetch pending withdrawals (assuming a status in transactions table)
        $stats["pending_withdrawals"] = $db->query("SELECT COUNT(*) FROM transactions WHERE transaction_type = "withdrawal" AND status = "pending"")->fetchColumn();

        // Example: Fetch open disputes (assuming a status in contracts table)
        $stats["open_disputes"] = $db->query("SELECT COUNT(*) FROM contracts WHERE status = "disputed"")->fetchColumn();

    } catch (PDOException $e) {
        error_log("Admin dashboard stats error: " . $e->getMessage());
        // Display an error message or default stats
    }
}

// Include Admin Header (might need a specific admin header/layout)
$page_title = "Admin Dashboard";
require_once __DIR__ . "/../../includes/header.php"; // Using main header for now

?>

<div class="container">
    <h1 class="page-title">Admin Dashboard</h1>
    <p>Welcome, <?php echo htmlspecialchars($admin_user_name); ?>!</p>

    <?php display_message(); // Display any session messages ?>

    <!-- Stats Overview -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <h4>Total Users</h4>
            <p><?php echo number_format($stats["total_users"]); ?></p>
        </div>
        <div class="stat-card">
            <h4>Total Projects</h4>
            <p><?php echo number_format($stats["total_projects"]); ?></p>
        </div>
        <div class="stat-card">
            <h4>Total Contracts</h4>
            <p><?php echo number_format($stats["total_contracts"]); ?></p>
        </div>
        <div class="stat-card">
            <h4>Total Transactions</h4>
            <p><?php echo number_format($stats["total_transactions"]); ?></p>
        </div>
        <div class="stat-card stat-card-warning">
            <h4>Pending Withdrawals</h4>
            <p><?php echo number_format($stats["pending_withdrawals"]); ?></p>
            <?php if ($stats["pending_withdrawals"] > 0): ?>
                <a href="/improved/pages/admin/manage_payments.php?filter=pending_withdrawals">View</a>
            <?php endif; ?>
        </div>
        <div class="stat-card stat-card-danger">
            <h4>Open Disputes</h4>
            <p><?php echo number_format($stats["open_disputes"]); ?></p>
            <?php if ($stats["open_disputes"] > 0): ?>
                <a href="/improved/pages/admin/manage_disputes.php">View</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions / Navigation -->
    <div class="card">
        <div class="card-content">
            <h3>Management Sections</h3>
            <ul class="admin-nav-list">
                <li><a href="/improved/pages/admin/manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="/improved/pages/admin/manage_projects.php"><i class="fas fa-briefcase"></i> Manage Projects</a></li>
                <li><a href="/improved/pages/admin/manage_contracts.php"><i class="fas fa-file-contract"></i> Manage Contracts</a></li>
                <li><a href="/improved/pages/admin/manage_payments.php"><i class="fas fa-dollar-sign"></i> Manage Payments & Withdrawals</a></li>
                <li><a href="/improved/pages/admin/manage_disputes.php"><i class="fas fa-gavel"></i> Manage Disputes</a></li>
                <li><a href="/improved/pages/admin/manage_categories.php"><i class="fas fa-tags"></i> Manage Categories & Skills</a></li>
                <li><a href="/improved/pages/admin/site_settings.php"><i class="fas fa-cog"></i> Site Settings</a></li>
                <li><a href="/improved/pages/admin/view_logs.php"><i class="fas fa-clipboard-list"></i> View System Logs</a></li>
            </ul>
        </div>
    </div>

</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
    }
    .stat-card {
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
    }
    .stat-card h4 {
        margin-top: 0;
        margin-bottom: 10px;
        color: #555;
        font-size: 1rem;
    }
    .stat-card p {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 5px;
        color: #333;
    }
    .stat-card a {
        font-size: 0.9rem;
        color: var(--primary-color);
    }
    .stat-card-warning p { color: var(--warning-color); }
    .stat-card-danger p { color: var(--danger-color); }

    .admin-nav-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    .admin-nav-list li a {
        display: block;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 5px;
        text-decoration: none;
        color: #333;
        transition: background-color 0.2s ease;
    }
    .admin-nav-list li a:hover {
        background-color: #e9ecef;
    }
    .admin-nav-list li a i {
        margin-right: 10px;
        color: var(--primary-color);
    }
</style>

<?php
// Include Footer
require_once __DIR__ . "/../../includes/footer.php";
?>

