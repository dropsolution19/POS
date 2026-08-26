<?php

class HttpClient
{
    public static function request(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();
        $method = strtoupper($method);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'HTTP_CLIENT_ERROR',
                    'message' => "cURL Error: " . $error
                ],
                '_http_code' => $httpCode
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_JSON_RESPONSE',
                    'message' => "Invalid JSON from " . $url . ": " . $response
                ],
                '_http_code' => $httpCode
            ];
        }

        $decoded['_http_code'] = $httpCode;
        return $decoded;
    }

    public static function get(string $url): array
    {
        return self::request('GET', $url);
    }

    public static function post(string $url, array $data): array
    {
        return self::request('POST', $url, $data);
    }

    public static function put(string $url, array $data): array
    {
        return self::request('PUT', $url, $data);
    }

    public static function delete(string $url): array
    {
        return self::request('DELETE', $url);
    }
}
