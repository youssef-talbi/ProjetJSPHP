<?php
// Process Admin User Actions (Activate/Suspend)
require_once __DIR__ . "/../../bootstrap.php";

// Check if admin is logged in
if (!is_admin_logged_in()) {
    redirect("/pages/admin/admin_auth.php?error=login_required");
    exit;
}

// Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/pages/admin/manage_users.php?error=invalid_request");
    exit;
}

// Validate CSRF token
if (!validate_form_token("admin_user_action_token")) {
    redirect("/pages/admin/manage_users.php?error=invalid_token");
    exit;
}

// Get action and user ID
$action = sanitize_input($_POST["action"] ?? "");
$user_id = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);

if (!$user_id || !in_array($action, ["activate", "suspend"])) {
    redirect("/pages/admin/manage_users.php?error=invalid_action_or_user");
    exit;
}

// Determine the new status based on the action
$new_status = null;
if ($action === "activate") {
    $new_status = "active";
} elseif ($action === "suspend") {
    $new_status = "suspended";
}

if (!$new_status) {
    redirect("/pages/admin/manage_users.php?error=invalid_action");
    exit;
}

// Database connection
$db = getDbConnection();
if (!$db) {
    redirect("/pages/admin/manage_users.php?error=db_error");
    exit;
}

try {
    // Update user status
    $stmt = $db->prepare("UPDATE users SET account_status = :new_status WHERE user_id = :user_id AND user_type != 'admin'"); // Prevent changing admin status
    $stmt->bindParam(":new_status", $new_status);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $message = ($action === "activate") ? "user_activated" : "user_suspended";
        redirect("/pages/admin/manage_users.php?message=" . $message);
    } else {
        redirect("/pages/admin/manage_users.php?error=user_not_found_or_no_change");
    }
    exit;

} catch (PDOException $e) {
    // Log error
    error_log("Admin user action error: " . $e->getMessage());

    // Redirect back with error
    redirect("/pages/admin/manage_users.php?error=db_error");
    exit;
}
?>

