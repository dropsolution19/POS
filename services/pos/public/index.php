<?php

require_once __DIR__ . '/../../../shared/Response.php';
require_once __DIR__ . '/../../../shared/Logger.php';
require_once __DIR__ . '/../src/Migrator.php';
require_once __DIR__ . '/../src/Repositories/OrderRepository.php';
require_once __DIR__ . '/../src/Services/PosService.php';

$config = require __DIR__ . '/../../../shared/config.php';
$dbPath = $config['services']['pos']['db'];
$logFile = $config['services']['pos']['log'];

PosMigrator::migrate($dbPath);

$orderRepo = new OrderRepository($dbPath);
$posService = new PosService($dbPath, $config);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    Response::json([], 200);
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Proxy products list for POS UI convenience
    if ($uri === '/api/products' && $method === 'GET') {
        $recipeUrl = $config['services']['recipe_inventory']['url'];
        $res = HttpClient::get($recipeUrl . '/api/products');
        if ($res['success'] ?? false) {
            Response::success($res['data']);
        } else {
            Response::error('SERVICE_ERROR', 'Failed to fetch products from Recipe & Inventory service', 500);
        }
    }

    if ($uri === '/api/orders' && $method === 'GET') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $orders = $orderRepo->getAll($limit);
        Response::success($orders);
    }

    if (preg_match('#^/api/orders/(\d+)$#', $uri, $matches) && $method === 'GET') {
        $id = (int)$matches[1];
        $order = $orderRepo->findById($id);
        if (!$order) {
            Response::error('NOT_FOUND', 'Order not found', 404);
        }
        Response::success($order);
    }

    if ($uri === '/api/orders' && $method === 'POST') {
        $items = $input['items'] ?? [];
        $receivedAmount = (float)($input['received_amount'] ?? 0);

        $result = $posService->processCheckout($items, $receivedAmount);

        if (!$result['success']) {
            Logger::log($logFile, 'pos', 'checkout_failed', false, [
                'code' => $result['error_code'],
                'message' => $result['message']
            ]);
            Response::error($result['error_code'], $result['message'], 400, $result['details'] ?? null);
        }

        Logger::log($logFile, 'pos', 'checkout_success', true, [
            'order_id' => $result['order_id'],
            'total_amount' => $result['total_amount']
        ]);

        Response::success($result, 201);
    }

    Response::error('NOT_FOUND', 'Endpoint not found', 404);
} catch (\Throwable $e) {
    Logger::log($logFile, 'pos', 'exception', false, ['message' => $e->getMessage()]);
    Response::error('SERVER_ERROR', $e->getMessage(), 500);
}
