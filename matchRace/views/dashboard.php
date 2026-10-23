    <section class="hero">
        <div class="hero-content">
            <h1>Welcome, <?= $h($me['first_name']) ?>!</h1>
            <p>Your Match Race dashboard.</p>
        </div>
    </section>

    <main>
        <div class="form-container">
            <section class="section">
                <div class="dashboard-header">
                    <div>
                        <h2>My Profile</h2>
                        <span class="user-badge <?= $me['user_type'] === 'boat_owner' ? 'boat-owner' : '' ?>"><?= $h($typeLabels[$me['user_type']]) ?></span>
                    </div>
                </div>
                <div class="profile-section">
                    <div class="profile-item"><span class="profile-label">Name</span><span class="profile-value"><?= $h(trim($me['first_name'] . ' ' . $me['last_name'])) ?></span></div>
                    <div class="profile-item"><span class="profile-label">Email</span><span class="profile-value"><?= $h($me['email']) ?></span></div>
                    <div class="profile-item"><span class="profile-label">Type</span><span class="profile-value"><?= $h($typeLabels[$me['user_type']]) ?></span></div>
                </div>
                <p><a href="<?= $h($profileUrl) ?>" class="btn btn-secondary">Edit my sailConnect account</a></p>
            </section>

            <section class="section" style="margin-top: 2rem;">
                <h2>Who's Here</h2>
                <h3>⛵ Boat Owners (<?= count($owners) ?>)</h3>
<?php if ($owners === []): ?>
                <p>No boat owners yet.</p>
<?php else: ?>
                <div class="crew-list">
<?php foreach ($owners as $o): ?>
                    <span class="crew-tag"><?= $h(trim($o['first_name'] . ' ' . $o['last_name'])) ?></span>
<?php endforeach; ?>
                </div>
<?php endif; ?>
                <h3 style="margin-top: 1.5rem;">🌊 Crew (<?= count($crew) ?>)</h3>
<?php if ($crew === []): ?>
                <p>No crew yet.</p>
<?php else: ?>
                <div class="crew-list">
<?php foreach ($crew as $c): ?>
                    <span class="crew-tag"><?= $h(trim($c['first_name'] . ' ' . $c['last_name'])) ?></span>
<?php endforeach; ?>
                </div>
<?php endif; ?>
            </section>
        </div>
    </main>
