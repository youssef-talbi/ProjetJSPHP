<?php
// Search Results Page
require_once __DIR__ . "/../bootstrap.php";

// Get search query
$query = isset($_GET["q"]) ? trim(filter_var($_GET["q"], FILTER_SANITIZE_STRING)) : null;

// Database connection
$db = getDbConnection();
if (!$db) {
    $error_message = "Database connection error.";
}

$projects = [];
$freelancers = [];

if ($query && $db) {
    try {
        $search_param = "%" . $query . "%";

        // Search Projects
        $project_sql = "SELECT p.project_id, p.title, p.description, p.status, p.creation_date, 
                               c.category_name, 
                               u.first_name as client_first_name, u.last_name as client_last_name
                        FROM projects p
                        JOIN categories c ON p.category_id = c.category_id
                        JOIN users u ON p.client_id = u.user_id
                        WHERE (p.title LIKE :query OR p.description LIKE :query) 
                        AND p.status = "open" -- Only show open projects in general search?
                        ORDER BY p.creation_date DESC
                        LIMIT 10"; // Limit results for performance
        $project_stmt = $db->prepare($project_sql);
        $project_stmt->bindParam(":query", $search_param);
        $project_stmt->execute();
        $projects = $project_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Search Freelancers
        $freelancer_sql = "SELECT u.user_id, u.first_name, u.last_name, u.profile_picture, 
                                  fp.headline, fp.summary as bio, 
                                  COALESCE(AVG(r.rating), 0) as average_rating, 
                                  COUNT(DISTINCT r.review_id) as review_count
                           FROM users u
                           JOIN freelancer_profiles fp ON u.user_id = fp.user_id
                           LEFT JOIN reviews r ON fp.user_id = r.reviewee_id
                           WHERE u.user_type = "freelancer" AND u.account_status = "active"
                           AND (u.first_name LIKE :query 
                                OR u.last_name LIKE :query 
                                OR u.username LIKE :query 
                                OR fp.headline LIKE :query 
                                OR fp.summary LIKE :query
                                OR u.user_id IN (SELECT us.user_id FROM user_skills us JOIN skills s ON us.skill_id = s.skill_id WHERE s.skill_name LIKE :query)
                               )
                           GROUP BY u.user_id, u.first_name, u.last_name, u.profile_picture, fp.headline, fp.summary
                           ORDER BY average_rating DESC, review_count DESC
                           LIMIT 10"; // Limit results
        $freelancer_stmt = $db->prepare($freelancer_sql);
        $freelancer_stmt->bindParam(":query", $search_param);
        $freelancer_stmt->execute();
        $freelancers = $freelancer_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        $error_message = "An error occurred while searching.";
    }
}

// Include Header
$page_title = "Search Results" . ($query ? " for \"" . htmlspecialchars($query) . "\"" : "");
require_once __DIR__ . "/../includes/header.php";

?>

<div class="container mt-4">
    <h2><?php echo $page_title; ?></h2>

    <?php display_message(); ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (!$query): ?>
        <p>Please enter a search term in the header search bar.</p>
    <?php else: ?>
        
        <!-- Project Results -->
        <section class="mb-5">
            <h3>Matching Projects (<?php echo count($projects); ?>)</h3>
            <?php if (empty($projects)): ?>
                <p class="text-muted">No open projects found matching your search.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($projects as $project): ?>
                        <a href="/pages/projects/view.php?id=<?php echo $project["project_id"]; ?>" class="list-group-item list-group-item-action flex-column align-items-start mb-2 card">
                            <div class="card-content">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1 card-title"><?php echo highlight_search_term(htmlspecialchars($project["title"]), $query); ?></h5>
                                    <small class="text-muted"><?php echo time_ago($project["creation_date"]); ?></small>
                                </div>
                                <p class="mb-1 card-text"><?php echo highlight_search_term(htmlspecialchars(truncate_text($project["description"], 150)), $query); ?></p>
                                <small class="text-muted">Category: <?php echo htmlspecialchars($project["category_name"]); ?> | By: <?php echo htmlspecialchars($project["client_first_name"] . " " . $project["client_last_name"]); ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($projects) >= 10): ?>
                    <p class="mt-2"><small>Showing the first 10 matching projects. Consider refining your search.</small></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- Freelancer Results -->
        <section>
            <h3>Matching Freelancers (<?php echo count($freelancers); ?>)</h3>
            <?php if (empty($freelancers)): ?>
                <p class="text-muted">No active freelancers found matching your search.</p>
            <?php else: ?>
                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                    <?php foreach ($freelancers as $freelancer): ?>
                        <div class="card">
                            <div class="card-content text-center">
                                <img src="<?php echo htmlspecialchars(get_profile_picture_url($freelancer["profile_picture"])); ?>" alt="<?php echo htmlspecialchars($freelancer["first_name"]); ?>" style="width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 15px; object-fit: cover;">
                                <h4 class="card-title mb-1"><a href="/pages/profile/view.php?id=<?php echo $freelancer["user_id"]; ?>"><?php echo highlight_search_term(htmlspecialchars($freelancer["first_name"] . " " . $freelancer["last_name"]), $query); ?></a></h4>
                                <p class="text-primary mb-2"><?php echo highlight_search_term(htmlspecialchars($freelancer["headline"] ?? "Freelancer"), $query); ?></p>
                                <p class="card-text text-muted mb-3"><?php echo highlight_search_term(htmlspecialchars(truncate_text($freelancer["bio"] ?? "", 100)), $query); ?></p>
                            </div>
                            <div class="card-footer">
                                <div>
                                    <span style="color: #ffc107;">★</span>
                                    <?php echo number_format($freelancer["average_rating"], 1); ?>
                                    <span class="text-muted">(<?php echo $freelancer["review_count"]; ?> reviews)</span>
                                </div>
                                <a href="/pages/profile/view.php?id=<?php echo $freelancer["user_id"]; ?>" class="btn btn-sm">View Profile</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                 <?php if (count($freelancers) >= 10): ?>
                    <p class="mt-2"><small>Showing the first 10 matching freelancers. Consider refining your search.</small></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

    <?php endif; ?>

</div>

<?php
// Include Footer
require_once __DIR__ . "/../includes/footer.php";

// Helper function to highlight search term
function highlight_search_term($text, $term) {
    if (empty($term)) {
        return $text;
    }
    // Use case-insensitive replacement
    return preg_replace("/(" . preg_quote($term, "/") . ")/i", 
                        "<mark class=\"search-highlight\">$1</mark>", 
                        $text);
}

?>

