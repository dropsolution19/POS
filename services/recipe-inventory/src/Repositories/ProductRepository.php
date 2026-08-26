<?php

require_once __DIR__ . '/../../../../shared/Database.php';

class ProductRepository
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = Database::getConnection($dbPath);
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM products ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function create(string $name, float $sellingPrice): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO products (name, selling_price, created_at, updated_at)
            VALUES (:name, :selling_price, datetime('now'), datetime('now'))
        ");
        $stmt->execute([
            'name' => $name,
            'selling_price' => $sellingPrice
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, float $sellingPrice): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE products
            SET name = :name, selling_price = :selling_price, updated_at = datetime('now')
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'name' => $name,
            'selling_price' => $sellingPrice
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
