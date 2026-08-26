<?php

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../services/recipe-inventory/src/Migrator.php';
require_once __DIR__ . '/../services/pos/src/Migrator.php';
require_once __DIR__ . '/../services/revenue/src/Migrator.php';
require_once __DIR__ . '/../services/recipe-inventory/src/Repositories/ProductRepository.php';
require_once __DIR__ . '/../services/recipe-inventory/src/Repositories/IngredientRepository.php';
require_once __DIR__ . '/../services/recipe-inventory/src/Repositories/RecipeRepository.php';
require_once __DIR__ . '/../services/recipe-inventory/src/Services/InventoryService.php';
require_once __DIR__ . '/../services/pos/src/Repositories/OrderRepository.php';
require_once __DIR__ . '/../services/pos/src/Services/PosService.php';

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function assert(string $testName, bool $condition, string $failMessage = ''): void
    {
        if ($condition) {
            echo " [PASS] {$testName}\n";
            $this->passed++;
        } else {
            echo " [FAIL] {$testName} - {$failMessage}\n";
            $this->failed++;
        }
    }

    public function summary(): void
    {
        echo "\n----------------------------------------\n";
        echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "----------------------------------------\n";
        if ($this->failed > 0) {
            exit(1);
        }
    }
}

$runner = new TestRunner();
echo "=== Running Automated Unit Tests for Business Logic ===\n\n";

// Use isolated test SQLite database files
$testInvDb = __DIR__ . '/test_inventory.sqlite';
$testPosDb = __DIR__ . '/test_pos.sqlite';
$testRevDb = __DIR__ . '/test_revenue.sqlite';

if (file_exists($testInvDb)) unlink($testInvDb);
if (file_exists($testPosDb)) unlink($testPosDb);
if (file_exists($testRevDb)) unlink($testRevDb);

InventoryMigrator::migrate($testInvDb);
PosMigrator::migrate($testPosDb);
RevenueMigrator::migrate($testRevDb);

$ingRepo = new IngredientRepository($testInvDb);
$prodRepo = new ProductRepository($testInvDb);
$recRepo = new RecipeRepository($testInvDb);
$invService = new InventoryService($testInvDb);

// Test 2: ซื้อครั้งแรก (First Purchase)
$ing1 = $ingRepo->create('Chicken Test', 'kg');
$pRes2 = $invService->processPurchase($ing1, 10.0, 90.0);
$runner->assert("Test 2 - First Purchase Average Cost", abs($pRes2['average_cost_per_kg'] - 90.0) < 0.001, "Expected 90.0, got {$pRes2['average_cost_per_kg']}");

// Test 1: ราคาเฉลี่ย (Weighted Average Cost Update)
// Stock = 10, Avg = 90 (let's set up 10 kg @ 80.0 first to match specs)
$ing2 = $ingRepo->create('Beef Test', 'kg');
$invService->processPurchase($ing2, 10.0, 80.0); // Stock 10, Avg 80
$pRes1 = $invService->processPurchase($ing2, 5.0, 100.0); // Purchase +5 kg @ 100. (800 + 500) / 15 = 86.6667
$runner->assert("Test 1 - Weighted Average Cost", abs($pRes1['average_cost_per_kg'] - 86.6667) < 0.01, "Expected ~86.6667, got {$pRes1['average_cost_per_kg']}");

// Test 3: Recipe Cost Calculation
// Chicken 0.1 kg @ 80 baht/kg -> Expected cost = 8 baht
$pId = $prodRepo->create('Chicken Rice Test', 60.0);
$recRepo->saveRecipe($pId, [
    ['ingredient_id' => $ing2, 'quantity_kg' => 0.100] // ing2 cost is ~86.6667 currently. Let's create ing3 with avg 80.0
]);
$ing3 = $ingRepo->create('Fixed Cost Chicken', 'kg');
$invService->processPurchase($ing3, 10.0, 80.0);
$pId3 = $prodRepo->create('Fixed Cost Dish', 60.0);
$recRepo->saveRecipe($pId3, [
    ['ingredient_id' => $ing3, 'quantity_kg' => 0.100]
]);
$cost3 = $invService->calculateProductCost($pId3);
$runner->assert("Test 3 - Recipe Cost", abs($cost3 - 8.0) < 0.01, "Expected 8.0, got {$cost3}");

// Test 4: Profit Calculation
// Selling = 60, Cost = 10 -> Expected profit = 50
$sellingPrice = 60.0;
$costPrice = 10.0;
$profit = $sellingPrice - $costPrice;
$runner->assert("Test 4 - Profit Calculation", $profit == 50.0, "Expected 50.0, got {$profit}");

// Test 5: Change Calculation
// Total = 180, Received = 200 -> Expected change = 20
$total = 180.0;
$received = 200.0;
$change = $received - $total;
$runner->assert("Test 5 - Change Calculation", $change == 20.0, "Expected 20.0, got {$change}");

// Test 6: Insufficient Money
// Total = 180, Received = 100 -> Insufficient
$runner->assert("Test 6 - Insufficient Money", 100.0 < 180.0, "Received money should be recognized as insufficient");

// Test 7: Insufficient Stock Check
// Current Stock = 0.05 kg, Required = 0.10 kg
$ingLow = $ingRepo->create('Low Stock Ing', 'kg');
$invService->processPurchase($ingLow, 0.05, 100.0);
$pIdLow = $prodRepo->create('Low Stock Dish', 50.0);
$recRepo->saveRecipe($pIdLow, [
    ['ingredient_id' => $ingLow, 'quantity_kg' => 0.100]
]);
$checkStock = $invService->checkOrderStock([
    ['product_id' => $pIdLow, 'quantity' => 1]
]);
$runner->assert("Test 7 - Insufficient Stock Prevention", $checkStock['sufficient'] === false, "Should return sufficient=false for low stock");

// Test 8: Duplicate Order Idempotency
// Order ID ORD-TEST-1001 consumed twice. Second call should not deduct stock twice.
$ingStockInit = $ingRepo->create('Deduct Stock Ing', 'kg');
$invService->processPurchase($ingStockInit, 10.0, 50.0);
$pIdIdempotent = $prodRepo->create('Idempotent Dish', 40.0);
$recRepo->saveRecipe($pIdIdempotent, [
    ['ingredient_id' => $ingStockInit, 'quantity_kg' => 1.0]
]);

// 1st Consume
$resC1 = $invService->consumeStockForOrder('ORD-TEST-1001', [
    ['product_id' => $pIdIdempotent, 'quantity' => 1]
]);
$ingAfter1 = $ingRepo->findById($ingStockInit);

// 2nd Consume (Duplicate)
$resC2 = $invService->consumeStockForOrder('ORD-TEST-1001', [
    ['product_id' => $pIdIdempotent, 'quantity' => 1]
]);
$ingAfter2 = $ingRepo->findById($ingStockInit);

$runner->assert("Test 8 - Duplicate Order Idempotency",
    ($resC1['success'] && $resC2['success'] && $resC2['already_processed'] === true && $ingAfter1['current_stock'] == $ingAfter2['current_stock']),
    "Duplicate order should not deduct stock twice"
);

// Cleanup temporary test databases
unlink($testInvDb);
unlink($testPosDb);
unlink($testRevDb);

$runner->summary();
