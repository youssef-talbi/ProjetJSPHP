<?php
// Process registration form submission
$baseUrl = '/improved';
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Helpers.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize form data
    $user_type = trim($_POST['user_type'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $terms = isset($_POST['terms']);

    // Validate input
    if (empty($user_type) || empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password) || !$terms) {
        header('Location: /pages/auth/register.php?error=empty');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ' . $baseUrl . '/pages/auth/register.php?error=invalid_email');
        exit;
    }

    if ($password !== $confirm_password) {
        header('Location: ' . $baseUrl . '/pages/auth/register.php?error=password_mismatch');
        exit;
    }

    if (strlen($password) < 8) {
        header('Location: ' . $baseUrl . '/pages/auth/register.php?error=weak_password');
        exit;
    }

    try {
        // Use shared DB connection
        $db = getDbConnection();
        if (!$db) throw new PDOException("Connection failed.");

        // Check if email exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :email");
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            header('Location: ' . $baseUrl . '/pages/auth/register.php?error=email_exists');
            exit;
        }
        if (!validate_form_token('register_form')) {
            http_response_code(403);
            exit;
        }


        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Begin transaction
        $db->beginTransaction();

        // Insert user
        $stmt = $db->prepare("
            INSERT INTO users (email, password_hash, user_type, first_name, last_name, location, registration_date, account_status)
            VALUES (:email, :password_hash, :user_type, :first_name, :last_name, :location, NOW(), 'active')
        ");
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':password_hash', $password_hash);
        $stmt->bindValue(':user_type', $user_type);
        $stmt->bindValue(':first_name', $first_name);
        $stmt->bindValue(':last_name', $last_name);
        $stmt->bindValue(':location', $location);
        $stmt->execute();

        $user_id = $db->lastInsertId();

        // Insert profile
        if ($user_type === 'freelancer') {
            $stmt = $db->prepare("INSERT INTO freelancer_profiles (user_id) VALUES (:user_id)");
        } elseif ($user_type === 'client') {
            $stmt = $db->prepare("INSERT INTO client_profiles (user_id) VALUES (:user_id)");
        }

        if (isset($stmt)) {
            $stmt->bindValue(':user_id', $user_id);
            $stmt->execute();
        }

        $db->commit();

        // Redirect to login with success message
        header('Location: ' . $baseUrl . '/pages/auth/login.php?message=registered');
        exit;

    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Registration error: " . $e->getMessage());
        header('Location: ' . $baseUrl . '/pages/auth/register.php?error=db_error');
        exit;
    }
} else {
    header('Location: ' . $baseUrl . '/pages/auth/register.php');
    exit;
}
