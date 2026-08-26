<?php

require_once __DIR__ . '/../../../shared/Database.php';

class PosMigrator
{
    public static function migrate(string $dbPath): void
    {
        $pdo = Database::getConnection($dbPath);

        $queries = [
            "CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_number TEXT UNIQUE NOT NULL,
                total_amount REAL NOT NULL CHECK(total_amount >= 0),
                received_amount REAL NOT NULL CHECK(received_amount >= 0),
                change_amount REAL NOT NULL CHECK(change_amount >= 0),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );",

            "CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                product_name TEXT NOT NULL,
                quantity INTEGER NOT NULL CHECK(quantity > 0),
                unit_price REAL NOT NULL CHECK(unit_price >= 0),
                total_price REAL NOT NULL CHECK(total_price >= 0),
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            );"
        ];

        foreach ($queries as $sql) {
            $pdo->exec($sql);
        }
    }
}
