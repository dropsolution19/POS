<?php

class Response
{
    public static function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success($data = null, int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'data' => $data
        ], $statusCode);
    }

    public static function error(string $code, string $message, int $statusCode = 400, $details = null): void
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        self::json($payload, $statusCode);
    }
}
