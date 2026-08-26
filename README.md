# POS Restaurant Microservices System

ระบบบริหารจัดการร้านอาหาร Point of Sale (POS) แบบ **Microservices Architecture** ด้วย **PHP 8.2+** และ **SQLite** พร้อม Web UI (Vanilla HTML5 / CSS3 / JavaScript)

---

## 🏗 Architecture & Services

```text
POS System
├── POS Service (Port 8001) ─── pos.sqlite
│   ├── สั่งซื้อสินค้า & ตรวจสอบการชำระเงิน
│   ├── สื่อสารกับ Inventory & Revenue Service ผ่าน REST API
│   └── บันทึกรายการสั่งซื้อ
│
├── Recipe & Inventory Service (Port 8002) ─── inventory.sqlite
│   ├── จัดการรายการอาหาร & สูตรอาหาร (Recipe)
│   ├── ซื้อวัตถุดิบ & คำนวณราคาเฉลี่ยวัตถุดิบ (Weighted Average Cost)
│   └── ตรวจสอบสต๊อก & ตัดสต๊อกตามสูตรอาหาร (ป้องกัน Stock ติดลบ)
│
├── Revenue Service (Port 8003) ─── revenue.sqlite
│   ├── บันทึก Snapshot ต้นทุน ณ เวลาขาย
│   └── สรุปยอดขาย ต้นทุน รายได้ และกำไรสุทธิ
│
└── Frontend Web Application (Port 8000)
    └── หน้าจอ POS, จัดการสูตร, คลังวัตถุดิบ, และรายงานรายได้
```

---

## ⚡ คุณสมบัติเด่น (Features)

1. **Weighted Average Cost Calculation**:
   - เมื่อซื้อวัตถุดิบใหม่เข้าสต๊อก ระบบจะคำนวณราคาเฉลี่ยแบบถ่วงน้ำหนักโดยอัตโนมัติ
   - Formula: `(Stockเดิม × ราคาเฉลี่ยเดิม + Qtyใหม่ × ราคาซื้อใหม่) / (Stockเดิม + Qtyใหม่)`
2. **Cost Snapshot at Sale Time**:
   - บันทึกต้นทุนสินค้า ณ เวลาที่ขายลงใน Revenue Service ป้องกันปัญหารายได้ย้อนหลังเปลี่ยนเมื่อราคาวัตถุดิบปรับตัว
3. **Stock Protection & Idempotency**:
   - ตรวจสอบความเพียงพอของวัตถุดิบก่อนปิดการขาย (ป้องกันสต๊อกติดลบ)
   - มี Idempotency Check ป้องกันการตัดสต๊อกซ้ำจาก Order ID เดิม
4. **Interactive Thai Web UI**:
   - หน้าขาย POS พร้อมปุ่มคีย์ลัดรับเงิน/เงินทอน
   - ระบบจัดการสูตรอาหาร (ระบุปริมาณวัตถุดิบเป็น kg) พร้อมคำนวณต้นทุนให้เห็นทันที
   - รายงานสรุป ยอดขาย, ต้นทุน, กำไรสุทธิ พร้อมตัวกรอง (วันนี้, เมื่อวาน, 7 วัน, เดือนนี้)

---

## 🚀 วิธีการติดตั้งและเริ่มต้นใช้งาน (Installation & Quick Start)

### 1. ความต้องการของระบบ (Prerequisites)
- PHP 8.2+ (พร้อม Extension `php-sqlite3` และ `php-curl`)

```bash
sudo apt-get update
sudo apt-get install -y php-cli php-sqlite3 php-curl
```

### 2. รันระบบทั้งหมดด้วยคำสั่งเดียว (Single Launcher)
รันสคริปต์ `run.sh` เพื่อ Migrate ฐานข้อมูล, Seed ข้อมูลตัวอย่าง, รัน Unit Tests, และเปิด Server ทั้งหมด:

```bash
./run.sh
```

เมื่อระบบเปิดทำงานแล้ว สามารถเข้าใช้งานผ่าน Browser:
👉 **http://localhost:8000**

---

## 🧪 การรันชุดทดสอบอัตโนมัติ (Automated Unit Tests)

รันคำสั่งทดสอบ Business Logic สำคัญทั้ง 8 ข้อตามข้อกำหนด:

```bash
php tests/run_tests.php
```

---

## 📂 โครงสร้างโฟลเดอร์ (Directory Structure)

```text
POS/
├── services/
│   ├── pos/                    # POS Service (Port 8001)
│   ├── recipe-inventory/       # Recipe & Inventory Service (Port 8002)
│   └── revenue/                # Revenue Service (Port 8003)
├── frontend/                   # HTML/CSS/JS User Interface (Port 8000)
├── shared/                     # Shared Database, HTTP Client & Logger
├── scripts/                    # Migration & Seed Script (seed.php)
├── tests/                      # Automated Business Logic Tests (run_tests.php)
├── API.md                      # Documentation รายละเอียด REST API Endpoints
├── README.md                   # เอกสารคู่มือระบบ
└── run.sh                      # Launcher Script
```
