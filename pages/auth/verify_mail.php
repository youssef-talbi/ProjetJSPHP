<?php
// Handle email verification
$baseUrl = "."; // Adjusted base URL relative to current directory
session_start();

require_once __DIR__ .
    '/../../config/database.php';
require_once __DIR__ .
    '/../../utils/Helpers.php';

// Get token from URL
$token = $_GET["token"] ?? "";

if (empty($token)) {
    // No token provided
    header("Location: {$baseUrl}/login.php?error=no_token");
    exit;
}

try {
    $db = getDbConnection();
    if (!$db) throw new PDOException("Connection failed.");

    // Find user by verification token
    $stmt = $db->prepare("SELECT user_id, email_verified FROM users WHERE verification_token = :token");
    $stmt->bindValue(":token", $token);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Invalid or expired token
        header("Location: {$baseUrl}/login.php?error=invalid_token");
        exit;
    }

    if ($user["email_verified"]) {
        // Email already verified
        header("Location: {$baseUrl}/login.php?message=already_verified");
        exit;
    }

    // Update user status to verified and clear token
    $stmt = $db->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE user_id = :user_id");
    $stmt->bindValue(":user_id", $user["user_id"]);
    $stmt->execute();

    // Redirect to login with success message
    header("Location: {$baseUrl}/login.php?message=verified");
    exit;

} catch (PDOException $e) {
    error_log("Verification error: " . $e->getMessage());
    header("Location: {$baseUrl}/login.php?error=verification_failed");
    exit;
}

