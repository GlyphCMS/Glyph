<?php

declare(strict_types=1);

use Glyph\adapters\http\Response;

set_error_handler(static function (
    int $severity,
    string $message,
    string $file,
    int $line,
): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $throwable): void {
    glyphLogThrowable($throwable);

    $message = 'An unexpected error occurred.';

    $response = new Response(
        500,
        ['Content-Type' => 'text/html; charset=UTF-8'],
        sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Error</title></head><body><h1>Glyph</h1><p>%s</p></body></html>',
            htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        ),
    );

    $response->send();
});

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalErrorTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if (!in_array($error['type'], $fatalErrorTypes, true)) {
        return;
    }

    glyphLogFatalError($error);

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Error</title></head><body><h1>Glyph</h1><p>An unrecoverable error occurred.</p></body></html>';
});

function glyphLogThrowable(Throwable $throwable): void
{
    $message = sprintf(
        "[%s] %s: %s in %s:%d\n%s\n\n",
        gmdate('c'),
        $throwable::class,
        $throwable->getMessage(),
        $throwable->getFile(),
        $throwable->getLine(),
        $throwable->getTraceAsString(),
    );

    glyphWriteErrorLog($message);
}

/**
 * @param array{type:int,message:string,file:string,line:int} $error
 */
function glyphLogFatalError(array $error): void
{
    $message = sprintf(
        "[%s] Fatal error %d: %s in %s:%d\n\n",
        gmdate('c'),
        $error['type'],
        $error['message'],
        $error['file'],
        $error['line'],
    );

    glyphWriteErrorLog($message);
}

function glyphWriteErrorLog(string $message): void
{
    $logDirectory = dirname(__DIR__) . '/storage/logs';

    if (!is_dir($logDirectory)) {
        @mkdir($logDirectory, 0775, true);
    }

    @file_put_contents($logDirectory . '/php-error.log', $message, FILE_APPEND);
}
