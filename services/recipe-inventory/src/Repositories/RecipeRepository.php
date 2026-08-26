<?php

require_once __DIR__ . '/../../../../shared/Database.php';

class RecipeRepository
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = Database::getConnection($dbPath);
    }

    public function getByProductId(int $productId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM recipes WHERE product_id = :product_id");
        $stmt->execute(['product_id' => $productId]);
        $recipe = $stmt->fetch();
        if (!$recipe) {
            return null;
        }

        $stmtItems = $this->pdo->prepare("
            SELECT ri.id, ri.recipe_id, ri.ingredient_id, ri.quantity_kg,
                   i.name as ingredient_name, i.unit, i.average_cost_per_kg, i.current_stock
            FROM recipe_ingredients ri
            JOIN ingredients i ON ri.ingredient_id = i.id
            WHERE ri.recipe_id = :recipe_id
        ");
        $stmtItems->execute(['recipe_id' => $recipe['id']]);
        $recipe['items'] = $stmtItems->fetchAll();

        return $recipe;
    }

    public function saveRecipe(int $productId, array $ingredients): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM recipes WHERE product_id = :product_id");
            $stmt->execute(['product_id' => $productId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $recipeId = (int)$existing['id'];
                $stmtDel = $this->pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = :recipe_id");
                $stmtDel->execute(['recipe_id' => $recipeId]);
                $stmtUpd = $this->pdo->prepare("UPDATE recipes SET updated_at = datetime('now') WHERE id = :id");
                $stmtUpd->execute(['id' => $recipeId]);
            } else {
                $stmtIns = $this->pdo->prepare("INSERT INTO recipes (product_id, created_at, updated_at) VALUES (:product_id, datetime('now'), datetime('now'))");
                $stmtIns->execute(['product_id' => $productId]);
                $recipeId = (int)$this->pdo->lastInsertId();
            }

            $stmtItem = $this->pdo->prepare("
                INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity_kg)
                VALUES (:recipe_id, :ingredient_id, :quantity_kg)
            ");

            foreach ($ingredients as $item) {
                $stmtItem->execute([
                    'recipe_id' => $recipeId,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity_kg' => $item['quantity_kg']
                ]);
            }

            $this->pdo->commit();
            return $recipeId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
