<?php

require_once __DIR__ . '/../../../../shared/Database.php';

class RevenueRepository
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = Database::getConnection($dbPath);
    }

    public function recordBatch(array $records): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO revenue_records (order_id, order_number, product_id, product_name, quantity, selling_price, cost_price, profit, created_at)
                VALUES (:order_id, :order_number, :product_id, :product_name, :quantity, :selling_price, :cost_price, :profit, datetime('now'))
            ");

            foreach ($records as $r) {
                $stmt->execute([
                    'order_id' => $r['order_id'],
                    'order_number' => $r['order_number'],
                    'product_id' => $r['product_id'],
                    'product_name' => $r['product_name'],
                    'quantity' => $r['quantity'],
                    'selling_price' => $r['selling_price'],
                    'cost_price' => $r['cost_price'],
                    'profit' => $r['profit']
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $whereClause = "";
        $params = [];

        if ($startDate && $endDate) {
            $whereClause = " WHERE date(created_at) BETWEEN date(:start_date) AND date(:end_date)";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        } elseif ($startDate) {
            $whereClause = " WHERE date(created_at) >= date(:start_date)";
            $params['start_date'] = $startDate;
        }

        $sql = "
            SELECT
                COALESCE(SUM(selling_price), 0.0) as total_revenue,
                COALESCE(SUM(cost_price), 0.0) as total_cost,
                COALESCE(SUM(profit), 0.0) as total_profit,
                COUNT(DISTINCT order_id) as total_orders,
                COALESCE(SUM(quantity), 0) as total_items_sold
            FROM revenue_records
            {$whereClause}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();

        return [
            'total_revenue' => round((float)$res['total_revenue'], 2),
            'total_cost' => round((float)$res['total_cost'], 2),
            'total_profit' => round((float)$res['total_profit'], 2),
            'total_orders' => (int)$res['total_orders'],
            'total_items_sold' => (int)$res['total_items_sold']
        ];
    }

    public function getReports(?string $startDate = null, ?string $endDate = null, int $limit = 100): array
    {
        $whereClause = "";
        $params = [];

        if ($startDate && $endDate) {
            $whereClause = " WHERE date(created_at) BETWEEN date(:start_date) AND date(:end_date)";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        } elseif ($startDate) {
            $whereClause = " WHERE date(created_at) >= date(:start_date)";
            $params['start_date'] = $startDate;
        }

        $sql = "
            SELECT * FROM revenue_records
            {$whereClause}
            ORDER BY id DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
