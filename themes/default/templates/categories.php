<?php declare(strict_types=1); ?>
<?php
/** @var list<array{category: Glyph\domain\categories\CategoryRecord, depth: int, path: string, archive_path: string}> $orderedCategories */
/** @var string $siteName */
/** @var string $pageTitle */
/** @var string $metaDescription */
/** @var Glyph\services\themes\ThemeView $__theme */
/** @var array<string, string> $documentMeta */

$documentTitle = ($pageTitle === $siteName || str_ends_with($pageTitle, ' - ' . $siteName)) ? $pageTitle : $pageTitle . ' - ' . $siteName;
$content = $__theme->partial('header');
$content .= '<main class="page-shell page-shell--narrow stack">';
$content .= '<div class="content-header">';
$content .= '<p class="content-header__eyebrow">Categories</p>';
$content .= '<h1 class="content-header__title">Browse by category.</h1>';
$content .= '<p class="hero__text">Explore the main topics across the site.</p>';
$content .= '</div>';

if ($orderedCategories === []) {
    $content .= '<div class="notice notice--warning"><p class="empty-state">No categories have been created yet.</p></div>';
    $content .= $__theme->slot('theme.before_footer');
    $content .= '</main>';
    $content .= $__theme->partial('footer');
    echo $__theme->document($documentTitle, $content, $metaDescription, 'theme-frontend', $documentMeta);
    return;
}

$content .= '<section class="article-list">';
foreach ($orderedCategories as $row) {
    $category = $row['category'];
    $content .= '<article class="article-card stack">';
    $content .= '<div class="cluster"><span class="badge">Category</span>';
    if ($row['depth'] > 0) {
        $content .= '<span class="badge">Level ' . $__theme->escape((string) ($row['depth'] + 1)) . '</span>';
    }
    $content .= '</div>';
    $content .= '<h2><a href="' . $__theme->escape($row['archive_path']) . '">' . $__theme->escape($category->name) . '</a></h2>';
    if ($category->description !== '') {
        $content .= '<p class="muted">' . $__theme->escape($category->description) . '</p>';
    } else {
        $content .= '<p class="muted">Browse content filed under this category.</p>';
    }
    $content .= '<div><a class="button button-secondary" href="' . $__theme->escape($row['archive_path']) . '">View Category</a></div>';
    $content .= '</article>';
}
$content .= '</section>';
$content .= $__theme->slot('theme.before_footer');
$content .= '</main>';
$content .= $__theme->partial('footer');

echo $__theme->document($documentTitle, $content, $metaDescription, 'theme-frontend', $documentMeta);