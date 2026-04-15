<?php
// Standalone reset-password page served at /reset-password?token=...
// Receives $token, $basePath, $baseHref, $siteName, $cssHrefs from the caller.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <base href="<?= htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') ?>">
<?php foreach ($cssHrefs as $href): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
<?php endforeach; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/plugins/users/assets/users.css">
    <style>
        .reset-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            background: var(--background-alt, #f5f5f5);
        }
        .reset-card {
            width: 100%;
            max-width: 380px;
            background: var(--background, #fff);
            border: 1px solid var(--border, #ddd);
            border-radius: var(--border-radius, 6px);
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .reset-card h2 {
            margin: 0 0 1rem;
            font-size: 1.3rem;
        }
        .reset-success {
            text-align: center;
        }
        .reset-success p {
            margin: 0.5rem 0;
            color: var(--text-light, #666);
        }
        .reset-success a {
            color: var(--primary-base, #3b82f6);
        }
    </style>
</head>
<body>
    <div class="reset-page">
        <div class="reset-card">
            <h2>Reset password</h2>
            <form id="reset-form" novalidate>
                <div class="auth-form-row">
                    <input type="password" name="new_password" placeholder="New password (min 8 chars)" required autocomplete="new-password" minlength="8">
                </div>
                <div class="auth-form-row">
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required autocomplete="new-password" minlength="8">
                </div>
                <p class="auth-error" aria-live="polite"></p>
                <button type="submit" class="auth-submit-btn">Reset password</button>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var form = document.getElementById("reset-form");
        var token = <?= json_encode($token, JSON_UNESCAPED_SLASHES) ?>;
        var authUrl = <?= json_encode(rtrim($baseHref, '/') . '/auth', JSON_UNESCAPED_SLASHES) ?>;
        var homeUrl = <?= json_encode($baseHref, JSON_UNESCAPED_SLASHES) ?>;

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var pw = form.querySelector('[name="new_password"]').value;
            var confirm = form.querySelector('[name="confirm_password"]').value;
            var errorEl = form.querySelector(".auth-error");
            var submitBtn = form.querySelector(".auth-submit-btn");

            errorEl.textContent = "";

            if (pw.length < 8) {
                errorEl.textContent = "Password must be at least 8 characters.";
                return;
            }
            if (pw !== confirm) {
                errorEl.textContent = "Passwords do not match.";
                return;
            }

            submitBtn.disabled = true;

            fetch(authUrl, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "reset-password", token: token, new_password: pw })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    document.querySelector(".reset-card").innerHTML =
                        '<div class="reset-success">' +
                        '<h2>Password reset</h2>' +
                        '<p>Your password has been updated.</p>' +
                        '<p><a href="' + homeUrl + '">Return to site</a></p>' +
                        '</div>';
                } else {
                    errorEl.textContent = data.error || "Something went wrong.";
                    submitBtn.disabled = false;
                }
            })
            .catch(function () {
                errorEl.textContent = "Network error. Please try again.";
                submitBtn.disabled = false;
            });
        });
    })();
    </script>
</body>
</html>
