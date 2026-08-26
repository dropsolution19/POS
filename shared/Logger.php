<?php

class Logger
{
    public static function log(string $logFile, string $service, string $action, bool $success, array $details = []): void
    {
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'service' => $service,
            'action' => $action,
            'success' => $success,
            'details' => $details
        ];

        file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }
}
