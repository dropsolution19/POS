# POS Microservices System

## Technology Stack: PHP + SQLite

## 1. เป้าหมายของระบบ

พัฒนาโปรแกรม POS (Point of Sale) สำหรับร้านอาหาร โดยใช้สถาปัตยกรรมแบบ **Microservices** และแบ่งระบบออกเป็น 3 ระบบหลัก ได้แก่

1. ระบบเติมเงิน / ขายสินค้า (POS)
2. ระบบสูตรอาหาร + สต๊อกวัตถุดิบ
3. ระบบรายได้และกำไร

Technology ที่ต้องใช้:

* Backend: PHP 8.2+
* Database: SQLite
* API: REST API
* Data format: JSON
* Frontend: HTML5 + CSS3 + JavaScript
* สามารถใช้ Vanilla JavaScript ได้ ไม่จำเป็นต้องใช้ Framework
* แต่ละ Microservice ต้องแยกความรับผิดชอบอย่างชัดเจน
* ระบบต้องสามารถรันบนเครื่อง Local ได้ง่าย
* รองรับการใช้งานบน Browser

---

# 2. Architecture

ให้สร้างระบบแบบ Microservices โดยแบ่งเป็น 3 Services หลัก

```text
POS System
│
├── POS Service
│   ├── รายการอาหาร
│   ├── ยอดรวม
│   ├── เงินที่รับ
│   └── เงินทอน
│
├── Recipe & Inventory Service
│   ├── สูตรอาหาร
│   ├── วัตถุดิบ
│   ├── ราคาขาย
│   ├── สต๊อก
│   ├── ราคาซื้อวันนี้
│   └── ราคาเฉลี่ยวัตถุดิบ
│
└── Revenue Service
    ├── รายการขาย
    ├── วัตถุดิบที่ใช้
    ├── ราคาต้นทุน
    ├── ราคาขาย
    └── กำไรสุทธิ
```

แต่ละ Service ควรมีโครงสร้างแยกกัน และติดต่อกันผ่าน REST API

ตัวอย่าง:

```text
Browser
   │
   ├──> POS Service
   │
   ├──> Recipe & Inventory Service
   │
   └──> Revenue Service
```

---

# 3. โครงสร้าง Project

ให้สร้างโครงสร้างประมาณนี้

```text
pos-system/
│
├── services/
│   │
│   ├── pos/
│   │   ├── public/
│   │   │   └── index.php
│   │   ├── src/
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   ├── Models/
│   │   │   └── Repositories/
│   │   ├── database/
│   │   │   └── pos.sqlite
│   │   └── config/
│   │
│   ├── recipe-inventory/
│   │   ├── public/
│   │   │   └── index.php
│   │   ├── src/
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   ├── Models/
│   │   │   └── Repositories/
│   │   ├── database/
│   │   │   └── inventory.sqlite
│   │   └── config/
│   │
│   └── revenue/
│       ├── public/
│       │   └── index.php
│       ├── src/
│       │   ├── Controllers/
│       │   ├── Services/
│       │   ├── Models/
│       │   └── Repositories/
│       ├── database/
│       │   └── revenue.sqlite
│       └── config/
│
├── frontend/
│   ├── index.html
│   ├── css/
│   └── js/
│
├── shared/
│   └── config/
│
├── README.md
└── .gitignore
```

---

# 4. Database Strategy

ให้ใช้ SQLite เป็น Database

สามารถแยก SQLite Database ตาม Microservice เพื่อให้แต่ละ Service มี Data Ownership ของตัวเอง

```text
pos.sqlite
inventory.sqlite
revenue.sqlite
```

ห้ามให้ Service หนึ่งเข้าไปแก้ไข Database ของ Service อื่นโดยตรง

ถ้าต้องการข้อมูลจาก Service อื่น ให้เรียกผ่าน REST API

---

# 5. Service 1 — POS Service

## หน้าที่

ระบบขายอาหาร / รับเงินจากลูกค้า

หน้าหลักต้องมี:

* รายการอาหาร
* จำนวน
* ราคาต่อหน่วย
* ราคารวมต่อรายการ
* ยอดเงินรวม
* เงินที่ลูกค้าจ่าย
* เงินทอน

---

## 5.1 POS Screen

หน้าจอควรมีลักษณะประมาณนี้

```text
------------------------------------------
              POS
------------------------------------------

สินค้า
------------------------------------------
อาหาร              จำนวน     ราคา    รวม
------------------------------------------
ข้าวผัด             2        50     100
กะเพรา              1        60      60
ไข่ดาว              2        10      20
------------------------------------------

ยอดรวม                         180 บาท

รับเงิน                         200 บาท

เงินทอน                          20 บาท

[ ชำระเงิน ]
------------------------------------------
```

---

# 6. POS Business Logic

เมื่อเลือกสินค้า:

```text
ราคาต่อหน่วย × จำนวน = ราคารวมรายการ
```

จากนั้น:

```text
ยอดรวม = SUM(ราคารวมทุกรายการ)
```

เงินทอน:

```text
เงินทอน = เงินที่รับ - ยอดรวม
```

ถ้าเงินที่รับน้อยกว่ายอดรวม ให้แสดง:

```text
เงินไม่เพียงพอ
```

และไม่อนุญาตให้ปิดการขาย

---

# 7. POS API

สร้าง REST API อย่างน้อยดังนี้

```text
GET /api/products
```

ดึงรายการอาหาร

```text
POST /api/orders
```

สร้างรายการขาย

ตัวอย่าง Request:

```json
{
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 3,
      "quantity": 1
    }
  ],
  "received_amount": 200
}
```

Response:

```json
{
  "success": true,
  "order_id": 1001,
  "total_amount": 180,
  "received_amount": 200,
  "change_amount": 20
}
```

---

# 8. Order Database

POS Service ควรมีตาราง

## orders

```text
id
order_number
total_amount
received_amount
change_amount
created_at
```

## order_items

```text
id
order_id
product_id
product_name
quantity
unit_price
total_price
```

ให้เก็บ `product_name` และ `unit_price` ณ เวลาที่ขายด้วย

เพื่อป้องกันปัญหาเมื่อราคาสินค้าเปลี่ยนในอนาคต

---

# 9. Service 2 — Recipe & Inventory Service

Service นี้แบ่งเป็น 2 ส่วน

```text
Recipe
Inventory
```

---

# 10. Recipe System — ระบบสร้างสูตรอาหาร

สามารถสร้างอาหารได้ เช่น

```text
อาหาร: ข้าวกะเพราไก่
ราคาขาย: 60 บาท
```

และกำหนดวัตถุดิบ

```text
ไก่       0.100 กก.
ใบกะเพรา  0.020 กก.
น้ำมัน     0.010 กก.
พริก       0.010 กก.
กระเทียม   0.005 กก.
```

---

# 11. Recipe Database

## products

```text
id
name
selling_price
created_at
updated_at
```

ตัวอย่าง:

```text
id = 1
name = ข้าวกะเพราไก่
selling_price = 60
```

---

## ingredients

```text
id
name
unit
current_stock
average_cost_per_kg
created_at
updated_at
```

ตัวอย่าง:

```text
id = 1
name = เนื้อไก่
unit = kg
current_stock = 10
average_cost_per_kg = 85
```

---

## recipes

```text
id
product_id
created_at
updated_at
```

---

## recipe_ingredients

```text
id
recipe_id
ingredient_id
quantity_kg
```

ตัวอย่าง:

```text
product:
ข้าวกะเพราไก่

ingredients:

ไก่       0.100 kg
ใบกะเพรา  0.020 kg
พริก       0.010 kg
```

---

# 12. Recipe Cost Calculation

ต้นทุนของสูตรอาหารต้องคำนวณจาก

```text
ปริมาณวัตถุดิบที่ใช้ × ราคาเฉลี่ยวัตถุดิบต่อกิโลกรัม
```

ตัวอย่าง:

```text
ไก่
ใช้ 0.100 kg
ราคาเฉลี่ย 85 บาท/kg

ต้นทุน = 0.100 × 85
       = 8.50 บาท
```

ถ้ามีหลายวัตถุดิบ:

```text
ต้นทุนสูตรอาหาร =
ต้นทุนไก่
+ ต้นทุนใบกะเพรา
+ ต้นทุนพริก
+ ต้นทุนกระเทียม
+ ...
```

---

# 13. ราคาขาย

แต่ละอาหารต้องมีราคาขาย

ตัวอย่าง:

```text
ข้าวกะเพราไก่

ราคาขาย = 60 บาท
ต้นทุน = 18 บาท
```

ข้อมูลต้นทุนต้องคำนวณแบบ Dynamic

ไม่ควรเก็บต้นทุนแบบ Fixed ในสินค้า เพราะราคาเฉลี่ยวัตถุดิบสามารถเปลี่ยนแปลงได้

---

# 14. Inventory System — ระบบสต๊อกวัตถุดิบ

ระบบต้องสามารถบันทึกการซื้อวัตถุดิบ

ข้อมูลที่ต้องบันทึก:

```text
วัตถุดิบ
จำนวนที่ซื้อ / กก.
ราคาที่ซื้อมาวันนี้ / กก.
ยอดเงินที่ซื้อ
วันที่ซื้อ
```

ตัวอย่าง:

```text
เนื้อไก่
ซื้อวันนี้ 10 kg
ราคา 90 บาท/kg

ยอดซื้อ = 900 บาท
```

---

# 15. Inventory Database

สร้างตาราง

## stock_purchases

```text
id
ingredient_id
quantity_kg
price_per_kg
total_cost
purchase_date
created_at
```

สูตร:

```text
total_cost = quantity_kg × price_per_kg
```

---

# 16. การคำนวณราคาเฉลี่ยวัตถุดิบ

นี่เป็น Business Logic สำคัญของระบบ

ถ้าวัตถุดิบมีของเก่าอยู่แล้ว และซื้อของใหม่เข้ามา ต้องคำนวณราคาเฉลี่ยแบบถ่วงน้ำหนักตามจำนวนกิโลกรัม

สูตร:

```text
ราคาเฉลี่ยใหม่ =
(
  มูลค่าสต๊อกเดิม
  + มูลค่าของที่ซื้อใหม่
)
/
(
  จำนวนสต๊อกเดิม
  + จำนวนที่ซื้อใหม่
)
```

หรือ:

```text
Average Cost =
(
  Old Stock × Old Average Cost
  +
  New Purchase Quantity × New Purchase Price
)
/
(
  Old Stock + New Purchase Quantity
)
```

---

# 17. ตัวอย่างการคำนวณราคาเฉลี่ย

มีไก่อยู่เดิม:

```text
ของเดิม = 10 kg
ราคาเฉลี่ยเดิม = 80 บาท/kg
```

มูลค่าของเดิม:

```text
10 × 80 = 800 บาท
```

ซื้อเพิ่ม:

```text
5 kg
ราคา 100 บาท/kg
```

มูลค่าของใหม่:

```text
5 × 100 = 500 บาท
```

ดังนั้น:

```text
ราคาเฉลี่ยใหม่ =
(800 + 500) / (10 + 5)

= 1,300 / 15

= 86.6667 บาท/kg
```

ให้ระบบเก็บค่าเป็น Decimal ที่มีความแม่นยำ เช่น 4 ตำแหน่ง

แสดงผลให้ผู้ใช้เห็นเป็น 2 ตำแหน่ง

```text
86.67 บาท/kg
```

---

# 18. กรณีไม่มี Stock เดิม

ถ้าวัตถุดิบไม่เคยมี Stock มาก่อน:

```text
Old Stock = 0
```

และซื้อครั้งแรก:

```text
10 kg
ราคา 90 บาท/kg
```

ราคาเฉลี่ยต้องเป็น:

```text
90 บาท/kg
```

ไม่ต้องนำไปเฉลี่ยกับวัตถุดิบอื่น

---

# 19. กรณีมีวัตถุดิบแต่ไม่เคยซื้อ

ต้องแยก `Ingredient` ออกจาก `Stock`

เช่นสร้างวัตถุดิบ:

```text
หมู
```

แต่ยังไม่เคยซื้อ

ระบบควรแสดง:

```text
Stock = 0 kg
Average Cost = 0
```

เมื่อมีการซื้อครั้งแรกจึงเริ่มคำนวณราคาเฉลี่ย

---

# 20. การตัด Stock เมื่อขายอาหาร

เมื่อ POS ขายอาหารสำเร็จ ต้องเรียก Inventory Service เพื่อหักวัตถุดิบตามสูตรอาหาร

ตัวอย่าง:

ขาย:

```text
ข้าวกะเพราไก่ 2 จาน
```

สูตรต่อ 1 จาน:

```text
ไก่ = 0.100 kg
```

ดังนั้นต้องหัก:

```text
0.100 × 2
= 0.200 kg
```

ถ้าใบกะเพรา:

```text
0.020 × 2
= 0.040 kg
```

Inventory Service ต้องบันทึก Transaction การใช้ Stock ด้วย

---

# 21. Stock Transactions

สร้างตาราง:

## stock_transactions

```text
id
ingredient_id
transaction_type
quantity_kg
unit_cost
reference_type
reference_id
created_at
```

ตัวอย่าง `transaction_type`:

```text
PURCHASE
USAGE
ADJUSTMENT
```

ตัวอย่างการขายอาหาร:

```text
ingredient_id = 1
transaction_type = USAGE
quantity_kg = -0.200
unit_cost = 86.67
reference_type = ORDER
reference_id = 1001
```

---

# 22. ป้องกัน Stock ติดลบ

ก่อนตัด Stock ต้องตรวจสอบว่า:

```text
current_stock >= required_quantity
```

ถ้าไม่พอ ให้ตอบ API ว่า:

```json
{
  "success": false,
  "error": "INSUFFICIENT_STOCK",
  "message": "วัตถุดิบไม่เพียงพอ"
}
```

ต้องไม่ทำให้ Stock ติดลบ

---

# 23. Service 3 — Revenue Service

ระบบรายได้ต้องแสดงข้อมูลการขายและต้นทุน

ข้อมูลหลัก:

```text
วัตถุดิบที่ใช้
ราคาต้นทุน
ราคาขาย
กำไรสุทธิ
```

---

# 24. Revenue Calculation

สำหรับอาหารแต่ละรายการ:

```text
ราคาต้นทุน =
SUM(
  ปริมาณวัตถุดิบที่ใช้
  ×
  ราคาเฉลี่ยวัตถุดิบ
)
```

จากนั้น:

```text
กำไรสุทธิ =
ราคาขาย - ราคาต้นทุน
```

ตัวอย่าง:

```text
อาหาร: ข้าวกะเพราไก่

ราคาขาย = 60 บาท

ต้นทุน:
ไก่ = 8.50
กะเพรา = 1.00
พริก = 0.50
กระเทียม = 0.30
น้ำมัน = 0.50

ต้นทุนรวม = 10.80 บาท

กำไรสุทธิ = 60 - 10.80
          = 49.20 บาท
```

---

# 25. Revenue Database

## revenue_records

```text
id
order_id
product_id
product_name
quantity
selling_price
cost_price
profit
created_at
```

โดย:

```text
profit = selling_price - cost_price
```

ถ้าขาย 2 จาน:

```text
selling_price = ราคาต่อจาน × 2
cost_price = ต้นทุนต่อจาน × 2
profit = selling_price - cost_price
```

---

# 26. สำคัญ: Snapshot ต้นทุน ณ เวลาขาย

เมื่อขายอาหาร ต้องบันทึกต้นทุน ณ เวลาที่ขายลงใน Revenue Service

ไม่ควรคำนวณต้นทุนย้อนหลังจากราคาเฉลี่ยปัจจุบัน เพราะราคาเฉลี่ยวัตถุดิบสามารถเปลี่ยนแปลงได้

ตัวอย่าง:

วันที่ 1:

```text
ไก่เฉลี่ย = 80 บาท/kg
```

ขายกะเพรา:

```text
ต้นทุน = 10 บาท
```

วันที่ 10:

```text
ไก่เฉลี่ย = 100 บาท/kg
```

ข้อมูลการขายวันที่ 1 ต้องยังคงเป็น:

```text
ต้นทุน = 10 บาท
```

ไม่ควรเปลี่ยนเป็นต้นทุนใหม่

---

# 27. Flow การขายแบบ Microservices

เมื่อผู้ใช้กดชำระเงิน:

```text
1. POS Service รับ Order
        │
        ▼
2. ตรวจสอบสินค้า
        │
        ▼
3. Recipe/Inventory Service
   ดึงสูตรอาหาร
        │
        ▼
4. คำนวณวัตถุดิบที่ต้องใช้
        │
        ▼
5. ตรวจสอบ Stock
        │
        ▼
6. คำนวณต้นทุน
        │
        ▼
7. ตัด Stock
        │
        ▼
8. POS Service บันทึก Order
        │
        ▼
9. Revenue Service บันทึกรายได้
        │
        ▼
10. แสดงเงินทอน
```

---

# 28. Transaction / Failure Handling

เนื่องจากเป็น Microservices ต้องระวังกรณี Service ใด Service หนึ่งทำงานไม่สำเร็จ

ตัวอย่าง:

```text
POS
 ↓
Inventory
 ↓
ตัด Stock สำเร็จ
 ↓
Revenue Service Error
```

ต้องไม่ปล่อยให้ข้อมูลเสียหาย

ให้ใช้แนวคิด Idempotency และ Compensation

ตัวอย่าง:

```text
order_id = 1001
```

ทุก Service ต้องสามารถตรวจสอบได้ว่า Transaction นี้เคยถูกประมวลผลแล้วหรือยัง

ห้ามตัด Stock ซ้ำจาก Order เดิม

---

# 29. API Communication

Service สามารถสื่อสารผ่าน HTTP REST API

ตัวอย่าง:

```text
POS Service
    ↓
GET /api/recipes/product/1
    ↓
Recipe Service
```

หรือ:

```text
POS Service
    ↓
POST /api/inventory/check
    ↓
Inventory Service
```

และ:

```text
POS Service
    ↓
POST /api/inventory/consume
    ↓
Inventory Service
```

---

# 30. API Response Standard

ทุก API ควรใช้รูปแบบ Response ที่เหมือนกัน

Success:

```json
{
  "success": true,
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "error": {
    "code": "INVALID_REQUEST",
    "message": "ข้อมูลไม่ถูกต้อง"
  }
}
```

HTTP Status ที่ควรใช้:

```text
200 OK
201 Created
400 Bad Request
404 Not Found
409 Conflict
422 Unprocessable Entity
500 Internal Server Error
```

---

# 31. หน้าจอหลัก

Frontend ควรมี Sidebar หรือ Navigation:

```text
POS
│
├── ขายสินค้า
│
├── อาหาร
│   ├── รายการอาหาร
│   └── สูตรอาหาร
│
├── วัตถุดิบ
│   ├── รายการวัตถุดิบ
│   ├── ซื้อวัตถุดิบ
│   └── สต๊อก
│
└── รายได้
    ├── รายได้วันนี้
    ├── รายได้รายวัน
    ├── รายได้รายเดือน
    └── กำไร
```

---

# 32. หน้าจอจัดการอาหาร

ต้องสามารถ:

* เพิ่มอาหาร
* แก้ไขอาหาร
* ลบอาหาร
* กำหนดราคาขาย
* สร้างสูตร
* เพิ่มวัตถุดิบในสูตร
* ระบุปริมาณวัตถุดิบเป็นกิโลกรัม
* ดูต้นทุนปัจจุบัน
* ดูกำไรโดยประมาณ

ตัวอย่าง:

```text
ชื่ออาหาร: ข้าวกะเพราไก่

ราคาขาย: 60.00 บาท

วัตถุดิบ
--------------------------------
วัตถุดิบ       จำนวน kg
--------------------------------
ไก่             0.100
ใบกะเพรา        0.020
พริก             0.010
กระเทียม         0.005
น้ำมัน           0.010
--------------------------------

ต้นทุนโดยประมาณ: 10.80 บาท
กำไรโดยประมาณ: 49.20 บาท
```

---

# 33. หน้าจอวัตถุดิบ

แสดง:

```text
วัตถุดิบ
--------------------------------------------
ชื่อ       Stock     ราคาเฉลี่ย     หน่วย
--------------------------------------------
ไก่        10.50 kg   86.67/kg      kg
หมู         8.00 kg   120.00/kg     kg
พริก        2.50 kg   85.00/kg      kg
```

---

# 34. หน้าจอซื้อวัตถุดิบ

Form:

```text
วัตถุดิบ
จำนวน kg
ราคาซื้อ / kg
วันที่ซื้อ

[บันทึก]
```

เมื่อบันทึก:

```text
เพิ่ม Stock
+
คำนวณ Average Cost ใหม่
+
บันทึก Purchase Transaction
```

---

# 35. Dashboard

หน้า Dashboard ควรแสดงข้อมูล:

```text
ยอดขายวันนี้
฿ 5,250

ต้นทุนวันนี้
฿ 1,850

กำไรวันนี้
฿ 3,400

จำนวนรายการขาย
82 รายการ
```

และสามารถเลือกวันที่:

```text
วันนี้
เมื่อวาน
7 วันที่ผ่านมา
เดือนนี้
กำหนดช่วงวันที่เอง
```

---

# 36. Revenue Report

รายงานควรมี:

```text
วันที่
เลขที่บิล
อาหาร
จำนวน
ราคาขาย
ต้นทุน
กำไร
```

ตัวอย่าง:

```text
วันที่       อาหาร            จำนวน  ขาย    ต้นทุน   กำไร
----------------------------------------------------------------
26/08/2026  กะเพราไก่         2     120     21.60    98.40
26/08/2026  ข้าวผัด           1      50     18.00    32.00
```

---

# 37. Revenue Summary

สามารถสรุป:

```text
ยอดขายรวม
ต้นทุนรวม
กำไรรวม
```

สูตร:

```text
ยอดขายรวม = SUM(selling_price)

ต้นทุนรวม = SUM(cost_price)

กำไรรวม = ยอดขายรวม - ต้นทุนรวม
```

---

# 38. Data Validation

ต้อง Validate ข้อมูลทุก API

ตัวอย่าง:

```text
quantity_kg > 0

price_per_kg >= 0

selling_price >= 0

received_amount >= total_amount
```

ชื่อวัตถุดิบและชื่ออาหารห้ามเป็นค่าว่าง

---

# 39. Money Calculation

ห้ามใช้ Floating Point แบบที่ทำให้เกิดปัญหาทศนิยมโดยตรงในการคำนวณเงิน

ควรใช้:

```text
DECIMAL
```

หรือเก็บเงินเป็นจำนวนเต็มหน่วยสตางค์ เช่น:

```text
60 บาท = 6000 satang
```

ถ้า SQLite ไม่สามารถบังคับ DECIMAL ได้อย่างสมบูรณ์ ให้ใช้ Integer สำหรับค่าที่เป็นเงินเพื่อป้องกัน Floating Point Error

ตัวอย่าง:

```text
60.50 บาท
=
6050 สตางค์
```

ส่วนปริมาณวัตถุดิบให้ใช้ REAL โดยกำหนด Precision ที่เหมาะสม เช่น 4-6 ตำแหน่ง

---

# 40. Security

ถึงแม้เป็นระบบ Local ให้เขียนโค้ดโดยคำนึงถึง Security

ต้อง:

* ใช้ PDO
* ใช้ Prepared Statements
* ป้องกัน SQL Injection
* Validate Input
* Escape Output
* ไม่เก็บ SQL แบบต่อ String จาก User Input
* แยก Configuration ออกจาก Business Logic

---

# 41. Logging

ทุก Service ควรมี Log

ตัวอย่าง:

```text
logs/
├── pos.log
├── inventory.log
└── revenue.log
```

บันทึกอย่างน้อย:

```text
timestamp
service
request
action
success/error
reference_id
error_message
```

ห้ามบันทึกข้อมูลสำคัญที่เป็นความลับลง Log

---

# 42. API Documentation

สร้างไฟล์:

```text
API.md
```

ระบุทุก Endpoint:

```text
Method
URL
Request
Response
Error
```

ตัวอย่าง:

```text
POST /api/inventory/purchase
```

Request:

```json
{
  "ingredient_id": 1,
  "quantity_kg": 10,
  "price_per_kg": 90
}
```

Response:

```json
{
  "success": true,
  "data": {
    "ingredient_id": 1,
    "quantity_kg": 20,
    "average_cost_per_kg": 85
  }
}
```

---

# 43. Seed Data

สร้างข้อมูลตัวอย่างเพื่อทดสอบระบบ

## Ingredients

```text
ไก่
หมู
ใบกะเพรา
พริก
กระเทียม
น้ำมัน
ข้าว
```

## Products

```text
ข้าวกะเพราไก่
ข้าวกะเพราหมู
ข้าวผัด
```

ให้สร้างสูตรอาหารตัวอย่างให้พร้อมใช้งาน

---

# 44. Testing

ต้องสร้าง Test สำหรับ Business Logic สำคัญ

อย่างน้อย:

## Test 1 — ราคาเฉลี่ย

```text
Stock เดิม = 10 kg
Average = 80

ซื้อเพิ่ม = 5 kg
ราคา = 100

Expected Average = 86.6667
```

## Test 2 — ซื้อครั้งแรก

```text
Stock = 0

ซื้อ 10 kg
ราคา 90

Expected Average = 90
```

## Test 3 — Cost Recipe

```text
ไก่ 0.1 kg
ราคาเฉลี่ย 80

Expected = 8 บาท
```

## Test 4 — Profit

```text
ราคาขาย = 60
ต้นทุน = 10

Expected Profit = 50
```

## Test 5 — Change

```text
Total = 180
Received = 200

Expected Change = 20
```

## Test 6 — Insufficient Money

```text
Total = 180
Received = 100

Expected:
ไม่อนุญาตให้ชำระเงิน
```

## Test 7 — Insufficient Stock

```text
Stock = 0.05 kg
Required = 0.10 kg

Expected:
INSUFFICIENT_STOCK
```

## Test 8 — Duplicate Order

หากส่ง Order เดิมซ้ำ:

```text
order_id = 1001
```

ต้องไม่ตัด Stock ซ้ำ

---

# 45. UX Requirements

UI ต้องเรียบง่ายและเหมาะกับร้านอาหาร

เน้น:

* ปุ่มขนาดใหญ่
* อ่านง่าย
* ใช้งานบน Tablet ได้
* POS ต้องกดสินค้าได้รวดเร็ว
* แสดงยอดรวมเด่นชัด
* แสดงเงินทอนเด่นชัด
* ใช้ภาษาไทยเป็นหลัก
* รองรับ Responsive Design

---

# 46. Error Handling

ไม่ควรแสดง PHP Error หรือ Stack Trace ให้ User เห็น

ตัวอย่างข้อความ:

```text
เกิดข้อผิดพลาด
ไม่สามารถบันทึกรายการได้
กรุณาลองใหม่อีกครั้ง
```

แต่ Log ภายในต้องเก็บรายละเอียด Error ไว้

---

# 47. Definition of Done

ถือว่า Project เสร็จเมื่อสามารถทำ Flow นี้ได้ตั้งแต่ต้นจนจบ:

```text
1. สร้างวัตถุดิบ
        ↓
2. ซื้อวัตถุดิบ
        ↓
3. ระบบเพิ่ม Stock
        ↓
4. ระบบคำนวณราคาเฉลี่ย
        ↓
5. สร้างอาหาร
        ↓
6. สร้างสูตรอาหาร
        ↓
7. ระบบคำนวณต้นทุนอาหาร
        ↓
8. ตั้งราคาขาย
        ↓
9. ไปหน้า POS
        ↓
10. เลือกอาหาร
        ↓
11. ระบบคำนวณยอดรวม
        ↓
12. รับเงิน
        ↓
13. คำนวณเงินทอน
        ↓
14. ตรวจสอบ Stock
        ↓
15. ตัด Stock ตามสูตร
        ↓
16. บันทึก Order
        ↓
17. บันทึกต้นทุน ณ เวลาขาย
        ↓
18. บันทึกรายได้
        ↓
19. คำนวณกำไร
        ↓
20. แสดงรายงานรายได้และกำไร
```

---

# 48. Important Development Rules

ให้ Antigravity ปฏิบัติตามกฎต่อไปนี้:

1. อย่าสร้างระบบทั้งหมดเป็น Monolith
2. แยก Microservices ตามความรับผิดชอบ
3. Service ห้ามเข้าถึง Database ของ Service อื่นโดยตรง
4. ให้ใช้ REST API สำหรับ Communication
5. Business Logic ต้องอยู่ใน Service Layer
6. Database Access ต้องอยู่ใน Repository Layer
7. Controller ไม่ควรมี Business Logic จำนวนมาก
8. ใช้ PDO + Prepared Statements
9. ใช้ Transaction สำหรับ Database Operation ที่เกี่ยวข้องกัน
10. ป้องกันการตัด Stock ซ้ำ
11. บันทึกต้นทุน ณ เวลาที่ขายเป็น Snapshot
12. ราคาเฉลี่ยวัตถุดิบต้องเป็น Weighted Average ตามจำนวน kg
13. ถ้าวัตถุดิบไม่เคยมี Stock เดิม ให้ใช้ราคาซื้อครั้งแรกเป็นราคาเฉลี่ย
14. ห้ามนำวัตถุดิบคนละชนิดมาเฉลี่ยกัน
15. ห้ามให้ Stock ติดลบ
16. ต้อง Validate ทุก Input
17. API ต้อง Return JSON
18. ใช้ HTTP Status Code ให้ถูกต้อง
19. เขียน README สำหรับวิธีติดตั้งและ Run
20. เขียน API Documentation
21. เขียน Database Migration / Initialization Script
22. เขียน Seed Data
23. เขียน Automated Tests สำหรับ Business Logic สำคัญ
24. Code ต้องอ่านง่ายและแยกความรับผิดชอบชัดเจน
25. อย่า Hard-code Business Logic ที่ควรอยู่ใน Database หรือ Configuration

---

# 49. สิ่งที่ต้องส่งมอบ

สร้าง Project ที่สามารถ Run ได้จริง โดยต้องมี:

```text
[ ] PHP Backend
[ ] SQLite Database
[ ] POS Service
[ ] Recipe Service
[ ] Inventory Service
[ ] Revenue Service
[ ] REST API
[ ] Frontend
[ ] Database Migration
[ ] Seed Data
[ ] API Documentation
[ ] README.md
[ ] Automated Tests
[ ] Error Handling
[ ] Logging
```

---

# 50. วิธี Run ที่ต้องการ

ระบบควรสามารถ Run แบบ Local ได้ง่าย เช่น:

```bash
php -S localhost:8000 -t frontend
```

และแต่ละ Microservice สามารถ Run แยก Port ได้ เช่น:

```text
POS Service
http://localhost:8001

Recipe/Inventory Service
http://localhost:8002

Revenue Service
http://localhost:8003
```

Frontend:

```text
http://localhost:8000
```

---

# 51. Final Requirement

ให้สร้างระบบจาก Specification นี้เป็น **ระบบที่ใช้งานได้จริง ไม่ใช่เพียง Mockup**

ต้องสามารถ:

* เพิ่มข้อมูลจริง
* แก้ไขข้อมูลจริง
* ลบข้อมูลจริง
* บันทึกลง SQLite
* เรียก REST API ระหว่าง Service
* คำนวณราคาเฉลี่ยจริง
* คำนวณต้นทุนจริง
* ตัด Stock จริง
* บันทึกการขายจริง
* คำนวณรายได้จริง
* คำนวณกำไรจริง
* แสดงข้อมูลผ่าน Web UI

ก่อนจบงานให้ทดสอบ End-to-End Flow ตั้งแต่:

```text
ซื้อวัตถุดิบ
→ สร้างสูตร
→ สร้างอาหาร
→ ขายอาหาร
→ ตัด Stock
→ บันทึกรายได้
→ คำนวณต้นทุน
→ คำนวณกำไร
```

และต้องแก้ไข Error ที่พบจากการทดสอบให้เรียบร้อยก่อนถือว่า Project เสร็จสมบูรณ์
