<?php

require_once __DIR__ . '/../../../../shared/Database.php';

class StockRepository
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = Database::getConnection($dbPath);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function recordPurchase(int $ingredientId, float $quantityKg, float $pricePerKg, string $purchaseDate): int
    {
        $totalCost = $quantityKg * $pricePerKg;
        $stmt = $this->pdo->prepare("
            INSERT INTO stock_purchases (ingredient_id, quantity_kg, price_per_kg, total_cost, purchase_date, created_at)
            VALUES (:ingredient_id, :quantity_kg, :price_per_kg, :total_cost, :purchase_date, datetime('now'))
        ");
        $stmt->execute([
            'ingredient_id' => $ingredientId,
            'quantity_kg' => $quantityKg,
            'price_per_kg' => $pricePerKg,
            'total_cost' => $totalCost,
            'purchase_date' => $purchaseDate
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function recordTransaction(int $ingredientId, string $type, float $quantityKg, float $unitCost, ?string $refType = null, ?string $refId = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO stock_transactions (ingredient_id, transaction_type, quantity_kg, unit_cost, reference_type, reference_id, created_at)
            VALUES (:ingredient_id, :type, :quantity_kg, :unit_cost, :ref_type, :ref_id, datetime('now'))
        ");
        $stmt->execute([
            'ingredient_id' => $ingredientId,
            'type' => $type,
            'quantity_kg' => $quantityKg,
            'unit_cost' => $unitCost,
            'ref_type' => $refType,
            'ref_id' => $refId
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getTransactions(?int $ingredientId = null): array
    {
        if ($ingredientId) {
            $stmt = $this->pdo->prepare("
                SELECT st.*, i.name as ingredient_name
                FROM stock_transactions st
                JOIN ingredients i ON st.ingredient_id = i.id
                WHERE st.ingredient_id = :ingredient_id
                ORDER BY st.id DESC
            ");
            $stmt->execute(['ingredient_id' => $ingredientId]);
        } else {
            $stmt = $this->pdo->query("
                SELECT st.*, i.name as ingredient_name
                FROM stock_transactions st
                JOIN ingredients i ON st.ingredient_id = i.id
                ORDER BY st.id DESC
            ");
        }
        return $stmt->fetchAll();
    }
}
