<?php
/**
 * Shared Footer Layout Template
 * Crown Basketball Academy
 */
?>
            </main>
            
            <footer style="padding: 20px 30px; text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.05); font-size: 0.85rem; color: var(--text-secondary); background-color: var(--bg-secondary);">
                &copy; <?= date('Y') ?> Crown Basketball Academy. All Rights Reserved.
            </footer>
            
        </div>
    </div>

    <!-- Main Client Script -->
    <script src="assets/js/main.js"></script>
    
    <!-- Render server flash alerts directly into toast notifier -->
    <?php renderFlashMessages(); ?>

</body>
</html>
