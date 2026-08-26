<?php

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../services/recipe-inventory/src/Migrator.php';
require_once __DIR__ . '/../services/pos/src/Migrator.php';
require_once __DIR__ . '/../services/revenue/src/Migrator.php';
require_once __DIR__ . '/../services/recipe-inventory/src/Repositories/ProductRepository.php';
require_once __DIR__ . '/../services/recipe-inventory/src/Repositories/IngredientRepository.php';
require_once __DIR__ . '/../services/recipe-inventory/src/Repositories/RecipeRepository.php';
require_once __DIR__ . '/../services/recipe-inventory/src/Services/InventoryService.php';

$config = require __DIR__ . '/../shared/config.php';

$inventoryDb = $config['services']['recipe_inventory']['db'];
$posDb = $config['services']['pos']['db'];
$revenueDb = $config['services']['revenue']['db'];

echo "=== Initializing Databases & Running Migrations ===\n";
InventoryMigrator::migrate($inventoryDb);
PosMigrator::migrate($posDb);
RevenueMigrator::migrate($revenueDb);

echo "=== Seeding Sample Data ===\n";
$productRepo = new ProductRepository($inventoryDb);
$ingredientRepo = new IngredientRepository($inventoryDb);
$recipeRepo = new RecipeRepository($inventoryDb);
$inventoryService = new InventoryService($inventoryDb);

// Check if ingredients already exist
$existingIngredients = $ingredientRepo->getAll();
if (empty($existingIngredients)) {
    // 1. Create Ingredients
    $ingChicken = $ingredientRepo->create('ไก่', 'kg');
    $ingPork = $ingredientRepo->create('หมู', 'kg');
    $ingBasil = $ingredientRepo->create('ใบกะเพรา', 'kg');
    $ingChili = $ingredientRepo->create('พริก', 'kg');
    $ingGarlic = $ingredientRepo->create('กระเทียม', 'kg');
    $ingOil = $ingredientRepo->create('น้ำมัน', 'kg');
    $ingRice = $ingredientRepo->create('ข้าว', 'kg');

    echo "Created 7 Ingredients\n";

    // 2. Add Initial Purchases
    $inventoryService->processPurchase($ingChicken, 10.0, 80.00);
    $inventoryService->processPurchase($ingPork, 10.0, 120.00);
    $inventoryService->processPurchase($ingBasil, 2.0, 50.00);
    $inventoryService->processPurchase($ingChili, 2.0, 60.00);
    $inventoryService->processPurchase($ingGarlic, 2.0, 40.00);
    $inventoryService->processPurchase($ingOil, 5.0, 45.00);
    $inventoryService->processPurchase($ingRice, 20.0, 30.00);

    echo "Purchased initial stock & calculated weighted average costs\n";

    // 3. Create Products & Recipes
    // Product 1: ข้าวกะเพราไก่ (60 บาท)
    $p1 = $productRepo->create('ข้าวกะเพราไก่', 60.00);
    $recipeRepo->saveRecipe($p1, [
        ['ingredient_id' => $ingChicken, 'quantity_kg' => 0.100],
        ['ingredient_id' => $ingBasil, 'quantity_kg' => 0.020],
        ['ingredient_id' => $ingChili, 'quantity_kg' => 0.010],
        ['ingredient_id' => $ingGarlic, 'quantity_kg' => 0.005],
        ['ingredient_id' => $ingOil, 'quantity_kg' => 0.010]
    ]);

    // Product 2: ข้าวกะเพราหมู (65 บาท)
    $p2 = $productRepo->create('ข้าวกะเพราหมู', 65.00);
    $recipeRepo->saveRecipe($p2, [
        ['ingredient_id' => $ingPork, 'quantity_kg' => 0.100],
        ['ingredient_id' => $ingBasil, 'quantity_kg' => 0.020],
        ['ingredient_id' => $ingChili, 'quantity_kg' => 0.010],
        ['ingredient_id' => $ingGarlic, 'quantity_kg' => 0.005],
        ['ingredient_id' => $ingOil, 'quantity_kg' => 0.010]
    ]);

    // Product 3: ข้าวผัด (50 บาท)
    $p3 = $productRepo->create('ข้าวผัด', 50.00);
    $recipeRepo->saveRecipe($p3, [
        ['ingredient_id' => $ingPork, 'quantity_kg' => 0.080],
        ['ingredient_id' => $ingRice, 'quantity_kg' => 0.150],
        ['ingredient_id' => $ingOil, 'quantity_kg' => 0.010]
    ]);

    echo "Created 3 Products with recipes\n";
} else {
    echo "Seed data already exists, skipping.\n";
}

echo "=== Seeding Completed Successfully ===\n";
