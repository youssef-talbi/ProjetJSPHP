<?php
// Messaging - Send Message (Handles AJAX request from view.php)
require_once __DIR__ . "/../../bootstrap.php";

// Set content type to JSON for AJAX response
header("Content-Type: application/json");

// Default response
$response = ["success" => false, "error" => "An unknown error occurred."];

// Check if user is logged in
if (!is_logged_in()) {
    $response["error"] = "Authentication required.";
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}

// Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response["error"] = "Invalid request method.";
    http_response_code(405); // Method Not Allowed
    echo json_encode($response);
    exit;
}

// Validate CSRF token
if (!validate_form_token("send_message_token")) {
    $response["error"] = "Invalid security token. Please refresh the page.";
    http_response_code(403); // Forbidden
    echo json_encode($response);
    exit;
}

// Get current user ID
$user_id = get_current_user_id();

// Get and sanitize input
$conversation_id = filter_input(INPUT_POST, "conversation_id", FILTER_VALIDATE_INT);
$message_text = trim($_POST["message_text"] ?? "");

// Validate input
if (!$conversation_id) {
    $response["error"] = "Invalid conversation ID.";
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit;
}
if (empty($message_text)) {
    $response["error"] = "Message text cannot be empty.";
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit;
}

// Database connection
$db = getDbConnection();
if (!$db) {
    $response["error"] = "Database connection error.";
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
    exit;
}

try {
    // Begin transaction
    $db->beginTransaction();

    // 1. Verify user is part of this conversation
    $verify_stmt = $db->prepare("SELECT 1 FROM conversation_participants WHERE conversation_id = :conversation_id AND user_id = :user_id");
    $verify_stmt->bindParam(":conversation_id", $conversation_id, PDO::PARAM_INT);
    $verify_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $verify_stmt->execute();
    if ($verify_stmt->fetchColumn() === false) {
        $db->rollBack();
        $response["error"] = "You are not a participant in this conversation.";
        http_response_code(403); // Forbidden
        echo json_encode($response);
        exit;
    }

    // 2. Insert the new message
    $insert_stmt = $db->prepare("
        INSERT INTO messages (conversation_id, sender_id, message_text, timestamp, read_status)
        VALUES (:conversation_id, :sender_id, :message_text, NOW(), 0)
    "); // read_status is 0 initially
    $insert_stmt->bindParam(":conversation_id", $conversation_id, PDO::PARAM_INT);
    $insert_stmt->bindParam(":sender_id", $user_id, PDO::PARAM_INT);
    $insert_stmt->bindParam(":message_text", $message_text, PDO::PARAM_STR);

    if (!$insert_stmt->execute()) {
        throw new PDOException("Failed to insert message.");
    }
    $message_id = $db->lastInsertId();
    $timestamp = date("Y-m-d H:i:s"); // Get current timestamp roughly

    // 3. Get other participants to notify
    $notify_stmt = $db->prepare("SELECT user_id FROM conversation_participants WHERE conversation_id = :conversation_id AND user_id != :sender_id");
    $notify_stmt->bindParam(":conversation_id", $conversation_id, PDO::PARAM_INT);
    $notify_stmt->bindParam(":sender_id", $user_id, PDO::PARAM_INT);
    $notify_stmt->execute();
    $participants_to_notify = $notify_stmt->fetchAll(PDO::FETCH_COLUMN);

    // 4. Create notifications for other participants
    $sender_name = get_user_name(); // Get sender name from session/helper
    $notification_content = "New message from " . $sender_name . ": " . truncate_text($message_text, 50);
    foreach ($participants_to_notify as $recipient_id) {
        create_notification($recipient_id, "message", $notification_content, $conversation_id, "medium");
        // Error handling for create_notification is within the function (logs errors)
    }

    // Commit transaction
    $db->commit();

    // Prepare success response for AJAX
    $response["success"] = true;
    $response["message"] = [
        "id" => $message_id,
        "text" => nl2br(htmlspecialchars($message_text)), // Format for display
        "sender_id" => $user_id,
        "timestamp_full" => $timestamp,
        "timestamp_relative" => time_ago($timestamp)
    ];
    unset($response["error"]); // Remove default error message

    http_response_code(201); // Created
    echo json_encode($response);
    exit;

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Send message error: " . $e->getMessage());
    $response["error"] = "Database error while sending message.";
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    // Catch any other unexpected errors
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("General send message error: " . $e->getMessage());
    $response["error"] = "An unexpected error occurred while sending the message.";
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
    exit;
}

?>

