<?php

require_once __DIR__ . '/../../../shared/Database.php';

class RevenueMigrator
{
    public static function migrate(string $dbPath): void
    {
        $pdo = Database::getConnection($dbPath);

        $sql = "CREATE TABLE IF NOT EXISTS revenue_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            order_number TEXT NOT NULL,
            product_id INTEGER NOT NULL,
            product_name TEXT NOT NULL,
            quantity INTEGER NOT NULL CHECK(quantity > 0),
            selling_price REAL NOT NULL,
            cost_price REAL NOT NULL,
            profit REAL NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";

        $pdo->exec($sql);
    }
}
