<?php
// Notifications page
require_once __DIR__ . "/../bootstrap.php";

// Check if user is logged in
if (!is_logged_in()) {
    redirect("/improved/pages/auth/login.php?error=login_required&redirect=/improved/pages/notifications.php");
    exit;
}

$user_id = get_current_user_id();
$db = getDbConnection();

$notifications = [];
$error_message = null;

if ($db) {
    try {
        // Fetch notifications for the user, newest first
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_date DESC LIMIT 50"); // Limit to recent 50
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark all fetched notifications as read (simple approach)
        // A more robust approach might mark only on specific user action or track individually
        $update_stmt = $db->prepare("UPDATE notifications SET read_status = 1 WHERE user_id = :user_id AND read_status = 0");
        $update_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $update_stmt->execute();

    } catch (PDOException $e) {
        error_log("Error fetching/updating notifications: " . $e->getMessage());
        $error_message = "Could not load notifications.";
    }
} else {
    $error_message = "Database connection error.";
}

// Include header
$page_title = "Notifications";
require_once __DIR__ . "/../includes/header.php";

?>

<div class="container">
    <h2 class="page-title">Your Notifications</h2>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="notifications-list">
        <?php if (empty($notifications)): ?>
            <p>You have no notifications.</p>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification["read_status"] == 0 ? "unread" : "read"; ?>">
                    <div class="notification-icon">
                        <?php
                        // Basic icon based on type
                        $icon_class = "fas fa-info-circle"; // Default
                        switch ($notification["type"]) {
                            case "message": $icon_class = "fas fa-envelope"; break;
                            case "project": $icon_class = "fas fa-briefcase"; break;
                            case "proposal": $icon_class = "fas fa-file-alt"; break;
                            case "contract": $icon_class = "fas fa-handshake"; break;
                            case "payment": $icon_class = "fas fa-dollar-sign"; break;
                            case "review": $icon_class = "fas fa-star"; break;
                            case "system": $icon_class = "fas fa-cog"; break;
                        }
                        ?>
                        <i class="<?php echo $icon_class; ?>"></i>
                    </div>
                    <div class="notification-content">
                        <p><?php echo htmlspecialchars($notification["content"]); ?></p>
                        <span class="timestamp"><?php echo time_ago($notification["created_date"]); ?></span>
                        <?php
                        // Add link if related_id exists and type is known
                        $link = null;
                        if (!empty($notification["related_id"])) {
                            switch ($notification["type"]) {
                                case "project":
                                case "proposal":
                                case "contract":
                                    $link = "/improved/pages/projects/view.php?id=" . $notification["related_id"];
                                    break;
                                // Add cases for messages, users etc. later
                            }
                        }
                        if ($link): ?>
                            <a href="<?php echo $link; ?>" class="notification-link">View Details</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>

