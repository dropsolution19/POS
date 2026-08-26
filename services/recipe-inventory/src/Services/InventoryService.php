<?php

require_once __DIR__ . '/../Repositories/ProductRepository.php';
require_once __DIR__ . '/../Repositories/IngredientRepository.php';
require_once __DIR__ . '/../Repositories/RecipeRepository.php';
require_once __DIR__ . '/../Repositories/StockRepository.php';

class InventoryService
{
    private ProductRepository $productRepo;
    private IngredientRepository $ingredientRepo;
    private RecipeRepository $recipeRepo;
    private StockRepository $stockRepo;

    public function __construct(string $dbPath)
    {
        $this->productRepo = new ProductRepository($dbPath);
        $this->ingredientRepo = new IngredientRepository($dbPath);
        $this->recipeRepo = new RecipeRepository($dbPath);
        $this->stockRepo = new StockRepository($dbPath);
    }

    public function processPurchase(int $ingredientId, float $quantityKg, float $pricePerKg, ?string $purchaseDate = null): array
    {
        if ($quantityKg <= 0) {
            throw new Exception("Quantity must be greater than 0");
        }
        if ($pricePerKg < 0) {
            throw new Exception("Price per kg cannot be negative");
        }

        $ingredient = $this->ingredientRepo->findById($ingredientId);
        if (!$ingredient) {
            throw new Exception("Ingredient not found");
        }

        $purchaseDate = $purchaseDate ?: date('Y-m-d H:i:s');

        $oldStock = (float)$ingredient['current_stock'];
        $oldAvgCost = (float)$ingredient['average_cost_per_kg'];

        if ($oldStock <= 0) {
            $newAvgCost = $pricePerKg;
        } else {
            $oldValue = $oldStock * $oldAvgCost;
            $newValue = $quantityKg * $pricePerKg;
            $newAvgCost = ($oldValue + $newValue) / ($oldStock + $quantityKg);
        }

        $newStock = $oldStock + $quantityKg;

        $pdo = $this->stockRepo->getPdo();
        $pdo->beginTransaction();
        try {
            $this->ingredientRepo->updateStockAndCost($ingredientId, $newStock, $newAvgCost);
            $purchaseId = $this->stockRepo->recordPurchase($ingredientId, $quantityKg, $pricePerKg, $purchaseDate);
            $this->stockRepo->recordTransaction($ingredientId, 'PURCHASE', $quantityKg, $pricePerKg, 'PURCHASE', (string)$purchaseId);
            $pdo->commit();

            return [
                'ingredient_id' => $ingredientId,
                'name' => $ingredient['name'],
                'added_stock' => $quantityKg,
                'new_stock' => $newStock,
                'average_cost_per_kg' => round($newAvgCost, 4)
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function calculateProductCost(int $productId): float
    {
        $recipe = $this->recipeRepo->getByProductId($productId);
        if (!$recipe || empty($recipe['items'])) {
            return 0.0;
        }

        $totalCost = 0.0;
        foreach ($recipe['items'] as $item) {
            $qty = (float)$item['quantity_kg'];
            $avgCost = (float)$item['average_cost_per_kg'];
            $totalCost += ($qty * $avgCost);
        }

        return round($totalCost, 4);
    }

    public function checkOrderStock(array $items): array
    {
        $requiredMap = [];

        foreach ($items as $item) {
            $productId = (int)$item['product_id'];
            $orderQty = (int)$item['quantity'];

            $recipe = $this->recipeRepo->getByProductId($productId);
            if (!$recipe || empty($recipe['items'])) {
                continue;
            }

            foreach ($recipe['items'] as $recipeItem) {
                $ingId = (int)$recipeItem['ingredient_id'];
                $needKg = (float)$recipeItem['quantity_kg'] * $orderQty;

                if (!isset($requiredMap[$ingId])) {
                    $requiredMap[$ingId] = [
                        'ingredient_id' => $ingId,
                        'name' => $recipeItem['ingredient_name'],
                        'required_kg' => 0.0,
                        'current_stock' => (float)$recipeItem['current_stock']
                    ];
                }
                $requiredMap[$ingId]['required_kg'] += $needKg;
            }
        }

        $insufficient = [];
        foreach ($requiredMap as $ingId => $info) {
            if ($info['current_stock'] < $info['required_kg']) {
                $insufficient[] = [
                    'ingredient_id' => $ingId,
                    'name' => $info['name'],
                    'required' => round($info['required_kg'], 4),
                    'available' => round($info['current_stock'], 4)
                ];
            }
        }

        if (!empty($insufficient)) {
            return [
                'sufficient' => false,
                'insufficient_ingredients' => $insufficient
            ];
        }

        return ['sufficient' => true];
    }

    public function consumeStockForOrder(string $orderId, array $items): array
    {
        // Check idempotency first: if reference_id = orderId already recorded as USAGE, don't re-deduct
        $stmt = $this->stockRepo->getPdo()->prepare("
            SELECT COUNT(*) as cnt FROM stock_transactions
            WHERE reference_type = 'ORDER' AND reference_id = :order_id AND transaction_type = 'USAGE'
        ");
        $stmt->execute(['order_id' => $orderId]);
        $res = $stmt->fetch();

        if ($res && (int)$res['cnt'] > 0) {
            // Already processed! Return snapshot cost without deducting stock again
            $productCosts = [];
            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $productCosts[$productId] = $this->calculateProductCost($productId);
            }
            return [
                'success' => true,
                'already_processed' => true,
                'product_costs' => $productCosts
            ];
        }

        // Validate stock sufficiency
        $check = $this->checkOrderStock($items);
        if (!$check['sufficient']) {
            return [
                'success' => false,
                'error_code' => 'INSUFFICIENT_STOCK',
                'message' => 'วัตถุดิบไม่เพียงพอ',
                'details' => $check['insufficient_ingredients']
            ];
        }

        $pdo = $this->stockRepo->getPdo();
        $pdo->beginTransaction();
        try {
            $productCosts = [];

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $orderQty = (int)$item['quantity'];

                $productCosts[$productId] = $this->calculateProductCost($productId);
                $recipe = $this->recipeRepo->getByProductId($productId);

                if ($recipe && !empty($recipe['items'])) {
                    foreach ($recipe['items'] as $recipeItem) {
                        $ingId = (int)$recipeItem['ingredient_id'];
                        $deductKg = (float)$recipeItem['quantity_kg'] * $orderQty;

                        $ingredient = $this->ingredientRepo->findById($ingId);
                        $currentStock = (float)$ingredient['current_stock'];
                        $newStock = $currentStock - $deductKg;
                        $avgCost = (float)$ingredient['average_cost_per_kg'];

                        if ($newStock < 0) {
                            throw new Exception("INSUFFICIENT_STOCK for ingredient ID: " . $ingId);
                        }

                        $this->ingredientRepo->updateStockAndCost($ingId, $newStock, $avgCost);
                        $this->stockRepo->recordTransaction($ingId, 'USAGE', -$deductKg, $avgCost, 'ORDER', (string)$orderId);
                    }
                }
            }

            $pdo->commit();

            return [
                'success' => true,
                'already_processed' => false,
                'product_costs' => $productCosts
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return [
                'success' => false,
                'error_code' => 'STOCK_CONSUME_FAILED',
                'message' => $e->getMessage()
            ];
        }
    }
}
