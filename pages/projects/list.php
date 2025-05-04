<?php
// Include bootstrap
require_once __DIR__ . "/../../bootstrap.php";
$baseUrl="/improved";
// Include header
require_once __DIR__ . "/../../includes/header.php";

// Database connection
$db = getDbConnection();
if (!$db) {
    echo "<div class=\"container\"><div class=\"alert alert-danger\">Database connection error.</div></div>";
    require_once __DIR__ . "/../../includes/footer.php";
    exit;
}

// --- Filtering & Sorting Logic ---
$category_filter = isset($_GET["category"]) ? filter_input(INPUT_GET, "category", FILTER_VALIDATE_INT) : null;
$skills_filter = isset($_GET["skills"]) && is_array($_GET["skills"]) ? $_GET["skills"] : [];
$budget_min_filter = isset($_GET["budget_min"]) ? filter_input(INPUT_GET, "budget_min", FILTER_VALIDATE_FLOAT) : null;
$budget_max_filter = isset($_GET["budget_max"]) ? filter_input(INPUT_GET, "budget_max", FILTER_VALIDATE_FLOAT) : null;
$project_type_filter = isset($_GET["project_type"]) ? filter_var($_GET["project_type"] ) : null;
$sort_order = isset($_GET["sort"]) ? filter_var($_GET["sort"]) : "newest";

// --- Pagination Logic ---
$page = isset($_GET["page"]) ? filter_input(INPUT_GET, "page", FILTER_VALIDATE_INT, ["options" => ["default" => 1, "min_range" => 1]]) : 1;
$limit = 10; // Projects per page
$offset = ($page - 1) * $limit;

// --- Build SQL Query ---
$sql = "SELECT p.project_id, p.title, p.description, p.budget_min, p.budget_max, p.project_type, p.creation_date, p.status, 
               c.category_name, 
               u.first_name as client_first_name, u.last_name as client_last_name, 
               (SELECT COUNT(*) FROM proposals pr WHERE pr.project_id = p.project_id) as proposal_count
        FROM projects p
        JOIN categories c ON p.category_id = c.category_id
        JOIN users u ON p.client_id = u.user_id
        WHERE p.status = :status";
$params = [":status" => "open"];

// Add filters
if ($category_filter) {
    $sql .= " AND p.category_id = :category_id";
    $params[":category_id"] = $category_filter;
}

if (!empty($skills_filter)) {
    $named_placeholders = [];
    foreach ($skills_filter as $index => $skill_name) {
        $param_name = ":skill_" . $index;
        $named_placeholders[] = $param_name;
        $params[$param_name] = $skill_name;
    }
    $placeholder_str = implode(",", $named_placeholders);
    $sql .= " AND p.project_id IN (
        SELECT ps.project_id
        FROM project_skills ps
        JOIN skills s ON ps.skill_id = s.skill_id
        WHERE s.skill_name IN ($placeholder_str)
    )";
}


// --- Count Total Projects for Pagination ---
$count_sql = "SELECT COUNT(DISTINCT p.project_id)
             FROM projects p
             JOIN categories c ON p.category_id = c.category_id
             JOIN users u ON p.client_id = u.user_id
             WHERE p.status = :status";
$count_params = [":status" => "open"];

if ($budget_min_filter !== null && $budget_min_filter !== false) {
    $count_sql .= " AND (p.budget_min >= :budget_min OR p.budget_max >= :budget_min)";
    $count_params[":budget_min"] = $budget_min_filter;
}

if ($budget_max_filter !== null && $budget_max_filter !== false) {
    $count_sql .= " AND (p.budget_max <= :budget_max OR p.budget_min <= :budget_max)";
    $count_params[":budget_max"] = $budget_max_filter;
}

if (in_array($project_type_filter, ["fixed", "hourly"])) {
    $count_sql .= " AND p.project_type = :project_type";
    $count_params[":project_type"] = $project_type_filter;
}

$count_stmt = $db->prepare($count_sql);

// Prepare parameters for count query execution
$execute_count_params = [];
$param_count_index = 1;
foreach ($count_params as $key => $val) {
    if (is_int($key)) {
        $execute_count_params[$param_count_index++] = $val;
    } else {
        $execute_count_params[$key] = $val;
    }
}

$count_stmt->execute($execute_count_params);
$total_projects = $count_stmt->fetchColumn();
$total_pages = ceil($total_projects / $limit);

// --- Add Sorting ---
$sql .= match ($sort_order) {
    "budget_high" => " ORDER BY COALESCE(p.budget_max, p.budget_min) DESC, p.creation_date DESC",
    "budget_low" => " ORDER BY COALESCE(p.budget_min, p.budget_max) ASC, p.creation_date DESC",
    default => " ORDER BY p.creation_date DESC",
};

// --- Add Pagination Limit ---
$sql .= " LIMIT :limit OFFSET :offset";
$params[":limit"] = $limit;
$params[":offset"] = $offset;



// Bind parameters for main query
$execute_params_main = [];
$param_main_index = 1;
foreach ($params as $key => &$val) {
    if (is_int($key)) {
        $execute_params_main[$param_main_index++] = $val;
    } elseif ($key === ":limit" || $key === ":offset" || $key === ":category_id") {
        $execute_params_main[$key] = (int)$val;
    } elseif ($key === ":budget_min" || $key === ":budget_max") {
        $execute_params_main[$key] = (float)$val;
    } else {
        $execute_params_main[$key] = $val;
    }
}
unset($val);
$stmt = $db->prepare($sql);

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($params as $key => $val) {
    if ($key === ":limit" || $key === ":offset") {
        $stmt->bindValue($key, (int)$val, PDO::PARAM_INT);
    } elseif ($key === ":budget_min" || $key === ":budget_max") {
        $stmt->bindValue($key, (float)$val, PDO::PARAM_STR); // MariaDB treats floats as strings
    } else {
        $stmt->bindValue( $key, $val);
    }
}


$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch categories and skills for filters
$category_stmt = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
$filter_categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
$skill_stmt = $db->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name LIMIT 100");
$filter_skills = $skill_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container">
    <div class="flex" style="gap: 30px; flex-wrap: wrap; margin-top: 2rem;">
        <!-- Sidebar -->
        <div style="flex: 1; min-width: 250px;">
            <div class="card">
                <div class="card-content">
                    <h3>Filter Projects</h3>
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
                            <label for="budget_min">Budget Range</label>
                            <div class="flex gap-10">
                                <input type="number" id="budget_min" name="budget_min" placeholder="Min $" style="flex: 1;" value="<?php echo htmlspecialchars($budget_min_filter ?? ''); ?>" step="0.01">
                                <label for="budget_max"><input type="number" id="budget_max" name="budget_max" placeholder="Max $" style="flex: 1;" value="<?php echo htmlspecialchars($budget_max_filter ?? ''); ?>" step="0.01"></label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="project_type">Project Type</label>
                            <select id="project_type" name="project_type">
                                <option value="" <?php echo !$project_type_filter ? 'selected' : ''; ?>>All Types</option>
                                <option value="fixed" <?php echo $project_type_filter === 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                                <option value="hourly" <?php echo $project_type_filter === 'hourly' ? 'selected' : ''; ?>>Hourly Rate</option>
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

                        <button type="submit" class="btn btn-sm" style="width: 100%;">Apply Filters</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div style="flex: 3; min-width: 300px;">
            <div class="flex justify-between items-center mb-4">
                <h2>Browse Projects (<?php echo $total_projects; ?> found)</h2>
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
                            <option value="newest" <?php echo $sort_order === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="budget_high" <?php echo $sort_order === 'budget_high' ? 'selected' : ''; ?>>Budget: High to Low</option>
                            <option value="budget_low" <?php echo $sort_order === 'budget_low' ? 'selected' : ''; ?>>Budget: Low to High</option>
                        </select>
                    </form>
                </div>
            </div>

            <?php display_message(); ?>

            <!-- Project Listings -->
            <div class="project-list">
                <?php if (empty($projects)): ?>
                    <div class="card mb-4">
                        <div class="card-content text-center">
                            <p>No projects found matching your criteria.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                        <div class="card mb-4">
                            <div class="card-content">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="mb-0"><a href="/pages/projects/view.php?id=<?php echo $project['project_id']; ?>"><?php echo htmlspecialchars($project['title']); ?></a></h3>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($project['category_name']); ?></span>
                                </div>
                                <p><?php echo htmlspecialchars(truncate_text($project['description'], 250)); ?></p>
                                <div class="flex justify-between items-center mt-3 text-sm text-muted">
                                    <div>
                                        <p><strong>Budget:</strong>
                                            <?php
                                            if ($project["project_type"] === 'hourly') {
                                                echo "Hourly Rate";
                                            } elseif ($project["budget_min"] && $project["budget_max"]) {
                                                echo format_currency($project["budget_min"]) . " - " . format_currency($project["budget_max"]);
                                            } elseif ($project["budget_min"]) {
                                                echo "From " . format_currency($project["budget_min"]);
                                            } elseif ($project["budget_max"]) {
                                                echo "Up to " . format_currency($project["budget_max"]);
                                            } else {
                                                echo "Not specified";
                                            }
                                            ?>
                                        </p>
                                        <p><strong>Posted:</strong> <?php echo time_ago($project['creation_date']); ?> by <?php echo htmlspecialchars($project['client_first_name']); ?></p>
                                    </div>
                                    <div>
                                        <p><strong>Proposals:</strong> <?php echo $project['proposal_count']; ?></p>
                                        <p><strong>Type:</strong> <?php echo ucfirst($project['project_type']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="flex gap-10 flex-wrap tags">
                                    <?php
                                    // Fetch skills for this project
                                    $p_skill_stmt = $db->prepare("SELECT s.skill_name FROM skills s JOIN project_skills ps ON s.skill_id = ps.skill_id WHERE ps.project_id = :project_id LIMIT 5");
                                    $p_skill_stmt->bindParam(":project_id", $project["project_id"], PDO::PARAM_INT);
                                    $p_skill_stmt->execute();
                                    $project_skills = $p_skill_stmt->fetchAll(PDO::FETCH_COLUMN);
                                    foreach ($project_skills as $skill_name) {
                                        echo '<span class="tag">' . htmlspecialchars($skill_name) . '</span>';
                                    }
                                    ?>
                                </div>
                                <a href="<?php echo $baseUrl; ?>/pages/projects/view.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm">View Details</a>
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

                    <?php
                    // Pagination links logic (simplified)
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) echo '<span class="pagination-ellipsis">...</span>';

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ["page" => $i])); ?>" class="btn btn-sm <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor;

                    if ($end_page < $total_pages) echo '<span class="pagination-ellipsis">...</span>';
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ["page" => $page + 1])); ?>" class="btn btn-sm">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>

