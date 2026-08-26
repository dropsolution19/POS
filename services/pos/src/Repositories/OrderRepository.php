<?php

require_once __DIR__ . '/../../../../shared/Database.php';

class OrderRepository
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

    public function getAll(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM orders ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $stmtItems = $this->pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
            $stmtItems->execute(['order_id' => $order['id']]);
            $order['items'] = $stmtItems->fetchAll();
        }

        return $orders;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();
        if (!$order) return null;

        $stmtItems = $this->pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
        $stmtItems->execute(['order_id' => $id]);
        $order['items'] = $stmtItems->fetchAll();

        return $order;
    }

    public function create(string $orderNumber, float $totalAmount, float $receivedAmount, float $changeAmount, array $items): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO orders (order_number, total_amount, received_amount, change_amount, created_at)
                VALUES (:order_number, :total_amount, :received_amount, :change_amount, datetime('now'))
            ");
            $stmt->execute([
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount,
                'received_amount' => $receivedAmount,
                'change_amount' => $changeAmount
            ]);
            $orderId = (int)$this->pdo->lastInsertId();

            $stmtItem = $this->pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price)
                VALUES (:order_id, :product_id, :product_name, :quantity, :unit_price, :total_price)
            ");

            foreach ($items as $item) {
                $stmtItem->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price']
                ]);
            }

            $this->pdo->commit();
            return $orderId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
