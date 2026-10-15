<?php

namespace Veho\SystemAlert;

use Throwable;

class VehoAlert
{
    public static function send($type, $summary, $system = null)
    {
        if (config('app.env') !== 'production') {
            return;
        }

        $url = config('veho-alert.url');
        $token = config('veho-alert.token');
        $system = $system ?: config('veho-alert.system') ?: config('app.name');
        $summary = trim((string) $summary);

        if (!$url || !$token || !$system || $summary === '') {
            return;
        }
        if (preg_match("/[\r\n]/", $token)) {
            return;
        }

        $payload = json_encode([
            'system' => mb_substr((string) $system, 0, 200),
            'environment' => 'production',
            'timestamp' => date('c'),
            'type' => $type,
            'summary' => mb_substr($summary, 0, 4000),
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nX-Veho-Alert-Token: {$token}\r\n",
                'content' => $payload,
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ]);

        try {
            @file_get_contents($url, false, $context);
        } catch (Throwable $e) {
            // A failed alert must not break the request or the worker.
        }
    }

    public static function reportException(Throwable $e)
    {
        if (app()->runningInConsole() || !self::isActionable($e)) {
            return;
        }

        self::send('application', $e->getMessage());
    }

    private static function isActionable(Throwable $e)
    {
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $e->getStatusCode() >= 500;
        }

        $skip = [
            'Illuminate\Validation\ValidationException',
            'Illuminate\Auth\AuthenticationException',
            'Illuminate\Auth\Access\AuthorizationException',
            'Illuminate\Database\Eloquent\ModelNotFoundException',
            'Illuminate\Session\TokenMismatchException',
        ];

        foreach ($skip as $class) {
            if (class_exists($class) && $e instanceof $class) {
                return false;
            }
        }

        return true;
    }
}
