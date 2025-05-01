<?php
// Include bootstrap and check user permissions
require_once __DIR__ . "/../../bootstrap.php";

// Check if user is logged in
if (!is_logged_in()) {
    redirect("/pages/auth/login.php?error=login_required&redirect=/pages/profile/edit.php");
    exit;
}

// Get current user ID and type
$user_id = get_current_user_id();
$user_type = get_user_type();

// Database connection
$db = getDbConnection();
if (!$db) {
    $error_message = "Database connection error.";
    // Optionally redirect or display error differently
}

// Fetch current user data
$user_data = null;
if ($db) {
    try {
        $stmt = $db->prepare("SELECT u.*, 
                                 fp.headline AS freelancer_headline, fp.summary AS freelancer_bio, fp.hourly_rate, fp.experience_level, 
                                 cp.company_name, cp.website AS client_website, cp.description AS client_bio
                          FROM users u
                          LEFT JOIN freelancer_profiles fp ON u.user_id = fp.user_id AND u.user_type = 'freelancer'
                          LEFT JOIN client_profiles cp ON u.user_id = cp.user_id AND u.user_type = 'client'
                          WHERE u.user_id = :user_id");
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            $error_message = "User data not found.";
        }

        // Fetch user skills if freelancer
        if ($user_data && $user_type === 'freelancer') {
            $skill_stmt = $db->prepare("SELECT s.skill_name FROM skills s JOIN user_skills us ON s.skill_id = us.skill_id WHERE us.user_id = :user_id");
            $skill_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $skill_stmt->execute();
            $user_skills = $skill_stmt->fetchAll(PDO::FETCH_COLUMN);
            $user_data['skills_string'] = implode(', ', $user_skills);
        }

    } catch (PDOException $e) {
        error_log("Error fetching user data for edit: " . $e->getMessage());
        $error_message = "Error fetching profile data.";
    }
}

// Include header
$page_title = "Edit Profile";
require_once __DIR__ . "/../../includes/header.php";

// Generate CSRF token
$token = generate_form_token('edit_profile_token');

?>

<div class="container">
    <div class="form-container">
        <h2 class="form-title">Edit Your Profile</h2>

        <?php display_message(); // Display any session messages ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if ($user_data): ?>
        <form action="/pages/profile/edit_process.php" method="post" class="validate-form" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?php echo $token; ?>">

            <h3 class="mb-3">Basic Information</h3>
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="profile_picture">Profile Picture</label>
                <?php if (!empty($user_data['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars(get_profile_picture_url($user_data['profile_picture'])); ?>" alt="Current Profile Picture" style="max-width: 100px; max-height: 100px; display: block; margin-bottom: 10px;">
                <?php endif; ?>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg, image/png, image/gif">
                <div class="hint">Upload a new picture (JPG, PNG, GIF). Max 2MB. Leave blank to keep current picture.</div>
            </div>

            <hr class="my-4">

            <?php if ($user_type === 'freelancer'): ?>
                <h3 class="mb-3">Freelancer Profile</h3>
                <div class="form-group">
                    <label for="freelancer_headline">Headline</label>
                    <input type="text" id="freelancer_headline" name="freelancer_headline" placeholder="e.g., Senior Web Developer | PHP & JavaScript Expert" value="<?php echo htmlspecialchars($user_data['freelancer_headline'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="freelancer_bio">Summary / Bio</label>
                    <textarea id="freelancer_bio" name="freelancer_bio" rows="6" placeholder="Tell clients about your skills, experience, and what makes you stand out."><?php echo htmlspecialchars($user_data['freelancer_bio'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="skills">Skills (comma-separated)</label>
                    <input type="text" id="skills" name="skills" placeholder="e.g., PHP, JavaScript, HTML, CSS, WordPress" value="<?php echo htmlspecialchars($user_data['skills_string'] ?? ''); ?>">
                    <div class="hint">Enter your key skills.</div>
                </div>

                <div class="form-group">
                    <label for="hourly_rate">Hourly Rate ($)</label>
                    <input type="number" id="hourly_rate" name="hourly_rate" placeholder="e.g., 50" step="0.01" min="0" value="<?php echo htmlspecialchars($user_data['hourly_rate'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="experience_level">Experience Level</label>
                    <select id="experience_level" name="experience_level">
                        <option value="" <?php echo empty($user_data['experience_level']) ? 'selected' : ''; ?>>Select Level</option>
                        <option value="entry" <?php echo ($user_data['experience_level'] ?? '') === 'entry' ? 'selected' : ''; ?>>Entry Level</option>
                        <option value="intermediate" <?php echo ($user_data['experience_level'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="expert" <?php echo ($user_data['experience_level'] ?? '') === 'expert' ? 'selected' : ''; ?>>Expert</option>
                    </select>
                </div>

            <?php elseif ($user_type === 'client'): ?>
                <h3 class="mb-3">Client Profile</h3>
                <div class="form-group">
                    <label for="company_name">Company Name (Optional)</label>
                    <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($user_data['company_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="client_website">Website (Optional)</label>
                    <input type="url" id="client_website" name="client_website" placeholder="https://yourcompany.com" value="<?php echo htmlspecialchars($user_data['client_website'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="client_bio">Company Description (Optional)</label>
                    <textarea id="client_bio" name="client_bio" rows="4" placeholder="Briefly describe your company or what you do."><?php echo htmlspecialchars($user_data['client_bio'] ?? ''); ?></textarea>
                </div>
            <?php endif; ?>

            <hr class="my-4">

            <h3 class="mb-3">Change Password (Optional)</h3>
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                <div class="hint">Required only if changing password.</div>
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password">
                <div class="hint">Leave blank to keep current password. Minimum 8 characters.</div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save Changes</button>
                <a href="/pages/profile/view.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        <?php else: ?>
            <p>Could not load profile data. Please try again later.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . "/../../includes/footer.php";
?>

