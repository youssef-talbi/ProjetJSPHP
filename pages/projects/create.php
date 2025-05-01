<?php
// Include bootstrap and check user permissions
require_once __DIR__ ."/../../bootstrap.php";

// Check if user is logged in and is a client
if (!is_logged_in() || get_user_type() !== 'client') {
    // Redirect to login page or show an error
    redirect('/pages/auth/login.php?error=unauthorized');
    exit;
}

// Database connection
$db = getDbConnection();
if (!$db) {
    // Handle DB connection error - maybe show a generic error page
    $error_message = "Database connection error.";
} else {
    // Fetch categories from database
    try {
        $category_stmt = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
        $categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching categories: " . $e->getMessage());
        $categories = [];
        $error_message = "Error fetching categories.";
    }
}

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Generate CSRF token
$token = generate_form_token('create_project_token');

?>

<div class="container">
    <div class="form-container">
        <h2 class="form-title">Post a New Project</h2>

        <?php display_message(); // Display any session messages (e.g., from previous errors) ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="create_process.php" method="post" class="validate-form" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?php echo $token; ?>">

            <div class="form-group">
                <label for="title">Project Title</label>
                <input type="text" id="title" name="title" required placeholder="e.g., Build a Responsive E-commerce Website">
            </div>

            <div class="form-group">
                <label for="description">Project Description</label>
                <textarea id="description" name="description" rows="6" required placeholder="Describe your project in detail. What are the goals, requirements, and deliverables?"></textarea>
            </div>

            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required <?php echo empty($categories) ? 'disabled' : ''; ?>>
                    <option value="">Select a category</option>
                    <?php
                    if (!empty($categories)) {
                        foreach ($categories as $category) {
                            echo "<option value=\"" . htmlspecialchars($category['category_id']) . "\">" . htmlspecialchars($category['category_name']) . "</option>";
                        }
                    }
                    ?>
                </select>
                <?php if (empty($categories) && !isset($error_message)): ?>
                    <div class="hint text-danger">Could not load categories.</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="skills">Required Skills (comma-separated)</label>
                <input type="text" id="skills" name="skills" placeholder="e.g., PHP, JavaScript, HTML, CSS, WordPress">
                <div class="hint">Enter the skills needed for this project.</div>
            </div>

            <div class="form-group">
                <label for="project_type">Project Type</label>
                <select id="project_type" name="project_type" required>
                    <option value="fixed">Fixed Price</option>
                    <option value="hourly">Hourly Rate</option>
                </select>
            </div>

            <div class="form-group">
                <label>Budget</label>
                <div class="flex gap-10">
                    <input type="number" id="budget_min" name="budget_min" placeholder="Min ($)" step="0.01" style="flex: 1;">
                    <input type="number" id="budget_max" name="budget_max" placeholder="Max ($)" step="0.01" style="flex: 1;">
                </div>
                <div class="hint">Enter a budget range or leave blank if unsure.</div>
            </div>

            <div class="form-group">
                <label for="deadline">Deadline (Optional)</label>
                <input type="date" id="deadline" name="deadline">
            </div>

            <div class="form-group">
                <label for="attachments">Attachments (Optional)</label>
                <input type="file" id="attachments" name="attachments[]" multiple>
                <div class="hint">Upload relevant documents (max 5MB each). Allowed types: jpg, png, pdf, doc, docx.</div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn" <?php echo empty($categories) ? 'disabled' : ''; ?>>Post Project</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>

