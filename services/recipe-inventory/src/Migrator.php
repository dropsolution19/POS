<?php

require_once __DIR__ . '/../../../shared/Database.php';

class InventoryMigrator
{
    public static function migrate(string $dbPath): void
    {
        $pdo = Database::getConnection($dbPath);

        $queries = [
            "CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                selling_price REAL NOT NULL CHECK(selling_price >= 0),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );",

            "CREATE TABLE IF NOT EXISTS ingredients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                unit TEXT DEFAULT 'kg',
                current_stock REAL DEFAULT 0.0 CHECK(current_stock >= 0),
                average_cost_per_kg REAL DEFAULT 0.0 CHECK(average_cost_per_kg >= 0),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );",

            "CREATE TABLE IF NOT EXISTS recipes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER UNIQUE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS recipe_ingredients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                recipe_id INTEGER NOT NULL,
                ingredient_id INTEGER NOT NULL,
                quantity_kg REAL NOT NULL CHECK(quantity_kg > 0),
                FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
                FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS stock_purchases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ingredient_id INTEGER NOT NULL,
                quantity_kg REAL NOT NULL CHECK(quantity_kg > 0),
                price_per_kg REAL NOT NULL CHECK(price_per_kg >= 0),
                total_cost REAL NOT NULL CHECK(total_cost >= 0),
                purchase_date DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS stock_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ingredient_id INTEGER NOT NULL,
                transaction_type TEXT NOT NULL,
                quantity_kg REAL NOT NULL,
                unit_cost REAL NOT NULL CHECK(unit_cost >= 0),
                reference_type TEXT,
                reference_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
            );"
        ];

        foreach ($queries as $sql) {
            $pdo->exec($sql);
        }
    }
}
