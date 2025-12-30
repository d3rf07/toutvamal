<!-- Footer -->
<footer class="footer">
    <div class="footer-grid">
        <div class="footer-brand">
            <div class="logo">TOUT<span>VA</span>MAL</div>
            <div class="tagline"><?= TAGLINE ?></div>
            <div class="footer-social">
                <a href="https://twitter.com/toutvamal" target="_blank" rel="noopener" aria-label="Twitter">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <a href="https://facebook.com/toutvamal" target="_blank" rel="noopener" aria-label="Facebook">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
            </div>
        </div>

        <div class="footer-column">
            <h5>Navigation</h5>
            <ul>
                <li><a href="/">Accueil</a></li>
                <li><a href="/equipe.html">L'Équipe</a></li>
                <li><a href="/a-propos.html">À Propos</a></li>
                <li><a href="/contact.html">Contact</a></li>
            </ul>
        </div>

        <div class="footer-column">
            <h5>Rubriques</h5>
            <ul>
                <?php foreach (CATEGORIES as $slug => $name): ?>
                    <li><a href="/?cat=<?= $slug ?>"><?= $name ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="footer-column">
            <h5>Légal</h5>
            <ul>
                <li><a href="/mentions-legales.html">Mentions légales</a></li>
                <li><a href="/cgu.html">CGU</a></li>
                <li><a href="/confidentialite.html">Confidentialité</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> ToutVaMal.fr - Site satirique. Toute ressemblance avec la réalité serait purement fortuite (ou pas).</p>
    </div>
</footer>

<script>
// Newsletter form
document.querySelectorAll('.newsletter-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = form.querySelector('input[type="email"]').value;
        const btn = form.querySelector('button');
        const originalText = btn.textContent;

        btn.textContent = 'Envoi...';
        btn.disabled = true;

        try {
            const res = await fetch('/api/newsletter.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });
            const data = await res.json();

            if (data.success) {
                btn.textContent = 'Inscrit !';
                form.querySelector('input').value = '';
            } else {
                btn.textContent = data.error || 'Erreur';
            }
        } catch (err) {
            btn.textContent = 'Erreur réseau';
        }

        setTimeout(() => {
            btn.textContent = originalText;
            btn.disabled = false;
        }, 3000);
    });
});
</script>
</body>
</html>
