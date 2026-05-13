<?php
// ============================================================
// CIRMS – Landing Page
// public/index.php
// ============================================================

require_once __DIR__ . '/../includes/functions.php';
session_start_secure();

if (is_logged_in()) {
    redirect('/public/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= APP_URL ?>/public/assets/images/cirms.png">
    <title>CIRMS – Campus Cyber Incident Reporting & Management System</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/landing.css">
</head>
<body class="landing-body">

    <!-- Navigation -->
    <nav class="landing-navbar">
        <a href="<?= APP_URL ?>/" class="landing-brand">
            <img src="<?= APP_URL ?>/public/assets/images/cirms_logo.png" alt="CIRMS Logo" class="landing-brand-logo"> CIRMS
        </a>
        
        <div class="landing-nav-links">
            <a href="#features">Features</a>
            <a href="#how-it-works">How it works</a>
            <a href="#contact-us">Contact Us</a>
        </div>
        
        <div class="landing-nav-actions">
            <a href="<?= APP_URL ?>/public/login.php" class="landing-btn-outline">Sign In</a>
            <a href="<?= APP_URL ?>/public/auth/register.php" class="landing-btn-primary">Get Started</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="landing-hero">
        <div class="landing-hero-bg">
            <img src="https://res.cloudinary.com/dyxplt0rb/image/upload/q_auto/f_auto/v1777947945/cyber_image_nolwnr.jpg" alt="Cybersecurity Abstract Background">
        </div>
        
        <div class="landing-badge">
            <span>New</span> Transform your campus security today
        </div>
        
        <h1 class="landing-title">
            Secure Your Campus<br>
            With Our <span>Cybersecurity Platform</span>
        </h1>
        
        <p class="landing-subtitle">
            Streamline incident reporting, boost response times, and enhance data protection with CIRMS. 
            Experience seamless incident tracking and unparalleled support for students and staff.
        </p>

        
        <div class="landing-hero-actions">
             
            <a href="<?= APP_URL ?>/public/auth/register.php" class="landing-btn-light">Get started</a>
            <a href="<?= APP_URL ?>/public/login.php" class="landing-btn-dark">Sign in</a>
        </div>
        
        <div class="landing-features-metrics">
            <div><i class="bi bi-lightning-charge"></i> Real-time tracking</div>
            <div><i class="bi bi-shield-check"></i> Encrypted reporting</div>
            <div><i class="bi bi-graph-up-arrow"></i> Actionable insights</div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="landing-section">
        <div class="landing-section-subtitle reveal">Why Choose CIRMS</div>
        <h2 class="landing-section-title reveal delay-1">Unlock the Full Potential of <span>Your Security</span></h2>
        <p class="landing-section-desc reveal delay-2">
            Our platform is designed to provide you with the tools and insights you need to drive campus safety and efficiency. 
            Here's how we help you achieve your security goals.
        </p>
        
        <div class="landing-cards">
            <!-- Card 1 -->
            <div class="landing-card reveal delay-1">
                <div class="landing-card-icon">
                    <i class="bi bi-layers"></i>
                </div>
                <h3 class="landing-card-title">Seamless Integration</h3>
                <p class="landing-card-desc">
                    Easily integrate with existing institutional systems and workflows, reducing downtime and ensuring a smooth transition for all staff and students.
                </p>
            </div>
            
            <!-- Card 2 -->
            <div class="landing-card reveal delay-2">
                <div class="landing-card-icon">
                    <i class="bi bi-cpu"></i>
                </div>
                <h3 class="landing-card-title">Enhanced Productivity</h3>
                <p class="landing-card-desc">
                    Automate repetitive reporting tasks and streamline incident tracking processes to free up time for what matters most — resolving critical issues.
                </p>
            </div>
            
            <!-- Card 3 -->
            <div class="landing-card reveal delay-3">
                <div class="landing-card-icon">
                    <i class="bi bi-headset"></i>
                </div>
                <h3 class="landing-card-title">Superior Support</h3>
                <p class="landing-card-desc">
                    Enable the IT team to provide dedicated support 24/7. Resolve issues securely, communicate internally, and keep the campus digital infrastructure running smoothly.
                </p>
            </div>
        </div>
        
        <!-- <div class="reveal delay-3" style="margin-top: 4rem;">
            <a href="<?= APP_URL ?>/public/auth/register.php" class="landing-btn-light">Request Access</a>
            <a href="<?= APP_URL ?>/public/login.php" style="color: #94a3b8; margin-left: 2rem; text-decoration: none; font-weight: 500;">Existing user login →</a>
        </div> -->
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="landing-section" style="background: rgba(255, 255, 255, 0.02);">
        <div class="landing-section-subtitle reveal">Simple Process</div>
        <h2 class="landing-section-title reveal delay-1">How CIRMS <span>Works</span></h2>
        <p class="landing-section-desc reveal delay-2">
            A streamlined process designed to resolve campus security incidents efficiently.
        </p>
        
        <div class="landing-timeline">
            <div class="timeline-step reveal delay-1">
                <div class="step-number">01</div>
                <h3 class="step-title">Report an Incident</h3>
                <p class="step-desc">Students or staff submit an incident detailing the issue and attaching any digital evidence securely.</p>
            </div>
            <div class="timeline-step reveal delay-2">
                <div class="step-number">02</div>
                <h3 class="step-title">IT Assessment</h3>
                <p class="step-desc">Our dedicated IT security team receives an instant notification, assesses the severity, and initiates an investigation.</p>
            </div>
            <div class="timeline-step reveal delay-3">
                <div class="step-number">03</div>
                <h3 class="step-title">Resolution & Tracking</h3>
                <p class="step-desc">Track real-time updates as IT officers resolve the incident. You will be notified via email upon resolution.</p>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact-us" class="landing-section">
        <div class="landing-section-subtitle reveal">24/7 Assistance</div>
        <h2 class="landing-section-title reveal delay-1">Need Help? <span>Contact Support</span></h2>
        <p class="landing-section-desc reveal delay-2">
            Our campus IT security team is available around the clock to ensure your digital environment is safe.
        </p>

        <div class="landing-contact-grid">
            <div class="contact-card reveal delay-1">
                <i class="bi bi-envelope-fill"></i>
                <h4>Email Us</h4>
                <p>support@university.ac.tz</p>
                <a href="mailto:support@university.ac.tz" class="contact-link">Send a message <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="contact-card reveal delay-2">
                <i class="bi bi-telephone-fill"></i>
                <h4>Call Center</h4>
                <p>+255 800 123 456</p>
                <a href="tel:+255800123456" class="contact-link">Call now <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="contact-card reveal delay-3">
                <i class="bi bi-geo-alt-fill"></i>
                <h4>Visit IT Office</h4>
                <p>ICT  Building, IAA Arusha</p>
                <span class="contact-link text-muted">Mon - Fri, 8AM - 5PM</span>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="landing-footer">
        <div class="footer-content reveal">
            <div class="footer-brand">
                <div class="landing-brand mb-3">
                    <img src="<?= APP_URL ?>/public/assets/images/cirms_logo.png" alt="CIRMS Logo" class="landing-brand-logo footer-logo"> CIRMS
                </div>
                <p class="footer-desc">
                    The ultimate Campus Cyber Incident Reporting & Management System, 
                    ensuring a secure, reliable, and swift digital environment for education.
                </p>
            </div>
            
            <div class="footer-links">
                <h4 class="footer-title">Quick Links</h4>
                <a href="#features">Features</a>
                <a href="#how-it-works">How it works</a>
                <a href="#security">Contact Support</a>
            </div>
            
            <div class="footer-links">
                <h4 class="footer-title">Legal & Security</h4>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Security Protocols</a>
            </div>
        </div>
        
        <div class="footer-bottom reveal delay-1">
            <p>&copy; <?= date('Y') ?> CIRMS - Campus Cyber Security. All rights reserved.</p>
            <!-- <div class="footer-social">
                <a href="#"><i class="bi bi-twitter-x"></i></a>
                <a href="#"><i class="bi bi-linkedin"></i></a>
                <a href="#"><i class="bi bi-github"></i></a>
            </div> -->
        </div>
    </footer>

    <!-- Interactivity JS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Navbar Scroll Effect
            const navbar = document.querySelector('.landing-navbar');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });

            // Scroll Reveal Animation via IntersectionObserver
            const reveals = document.querySelectorAll('.reveal');
            
            const revealOptions = {
                threshold: 0.1,
                rootMargin: "0px 0px -50px 0px"
            };

            const revealOnScroll = new IntersectionObserver(function(entries, observer) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                        observer.unobserve(entry.target); // Optional: stop observing once revealed
                    }
                });
            }, revealOptions);

            reveals.forEach(reveal => {
                revealOnScroll.observe(reveal);
            });
        });
    </script>
</body>
</html>
