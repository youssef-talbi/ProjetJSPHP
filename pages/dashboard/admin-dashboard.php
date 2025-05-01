<?php
require_once __DIR__ . '/../../includes/header.php';

if (get_user_type() !== 'admin') {
    redirect('/pages/auth/login.php', 'Access denied.', 'danger');
}
?>

<section class="container mt-5">
    <h2>Admin Dashboard</h2>
    <p class="text-muted">Overview and control panel for the site administrator.</p>

    <div class="grid mt-4">
        <div class="card text-center">
            <div class="card-content">
                <h3 class="card-title">Manage Users</h3>
                <p class="card-text">View and moderate user accounts.</p>
                <a href="/pages/admin/users.php" class="btn">Go to Users</a>
            </div>
        </div>

        <div class="card text-center">
            <div class="card-content">
                <h3 class="card-title">Platform Settings</h3>
                <p class="card-text">Configure site-wide options and monitor activity.</p>
                <a href="/pages/admin/settings.php" class="btn btn-secondary">Manage Settings</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
