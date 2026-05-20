<?php
// ============================================================
// CIRMS – Landing Page
// public/index.php
// ============================================================

require_once __DIR__ . '/../includes/functions.php';
send_security_headers();
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
                <p>+255 615 359 265</p>
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

    <!-- Page-transition loading overlay (no Bootstrap needed — custom CSS spinner) -->
    <div id="pageLoader" role="status" aria-label="Loading"
         style="display:none;position:fixed;inset:0;background:rgba(10,20,35,.92);z-index:9999;
                align-items:center;justify-content:center;flex-direction:column;gap:1.4rem;">
        <!-- Outer glow ring + inner spinner -->
        <div class="cirms-loader-wrap">
            <div class="cirms-glow-ring"></div>
            <div class="cirms-spin-ring"></div>
            <div class="cirms-logo-center">
                <img src="<?= APP_URL ?>/public/assets/images/cirms_logo.png"
                     alt="" style="width:28px;height:28px;border-radius:7px;object-fit:cover;">
            </div>
        </div>
        <span id="pageLoaderLabel"
              style="color:#cbd5e1;font-size:1rem;font-weight:500;letter-spacing:.06em;">Loading…</span>
        <!-- Animated dot-progress bar -->
        <div class="cirms-progress-bar"><div class="cirms-progress-fill"></div></div>
    </div>
    <style>
        .cirms-loader-wrap {
            position: relative;
            width: 80px; height: 80px;
            display: flex; align-items: center; justify-content: center;
        }
        .cirms-glow-ring {
            position: absolute; inset: 0;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0,212,255,.12) 0%, transparent 70%);
            animation: cirmsGlowPulse 2s ease-in-out infinite;
        }
        .cirms-spin-ring {
            position: absolute; inset: 6px;
            border: 4px solid rgba(0,212,255,.18);
            border-top-color: #00d4ff;
            border-radius: 50%;
            animation: landingSpin .85s linear infinite;
        }
        .cirms-logo-center {
            position: relative; z-index: 1;
            animation: cirmsLogoPulse 2s ease-in-out infinite;
        }
        .cirms-progress-bar {
            width: 180px; height: 3px;
            background: rgba(0,212,255,.15);
            border-radius: 99px;
            overflow: hidden;
        }
        .cirms-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, transparent, #00d4ff, transparent);
            border-radius: 99px;
            animation: cirmsProgressSlide 1.4s ease-in-out infinite;
        }
        @keyframes landingSpin        { to { transform: rotate(360deg); } }
        @keyframes cirmsGlowPulse     { 0%,100%{opacity:.6;transform:scale(1)} 50%{opacity:1;transform:scale(1.15)} }
        @keyframes cirmsLogoPulse     { 0%,100%{opacity:.85} 50%{opacity:1} }
        @keyframes cirmsProgressSlide { 0%{transform:translateX(-100%)} 100%{transform:translateX(200%)} }
    </style>

    <!-- Interactivity JS -->
    <script>
    (function () {
        'use strict';

        /* ── Page-transition overlay with 5-second delay ────────── */
        var LOAD_DELAY = 5000; // ms — minimum time loader stays visible

        function navigateWithLoader(href, label) {
            var el = document.getElementById('pageLoader');
            var lb = document.getElementById('pageLoaderLabel');
            if (!el) { window.location.href = href; return; }
            if (lb && label) lb.textContent = label;
            el.style.display = 'flex';
            setTimeout(function () { window.location.href = href; }, LOAD_DELAY);
        }

        /* Attach intercepted navigation to external links */
        document.querySelectorAll('a[href]').forEach(function (link) {
            var href = link.getAttribute('href') || '';
            if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
            var text = link.textContent.trim();
            var label = text === 'Sign In' || text === 'Sign in'     ? 'Opening sign in…'
                      : text === 'Get Started' || text === 'Get started' ? 'Getting started…'
                      : text.length ? text + '…' : 'Loading…';
            link.addEventListener('click', function (e) {
                if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
                e.preventDefault();
                navigateWithLoader(href, label);
            });
        });

        document.addEventListener('DOMContentLoaded', function () {

            /* ── Navbar scroll effect ─────────────────────────── */
            var navbar = document.querySelector('.landing-navbar');
            if (navbar) {
                window.addEventListener('scroll', function () {
                    navbar.classList.toggle('scrolled', window.scrollY > 50);
                });
            }

            /* ── Scroll-reveal via IntersectionObserver ──────── */
            var reveals = document.querySelectorAll('.reveal');
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries, obs) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('active');
                            obs.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
                reveals.forEach(function (el) { observer.observe(el); });
            } else {
                reveals.forEach(function (el) { el.classList.add('active'); });
            }
        });
    }());
    </script>
</body>
</html>
