<?php
// Payments - Release Milestone Payment (Simulated)
require_once __DIR__ . "/../../bootstrap.php";

// Check if user is logged in and is a client
if (!is_logged_in() || !has_permission("release_milestone")) {
    redirect("/improved/pages/auth/login.php?error=login_required");
    exit;
}

// Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/improved/?error=invalid_request");
    exit;
}

// Validate CSRF token
if (!validate_form_token("project_action_token")) {
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

    // 1. Fetch milestone, contract, and project details
    $stmt = $db->prepare("
        SELECT m.milestone_id, m.amount, m.payment_status, m.status as work_status,
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

    $project_id = $milestone_data["project_id"];
    $freelancer_id = $milestone_data["freelancer_id"];
    $amount = (float)$milestone_data["amount"];

    // 2. Verify the current user is the client
    if ($milestone_data["client_id"] != $client_id) {
        throw new Exception("Authorization denied. You are not the client for this project.");
    }

    // 3. Check if the milestone payment is currently in escrow
    if ($milestone_data["payment_status"] !== "in_escrow") {
        throw new Exception("Payment cannot be released. Current payment status: " . $milestone_data["payment_status"] . ".");
    }

    // 4. Check if the milestone work is marked as completed (optional but recommended)
    // if ($milestone_data["work_status"] !== "completed") {
    //     throw new Exception("Cannot release payment until milestone work is marked as completed by the freelancer and approved.");
    // }

    // 5. Update milestone payment status to "released"
    $update_milestone_stmt = $db->prepare("UPDATE milestones SET payment_status = "released" WHERE milestone_id = :milestone_id");
    $update_milestone_stmt->bindParam(":milestone_id", $milestone_id, PDO::PARAM_INT);
    if (!$update_milestone_stmt->execute()) {
        throw new PDOException("Failed to update milestone payment status.");
    }

    // 6. Record the transaction for the client (releasing escrow)
    $client_tx_desc = "Escrow release for Milestone ID: " . $milestone_id . " to Freelancer ID: " . $freelancer_id;
    $client_tx_id = record_transaction(
        $client_id,
        "escrow_release",
        $amount,
        "completed",
        $freelancer_id,
        $milestone_data["contract_id"],
        $milestone_id,
        $client_tx_desc
    );
    if (!$client_tx_id) {
        throw new Exception("Failed to record client transaction for escrow release.");
    }

    // 7. Record the transaction for the freelancer (receiving payment)
    $freelancer_tx_desc = "Payment received for Milestone ID: " . $milestone_id . " from Client ID: " . $client_id;
    $freelancer_tx_id = record_transaction(
        $freelancer_id,
        "escrow_release", // Or use a type like "payout_received"
        $amount,
        "completed",
        $client_id,
        $milestone_data["contract_id"],
        $milestone_id,
        $freelancer_tx_desc
    );
    if (!$freelancer_tx_id) {
        throw new Exception("Failed to record freelancer transaction for payment received.");
    }

    // 8. Update client's total spent (optional, could be calculated dynamically)
    $update_client_spent_stmt = $db->prepare("UPDATE client_profiles SET total_spent = total_spent + :amount WHERE user_id = :client_id");
    $update_client_spent_stmt->bindParam(":amount", $amount);
    $update_client_spent_stmt->bindParam(":client_id", $client_id, PDO::PARAM_INT);
    $update_client_spent_stmt->execute(); // Best effort, failure might not warrant rollback

    // 9. Update freelancer's total earnings (optional, could be calculated dynamically)
    $update_freelancer_earnings_stmt = $db->prepare("UPDATE freelancer_profiles SET total_earnings = total_earnings + :amount WHERE user_id = :freelancer_id");
    $update_freelancer_earnings_stmt->bindParam(":amount", $amount);
    $update_freelancer_earnings_stmt->bindParam(":freelancer_id", $freelancer_id, PDO::PARAM_INT);
    $update_freelancer_earnings_stmt->execute(); // Best effort

    // 10. Create notification for the freelancer
    $client_name = get_user_name();
    $freelancer_notification = "Payment of " . format_currency($amount) . " for milestone ID: " . $milestone_id . " has been released by client " . $client_name . ".";
    create_notification($freelancer_id, "payment", $freelancer_notification, $project_id, "high");

    // 11. Create notification for the client (confirmation)
    $client_notification = "You have successfully released payment of " . format_currency($amount) . " for milestone ID: " . $milestone_id . ".";
    create_notification($client_id, "payment", $client_notification, $project_id, "medium");

    // Commit transaction
    $db->commit();

    // Redirect back to project page with success message
    redirect("/improved/pages/projects/view.php?id=" . $project_id . "&message=milestone_released");
    exit;

} catch (PDOException | Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Milestone release error: " . $e->getMessage());
    // Redirect back with error message
    $redirect_url = $project_id ? "/improved/pages/projects/view.php?id=" . $project_id : "/improved/";
    redirect($redirect_url . "?error=" . urlencode("release_failed: " . $e->getMessage()));
    exit;
}

?>

