<?php

require_once __DIR__ . '/../../../../shared/Database.php';

class IngredientRepository
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = Database::getConnection($dbPath);
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM ingredients ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ingredients WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function create(string $name, string $unit = 'kg'): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ingredients (name, unit, current_stock, average_cost_per_kg, created_at, updated_at)
            VALUES (:name, :unit, 0.0, 0.0, datetime('now'), datetime('now'))
        ");
        $stmt->execute([
            'name' => $name,
            'unit' => $unit
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $unit): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ingredients
            SET name = :name, unit = :unit, updated_at = datetime('now')
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'name' => $name,
            'unit' => $unit
        ]);
    }

    public function updateStockAndCost(int $id, float $newStock, float $newAverageCost): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ingredients
            SET current_stock = :current_stock, average_cost_per_kg = :average_cost_per_kg, updated_at = datetime('now')
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'current_stock' => $newStock,
            'average_cost_per_kg' => $newAverageCost
        ]);
    }
}
