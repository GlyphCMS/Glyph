<?php

declare(strict_types=1);

namespace Glyph\ui\frontend;

use Glyph\domain\content\ContentRecord;
use Glyph\services\content\ContentListResult;
use Glyph\ui\shared\DocumentRenderer;

final class FrontendPageRenderer
{
    public function renderHome(ContentListResult $listResult, string $siteName): string
    {
        $document = new DocumentRenderer();

        $content = $this->renderSiteHeader($siteName, $document);
        $content .= '<main class="page-shell page-shell--narrow stack">';

        $content .= '<div class="content-header">';
        $content .= '<p class="content-header__eyebrow">' . $document->escape($siteName) . '</p>';
        $content .= '<h1 class="content-header__title">A modern flat-file publishing experience.</h1>';
        $content .= '<p class="hero__text">Browse the latest published posts, search the site, and explore content.</p>';
        $content .= '</div>';

        $content .= '<div class="search-bar">';
        $content .= '<input type="search" name="q" placeholder="Search posts and pages..." form="site-search" aria-label="Search">';
        $content .= '<button type="submit" form="site-search">Search</button>';
        $content .= '</div>';
        $content .= '<form id="site-search" method="get" action="/search" hidden></form>';

        if ($listResult->items === []) {
            $content .= '<div class="notice notice--warning"><p class="empty-state">No published posts yet.</p></div>';
            $content .= '</main>';
            $content .= $this->renderSiteFooter($siteName, $document);

            return $document->render($siteName, $content, 'Home page for ' . $siteName . '.', 'theme-frontend');
        }

        $content .= '<section class="article-list">';

        foreach ($listResult->items as $item) {
            $content .= '<article class="article-card stack">';
            $content .= '<div class="cluster">';

            if ($item->publishedAt !== null) {
                $content .= '<span class="article-meta">' . $document->escape($item->publishedAt) . '</span>';
            }

            $content .= '</div>';
            $content .= '<h2><a href="' . $document->escape($item->slug) . '">' . $document->escape($item->title) . '</a></h2>';

            if ($item->excerpt !== '') {
                $content .= '<p class="muted">' . $document->escape($item->excerpt) . '</p>';
            }

            $content .= '<div><a class="button button-secondary" href="' . $document->escape($item->slug) . '">Read More</a></div>';
            $content .= '</article>';
        }

        $content .= '</section>';
        $content .= $this->renderPagination('/', [], $listResult, $document);
        $content .= '</main>';
        $content .= $this->renderSiteFooter($siteName, $document);

        return $document->render($siteName, $content, 'Home page for ' . $siteName . '.', 'theme-frontend');
    }

    public function renderContent(ContentRecord $contentRecord, string $siteName): string
    {
        $document = new DocumentRenderer();
        $title = $contentRecord->seoTitle !== '' ? $contentRecord->seoTitle : $contentRecord->title;
        $description = $contentRecord->seoDescription !== '' ? $contentRecord->seoDescription : $contentRecord->excerpt;

        $content = $this->renderSiteHeader($siteName, $document);
        $content .= '<main class="page-shell page-shell--narrow stack">';

        $content .= '<div class="content-header">';
        $content .= '<p class="content-header__eyebrow">' . $document->escape($siteName) . '</p>';
        $content .= '<h1 class="content-header__title">' . $document->escape($contentRecord->title) . '</h1>';

        if ($contentRecord->publishedAt !== null) {
            $content .= '<p class="content-header__meta">Published ' . $document->escape($contentRecord->publishedAt) . '</p>';
        }

        $content .= '</div>';
        $content .= '<article class="panel"><div class="prose">' . $contentRecord->bodyHtml . '</div></article>';
        $content .= '</main>';
        $content .= $this->renderSiteFooter($siteName, $document);

        return $document->render($title, $content, $description, 'theme-frontend');
    }

    public function renderSearch(string $query, ContentListResult $listResult, string $siteName): string
    {
        $document = new DocumentRenderer();

        $content = $this->renderSiteHeader($siteName, $document);
        $content .= '<main class="page-shell page-shell--narrow stack">';

        $content .= '<div class="content-header">';
        $content .= '<p class="content-header__eyebrow">Search</p>';
        $content .= '<h1 class="content-header__title">Find content across your site.</h1>';
        $content .= '</div>';

        $content .= '<div class="search-bar">';
        $content .= '<input type="search" name="q" value="' . $document->escape($query) . '" placeholder="Search posts and pages..." form="site-search" aria-label="Search">';
        $content .= '<button type="submit" form="site-search">Search</button>';
        $content .= '</div>';
        $content .= '<form id="site-search" method="get" action="/search" hidden></form>';

        if (trim($query) === '') {
            $content .= '<div class="notice notice--warning"><p class="empty-state">Enter a search term above.</p></div>';
            $content .= '</main>';
            $content .= $this->renderSiteFooter($siteName, $document);

            return $document->render('Search - ' . $siteName, $content, 'Search the site.', 'theme-frontend');
        }

        if ($listResult->items === []) {
            $content .= '<p class="muted">No results for <strong>' . $document->escape($query) . '</strong>.</p>';
            $content .= '</main>';
            $content .= $this->renderSiteFooter($siteName, $document);

            return $document->render('Search - ' . $siteName, $content, 'Search the site.', 'theme-frontend');
        }

        $plural = $listResult->totalItems !== 1 ? 's' : '';
        $content .= '<p class="muted">Found ' . $listResult->totalItems . ' result' . $plural . ' for <strong>' . $document->escape($query) . '</strong>.</p>';

        $content .= '<section class="article-list">';

        foreach ($listResult->items as $item) {
            $content .= '<article class="article-card stack">';
            $content .= '<div class="cluster"><span class="badge">' . $document->escape(ucfirst($item->type)) . '</span></div>';
            $content .= '<h2><a href="' . $document->escape($item->slug) . '">' . $document->escape($item->title) . '</a></h2>';

            if ($item->excerpt !== '') {
                $content .= '<p class="muted">' . $document->escape($item->excerpt) . '</p>';
            }

            $content .= '</article>';
        }

        $content .= '</section>';
        $content .= $this->renderPagination('/search', ['q' => $query], $listResult, $document);
        $content .= '</main>';
        $content .= $this->renderSiteFooter($siteName, $document);

        return $document->render('Search - ' . $siteName, $content, 'Search the site.', 'theme-frontend');
    }

    public function renderNotFound(string $siteName): string
    {
        $document = new DocumentRenderer();

        $content = $this->renderSiteHeader($siteName, $document);
        $content .= '<main class="page-shell page-shell--narrow">';
        $content .= '<div class="error-page">';
        $content .= '<div class="error-page__code">404</div>';
        $content .= '<h1 class="error-page__title">Page not found.</h1>';
        $content .= '<p class="error-page__text">The content you\'re looking for doesn\'t exist, isn\'t published, or may have moved.</p>';
        $content .= '<div class="cluster"><a class="button" href="/">Go home</a><a class="button button-secondary" href="/search">Search the site</a></div>';
        $content .= '</div>';
        $content .= '</main>';
        $content .= $this->renderSiteFooter($siteName, $document);

        return $document->render('Not Found - ' . $siteName, $content, 'Requested page not found.', 'theme-frontend');
    }

    private function renderSiteHeader(string $siteName, DocumentRenderer $document): string
    {
        $header = '<header class="site-header">';
        $header .= '<div class="site-header__inner">';
        $header .= '<a class="site-header__brand" href="/">';
        $header .= '<span class="site-header__site-name">' . $document->escape($siteName) . '</span>';
        $header .= '</a>';
        $header .= '<nav class="site-header__nav"></nav>';
        $header .= '<div class="site-header__search">';
        $header .= '<form class="site-header__search-form" method="get" action="/search">';
        $header .= '<input class="site-header__search-input" type="search" name="q" placeholder="Search..." aria-label="Search site">';
        $header .= '<button class="site-header__search-btn" type="submit" aria-label="Submit search">';
        $header .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
        $header .= '</button>';
        $header .= '</form>';
        $header .= '</div>';
        $header .= '</div>';
        $header .= '</header>';

        return $header;
    }

    private function renderSiteFooter(string $siteName, DocumentRenderer $document): string
    {
        $footer = '<footer class="site-footer">';
        $footer .= '<div class="site-footer__inner">';
        $footer .= '<span class="site-footer__copy">' . $document->escape($siteName) . '</span>';
        $footer .= '<span class="site-footer__copy">' . $document->renderPoweredByGlyph() . '</span>';
        $footer .= '</div>';
        $footer .= '</footer>';

        return $footer;
    }

    /**
     * @param array<string, string> $query
     */
    private function renderPagination(
        string $basePath,
        array $query,
        ContentListResult $listResult,
        DocumentRenderer $document,
    ): string {
        if ($listResult->totalPages <= 1) {
            return '';
        }

        $body = '<nav class="pagination">';
        $body .= '<span>Page ' . $listResult->currentPage . ' of ' . $listResult->totalPages . '</span>';
        $body .= '<div class="cluster">';

        if ($listResult->currentPage > 1) {
            $previousQuery = $query;
            $previousQuery['page'] = (string) ($listResult->currentPage - 1);
            $body .= '<a class="button button-secondary" href="' . $document->escape($this->buildUrl($basePath, $previousQuery)) . '">Previous</a>';
        }

        if ($listResult->currentPage < $listResult->totalPages) {
            $nextQuery = $query;
            $nextQuery['page'] = (string) ($listResult->currentPage + 1);
            $body .= '<a class="button button-secondary" href="' . $document->escape($this->buildUrl($basePath, $nextQuery)) . '">Next</a>';
        }

        $body .= '</div></nav>';

        return $body;
    }

    /**
     * @param array<string, string> $query
     */
    private function buildUrl(string $basePath, array $query): string
    {
        $queryString = http_build_query($query);

        return $queryString === '' ? $basePath : $basePath . '?' . $queryString;
    }
}

