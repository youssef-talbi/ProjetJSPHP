<?php
// Helper functions for the freelance platform

// Check if user is logged in
function log_user_in(array $user): void {
    // Assuming session_start() is called elsewhere
    $_SESSION["logged_in"] = true;
    $_SESSION["user_id"] = $user["user_id"];
    $_SESSION["user_type"] = $user["user_type"];
    $_SESSION["user_name"] = $user["first_name"]; // Or combine first/last name
    // Update last login time
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
        $stmt->bindParam(":user_id", $user["user_id"], PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Failed to update last login time for user " . $user["user_id"] . ": " . $e->getMessage());
    }
}

use Random\RandomException;

function is_logged_in(): bool
{
    // Ensure session is started before checking
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true;
}

// Get current user ID
function get_current_user_id() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION["user_id"] ?? 0;
}

// Get user type (client or freelancer)
function get_user_type() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION["user_type"] ?? "";
}

// Get user name
function get_user_name() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION["user_name"] ?? "";
}

// Format currency
function format_currency($amount, $currency = "$"): string
{
    return $currency . number_format((float)$amount, 2);
}

// Format date
function format_date($date, $format = "M j, Y") {
    if (empty($date)) return "N/A";
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : "Invalid Date";
}

// Calculate time ago
function time_ago($datetime): string
{
    if (empty($datetime)) return "never";
    $time = strtotime($datetime);
    if (!$time) return "invalid date";

    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . " month" . ($months > 1 ? "s" : "") . " ago";
    } else {
        $years = floor($diff / 31536000);
        return $years . " year" . ($years > 1 ? "s" : "") . " ago";
    }
}

// Sanitize input
function sanitize_input($data): string
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, "UTF-8");
    return $data;
}

// Generate random string
function generate_random_string($length = 10): string
{
    $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $charactersLength = strlen($characters);
    $randomString = "";
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Redirect with message
function redirect($url, $message = "", $message_type = "info") {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($message)) {
        $_SESSION["message"] = $message;
        $_SESSION["message_type"] = $message_type;
    }
    // Ensure URL is properly formed (handle relative/absolute)
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        // Assuming $baseUrl is defined globally or accessible
        global $baseUrl;
        $url = ($baseUrl ?? ".") . $url; // Adjust base URL as needed
    }
    header("Location: " . $url);
    exit;
}

// Display message
function display_message() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION["message"])) {
        $message = $_SESSION["message"];
        $message_type = $_SESSION["message_type"] ?? "info";

        // Sanitize message type for class name
        $alert_class = "alert-" . htmlspecialchars($message_type);

        echo
        "<div class=\"alert {$alert_class}\" data-dismissible data-auto-dismiss>";
        echo
            "<div class=\"alert-icon\">" . ($message_type === "success" ? "✓" : "!") . "</div>";
        echo
            "<div>" . htmlspecialchars($message) . "</div>"; // Sanitize message output
        echo
        "</div>";

        // Clear the message
        unset($_SESSION["message"]);
        unset($_SESSION["message_type"]);
    }
}

// Truncate text
function truncate_text($text, $length = 100, $append = "...") {
    if (mb_strlen($text) > $length) {
        $text = mb_substr($text, 0, $length) . $append;
    }
    return htmlspecialchars($text);
}

// Check if user has permission
function has_permission($permission): bool
{
    // This is a simple implementation, can be expanded based on roles
    if (!is_logged_in()) {
        return false;
    }

    $user_type = get_user_type();

    // Admin has all permissions
    if ($user_type === "admin") {
        return true;
    }

    switch ($permission) {
        case "create_project":
            return $user_type === "client";
        case "submit_proposal":
            return $user_type === "freelancer";
        case "view_dashboard":
            return true; // Both client and freelancer can view their dashboards
        // Add more specific permissions as needed
        default:
            return false;
    }
}

// Upload file helper (ensure BASE_PATH is defined in bootstrap.php)
function upload_file($file, $destination_folder, $allowed_types = ["jpg", "jpeg", "png", "pdf", "doc", "docx"], $max_size = 5 * 1024 * 1024): array
{
    // Check if file was uploaded without errors
    if ($file["error"] !== UPLOAD_ERR_OK) {
        return [
            "success" => false,
            "error" => "Upload failed with error code: " . $file["error"]
        ];
    }

    // Check file size
    if ($file["size"] > $max_size) {
        return [
            "success" => false,
            "error" => "File size exceeds the limit of " . ($max_size / 1024 / 1024) . "MB"
        ];
    }

    // Get file extension
    $file_info = pathinfo($file["name"]);
    $extension = strtolower($file_info["extension"] ?? "");

    // Check if file type is allowed
    if (empty($extension) || !in_array($extension, $allowed_types)) {
        return [
            "success" => false,
            "error" => "File type not allowed. Allowed types: " . implode(", ", $allowed_types)
        ];
    }

    // Create a unique filename to prevent overwrites and sanitize
    $safe_basename = preg_replace("/[^a-zA-Z0-9_.-]/", "_", $file_info["filename"]);
    $new_filename = uniqid() . "_" . $safe_basename . "." . $extension;
    $destination_path = rtrim($destination_folder, "/") . "/" . $new_filename;

    // Ensure destination folder exists and is writable
    if (!is_dir($destination_folder)) {
        if (!mkdir($destination_folder, 0775, true)) {
            return ["success" => false, "error" => "Failed to create upload directory."];
        }
    }
    if (!is_writable($destination_folder)) {
        return ["success" => false, "error" => "Upload directory is not writable."];
    }

    // Move the uploaded file
    if (move_uploaded_file($file["tmp_name"], $destination_path)) {
        return [
            "success" => true,
            "filename" => $new_filename, // Just the filename
            "path" => $destination_path, // Full path
            "relative_path" => str_replace(rtrim($_SERVER["DOCUMENT_ROOT"], "/"), "", $destination_path) // Example relative path
        ];
    } else {
        // Log detailed error if possible
        error_log("Failed to move uploaded file from " . $file["tmp_name"] . " to " . $destination_path);
        return [
            "success" => false,
            "error" => "Failed to move uploaded file. Check permissions and paths."
        ];
    }
}

// Get file icon based on extension
function get_file_icon($filename): string
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    return match ($extension) {
        "pdf" => "fa-file-pdf", // Example using Font Awesome classes
        "doc", "docx" => "fa-file-word",
        "xls", "xlsx" => "fa-file-excel",
        "ppt", "pptx" => "fa-file-powerpoint",
        "jpg", "jpeg", "png", "gif", "webp" => "fa-file-image",
        "zip", "rar", "7z" => "fa-file-archive",
        "txt" => "fa-file-alt",
        default => "fa-file",
    };
}

// Validate form token (CSRF protection)
function validate_form_token($token_name): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION[$token_name]) || !isset($_POST["token"])) {
        error_log("CSRF validation failed: Session or POST token not set for " . $token_name);
        return false;
    }

    $session_token = $_SESSION[$token_name];
    $post_token = $_POST["token"];

    // Use hash_equals for timing attack resistance
    if (!hash_equals($session_token, $post_token)) {
        error_log("CSRF validation failed: Token mismatch for " . $token_name);
        // Optionally keep the token for debugging or retry, but usually unset
        // unset($_SESSION[$token_name]);
        return false;
    }

    // Token is valid, remove it from session to prevent reuse
    unset($_SESSION[$token_name]);

    return true;
}

// Generate form token
/**
 * @throws RandomException
 */
function generate_form_token($token_name): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION[$token_name] = $token;
    return $token;
}

// Get Profile Picture URL
function get_profile_picture_url($filename):
string {
    if (empty($filename)) {
        // Return path to a default avatar
        return "/improved/assets/images/default_avatar.png"; // Adjust path as needed
    }
    // Assuming profile pictures are stored in /uploads/profile_pictures/ relative to web root
    return "/improved/uploads/profile_pictures/" . htmlspecialchars($filename);
}

// --- Notification Helper ---
/**
 * Creates a notification for a user.
 *
 * @param int $user_id The ID of the user to notify.
 * @param string $type A category for the notification (e.g., "message", "project", "proposal", "system").
 * @param string $content The notification message text.
 * @param int|null $related_id (Optional) ID related to the notification (e.g., project_id, message_id).
 * @param string $importance_level (Optional) Importance level ("low", "medium", "high").
 * @return bool True on success, false on failure.
 */
function create_notification($user_id, $type, $content, $related_id = null) {
    $db = getDbConnection();
    if (!$db) return;

    try {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, type, content, related_id) VALUES (:user_id, :type, :content, :related_id)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':type' => $type,
            ':content' => $content,
            ':related_id' => $related_id
        ]);
    } catch (PDOException $e) {
        error_log("Notification creation failed: " . $e->getMessage());
    }
}

// --- Payment/Transaction Helper ---
/**
 * Records a financial transaction in the system.
 *
 * @param int $user_id User initiating or receiving.
 * @param string $type Type of transaction.
 * @param float $amount Transaction amount.
 * @param string $status Initial status (e.g., "pending", "completed").
 * @param int|null $related_user_id Other user involved.
 * @param int|null $contract_id Related contract.
 * @param int|null $milestone_id Related milestone.
 * @param string $description Optional description.
 * @param string $currency Currency code (default USD).
 * @param string|null $external_id External gateway ID.
 * @return int|false The ID of the created transaction on success, false on failure.
 */
function record_transaction(
    int $user_id,
    string $type,
    float $amount,
    string $status = "completed",
    ?int $related_user_id = null,
    ?int $contract_id = null,
    ?int $milestone_id = null,
    string $description = "",
    string $currency = "USD",
    ?string $external_id = null
): int|false {
    try {
        $db = getDbConnection();
        if (!$db) {
            throw new PDOException("Database connection failed for transaction recording.");
        }

        $stmt = $db->prepare("
            INSERT INTO transactions (
                user_id, related_user_id, contract_id, milestone_id, 
                transaction_type, amount, currency, status, description, 
                transaction_date, external_transaction_id
            )
            VALUES (
                :user_id, :related_user_id, :contract_id, :milestone_id, 
                :type, :amount, :currency, :status, :description, 
                NOW(), :external_id
            )
        ");

        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":related_user_id", $related_user_id, PDO::PARAM_INT);
        $stmt->bindParam(":contract_id", $contract_id, PDO::PARAM_INT);
        $stmt->bindParam(":milestone_id", $milestone_id, PDO::PARAM_INT);
        $stmt->bindParam(":type", $type, PDO::PARAM_STR);
        $stmt->bindParam(":amount", $amount); // PDO handles float/decimal conversion
        $stmt->bindParam(":currency", $currency, PDO::PARAM_STR);
        $stmt->bindParam(":status", $status, PDO::PARAM_STR);
        $stmt->bindParam(":description", $description, PDO::PARAM_STR);
        $stmt->bindParam(":external_id", $external_id, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return (int)$db->lastInsertId();
        } else {
            return false;
        }

    } catch (PDOException $e) {
        error_log("Failed to record transaction for user {$user_id}: " . $e->getMessage());
        return false;
    }
}

?>

