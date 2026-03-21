<?php declare(strict_types=1); ?>
<?php
/** @var string $query */
/** @var Glyph\services\content\ContentListResult $listResult */
/** @var string $siteName */
/** @var string $pageTitle */
/** @var string $metaDescription */
/** @var Glyph\services\themes\ThemeView $__theme */
/** @var array<string, string> $documentMeta */

$documentTitle = ($pageTitle === $siteName || str_ends_with($pageTitle, ' - ' . $siteName)) ? $pageTitle : $pageTitle . ' - ' . $siteName;

$content = $__theme->partial('header');
$content .= '<main class="page-shell page-shell--narrow stack">';

$content .= '<div class="content-header">';
$content .= '<p class="content-header__eyebrow">Search</p>';
$content .= '<h1 class="content-header__title">Find content across your site.</h1>';
$content .= '</div>';

$content .= '<div class="search-bar">';
$content .= '<input type="search" name="q" value="' . $__theme->escape($query) . '" placeholder="Search posts and pages..." form="site-search" aria-label="Search">';
$content .= '<button type="submit" form="site-search">Search</button>';
$content .= '</div>';
$content .= '<form id="site-search" method="get" action="/search" hidden></form>';

if (trim($query) === '') {
    $content .= '<div class="notice notice--warning"><p class="empty-state">Enter a search term above.</p></div>';
    $content .= $__theme->slot('theme.before_footer');
    $content .= '</main>';
    $content .= $__theme->partial('footer');
    echo $__theme->document($documentTitle, $content, $metaDescription, 'theme-frontend', $documentMeta);
    return;
}

if ($listResult->items === []) {
    $content .= '<p class="muted">No results for <strong>' . $__theme->escape($query) . '</strong>.</p>';
    $content .= $__theme->slot('theme.before_footer');
    $content .= '</main>';
    $content .= $__theme->partial('footer');
    echo $__theme->document($documentTitle, $content, $metaDescription, 'theme-frontend', $documentMeta);
    return;
}

$content .= '<p class="muted">Found ' . $listResult->totalItems . ' result' . ($listResult->totalItems !== 1 ? 's' : '') . ' for <strong>' . $__theme->escape($query) . '</strong>.</p>';

$content .= '<section class="article-list">';

foreach ($listResult->items as $item) {
    $content .= '<article class="article-card stack">';
    if ($item->featuredImage !== null && $item->featuredImage !== '') {
        $content .= '<a class="article-card__media" href="' . $__theme->escape($item->slug) . '"><img src="' . $__theme->escape($__theme->contentImageUrl($item)) . '" alt="' . $__theme->escape($item->title) . '"></a>';
    }
    $content .= '<div class="cluster"><span class="badge">' . $__theme->escape(ucfirst($item->type)) . '</span></div>';
    $content .= '<h2><a href="' . $__theme->escape($item->slug) . '">' . $__theme->escape($item->title) . '</a></h2>';

    if ($item->excerpt !== '') {
        $content .= '<p class="muted">' . $__theme->escape($item->excerpt) . '</p>';
    }

    $content .= '</article>';
}

$content .= '</section>';
$content .= $__theme->pagination('/search', ['q' => $query], $listResult);
$content .= $__theme->slot('theme.before_footer');
$content .= '</main>';
$content .= $__theme->partial('footer');

echo $__theme->document($documentTitle, $content, $metaDescription, 'theme-frontend', $documentMeta);
