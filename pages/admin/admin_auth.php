<?php
// Admin Authentication Page
require_once __DIR__ . "/../../bootstrap.php";

// If admin is already logged in, redirect to dashboard
if (is_admin_logged_in()) {
    redirect("/pages/admin/dashboard.php");
    exit;
}

// Include header (use a specific admin header if available, otherwise the main one)
$page_title = "Admin Login";
// Note: Consider creating a separate, simpler header/footer for the admin section
require_once __DIR__ . "/../../includes/header.php"; 

// Generate CSRF token
$token = generate_form_token("admin_login_token");

?>

<div class="container">
    <div class="form-container" style="max-width: 400px; margin: 50px auto;">
        <h2 class="form-title">Admin Login</h2>

        <?php display_message(); // Display any session messages (e.g., login errors) ?>

        <form action="/pages/admin/admin_auth_process.php" method="post" class="validate-form">
            <input type="hidden" name="token" value="<?php echo $token; ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Login</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer (use a specific admin footer if available)
require_once __DIR__ . "/../../includes/footer.php"; 
?>

