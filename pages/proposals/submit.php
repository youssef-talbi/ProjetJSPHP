<?php
// Include bootstrap and check user permissions
require_once __DIR__ . "/../../bootstrap.php";
$baseUrl="/improved";
// Check if user is logged in and is a freelancer
if (!is_logged_in() || get_user_type() !== 'freelancer') {
    redirect("improved/pages/auth/login.php?error=login_required&redirect=" . urlencode($_SERVER["REQUEST_URI"]));
    exit;
}

// Get project ID from query string
$project_id = isset($_GET["project_id"]) ? filter_input(INPUT_GET, "project_id", FILTER_VALIDATE_INT) : null;

if (!$project_id) {
    redirect("improved/pages/projects/list.php?error=invalid_project_id");
    exit;
}

// Database connection
$db = getDbConnection();
if (!$db) {
    $error_message = "Database connection error.";
} else {
    try {
        // Fetch project details
        $stmt = $db->prepare("SELECT project_id, title, status FROM projects WHERE project_id = :project_id");
        $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
        $stmt->execute();
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            redirect("improved/pages/projects/list.php?error=project_not_found");
            exit;
        }

        // Check if project is open for proposals
        if ($project["status"] !== 'open') {
            redirect("improved/pages/projects/view.php?id=" . $project_id . "&error=project_not_open");
            exit;
        }

        // Check if freelancer has already submitted a proposal
        $freelancer_id = get_current_user_id();
        $check_stmt = $db->prepare("SELECT proposal_id FROM proposals WHERE project_id = :project_id AND freelancer_id = :freelancer_id");
        $check_stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
        $check_stmt->bindParam(":freelancer_id", $freelancer_id, PDO::PARAM_INT);
        $check_stmt->execute();
        if ($check_stmt->fetch()) {
            redirect("improved/pages/projects/view.php?id=" . $project_id . "&error=already_submitted");
            exit;
        }

    } catch (PDOException $e) {
        error_log("Error fetching project data for proposal: " . $e->getMessage());
        $error_message = "Error loading project details.";
        $project = null; // Ensure project is null on error
    }
}

// Include header
$page_title = "Submit Proposal for: " . ($project ? htmlspecialchars($project["title"]) : "Project");
require_once __DIR__ . "/../../includes/header.php";

// Generate CSRF token
try {
    $token = generate_form_token('submit_proposal_token');
} catch (\Random\RandomException $e) {

}


?>

<div class="container">
    <div class="form-container">
        <?php if ($project): ?>
            <h2 class="form-title">Submit Proposal for: <?php echo htmlspecialchars($project["title"]); ?></h2>
        <?php else: ?>
            <h2 class="form-title">Submit Proposal</h2>
        <?php endif; ?>

        <?php display_message(); // Display any session messages ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if ($project && !isset($error_message)): ?>
            <p class="mb-4">You are submitting a proposal for the project <a href="<?php echo $baseUrl; ?>/pages/projects/view.php?id=<?php echo $project_id; ?>"><?php echo htmlspecialchars($project["title"]); ?></a>.</p>

            <form action="<?php echo $baseUrl; ?>/pages/proposals/submit_process.php" method="post" class="validate-form">
                <input type="hidden" name="token" value="<?php echo $token; ?>">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

                <div class="form-group">
                    <label for="cover_letter">Cover Letter</label>
                    <textarea id="cover_letter" name="cover_letter" rows="8" required placeholder="Introduce yourself and explain why you are a good fit for this project. Highlight relevant skills and experience."></textarea>
                </div>

                <div class="form-group">
                    <label for="price">Your Bid Amount ($)</label>
                    <input type="number" id="price" name="price" required placeholder="e.g., 500" step="0.01" min="0">
                    <div class="hint">Enter the total amount you want to bid for this project.</div>
                </div>

                <div class="form-group">
                    <label for="estimated_completion_days">Estimated Completion Time (Days)</label>
                    <input type="number" id="estimated_completion_days" name="estimated_completion_days" required placeholder="e.g., 14" min="1">
                    <div class="hint">Estimate how many days it will take you to complete the project.</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Submit Proposal</button>
                    <a href="/pages/projects/view.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <p>Could not load project details. Please go back to the <a href="<?php echo $baseUrl; ?>/pages/projects/list.php">project list</a> and try again.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . "/../../includes/footer.php";
?>

