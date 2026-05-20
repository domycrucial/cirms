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

// ── Sidebar: desktop collapse ↔ mobile drawer ───────────────
document.addEventListener('DOMContentLoaded', () => {
    const shell    = document.getElementById('appShell');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!shell) return;

    const isMobile = () => window.innerWidth <= 768;

    /* Restore desktop collapsed state */
    const applyDesktop = () => {
        if (isMobile()) return;
        const collapsed = localStorage.getItem('cirms.sb.collapsed') === '1';
        shell.classList.toggle('sidebar-collapsed', collapsed);
        shell.classList.remove('sidebar-open');
    };

    const open  = () => { shell.classList.add('sidebar-open');    };
    const close = () => { shell.classList.remove('sidebar-open'); };

    const toggle = () => {
        if (isMobile()) {
            shell.classList.contains('sidebar-open') ? close() : open();
        } else {
            const next = !shell.classList.contains('sidebar-collapsed');
            shell.classList.toggle('sidebar-collapsed', next);
            localStorage.setItem('cirms.sb.collapsed', next ? '1' : '0');
        }
    };

    /* Toggle buttons (desktop collapse btn + mobile hamburger) */
    document.querySelectorAll('[data-sidebar-toggle]').forEach(btn =>
        btn.addEventListener('click', toggle)
    );

    /* Backdrop tap closes drawer on mobile */
    if (backdrop) backdrop.addEventListener('click', close);

    /* Nav links close the drawer on mobile */
    document.querySelectorAll('.sb-link, .sb-logout').forEach(el =>
        el.addEventListener('click', () => { if (isMobile()) close(); })
    );

    window.addEventListener('resize', () => {
        if (!isMobile()) { close(); applyDesktop(); }
    });

    applyDesktop();
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

// ── Status filter form auto-submit with loading indicator ────
const filterForm = document.getElementById('filterForm');
if (filterForm) {
    let filterSubmitting = false;
    filterForm.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', () => {
            if (filterSubmitting) return;
            filterSubmitting = true;
            // Show subtle loading state on submit button
            const btn = filterForm.querySelector('[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Filtering…';
            }
            filterForm.submit();
        });
    });
}

// ── Global tooltips (Bootstrap) ──────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Bootstrap tooltip on elements with [title] or [data-bs-toggle="tooltip"]
    document.querySelectorAll('[data-bs-toggle="tooltip"], .text-truncate[title]').forEach(el => {
        if (typeof bootstrap !== 'undefined') {
            new bootstrap.Tooltip(el, { trigger: 'hover', placement: 'top' });
        }
    });
});

// ── Animate number counters on page load ─────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.stat-value[data-target]').forEach(el => {
        const target = parseInt(el.dataset.target, 10) || 0;
        let current  = 0;
        const step   = Math.ceil(target / 25) || 1;
        const timer  = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = current.toLocaleString();
            if (current >= target) clearInterval(timer);
        }, 28);
    });
});
