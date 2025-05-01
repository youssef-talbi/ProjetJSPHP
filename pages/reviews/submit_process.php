<?php
// Reviews - Process Review Submission
require_once __DIR__ . "/../../bootstrap.php";

// Check if user is logged in
if (!is_logged_in()) {
    redirect("/improved/pages/auth/login.php?error=login_required");
    exit;
}

// Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/improved/?error=invalid_request");
    exit;
}

// Validate CSRF token
if (!validate_form_token("submit_review_token")) {
    redirect("/improved/?error=invalid_token");
    exit;
}

// Get and sanitize input data
$contract_id = filter_input(INPUT_POST, "contract_id", FILTER_VALIDATE_INT);
$reviewer_id = filter_input(INPUT_POST, "reviewer_id", FILTER_VALIDATE_INT);
$reviewee_id = filter_input(INPUT_POST, "reviewee_id", FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, "rating", FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 1, "max_range" => 5]]);
$comment = sanitize_input($_POST["comment"] ?? "");

// Specific criteria (validate as floats between 1 and 5, or null if not set)
$communication_rating = filter_input(INPUT_POST, "communication_rating", FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 1, "max_range" => 5]]);
$quality_rating = filter_input(INPUT_POST, "quality_rating", FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 1, "max_range" => 5]]);
$deadline_rating = filter_input(INPUT_POST, "deadline_rating", FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 1, "max_range" => 5]]);
$clarity_rating = filter_input(INPUT_POST, "clarity_rating", FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 1, "max_range" => 5]]);
$payment_rating = filter_input(INPUT_POST, "payment_rating", FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 1, "max_range" => 5]]);

// Basic validation
if (!$contract_id || !$reviewer_id || !$reviewee_id || $rating === false) {
    redirect("/improved/pages/reviews/submit.php?contract_id=" . $contract_id . "&error=invalid_input");
    exit;
}

// Ensure logged-in user matches the reviewer ID from the form
if ($reviewer_id != get_current_user_id()) {
    redirect("/improved/?error=authorization_failed");
    exit;
}

$db = getDbConnection();
if (!$db) {
    redirect("/improved/pages/reviews/submit.php?contract_id=" . $contract_id . "&error=db_error");
    exit;
}

try {
    $db->beginTransaction();

    // 1. Verify contract, user involvement, status, and if review already exists (redundant check for safety)
    $stmt = $db->prepare("
        SELECT c.status, c.client_id, c.freelancer_id, p.project_id
        FROM contracts c
        JOIN projects p ON c.project_id = p.project_id
        WHERE c.contract_id = :contract_id
    ");
    $stmt->bindParam(":contract_id", $contract_id, PDO::PARAM_INT);
    $stmt->execute();
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        throw new Exception("Contract not found.");
    }
    if ($contract["status"] !== "completed") {
        throw new Exception("Reviews can only be submitted for completed contracts.");
    }
    if ($reviewer_id != $contract["client_id"] && $reviewer_id != $contract["freelancer_id"]) {
        throw new Exception("Reviewer is not part of this contract.");
    }
    if ($reviewee_id != $contract["client_id"] && $reviewee_id != $contract["freelancer_id"]) {
        throw new Exception("Reviewee is not part of this contract.");
    }
    if ($reviewer_id == $reviewee_id) {
        throw new Exception("Cannot review yourself.");
    }

    // Check again if review exists
    $check_stmt = $db->prepare("SELECT review_id FROM reviews WHERE contract_id = :contract_id AND reviewer_id = :reviewer_id");
    $check_stmt->bindParam(":contract_id", $contract_id, PDO::PARAM_INT);
    $check_stmt->bindParam(":reviewer_id", $reviewer_id, PDO::PARAM_INT);
    $check_stmt->execute();
    if ($check_stmt->fetch()) {
        throw new Exception("Review already submitted.");
    }

    // 2. Insert the review
    $insert_stmt = $db->prepare("
        INSERT INTO reviews (
            contract_id, reviewer_id, reviewee_id, rating, comment, submission_date, 
            communication_rating, quality_rating, expertise_rating, deadline_rating, value_rating, 
            clarity_rating, payment_rating -- Add new client-specific fields if needed in schema
        )
        VALUES (
            :contract_id, :reviewer_id, :reviewee_id, :rating, :comment, NOW(),
            :communication_rating, :quality_rating, NULL, :deadline_rating, NULL, -- Placeholder for freelancer review criteria
            :clarity_rating, :payment_rating -- Placeholder for client review criteria
        )
    ");

    $insert_stmt->bindParam(":contract_id", $contract_id, PDO::PARAM_INT);
    $insert_stmt->bindParam(":reviewer_id", $reviewer_id, PDO::PARAM_INT);
    $insert_stmt->bindParam(":reviewee_id", $reviewee_id, PDO::PARAM_INT);
    $insert_stmt->bindParam(":rating", $rating); // PDO handles float
    $insert_stmt->bindParam(":comment", $comment, PDO::PARAM_STR);

    // Bind specific criteria (use PDO::PARAM_NULL if value is false/null)
    $insert_stmt->bindValue(":communication_rating", $communication_rating ?: null, $communication_rating ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $insert_stmt->bindValue(":quality_rating", $quality_rating ?: null, $quality_rating ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $insert_stmt->bindValue(":deadline_rating", $deadline_rating ?: null, $deadline_rating ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $insert_stmt->bindValue(":clarity_rating", $clarity_rating ?: null, $clarity_rating ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $insert_stmt->bindValue(":payment_rating", $payment_rating ?: null, $payment_rating ? PDO::PARAM_STR : PDO::PARAM_NULL);
    // Note: Added clarity_rating and payment_rating to VALUES, ensure schema matches or adjust query

    if (!$insert_stmt->execute()) {
        throw new PDOException("Failed to insert review.");
    }

    // 3. Update average rating for the reviewee (simplified - recalculate average)
    $avg_stmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(review_id) as review_count FROM reviews WHERE reviewee_id = :reviewee_id AND public_status = 1");
    $avg_stmt->bindParam(":reviewee_id", $reviewee_id, PDO::PARAM_INT);
    $avg_stmt->execute();
    $stats = $avg_stmt->fetch(PDO::FETCH_ASSOC);
    $new_avg_rating = $stats ? (float)$stats["avg_rating"] : 0;
    $new_review_count = $stats ? (int)$stats["review_count"] : 0;

    // Update the appropriate profile table (freelancer or client)
    // Determine reviewee type based on contract details
    $reviewee_user_type_stmt = $db->prepare("SELECT user_type FROM users WHERE user_id = :reviewee_id");
    $reviewee_user_type_stmt->bindParam(":reviewee_id", $reviewee_id, PDO::PARAM_INT);
    $reviewee_user_type_stmt->execute();
    $reviewee_user_type = $reviewee_user_type_stmt->fetchColumn();

    if ($reviewee_user_type === "freelancer") {
        $update_profile_stmt = $db->prepare("UPDATE freelancer_profiles SET average_rating = :avg_rating, review_count = :review_count WHERE user_id = :reviewee_id");
        // Ensure freelancer_profiles has average_rating and review_count columns
    } elseif ($reviewee_user_type === "client") {
        $update_profile_stmt = $db->prepare("UPDATE client_profiles SET average_rating = :avg_rating, review_count = :review_count WHERE user_id = :reviewee_id");
        // Ensure client_profiles has average_rating and review_count columns
    } else {
        $update_profile_stmt = null; // Or log an error
    }

    if ($update_profile_stmt) {
        $update_profile_stmt->bindParam(":avg_rating", $new_avg_rating);
        $update_profile_stmt->bindParam(":review_count", $new_review_count, PDO::PARAM_INT);
        $update_profile_stmt->bindParam(":reviewee_id", $reviewee_id, PDO::PARAM_INT);
        $update_profile_stmt->execute(); // Best effort update
    }

    // 4. Create notification for the reviewee
    $reviewer_name = get_user_name();
    $notification_content = $reviewer_name . " has left you a review for Contract #" . $contract_id . ".";
    create_notification($reviewee_id, "review", $notification_content, $contract["project_id"], "medium");

    // Commit transaction
    $db->commit();

    // Redirect back to contract page with success message
    redirect("/improved/pages/contracts/view.php?id=" . $contract_id . "&message=review_submitted");
    exit;

} catch (PDOException | Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Review submission error: " . $e->getMessage());
    // Redirect back to submission form with error message
    redirect("/improved/pages/reviews/submit.php?contract_id=" . $contract_id . "&error=" . urlencode("submission_failed: " . $e->getMessage()));
    exit;
}

?>

