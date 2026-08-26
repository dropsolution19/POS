const API_CONFIG = {
    pos: 'http://127.0.0.1:8001',
    inventory: 'http://127.0.0.1:8002',
    revenue: 'http://127.0.0.1:8003'
};

// Global State
let posProducts = [];
let cart = [];
let allIngredients = [];

// DOM Loaded Initialization
document.addEventListener('DOMContentLoaded', () => {
    setupNavigation();
    loadPosProducts();
    loadProductsTable();
    loadIngredientsTable();
    loadTransactionsTable();
    loadRevenue('today');
});

// NAVIGATION LOGIC
function setupNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');

            const tabId = item.getAttribute('data-tab');
            document.querySelectorAll('.tab-section').forEach(sec => sec.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');

            // Refresh tab specific data
            if (tabId === 'tab-pos') loadPosProducts();
            if (tabId === 'tab-products') loadProductsTable();
            if (tabId === 'tab-inventory') { loadIngredientsTable(); loadTransactionsTable(); }
            if (tabId === 'tab-revenue') loadRevenue('today');
        });
    });
}

// ---------------------------------------------------------------------
// 1. POS TERMINAL MODULE
// ---------------------------------------------------------------------
async function loadPosProducts() {
    try {
        const res = await fetch(`${API_CONFIG.inventory}/api/products`);
        const json = await res.json();
        if (json.success) {
            posProducts = json.data;
            renderPosProductsGrid();
        }
    } catch (e) {
        console.error("Error loading products:", e);
    }
}

function renderPosProductsGrid() {
    const grid = document.getElementById('pos-products-grid');
    grid.innerHTML = '';

    if (posProducts.length === 0) {
        grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 3rem;">ยังไม่มีรายการอาหาร กรุณาเพิ่มอาหารในเมนู "อาหาร & สูตร"</div>`;
        return;
    }

    posProducts.forEach(prod => {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.onclick = () => addToCart(prod);
        card.innerHTML = `
            <div>
                <div class="product-card-title">${prod.name}</div>
                <div class="product-card-cost">ต้นทุน: ฿${(prod.cost_price || 0).toFixed(2)}</div>
            </div>
            <div class="product-card-price">฿${parseFloat(prod.selling_price).toFixed(2)}</div>
        `;
        grid.appendChild(card);
    });
}

function addToCart(product) {
    const existing = cart.find(item => item.product_id === product.id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({
            product_id: product.id,
            name: product.name,
            selling_price: parseFloat(product.selling_price),
            quantity: 1
        });
    }
    renderCart();
}

function updateCartQuantity(productId, delta) {
    const item = cart.find(i => i.product_id === productId);
    if (item) {
        item.quantity += delta;
        if (item.quantity <= 0) {
            cart = cart.filter(i => i.product_id !== productId);
        }
    }
    renderCart();
}

function clearCart() {
    cart = [];
    document.getElementById('received-amount-input').value = '';
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cart-items-container');
    container.innerHTML = '';

    let total = 0;
    cart.forEach(item => {
        const itemTotal = item.selling_price * item.quantity;
        total += itemTotal;

        const el = document.createElement('div');
        el.className = 'cart-item';
        el.innerHTML = `
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-price">฿${item.selling_price.toFixed(2)} x ${item.quantity} = ฿${itemTotal.toFixed(2)}</div>
            </div>
            <div class="cart-item-controls">
                <button class="btn-qty" onclick="updateCartQuantity(${item.product_id}, -1)">-</button>
                <span style="font-weight: bold; width: 20px; text-align: center;">${item.quantity}</span>
                <button class="btn-qty" onclick="updateCartQuantity(${item.product_id}, 1)">+</button>
            </div>
        `;
        container.appendChild(el);
    });

    document.getElementById('cart-total-display').innerText = `${total.toFixed(2)} บาท`;
    calculateChange();
}

function calculateChange() {
    const total = cart.reduce((sum, item) => sum + (item.selling_price * item.quantity), 0);
    const received = parseFloat(document.getElementById('received-amount-input').value) || 0;
    const change = received - total;

    const changeEl = document.getElementById('cart-change-display');
    if (received === 0) {
        changeEl.innerText = '0.00 บาท';
        changeEl.style.color = 'var(--text-secondary)';
    } else if (change < 0) {
        changeEl.innerText = `เงินไม่เพียงพอ (ขาด ${(Math.abs(change)).toFixed(2)} บาท)`;
        changeEl.style.color = 'var(--accent-danger)';
    } else {
        changeEl.innerText = `${change.toFixed(2)} บาท`;
        changeEl.style.color = 'var(--accent-success)';
    }
}

function setQuickCash(amount) {
    const total = cart.reduce((sum, item) => sum + (item.selling_price * item.quantity), 0);
    if (amount === 'exact') {
        document.getElementById('received-amount-input').value = total;
    } else {
        document.getElementById('received-amount-input').value = amount;
    }
    calculateChange();
}

async function submitCheckout() {
    if (cart.length === 0) {
        alert("กรุณาเลือกสินค้าอย่างน้อย 1 รายการ");
        return;
    }

    const received = parseFloat(document.getElementById('received-amount-input').value) || 0;
    const total = cart.reduce((sum, item) => sum + (item.selling_price * item.quantity), 0);

    if (received < total) {
        alert("เงินที่รับมาไม่เพียงพอกับยอดรวมสินค้า");
        return;
    }

    const payload = {
        items: cart.map(i => ({ product_id: i.product_id, quantity: i.quantity })),
        received_amount: received
    };

    try {
        const res = await fetch(`${API_CONFIG.pos}/api/orders`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const json = await res.json();

        if (json.success) {
            const data = json.data;
            document.getElementById('receipt-order-number').innerText = `บิลเลขที่: ${data.order_number}`;
            document.getElementById('receipt-total').innerText = `${parseFloat(data.total_amount).toFixed(2)} บาท`;
            document.getElementById('receipt-received').innerText = `${parseFloat(data.received_amount).toFixed(2)} บาท`;
            document.getElementById('receipt-change').innerText = `${parseFloat(data.change_amount).toFixed(2)} บาท`;

            openModal('modal-receipt');
            clearCart();
            loadPosProducts();
        } else {
            let errorMsg = json.error ? json.error.message : 'เกิดข้อผิดพลาดในการสั่งซื้อ';
            if (json.error && json.error.details && Array.isArray(json.error.details)) {
                errorMsg += "\n\nรายละเอียดวัตถุดิบไม่พอ:\n" + json.error.details.map(d => `- ${d.name}: ต้องการ ${d.required} kg (มี ${d.available} kg)`).join('\n');
            }
            alert(`[ข้อผิดพลาด] ${errorMsg}`);
        }
    } catch (e) {
        console.error("Checkout Error:", e);
        alert("ไม่สามารถติดต่อ POS Service ได้");
    }
}


// ---------------------------------------------------------------------
// 2. PRODUCTS & RECIPES MODULE
// ---------------------------------------------------------------------
async function loadProductsTable() {
    try {
        const res = await fetch(`${API_CONFIG.inventory}/api/products`);
        const json = await res.json();
        if (json.success) {
            const tbody = document.getElementById('products-table-body');
            tbody.innerHTML = '';

            json.data.forEach(p => {
                const tr = document.createElement('tr');

                let recipeText = '<span style="color: var(--text-muted);">ยังไม่มีสูตร</span>';
                if (p.recipe && p.recipe.items && p.recipe.items.length > 0) {
                    recipeText = p.recipe.items.map(i => `${i.ingredient_name} (${i.quantity_kg} kg)`).join(', ');
                }

                tr.innerHTML = `
                    <td>#${p.id}</td>
                    <td><strong>${p.name}</strong></td>
                    <td>฿${parseFloat(p.selling_price).toFixed(2)}</td>
                    <td style="color: var(--accent-warning);">฿${parseFloat(p.cost_price).toFixed(2)}</td>
                    <td style="color: var(--accent-success); font-weight: bold;">฿${parseFloat(p.estimated_profit).toFixed(2)}</td>
                    <td style="font-size: 0.9rem;">${recipeText}</td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick="openRecipeModal(${p.id})"><i class="fa-solid fa-scroll"></i> จัดการสูตร</button>
                        <button class="btn btn-secondary btn-sm" onclick="openProductModal(${p.id})"><i class="fa-solid fa-pen"></i> แก้ไข</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteProduct(${p.id})"><i class="fa-solid fa-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) {
        console.error("Error loading products table:", e);
    }
}

function openProductModal(id = null) {
    document.getElementById('form-product').reset();
    document.getElementById('prod-id').value = id || '';
    document.getElementById('product-modal-title').innerText = id ? 'แก้ไขรายการอาหาร' : 'เพิ่มรายการอาหารใหม่';

    if (id) {
        fetch(`${API_CONFIG.inventory}/api/products/${id}`)
            .then(res => res.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('prod-name').value = json.data.name;
                    document.getElementById('prod-price').value = json.data.selling_price;
                }
            });
    }

    openModal('modal-product');
}

async function saveProduct(e) {
    e.preventDefault();
    const id = document.getElementById('prod-id').value;
    const name = document.getElementById('prod-name').value;
    const price = parseFloat(document.getElementById('prod-price').value);

    const url = id ? `${API_CONFIG.inventory}/api/products/${id}` : `${API_CONFIG.inventory}/api/products`;
    const method = id ? 'PUT' : 'POST';

    try {
        const res = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name, selling_price: price })
        });
        const json = await res.json();
        if (json.success) {
            closeModal('modal-product');
            loadProductsTable();
        } else {
            alert(json.error.message);
        }
    } catch (err) {
        alert("ไม่สามารถบันทึกข้อมูลได้");
    }
}

async function deleteProduct(id) {
    if (!confirm("คุณต้องการลบรายการอาหารนี้ใช่หรือไม่?")) return;
    try {
        const res = await fetch(`${API_CONFIG.inventory}/api/products/${id}`, { method: 'DELETE' });
        const json = await res.json();
        if (json.success) {
            loadProductsTable();
        }
    } catch (e) {
        alert("ไม่สามารถลบข้อมูลได้");
    }
}

// RECIPE MODAL LOGIC
async function openRecipeModal(productId) {
    document.getElementById('recipe-prod-id').value = productId;
    document.getElementById('recipe-ingredients-list').innerHTML = '';

    // Load Ingredients dropdown list
    const resIng = await fetch(`${API_CONFIG.inventory}/api/ingredients`);
    const jsonIng = await resIng.json();
    allIngredients = jsonIng.data || [];

    // Load Current Product Recipe
    const resRec = await fetch(`${API_CONFIG.inventory}/api/recipes/product/${productId}`);
    const jsonRec = await resRec.json();

    const recipe = jsonRec.data.recipe;
    if (recipe && recipe.items && recipe.items.length > 0) {
        recipe.items.forEach(item => {
            addRecipeIngredientRow(item.ingredient_id, item.quantity_kg);
        });
    } else {
        addRecipeIngredientRow(); // Add 1 empty row
    }

    calculateRecipeCostPreview();
    openModal('modal-recipe');
}

function addRecipeIngredientRow(selectedIngId = '', quantity = '') {
    const container = document.getElementById('recipe-ingredients-list');
    const row = document.createElement('div');
    row.className = 'recipe-row';
    row.style.display = 'flex';
    row.style.gap = '0.5rem';
    row.style.alignItems = 'center';

    let optionsHtml = '<option value="">-- เลือกวัตถุดิบ --</option>';
    allIngredients.forEach(ing => {
        const sel = ing.id == selectedIngId ? 'selected' : '';
        optionsHtml += `<option value="${ing.id}" data-cost="${ing.average_cost_per_kg}" ${sel}>${ing.name} (เฉลี่ย ฿${parseFloat(ing.average_cost_per_kg).toFixed(2)}/kg)</option>`;
    });

    row.innerHTML = `
        <select class="form-control recipe-ing-select" style="flex: 2;" onchange="calculateRecipeCostPreview()" required>
            ${optionsHtml}
        </select>
        <input type="number" class="form-control recipe-qty-input" style="flex: 1;" placeholder="ปริมาณ (kg)" step="0.001" value="${quantity}" oninput="calculateRecipeCostPreview()" required>
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove(); calculateRecipeCostPreview();">&times;</button>
    `;

    container.appendChild(row);
}

function calculateRecipeCostPreview() {
    let totalCost = 0;
    document.querySelectorAll('.recipe-row').forEach(row => {
        const select = row.querySelector('.recipe-ing-select');
        const qtyInput = row.querySelector('.recipe-qty-input');

        if (select && qtyInput && select.value && qtyInput.value) {
            const selectedOption = select.options[select.selectedIndex];
            const costPerKg = parseFloat(selectedOption.getAttribute('data-cost')) || 0;
            const qty = parseFloat(qtyInput.value) || 0;
            totalCost += (costPerKg * qty);
        }
    });

    document.getElementById('recipe-calculated-cost').innerText = totalCost.toFixed(2);
}

async function saveRecipe(e) {
    e.preventDefault();
    const productId = document.getElementById('recipe-prod-id').value;
    const ingredients = [];

    document.querySelectorAll('.recipe-row').forEach(row => {
        const ingId = row.querySelector('.recipe-ing-select').value;
        const qty = parseFloat(row.querySelector('.recipe-qty-input').value);
        if (ingId && qty > 0) {
            ingredients.push({ ingredient_id: parseInt(ingId), quantity_kg: qty });
        }
    });

    try {
        const res = await fetch(`${API_CONFIG.inventory}/api/recipes`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: parseInt(productId), ingredients: ingredients })
        });
        const json = await res.json();
        if (json.success) {
            closeModal('modal-recipe');
            loadProductsTable();
        } else {
            alert(json.error.message);
        }
    } catch (err) {
        alert("ไม่สามารถบันทึกสูตรได้");
    }
}


// ---------------------------------------------------------------------
// 3. INGREDIENTS & INVENTORY MODULE
// ---------------------------------------------------------------------
async function loadIngredientsTable() {
    try {
        const res = await fetch(`${API_CONFIG.inventory}/api/ingredients`);
        const json = await res.json();
        if (json.success) {
            allIngredients = json.data;
            const tbody = document.getElementById('ingredients-table-body');
            tbody.innerHTML = '';

            json.data.forEach(ing => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>#${ing.id}</td>
                    <td><strong>${ing.name}</strong></td>
                    <td style="font-weight: bold; color: ${ing.current_stock > 0 ? 'var(--accent-success)' : 'var(--accent-danger)'};">${parseFloat(ing.current_stock).toFixed(3)} ${ing.unit}</td>
                    <td>฿${parseFloat(ing.average_cost_per_kg).toFixed(2)} / ${ing.unit}</td>
                    <td>${ing.unit}</td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) {
        console.error("Error loading ingredients:", e);
    }
}

async function loadTransactionsTable() {
    try {
        const res = await fetch(`${API_CONFIG.inventory}/api/transactions`);
        const json = await res.json();
        if (json.success) {
            const tbody = document.getElementById('transactions-table-body');
            tbody.innerHTML = '';

            json.data.forEach(tx => {
                const tr = document.createElement('tr');

                let badge = `<span class="badge badge-success">${tx.transaction_type}</span>`;
                if (tx.transaction_type === 'USAGE') badge = `<span class="badge badge-danger">USAGE</span>`;

                tr.innerHTML = `
                    <td style="font-size: 0.85rem; color: var(--text-muted);">${tx.created_at}</td>
                    <td><strong>${tx.ingredient_name}</strong></td>
                    <td>${badge}</td>
                    <td style="font-weight: bold; color: ${tx.quantity_kg > 0 ? 'var(--accent-success)' : 'var(--accent-danger)'}">${tx.quantity_kg > 0 ? '+' : ''}${parseFloat(tx.quantity_kg).toFixed(3)} kg</td>
                    <td>฿${parseFloat(tx.unit_cost).toFixed(2)}</td>
                    <td style="font-size: 0.85rem;">${tx.reference_type || '-'} (${tx.reference_id || '-'})</td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) {
        console.error("Error loading transactions:", e);
    }
}

function openIngredientModal() {
    document.getElementById('form-ingredient').reset();
    openModal('modal-ingredient');
}

async function saveIngredient(e) {
    e.preventDefault();
    const name = document.getElementById('ing-name').value;
    const unit = document.getElementById('ing-unit').value;

    try {
        const res = await fetch(`${API_CONFIG.inventory}/api/ingredients`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name, unit: unit })
        });
        const json = await res.json();
        if (json.success) {
            closeModal('modal-ingredient');
            loadIngredientsTable();
        } else {
            alert(json.error.message);
        }
    } catch (err) {
        alert("ไม่สามารถสร้างวัตถุดิบได้");
    }
}

function openPurchaseModal() {
    const select = document.getElementById('purchase-ing-id');
    select.innerHTML = '';

    if (allIngredients.length === 0) {
        alert("กรุณาเพิ่มรายการวัตถุดิบก่อนบันทึกการซื้อ");
        return;
    }

    allIngredients.forEach(ing => {
        select.innerHTML += `<option value="${ing.id}">${ing.name} (สต๊อกปัจจุบัน: ${parseFloat(ing.current_stock).toFixed(2)} ${ing.unit})</option>`;
    });

    document.getElementById('form-purchase').reset();
    openModal('modal-purchase');
}

async function savePurchase(e) {
    e.preventDefault();
    const ingId = parseInt(document.getElementById('purchase-ing-id').value);
    const qty = parseFloat(document.getElementById('purchase-qty').value);
    const price = parseFloat(document.getElementById('purchase-price').value);

    try {
        const res = await fetch(`${API_CONFIG.inventory}/api/ingredients/purchase`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ingredient_id: ingId,
                quantity_kg: qty,
                price_per_kg: price
            })
        });

        const json = await res.json();
        if (json.success) {
            closeModal('modal-purchase');
            loadIngredientsTable();
            loadTransactionsTable();
            alert(`[บันทึกสำเร็จ] เพิ่มสต๊อก ${json.data.added_stock} kg\nราคาเฉลี่ยใหม่ = ฿${json.data.average_cost_per_kg}/kg`);
        } else {
            alert(json.error.message);
        }
    } catch (err) {
        alert("ไม่สามารถบันทึกการซื้อได้");
    }
}


// ---------------------------------------------------------------------
// 4. REVENUE & REPORTING MODULE
// ---------------------------------------------------------------------
async function loadRevenue(period = 'today', btnElement = null) {
    if (btnElement) {
        document.querySelectorAll('.btn-period').forEach(b => b.classList.remove('active'));
        btnElement.classList.add('active');
    }

    try {
        // Summary
        const resSum = await fetch(`${API_CONFIG.revenue}/api/revenue/summary?period=${period}`);
        const jsonSum = await resSum.json();

        if (jsonSum.success) {
            const data = jsonSum.data;
            document.getElementById('stat-total-revenue').innerText = `฿ ${data.total_revenue.toLocaleString('th-TH', {minimumFractionDigits: 2})}`;
            document.getElementById('stat-total-cost').innerText = `฿ ${data.total_cost.toLocaleString('th-TH', {minimumFractionDigits: 2})}`;
            document.getElementById('stat-total-profit').innerText = `฿ ${data.total_profit.toLocaleString('th-TH', {minimumFractionDigits: 2})}`;
            document.getElementById('stat-total-orders').innerText = `${data.total_orders} บิล (${data.total_items_sold} รายการ)`;
        }

        // Reports Table
        let reportUrl = `${API_CONFIG.revenue}/api/revenue/reports`;
        if (jsonSum.data && jsonSum.data.start_date && jsonSum.data.end_date) {
            reportUrl += `?start_date=${jsonSum.data.start_date}&end_date=${jsonSum.data.end_date}`;
        }

        const resRep = await fetch(reportUrl);
        const jsonRep = await resRep.json();

        if (jsonRep.success) {
            const tbody = document.getElementById('revenue-records-table-body');
            tbody.innerHTML = '';

            jsonRep.data.forEach(rec => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-size: 0.85rem; color: var(--text-muted);">${rec.created_at}</td>
                    <td><code>${rec.order_number}</code></td>
                    <td><strong>${rec.product_name}</strong></td>
                    <td>${rec.quantity}</td>
                    <td>฿${parseFloat(rec.selling_price).toFixed(2)}</td>
                    <td style="color: var(--accent-warning);">฿${parseFloat(rec.cost_price).toFixed(2)}</td>
                    <td style="color: var(--accent-success); font-weight: bold;">฿${parseFloat(rec.profit).toFixed(2)}</td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) {
        console.error("Error loading revenue report:", e);
    }
}

// UTILITY MODAL HELPERS
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}
