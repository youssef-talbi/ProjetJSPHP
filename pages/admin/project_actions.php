<?php
// Process Admin Project Actions (Close/Delete)
require_once __DIR__ . "/../../bootstrap.php";

// Check if admin is logged in
if (!is_admin_logged_in()) {
    redirect("/pages/admin/admin_auth.php?error=login_required");
    exit;
}

// Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/pages/admin/manage_projects.php?error=invalid_request");
    exit;
}

// Validate CSRF token
if (!validate_form_token("admin_project_action_token")) {
    redirect("/pages/admin/manage_projects.php?error=invalid_token");
    exit;
}

// Get action and project ID
$action = sanitize_input($_POST["action"] ?? "");
$project_id = filter_input(INPUT_POST, "project_id", FILTER_VALIDATE_INT);

if (!$project_id || !in_array($action, ["close", "delete"])) {
    redirect("/pages/admin/manage_projects.php?error=invalid_action_or_project");
    exit;
}

// Database connection
$db = getDbConnection();
if (!$db) {
    redirect("/pages/admin/manage_projects.php?error=db_error");
    exit;
}

try {
    if ($action === "close") {
        // Update project status to "closed"
        $stmt = $db->prepare("UPDATE projects SET status = :new_status WHERE project_id = :project_id");
        $new_status = "closed";
        $stmt->bindParam(":new_status", $new_status);
        $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            redirect("/pages/admin/manage_projects.php?message=project_closed");
        } else {
            redirect("/pages/admin/manage_projects.php?error=project_not_found_or_no_change");
        }
    } elseif ($action === "delete") {
        // Permanently delete the project and related data (use with extreme caution!)
        $db->beginTransaction();

        // Delete related data first (proposals, attachments, skills)
        $db->prepare("DELETE FROM proposals WHERE project_id = :project_id")->execute([":project_id" => $project_id]);
        $db->prepare("DELETE FROM project_attachments WHERE project_id = :project_id")->execute([":project_id" => $project_id]);
        $db->prepare("DELETE FROM project_skills WHERE project_id = :project_id")->execute([":project_id" => $project_id]);
        // Add deletion for contracts, reviews, etc. if applicable

        // Delete the project itself
        $stmt = $db->prepare("DELETE FROM projects WHERE project_id = :project_id");
        $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Optionally delete attachment files from the server here
            $db->commit();
            redirect("/pages/admin/manage_projects.php?message=project_deleted");
        } else {
            $db->rollBack();
            redirect("/pages/admin/manage_projects.php?error=project_not_found_for_delete");
        }
    }
    exit;

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    // Log error
    error_log("Admin project action error: " . $e->getMessage());

    // Redirect back with error
    redirect("/pages/admin/manage_projects.php?error=db_error");
    exit;
}
?>

