// Users plugin: auth dropdown handler. Toggle on button click, close on
// outside click or Escape, handle login/register form submissions, logout,
// account deletion, and form switching.
(function () {
    "use strict";

    function init() {
        var wrappers = document.querySelectorAll('.user-menu-wrapper');
        if (wrappers.length === 0) return;

        var authUrl = (document.querySelector('base') || {}).href || '';
        var apiBase = authUrl ? authUrl.replace(/\/$/, '') + '/auth' : 'auth';

        // Helper: POST a JSON body to the auth endpoint
        function authRequest(action, extra) {
            return fetch(apiBase, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ action: action }, extra || {}))
            });
        }

        // Helper: position a fixed-position dropdown next to its trigger button.
        // If the dropdown uses position:fixed (e.g. console theme sidebar), place
        // it to the right of the button; otherwise leave CSS positioning alone.
        function positionDropdown(triggerBtn, dropdown) {
            var style = window.getComputedStyle(dropdown);
            if (style.position !== 'fixed') return;
            var rect = triggerBtn.getBoundingClientRect();
            dropdown.style.top = rect.top + 'px';
            dropdown.style.left = (rect.right + 8) + 'px';
        }

        // Helper: close every auth dropdown on the page
        function closeAll() {
            wrappers.forEach(function (w) {
                w.querySelector('.auth-dropdown').setAttribute('aria-hidden', 'true');
                w.querySelector('.user-btn').setAttribute('aria-expanded', 'false');
            });
        }

        // Bind per-wrapper handlers (themes may emit multiple copies of
        // the user menu — e.g. one in the core header for mobile, another
        // in the console sidebar for desktop)
        wrappers.forEach(function (wrapper) {
            var btn = wrapper.querySelector('.user-btn');
            var dd = wrapper.querySelector('.auth-dropdown');
            if (!btn || !dd) return;

            // Toggle dropdown visibility on button click
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = dd.getAttribute('aria-hidden') !== 'true';
                closeAll();
                if (!isOpen) {
                    dd.setAttribute('aria-hidden', 'false');
                    btn.setAttribute('aria-expanded', 'true');
                    positionDropdown(btn, dd);
                }
            });

            // Handle login and register form submissions
            dd.querySelectorAll('.auth-form').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var action = form.getAttribute('data-auth-form');
                    var email = form.querySelector('[name="email"]').value.trim();
                    var password = form.querySelector('[name="password"]').value;
                    var errorEl = form.querySelector('.auth-error');
                    var submitBtn = form.querySelector('.auth-submit-btn');

                    errorEl.textContent = '';
                    submitBtn.disabled = true;

                    authRequest(action, { email: email, password: password })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            window.location.reload();
                        } else {
                            errorEl.textContent = data.error || 'Something went wrong.';
                            submitBtn.disabled = false;
                        }
                    })
                    .catch(function () {
                        errorEl.textContent = 'Network error. Please try again.';
                        submitBtn.disabled = false;
                    });
                });
            });

            // Handle logout button
            var logoutBtn = dd.querySelector('.auth-logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function () {
                    authRequest('logout')
                    .then(function () { window.location.reload(); })
                    .catch(function () { window.location.reload(); });
                });
            }

            // Handle remove account button
            var removeBtn = dd.querySelector('.auth-remove-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    if (!window.confirm('Are you sure you want to delete your account? This cannot be undone.')) return;
                    authRequest('delete-account')
                    .then(function () { window.location.reload(); })
                    .catch(function () { window.location.reload(); });
                });
            }

            // Handle switching between login and register forms
            dd.querySelectorAll('.auth-switch-btn').forEach(function (switchBtn) {
                switchBtn.addEventListener('click', function () {
                    var target = switchBtn.getAttribute('data-show');
                    dd.querySelectorAll('.auth-form').forEach(function (f) {
                        var isTarget = f.getAttribute('data-auth-form') === target;
                        f.hidden = !isTarget;
                        if (isTarget) {
                            f.querySelector('.auth-error').textContent = '';
                        }
                    });
                });
            });
        });

        // Close all dropdowns when clicking outside any wrapper
        document.addEventListener('click', function (e) {
            var insideAny = false;
            wrappers.forEach(function (w) {
                if (w.contains(e.target)) insideAny = true;
            });
            if (!insideAny) closeAll();
        });

        // Close all dropdowns on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAll();
        });

        // Open the auth dropdown when any #login link is clicked (e.g. from
        // the members-only page). Opens the first visible dropdown.
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href$="#login"]');
            if (!link) return;
            e.preventDefault();
            for (var i = 0; i < wrappers.length; i++) {
                var btn = wrappers[i].querySelector('.user-btn');
                if (btn && btn.offsetParent !== null) {
                    var dd = wrappers[i].querySelector('.auth-dropdown');
                    dd.setAttribute('aria-hidden', 'false');
                    btn.setAttribute('aria-expanded', 'true');
                    var firstInput = dd.querySelector('input');
                    if (firstInput) firstInput.focus();
                    break;
                }
            }
        });
    }

    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
