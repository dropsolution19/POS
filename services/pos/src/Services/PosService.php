<?php

require_once __DIR__ . '/../Repositories/OrderRepository.php';
require_once __DIR__ . '/../../../../shared/HttpClient.php';

class PosService
{
    private OrderRepository $orderRepo;
    private array $config;

    public function __construct(string $dbPath, array $config)
    {
        $this->orderRepo = new OrderRepository($dbPath);
        $this->config = $config;
    }

    public function processCheckout(array $itemsInput, float $receivedAmount): array
    {
        if (empty($itemsInput)) {
            throw new Exception("ORDER_ITEMS_EMPTY: ต้องมีรายการอย่างน้อย 1 รายการ");
        }

        $recipeServiceUrl = $this->config['services']['recipe_inventory']['url'];
        $revenueServiceUrl = $this->config['services']['revenue']['url'];

        // 1. Fetch current product information from Recipe/Inventory service
        $resProducts = HttpClient::get($recipeServiceUrl . '/api/products');
        if (!($resProducts['success'] ?? false)) {
            throw new Exception("FAILED_TO_FETCH_PRODUCTS: ไม่สามารถติดต่อระบบสินค้าได้");
        }

        $productMap = [];
        foreach ($resProducts['data'] as $prod) {
            $productMap[(int)$prod['id']] = $prod;
        }

        $totalAmount = 0.0;
        $orderItems = [];

        foreach ($itemsInput as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                throw new Exception("INVALID_ITEM: รายการสินค้าหรือจำนวนไม่ถูกต้อง");
            }

            if (!isset($productMap[$productId])) {
                throw new Exception("PRODUCT_NOT_FOUND: ไม่พบสินค้า ID " . $productId);
            }

            $product = $productMap[$productId];
            $unitPrice = (float)$product['selling_price'];
            $itemTotal = $unitPrice * $qty;
            $totalAmount += $itemTotal;

            $orderItems[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total_price' => $itemTotal
            ];
        }

        $totalAmount = round($totalAmount, 2);
        $receivedAmount = round($receivedAmount, 2);

        // 2. Validate Payment Amount
        if ($receivedAmount < $totalAmount) {
            return [
                'success' => false,
                'error_code' => 'INSUFFICIENT_PAYMENT',
                'message' => 'เงินไม่เพียงพอ',
                'details' => [
                    'total_amount' => $totalAmount,
                    'received_amount' => $receivedAmount,
                    'missing' => round($totalAmount - $receivedAmount, 2)
                ]
            ];
        }

        $changeAmount = round($receivedAmount - $totalAmount, 2);

        // 3. Pre-check stock with Inventory Service
        $stockCheck = HttpClient::post($recipeServiceUrl . '/api/inventory/check', [
            'items' => $itemsInput
        ]);

        if (!($stockCheck['success'] ?? false)) {
            return [
                'success' => false,
                'error_code' => $stockCheck['error']['code'] ?? 'INSUFFICIENT_STOCK',
                'message' => $stockCheck['error']['message'] ?? 'วัตถุดิบไม่เพียงพอ',
                'details' => $stockCheck['error']['details'] ?? null
            ];
        }

        // 4. Generate Order Number
        $orderNumber = 'ORD-' . date('YmdHis') . '-' . rand(100, 999);

        // 5. Consume Stock & Get Unit Cost Snapshots from Inventory Service
        $consumeRes = HttpClient::post($recipeServiceUrl . '/api/inventory/consume', [
            'order_id' => $orderNumber,
            'items' => $itemsInput
        ]);

        if (!($consumeRes['success'] ?? false)) {
            return [
                'success' => false,
                'error_code' => $consumeRes['error']['code'] ?? 'STOCK_CONSUME_ERROR',
                'message' => $consumeRes['error']['message'] ?? 'ไม่สามารถตัดสต๊อกได้',
                'details' => $consumeRes['error']['details'] ?? null
            ];
        }

        $productCosts = $consumeRes['data']['product_costs'] ?? [];

        // 6. Record Order locally in pos.sqlite
        $orderId = $this->orderRepo->create($orderNumber, $totalAmount, $receivedAmount, $changeAmount, $orderItems);

        // 7. Record Sales & Profit Snapshot in Revenue Service
        $revenueRecords = [];
        foreach ($orderItems as $item) {
            $prodId = $item['product_id'];
            $unitCost = (float)($productCosts[$prodId] ?? 0.0);
            $qty = $item['quantity'];

            $sellingPriceTotal = $item['total_price'];
            $costPriceTotal = round($unitCost * $qty, 2);
            $profitTotal = round($sellingPriceTotal - $costPriceTotal, 2);

            $revenueRecords[] = [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'product_id' => $prodId,
                'product_name' => $item['product_name'],
                'quantity' => $qty,
                'selling_price' => $sellingPriceTotal,
                'cost_price' => $costPriceTotal,
                'profit' => $profitTotal
            ];
        }

        $revenueRes = HttpClient::post($revenueServiceUrl . '/api/revenue/record', [
            'records' => $revenueRecords
        ]);

        return [
            'success' => true,
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'total_amount' => $totalAmount,
            'received_amount' => $receivedAmount,
            'change_amount' => $changeAmount,
            'items' => $orderItems,
            'revenue_recorded' => $revenueRes['success'] ?? false
        ];
    }
}
