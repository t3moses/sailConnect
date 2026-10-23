    <section class="hero">
        <div class="hero-content">
            <h1>Let's Get You Racing!</h1>
            <p>First things first—are you crew or captain?</p>
        </div>
    </section>

    <main>
        <div class="form-container">
            <section class="section">
                <h2>I Am A...</h2>
<?php if ($error !== ''): ?>
                <div class="form-error"><?= $h($error) ?></div>
<?php endif; ?>
                <form action="/join" method="post">
                    <div class="form-group">
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="crew" name="user_type" value="crew" required<?= $selected === 'crew' ? ' checked' : '' ?>>
                                <label for="crew">
                                    <div style="font-size: 4rem; margin-bottom: 1rem;">🌊</div>
                                    <div style="font-size: 1.3rem; margin-bottom: 0.5rem;">Crew Member</div>
                                    <div style="font-size: 0.95rem; font-weight: 400; color: var(--text-gray); margin-top: 1rem;">I want to race as crew</div>
                                </label>
                            </div>

                            <div class="radio-option">
                                <input type="radio" id="boat_owner" name="user_type" value="boat_owner" required<?= $selected === 'boat_owner' ? ' checked' : '' ?>>
                                <label for="boat_owner">
                                    <div style="font-size: 4rem; margin-bottom: 1rem;">⛵</div>
                                    <div style="font-size: 1.3rem; margin-bottom: 0.5rem;">Boat Owner</div>
                                    <div style="font-size: 0.95rem; font-weight: 400; color: var(--text-gray); margin-top: 1rem;">I want to host crew</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 3rem;">
                        <button type="submit" class="btn btn-primary"><?= $signedIn ? "Let's Go!" : 'Continue' ?></button>
                    </div>
                </form>
            </section>

            <div class="alert alert-info nav-unauthenticated" style="margin-top: 2rem;">
                Next you will create your sailConnect account, or sign in if you already have one. <strong>Already have an account?</strong> <a href="/signin" style="color: var(--teal); font-weight: 600;">Sign in</a>.
            </div>
        </div>
    </main>
