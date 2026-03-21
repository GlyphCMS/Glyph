<?php declare(strict_types=1); ?>
<?php
/** @var Glyph\domain\content\ContentRecord $contentRecord */
/** @var string $siteName */
/** @var string $pageTitle */
/** @var string $metaDescription */
/** @var Glyph\services\themes\ThemeView $__theme */
/** @var array<string, string> $documentMeta */

$sidebar = $__theme->renderSidebar($contentRecord);
$mainClass = $sidebar !== '' ? 'page-shell stack' : 'page-shell page-shell--narrow stack';

$documentTitle = ($pageTitle === $siteName || str_ends_with($pageTitle, ' - ' . $siteName)) ? $pageTitle : $pageTitle . ' - ' . $siteName;

$content = $__theme->partial('header');
$content .= '<main class="' . $__theme->escape($mainClass) . '">';

$content .= '<div class="content-header">';
$content .= '<p class="content-header__eyebrow">' . $__theme->escape($siteName) . '</p>';
$content .= '<h1 class="content-header__title">' . $__theme->escape($contentRecord->title) . '</h1>';
if ($contentRecord->excerpt !== '') {
    $content .= '<p class="content-header__dek">' . $__theme->escape($contentRecord->excerpt) . '</p>';
}

$metaBits = [];
if ($contentRecord->type === 'post' && $contentRecord->publishedAt !== null) {
    $metaBits[] = 'Published ' . $__theme->formatDateTime($contentRecord->publishedAt);
}
$authorName = $__theme->authorDisplayName($contentRecord->authorId);
if ($contentRecord->type === 'post' && $authorName !== '') {
    $metaBits[] = 'By ' . $authorName;
}
if ($metaBits !== []) {
    $content .= '<p class="content-header__meta">' . $__theme->escape(implode(' | ', $metaBits)) . '</p>';
}
$content .= '</div>';

if ($contentRecord->featuredImage !== null && $contentRecord->featuredImage !== '') {
    $content .= '<div class="content-feature"><img class="content-feature__image" src="' . $__theme->escape($__theme->contentImageUrl($contentRecord)) . '" alt="' . $__theme->escape($contentRecord->title) . '"></div>';
}

$body = trim($contentRecord->bodyHtml);

if ($sidebar !== '') {
    $content .= '<div class="content-layout">';
    if ($body !== '') {
        $content .= '<article class="panel"><div class="prose">' . $body . '</div></article>';
    }
    $content .= $sidebar;
    $content .= '</div>';
} elseif ($body !== '') {
    $content .= '<article class="panel"><div class="prose">' . $body . '</div></article>';
}

$content .= $__theme->slot('theme.before_footer');
$content .= '</main>';
$content .= $__theme->partial('footer');

echo $__theme->document($documentTitle, $content, $metaDescription, 'theme-frontend', $documentMeta);



