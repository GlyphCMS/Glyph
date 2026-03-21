<?php declare(strict_types=1); ?>
<?php /** @var Glyph\services\themes\ThemeView $__theme */ ?>
<?php $footerNav = $__theme->navigationHtml('footer', 'site-footer__nav-list'); ?>
<footer class="site-footer">
    <div class="site-footer__inner">
        <a class="site-footer__brand" href="/">
            <?php if ($__theme->siteLogo() !== ''): ?>
                <img class="site-footer__brand-image" src="<?= $__theme->escape($__theme->siteBrandImage()) ?>" alt="<?= $__theme->escape($__theme->siteName()) ?>">
            <?php endif; ?>
            <?php if ($__theme->siteLogoShowName()): ?>
                <span class="site-footer__brand-text"><?= $__theme->escape($__theme->siteName()) ?></span>
            <?php endif; ?>
        </a>
        <?php if ($footerNav !== ''): ?>
            <nav class="site-footer__nav" aria-label="Footer" data-site-footer-nav>
                <?= $footerNav ?>
            </nav>
        <?php endif; ?>

        <span class="site-footer__copy">Powered by <a class="powered-by-glyph" href="https://glyphcms.com" target="_blank" rel="noopener"><img src="/assets/branding/glyph-app-icon-256.png" alt="Glyph"><span>Glyph</span></a></span>
    </div>
</footer>
<?php if ($footerNav !== ''): ?>
<script>
(() => {
    const nav = document.querySelector('[data-site-footer-nav]');

    if (!nav) {
        return;
    }

    const isMobile = () => window.innerWidth <= 720;
    const items = Array.from(nav.querySelectorAll('.site-nav__item--has-children'));

    const setOpenItem = (nextItem) => {
        items.forEach((item) => {
            const isOpen = item === nextItem;
            const link = item.firstElementChild;
            item.dataset.mobileOpen = isOpen ? 'true' : 'false';

            if (link instanceof HTMLAnchorElement) {
                link.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
        });
    };

    items.forEach((item) => {
        const link = item.firstElementChild;
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        link.addEventListener('click', (event) => {
            if (!isMobile()) {
                return;
            }

            const isOpen = item.dataset.mobileOpen === 'true';
            if (!isOpen) {
                event.preventDefault();
                setOpenItem(item);
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!isMobile()) {
            return;
        }

        const target = event.target;
        if (!(target instanceof Element) || nav.contains(target)) {
            return;
        }

        setOpenItem(null);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpenItem(null);
        }
    });

    window.addEventListener('resize', () => {
        if (!isMobile()) {
            setOpenItem(null);
        }
    });
})();
</script>
<?php endif; ?>
