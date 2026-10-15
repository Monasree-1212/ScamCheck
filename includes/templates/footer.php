</div><!-- /.main-wrapper -->

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-top">
            <div>
                <div class="footer-brand">
                    <span style="color: var(--navy);"><?= \ScamCheck\Helper::icon('shield-check', 20) ?></span>
                    <span>ScamCheck</span>
                </div>
                <p class="footer-desc">
                    A dedicated platform for college students and job seekers to identify, report, and verify deceptive recruitment offers using high-performance Redis key-value storage.
                </p>
            </div>

            <div>
                <div class="footer-col-title">Navigation</div>
                <ul class="footer-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="reports.php">Browse Verified Reports</a></li>
                    <li><a href="submit-report.php">Submit a Scam Report</a></li>
                    <li><a href="index.php#how-it-works">How the Platform Works</a></li>
                    <li><a href="index.php#about">About the Architecture</a></li>
                </ul>
            </div>

            <div>
                <div class="footer-col-title">Account &amp; Security</div>
                <ul class="footer-links">
                    <?php if (\ScamCheck\Auth::check()): ?>
                        <li><a href="dashboard.php">User Dashboard</a></li>
                        <?php if (\ScamCheck\Auth::isAdmin()): ?>
                            <li><a href="admin-dashboard.php">Moderation Queue</a></li>
                            <li><a href="admin-users.php">Manage Users</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php">Sign Out</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Sign In</a></li>
                        <li><a href="register.php">Create Free Account</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-disclaimer">
                <strong>Disclaimer:</strong> Risk scores and published incident narratives are community-sourced indicators evaluated by platform moderators. They serve educational and awareness purposes to assist job seekers in due diligence.
            </div>
            <div>&copy; <?= date('Y') ?> ScamCheck &bull; Built with PHP &amp; Redis</div>
        </div>
    </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
