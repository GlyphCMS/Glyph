<?php declare(strict_types=1); ?>
<?php
/** @var string $siteName */
/** @var Glyph\services\themes\ThemeView $__theme */
?>
<?php $primaryNav = $__theme->navigationHtml('primary', 'site-header__nav-list'); ?>
<header class="site-header">
    <div class="site-header__inner">
        <a class="site-header__brand" href="/">
            <?php if ($__theme->siteLogo() !== ''): ?>
                <img class="site-header__logo-image" src="<?= $__theme->escape($__theme->siteBrandImage()) ?>" alt="<?= $__theme->escape($__theme->siteName()) ?>">
            <?php endif; ?>
            <?php if ($__theme->siteLogoShowName()): ?>
                <span class="site-header__site-name"><?= $__theme->escape($__theme->siteName()) ?></span>
            <?php endif; ?>
        </a>

        <?php if ($primaryNav !== ''): ?>
            <button class="site-header__menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="site-header-nav" data-site-header-toggle>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="4" y1="7" x2="20" y2="7" /><line x1="4" y1="12" x2="20" y2="12" /><line x1="4" y1="17" x2="20" y2="17" /></svg>
            </button>
        <?php endif; ?>

        <div class="site-header__search">
            <form class="site-header__search-form" method="get" action="/search">
                <input class="site-header__search-input" type="search" name="q" placeholder="Search..." aria-label="Search site">
                <button class="site-header__search-btn" type="submit" aria-label="Submit search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                </button>
            </form>
        </div>

        <?php if ($primaryNav !== ''): ?>
            <nav class="site-header__nav" id="site-header-nav" data-site-header-nav data-mobile-open="false" aria-label="Primary">
                <?= $primaryNav ?>
            </nav>
        <?php else: ?>
            <nav class="site-header__nav" aria-label="Primary"></nav>
        <?php endif; ?>
    </div>
</header>
<?php if ($primaryNav !== ''): ?>
<script>
(() => {
    const toggle = document.querySelector('[data-site-header-toggle]');
    const nav = document.querySelector('[data-site-header-nav]');

    if (!toggle || !nav) {
        return;
    }

    const setOpen = (isOpen) => {
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        nav.dataset.mobileOpen = isOpen ? 'true' : 'false';
    };

    toggle.addEventListener('click', () => {
        setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    document.addEventListener('click', (event) => {
        if (window.innerWidth > 720) {
            return;
        }

        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        if (toggle.contains(target) || nav.contains(target)) {
            return;
        }

        setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 720) {
            setOpen(false);
        }
    });
})();
</script>
<?php endif; ?>
