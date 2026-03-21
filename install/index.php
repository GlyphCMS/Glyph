<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\adapters\security\PasswordHasher;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\session\SessionManager;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\install\EnvironmentChecker;
use Glyph\services\install\InstallInput;
use Glyph\services\install\Installer;
use Glyph\services\install\InstallState;
use Glyph\services\install\InstallValidator;
use Glyph\services\install\ValidationResult;
use Glyph\ui\install\InstallPageRenderer;

$bootstrap = require dirname(__DIR__) . '/bootstrap/config.php';
require dirname(__DIR__) . '/bootstrap/errors.php';

$config = $bootstrap['config'];
$paths = $bootstrap['paths'];

$filesystem = new LocalFilesystem();
$installState = new InstallState($config['generated'], $paths, $filesystem);

if ($installState->isInstalled()) {
    header('Location: /');
    exit;
}

$sessionManager = new SessionManager(
    authConfig: $config['auth'],
    sessionSavePath: $paths->get('data_sessions'),
);
$sessionManager->start();

$secretGenerator = new SecretGenerator();
$csrfTokenManager = new CsrfTokenManager($sessionManager, $secretGenerator);

$environmentChecker = new EnvironmentChecker($filesystem, $paths);
$environmentCheck = $environmentChecker->check();
$isApcuAvailable = $environmentChecker->isApcuAvailable();

$https = $_SERVER['HTTPS'] ?? null;
$isHttps = is_string($https) && strtolower($https) !== 'off' && $https !== '';
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;
$defaultSiteUrl = 'http://localhost';

if (is_string($host) && $host !== '') {
    $defaultSiteUrl = ($isHttps ? 'https://' : 'http://') . $host;
}

$defaultCacheDriver = $isApcuAvailable ? 'apcu' : 'file';
$input = new InstallInput('', $defaultSiteUrl, '', '', '', $defaultCacheDriver);
$validationResult = new ValidationResult([]);
$installErrorMessage = null;

$requestMethod = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
    ? strtoupper($_SERVER['REQUEST_METHOD'])
    : 'GET';

if ($requestMethod === 'POST' && $environmentCheck->isValid()) {
    $input = InstallInput::fromPost($_POST);

    if (!$csrfTokenManager->validate('install_form', isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
        $installErrorMessage = 'Your session token is invalid. Please refresh the page and try again.';
    } else {
        $validator = new InstallValidator();
        $validationResult = $validator->validate($input, $isApcuAvailable);

        if ($validationResult->isValid()) {
            $installer = new Installer(
                filesystem: $filesystem,
                configWriter: new PhpConfigWriter($filesystem),
                passwordHasher: new PasswordHasher(),
                secretGenerator: $secretGenerator,
                paths: $paths,
            );

            $installationResult = $installer->install($input, $isApcuAvailable);

            if ($installationResult->isSuccessful()) {
                header('Location: /');
                exit;
            }

            $installErrorMessage = $installationResult->errorMessage();
        }
    }
}

$renderer = new InstallPageRenderer();
echo $renderer->render(
    environmentCheck: $environmentCheck,
    input: $input,
    validationResult: $validationResult,
    installErrorMessage: $installErrorMessage,
    csrfToken: $csrfTokenManager->token('install_form'),
    isApcuAvailable: $isApcuAvailable,
);
