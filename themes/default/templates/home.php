<?php declare(strict_types=1); ?>
<?php
/** @var Glyph\services\content\ContentListResult $listResult */
/** @var string $siteName */
/** @var string $siteTagline */
/** @var string $pageTitle */
/** @var string $metaDescription */
/** @var Glyph\services\themes\ThemeView $__theme */
/** @var array<string, string> $documentMeta */
/** @var string|null $heroEyebrow */
/** @var string|null $heroTitle */
/** @var string|null $heroSubtitle */
/** @var string|null $emptyStateMessage */
/** @var string|null $paginationBasePath */
/** @var array<string, string>|null $paginationQuery */

$resolvedHeroEyebrow = isset($heroEyebrow) && is_string($heroEyebrow) && $heroEyebrow !== '' ? $heroEyebrow : $siteName;
$resolvedHeroTitle = isset($heroTitle) && is_string($heroTitle) && $heroTitle !== '' ? $heroTitle : ($siteTagline !== '' ? $siteTagline : $siteName);
$resolvedHeroSubtitle = isset($heroSubtitle) && is_string($heroSubtitle) && $heroSubtitle !== '' ? $heroSubtitle : ($metaDescription !== '' ? $metaDescription : 'Browse the latest posts, search the site, and explore content.');
$resolvedEmptyStateMessage = isset($emptyStateMessage) && is_string($emptyStateMessage) && $emptyStateMessage !== '' ? $emptyStateMessage : 'No published posts yet.';
$resolvedPaginationBasePath = isset($paginationBasePath) && is_string($paginationBasePath) && $paginationBasePath !== '' ? $paginationBasePath : '/';
$resolvedPaginationQuery = isset($paginationQuery) && is_array($paginationQuery) ? $paginationQuery : [];
$documentTitle = ($pageTitle === $siteName || str_ends_with($pageTitle, ' - ' . $siteName)) ? $pageTitle : $pageTitle . ' - ' . $siteName;

$content = $__theme->partial('header');

$content .= '<main class="page-shell page-shell--narrow stack">';

$content .= '<div class="content-header">';
$content .= '<p class="content-header__eyebrow">' . $__theme->escape($resolvedHeroEyebrow) . '</p>';
$content .= '<h1 class="content-header__title">' . $__theme->escape($resolvedHeroTitle) . '</h1>';
$content .= '<p class="hero__text">' . $__theme->escape($resolvedHeroSubtitle) . '</p>';
$content .= '</div>';

$content .= '<div class="search-bar">';
$content .= '<input type="search" name="q" placeholder="Search posts and pages..." form="site-search" aria-label="Search">';
$content .= '<button type="submit" form="site-search">Search</button>';
$content .= '</div>';
$content .= '<form id="site-search" method="get" action="/search" hidden></form>';

if ($listResult->items === []) {
    $content .= '<div class="notice notice--warning"><p class="empty-state">' . $__theme->escape($resolvedEmptyStateMessage) . '</p></div>';
    $content .= $__theme->slot('theme.before_footer');
    $content .= '</main>';
    $content .= $__theme->partial('footer');
    echo $__theme->document($documentTitle, $content, $metaDescription, 'theme-frontend', $documentMeta);
    return;
}

$content .= '<section class="article-list">';

foreach ($listResult->items as $item) {
    $content .= '<article class="article-card stack">';
    if ($item->featuredImage !== null && $item->featuredImage !== '') {
        $content .= '<a class="article-card__media" href="' . $__theme->escape($item->slug) . '"><img src="' . $__theme->escape($__theme->contentImageUrl($item)) . '" alt="' . $__theme->escape($item->title) . '"></a>';
    }
    $content .= '<div class="cluster">';

    if ($item->publishedAt !== null) {
        $content .= '<span class="article-meta">' . $__theme->escape($__theme->formatDate($item->publishedAt)) . '</span>';
    }

    $content .= '</div>';
    $content .= '<h2><a href="' . $__theme->escape($item->slug) . '">' . $__theme->escape($item->title) . '</a></h2>';

    if ($item->excerpt !== '') {
        $content .= '<p class="muted">' . $__theme->escape($item->excerpt) . '</p>';
    }

    $content .= '<div><a class="button button-secondary" href="' . $__theme->escape($item->slug) . '">Read More</a></div>';
    $content .= '</article>';
}

$content .= '</section>';
$content .= $__theme->pagination($resolvedPaginationBasePath, $resolvedPaginationQuery, $listResult);
$content .= $__theme->slot('theme.before_footer');
$content .= '</main>';
$content .= $__theme->partial('footer');

echo $__theme->document($documentTitle, $content, $metaDescription, 'theme-frontend', $documentMeta);

