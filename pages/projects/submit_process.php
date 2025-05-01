<?php
// Include bootstrap and check login
require_once __DIR__ . "/../../bootstrap.php";

// Check if user is a freelancer
if (!is_logged_in() || get_user_type() !== 'freelancer') {
    redirect('/pages/auth/login.php?error=unauthorized');
    exit;
}

// Check POST method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect('/pages/projects/list.php?error=invalid_request');
    exit;
}

// Validate CSRF token
if (!validate_form_token('submit_proposal_token')) {
    redirect('/pages/projects/list.php?error=invalid_token');
    exit;
}

// Get and sanitize input
$project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
$freelancer_id = get_current_user_id();
$cover_letter = sanitize_input($_POST['cover_letter'] ?? '');
$price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
$estimated_days = filter_input(INPUT_POST, 'estimated_completion_days', FILTER_VALIDATE_INT);

// Basic validation
if (!$project_id || !$cover_letter || !$price || !$estimated_days || $price <= 0 || $estimated_days <= 0) {
    redirect("/pages/proposals/submit.php?project_id=$project_id&error=invalid_input");
    exit;
}

$db = getDbConnection();

// Check if project exists and is open
$stmt = $db->prepare("SELECT status FROM projects WHERE project_id = :project_id");
$stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
$stmt->execute();
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project || $project['status'] !== 'open') {
    redirect('/pages/projects/list.php?error=project_closed');
    exit;
}

// Check if already submitted
$stmt = $db->prepare("SELECT proposal_id FROM proposals WHERE project_id = :project_id AND freelancer_id = :freelancer_id");
$stmt->bindParam(':project_id', $project_id);
$stmt->bindParam(':freelancer_id', $freelancer_id);
$stmt->execute();

if ($stmt->fetch()) {
    redirect('/pages/projects/view.php?id=' . $project_id . '&message=already_applied');
    exit;
}

// Insert the proposal
try {
    $stmt = $db->prepare("INSERT INTO proposals (project_id, freelancer_id, cover_letter, price, estimated_completion_days, submission_date)
                          VALUES (:project_id, :freelancer_id, :cover_letter, :price, :estimated_days, NOW())");

    $stmt->bindParam(':project_id', $project_id);
    $stmt->bindParam(':freelancer_id', $freelancer_id);
    $stmt->bindParam(':cover_letter', $cover_letter);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':estimated_days', $estimated_days);

    $stmt->execute();

    redirect("/pages/projects/view.php?id=$project_id&message=proposal_submitted");
    exit;

} catch (PDOException $e) {
    error_log("Proposal submission error: " . $e->getMessage());
    redirect("/pages/projects/view.php?id=$project_id&error=db_error");
    exit;
}
