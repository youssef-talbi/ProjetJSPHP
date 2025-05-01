<?php
// Admin Check - Include this at the top of all admin pages

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== "admin") {
    // If not logged in or not an admin, redirect to login page
    // Store the intended destination to redirect back after login
    $redirect_url = urlencode($_SERVER["REQUEST_URI"]);
    redirect("/improved/pages/auth/login.php?error=admin_required&redirect=" . $redirect_url);
    exit;
}

// User is confirmed as admin, proceed with the admin page content.
$admin_user_id = $_SESSION["user_id"];
$admin_user_name = $_SESSION["user_name"];

?>
