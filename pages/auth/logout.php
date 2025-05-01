<?php
require_once __DIR__ . '/../../bootstrap.php';
$baseUrl = '/improved';
if (is_logged_in()) {
    $userId = get_current_user_id();

    try {
        $db = getDbConnection();

        // Remove token from DB
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $stmt = $db->prepare("DELETE FROM user_tokens WHERE user_id = :user_id AND token = :token");
            $stmt->bindValue(':user_id', $userId);
            $stmt->bindValue(':token', $token);
            $stmt->execute();

            // Remove cookie
            setcookie('remember_token', '', time() - 3600, '/');
        }

    } catch (PDOException $e) {
        error_log("Logout error: " . $e->getMessage());
    }
}

// End session
session_unset();
session_destroy();

// Redirect to login with message
header("Location: " . $baseUrl . "/pages/auth/login.php?message=logged_out");

exit;
