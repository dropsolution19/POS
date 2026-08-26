# POS Microservices System - API Documentation

## System Base Architecture & Endpoints

| Service Name | Base URL | Database File | Responsibility |
|--------------|----------|---------------|----------------|
| **POS Service** | `http://127.0.0.1:8001` | `services/pos/database/pos.sqlite` | Orders, Checkout, Inter-service Orchestration |
| **Recipe & Inventory Service** | `http://127.0.0.1:8002` | `services/recipe-inventory/database/inventory.sqlite` | Products, Recipes, Stock Purchases, Weighted Avg Cost |
| **Revenue Service** | `http://127.0.0.1:8003` | `services/revenue/database/revenue.sqlite` | Sales Records, Snapshot Costs, Profit Reports |
| **Frontend** | `http://127.0.0.1:8000` | - | Vanilla HTML5 / CSS3 / JS UI |

---

## 1. Recipe & Inventory Service (`http://127.0.0.1:8002`)

### `GET /api/products`
Retrieves all products with dynamically calculated recipe cost and estimated profit.

**Response `200 OK`**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "ข้าวกะเพราไก่",
      "selling_price": 60,
      "cost_price": 10.8,
      "estimated_profit": 49.2,
      "recipe": { ... }
    }
  ]
}
```

### `POST /api/products`
Creates a new food menu item.

**Request Payload**:
```json
{
  "name": "ข้าวผัดหมู",
  "selling_price": 55.0
}
```

### `PUT /api/products/{id}`
Updates product details.

### `DELETE /api/products/{id}`
Deletes a product item.

---

### `GET /api/ingredients`
Lists all raw ingredients, current stock levels, and weighted average costs.

**Response `200 OK`**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "ไก่",
      "unit": "kg",
      "current_stock": 10.0,
      "average_cost_per_kg": 80.0
    }
  ]
}
```

### `POST /api/ingredients`
Creates a new raw ingredient.

### `POST /api/ingredients/purchase`
Records raw ingredient purchases. Automatically updates stock level and calculates new weighted average cost per kg.

**Request Payload**:
```json
{
  "ingredient_id": 1,
  "quantity_kg": 5.0,
  "price_per_kg": 100.0,
  "purchase_date": "2026-08-26 19:00:00"
}
```

**Formula Used**:
`New Avg Cost = (Old Stock * Old Avg Cost + New Qty * New Price) / (Old Stock + New Qty)`

---

### `GET /api/recipes/product/{productId}`
Gets current ingredient formula for a product.

### `POST /api/recipes`
Creates or updates a product's ingredient formula.

**Request Payload**:
```json
{
  "product_id": 1,
  "ingredients": [
    { "ingredient_id": 1, "quantity_kg": 0.100 },
    { "ingredient_id": 3, "quantity_kg": 0.020 }
  ]
}
```

---

### `POST /api/inventory/check`
Validates stock sufficiency for order items before checkout.

### `POST /api/inventory/consume`
Deducts stock according to recipes and returns snapshot unit costs per product. Features idempotency protection via `order_id`.

---

## 2. POS Service (`http://127.0.0.1:8001`)

### `GET /api/orders`
Retrieves past orders with itemized details.

### `POST /api/orders`
Processes order checkout. Performs payment verification, stock pre-check, stock deduction call, and revenue logging call.

**Request Payload**:
```json
{
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 3, "quantity": 1 }
  ],
  "received_amount": 200.0
}
```

**Response `201 Created`**:
```json
{
  "success": true,
  "data": {
    "order_id": 1,
    "order_number": "ORD-20260826190000-123",
    "total_amount": 170.0,
    "received_amount": 200.0,
    "change_amount": 30.0,
    "items": [ ... ]
  }
}
```

---

## 3. Revenue Service (`http://127.0.0.1:8003`)

### `POST /api/revenue/record`
Logs sales snapshot records from POS service.

### `GET /api/revenue/summary?period=today`
Gets sales summary (Total Revenue, Total Cost, Total Profit, Total Orders).
Supported period params: `today`, `yesterday`, `last_7_days`, `this_month`, or `start_date` / `end_date`.

### `GET /api/revenue/reports`
Gets detailed itemized sale reports.
