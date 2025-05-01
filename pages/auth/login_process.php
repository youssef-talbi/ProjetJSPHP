<?php
// Process login form submission
use Random\RandomException;

$baseUrl = '/improved';
// Start session if not already started
session_start();

// Include database connection
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Helpers.php';

// Initialize variables
$email = '';
$password = '';
$remember = false;
$error = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validate form data
    if (empty($email) || empty($password)) {
        // Redirect back with error
        header('Location: ' . $baseUrl . '/pages/auth/login.php?error=empty');
        exit;
    }
    
    try {
        // Connect to database
        $db = getDbConnection();
        if (!$db) {
            throw new PDOException("Database connection failed.");
        }

        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Prepare query to find user by email
        $stmt = $db->prepare("SELECT user_id, email, password_hash, user_type, first_name, last_name FROM users WHERE email = :email AND account_status = 'active'");
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        
        // Check if user exists
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Password is correct, create session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['logged_in'] = true;
                
                // Update last login time
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
                $updateStmt->bindParam(':user_id', $user['user_id']);
                $updateStmt->execute();
                
                // Set remember me cookie if requested
                if ($remember) {
                    $token = generateRandomToken(32);
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                    
                    // Store token in database
                    $tokenStmt = $db->prepare("INSERT INTO user_tokens (user_id, token, expiry) VALUES (:user_id, :token, :expiry)");
                    $tokenStmt->bindParam(':user_id', $user['user_id']);
                    $tokenStmt->bindParam(':token', $token);
                    $date = date('Y-m-d H:i:s', $expiry);
                    $tokenStmt->bindParam(':expiry', $date);
                    $tokenStmt->execute();
                    
                    // Set cookie
                    setcookie('remember_token', $token, $expiry, '/', '', false, true);
                }

                if ($user['user_type'] === 'client') {
                    header('Location: ' . $baseUrl . '/pages/dashboard/client-dashboard.php');
                } elseif ($user['user_type'] === 'freelancer') {
                    header('Location: ' . $baseUrl . '/pages/dashboard/freelancer-dashboard.php');
                } else {
                    header('Location: ' . $baseUrl . '/pages/dashboard/admin-dashboard.php');
                }

                exit;
            } else {
                // Password is incorrect
                $error = 'invalid';
            }
        } else {
            // User not found
            $error = 'invalid';
        }
    } catch (PDOException $e) {
        // Database error
        $error = 'db_error';
        // Log error for debugging
        error_log('Login error: ' . $e->getMessage());
    }
    
    // If we got here, there was an error
    header('Location: ' . $baseUrl . '/pages/auth/login.php?error=' . $error);
    exit;
}

// Helper function to generate random token
/**
 * @throws RandomException
 */
function generateRandomToken($length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}
