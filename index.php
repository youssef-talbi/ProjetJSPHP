<?php
// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Find the Perfect Freelancer for Your Project</h1>
            <p>Connect with skilled professionals or showcase your expertise to find your next opportunity</p>
            <div class="hero-buttons">
                <a href="pages/projects/create.php" class="btn btn-lg">Post a Project</a>
                <a href="pages/auth/register.php" class="btn btn-secondary btn-lg">Join as Freelancer</a>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="container">
    <div class="text-center mb-5">
        <h2>How It Works</h2>
        <p class="text-muted">Simple steps to start working with talented freelancers</p>
    </div>
    
    <div class="grid">
        <div class="card text-center">
            <div class="card-content">
                <img src="assets/images/freelancer_logo.png" alt="Post a Project" style="width: 80px; height: 80px; margin: 0 auto 20px;">
                <h3>Post a Project</h3>
                <p>Describe your project, set your budget, and receive proposals from freelancers ready to help.</p>
            </div>
        </div>
        
        <div class="card text-center">
            <div class="card-content">
                <img src="assets/images/freelancer_logo.png" alt="Hire Freelancers" style="width: 80px; height: 80px; margin: 0 auto 20px;">
                <h3>Hire Freelancers</h3>
                <p>Compare proposals, portfolios, and reviews to find the perfect match for your needs.</p>
            </div>
        </div>
        
        <div class="card text-center">
            <div class="card-content">
                <img src="assets/images/freelancer_logo.png" alt="Work Together" style="width: 80px; height: 80px; margin: 0 auto 20px;">
                <h3>Work Together</h3>
                <p>Use our platform to communicate, share files, and collaborate effectively.</p>
            </div>
        </div>
    </div>
</section>

<!-- Featured Projects Section -->
<section class="container">
    <div class="text-center mb-5">
        <h2>Featured Projects</h2>
        <p class="text-muted">Browse our latest opportunities</p>
    </div>
    
    <div class="grid">
        <div class="card">
            <img src="assets/images/freelance_laptop.jpeg" alt="Web Development Project" class="card-image">
            <div class="card-content">
                <h3 class="card-title">WordPress Website Development</h3>
                <p class="card-text">Looking for an experienced WordPress developer to create a business website with e-commerce functionality.</p>
                <p><strong>Budget:</strong> $500 - $1,000</p>
            </div>
            <div class="card-footer">
                <span class="badge badge-primary">Web Development</span>
                <a href="pages/projects/view.php?id=1" class="btn btn-sm">View Details</a>
            </div>
        </div>
        
        <div class="card">
            <img src="assets/images/freelance_hero.jpeg" alt="Logo Design Project" class="card-image">
            <div class="card-content">
                <h3 class="card-title">Logo Design for Tech Startup</h3>
                <p class="card-text">Need a modern, clean logo for our new tech startup. We are in the AI space and want something that conveys innovation.</p>
                <p><strong>Budget:</strong> $200 - $300</p>
            </div>
            <div class="card-footer">
                <span class="badge badge-primary">Design</span>
                <a href="pages/projects/view.php?id=2" class="btn btn-sm">View Details</a>
            </div>
        </div>
        
        <div class="card">
            <img src="assets/images/freelance_laptop.jpeg" alt="Content Writing Project" class="card-image">
            <div class="card-content">
                <h3 class="card-title">Content Writing for Blog</h3>
                <p class="card-text">Seeking a content writer to create 10 blog posts about digital marketing trends. Each post should be 1,500+ words.</p>
                <p><strong>Budget:</strong> $300 - $500</p>
            </div>
            <div class="card-footer">
                <span class="badge badge-primary">Writing</span>
                <a href="pages/projects/view.php?id=3" class="btn btn-sm">View Details</a>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <a href="pages/projects/list.php" class="btn">View All Projects</a>
    </div>
</section>

<!-- Top Freelancers Section -->
<section class="container">
    <div class="text-center mb-5">
        <h2>Top Freelancers</h2>
        <p class="text-muted">Meet our most successful professionals</p>
    </div>
    
    <div class="grid">
        <div class="card">
            <div class="card-content text-center">
                <img src="assets/images/freelancer_logo.png" alt="John Doe" style="width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 20px;">
                <h3 class="card-title">John Doe</h3>
                <p class="text-primary mb-2">Web Developer</p>
                <p class="card-text">Full-stack developer with 5+ years of experience in React, Node.js, and MongoDB.</p>
                <div class="mt-3">
                    <span class="badge badge-primary">JavaScript</span>
                    <span class="badge badge-primary">React</span>
                    <span class="badge badge-primary">Node.js</span>
                </div>
            </div>
            <div class="card-footer">
                <div>⭐⭐⭐⭐⭐ (4.9)</div>
                <a href="pages/profile/view.php?id=1" class="btn btn-sm">View Profile</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-content text-center">
                <img src="assets/images/freelancer_logo.png" alt="Jane Smith" style="width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 20px;">
                <h3 class="card-title">Jane Smith</h3>
                <p class="text-primary mb-2">Graphic Designer</p>
                <p class="card-text">Creative designer specializing in branding, logo design, and UI/UX for websites and apps.</p>
                <div class="mt-3">
                    <span class="badge badge-primary">Photoshop</span>
                    <span class="badge badge-primary">Illustrator</span>
                    <span class="badge badge-primary">Figma</span>
                </div>
            </div>
            <div class="card-footer">
                <div>⭐⭐⭐⭐⭐ (4.8)</div>
                <a href="pages/profile/view.php?id=2" class="btn btn-sm">View Profile</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-content text-center">
                <img src="assets/images/freelancer_logo.png" alt="Michael Johnson" style="width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 20px;">
                <h3 class="card-title">Michael Johnson</h3>
                <p class="text-primary mb-2">Content Writer</p>
                <p class="card-text">SEO-focused content writer with expertise in technology, marketing, and business niches.</p>
                <div class="mt-3">
                    <span class="badge badge-primary">SEO</span>
                    <span class="badge badge-primary">Copywriting</span>
                    <span class="badge badge-primary">Blogging</span>
                </div>
            </div>
            <div class="card-footer">
                <div>⭐⭐⭐⭐⭐ (4.7)</div>
                <a href="pages/profile/view.php?id=3" class="btn btn-sm">View Profile</a>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <a href="pages/freelancers/list.php" class="btn">View All Freelancers</a>
    </div>
</section>

<!-- Testimonials Section -->
<section class="bg-light p-5 mt-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2>What Our Users Say</h2>
            <p class="text-muted">Success stories from our community</p>
        </div>
        
        <div class="grid">
            <div class="card">
                <div class="card-content">
                    <p class="card-text">"I found an amazing developer on this platform who turned my idea into reality. The process was smooth and the results exceeded my expectations."</p>
                    <div class="flex items-center mt-4">
                        <img src="assets/images/freelancer_logo.png" alt="Client" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px;">
                        <div>
                            <h4 class="mb-1">Robert Williams</h4>
                            <p class="text-muted">Startup Founder</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-content">
                    <p class="card-text">"As a freelancer, this platform has been a game-changer for me. I've connected with clients from around the world and grown my business significantly."</p>
                    <div class="flex items-center mt-4">
                        <img src="assets/images/freelancer_logo.png" alt="Freelancer" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px;">
                        <div>
                            <h4 class="mb-1">Sarah Johnson</h4>
                            <p class="text-muted">UX Designer</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-content">
                    <p class="card-text">"The quality of freelancers here is outstanding. I've hired multiple professionals for different projects and have always been satisfied with the results."</p>
                    <div class="flex items-center mt-4">
                        <img src="assets/images/freelancer_logo.png" alt="Client" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px;">
                        <div>
                            <h4 class="mb-1">David Chen</h4>
                            <p class="text-muted">Marketing Director</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="container text-center p-5 mt-5 mb-5">
    <h2>Ready to Get Started?</h2>
    <p class="mb-4">Join thousands of clients and freelancers already using our platform</p>
    <div class="flex justify-center gap-20">
        <a href="pages/projects/create.php" class="btn btn-lg">Post a Project</a>
        <a href="pages/auth/register.php" class="btn btn-secondary btn-lg">Create an Account</a>
    </div>
</section>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';
?>
