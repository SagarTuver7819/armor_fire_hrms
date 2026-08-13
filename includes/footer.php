<?php
/**
 * Common Footer
 */
require_once __DIR__ . '/../config/app.php';
?>
<?php if (!empty($useSidebar)): ?>
        </div><!-- /.app-content -->
    </div><!-- /.app-shell -->
<?php endif; ?>
    <footer class="page-footer">
        <div class="footer-left">2026 &copy; Armor Fire HRMS</div>
        <div class="footer-right">Designed &amp; Developed by Ocean Infotech</div>
    </footer>

    <!-- Delete confirmation popup -->
    <div id="confirmDeleteModal" class="confirm-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="confirmDeleteTitle">
        <div class="confirm-modal-backdrop"></div>
        <div class="confirm-modal-box">
            <div class="confirm-modal-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 id="confirmDeleteTitle">Are you sure?</h3>
            <p class="confirm-modal-msg">Are you sure you want to delete this record?</p>
            <div class="confirm-modal-actions">
                <button type="button" class="btn-secondary confirm-cancel">Cancel</button>
                <button type="button" class="btn-primary confirm-yes">
                    <i class="fa-solid fa-trash"></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>

    <script src="<?php echo app_url('assets/js/dashboard.js'); ?>"></script>
    <script src="<?php echo app_url('assets/js/confirm_delete.js'); ?>"></script>
    <?php if (!empty($useSidebar)): ?>
        <script src="<?php echo app_url('assets/js/sidebar.js'); ?>"></script>
    <?php endif; ?>
    <?php if (!empty($extraJs) && is_array($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?php echo htmlspecialchars((strpos($js, 'http') === 0) ? $js : app_url($js)); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
