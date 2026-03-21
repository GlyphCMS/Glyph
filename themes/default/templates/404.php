<?php declare(strict_types=1); ?>
<?php
/** @var string $siteName */
/** @var string $pageTitle */
/** @var string $metaDescription */
/** @var Glyph\services\themes\ThemeView $__theme */

$documentTitle = ($pageTitle === $siteName || str_ends_with($pageTitle, ' - ' . $siteName)) ? $pageTitle : $pageTitle . ' - ' . $siteName;

$content = $__theme->partial('header');
$content .= '<main class="page-shell page-shell--narrow">';
$content .= '<div class="error-page">';
$content .= '<div class="error-page__code">404</div>';
$content .= '<h1 class="error-page__title">Page not found.</h1>';
$content .= '<p class="error-page__text">The content you\'re looking for doesn\'t exist, isn\'t published, or may have moved.</p>';
$content .= '<div class="cluster"><a class="button" href="/">Go home</a><a class="button button-secondary" href="/search">Search the site</a></div>';
$content .= '</div>';
$content .= '</main>';
$content .= $__theme->partial('footer');

echo $__theme->document($documentTitle, $content, $metaDescription);
