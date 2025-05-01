<?php
// Include bootstrap
require_once __DIR__ . "/../../bootstrap.php";

// Include header
require_once __DIR__ . "/../../includes/header.php";

// Database connection
$db = getDbConnection();
if (!$db) {
    echo "<div class=\"container\"><div class=\"alert alert-danger\">Database connection error.</div></div>";
    require_once __DIR__ . "/../../includes/footer.php"; // Fixed footer include
    exit;
}

// --- Filtering & Sorting Logic ---
$category_filter = isset($_GET["category"]) ? filter_var($_GET["category"], FILTER_SANITIZE_STRING) : null;
$skills_filter = isset($_GET["skills"]) && is_array($_GET["skills"]) ? $_GET["skills"] : [];
$rating_min_filter = isset($_GET["rating_min"]) ? filter_input(INPUT_GET, "rating_min", FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 0, "max_range" => 5]]) : null;
$sort_order = isset($_GET["sort"]) ? filter_var($_GET["sort"], FILTER_SANITIZE_STRING) : "rating";

// --- Pagination Logic ---
$page = isset($_GET["page"]) ? filter_input(INPUT_GET, "page", FILTER_VALIDATE_INT, ["options" => ["default" => 1, "min_range" => 1]]) : 1;
$limit = 12; // Freelancers per page
$offset = ($page - 1) * $limit;

// --- Build SQL Query ---
// Select freelancers with their profile info and average rating
$sql = "SELECT u.user_id, u.first_name, u.last_name, u.headline, fp.headline, fp.summary as bio, u.profile_picture, 
               COALESCE(AVG(r.rating), 0) as average_rating, 
               COUNT(DISTINCT r.review_id) as review_count
        FROM users u
        JOIN freelancer_profiles fp ON u.user_id = fp.user_id
        LEFT JOIN reviews r ON fp.user_id = r.reviewee_id -- Assuming reviewee_id is the freelancer
        WHERE u.user_type = :user_type AND u.account_status = :status";
$params = [":user_type" => "freelancer", ":status" => "active"];

// Add filters
if ($category_filter) {
    $sql .= " AND u.user_id IN (SELECT us.user_id FROM user_skills us JOIN skills s ON us.skill_id = s.skill_id WHERE s.category_id = :category_id)";
    $params[":category_id"] = $category_filter;
}

if (!empty($skills_filter)) {
    $placeholders = implode(",", array_fill(0, count($skills_filter), "?"));
    $sql .= " AND u.user_id IN (SELECT us.user_id FROM user_skills us JOIN skills s ON us.skill_id = s.skill_id WHERE s.skill_name IN ($placeholders))";
    foreach ($skills_filter as $skill_name) {
        $params[] = $skill_name;
    }
}

// Group by freelancer for rating calculation BEFORE filtering by rating
$sql .= " GROUP BY u.user_id, u.first_name, u.last_name, u.headline, fp.headline, fp.summary, u.profile_picture";

// Add rating filter (HAVING clause after GROUP BY)
if ($rating_min_filter !== null && $rating_min_filter !== false) {
    $sql .= " HAVING average_rating >= :rating_min";
    $params[":rating_min"] = $rating_min_filter;
}

// --- Count Total Freelancers for Pagination ---
$count_sql = "SELECT COUNT(*) FROM (
    SELECT u.user_id, COALESCE(AVG(r.rating), 0) as average_rating
    FROM users u
    JOIN freelancer_profiles fp ON u.user_id = fp.user_id
    LEFT JOIN reviews r ON fp.user_id = r.reviewee_id
    WHERE u.user_type = :user_type AND u.account_status = :status";

$count_params = [":user_type" => "freelancer", ":status" => "active"];

if ($category_filter) {
    $count_sql .= " AND u.user_id IN (SELECT us.user_id FROM user_skills us JOIN skills s ON us.skill_id = s.skill_id WHERE s.category_id = :category_id)";
    $count_params[":category_id"] = $category_filter;
}
if (!empty($skills_filter)) {
    $placeholders = implode(",", array_fill(0, count($skills_filter), "?"));
    $count_sql .= " AND u.user_id IN (SELECT us.user_id FROM user_skills us JOIN skills s ON us.skill_id = s.skill_id WHERE s.skill_name IN ($placeholders))";
    foreach ($skills_filter as $skill_name) {
        $count_params[] = $skill_name;
    }
}

$count_sql .= " GROUP BY u.user_id";

if ($rating_min_filter !== null && $rating_min_filter !== false) {
    $count_sql .= " HAVING average_rating >= :rating_min";
    $count_params[":rating_min"] = $rating_min_filter;
}

$count_sql .= ") AS filtered_freelancers";

$count_stmt = $db->prepare($count_sql);

// Prepare parameters for count query execution
$execute_params = [];
$param_index = 1;
foreach ($count_params as $key => $val) {
    if (is_int($key)) {
        $execute_params[$param_index++] = $val;
    } else {
        $execute_params[$key] = $val;
    }
}

$count_stmt->execute($execute_params);
$total_freelancers = $count_stmt->fetchColumn();
$total_pages = ceil($total_freelancers / $limit);

// --- Add Sorting ---
switch ($sort_order) {
    case "name_asc":
        $sql .= " ORDER BY u.first_name ASC, u.last_name ASC";
        break;
    case "name_desc":
        $sql .= " ORDER BY u.first_name DESC, u.last_name DESC";
        break;
    case "newest":
        $sql .= " ORDER BY u.registration_date DESC";
        break;
    case "rating":
    default:
        $sql .= " ORDER BY average_rating DESC, review_count DESC";
        break;
}

// --- Add Pagination Limit ---
$sql .= " LIMIT :limit OFFSET :offset";
$params[":limit"] = $limit;
$params[":offset"] = $offset;

// --- Fetch Freelancers ---
$stmt = $db->prepare($sql);

// Bind parameters for main query
$execute_params_main = [];
$param_index_main = 1;
foreach ($params as $key => &$val) {
    if (is_int($key)) {
        $execute_params_main[$param_index_main++] = $val;
    } elseif ($key === ":limit" || $key === ":offset" || $key === ":category_id") {
        $execute_params_main[$key] = (int)$val;
    } elseif ($key === ":rating_min") {
        $execute_params_main[$key] = (float)$val;
    } else {
        $execute_params_main[$key] = $val;
    }
}
unset($val);

$stmt->execute($execute_params_main);
$freelancers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories and skills for filters
$category_stmt = $db->query("SELECT category_id, category_name FROM categories WHERE parent_category_id IS NULL ORDER BY category_name");
$filter_categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
$skill_stmt = $db->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name LIMIT 100"); // Increased limit for skills filter
$filter_skills = $skill_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container">
    <div class="flex" style="gap: 30px; flex-wrap: wrap; margin-top: 2rem;">
        <!-- Sidebar -->
        <div style="flex: 1; min-width: 250px;">
            <div class="card">
                <div class="card-content">
                    <h3>Filter Freelancers</h3>
                    <form action="" method="get">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="" <?php echo !$category_filter ? 'selected' : ''; ?>>All Categories</option>
                                <?php foreach ($filter_categories as $cat): ?>
                                    <option value="<?php echo $cat["category_id"]; ?>" <?php echo $category_filter == $cat["category_id"] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat["category_name"]); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Skills</label>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #eee; padding: 10px;">
                                <?php foreach ($filter_skills as $skill): ?>
                                <div class="form-check">
                                    <input type="checkbox" id="skill_<?php echo $skill["skill_id"]; ?>" name="skills[]" value="<?php echo htmlspecialchars($skill["skill_name"]); ?>" <?php echo in_array($skill["skill_name"], $skills_filter) ? 'checked' : ''; ?>>
                                    <label for="skill_<?php echo $skill["skill_id"]; ?>"><?php echo htmlspecialchars($skill["skill_name"]); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="rating_min">Minimum Rating</label>
                            <select id="rating_min" name="rating_min">
                                <option value="" <?php echo $rating_min_filter === null ? 'selected' : ''; ?>>Any Rating</option>
                                <option value="4.5" <?php echo $rating_min_filter === 4.5 ? 'selected' : ''; ?>>4.5 Stars & Up</option>
                                <option value="4.0" <?php echo $rating_min_filter === 4.0 ? 'selected' : ''; ?>>4.0 Stars & Up</option>
                                <option value="3.0" <?php echo $rating_min_filter === 3.0 ? 'selected' : ''; ?>>3.0 Stars & Up</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-sm" style="width: 100%;">Apply Filters</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div style="flex: 3; min-width: 300px;">
            <div class="flex justify-between items-center mb-4">
                <h2>Browse Freelancers (<?php echo $total_freelancers; ?> found)</h2>
                <div>
                    <form action="" method="get" id="sortForm" style="display: inline;">
                        <!-- Include existing filters -->
                        <?php foreach ($_GET as $key => $value): if ($key != 'sort' && $key != 'page'): ?>
                            <?php if (is_array($value)): foreach ($value as $sub_value): ?>
                                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>[]" value="<?php echo htmlspecialchars($sub_value); ?>">
                            <?php endforeach; else: ?>
                                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                            <?php endif; ?>
                        <?php endif; endforeach; ?>
                        <label for="sort" style="margin-right: 5px;">Sort by:</label>
                        <select id="sort" name="sort" onchange="document.getElementById('sortForm').submit()">
                            <option value="rating" <?php echo $sort_order === 'rating' ? 'selected' : ''; ?>>Rating</option>
                            <option value="newest" <?php echo $sort_order === 'newest' ? 'selected' : ''; ?>>Newest</option>
                            <option value="name_asc" <?php echo $sort_order === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="name_desc" <?php echo $sort_order === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        </select>
                    </form>
                </div>
            </div>

            <?php display_message(); ?>

            <!-- Freelancer Listings -->
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                <?php if (empty($freelancers)): ?>
                    <div class="card mb-4" style="grid-column: 1 / -1;">
                        <div class="card-content text-center">
                            <p>No freelancers found matching your criteria.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($freelancers as $freelancer): ?>
                        <div class="card">
                            <div class="card-content text-center">
                                <img src="<?php echo htmlspecialchars(get_profile_picture_url($freelancer["profile_picture"])); ?>" alt="<?php echo htmlspecialchars($freelancer["first_name"]); ?>" style="width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 15px; object-fit: cover;">
                                <h3 class="card-title mb-1"><a href="/pages/profile/view.php?id=<?php echo $freelancer["user_id"]; ?>"><?php echo htmlspecialchars($freelancer["first_name"] . " " . $freelancer["last_name"]); ?></a></h3>
                                <p class="text-primary mb-2"><?php echo htmlspecialchars($freelancer["headline"] ?? "Freelancer"); ?></p>
                                <p class="card-text text-muted mb-3"><?php echo htmlspecialchars(truncate_text($freelancer["bio"] ?? "", 100)); ?></p>
                                <div class="mb-3 tags">
                                    <?php
                                    // Fetch skills for this freelancer
                                    $f_skill_stmt = $db->prepare("SELECT s.skill_name FROM skills s JOIN user_skills us ON s.skill_id = us.skill_id WHERE us.user_id = :user_id LIMIT 3");
                                    $f_skill_stmt->bindParam(":user_id", $freelancer["user_id"], PDO::PARAM_INT);
                                    $f_skill_stmt->execute();
                                    $freelancer_skills = $f_skill_stmt->fetchAll(PDO::FETCH_COLUMN);
                                    foreach ($freelancer_skills as $skill_name) {
                                        echo '<span class="tag">' . htmlspecialchars($skill_name) . '</span>'; // Fixed echo statement
                                    }
                                    ?>
                                </div>
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
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-5">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ["page" => $page - 1])); ?>" class="btn btn-sm">&laquo; Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ["page" => $i])); ?>" class="btn btn-sm <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ["page" => $page + 1])); ?>" class="btn btn-sm">Next &raquo;</a> <!-- Fixed pagination link -->
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . "/../../includes/footer.php";
?>

