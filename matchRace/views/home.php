    <section class="hero">
        <div class="hero-content">
            <h1>Match Race</h1>
            <p>Match boats and crew for racing.</p>
            <p style="margin-top: 2rem;">
<?php if ($enrolled): ?>
                <a href="/dashboard" class="btn btn-accent">Go to Dashboard</a>
<?php else: ?>
                <a href="/join" class="btn btn-accent">Join Now</a>
<?php endif; ?>
            </p>
        </div>
    </section>

    <main>
        <div class="card-grid">
            <div class="card">
                <h3>⛵ Boat Owners</h3>
                <p>List your boat and find crew for the match race series.</p>
            </div>
            <div class="card">
                <h3>🌊 Crew</h3>
                <p>Tell skippers you are ready to race and get matched to a boat.</p>
            </div>
        </div>
    </main>
