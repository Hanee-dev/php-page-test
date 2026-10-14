    </main>
    <footer class="site-footer">
        <nav aria-label="안내">
            <a href="<?= e(url('about')) ?>">편지는 이렇게 보관됩니다</a>
            <a href="<?= e(url('privacy')) ?>">개인정보처리방침</a>
        </nav>
        <p>&copy; <?= date('Y') ?> <?= e($appName) ?></p>
    </footer>
    <script src="<?= e(asset('js/main.js')) ?>" defer></script>
</body>
</html>
