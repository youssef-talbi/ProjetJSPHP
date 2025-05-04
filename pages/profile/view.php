<?php
// Include bootstrap
require_once __DIR__ . "/../../bootstrap.php";
$baseUrl="/improved";
// Get user ID from query string
$user_id = isset($_GET["id"]) ? filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT) : null;

if (!$user_id) {
    // Redirect to a generic error page or homepage if ID is missing or invalid
    redirect("/index.php?error=invalid_profile_id");
    exit;
}

// Database connection
$db = getDbConnection();
if (!$db) {
    // Handle DB connection error - maybe show a generic error page
    echo "Database connection error."; // Replace with a proper error page
    exit;
}

// Fetch user data
$stmt = $db->prepare("SELECT u.*, 
                         fp.headline AS freelancer_headline, fp.summary AS freelancer_bio, fp.hourly_rate, fp.experience_level, u.profile_picture, 
                         cp.company_name, cp.website AS client_website, cp.description AS client_bio
                  FROM users u
                  LEFT JOIN freelancer_profiles fp ON u.user_id = fp.user_id AND u.user_type = 'freelancer'
                  LEFT JOIN client_profiles cp ON u.user_id = cp.user_id AND u.user_type = 'client'
                  WHERE u.user_id = :user_id AND u.account_status = 'active'");
$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // User not found or not active
    redirect("/index.php?error=profile_not_found");
    exit;
}

// Determine profile type and specific data
$is_freelancer = ($user["user_type"] === "freelancer");
$is_client = ($user["user_type"] === "client");

// Fetch additional data based on user type
$skills = [];
$projects_posted = [];
$reviews = [];
$average_rating = 0;
$review_count = 0;

if ($is_freelancer) {
    // Fetch freelancer skills
    $skill_stmt = $db->prepare("SELECT s.skill_name FROM skills s JOIN user_skills us ON s.skill_id = us.skill_id WHERE us.user_id = :user_id");
    $skill_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $skill_stmt->execute();
    $skills = $skill_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch freelancer reviews and calculate average rating
    $review_stmt = $db->prepare("SELECT r.*, u.first_name as reviewer_first_name, u.last_name as reviewer_last_name 
                                FROM reviews r 
                                JOIN users u ON r.reviewer_id = u.user_id 
                                WHERE r.reviewee_id = :user_id -- Assuming reviewee_id is the freelancer
                                ORDER BY r.submission_date DESC");
    $review_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $review_stmt->execute();
    $reviews = $review_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($reviews)) {
        $total_rating = array_sum(array_column($reviews, 'rating'));
        $review_count = count($reviews);
        $average_rating = $total_rating / $review_count;
    }
} elseif ($is_client) {
    // Fetch projects posted by the client
    $project_stmt = $db->prepare("SELECT project_id, title, status, creation_date FROM projects WHERE client_id = :user_id ORDER BY creation_date DESC LIMIT 5");
    $project_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $project_stmt->execute();
    $projects_posted = $project_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch reviews given by the client
    $review_stmt = $db->prepare("SELECT r.*, u.first_name as freelancer_first_name, u.last_name as freelancer_last_name
                                FROM reviews r
                                JOIN users u ON r.reviewee_id = u.user_id
                                WHERE r.reviewer_id = :user_id -- Assuming reviewer_id is the client
                                ORDER BY r.submission_date DESC");
    $review_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $review_stmt->execute();
    $reviews = $review_stmt->fetchAll(PDO::FETCH_ASSOC);
    $review_count = count($reviews);
}

// Include header
$page_title = htmlspecialchars($user["first_name"] . " " . $user["last_name"]) . " - Profile";
require_once __DIR__ . "/../../includes/header.php"; // Fixed header include

?>

<div class="container mt-5">
    <div class="flex" style="gap: 30px; flex-wrap: wrap;">
        <!-- Left Sidebar (Profile Summary) -->
        <div style="flex: 1; min-width: 280px;">
            <div class="card text-center">
                <div class="card-content">
                    <img src="<?php echo htmlspecialchars(get_profile_picture_url($user["profile_picture"])); ?>" alt="<?php echo htmlspecialchars($user["first_name"]); ?>" style="width: 150px; height: 150px; border-radius: 50%; margin: 0 auto 20px; object-fit: cover;">
                    <h2 class="mb-1"><?php echo htmlspecialchars($user["first_name"] . " " . $user["last_name"]); ?></h2>

                    <?php if ($is_freelancer && !empty($user["freelancer_headline"])): ?>
                        <p class="text-primary mb-3" style="font-size: 1.1rem;"><?php echo htmlspecialchars($user["freelancer_headline"]); ?></p>
                    <?php elseif ($is_client && !empty($user["company_name"])): ?>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($user["company_name"]); ?></p>
                    <?php endif; ?>

                    <?php if ($is_freelancer): ?>
                        <div class="mb-3">
                            <span style="color: #ffc107; font-size: 1.2rem;">★</span>
                            <strong><?php echo number_format($average_rating, 1); ?></strong>
                            <span class="text-muted">(<?php echo $review_count; ?> reviews)</span>
                        </div>
                        <?php if (!empty($user["hourly_rate"])): ?>
                            <p><strong>Hourly Rate:</strong> <?php echo format_currency($user["hourly_rate"]); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p class="text-muted">Member since: <?php echo date("M Y", strtotime($user["registration_date"])); ?></p>

                    <?php if ($is_client && !empty($user["client_website"])): ?>
                        <p><a href="<?php echo htmlspecialchars(add_http_prefix($user["client_website"])); ?>" target="_blank" rel="noopener noreferrer">Visit Website</a></p>
                    <?php endif; ?>

                    <!-- Add Contact Button (if viewer is logged in and not the profile owner) -->
                    <?php if (is_logged_in() && get_current_user_id() != $user_id): ?>
                        <a href="/pages/messaging/conversation.php?recipient_id=<?php echo $user_id; ?>" class="btn mt-3">Contact <?php echo htmlspecialchars($user["first_name"]); ?></a>
                    <?php endif; ?>

                    <!-- Add Edit Profile Button (if viewer is the profile owner) -->
                    <?php if (is_logged_in() && get_current_user_id() == $user_id): ?>
                        <a href="<?php echo $baseUrl; ?>/pages/profile/edit.php" class="btn btn-secondary mt-3">Edit Profile</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Content Area (Details, Reviews, Projects) -->
        <div style="flex: 2.5; min-width: 300px;">
            <!-- About Section -->
            <div class="card mb-4">
                <div class="card-content">
                    <h3>About <?php echo htmlspecialchars($user["first_name"]); ?></h3>
                    <?php
                    $bio = $is_freelancer ? $user["freelancer_bio"] : ($is_client ? $user["client_bio"] : null);
                    if (!empty($bio)):
                        ?>
                        <p><?php echo nl2br(htmlspecialchars($bio)); ?></p>
                    <?php else: ?>
                        <p class="text-muted">No bio provided.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($is_freelancer): ?>
            <!-- Skills Section -->
            <div class="card mb-4">
                <div class="card-content">
                    <h3>Skills</h3>
                    <?php if (!empty($skills)): ?>
                        <div class="flex gap-10 flex-wrap tags">
                            <?php foreach ($skills as $skill): ?>
                                <span class="tag"><?php echo htmlspecialchars($skill); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No skills listed.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews Section (Reviews Received) -->
            <div class="card mb-4">
                <div class="card-content">
                    <h3>Reviews Received (<?php echo $review_count; ?>)</h3>
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $review): ?>
                        <div style="border-bottom: 1px solid #eee; padding-bottom: 1rem; margin-bottom: 1rem;">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($review["reviewer_first_name"] . " " . $review["reviewer_last_name"]); ?></strong>
                                    <span class="text-muted ml-2"><?php echo time_ago($review["submission_date"]); ?></span>
                                </div>
                                <div>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span style="color: <?php echo $i <= $review["rating"] ? '#ffc107' : '#e0e0e0'; ?>;">★</span>
                                    <?php endfor; ?>
                                    (<?php echo number_format($review["rating"], 1); ?>)
                                </div>
                            </div>
                            <p><?php echo nl2br(htmlspecialchars($review["comment"])); ?></p>
                        </div>
                        <?php endforeach; ?>
                        <!-- Add pagination for reviews if needed -->
                    <?php else: ?>
                        <p class="text-muted">No reviews received yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($is_client): ?>
            <!-- Projects Posted Section -->
            <div class="card mb-4">
                <div class="card-content">
                    <h3>Recent Projects Posted</h3>
                    <?php if (!empty($projects_posted)): ?>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach ($projects_posted as $project): ?>
                            <li style="border-bottom: 1px solid #eee; padding: 0.75rem 0; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <a href="<?php echo $baseUrl; ?>/pages/projects/view.php?id=<?php echo $project["project_id"]; ?>"><?php echo htmlspecialchars($project["title"]); ?></a>
                                    <small class="text-muted d-block">Posted <?php echo time_ago($project["creation_date"]); ?></small>
                                </div>
                                <span class="badge <?php echo $project["status"] === 'open' ? 'badge-success' : 'badge-secondary'; ?>"><?php echo ucfirst($project["status"]); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <!-- Add link to view all client projects if needed -->
                    <?php else: ?>
                        <p class="text-muted">No projects posted yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews Given Section -->
            <div class="card mb-4">
                <div class="card-content">
                    <h3>Reviews Given (<?php echo $review_count; ?>)</h3>
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $review): ?>
                        <div style="border-bottom: 1px solid #eee; padding-bottom: 1rem; margin-bottom: 1rem;">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    Reviewed <a href="<?php echo $baseUrl; ?>/pages/profile/view.php?id=<?php echo $review["reviewee_id"]; ?>"><strong><?php echo htmlspecialchars($review["freelancer_first_name"] . " " . $review["freelancer_last_name"]); ?></strong></a>
                                    <span class="text-muted ml-2"><?php echo time_ago($review["submission_date"]); ?></span>
                                </div>
                                <div>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span style="color: <?php echo $i <= $review["rating"] ? '#ffc107' : '#e0e0e0'; ?>;">★</span>
                                    <?php endfor; ?>
                                    (<?php echo number_format($review["rating"], 1); ?>)
                                </div>
                            </div>
                            <p><?php echo nl2br(htmlspecialchars($review["comment"])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No reviews given yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . "/../../includes/footer.php"; // Fixed footer include
?>

