<?php
/**
 * Global Footer Component
 */
$currentTimeStr = format_datetime(date('Y-m-d H:i:s'), 'd M Y, h:i:s A');
?>
        </div><!-- /.container -->
    </main><!-- /.main-content -->

    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div>
                    <strong><?= e(APP_NAME) ?></strong> &bull; Monitored 24/7 with instant Telegram alerts
                </div>
                <div>
                    Timezone: <code><?= e(APP_TIMEZONE) ?></code> (MYT: <?= $currentTimeStr ?>)
                </div>
            </div>
        </div>
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/script.js"></script>
</body>
</html>
