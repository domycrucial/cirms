/* ============================================================
   CIRMS - Core JavaScript
   public/js/cirms.js
   ============================================================ */

'use strict';

// ── Auto-dismiss flash messages after 5 s ────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bs = bootstrap.Alert.getOrCreateInstance(alert);
            if (bs) bs.close();
        }, 5000);
    });
});

// ── Sidebar toggle (desktop collapse + mobile slide-in) ─────
document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.cirms-shell');
    if (!shell) return;

    const desktopQuery = window.matchMedia('(min-width: 769px)');
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');
    const closes = document.querySelectorAll('[data-sidebar-close], .sidebar-link, .sidebar-logout');

    const applyDesktopState = () => {
        if (!desktopQuery.matches) return;
        const collapsed = localStorage.getItem('cirms.sidebar.collapsed') === '1';
        shell.classList.toggle('sidebar-collapsed', collapsed);
        shell.classList.remove('sidebar-open');
    };

    const toggleSidebar = () => {
        if (desktopQuery.matches) {
            const next = !shell.classList.contains('sidebar-collapsed');
            shell.classList.toggle('sidebar-collapsed', next);
            localStorage.setItem('cirms.sidebar.collapsed', next ? '1' : '0');
        } else {
            shell.classList.toggle('sidebar-open');
        }
    };

    toggles.forEach(btn => btn.addEventListener('click', toggleSidebar));
    closes.forEach(el => el.addEventListener('click', () => shell.classList.remove('sidebar-open')));
    window.addEventListener('resize', applyDesktopState);
    applyDesktopState();
});

// ── Incident Report Form Validation ─────────────────────────
(function () {
    const form = document.getElementById('incidentForm');
    if (!form) return;

    form.addEventListener('submit', function (e) { 
        let valid = true;
        const errors = [];

        // Description minimum length
        const desc = document.getElementById('description');
        if (desc && desc.value.trim().length < 50) {
            valid = false;
            errors.push('Description must be at least 50 characters.');
            desc.classList.add('is-invalid');
        } else if (desc) {
            desc.classList.remove('is-invalid');
        }

        // Category required
        const cat = document.getElementById('category_id');
        if (cat && !cat.value) {
            valid = false;
            errors.push('Please select an incident category.');
            cat.classList.add('is-invalid');
        } else if (cat) {
            cat.classList.remove('is-invalid');
        }

        // Severity required
        const sev = document.getElementById('severity');
        if (sev && !sev.value) {
            valid = false;
            errors.push('Please select a severity level.');
            sev.classList.add('is-invalid');
        } else if (sev) {
            sev.classList.remove('is-invalid');
        }

        // Consent checkbox
        const consent = document.getElementById('consent');
        if (consent && !consent.checked) {
            valid = false;
            errors.push('You must acknowledge the data consent statement.');
            consent.classList.add('is-invalid');
        } else if (consent) {
            consent.classList.remove('is-invalid');
        }

        // File size check
        const fileInput = document.getElementById('attachments');
        if (fileInput && fileInput.files.length > 0) {
            const maxBytes = 10 * 1024 * 1024; // 10 MB
            for (const file of fileInput.files) {
                if (file.size > maxBytes) {
                    valid = false;
                    errors.push(`File "${file.name}" exceeds the 10 MB limit.`);
                }
            }
        }

        if (!valid) {
            e.preventDefault();
            showFormErrors(errors);
        }
    });

    // Live character counter for description
    const desc = document.getElementById('description');
    const counter = document.getElementById('descCounter');
    if (desc && counter) {
        desc.addEventListener('input', () => {
            const len = desc.value.trim().length;
            counter.textContent = `${len} characters (minimum 50)`;
            counter.style.color = len >= 50 ? '#16a34a' : '#ef4444';
        });
    }
})();

// ── Confirm delete / status change dialogs ───────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
        if (!confirm(this.dataset.confirm)) {
            e.preventDefault();
        }
    });
});

// ── Show form validation errors ──────────────────────────────
function showFormErrors(errors) {
    let box = document.getElementById('formErrorBox');
    if (!box) {
        box = document.createElement('div');
        box.id = 'formErrorBox';
        box.className = 'alert alert-danger mt-3';
        const form = document.getElementById('incidentForm');
        form.prepend(box);
    }
    box.innerHTML = '<strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Please fix the following:</strong><ul class="mb-0 mt-1">'
        + errors.map(e => `<li>${e}</li>`).join('')
        + '</ul>';
    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Severity colour on dropdown change ───────────────────────
const sevSelect = document.getElementById('severity');
if (sevSelect) {
    const updateColour = () => {
        sevSelect.className = sevSelect.className.replace(/sev-\w+/g, '');
        if (sevSelect.value) {
            sevSelect.classList.add('sev-' + sevSelect.value.toLowerCase());
        }
    };
    sevSelect.addEventListener('change', updateColour);
    updateColour();
}

// ── Status filter form auto-submit ───────────────────────────
const filterForm = document.getElementById('filterForm');
if (filterForm) {
    filterForm.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', () => filterForm.submit());
    });
}

// ── Tooltips ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(el => new bootstrap.Tooltip(el));
});
