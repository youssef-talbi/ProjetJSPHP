<?php
$baseUrl = '/improved';
?>
</main>
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>Freelance Hub</h3>
                    <p>Connect with skilled freelancers or find projects that match your expertise. Our platform makes it easy to collaborate and succeed.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook">FB</a>
                        <a href="#" aria-label="Twitter">TW</a>
                        <a href="#" aria-label="Instagram">IG</a>
                        <a href="#" aria-label="LinkedIn">LI</a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>For Clients</h3>
                    <ul class="footer-links">
                        <li><a href="/pages/projects/create.php">Post a Project</a></li>
                        <li><a href="/pages/freelancers/list.php">Browse Freelancers</a></li> <!-- Corrected path -->
                        <li><a href="/pages/dashboard/client-dashboard.php">Client Dashboard</a></li>
                        <li><a href="/pages/help/client-guide.php">How It Works</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>For Freelancers</h3>
                    <ul class="footer-links">
                        <li><a href="/pages/projects/list.php">Find Projects</a></li>
                        <li><a href="/pages/profile/edit.php">Create Profile</a></li>
                        <li><a href="/pages/dashboard/freelancer-dashboard.php">Freelancer Dashboard</a></li>
                        <li><a href="/pages/help/freelancer-guide.php">Getting Started</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Resources</h3>
                    <ul class="footer-links">
                        <li><a href="/pages/help/faq.php">FAQ</a></li>
                        <li><a href="/pages/blog/index.php">Blog</a></li>
                        <li><a href="/pages/help/support.php">Support</a></li>
                        <li><a href="/pages/help/contact.php">Contact Us</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> Freelance Hub. All rights reserved.</p>
                <ul class="footer-links">
                    <li><a href="/pages/legal/terms.php">Terms of Service</a></li>
                    <li><a href="/pages/legal/privacy.php">Privacy Policy</a></li>
                    <li><a href="/pages/legal/cookies.php">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
    </footer>
    <!-- Main JavaScript -->
    <script src="<?php echo $baseUrl; ?>/assets/js/main.js"></script>
</body>
</html>

