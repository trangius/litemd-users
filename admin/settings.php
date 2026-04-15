<?php
// SMTP settings panel for the Users plugin (rendered inside the Advanced page)
$usersConfig = \LiteMD\Config::getPluginConfig('users', []);
$smtp = $usersConfig['smtp'] ?? [];
?>

            <div class="advanced-form" style="max-width:500px;padding:1.25rem">
                <h2 class="advanced-section-title" style="margin-top:0">SMTP Settings</h2>
                <p class="advanced-section-desc">Configure outgoing email for password resets and notifications.</p>

                <label class="advanced-field">
                    <span class="advanced-label">SMTP Host</span>
                    <input type="text" id="users-smtp-host" class="advanced-input" placeholder="smtp.example.com" value="<?= htmlspecialchars((string) ($smtp['host'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="advanced-field">
                    <span class="advanced-label">Port</span>
                    <input type="number" id="users-smtp-port" class="advanced-input" placeholder="587" value="<?= htmlspecialchars((string) ($smtp['port'] ?? '587'), ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="advanced-field">
                    <span class="advanced-label">Encryption</span>
                    <select id="users-smtp-encryption" class="advanced-input">
                        <option value="tls"<?= (($smtp['encryption'] ?? 'tls') === 'tls') ? ' selected' : '' ?>>TLS (STARTTLS)</option>
                        <option value="ssl"<?= (($smtp['encryption'] ?? '') === 'ssl') ? ' selected' : '' ?>>SSL</option>
                        <option value="none"<?= (($smtp['encryption'] ?? '') === 'none') ? ' selected' : '' ?>>None</option>
                    </select>
                </label>

                <label class="advanced-field">
                    <span class="advanced-label">Username</span>
                    <input type="text" id="users-smtp-username" class="advanced-input" value="<?= htmlspecialchars((string) ($smtp['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="advanced-field">
                    <span class="advanced-label">Password</span>
                    <input type="password" id="users-smtp-password" class="advanced-input" value="<?= htmlspecialchars((string) ($smtp['password'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="advanced-field">
                    <span class="advanced-label">From Email</span>
                    <input type="email" id="users-smtp-from-email" class="advanced-input" placeholder="noreply@example.com" value="<?= htmlspecialchars((string) ($smtp['from_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="advanced-field">
                    <span class="advanced-label">From Name</span>
                    <input type="text" id="users-smtp-from-name" class="advanced-input" placeholder="My Site" value="<?= htmlspecialchars((string) ($smtp['from_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="advanced-field">
                    <span class="advanced-label">Email Footer</span>
                    <textarea id="users-smtp-email-footer" class="advanced-input" rows="3" placeholder="Optional text appended to every outgoing email"><?= htmlspecialchars((string) ($smtp['email_footer'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>

                <!-- Live preview of the password reset email -->
                <div class="advanced-field">
                    <span class="advanced-label">Email Preview</span>
                    <div id="users-email-preview" style="border:1px solid var(--border);border-radius:var(--border-radius);padding:1rem;background:var(--background);font-size:0.85rem;line-height:1.5;color:var(--text)">
                    </div>
                </div>

                <div class="advanced-actions">
                    <button class="advanced-btn advanced-btn-primary" id="users-smtp-save">Save</button>
                </div>

                <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border)">

                <h2 class="advanced-section-title">Test Email</h2>
                <p class="advanced-section-desc">Send a test email to verify your SMTP settings.</p>

                <label class="advanced-field">
                    <span class="advanced-label">Recipient</span>
                    <input type="email" id="users-smtp-test-email" class="advanced-input" placeholder="you@example.com">
                </label>

                <div class="advanced-actions">
                    <button class="advanced-btn" id="users-smtp-test">Send test email</button>
                </div>
            </div>

<script>
(function () {
    var saveBtn = document.getElementById("users-smtp-save");
    var testBtn = document.getElementById("users-smtp-test");
    var footerInput = document.getElementById("users-smtp-email-footer");
    var previewEl = document.getElementById("users-email-preview");
    if (!saveBtn) return;

    // Render the email preview with the current site name and footer text
    var siteName = <?= json_encode(\LiteMD\Config::get('site_name', 'Your Site'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    function updatePreview() {
        var footer = footerInput.value.trim();
        var html = '<p>You requested a password reset for your account at ' + siteName + '.</p>'
            + '<p><a href="#">Click here to reset your password</a></p>'
            + '<p>This link expires in 1 hour. If you did not request this, you can ignore this email.</p>';
        if (footer) {
            html += '<p>' + footer.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\n/g, '<br>') + '</p>';
        }
        previewEl.innerHTML = html;
    }
    footerInput.addEventListener("input", updatePreview);
    updatePreview();

    // Save SMTP settings
    saveBtn.addEventListener("click", function () {
        EditorUtils.apiPost("users-smtp-save", {
            host: document.getElementById("users-smtp-host").value,
            port: parseInt(document.getElementById("users-smtp-port").value, 10) || 587,
            encryption: document.getElementById("users-smtp-encryption").value,
            username: document.getElementById("users-smtp-username").value,
            password: document.getElementById("users-smtp-password").value,
            from_email: document.getElementById("users-smtp-from-email").value,
            from_name: document.getElementById("users-smtp-from-name").value,
            email_footer: footerInput.value,
            csrf: (window.EDITOR_CONFIG || {}).csrfToken || ""
        }).then(function () {
            alert("SMTP settings saved.");
        }).catch(function (err) {
            alert(err.message || "Failed to save.");
        });
    });

    // Send test email
    if (testBtn) {
        testBtn.addEventListener("click", function () {
            var email = document.getElementById("users-smtp-test-email").value.trim();
            if (!email) { alert("Enter a recipient email address."); return; }

            testBtn.disabled = true;
            testBtn.textContent = "Sending...";

            EditorUtils.apiPost("users-smtp-test", {
                test_email: email,
                csrf: (window.EDITOR_CONFIG || {}).csrfToken || ""
            }).then(function () {
                alert("Test email sent to " + email + ".");
                testBtn.disabled = false;
                testBtn.textContent = "Send test email";
            }).catch(function (err) {
                alert("Failed: " + (err.message || "Unknown error"));
                testBtn.disabled = false;
                testBtn.textContent = "Send test email";
            });
        });
    }
})();
</script>
