<?php
// Messaging - View Conversation
require_once __DIR__ . "/../../bootstrap.php";

// Check if user is logged in
if (!is_logged_in()) {
    redirect("/improved/pages/auth/login.php?error=login_required&redirect=" . urlencode($_SERVER["REQUEST_URI"]));
    exit;
}

$user_id = get_current_user_id();
$conversation_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$conversation_id) {
    redirect("/improved/pages/messaging/?error=invalid_conversation");
    exit;
}

$db = getDbConnection();

$conversation = null;
$messages = [];
$participants = [];
$project_title = null;
$error_message = null;

if ($db) {
    try {
        // 1. Verify user is part of this conversation
        $verify_stmt = $db->prepare("SELECT 1 FROM conversation_participants WHERE conversation_id = :conversation_id AND user_id = :user_id");
        $verify_stmt->bindParam(":conversation_id", $conversation_id, PDO::PARAM_INT);
        $verify_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $verify_stmt->execute();
        if ($verify_stmt->fetchColumn() === false) {
            redirect("/improved/pages/messaging/?error=access_denied");
            exit;
        }

        // 2. Fetch conversation details (participants, project)
        $convo_stmt = $db->prepare("
            SELECT 
                c.project_id,
                p.title AS project_title,
                GROUP_CONCAT(DISTINCT CONCAT(u.user_id, "," u.first_name, ", ", u.last_name) ORDER BY u.user_id SEPARATOR ",") AS participant_details
            FROM conversations c
            JOIN conversation_participants cp ON c.conversation_id = cp.conversation_id
            JOIN users u ON cp.user_id = u.user_id
            LEFT JOIN projects p ON c.project_id = p.project_id
            WHERE c.conversation_id = :conversation_id
            GROUP BY c.conversation_id, p.title
        ");
        $convo_stmt->bindParam(":conversation_id", $conversation_id, PDO::PARAM_INT);
        $convo_stmt->execute();
        $conversation = $convo_stmt->fetch(PDO::FETCH_ASSOC);

        if ($conversation) {
            $project_title = $conversation["project_title"];
            // Parse participants
            $details = explode(";", $conversation["participant_details"]);
            foreach ($details as $detail) {
                list($p_id, $p_name) = explode(":", $detail, 2);
                $participants[(int)$p_id] = $p_name;
            }
        }

        // 3. Fetch messages for this conversation
        $msg_stmt = $db->prepare("
            SELECT m.*, u.first_name AS sender_name 
            FROM messages m 
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.conversation_id = :conversation_id 
            ORDER BY m.timestamp ASC
        ");
        $msg_stmt->bindParam(":conversation_id", $conversation_id, PDO::PARAM_INT);
        $msg_stmt->execute();
        $messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Mark messages as read for the current user in this conversation
        $update_read_stmt = $db->prepare("
            UPDATE messages 
            SET read_status = 1 
            WHERE conversation_id = :conversation_id 
              AND sender_id != :user_id 
              AND read_status = 0
        ");
        // Note: A more robust read status might involve a separate read receipts table
        // or updating last_read_message_id in conversation_participants.
        // This simple approach marks all incoming messages as read upon viewing the conversation.
        $update_read_stmt->bindParam(":conversation_id", $conversation_id, PDO::PARAM_INT);
        $update_read_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $update_read_stmt->execute();

        // Also update the related notification if one exists and links here (optional)
        // $update_notif_stmt = $db->prepare("UPDATE notifications SET read_status = 1 WHERE user_id = :user_id AND type = "message" AND related_id = :conversation_id");
        // ... execute ...

    } catch (PDOException $e) {
        error_log("Error fetching conversation view: " . $e->getMessage());
        $error_message = "Could not load conversation details.";
    }
} else {
    $error_message = "Database connection error.";
}

// Determine other participant(s) names for the title
$other_participants_names = [];
foreach ($participants as $p_id => $p_name) {
    if ($p_id !== $user_id) {
        $other_participants_names[] = $p_name;
    }
}
$conversation_title = implode(", ", $other_participants_names);
if ($project_title) {
    $conversation_title .= " - Project: " . htmlspecialchars($project_title);
}

// Include header
$page_title = "Conversation: " . htmlspecialchars(implode(", ", $other_participants_names));
require_once __DIR__ . "/../../includes/header.php";

// Generate CSRF token for message form
$token = generate_form_token("send_message_token");

?>

<div class="container messaging-container">
    <div class="messaging-header">
        <a href="/improved/pages/messaging/" class="back-link">&larr; Back to Conversations</a>
        <h2 class="page-title conversation-title"><?php echo $conversation_title; ?></h2>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="message-list" id="message-list">
        <?php if (empty($messages)): ?>
            <p class="no-messages">No messages in this conversation yet. Start by sending one below.</p>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <div class="message-item <?php echo ($message["sender_id"] == $user_id) ? "sent" : "received"; ?>">
                    <div class="message-bubble">
                        <div class="message-sender"><strong><?php echo ($message["sender_id"] == $user_id) ? "You" : htmlspecialchars($message["sender_name"]); ?></strong></div>
                        <div class="message-text"><?php echo nl2br(htmlspecialchars($message["message_text"])); ?></div>
                        <div class="message-timestamp" title="<?php echo format_date($message["timestamp"], "Y-m-d H:i:s"); ?>">
                            <?php echo time_ago($message["timestamp"]); ?>
                        </div>
                        <!-- Add attachment display later -->
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="message-input-area">
        <form action="/improved/pages/messaging/send_message.php" method="post" id="send-message-form">
            <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
            <input type="hidden" name="token" value="<?php echo $token; ?>">
            <div class="form-group">
                <textarea name="message_text" id="message_text" rows="3" placeholder="Type your message here..." required></textarea>
            </div>
            <!-- Add attachment button later -->
            <div class="form-actions">
                <button type="submit" class="btn">Send Message</button>
            </div>
        </form>
    </div>

</div>

<!-- Add JS for auto-scrolling to bottom -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const messageList = document.getElementById("message-list");
        if (messageList) {
            messageList.scrollTop = messageList.scrollHeight;
        }

        // Optional: Basic AJAX submission to prevent full page reload
        const form = document.getElementById("send-message-form");
        if (form) {
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                const messageText = formData.get("message_text").trim();

                if (!messageText) return; // Don"t send empty messages

                fetch(form.action, {
                    method: "POST",
                    body: formData
                })
                    .then(response => response.json()) // Expect JSON response from send_message.php
                    .then(data => {
                        if (data.success && data.message) {
                            // Add the new message to the UI
                            const newMessageDiv = document.createElement("div");
                            newMessageDiv.classList.add("message-item", "sent");
                            newMessageDiv.innerHTML = `
                            <div class="message-bubble">
                                <div class="message-sender"><strong>You</strong></div>
                                <div class="message-text">${data.message.text}</div>
                                <div class="message-timestamp" title="${data.message.timestamp_full}">${data.message.timestamp_relative}</div>
                            </div>
                        `;
                            messageList.appendChild(newMessageDiv);
                            messageList.scrollTop = messageList.scrollHeight; // Scroll down
                            form.reset(); // Clear the textarea
                            // Regenerate CSRF token if needed (requires more complex handling)
                        } else {
                            // Display error message (e.g., in a dedicated error div)
                            console.error("Failed to send message:", data.error || "Unknown error");
                            alert("Error sending message: " + (data.error || "Please try again."));
                        }
                    })
                    .catch(error => {
                        console.error("Network or server error:", error);
                        alert("Error sending message. Please check your connection and try again.");
                    });
            });
        }
    });
</script>

<?php
// Include footer
require_once __DIR__ . "/../../includes/footer.php";
?>

