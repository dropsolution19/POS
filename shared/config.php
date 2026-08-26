<?php

return [
    'services' => [
        'pos' => [
            'url' => 'http://127.0.0.1:8001',
            'db' => __DIR__ . '/../services/pos/database/pos.sqlite',
            'log' => __DIR__ . '/../logs/pos.log'
        ],
        'recipe_inventory' => [
            'url' => 'http://127.0.0.1:8002',
            'db' => __DIR__ . '/../services/recipe-inventory/database/inventory.sqlite',
            'log' => __DIR__ . '/../logs/inventory.log'
        ],
        'revenue' => [
            'url' => 'http://127.0.0.1:8003',
            'db' => __DIR__ . '/../services/revenue/database/revenue.sqlite',
            'log' => __DIR__ . '/../logs/revenue.log'
        ]
    ]
];
