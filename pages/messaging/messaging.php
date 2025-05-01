<?php
// Messaging - List Conversations
require_once __DIR__ . "/../../bootstrap.php";

// Check if user is logged in
if (!is_logged_in()) {
    redirect("/improved/pages/auth/login.php?error=login_required&redirect=/improved/pages/messaging/");
    exit;
}

$user_id = get_current_user_id();
$db = getDbConnection();

$conversations = [];
$error_message = null;

if ($db) {
    try {
        // Fetch conversations involving the current user
        // Join with users table to get the name of the other participant(s)
        // Join with messages table to get the last message and its timestamp
        $stmt = $db->prepare("
            SELECT 
                c.conversation_id, 
                c.project_id, 
                p.title AS project_title,
                GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') AS participants,
                MAX(m.timestamp) AS last_message_time,
                (SELECT message_text FROM messages WHERE conversation_id = c.conversation_id ORDER BY timestamp DESC LIMIT 1) AS last_message_text,
                (SELECT COUNT(*) FROM messages WHERE conversation_id = c.conversation_id AND sender_id != :current_user_id AND read_status = 0 AND message_id > IFNULL(cp.last_read_message_id, 0)) AS unread_count
            FROM conversations c
            JOIN conversation_participants cp ON c.conversation_id = cp.conversation_id
            JOIN users u ON cp.user_id = u.user_id
            LEFT JOIN projects p ON c.project_id = p.project_id
            LEFT JOIN messages m ON c.conversation_id = m.conversation_id
            WHERE c.conversation_id IN (SELECT conversation_id FROM conversation_participants WHERE user_id = :current_user_id)
              AND cp.user_id != :current_user_id -- Select the *other* participants for display
            GROUP BY c.conversation_id
            ORDER BY last_message_time DESC
        ");
        // Note: The unread_count logic might need refinement based on how last_read_message_id is updated.
        // A simpler approach might just count messages where read_status = 0 and sender_id != current_user_id.
        // Let's refine the unread count query slightly.

        $stmt_refined = $db->prepare("
            SELECT 
                c.conversation_id, 
                c.project_id, 
                p.title AS project_title,
                GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) ORDER BY u.user_id SEPARATOR ', ') AS participant_names,
                MAX(m.timestamp) AS last_message_time,
                (SELECT message_text FROM messages WHERE conversation_id = c.conversation_id ORDER BY timestamp DESC LIMIT 1) AS last_message_text,
                (SELECT COUNT(*) FROM messages msg 
                 JOIN conversation_participants current_cp ON msg.conversation_id = current_cp.conversation_id
                 WHERE msg.conversation_id = c.conversation_id 
                   AND msg.sender_id != :current_user_id 
                   AND msg.read_status = 0 
                   AND current_cp.user_id = :current_user_id
                 ) AS unread_count -- Simplified unread count for now
            FROM conversations c
            JOIN conversation_participants cp_self ON c.conversation_id = cp_self.conversation_id AND cp_self.user_id = :current_user_id
            JOIN conversation_participants cp_other ON c.conversation_id = cp_other.conversation_id AND cp_other.user_id != :current_user_id
            JOIN users u ON cp_other.user_id = u.user_id
            LEFT JOIN projects p ON c.project_id = p.project_id
            LEFT JOIN messages m ON c.conversation_id = m.conversation_id
            GROUP BY c.conversation_id, p.title
            ORDER BY last_message_time DESC
        ");

        $stmt_refined->bindParam(":current_user_id", $user_id, PDO::PARAM_INT);
        $stmt_refined->execute();
        $conversations = $stmt_refined->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching conversations: " . $e->getMessage());
        $error_message = "Could not load conversations.";
    }
} else {
    $error_message = "Database connection error.";
}

// Include header
$page_title = "Messages";
require_once __DIR__ . "/../../includes/header.php";

?>

<div class="container">
    <h2 class="page-title">Your Conversations</h2>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="conversations-list">
        <?php if (empty($conversations)): ?>
            <p>You have no active conversations.</p>
            <!-- Option to start a new conversation could go here -->
        <?php else: ?>
            <?php foreach ($conversations as $convo): ?>
                <a href="/improved/pages/messaging/view.php?id=<?php echo $convo['conversation_id']; ?>" class="conversation-item-link">
                    <div class="conversation-item <?php echo ($convo['unread_count'] > 0) ? 'unread' : 'read'; ?>">
                        <div class="convo-participants">
                            <?php echo htmlspecialchars($convo['participant_names']); ?>
                            <?php if ($convo['project_title']): ?>
                                <span class="convo-project-title">(Project: <?php echo htmlspecialchars(truncate_text($convo['project_title'], 30)); ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="convo-last-message">
                            <?php echo htmlspecialchars(truncate_text($convo['last_message_text'] ?? 'No messages yet', 50)); ?>
                        </div>
                        <div class="convo-meta">
                            <span class="timestamp"><?php echo time_ago($convo['last_message_time']); ?></span>
                            <?php if ($convo['unread_count'] > 0): ?>
                                <span class="unread-badge"><?php echo $convo['unread_count']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php
// Include footer
require_once __DIR__ . "/../../includes/footer.php";
?>

