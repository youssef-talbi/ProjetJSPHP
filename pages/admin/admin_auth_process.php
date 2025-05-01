<?php
// Process Admin Login Form
require_once __DIR__ . "/../../bootstrap.php";

// Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/pages/admin/admin_auth.php?error=invalid_request");
    exit;
}

// Validate CSRF token
if (!validate_form_token("admin_login_token")) {
    redirect("/pages/admin/admin_auth.php?error=invalid_token");
    exit;
}

// Get form data
$username = sanitize_input($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

// Basic validation
if (empty($username) || empty($password)) {
    redirect("/pages/admin/admin_auth.php?error=empty_fields");
    exit;
}

// Database connection
$db = getDbConnection();
if (!$db) {
    redirect("/pages/admin/admin_auth.php?error=db_error");
    exit;
}

try {
    // Find the admin user by username
    $stmt = $db->prepare("SELECT user_id, username, password_hash, first_name, user_type 
                         FROM users 
                         WHERE username = :username AND user_type = 'admin' AND account_status = 'active'");
    $stmt->bindParam(":username", $username);
    $stmt->execute();
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify user exists and password is correct
    if ($admin_user && password_verify($password, $admin_user["password_hash"])) {
        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set admin session variables
        $_SESSION["admin_logged_in"] = true;
        $_SESSION["admin_user_id"] = $admin_user["user_id"];
        $_SESSION["admin_username"] = $admin_user["username"];
        $_SESSION["admin_first_name"] = $admin_user["first_name"];

        // Redirect to admin dashboard
        redirect("/pages/admin/dashboard.php");
        exit;
    } else {
        // Invalid credentials
        redirect("/pages/admin/admin_auth.php?error=invalid_credentials");
        exit;
    }

} catch (PDOException $e) {
    // Log error
    error_log("Admin login error: " . $e->getMessage());

    // Redirect back with generic error
    redirect("/pages/admin/admin_auth.php?error=login_failed");
    exit;
}
?>

