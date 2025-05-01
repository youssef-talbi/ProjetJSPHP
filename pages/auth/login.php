<?php
// Include header
require_once __DIR__ . '/../../includes/header.php';
$baseUrl = '/improved';
?>
<?php
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header("Location: ' . $baseUrl . '/pages/dashboard/" . $_SESSION['user_type'] . "-dashboard.php");
    exit;
}
?>


<div class="container">
    <div class="form-container">
        <h2 class="form-title">Login to Your Account</h2>
        
        <!-- Alert for errors or messages -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger" data-dismissible data-auto-dismiss>
                <div class="alert-icon">!</div>
                <div>
                    <?php 
                    $error = $_GET['error'];
                    if ($error === 'invalid') {
                        echo "Invalid email or password. Please try again.";
                    } elseif ($error === 'empty') {
                        echo "Please fill in all required fields.";
                    } else {
                        echo "An error occurred. Please try again.";
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['message']) && $_GET['message'] === 'logged_out'): ?>
            <div class="alert alert-success" data-dismissible data-auto-dismiss>
                <div class="alert-icon">✓</div>
                <div>You have been logged out successfully.</div>
            </div>
        <?php endif; ?>


        <?php if (isset($_GET['message']) && $_GET['message'] === 'registered'): ?>
            <div class="alert alert-success" data-dismissible data-auto-dismiss>
                <div class="alert-icon">✓</div>
                <div>Registration successful! Please login with your credentials.</div>
            </div>
        <?php endif; ?>
        
        <form action="<?php echo $baseUrl; ?>/pages/auth/login_process.php" method="post" class="validate-form">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>
            
            <div class="form-check">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn">Login</button>
                <a href="<?php echo $baseUrl; ?>/pages/auth/forgot-password.php">Forgot Password?</a>
            </div>
        </form>
        
        <div class="form-links mt-4">
            <p>Don't have an account? <a href="<?php echo $baseUrl; ?>/pages/auth/register.php">Sign Up</a></p>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
