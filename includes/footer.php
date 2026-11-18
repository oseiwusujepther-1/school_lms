</main>
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?= date('Y') ?> Essienimpong M/A Primary and KG School | School LMS</p>
            <!--<p>Faculty of Applied Sciences and Mathematics Education | ICT243 Web Programming</p>-->
            <?php if (!isLoggedIn()): ?>
            <p class="footer-links">
                <a href="<?= APP_URL ?>/teacher_register.php">Teachers Registration</a>
            </p>
            <?php endif; ?>
        </div>
    </footer>
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>