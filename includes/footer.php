    <!-- end .cirms-main -->
</main>

<!-- ── Footer ───────────────────────────────────────────── -->
<footer class="cirms-footer">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-md-6 text-muted small">
                &copy; <?= date('Y') ?> <?= APP_FULL_NAME ?> &nbsp;|&nbsp; Version <?= APP_VERSION ?>
            </div>
            <div class="col-md-6 text-md-end text-muted small">
                Secured with HTTPS &bull; Data encrypted at rest
            </div>
        </div>
    </div>
</footer>

</div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js (loaded here so all pages can use it) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<!-- CIRMS Core JS (same base URL rules as CSS in head_assets.php) -->
<script src="<?= e(asset_url('public/js/cirms.js')) ?>"></script>

</body>
</html>
