<?php
// templates/footer.php - Common footer template
?>
    </main>
    
    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">
                        &copy; <?php echo date('Y'); ?> CyberGuardX Integrated Solutions LLC. All rights reserved.
                    </span>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        Invoice System v1.0 | 
                        <?php if (isset($_SESSION['user_id'])): ?>
                            Logged in as: <?php echo htmlspecialchars($_SESSION['username'] ?? 'user'); ?>
                        <?php else: ?>
                            Not logged in
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/assets/js/invoice-calc.js"></script>
    
    <!-- Additional JS (optional) -->
    <?php if (isset($custom_js)): ?>
        <script src="<?php echo $custom_js; ?>"></script>
    <?php endif; ?>
    
    <?php if (isset($additional_scripts)): ?>
        <?php echo $additional_scripts; ?>
    <?php endif; ?>
</body>
</html>
