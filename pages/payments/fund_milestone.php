<?php
// Payments - Fund Milestone (Simulated)
require_once __DIR__ . "/../../bootstrap.php";

// Check if user is logged in and is a client
if (!is_logged_in() || !has_permission("fund_milestone")) {
    redirect("/improved/pages/auth/login.php?error=login_required");
    exit;
}

// Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/improved/?error=invalid_request"); // Redirect to dashboard or relevant page
    exit;
}

// Validate CSRF token
if (!validate_form_token("project_action_token")) {
    // Find a suitable redirect URL, maybe back to the project page if possible
    redirect("/improved/?error=invalid_token");
    exit;
}

// Get current user ID (Client)
$client_id = get_current_user_id();

// Get and validate milestone ID
$milestone_id = filter_input(INPUT_POST, "milestone_id", FILTER_VALIDATE_INT);

if (!$milestone_id) {
    redirect("/improved/?error=invalid_milestone");
    exit;
}

// Database connection
$db = getDbConnection();
if (!$db) {
    redirect("/improved/?error=db_error");
    exit;
}

$project_id = null; // To redirect back

try {
    // Begin transaction
    $db->beginTransaction();

    // 1. Fetch milestone, contract, and project details to verify ownership and status
    $stmt = $db->prepare("
        SELECT m.milestone_id, m.amount, m.payment_status, 
               c.contract_id, c.client_id, c.freelancer_id, c.project_id
        FROM milestones m
        JOIN contracts c ON m.contract_id = c.contract_id
        WHERE m.milestone_id = :milestone_id
    ");
    $stmt->bindParam(":milestone_id", $milestone_id, PDO::PARAM_INT);
    $stmt->execute();
    $milestone_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$milestone_data) {
        throw new Exception("Milestone not found.");
    }

    $project_id = $milestone_data["project_id"]; // Get project ID for redirect

    // 2. Verify the current user is the client for this contract
    if ($milestone_data["client_id"] != $client_id) {
        throw new Exception("Authorization denied. You are not the client for this project.");
    }

    // 3. Check if the milestone is already funded or in an invalid state
    if ($milestone_data["payment_status"] !== "unpaid") {
        throw new Exception("Milestone cannot be funded. Current status: " . $milestone_data["payment_status"] . ".");
    }

    // 4. Update milestone status to "in_escrow"
    $update_stmt = $db->prepare("UPDATE milestones SET payment_status = "in_escrow" WHERE milestone_id = :milestone_id");
    $update_stmt->bindParam(":milestone_id", $milestone_id, PDO::PARAM_INT);
    if (!$update_stmt->execute()) {
        throw new PDOException("Failed to update milestone status.");
    }

    // 5. Record the transaction (Simulated deposit/funding)
    $transaction_desc = "Escrow funding for Milestone ID: " . $milestone_id . " (Project ID: " . $project_id . ")";
    $transaction_id = record_transaction(
        $client_id,
        "escrow_funding",
        $milestone_data["amount"],
        "completed", // Assume funding is immediate in simulation
        $milestone_data["freelancer_id"], // Related user is the freelancer
        $milestone_data["contract_id"],
        $milestone_id,
        $transaction_desc
    );

    if (!$transaction_id) {
        throw new Exception("Failed to record funding transaction.");
    }

    // 6. Create notification for the freelancer
    $freelancer_id = $milestone_data["freelancer_id"];
    $client_name = get_user_name(); // Get client name
    $notification_content = "Client " . $client_name . " has funded the escrow for milestone ID: " . $milestone_id . " (" . format_currency($milestone_data["amount"]) . ").";
    create_notification($freelancer_id, "payment", $notification_content, $project_id, "medium");

    // Commit transaction
    $db->commit();

    // Redirect back to project page with success message
    redirect("/improved/pages/projects/view.php?id=" . $project_id . "&message=milestone_funded");
    exit;

} catch (PDOException | Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Milestone funding error: " . $e->getMessage());
    // Redirect back with error message
    $redirect_url = $project_id ? "/improved/pages/projects/view.php?id=" . $project_id : "/improved/";
    redirect($redirect_url . "?error=" . urlencode("funding_failed: " . $e->getMessage()));
    exit;
}

?>

