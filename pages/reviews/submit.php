<?php
// Reviews - Submit Review Page
require_once __DIR__ . "/../../bootstrap.php";

// Check if user is logged in
if (!is_logged_in()) {
    redirect("/improved/pages/auth/login.php?error=login_required&redirect=" . urlencode($_SERVER["REQUEST_URI"]));
    exit;
}

$user_id = get_current_user_id();
$user_type = get_user_type();
$contract_id = filter_input(INPUT_GET, "contract_id", FILTER_VALIDATE_INT);

if (!$contract_id) {
    redirect("/improved/?error=invalid_contract"); // Redirect to dashboard
    exit;
}

$db = getDbConnection();
$contract = null;
$reviewee = null;
$existing_review = null;
$error_message = null;

if ($db) {
    try {
        // 1. Fetch contract details and verify user involvement and contract status
        $stmt = $db->prepare("
            SELECT c.*, p.title AS project_title, 
                   cl.user_id AS client_user_id, CONCAT(cl.first_name, ", ", cl.last_name) AS client_name, 
                   fr.user_id AS freelancer_user_id, CONCAT(fr.first_name, " ,", fr.last_name) AS freelancer_name
            FROM contracts c
            JOIN projects p ON c.project_id = p.project_id
            JOIN users cl ON c.client_id = cl.user_id
            JOIN users fr ON c.freelancer_id = fr.user_id
            WHERE c.contract_id = :contract_id
        ");
        $stmt->bindParam(":contract_id", $contract_id, PDO::PARAM_INT);
        $stmt->execute();
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contract) {
            throw new Exception("Contract not found.");
        }

        // 2. Check if the current user is either the client or the freelancer for this contract
        if ($user_id != $contract["client_user_id"] && $user_id != $contract["freelancer_user_id"]) {
            throw new Exception("You are not authorized to review this contract.");
        }

        // 3. Check if the contract is completed (or in a state where reviews are allowed)
        if ($contract["status"] !== "completed") {
            // Allow reviews even if cancelled/disputed? Maybe not initially.
            throw new Exception("Reviews can only be submitted for completed contracts.");
        }

        // 4. Determine who is being reviewed
        $reviewer_id = $user_id;
        if ($user_id == $contract["client_user_id"]) {
            $reviewee_id = $contract["freelancer_user_id"];
            $reviewee_name = $contract["freelancer_name"];
            $reviewee_type = "freelancer";
        } else { // User is the freelancer
            $reviewee_id = $contract["client_user_id"];
            $reviewee_name = $contract["client_name"];
            $reviewee_type = "client";
        }

        // 5. Check if the user has already submitted a review for this contract
        $check_stmt = $db->prepare("SELECT * FROM reviews WHERE contract_id = :contract_id AND reviewer_id = :reviewer_id");
        $check_stmt->bindParam(":contract_id", $contract_id, PDO::PARAM_INT);
        $check_stmt->bindParam(":reviewer_id", $reviewer_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $existing_review = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_review) {
            // Optionally allow editing, but for now, just show message
            $error_message = "You have already submitted a review for this contract.";
            // redirect("/improved/pages/contracts/view.php?id=" . $contract_id . "&message=already_reviewed");
            // exit;
        }

    } catch (PDOException | Exception $e) {
        error_log("Error preparing review form: " . $e->getMessage());
        $error_message = "Error loading review details: " . $e->getMessage();
        // Don"t exit, show error on the page
    }
} else {
    $error_message = "Database connection error.";
}

// Include header
$page_title = "Submit Review";
if ($contract) {
    $page_title .= " for Contract #" . $contract["contract_id"];
}
require_once __DIR__ . "/../../includes/header.php";

// Generate CSRF token
$token = generate_form_token("submit_review_token");

?>

<div class="container">
    <h2 class="page-title"><?php echo $page_title; ?></h2>

    <?php display_message(); ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($contract && !$existing_review && !$error_message): ?>
        <p>You are reviewing <strong><?php echo htmlspecialchars($reviewee_name); ?></strong> (<?php echo ucfirst($reviewee_type); ?>) for the project "<strong><?php echo htmlspecialchars($contract["project_title"]); ?></strong>".</p>

        <form action="/improved/pages/reviews/submit_process.php" method="post" class="validate-form form-container">
            <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
            <input type="hidden" name="reviewer_id" value="<?php echo $reviewer_id; ?>">
            <input type="hidden" name="reviewee_id" value="<?php echo $reviewee_id; ?>">
            <input type="hidden" name="token" value="<?php echo $token; ?>">

            <div class="form-group rating-group">
                <label for="rating">Overall Rating (1-5 Stars)</label>
                <div class="star-rating">
                    <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 stars">★</label>
                    <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 stars">★</label>
                    <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 stars">★</label>
                    <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 stars">★</label>
                    <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 star">★</label>
                </div>
            </div>

            <?php // Add specific criteria based on who is reviewing whom ?>
            <?php if ($reviewee_type === "freelancer"): ?>
                <div class="form-group rating-group">
                    <label for="quality_rating">Quality of Work</label>
                    <div class="star-rating">
                        <input type="radio" id="q_star5" name="quality_rating" value="5"><label for="q_star5">★</label>
                        <input type="radio" id="q_star4" name="quality_rating" value="4"><label for="q_star4">★</label>
                        <input type="radio" id="q_star3" name="quality_rating" value="3"><label for="q_star3">★</label>
                        <input type="radio" id="q_star2" name="quality_rating" value="2"><label for="q_star2">★</label>
                        <input type="radio" id="q_star1" name="quality_rating" value="1"><label for="q_star1">★</label>
                    </div>
                </div>
                <div class="form-group rating-group">
                    <label for="communication_rating">Communication</label>
                    <div class="star-rating">
                        <input type="radio" id="c_star5" name="communication_rating" value="5"><label for="c_star5">★</label>
                        <input type="radio" id="c_star4" name="communication_rating" value="4"><label for="c_star4">★</label>
                        <input type="radio" id="c_star3" name="communication_rating" value="3"><label for="c_star3">★</label>
                        <input type="radio" id="c_star2" name="communication_rating" value="2"><label for="c_star2">★</label>
                        <input type="radio" id="c_star1" name="communication_rating" value="1"><label for="c_star1">★</label>
                    </div>
                </div>
                <div class="form-group rating-group">
                    <label for="deadline_rating">Adherence to Schedule</label>
                    <div class="star-rating">
                        <input type="radio" id="d_star5" name="deadline_rating" value="5"><label for="d_star5">★</label>
                        <input type="radio" id="d_star4" name="deadline_rating" value="4"><label for="d_star4">★</label>
                        <input type="radio" id="d_star3" name="deadline_rating" value="3"><label for="d_star3">★</label>
                        <input type="radio" id="d_star2" name="deadline_rating" value="2"><label for="d_star2">★</label>
                        <input type="radio" id="d_star1" name="deadline_rating" value="1"><label for="d_star1">★</label>
                    </div>
                </div>
            <?php elseif ($reviewee_type === "client"): ?>
                <div class="form-group rating-group">
                    <label for="communication_rating">Communication</label>
                    <div class="star-rating">
                        <input type="radio" id="c_star5" name="communication_rating" value="5"><label for="c_star5">★</label>
                        <input type="radio" id="c_star4" name="communication_rating" value="4"><label for="c_star4">★</label>
                        <input type="radio" id="c_star3" name="communication_rating" value="3"><label for="c_star3">★</label>
                        <input type="radio" id="c_star2" name="communication_rating" value="2"><label for="c_star2">★</label>
                        <input type="radio" id="c_star1" name="communication_rating" value="1"><label for="c_star1">★</label>
                    </div>
                </div>
                <div class="form-group rating-group">
                    <label for="clarity_rating">Clarity of Requirements</label>
                    <div class="star-rating">
                        <input type="radio" id="cl_star5" name="clarity_rating" value="5"><label for="cl_star5">★</label>
                        <input type="radio" id="cl_star4" name="clarity_rating" value="4"><label for="cl_star4">★</label>
                        <input type="radio" id="cl_star3" name="clarity_rating" value="3"><label for="cl_star3">★</label>
                        <input type="radio" id="cl_star2" name="clarity_rating" value="2"><label for="cl_star2">★</label>
                        <input type="radio" id="cl_star1" name="clarity_rating" value="1"><label for="cl_star1">★</label>
                    </div>
                </div>
                <div class="form-group rating-group">
                    <label for="payment_rating">Promptness of Payment</label>
                    <div class="star-rating">
                        <input type="radio" id="p_star5" name="payment_rating" value="5"><label for="p_star5">★</label>
                        <input type="radio" id="p_star4" name="payment_rating" value="4"><label for="p_star4">★</label>
                        <input type="radio" id="p_star3" name="payment_rating" value="3"><label for="p_star3">★</label>
                        <input type="radio" id="p_star2" name="payment_rating" value="2"><label for="p_star2">★</label>
                        <input type="radio" id="p_star1" name="payment_rating" value="1"><label for="p_star1">★</label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="comment">Comments (Optional)</label>
                <textarea name="comment" id="comment" rows="5" placeholder="Share your experience working with <?php echo htmlspecialchars($reviewee_name); ?>..."></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Submit Review</button>
                <a href="/improved/pages/contracts/view.php?id=<?php echo $contract_id; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    <?php elseif ($existing_review): ?>
        <p>Thank you for submitting your review.</p>
        <!-- Optionally display the submitted review here -->
        <a href="/improved/pages/contracts/view.php?id=<?php echo $contract_id; ?>" class="btn btn-secondary">Back to Contract</a>
    <?php else: ?>
        <p>Could not load review form. Please check the contract ID or try again later.</p>
        <a href="/improved/" class="btn btn-secondary">Back to Dashboard</a>
    <?php endif; ?>

</div>

<style>
    /* Basic Star Rating CSS */
    .star-rating {
        display: inline-block;
        direction: rtl; /* Right-to-left to make stars fill correctly on hover */
        font-size: 2em; /* Adjust size as needed */
        line-height: 1;
    }
    .star-rating input[type="radio"] {
        display: none; /* Hide radio buttons */
    }
    .star-rating label {
        color: #ddd; /* Color of empty stars */
        cursor: pointer;
        padding: 0 0.1em;
    }
    .star-rating input[type="radio"]:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label {
        color: #ffc107; /* Color of selected/hovered stars */
    }
    /* Ensure LTR display for the group */
    .rating-group {
        direction: ltr;
    }
    .rating-group label[for^="star"] { /* Target only star labels */
        direction: rtl;
    }
</style>

<?php
// Include footer
require_once __DIR__ . "/../../includes/footer.php";
?>

