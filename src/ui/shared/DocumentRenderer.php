<?php

declare(strict_types=1);

namespace Glyph\ui\shared;

use Glyph\services\plugins\HookManager;
use Glyph\ui\admin\AdminNavigation;

final class DocumentRenderer
{
    private static string $defaultAdminUserEmail = '';
    private static string $defaultAdminUserRole = '';
    private static string $defaultAdminUserDisplayName = '';
    private static string $defaultAdminFaviconHref = '';

    public static function setAdminUserContext(string $email, string $role, string $displayName = ""): void
    {
        self::$defaultAdminUserEmail = $email;
        self::$defaultAdminUserRole = $role;
        self::$defaultAdminUserDisplayName = $displayName;
    }

    public static function setAdminBrandingContext(string $faviconHref = ''): void
    {
        self::$defaultAdminFaviconHref = trim($faviconHref);
    }

    public function __construct(
        private readonly ?HookManager $hookManager = null,
    ) {
    }

    /**
     * @param array<string, string> $meta
     */
    public function render(
        string $title,
        string $content,
        ?string $metaDescription = null,
        string $bodyClass = '',
        string $currentUserEmail = '',
        string $currentUserRole = '',
        array $meta = [],
        string $currentUserDisplayName = '',
    ): string {
        if ($this->hookManager !== null) {
            $title = (string) $this->hookManager->applyFilters('document.title', $title, $bodyClass);
            $metaDescription = $metaDescription !== null
                ? (string) $this->hookManager->applyFilters('document.meta_description', $metaDescription, $bodyClass)
                : null;
            $content = (string) $this->hookManager->applyFilters('document.content', $content, $bodyClass);
        }

        $customFaviconHref = trim((string) ($meta['favicon_href'] ?? ''));
        if ($customFaviconHref === '' && in_array($bodyClass, ['theme-admin', 'theme-auth'], true)) {
            $customFaviconHref = self::$defaultAdminFaviconHref;
        }
        $favicon16Href = $this->versionedAssetPath('/assets/branding/glyph-favicon-16.ico');
        $favicon32Href = $this->versionedAssetPath('/assets/branding/glyph-favicon-32.ico');
        $appIconHref = $this->versionedAssetPath('/assets/branding/glyph-app-icon-256.png');
        $glyphStylesheetHref = $this->versionedAssetPath('/assets/glyph.css');

        $head = '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        $head .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
        $head .= '<title>' . $this->escape($title) . '</title>';

        if ($metaDescription !== null && $metaDescription !== '') {
            $head .= '<meta name="description" content="' . $this->escape($metaDescription) . '">';
        }

        if (($meta['robots'] ?? '') !== '') {
            $head .= '<meta name="robots" content="' . $this->escape($meta['robots']) . '">';
        }

        if (($meta['canonical_url'] ?? '') !== '') {
            $head .= '<link rel="canonical" href="' . $this->escape($meta['canonical_url']) . '">';
        }

        $ogType = $meta['og_type'] ?? 'website';
        $head .= '<meta property="og:title" content="' . $this->escape($title) . '">';
        if ($metaDescription !== null && $metaDescription !== '') {
            $head .= '<meta property="og:description" content="' . $this->escape($metaDescription) . '">';
        }
        $head .= '<meta property="og:type" content="' . $this->escape($ogType) . '">';
        if (($meta['canonical_url'] ?? '') !== '') {
            $head .= '<meta property="og:url" content="' . $this->escape($meta['canonical_url']) . '">';
        }
        if (($meta['site_name'] ?? '') !== '') {
            $head .= '<meta property="og:site_name" content="' . $this->escape($meta['site_name']) . '">';
        }
        if (($meta['og_image'] ?? '') !== '') {
            $head .= '<meta property="og:image" content="' . $this->escape($meta['og_image']) . '">';
        }

        $twitterCard = $meta['twitter_card'] ?? (($meta['og_image'] ?? '') !== '' ? 'summary_large_image' : 'summary');
        $head .= '<meta name="twitter:card" content="' . $this->escape($twitterCard) . '">';
        $head .= '<meta name="twitter:title" content="' . $this->escape($title) . '">';
        if ($metaDescription !== null && $metaDescription !== '') {
            $head .= '<meta name="twitter:description" content="' . $this->escape($metaDescription) . '">';
        }
        if (($meta['og_image'] ?? '') !== '') {
            $head .= '<meta name="twitter:image" content="' . $this->escape($meta['og_image']) . '">';
        }

        if ($customFaviconHref !== '') {
            $head .= '<link rel="icon" href="' . $this->escape($customFaviconHref) . '">';
        } else {
            $head .= '<link rel="icon" href="' . $this->escape($favicon16Href) . '" sizes="16x16">';
            $head .= '<link rel="icon" href="' . $this->escape($favicon32Href) . '" sizes="32x32">';
        }
        $head .= '<link rel="apple-touch-icon" href="' . $this->escape($appIconHref) . '">';
        $head .= '<meta name="application-name" content="Glyph">';
        $head .= '<meta name="theme-color" content="#0d0f12">';
        $head .= '<style>' . $this->criticalShellStyles($bodyClass) . '</style>';
        $head .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
        $head .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        $head .= '<link rel="preload" href="' . $this->escape($glyphStylesheetHref) . '" as="style">';
        $head .= '<link rel="stylesheet" href="' . $this->escape($glyphStylesheetHref) . '">';

        if ($this->hookManager !== null) {
            $head .= $this->hookManager->renderSlot('document.head');
        }

        $head .= '</head>';

        $bodyClassAttribute = $bodyClass !== '' ? ' class="' . $this->escape($bodyClass) . '"' : '';
        $body = '<body' . $bodyClassAttribute . '>';

        if ($this->hookManager !== null) {
            $body .= $this->hookManager->renderSlot('document.body_start', ['body_class' => $bodyClass]);
        }

        if ($bodyClass === 'theme-admin') {
            $currentUserEmail = $currentUserEmail !== '' ? $currentUserEmail : self::$defaultAdminUserEmail;
            $currentUserRole = $currentUserRole !== '' ? $currentUserRole : self::$defaultAdminUserRole;
            $currentUserDisplayName = $currentUserDisplayName !== '' ? $currentUserDisplayName : self::$defaultAdminUserDisplayName;
            $body .= '<div class="admin-shell">';
            $body .= $this->renderAdminSidebar($currentUserEmail, $currentUserRole, $currentUserDisplayName);
            $body .= '<div class="admin-body">';
            $body .= $content;
            $body .= $this->renderAdminFooter();
            $body .= '</div></div>';
        } else {
            $body .= $content;
        }

        if ($this->hookManager !== null) {
            $body .= $this->hookManager->renderSlot('document.body_end', ['body_class' => $bodyClass]);
        }

        $body .= '</body></html>';

        return $head . $body;
    }

    public function renderPoweredByGlyph(): string
    {
        return 'Powered by <a class="powered-by-glyph" href="https://glyphcms.com" target="_blank" rel="noopener"><img src="/assets/branding/glyph-app-icon-256.png" alt="Glyph"><span>Glyph</span></a>';
    }

    private function renderAdminFooter(): string
    {
        return '<footer class="admin-footer"><div class="admin-footer__inner"><span class="site-footer__copy">' . $this->renderPoweredByGlyph() . '</span></div></footer>';
    }

    private function versionedAssetPath(string $publicPath): string
    {
        $normalizedPath = '/' . ltrim($publicPath, '/');
        $filesystemPath = dirname(__DIR__, 3) . $normalizedPath;

        if (!is_file($filesystemPath)) {
            return $normalizedPath;
        }

        $modifiedAt = filemtime($filesystemPath);

        if (!is_int($modifiedAt) || $modifiedAt <= 0) {
            return $normalizedPath;
        }

        return $normalizedPath . '?v=' . rawurlencode((string) $modifiedAt);
    }

    private function criticalShellStyles(string $bodyClass): string
    {
        if (!in_array($bodyClass, ['theme-admin', 'theme-auth', 'theme-frontend'], true)) {
            return 'body{margin:0;}';
        }

        return 'html{background:#0d0f12;color-scheme:dark;}body{margin:0;min-height:100vh;background:#0d0f12;color:#e4e8f0;}';
    }

    private function renderAdminSidebar(string $userEmail, string $userRole, string $userDisplayName): string
    {
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $sections = AdminNavigation::sidebarSectionsForRole($userRole);

        $nav = '<aside class="admin-sidebar">';
        $nav .= '<a class="admin-sidebar__logo" href="/admin">';
        $nav .= '<span class="admin-sidebar__logo-mark">';
        $nav .= '<img src="/assets/branding/glyph-app-icon-256.png" alt="Glyph">';
        $nav .= '</span>';
        $nav .= '<span class="admin-sidebar__logo-text">Glyph</span>';
        $nav .= '</a>';

        $nav .= '<nav class="admin-nav">';
        $nav .= '<a class="admin-nav__link" href="/" target="_blank" rel="noreferrer noopener"><svg viewBox="0 0 24 24"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>View Site</a>';
        foreach ($sections as $section) {
            $nav .= '<span class="admin-nav__label">' . $this->escape($section['label']) . '</span>';
            foreach ($section['links'] as $link) {
                $nav .= $this->renderNavLink($link['href'], $link['label'], $currentPath, $link['svg_paths']);
            }
        }
        $nav .= '</nav>';

        $userIdentity = $userDisplayName !== '' ? $userDisplayName : $userEmail;
        $nav .= '<div class="admin-sidebar__footer">';
        if ($userIdentity !== '') {
            $nav .= '<div class="admin-sidebar__user"><div class="admin-sidebar__user-email">' . $this->escape($userIdentity) . '</div>';
            if ($userRole !== '') {
                $nav .= '<div class="admin-sidebar__user-role">' . $this->escape($userRole) . '</div>';
            }
            $nav .= '</div>';
        }
        $nav .= '<a class="admin-sidebar__logout" href="/logout"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sign Out</a>';
        $nav .= '</div></aside>';

        return $nav;
    }

    private function renderNavLink(string $href, string $label, string $currentPath, string $svgPaths): string
    {
        $isActive = $href === '/admin' ? $currentPath === '/admin' : str_starts_with($currentPath, $href);
        $activeClass = $isActive ? ' is-active' : '';

        return '<a class="admin-nav__link' . $activeClass . '" href="' . $this->escape($href) . '"><svg viewBox="0 0 24 24">' . $svgPaths . '</svg>' . $this->escape($label) . '</a>';
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}




