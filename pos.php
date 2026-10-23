<?php
session_start();
require_once 'config/db.php';

// Handle Cashier Checkout Submission
$success_msg = ''; $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cashier_checkout'])) {
    try {
        global $pdo;
        $customer_name  = trim($_POST['customer_name'] ?? 'Walk-in Customer');
        $phone          = trim($_POST['phone'] ?? '09000000000');
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $cash_tendered  = floatval($_POST['cash_tendered'] ?? 0);
        $cart_json      = $_POST['cart_json'] ?? '[]';
        $cart_items     = json_decode($cart_json, true);

        if (empty($cart_items)) {
            $error_msg = 'Cart is empty. Please select menu items.';
        } else {
            $total_amount = 0;
            foreach ($cart_items as $item) {
                $total_amount += (float)$item['price'] * (int)$item['qty'];
            }

            if ($payment_method === 'Cash' && $cash_tendered < $total_amount) {
                throw new Exception('Cash tendered is less than the total amount due.');
            }

            $pdo->beginTransaction();

            // Insert Order (Marked as paid and preparing for immediate kitchen dispatch)
            $stmt = $pdo->prepare("INSERT INTO orders (customer_name, phone, delivery_area, address, total_amount, payment_method, payment_status, order_status) VALUES (?, ?, 'Counter Dine-in / Takeout', 'Cashier POS Terminal', ?, ?, 'paid', 'preparing')");
            $stmt->execute([$customer_name, $phone, $total_amount, $payment_method]);
            $order_id = $pdo->lastInsertId();

            // Insert Order Items
            $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, item_name, egg_choice, notes, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($cart_items as $item) {
                $item_stmt->execute([
                    $order_id,
                    $item['name'],
                    $item['egg'] ?? 'Sunny Side Up',
                    $item['notes'] ?? '',
                    (int)$item['qty'],
                    (float)$item['price']
                ]);
            }

            $pdo->commit();
            $success_msg = "Transaction Complete! Order #$order_id successfully placed.";
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $error_msg = 'Checkout Error: ' . $e->getMessage();
    }
}

// Handle Marking Kiosk Order as Complete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_kiosk_order_id'])) {
    try {
        global $pdo;
        $k_order_id = intval($_POST['complete_kiosk_order_id']);
        $update_stmt = $pdo->prepare("UPDATE kiosk_database.kiosk_orders SET payment_status = 'completed' WHERE id = ?");
        $update_stmt->execute([$k_order_id]);
        header("Location: pos.php");
        exit;
    } catch (Exception $e) {
        $error_msg = 'Error updating kiosk order: ' . $e->getMessage();
    }
}

// Fetch Menu Items from Database
global $pdo;
$menu_categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM menu WHERE is_available = 1 ORDER BY category, item_name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $menu_categories[$row['category']][] = $row;
    }
} catch (Exception $e) {
    // Fallback
}

// Fetch Active Live Kiosk Orders from Standalone Kiosk Database
$kiosk_orders = [];
try {
    $kiosk_stmt = $pdo->query("SELECT * FROM kiosk_database.kiosk_orders WHERE payment_status = 'paid' ORDER BY id DESC LIMIT 15");
    while ($k_row = $kiosk_stmt->fetch(PDO::FETCH_ASSOC)) {
        $k_item_stmt = $pdo->prepare("SELECT * FROM kiosk_database.kiosk_order_items WHERE order_id = ?");
        $k_item_stmt->execute([$k_row['id']]);
        $k_row['items'] = $k_item_stmt->fetchAll(PDO::FETCH_ASSOC);
        $kiosk_orders[] = $k_row;
    }
} catch (Exception $e) {
    // Kiosk DB table might not exist yet
}

// Map database item names to their respective image filenames in the kiosk/image/ folder
$pos_images = [
    'Tapsilog' => 'tapsilog.png',
    'Bagnetsilog' => 'bagnetsilog.png',
    'Sisigsilog' => 'sisigsilog.png',
    'Liemposilog' => 'liemposilog.png',
    'Malingsilog' => 'malingsilog.png',
    'Tocilog' => 'tocilog.png',
    'Hungariansilog' => 'hungariansilog.png',
    'Hotdogsilog' => 'hotsilog.png',
    'Bagnet + Hungarian Combo' => 'bagnet_hungaria.png',
    'Tapa + Hungarian Combo' => 'tapa_hungarian.png',
    'Tapa + Bagnet Silog Combo' => 'tapa_bagnetsilog.png',
    'Liempo + Hungarian Combo' => 'liempo_hungaria.png',
    'Sisig + Hungarian Combo' => 'sisig_hungarian.png',
    'Special Tapa Portion' => 'special_tapa.png',
    'Special Bagnet Portion' => 'special_bagnet.png',
    'Special Sisig Portion' => 'special_sisig.png',
    'Hungarian Portion' => 'hungarian.png',
    'Garlic Rice' => 'garlic_rice.png',
    'Plain Rice' => 'plain_rice.png',
    'Extra Egg' => 'egg.png'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier POS Terminal - Sairene TephTeph Tapsilogan</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            color: #121212;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 15px;
        }

        .page-outer-frame {
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            max-width: 1350px;
            margin: 0 auto;
        }

        .cashier-header {
            background: linear-gradient(135deg, #212529 0%, #343a40 100%);
            color: #ffc107;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 15px 25px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-btn {
            border: 1px solid #ffc107;
            background-color: #fffdf0;
            color: #121212;
            font-weight: bold;
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 50px;
            transition: all 0.2s ease;
        }

        .category-btn.active, .category-btn:hover {
            background-color: #ffc107;
            color: #121212;
        }

        .menu-pos-card {
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .menu-pos-card:hover {
            border-color: #ffc107;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.25);
            transform: translateY(-2px);
        }

        .pos-cart-panel {
            background: #fffdf5;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .cart-list-box {
            max-height: 320px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            background-color: #ffffff;
        }

        .qty-btn {
            background: #ffc107;
            border: 1px solid #ffc107;
            color: #121212;
            width: 28px;
            height: 28px;
            border-radius: 5px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .kiosk-order-card {
            background: #fff8e1;
            border: 1.5px solid #ffc107;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .bottom-full-line {
            border: 1px solid #ffc107;
            background-color: #ffffff;
            height: 36px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555555;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <div class="page-outer-frame">

        <!-- Cashier Header -->
        <div class="cashier-header">
            <div>
                <h3 class="fw-bold mb-0"><i class="fa-solid fa-cash-register me-2"></i>Cashier POS Terminal</h3>
                <small class="text-light">Sairene TephTeph Tapsilogan • Counter Operations & Kiosk Sync</small>
            </div>
            <div>
                <a href="Home_Page.php" class="btn btn-outline-warning btn-sm fw-bold"><i class="fa-solid fa-arrow-left me-1"></i> Back to Home</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $error_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- KIOSK LIVE ORDERS BANNER / ACCORDION -->
        <div class="mb-4">
            <div class="card border-warning shadow-sm">
                <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#kioskOrdersCollapse" role="button" aria-expanded="true" style="cursor: pointer;">
                    <span><i class="fa-solid fa-tablet-screen-button me-2"></i> Live Tablet Kiosk Orders (<?= count($kiosk_orders) ?>)</span>
                    <small><i class="fa-solid fa-chevron-down"></i> Click to toggle</small>
                </div>
                <div class="collapse show" id="kioskOrdersCollapse">
                    <div class="card-body bg-light" style="max-height: 280px; overflow-y: auto;">
                        <?php if (empty($kiosk_orders)): ?>
                            <p class="text-muted text-center small mb-0 py-3">No active orders placed from the kiosk.</p>
                        <?php else: ?>
                            <div class="row g-2">
                                <?php foreach ($kiosk_orders as $k_order): ?>
                                    <div class="col-md-4">
                                        <div class="kiosk-order-card">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="badge bg-dark text-warning fw-bold"><?= htmlspecialchars($k_order['queue_number']) ?></span>
                                                <span class="small text-muted"><?= htmlspecialchars($k_order['dining_style']) ?></span>
                                            </div>
                                            <div class="fw-bold small text-dark"><?= htmlspecialchars($k_order['customer_name']) ?></div>
                                            <?php if (!empty($k_order['table_tag'])): ?>
                                                <div class="small text-danger fw-bold"><?= htmlspecialchars($k_order['table_tag']) ?></div>
                                            <?php endif; ?>
                                            <div class="border-top border-warning my-1 pt-1 small">
                                                <?php foreach ($k_order['items'] as $k_item): ?>
                                                    <div>• <?= $k_item['quantity'] ?>x <?= htmlspecialchars($k_item['product_name']) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                                                <span class="small text-muted"><?= htmlspecialchars($k_order['neighborhood_badge']) ?></span>
                                                <span class="fw-bold text-dark">₱<?= number_format($k_order['total_amount'], 2) ?></span>
                                            </div>
                                            <!-- Mark as Complete Button -->
                                            <form method="POST" class="mt-2 pt-2 border-top">
                                                <input type="hidden" name="complete_kiosk_order_id" value="<?= $k_order['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm w-100 fw-bold py-1 fs-8 text-white">
                                                    <i class="fa-solid fa-check me-1"></i> Mark as Complete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            
            <!-- LEFT SIDE: Menu Catalog Grid -->
            <div class="col-lg-7">
                <div class="bg-white border rounded-3 p-3 h-100 shadow-sm">
                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                        <i class="fa-solid fa-utensils text-warning me-2"></i>Menu Catalog
                    </h5>

                    <!-- Category Tabs -->
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="category-btn active" onclick="filterCat('all', this)">All Items</button>
                        <button type="button" class="category-btn" onclick="filterCat('silog', this)">Silog</button>
                        <button type="button" class="category-btn" onclick="filterCat('combo', this)">Combo</button>
                        <button type="button" class="category-btn" onclick="filterCat('alacarte', this)">Ala Carte</button>
                        <button type="button" class="category-btn" onclick="filterCat('foodpack', this)">Food Pack</button>
                        <button type="button" class="category-btn" onclick="filterCat('drinks', this)">Drinks</button>
                    </div>

                    <!-- Menu Cards Grid -->
                    <div class="row g-2 overflow-y-auto" style="max-height: 480px;" id="posMenuGrid">
                        <?php if (!empty($menu_categories)): ?>
                            <?php foreach ($menu_categories as $cat => $items): ?>
                                <?php foreach ($items as $item): ?>
                                    <?php 
                                        $img_file = $pos_images[$item['item_name']] ?? '';
                                        $img_src = $img_file ? 'images/' . $img_file : '';
                                    ?>
                                    <div class="col-md-4 menu-item-box" data-category="<?= htmlspecialchars($cat) ?>">
                                        <div class="menu-pos-card" onclick="addPosItem('<?= htmlspecialchars(addslashes($item['item_name'])) ?>', <?= (float)$item['price'] ?>)">
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($img_src): ?>
                                                    <div style="width: 48px; height: 48px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: #fffaf0; display: grid; place-items: center;">
                                                        <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.style.display='none';">
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-1 fs-6"><?= htmlspecialchars($item['item_name']) ?></h6>
                                                    <span class="badge bg-warning text-dark fs-9 text-uppercase"><?= htmlspecialchars($cat) ?></span>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                                <span class="text-dark fw-bold fs-6">₱<?= number_format($item['price'], 2) ?></span>
                                                <span class="btn btn-dark btn-sm py-0 px-2 fw-bold text-warning fs-8">+ Tap</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted small">No menu items found in database.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDE: Cart & Payment Calculator -->
            <div class="col-lg-5">
                <div class="pos-cart-panel shadow-sm">
                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom border-warning">
                        <i class="fa-solid fa-cart-shopping text-warning me-2"></i>Current Transaction
                    </h5>

                    <!-- Cart Items List -->
                    <div id="posCartList" class="cart-list-box mb-3">
                        <p class="text-muted text-center small my-5" id="emptyCartText">No items added yet. Tap menu items on the left.</p>
                    </div>

                    <!-- Calculation Totals -->
                    <div class="bg-white p-3 rounded-2 border mb-3">
                        <div class="d-flex justify-content-between text-dark fw-bold fs-5 mb-1">
                            <span>Total Amount Due:</span>
                            <span class="text-warning text-dark" id="posGrandTotal">₱0.00</span>
                        </div>
                    </div>

                    <!-- Checkout Form -->
                    <form method="POST" onsubmit="return validateCashierCheckout(event)">
                        <input type="hidden" name="cart_json" id="posCartJson">

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-dark mb-1">Customer / Table #</label>
                                <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="e.g. Table 3" value="Walk-in" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-dark mb-1">Payment Method</label>
                                <select name="payment_method" id="paymentMethod" class="form-select form-select-sm" onchange="toggleCashInput()">
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                </select>
                            </div>
                        </div>

                        <!-- Cash Tendered & Change Calculator -->
                        <div id="cashCalculatorBox" class="row g-2 mb-3 bg-light p-2 rounded border">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-dark mb-1">Cash Tendered (₱)</label>
                                <input type="number" step="0.01" name="cash_tendered" id="cashTendered" class="form-control form-control-sm" placeholder="0.00" oninput="calculateChange()">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-dark mb-1">Change Due (₱)</label>
                                <input type="text" id="changeDue" class="form-control form-control-sm bg-white fw-bold text-success" readonly value="₱0.00">
                            </div>
                        </div>

                        <input type="hidden" name="phone" value="09000000000">

                        <button type="submit" name="cashier_checkout" class="btn btn-warning w-100 fw-bold py-2 text-dark shadow">
                            <i class="fa-solid fa-check-double me-1"></i> Complete Payment & Print
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <!-- Bottom Footer Line -->
        <div class="bottom-full-line">
            <span>&copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan • Cashier POS Terminal</span>
        </div>

    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- POS JavaScript Logic -->
    <script>
        let posCart = [];

        function addPosItem(name, price) {
            let existing = posCart.find(item => item.name === name);
            if (existing) {
                existing.qty += 1;
            } else {
                posCart.push({
                    name: name,
                    price: price,
                    qty: 1,
                    egg: 'Sunny Side Up',
                    notes: ''
                });
            }
            renderPosCart();
        }

        function updatePosQty(index, delta) {
            posCart[index].qty += delta;
            if (posCart[index].qty <= 0) {
                posCart.splice(index, 1);
            }
            renderPosCart();
        }

        function updatePosEgg(index, val) {
            posCart[index].egg = val;
        }

        function updatePosNotes(index, val) {
            posCart[index].notes = val;
        }

        function renderPosCart() {
            let container = document.getElementById('posCartList');
            let totalEl = document.getElementById('posGrandTotal');

            if (posCart.length === 0) {
                container.innerHTML = '<p class="text-muted text-center small my-5" id="emptyCartText">No items added yet. Tap menu items on the left.</p>';
                totalEl.innerText = '₱0.00';
                calculateChange();
                return;
            }

            container.innerHTML = '';
            let total = 0;

            posCart.forEach((item, index) => {
                let subtotal = item.price * item.qty;
                total += subtotal;

                let html = `
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-dark">${item.name}</span>
                            <span class="text-warning fw-bold small text-dark">₱${subtotal.toFixed(2)}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <div class="d-flex gap-1 align-items-center">
                                <select onchange="updatePosEgg(${index}, this.value)" class="form-select form-select-sm fs-9 py-0 px-1" style="width: 95px;">
                                    <option value="Sunny Side Up" ${item.egg === 'Sunny Side Up' ? 'selected' : ''}>Sunny Up</option>
                                    <option value="Scrambled" ${item.egg === 'Scrambled' ? 'selected' : ''}>Scrambled</option>
                                    <option value="Well Done" ${item.egg === 'Well Done' ? 'selected' : ''}>Well Done</option>
                                    <option value="No Egg" ${item.egg === 'No Egg' ? 'selected' : ''}>No Egg</option>
                                </select>
                                <input type="text" placeholder="Note" value="${item.notes}" oninput="updatePosNotes(${index}, this.value)" class="form-control form-control-sm fs-9 py-0 px-1" style="width: 80px;">
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="qty-btn" onclick="updatePosQty(${index}, -1)">-</button>
                                <span class="small fw-bold px-1">${item.qty}</span>
                                <button type="button" class="qty-btn" onclick="updatePosQty(${index}, 1)">+</button>
                            </div>
                        </div>
                    </div>
                `;
                container.innerHTML += html;
            });

            totalEl.innerText = `₱${total.toFixed(2)}`;
            calculateChange();
        }

        function filterCat(cat, btn) {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            document.querySelectorAll('.menu-item-box').forEach(box => {
                if (cat === 'all' || box.dataset.category === cat) {
                    box.style.display = 'block';
                } else {
                    box.style.display = 'none';
                }
            });
        }

        function toggleCashInput() {
            let method = document.getElementById('paymentMethod').value;
            let box = document.getElementById('cashCalculatorBox');
            if (method === 'Cash') {
                box.style.display = 'flex';
            } else {
                box.style.display = 'none';
            }
        }

        function calculateChange() {
            let total = 0;
            posCart.forEach(item => total += item.price * item.qty);

            let tendered = parseFloat(document.getElementById('cashTendered').value) || 0;
            let change = tendered - total;

            let changeEl = document.getElementById('changeDue');
            if (change >= 0) {
                changeEl.value = `₱${change.toFixed(2)}`;
                changeEl.style.color = 'green';
            } else {
                changeEl.value = `₱0.00`;
                changeEl.style.color = 'red';
            }
        }

        function validateCashierCheckout(e) {
            if (posCart.length === 0) {
                alert('Cart is empty!');
                e.preventDefault();
                return false;
            }

            let method = document.getElementById('paymentMethod').value;
            if (method === 'Cash') {
                let total = 0;
                posCart.forEach(item => total += item.price * item.qty);
                let tendered = parseFloat(document.getElementById('cashTendered').value) || 0;

                if (tendered < total) {
                    alert('Cash tendered is less than total amount due!');
                    e.preventDefault();
                    return false;
                }
            }

            document.getElementById('posCartJson').value = JSON.stringify(posCart);
            return true;
        }
    </script>
</body>
</html>
