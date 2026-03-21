<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

$testFiles = [
    __DIR__ . '/install/InstallValidatorTest.php',
    __DIR__ . '/install/RuntimeConfigMergeTest.php',
    __DIR__ . '/ui/DocumentRendererSeoTest.php',
    __DIR__ . '/ui/DocumentRendererAdminFooterTest.php',
    __DIR__ . '/ui/DateTimeFormatterTest.php',
    __DIR__ . '/ui/UserPageRendererTest.php',
    __DIR__ . '/auth/LoginValidatorTest.php',
    __DIR__ . '/auth/LoginThrottleScopeTest.php',
    __DIR__ . '/auth/RoleCapabilitiesTest.php',
    __DIR__ . '/auth/PasswordResetFlowTest.php',
    __DIR__ . '/auth/AuthControllerBaseUrlTest.php',
    __DIR__ . '/admin/AdminControllerDashboardStatsTest.php',
    __DIR__ . '/content/ContentValidatorTest.php',
    __DIR__ . '/content/ContentControllerSavedEditSkipsAutosaveTest.php',
    __DIR__ . '/content/ContentControllerDraftCreateSlugNormalizationTest.php',
    __DIR__ . '/content/ContentControllerFeaturedImageAutosaveFallbackTest.php',
    __DIR__ . '/content/ContentPageRendererSidebarLayoutTest.php',
    __DIR__ . '/content/SlugManagerTest.php',
    __DIR__ . '/content/ContentServiceTest.php',
    __DIR__ . '/content/ContentServiceSanitizedBodyValidationTest.php',
    __DIR__ . '/content/ContentServiceHtmlPolicyTest.php',
    __DIR__ . '/security/CsrfTokenManagerTest.php',
    __DIR__ . '/security/HtmlSanitizerTest.php',
    __DIR__ . '/media/MediaValidatorTest.php',
    __DIR__ . '/media/MediaServiceDeleteTest.php',
    __DIR__ . '/media/MediaControllerUploadBrowserTest.php',
    __DIR__ . '/media/MediaPageRendererTest.php',
    __DIR__ . '/settings/SettingsValidatorTest.php',
    __DIR__ . '/settings/SettingsPageRendererCacheDriverTest.php',
    __DIR__ . '/frontend/HomepageModeTest.php',
    __DIR__ . '/navigation/NavigationManagerTest.php',
    __DIR__ . '/navigation/NavigationManagerIdAssignmentTest.php',
    __DIR__ . '/navigation/NavigationPageRendererBlankRowTest.php',
    __DIR__ . '/settings/SettingsManagerTest.php',
    __DIR__ . '/themes/ThemeResolverTest.php',
    __DIR__ . '/themes/ThemeRendererTest.php',
    __DIR__ . '/themes/ThemePageRendererTest.php',
    __DIR__ . '/themes/ThemeRendererFeaturedImageVersionTest.php',
    __DIR__ . '/themes/ThemeViewBrandingTest.php',
    __DIR__ . '/themes/ThemeViewSidebarTest.php',
    __DIR__ . '/themes/ThemeViewNavigationDropdownTest.php',
    __DIR__ . '/themes/ThemeAdminServiceTest.php',
    __DIR__ . '/themes/ThemePackageInstallerTest.php',
    __DIR__ . '/plugins/HookManagerTest.php',
    __DIR__ . '/plugins/PluginResolverTest.php',
    __DIR__ . '/plugins/PluginLoaderTest.php',
    __DIR__ . '/plugins/PluginAdminServiceTest.php',
    __DIR__ . '/plugins/PluginPackageInstallerTest.php',
    __DIR__ . '/content/ContentAutosaveServiceTest.php',
    __DIR__ . '/plugins/PluginSettingsStoreTest.php',
    __DIR__ . '/users/UserManagerTest.php',
    __DIR__ . '/system/MaintenanceManagerTest.php',
    __DIR__ . '/system/SystemBackupManagerTest.php',
    __DIR__ . '/system/UpdateManifestFetcherTest.php',
    __DIR__ . '/system/UpdatePackageValidatorTest.php',
    __DIR__ . '/system/UpdateApplyServiceTest.php',
    __DIR__ . '/system/UpdateApplyServiceHardeningTest.php',
    __DIR__ . '/system/VersionStateManagerTest.php',
    __DIR__ . '/system/MigrationManagerTest.php',
    __DIR__ . '/system/RenameUserRolesMigrationTest.php',
    __DIR__ . '/system/AdminRoutesTest.php',
    __DIR__ . '/filesystem/LocalFilesystemSymlinkHardeningTest.php',
];

$failures = [];

foreach ($testFiles as $testFile) {
    $result = require $testFile;

    if ($result !== true) {
        $failures[] = basename($testFile);
    }
}

if ($failures !== []) {
    fwrite(STDERR, 'Failed tests: ' . implode(', ', $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'All tests passed.' . PHP_EOL);
exit(0);















