<?php
// Process profile edit form submission

// Include bootstrap and check user permissions
require_once __DIR__ . "/../../bootstrap.php";

// Check if user is logged in
if (!is_logged_in()) {
    redirect("/pages/auth/login.php?error=login_required");
    exit;
}

// Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/pages/profile/edit.php?error=invalid_request");
    exit;
}

// Validate CSRF token
if (!validate_form_token("edit_profile_token")) {
    redirect("/pages/profile/edit.php?error=invalid_token");
    exit;
}

// Get current user ID and type
$user_id = get_current_user_id();
$user_type = get_user_type();

// --- Get and Sanitize Form Data ---

// Basic Info
$first_name = sanitize_input($_POST["first_name"] ?? "");
$last_name = sanitize_input($_POST["last_name"] ?? "");
$email = filter_input(INPUT_POST, "email", FILTER_VALIDATE_EMAIL);

// Profile Specific Info
$freelancer_headline = ($user_type === "freelancer") ? sanitize_input($_POST["freelancer_headline"] ?? "") : null;
$freelancer_bio = ($user_type === "freelancer") ? sanitize_input($_POST["freelancer_bio"] ?? "") : null;
$skills_input = ($user_type === "freelancer") ? sanitize_input($_POST["skills"] ?? "") : null;
$hourly_rate = ($user_type === "freelancer") ? filter_input(INPUT_POST, "hourly_rate", FILTER_VALIDATE_FLOAT) : null;
$experience_level = ($user_type === "freelancer") ? sanitize_input($_POST["experience_level"] ?? "") : null;

$company_name = ($user_type === "client") ? sanitize_input($_POST["company_name"] ?? "") : null;
$client_website = ($user_type === "client") ? filter_input(INPUT_POST, "client_website", FILTER_VALIDATE_URL) : null;
$client_bio = ($user_type === "client") ? sanitize_input($_POST["client_bio"] ?? "") : null;

// Password Change
$current_password = $_POST["current_password"] ?? "";
$new_password = $_POST["new_password"] ?? "";
$confirm_password = $_POST["confirm_password"] ?? "";

// --- Basic Validation ---
if (empty($first_name) || empty($last_name) || empty($email)) {
    redirect("/pages/profile/edit.php?error=empty_basic");
    exit;
}

// --- Password Change Validation ---
$change_password = !empty($new_password);
if ($change_password) {
    if (empty($current_password) || empty($confirm_password)) {
        redirect("/pages/profile/edit.php?error=empty_password_fields");
        exit;
    }
    if (strlen($new_password) < 8) {
        redirect("/pages/profile/edit.php?error=password_short");
        exit;
    }
    if ($new_password !== $confirm_password) {
        redirect("/pages/profile/edit.php?error=password_mismatch");
        exit;
    }
}

// Database connection
$db = getDbConnection();
if (!$db) {
    redirect("/pages/profile/edit.php?error=db_error");
    exit;
}

try {
    // Begin transaction
    $db->beginTransaction();

    // --- Verify Current Password (if changing) ---
    if ($change_password) {
        $pwd_stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
        $pwd_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $pwd_stmt->execute();
        $user_pwd = $pwd_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_pwd || !password_verify($current_password, $user_pwd["password_hash"])) {
            $db->rollBack();
            redirect("/pages/profile/edit.php?error=current_password_incorrect");
            exit;
        }
    }

    // --- Handle Profile Picture Upload ---
    $profile_picture_path = null;
    if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] === UPLOAD_ERR_OK) {
        $upload_dir = BASE_PATH . "/public_html/uploads/profile_pictures/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $upload_result = upload_file($_FILES["profile_picture"], $upload_dir, ["jpg", "jpeg", "png", "gif"], 2 * 1024 * 1024); // Max 2MB

        if ($upload_result["success"]) {
            $profile_picture_path = $upload_result["path"]; // Relative path for DB
            // Optionally, delete the old profile picture file here
        } else {
            $db->rollBack();
            redirect("/pages/profile/edit.php?error=" . urlencode($upload_result["error"]));
            exit;
        }
    }

    // --- Update Users Table ---
    $sql_user_update = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email";
    $params_user_update = [
        ":first_name" => $first_name,
        ":last_name" => $last_name,
        ":email" => $email,
        ":user_id" => $user_id
    ];

    if ($change_password) {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $sql_user_update .= ", password_hash = :password_hash";
        $params_user_update[":password_hash"] = $new_password_hash;
    }

    if ($profile_picture_path !== null) {
        $sql_user_update .= ", profile_picture = :profile_picture";
        $params_user_update[":profile_picture"] = $profile_picture_path;
    }

    $sql_user_update .= " WHERE user_id = :user_id";
    $stmt_user = $db->prepare($sql_user_update);
    $stmt_user->execute($params_user_update);

    // --- Update Profile Specific Table ---
    if ($user_type === "freelancer") {
        $stmt_profile = $db->prepare("UPDATE freelancer_profiles 
                                     SET headline = :headline, summary = :summary, hourly_rate = :hourly_rate, experience_level = :experience_level
                                     WHERE user_id = :user_id");
        $hourly_rate_param = ($hourly_rate === false || $hourly_rate === ") ? null : $hourly_rate;
        $stmt_profile->execute([
            ":headline" => $freelancer_headline,
            ":summary" => $freelancer_bio,
            ":hourly_rate" => $hourly_rate_param,
            ":experience_level" => $experience_level,
            ":user_id" => $user_id
        ]);

        // --- Update Skills ---
        // 1. Remove existing skills for the user
        $delete_skills_stmt = $db->prepare("DELETE FROM user_skills WHERE user_id = :user_id");
        $delete_skills_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $delete_skills_stmt->execute();

        // 2. Add new skills
        $skills_array = array_unique(array_filter(array_map("trim", explode(",", $skills_input))));
        if (!empty($skills_array)) {
            $skill_stmt_find = $db->prepare("SELECT skill_id FROM skills WHERE skill_name = :skill_name");
            $skill_stmt_insert = $db->prepare("INSERT INTO skills (skill_name) VALUES (:skill_name)"); // Assuming skills are global
            $user_skill_stmt = $db->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (:user_id, :skill_id)");

            foreach ($skills_array as $skill_name) {
                $skill_stmt_find->bindParam(":skill_name", $skill_name);
                $skill_stmt_find->execute();
                $skill_result = $skill_stmt_find->fetch(PDO::FETCH_ASSOC);

                $current_skill_id = null;
                if ($skill_result) {
                    $current_skill_id = $skill_result["skill_id"];
                } else {
                    // Skill doesn"t exist, insert it
                    $skill_stmt_insert->bindParam(":skill_name", $skill_name);
                    $skill_stmt_insert->execute();
                    $current_skill_id = $db->lastInsertId();
                }

                // Associate skill with user
                if ($current_skill_id) {
                    $user_skill_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                    $user_skill_stmt->bindParam(":skill_id", $current_skill_id, PDO::PARAM_INT);
                    try {
                        $user_skill_stmt->execute();
                    } catch (PDOException $e) {
                        if ($e->getCode() != 23000) { throw $e; } // Ignore duplicates
                    }
                }
            }
        }

    } elseif ($user_type === "client") {
        $stmt_profile = $db->prepare("UPDATE client_profiles 
                                     SET company_name = :company_name, website = :website, description = :description
                                     WHERE user_id = :user_id");
        $stmt_profile->execute([
            ":company_name" => $company_name,
            ":website" => $client_website,
            ":description" => $client_bio,
            ":user_id" => $user_id
        ]);
    }

    // Commit transaction
    $db->commit();

    // Update session data if necessary (e.g., name, email)
    $_SESSION["user"]["first_name"] = $first_name;
    $_SESSION["user"]["email"] = $email;
    // Add other fields as needed

    // Redirect to profile view page with success message
    redirect("/pages/profile/view.php?id=" . $user_id . "&message=profile_updated");
    exit;

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Log error
    error_log("Profile update error for user $user_id: " . $e->getMessage());

    // Redirect back with error
    redirect("/pages/profile/edit.php?error=db_error");
    exit;
}
?>

