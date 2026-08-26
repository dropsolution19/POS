<?php

require_once __DIR__ . '/../../../shared/Response.php';
require_once __DIR__ . '/../../../shared/Logger.php';
require_once __DIR__ . '/../src/Migrator.php';
require_once __DIR__ . '/../src/Repositories/ProductRepository.php';
require_once __DIR__ . '/../src/Repositories/IngredientRepository.php';
require_once __DIR__ . '/../src/Repositories/RecipeRepository.php';
require_once __DIR__ . '/../src/Repositories/StockRepository.php';
require_once __DIR__ . '/../src/Services/InventoryService.php';

$config = require __DIR__ . '/../../../shared/config.php';
$dbPath = $config['services']['recipe_inventory']['db'];
$logFile = $config['services']['recipe_inventory']['log'];

// Run migrations automatically
InventoryMigrator::migrate($dbPath);

$productRepo = new ProductRepository($dbPath);
$ingredientRepo = new IngredientRepository($dbPath);
$recipeRepo = new RecipeRepository($dbPath);
$stockRepo = new StockRepository($dbPath);
$inventoryService = new InventoryService($dbPath);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    Response::json([], 200);
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // PRODUCTS ENDPOINTS
    if ($uri === '/api/products' && $method === 'GET') {
        $products = $productRepo->getAll();
        foreach ($products as &$p) {
            $p['cost_price'] = $inventoryService->calculateProductCost((int)$p['id']);
            $p['estimated_profit'] = round((float)$p['selling_price'] - $p['cost_price'], 2);
            $p['recipe'] = $recipeRepo->getByProductId((int)$p['id']);
        }
        Response::success($products);
    }

    if (preg_match('#^/api/products/(\d+)$#', $uri, $matches) && $method === 'GET') {
        $id = (int)$matches[1];
        $p = $productRepo->findById($id);
        if (!$p) {
            Response::error('NOT_FOUND', 'Product not found', 404);
        }
        $p['cost_price'] = $inventoryService->calculateProductCost($id);
        $p['estimated_profit'] = round((float)$p['selling_price'] - $p['cost_price'], 2);
        $p['recipe'] = $recipeRepo->getByProductId($id);
        Response::success($p);
    }

    if ($uri === '/api/products' && $method === 'POST') {
        $name = trim($input['name'] ?? '');
        $sellingPrice = (float)($input['selling_price'] ?? 0);
        if (empty($name) || $sellingPrice < 0) {
            Response::error('INVALID_INPUT', 'Product name cannot be empty and selling price must be >= 0', 400);
        }
        $id = $productRepo->create($name, $sellingPrice);
        Logger::log($logFile, 'recipe-inventory', 'create_product', true, ['id' => $id, 'name' => $name]);
        Response::success(['id' => $id, 'name' => $name, 'selling_price' => $sellingPrice], 201);
    }

    if (preg_match('#^/api/products/(\d+)$#', $uri, $matches) && $method === 'PUT') {
        $id = (int)$matches[1];
        $name = trim($input['name'] ?? '');
        $sellingPrice = (float)($input['selling_price'] ?? 0);
        if (empty($name) || $sellingPrice < 0) {
            Response::error('INVALID_INPUT', 'Product name cannot be empty and selling price must be >= 0', 400);
        }
        $productRepo->update($id, $name, $sellingPrice);
        Logger::log($logFile, 'recipe-inventory', 'update_product', true, ['id' => $id]);
        Response::success(['id' => $id, 'name' => $name, 'selling_price' => $sellingPrice]);
    }

    if (preg_match('#^/api/products/(\d+)$#', $uri, $matches) && $method === 'DELETE') {
        $id = (int)$matches[1];
        $productRepo->delete($id);
        Logger::log($logFile, 'recipe-inventory', 'delete_product', true, ['id' => $id]);
        Response::success(['id' => $id, 'deleted' => true]);
    }

    // INGREDIENTS ENDPOINTS
    if ($uri === '/api/ingredients' && $method === 'GET') {
        $ingredients = $ingredientRepo->getAll();
        Response::success($ingredients);
    }

    if ($uri === '/api/ingredients' && $method === 'POST') {
        $name = trim($input['name'] ?? '');
        $unit = trim($input['unit'] ?? 'kg');
        if (empty($name)) {
            Response::error('INVALID_INPUT', 'Ingredient name cannot be empty', 400);
        }
        $id = $ingredientRepo->create($name, $unit);
        Logger::log($logFile, 'recipe-inventory', 'create_ingredient', true, ['id' => $id, 'name' => $name]);
        Response::success(['id' => $id, 'name' => $name, 'unit' => $unit], 201);
    }

    if (preg_match('#^/api/ingredients/(\d+)$#', $uri, $matches) && $method === 'PUT') {
        $id = (int)$matches[1];
        $name = trim($input['name'] ?? '');
        $unit = trim($input['unit'] ?? 'kg');
        if (empty($name)) {
            Response::error('INVALID_INPUT', 'Ingredient name cannot be empty', 400);
        }
        $ingredientRepo->update($id, $name, $unit);
        Response::success(['id' => $id, 'name' => $name, 'unit' => $unit]);
    }

    if ($uri === '/api/ingredients/purchase' && $method === 'POST') {
        $ingredientId = (int)($input['ingredient_id'] ?? 0);
        $quantityKg = (float)($input['quantity_kg'] ?? 0);
        $pricePerKg = (float)($input['price_per_kg'] ?? 0);
        $purchaseDate = $input['purchase_date'] ?? date('Y-m-d H:i:s');

        if ($ingredientId <= 0 || $quantityKg <= 0 || $pricePerKg < 0) {
            Response::error('INVALID_INPUT', 'Invalid purchase data (quantity must be > 0 and price >= 0)', 400);
        }

        $res = $inventoryService->processPurchase($ingredientId, $quantityKg, $pricePerKg, $purchaseDate);
        Logger::log($logFile, 'recipe-inventory', 'purchase_stock', true, $res);
        Response::success($res, 201);
    }

    // RECIPES ENDPOINTS
    if (preg_match('#^/api/recipes/product/(\d+)$#', $uri, $matches) && $method === 'GET') {
        $productId = (int)$matches[1];
        $recipe = $recipeRepo->getByProductId($productId);
        $cost = $inventoryService->calculateProductCost($productId);
        Response::success([
            'recipe' => $recipe,
            'cost_price' => $cost
        ]);
    }

    if ($uri === '/api/recipes' && $method === 'POST') {
        $productId = (int)($input['product_id'] ?? 0);
        $items = $input['ingredients'] ?? [];
        if ($productId <= 0) {
            Response::error('INVALID_INPUT', 'Product ID is required', 400);
        }

        $recipeId = $recipeRepo->saveRecipe($productId, $items);
        $cost = $inventoryService->calculateProductCost($productId);
        Logger::log($logFile, 'recipe-inventory', 'save_recipe', true, ['product_id' => $productId, 'recipe_id' => $recipeId]);
        Response::success([
            'recipe_id' => $recipeId,
            'product_id' => $productId,
            'calculated_cost' => $cost
        ], 200);
    }

    // INVENTORY CHECK & CONSUME ENDPOINTS FOR POS SERVICE
    if ($uri === '/api/inventory/check' && $method === 'POST') {
        $items = $input['items'] ?? [];
        $check = $inventoryService->checkOrderStock($items);
        if (!$check['sufficient']) {
            Response::error('INSUFFICIENT_STOCK', 'วัตถุดิบไม่เพียงพอ', 422, $check['insufficient_ingredients']);
        }
        Response::success(['sufficient' => true]);
    }

    if ($uri === '/api/inventory/consume' && $method === 'POST') {
        $orderId = (string)($input['order_id'] ?? '');
        $items = $input['items'] ?? [];
        if (empty($orderId) || empty($items)) {
            Response::error('INVALID_INPUT', 'order_id and items are required', 400);
        }

        $result = $inventoryService->consumeStockForOrder($orderId, $items);
        if (!$result['success']) {
            Logger::log($logFile, 'recipe-inventory', 'consume_stock_failed', false, ['order_id' => $orderId, 'reason' => $result['message']]);
            Response::error($result['error_code'], $result['message'], 422, $result['details'] ?? null);
        }

        Logger::log($logFile, 'recipe-inventory', 'consume_stock_success', true, ['order_id' => $orderId]);
        Response::success($result);
    }

    // TRANSACTIONS ENDPOINT
    if ($uri === '/api/transactions' && $method === 'GET') {
        $ingId = isset($_GET['ingredient_id']) ? (int)$_GET['ingredient_id'] : null;
        $tx = $stockRepo->getTransactions($ingId);
        Response::success($tx);
    }

    Response::error('NOT_FOUND', 'Endpoint not found', 404);
} catch (\Throwable $e) {
    Logger::log($logFile, 'recipe-inventory', 'exception', false, ['message' => $e->getMessage()]);
    Response::error('SERVER_ERROR', 'เกิดข้อผิดพลาดในการประมวลผล', 500);
}
