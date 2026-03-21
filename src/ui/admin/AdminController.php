<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\plugins\HookManager;
use Glyph\services\system\SystemInfoService;
use Glyph\ui\shared\DocumentRenderer;

final class AdminController
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly SystemInfoService $systemInfoService,
        private readonly ?HookManager $hookManager = null,
    ) {
    }

    public function dashboard(Request $request): Response
    {
        $user = $this->authenticationManager->currentUser();

        if ($user === null) {
            return Response::redirect('/login');
        }

        \Glyph\ui\shared\DocumentRenderer::setAdminUserContext($user->email, $user->role, $user->displayNameOrFallback());

        if (!$this->authenticationManager->hasCapability(RoleCapabilities::ADMIN_ACCESS)) {
            return Response::html(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to access the admin panel.</p></body></html>',
                403,
            );
        }

        $document = new DocumentRenderer($this->hookManager);
        $systemInfo = $this->systemInfoService->collect();
        $counts = is_array($systemInfo['content_counts'] ?? null) ? $systemInfo['content_counts'] : [];

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero hero--dashboard">';
        $content .= '<div class="toolbar">';
        $content .= '<div><p class="hero__eyebrow">Dashboard</p><h1 class="hero__title">Welcome back.</h1><p class="hero__text">Signed in as ' . $document->escape($user->displayNameOrFallback()) . ' with the <span class="badge">' . $document->escape($user->role) . '</span> role.</p></div>';
        $content .= '<div class="cluster"><a class="button button-secondary" href="/">View Site</a><a class="button" href="/admin/content/create?type=post">New Post</a></div>';
        $content .= '</div>';
        $content .= '</section>';

        if ($this->hookManager !== null) {
            $content .= $this->hookManager->renderSlot('admin.dashboard.after_stats', [
                'user_email' => $user->email,
                'user_role' => $user->role,
                'system_info' => $systemInfo,
            ]);
        }

        $content .= '<div class="admin-dashboard-layout">';
        $content .= '<section class="panel stack">';
        $content .= '<div><p class="kicker">Quick Access</p><h2 class="page-title">Start here</h2><p class="page-subtitle">A calmer list of the areas you will use most often.</p></div>';
        $content .= '<div class="admin-link-list">';
        foreach (AdminNavigation::quickLinksForRole($user->role) as $link) {
            $content .= $this->renderQuickLink($link['href'], $link['label'], $link['description']);
        }
        $content .= '</div>';
        $content .= '</section>';

        $content .= '<aside class="admin-dashboard-sidebar">';
        $content .= '<section class="sidebar-section sidebar-section--overview">';
        $content .= '<div><p class="kicker">Site Overview</p><h2 class="page-title">At a glance</h2><p class="page-subtitle">Publishing totals and server headroom for your site.</p></div>';
        $content .= '<div class="admin-metric-grid">';
        $content .= $this->renderMetricCard('Posts', (string) ($counts['published_posts'] ?? 0), $this->draftMeta((int) ($counts['draft_posts'] ?? 0)));
        $content .= $this->renderMetricCard('Pages', (string) ($counts['published_pages'] ?? 0), $this->draftMeta((int) ($counts['draft_pages'] ?? 0)));
        $content .= $this->renderMetricCard('Users', (string) ($systemInfo['user_count'] ?? 0), (string) ($systemInfo['active_user_count'] ?? 0) . ' active');
        $content .= $this->renderMetricCard('Plugins', (string) ($systemInfo['plugin_count'] ?? 0), (string) ($systemInfo['enabled_plugin_count'] ?? 0) . ' enabled');
        if (($systemInfo['storage_free_bytes'] ?? null) !== null) {
            $content .= $this->renderMetricCard(
                'Storage Free',
                $this->formatBytes((int) $systemInfo['storage_free_bytes']),
                $this->storageMeta($systemInfo['storage_used_percent'] ?? null)
            );
        }
        if (($systemInfo['load_average_1m'] ?? null) !== null) {
            $content .= $this->renderMetricCard('CPU Load', number_format((float) $systemInfo['load_average_1m'], 2), '1m average');
        } elseif (($systemInfo['storage_used_bytes'] ?? null) !== null) {
            $storageTotal = $systemInfo['storage_total_bytes'] ?? null;
            $storageMeta = $storageTotal !== null ? 'of ' . $this->formatBytes((int) $storageTotal) . ' total' : '';
            $content .= $this->renderMetricCard('Storage Used', $this->formatBytes((int) $systemInfo['storage_used_bytes']), $storageMeta);
        }
        $content .= '</div>';
        $content .= '</section>';
        $content .= '</aside>';
        $content .= '</div>';
        $content .= '</main>';

        return Response::html($document->render('Glyph Admin', $content, 'Glyph admin dashboard.', 'theme-admin', $user->email, $user->role));
    }

    private function renderQuickLink(string $href, string $title, string $description): string
    {
        return '<a class="admin-link-row" href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . '<span class="admin-link-row__title">' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>'
            . '<span class="admin-link-row__text">' . htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>'
            . '</a>';
    }

    private function renderMetricCard(string $label, string $value, string $meta = ''): string
    {
        $card = '<article class="admin-metric-card">';
        $card .= '<span class="admin-metric-card__label">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
        $card .= '<strong class="admin-metric-card__value">' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';

        if ($meta !== '') {
            $card .= '<span class="admin-metric-card__meta">' . htmlspecialchars($meta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
        }

        return $card . '</article>';
    }

    private function draftMeta(int $draftCount): string
    {
        return $draftCount . ' draft' . ($draftCount === 1 ? '' : 's');
    }

    private function storageMeta(mixed $usedPercent): string
    {
        if (!is_int($usedPercent) && !is_float($usedPercent)) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format((float) $usedPercent, 1), '0'), '.');

        return $formatted . '% used';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = max(0, $bytes);
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        $precision = $value >= 100 || $unitIndex === 0 ? 0 : 1;

        return number_format($value, $precision) . ' ' . $units[$unitIndex];
    }
}


