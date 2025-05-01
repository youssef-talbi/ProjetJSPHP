<?php
// Include bootstrap (already included in pages that use this header)
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../utils/Helpers.php';

// Set base URL relative to your project folder
$baseUrl = '/improved';

// Fetch unread notification count for logged-in user
$unread_notification_count = 0;
if (is_logged_in()) {
    try {
        $db = getDbConnection();
        $user_id = get_current_user_id();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_status = 0");
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $unread_notification_count = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Failed to fetch notification count: " . $e->getMessage());
        // Handle error appropriately, maybe show 0 or an error indicator
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . " - " : ""; ?>Freelance Hub</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Main CSS -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/main.css">

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Add specific CSS for notifications dropdown if needed -->
    <style>
        .notification-badge {
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7em;
            position: relative;
            top: -8px;
            right: -2px;
        }
        .nav-links .notification-icon {
            position: relative;
        }
    </style>
</head>
<body>
<header>
    <div class="container">
        <nav>
            <div class="logo">
                <a href="<?php echo $baseUrl; ?>/">
                    <img src="<?php echo $baseUrl; ?>/assets/images/freelancer_logo.png" alt="Freelance Hub Logo">
                    Freelance Hub
                </a>
            </div>
            <!-- Search Bar -->
            <form action="<?php echo $baseUrl; ?>/pages/search_results.php" method="get" class="search-form">
                <label>
                    <input type="text" name="query" placeholder="Search projects, freelancers..." required>
                </label>
                <button type="submit" class="btn btn-sm">Search</button>
            </form>
            <ul class="nav-links">
                <li><a href="<?php echo $baseUrl; ?>/pages/projects/list.php">Browse Projects</a></li>
                <li><a href="<?php echo $baseUrl; ?>/pages/projects/create.php">Post a Project</a></li>
                <?php if (is_logged_in()): ?>
                    <li><a href="<?php echo $baseUrl; ?>/pages/dashboard/<?php echo get_user_type(); ?>-dashboard.php">Dashboard</a></li>
                    <!-- Notification Icon/Link -->
                    <li class="notification-icon">
                        <a href="<?php echo $baseUrl; ?>/pages/notifications.php">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_notification_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_notification_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <!-- Dropdown can be added here later -->
                    </li>
                    <li><a href="<?php echo $baseUrl; ?>/pages/profile/view.php?id=<?php echo get_current_user_id(); ?>">Profile</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/pages/auth/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo $baseUrl; ?>/pages/auth/login.php">Login</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/pages/auth/register.php" class="btn btn-sm">Sign Up</a></li>
                <?php endif; ?>
            </ul>
            <div class="burger">
                <div class="line1"></div>
                <div class="line2"></div>
                <div class="line3"></div>
            </div>
        </nav>
    </div>
</header>
<main>
    <?php display_message(); // Display flash messages after header ?>

