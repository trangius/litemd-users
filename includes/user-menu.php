<?php
// User menu partial rendered by the Users plugin via the 'user-menu' slot.
// Uses $isLoggedIn and $currentUser from the slot context.
?>
            <div class="user-menu-wrapper">
                <button class="user-btn" type="button" aria-expanded="false" aria-label="<?= ($isLoggedIn ?? false) ? 'Account menu' : 'Log in' ?>" title="<?= ($isLoggedIn ?? false) ? 'Account' : 'Log in' ?>">
                    <svg class="user-icon" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
                        <path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
<?php if ($isLoggedIn ?? false): ?>
                    <span class="user-logged-in-dot" aria-hidden="true"></span>
<?php endif; ?>
                </button>

                <!-- Auth dropdown panel -->
                <div class="auth-dropdown" aria-hidden="true">
<?php if ($isLoggedIn ?? false): ?>
                    <div class="auth-dropdown-logged-in">
                        <p class="auth-user-email"><?= htmlspecialchars($currentUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
<?php \LiteMD\Plugin::renderSlot('auth-dropdown-extras', ['currentUser' => $currentUser ?? null]); ?>
                        <button type="button" class="auth-changepw-toggle">Change password</button>

                        <!-- Change password form (hidden by default) -->
                        <form class="auth-changepw-form" hidden novalidate>
                            <div class="auth-form-row">
                                <input type="password" name="current_password" placeholder="Current password" required autocomplete="current-password">
                            </div>
                            <div class="auth-form-row">
                                <input type="password" name="new_password" placeholder="New password (min 8 chars)" required autocomplete="new-password" minlength="8">
                            </div>
                            <p class="auth-error" aria-live="polite"></p>
                            <button type="submit" class="auth-submit-btn">Update password</button>
                        </form>

                        <button type="button" class="auth-logout-btn">Log out</button>
                        <button type="button" class="auth-remove-btn">Remove account</button>
                    </div>
<?php else: ?>
                    <!-- Login form -->
                    <form class="auth-form" data-auth-form="login" novalidate>
                        <h3>Log in</h3>
                        <div class="auth-form-row">
                            <label class="visually-hidden" for="login-email">Email</label>
                            <input type="email" name="email" id="login-email" placeholder="Email" required autocomplete="email">
                        </div>
                        <div class="auth-form-row">
                            <label class="visually-hidden" for="login-password">Password</label>
                            <input type="password" name="password" id="login-password" placeholder="Password" required autocomplete="current-password">
                        </div>
                        <p class="auth-error" aria-live="polite"></p>
                        <button type="submit" class="auth-submit-btn">Log in</button>
                        <p class="auth-switch">No account? <button type="button" class="auth-switch-btn" data-show="register">Create one</button></p>
                    </form>

                    <!-- Register form -->
                    <form class="auth-form" data-auth-form="register" hidden novalidate>
                        <h3>Create account</h3>
                        <div class="auth-form-row">
                            <label class="visually-hidden" for="register-email">Email</label>
                            <input type="email" name="email" id="register-email" placeholder="Email" required autocomplete="email">
                        </div>
                        <div class="auth-form-row">
                            <label class="visually-hidden" for="register-password">Password</label>
                            <input type="password" name="password" id="register-password" placeholder="Password (min 8 chars)" required autocomplete="new-password" minlength="8">
                        </div>
                        <div class="auth-form-row">
                            <label class="visually-hidden" for="register-password-confirm">Confirm password</label>
                            <input type="password" name="password_confirm" id="register-password-confirm" placeholder="Confirm password" required autocomplete="new-password" minlength="8">
                        </div>
                        <p class="auth-error" aria-live="polite"></p>
                        <button type="submit" class="auth-submit-btn">Create account</button>
                        <p class="auth-switch">Already have an account? <button type="button" class="auth-switch-btn" data-show="login">Log in</button></p>
                    </form>
<?php endif; ?>
                </div>
            </div>
