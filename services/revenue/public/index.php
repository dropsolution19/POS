<?php

require_once __DIR__ . '/../../../shared/Response.php';
require_once __DIR__ . '/../../../shared/Logger.php';
require_once __DIR__ . '/../src/Migrator.php';
require_once __DIR__ . '/../src/Repositories/RevenueRepository.php';

$config = require __DIR__ . '/../../../shared/config.php';
$dbPath = $config['services']['revenue']['db'];
$logFile = $config['services']['revenue']['log'];

RevenueMigrator::migrate($dbPath);

$revenueRepo = new RevenueRepository($dbPath);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    Response::json([], 200);
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($uri === '/api/revenue/record' && $method === 'POST') {
        $records = $input['records'] ?? [];
        if (empty($records)) {
            Response::error('INVALID_INPUT', 'Records array is required', 400);
        }

        $revenueRepo->recordBatch($records);
        Logger::log($logFile, 'revenue', 'record_batch', true, ['count' => count($records)]);
        Response::success(['recorded_count' => count($records)], 201);
    }

    if ($uri === '/api/revenue/summary' && $method === 'GET') {
        $period = $_GET['period'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        if ($period) {
            $today = date('Y-m-d');
            switch ($period) {
                case 'today':
                    $startDate = $today;
                    $endDate = $today;
                    break;
                case 'yesterday':
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $startDate = $yesterday;
                    $endDate = $yesterday;
                    break;
                case 'last_7_days':
                    $startDate = date('Y-m-d', strtotime('-6 days'));
                    $endDate = $today;
                    break;
                case 'this_month':
                    $startDate = date('Y-m-01');
                    $endDate = date('Y-m-t');
                    break;
            }
        }

        $summary = $revenueRepo->getSummary($startDate, $endDate);
        $summary['period'] = $period ?: 'custom';
        $summary['start_date'] = $startDate;
        $summary['end_date'] = $endDate;

        Response::success($summary);
    }

    if ($uri === '/api/revenue/reports' && $method === 'GET') {
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

        $reports = $revenueRepo->getReports($startDate, $endDate, $limit);
        Response::success($reports);
    }

    Response::error('NOT_FOUND', 'Endpoint not found', 404);
} catch (\Throwable $e) {
    Logger::log($logFile, 'revenue', 'exception', false, ['message' => $e->getMessage()]);
    Response::error('SERVER_ERROR', $e->getMessage(), 500);
}
