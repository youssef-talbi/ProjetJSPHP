<?php
// Include header
require_once __DIR__ . '/../../includes/header.php';
$baseUrl = '/improved';
?>

<div class="container">
    <div class="form-container">
        <h2 class="form-title">Create an Account</h2>
        
        <!-- Alert for errors -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger" data-dismissible data-auto-dismiss>
                <div class="alert-icon">!</div>
                <div>
                    <?php 
                    $error = $_GET['error'];
                    if ($error === 'email_exists') {
                        echo "Email already exists. Please use a different email or login.";
                    } elseif ($error === 'password_mismatch') {
                        echo "Passwords do not match. Please try again.";
                    } elseif ($error === 'empty') {
                        echo "Please fill in all required fields.";
                    } else {
                        echo "An error occurred. Please try again.";
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?php echo $baseUrl; ?>/pages/auth/register_process.php" method="post" class="validate-form">
            <input type="hidden" name="token" value="<?php echo generate_form_token('register_form'); ?>">
            <div class="form-group">
                <label for="user_type">I am a</label>
                <select id="user_type" name="user_type" required>
                    <option value="">Select account type</option>
                    <option value="client">Client - I want to hire freelancers</option>
                    <option value="freelancer">Freelancer - I want to find work</option>
                </select>
            </div>
            
            <div class="flex gap-20">
                <div class="form-group" style="flex: 1;">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required placeholder="Enter your first name">
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required placeholder="Enter your last name">
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
                <div class="hint">We'll never share your email with anyone else.</div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Create a password" minlength="8">
                <div class="hint">Password must be at least 8 characters long and include letters and numbers.</div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
            </div>
            
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" placeholder="City, Country">
            </div>
            
            <div class="form-check">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="/pages/legal/terms.php" target="_blank">Terms of Service</a> and <a href="/pages/legal/privacy.php" target="_blank">Privacy Policy</a></label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn">Create Account</button>
            </div>
        </form>
        
        <div class="form-links mt-4">
            <p>Already have an account? <a href="<?php echo $baseUrl; ?>/pages/auth/login.php">Login</a></p>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
