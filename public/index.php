<?php
// ============================================================
// IRS – Landing Page
// Institute of Accountancy Arusha Reporting System
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
    <link rel="icon" href="<?= APP_URL ?>/public/assets/images/iaa.png">
    <title>IRS – Institute of Accountancy Arusha Reporting System</title>

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
            <img src="<?= APP_URL ?>/public/assets/images/iaa.png" alt="IAA Logo" class="landing-brand-logo"> IRS
        </a>

        <div class="landing-nav-links">
            <a href="#features">Features</a>
            <a href="#categories">Incident Types</a>
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
            <img src="https://res.cloudinary.com/dyxplt0rb/image/upload/q_auto/f_auto/v1777947945/cyber_image_nolwnr.jpg" alt="IAA Campus Background">
        </div>

        <div class="landing-badge">
            <span>IAA</span> Institute of Accountancy Arusha &mdash; ICT Support Portal
        </div>

        <h1 class="landing-title">
            Report ICT Incidents<br>
            at <span>IAA with Ease</span>
        </h1>

        <p class="landing-subtitle">
            The IRS (Institute of Accountancy Arusha Reporting System) enables students and staff to report ISMS, eLearning, and ICT-related incidents quickly.
            Track your report in real time and get timely resolution from the ICT support team.
        </p>

        <div class="landing-hero-actions">
            <a href="<?= APP_URL ?>/public/auth/register.php" class="landing-btn-light">Report an Incident</a>
            <a href="<?= APP_URL ?>/public/login.php" class="landing-btn-dark">Sign In</a>
        </div>

        <div class="landing-features-metrics">
            <div><i class="bi bi-lightning-charge"></i> Real-time tracking</div>
            <div><i class="bi bi-shield-check"></i> Secure reporting</div>
            <div><i class="bi bi-graph-up-arrow"></i> Actionable insights</div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="landing-section">
        <div class="landing-section-subtitle reveal">Why Choose IRS</div>
        <h2 class="landing-section-title reveal delay-1">Powering <span>ICT Support at IAA</span></h2>
        <p class="landing-section-desc reveal delay-2">
            A purpose-built platform for IAA students and staff to report system issues,
            track resolutions, and ensure uninterrupted access to ISMS and eLearning services.
        </p>

        <div class="landing-cards">
            <!-- Card 1 -->
            <div class="landing-card reveal delay-1">
                <div class="landing-card-icon">
                    <i class="bi bi-pc-display-horizontal"></i>
                </div>
                <h3 class="landing-card-title">ISMS & eLearning Support</h3>
                <p class="landing-card-desc">
                    Report issues with the Institute Student Management System (ISMS) and Moodle eLearning portal from login failures to missing course materials and exam access problems.
                </p>
            </div>

            <!-- Card 2 -->
            <div class="landing-card reveal delay-2">
                <div class="landing-card-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h3 class="landing-card-title">Fast Resolution Tracking</h3>
                <p class="landing-card-desc">
                    Every submitted report receives a unique reference number. Track the status of your incident in real time and receive email notifications when resolved.
                </p>
            </div>

            <!-- Card 3 -->
            <div class="landing-card reveal delay-3">
                <div class="landing-card-icon">
                    <i class="bi bi-headset"></i>
                </div>
                <h3 class="landing-card-title">Dedicated ICT Support</h3>
                <p class="landing-card-desc">
                    The IAA ICT support team is notified instantly on every submission. Officers resolve incidents with priority-based SLA timelines critical issues within 2 hours.
                </p>
            </div>
        </div>
    </section>

    <!-- Incident Categories Section -->
    <!-- <section id="categories" class="landing-section" style="background: rgba(255,255,255,0.015);">
        <div class="landing-section-subtitle reveal">Coverage</div>
        <h2 class="landing-section-title reveal delay-1">All ICT Incident <span>Categories Covered</span></h2>
        <p class="landing-section-desc reveal delay-2">
            IRS handles all student and staff ICT incidents at IAA across 11 categories.
            Select the right category when submitting your report for faster routing.
        </p>

        <div class="landing-categories-grid reveal delay-2">
            <div class="category-pill"><i class="bi bi-key-fill"></i> Account &amp; Authentication Issues</div>
            <div class="category-pill"><i class="bi bi-book-fill"></i> Course Access Problems</div>
            <div class="category-pill"><i class="bi bi-upload"></i> Assignment &amp; Submission Issues</div>
            <div class="category-pill"><i class="bi bi-journal-check"></i> Online Quiz &amp; Exam Issues</div>
            <div class="category-pill"><i class="bi bi-card-list"></i> Registration &amp; Academic Records</div>
            <div class="category-pill"><i class="bi bi-cash-coin"></i> Fee Payment &amp; Financial Issues</div>
            <div class="category-pill"><i class="bi bi-speedometer2"></i> System Performance &amp; Availability</div>
            <div class="category-pill"><i class="bi bi-phone-fill"></i> Mobile &amp; Device Compatibility</div>
            <div class="category-pill"><i class="bi bi-envelope-fill"></i> Email &amp; Notification Issues</div>
            <div class="category-pill"><i class="bi bi-person-fill"></i> User Profile &amp; Data Issues</div>
            <div class="category-pill"><i class="bi bi-wifi"></i> Connectivity &amp; Access Issues</div>
        </div>
    </section> -->

    <!-- How It Works Section -->
    <section id="how-it-works" class="landing-section">
        <div class="landing-section-subtitle reveal">Simple Process</div>
        <h2 class="landing-section-title reveal delay-1">How IRS <span>Works</span></h2>
        <p class="landing-section-desc reveal delay-2">
            A simple three-step process designed to resolve IAA ICT incidents efficiently.
        </p>

        <div class="landing-timeline">
            <div class="timeline-step reveal delay-1">
                <div class="step-number">01</div>
                <h3 class="step-title">Submit Your Report</h3>
                <p class="step-desc">Students or staff log in and submit an incident select the category, describe the issue, and optionally attach a screenshot for faster diagnosis.</p>
            </div>
            <div class="timeline-step reveal delay-2">
                <div class="step-number">02</div>
                <h3 class="step-title">ICT Team Assessment</h3>
                <p class="step-desc">The IAA ICT support team receives an instant notification, assesses the severity, and assigns an officer to investigate the issue.</p>
            </div>
            <div class="timeline-step reveal delay-3">
                <div class="step-number">03</div>
                <h3 class="step-title">Resolution &amp; Notification</h3>
                <p class="step-desc">Track updates in real time as the ICT team resolves your incident. You receive an email confirmation once the issue is fully resolved.</p>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact-us" class="landing-section">
        <div class="landing-section-subtitle reveal">Support</div>
        <h2 class="landing-section-title reveal delay-1">Need Help? <span>Contact ICT Support</span></h2>
        <p class="landing-section-desc reveal delay-2">
            The IAA ICT support team is available during working hours to ensure uninterrupted access to all institutional systems.
        </p>

        <div class="landing-contact-grid">
            <div class="contact-card reveal delay-1">
                <i class="bi bi-envelope-fill"></i>
                <h4>Email ICT Support</h4>
                <p>ict@iaa.ac.tz</p>
                <a href="mailto:ict@iaa.ac.tz" class="contact-link">Send a message <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="contact-card reveal delay-2">
                <i class="bi bi-telephone-fill"></i>
                <h4>Call ICT Helpdesk</h4>
                <p>+255 27 250 4080</p>
                <a href="tel:+255272504080" class="contact-link">Call now <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="contact-card reveal delay-3">
                <i class="bi bi-geo-alt-fill"></i>
                <h4>Visit ICT Office</h4>
                <p>ICT Building, IAA Arusha Campus</p>
                <span class="contact-link text-muted">Mon – Fri, 8:00AM – 5:00PM</span>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="landing-footer">
        <div class="footer-content reveal">
            <div class="footer-brand">
                <div class="landing-brand mb-3">
                    <img src="<?= APP_URL ?>/public/assets/images/iaa.png" alt="IAA Logo" class="landing-brand-logo footer-logo"> IRS
                </div>
                <p class="footer-desc">
                    The Institute of Accountancy Arusha Reporting System (IRS) —
                    your dedicated platform for ICT incident reporting, tracking, and resolution at IAA.
                </p>
            </div>

            <div class="footer-links">
                <h4 class="footer-title">Quick Links</h4>
                <a href="#features">Features</a>
                <a href="#categories">Incident Types</a>
                <a href="#how-it-works">How it works</a>
                <a href="#contact-us">Contact Support</a>
            </div>

            <div class="footer-links">
                <h4 class="footer-title">Resources</h4>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">ICT Help Center</a>
            </div>
        </div>

        <div class="footer-bottom reveal delay-1">
            <p>&copy; <?= date('Y') ?> Institute of Accountancy Arusha (IAA) &mdash; IRS. All rights reserved.</p>
        </div>
    </footer>

    <!-- Page-transition loading overlay -->
    <div id="pageLoader" role="status" aria-label="Loading"
         style="display:none;position:fixed;inset:0;background:rgba(10,20,35,.92);z-index:9999;
                align-items:center;justify-content:center;flex-direction:column;gap:1.4rem;">
        <div class="cirms-loader-wrap">
            <div class="cirms-glow-ring"></div>
            <div class="cirms-spin-ring"></div>
            <div class="cirms-logo-center">
                <img src="<?= APP_URL ?>/public/assets/images/iaa.png"
                     alt="" style="width:28px;height:28px;border-radius:7px;object-fit:contain;">
            </div>
        </div>
        <span id="pageLoaderLabel"
              style="color:#cbd5e1;font-size:1rem;font-weight:500;letter-spacing:.06em;">Loading…</span>
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
        /* Categories grid */
        .landing-categories-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            max-width: 950px;
            margin: 0 auto;
        }
        .category-pill {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: linear-gradient(135deg, rgba(0,212,255,.07), rgba(0,85,255,.07));
            border: 1px solid rgba(0,212,255,.2);
            border-radius: 50px;
            padding: .55rem 1.2rem;
            font-size: .9rem;
            color: #cbd5e1;
            font-weight: 500;
            transition: background .2s, border-color .2s, transform .2s;
            cursor: default;
        }
        .category-pill i {
            color: #00d4ff;
            font-size: 1rem;
        }
        .category-pill:hover {
            background: linear-gradient(135deg, rgba(0,212,255,.15), rgba(0,85,255,.12));
            border-color: rgba(0,212,255,.5);
            transform: translateY(-2px);
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

        var LOAD_DELAY = 5000;

        function navigateWithLoader(href, label) {
            var el = document.getElementById('pageLoader');
            var lb = document.getElementById('pageLoaderLabel');
            if (!el) { window.location.href = href; return; }
            if (lb && label) lb.textContent = label;
            el.style.display = 'flex';
            setTimeout(function () { window.location.href = href; }, LOAD_DELAY);
        }

        document.querySelectorAll('a[href]').forEach(function (link) {
            var href = link.getAttribute('href') || '';
            if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
            var text = link.textContent.trim();
            var label = text === 'Sign In' || text === 'Sign in'     ? 'Opening sign in…'
                      : text === 'Get Started' || text === 'Get started' ? 'Getting started…'
                      : text === 'Report an Incident' ? 'Preparing report form…'
                      : text.length ? text + '…' : 'Loading…';
            link.addEventListener('click', function (e) {
                if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
                e.preventDefault();
                navigateWithLoader(href, label);
            });
        });

        document.addEventListener('DOMContentLoaded', function () {

            var navbar = document.querySelector('.landing-navbar');
            if (navbar) {
                window.addEventListener('scroll', function () {
                    navbar.classList.toggle('scrolled', window.scrollY > 50);
                });
            }

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
