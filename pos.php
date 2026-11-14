<?php
session_start();
require_once 'config/db.php';

// Robust Logout Handler
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: homepage.php");
    exit;
}

// Handle Cashier Checkout Submission
$success_msg = ''; $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cashier_checkout'])) {
    try {
        global $pdo;
        $dining_style   = trim($_POST['dining_style'] ?? 'Dine-In');
        $customer_input = trim($_POST['customer_name'] ?? '');
        $customer_name  = !empty($customer_input) ? "$dining_style - $customer_input" : "$dining_style - Walk-in";
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

            $stmt = $pdo->prepare("INSERT INTO orders (customer_name, phone, delivery_area, address, total_amount, payment_method, payment_status, order_status) VALUES (?, ?, ?, 'Cashier POS Terminal', ?, ?, 'paid', 'preparing')");
            $stmt->execute([$customer_name, $phone, $dining_style, $total_amount, $payment_method]);
            $order_id = $pdo->lastInsertId();

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
        $update_stmt = $pdo->prepare("UPDATE kiosk_orders SET payment_status = 'completed' WHERE id = ?");
        $update_stmt->execute([$k_order_id]);
        header("Location: pos.php");
        exit;
    } catch (Exception $e) {
        $error_msg = 'Error updating kiosk order: ' . $e->getMessage();
    }
}

// Handle Marking Website Order as Complete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_website_order_id'])) {
    try {
        global $pdo;
        $w_order_id = intval($_POST['complete_website_order_id']);
        $update_stmt = $pdo->prepare("UPDATE website_orders SET order_status = 'completed', updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$w_order_id]);
        header("Location: pos.php");
        exit;
    } catch (Exception $e) {
        $error_msg = 'Error updating website order: ' . $e->getMessage();
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
} catch (Exception $e) {}

// Fetch Active Live Kiosk Orders
$kiosk_orders = [];
try {
    $kiosk_stmt = $pdo->query("SELECT * FROM kiosk_orders WHERE payment_status = 'paid' ORDER BY id DESC LIMIT 15");
    while ($k_row = $kiosk_stmt->fetch(PDO::FETCH_ASSOC)) {
        $k_item_stmt = $pdo->prepare("SELECT * FROM kiosk_order_items WHERE order_id = ?");
        $k_item_stmt->execute([$k_row['id']]);
        $k_row['items'] = $k_item_stmt->fetchAll(PDO::FETCH_ASSOC);
        $k_row['source'] = 'kiosk';
        $kiosk_orders[] = $k_row;
    }
} catch (Exception $e) {}

// Fetch Active Live Website Orders
try {
    $web_stmt = $pdo->query("SELECT * FROM website_orders WHERE order_status != 'completed' ORDER BY id DESC LIMIT 15");
    while ($w_row = $web_stmt->fetch(PDO::FETCH_ASSOC)) {
        $w_item_stmt = $pdo->prepare("SELECT * FROM website_order_items WHERE order_id = ?");
        $w_item_stmt->execute([$w_row['id']]);
        $w_row['items'] = $w_item_stmt->fetchAll(PDO::FETCH_ASSOC);
        $w_row['source'] = 'website';
        $w_row['dining_style'] = 'Take-Out';
        $w_row['table_tag'] = $w_row['address'] ?? '';
        $w_row['neighborhood_badge'] = $w_row['delivery_area'] ?? '';
        foreach ($w_row['items'] as &$it) {
            $it['product_name'] = $it['item_name'];
        }
        unset($it);
        $kiosk_orders[] = $w_row;
    }
} catch (Exception $e) {}

usort($kiosk_orders, function($a, $b) {
    return $b['id'] - $a['id'];
});

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        body {
            background-color: #2b1d0c;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 15px;
            position: relative;
            min-height: 100vh;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(0, 0, 0, 0.25), rgba(0, 0, 0, 0.35)), url('images/Wood .jpg') no-repeat center center;
            background-size: cover;
            z-index: -1;
        }
        .page-outer-frame {
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(8px);
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
            background-color: rgba(0, 0, 0, 0.4);
            color: #ffffff;
            font-weight: bold;
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 50px;
        }
        .category-btn.active, .category-btn:hover {
            background-color: #ffc107;
            color: #121212;
        }
        .menu-pos-card {
            border: 1.5px solid #ffc107;
            border-radius: 8px;
            padding: 12px;
            background: rgba(0, 0, 0, 0.5);
            cursor: pointer;
            transition: all 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #ffffff;
        }
        .menu-pos-card:hover {
            background: rgba(255, 193, 7, 0.2);
        }
        .pos-cart-panel {
            background: rgba(0, 0, 0, 0.45);
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
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 10px;
            background-color: rgba(0, 0, 0, 0.4);
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
            background: rgba(0, 0, 0, 0.5);
            border: 1.5px solid #ffc107;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
        select.form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        }
        .bottom-full-line {
            border: 1px solid #ffc107;
            background: rgba(0, 0, 0, 0.4);
            height: 36px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 0.85rem;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <div class="page-outer-frame">
        <div class="cashier-header">
            <div>
                <h3 class="fw-bold mb-0"><i class="fa-solid fa-cash-register me-2"></i>Cashier POS Terminal</h3>
                <small class="text-light">Sairene TephTeph Tapsilogan • Counter Operations & Kiosk Sync</small>
            </div>
            <div class="d-flex gap-2">
                <a href="admin.php" class="btn btn-warning btn-sm fw-bold text-dark"><i class="fa-solid fa-gauge-high me-1"></i> Admin Panel</a>
                <a href="pos.php?action=logout" class="btn btn-outline-danger btn-sm fw-bold"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a>
            </div>
        </div>

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

        <!-- ACTIVE ORDERS BANNER -->
        <div class="mb-4">
            <div class="card border-warning shadow-sm bg-transparent">
                <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#kioskOrdersCollapse" role="button" aria-expanded="true" style="cursor: pointer;">
                    <span><i class="fa-solid fa-tablet-screen-button me-2"></i> Tablet Kiosk and Online Website Orders</span>
                    <small><i class="fa-solid fa-chevron-down"></i></small>
                </div>
                <div class="collapse show" id="kioskOrdersCollapse">
                    <div class="card-body" style="background: rgba(0, 0, 0, 0.4); max-height: 280px; overflow-y: auto;">
                        <?php if (empty($kiosk_orders)): ?>
                            <p class="text-light text-center small mb-0 py-3">No active orders found.</p>
                        <?php else: ?>
                            <div class="row g-2">
                                <?php foreach ($kiosk_orders as $k_order): ?>
                                    <div class="col-md-4">
                                        <div class="kiosk-order-card">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="badge bg-dark text-warning fw-bold"><?= htmlspecialchars($k_order['queue_number'] ?? 'Q-01') ?></span>
                                                <span class="small text-warning"><?= htmlspecialchars($k_order['dining_style']) ?></span>
                                            </div>
                                            <div class="fw-bold small text-white"><?= htmlspecialchars($k_order['customer_name']) ?></div>
                                            <div class="border-top border-warning my-1 pt-1 small text-light">
                                                <?php foreach ($k_order['items'] as $k_item): ?>
                                                    <div>• <?= $k_item['quantity'] ?>x <?= htmlspecialchars($k_item['product_name']) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top border-secondary">
                                                <span class="small text-white"><?= htmlspecialchars($k_order['neighborhood_badge'] ?? '') ?></span>
                                                <span class="fw-bold text-warning">₱<?= number_format($k_order['total_amount'], 2) ?></span>
                                            </div>
                                            <form method="POST" class="mt-2 pt-2 border-top border-secondary">
                                                <?php if (($k_order['source'] ?? 'kiosk') === 'website'): ?>
                                                    <input type="hidden" name="complete_website_order_id" value="<?= $k_order['id'] ?>">
                                                <?php else: ?>
                                                    <input type="hidden" name="complete_kiosk_order_id" value="<?= $k_order['id'] ?>">
                                                <?php endif; ?>
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
            <!-- MENU GRID -->
            <div class="col-lg-7">
                <div class="border border-warning rounded-3 p-3 h-100 shadow-sm" style="background: rgba(0, 0, 0, 0.45);">
                    <h5 class="fw-bold text-warning mb-3 pb-2 border-bottom border-warning">Menu Catalog</h5>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="category-btn active" onclick="filterCat('all', this)">All</button>
                        <button type="button" class="category-btn" onclick="filterCat('silog', this)">Silog</button>
                        <button type="button" class="category-btn" onclick="filterCat('combo', this)">Combo</button>
                        <button type="button" class="category-btn" onclick="filterCat('alacarte', this)">Ala Carte</button>
                        <button type="button" class="category-btn" onclick="filterCat('foodpack', this)">Food Pack</button>
                        <button type="button" class="category-btn" onclick="filterCat('drinks', this)">Drinks</button>
                    </div>
                    <div class="row g-2 overflow-y-auto" style="max-height: 480px;" id="posMenuGrid">
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
                                                <h6 class="fw-bold text-white mb-1 fs-6"><?= htmlspecialchars($item['item_name']) ?></h6>
                                                <span class="badge bg-warning text-dark fs-9 text-uppercase"><?= htmlspecialchars($cat) ?></span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-secondary">
                                            <span class="text-warning fw-bold">₱<?= number_format($item['price'], 2) ?></span>
                                            <span class="btn btn-warning btn-sm py-0 px-2 fw-bold text-dark">+ Tap</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- CART PANEL -->
            <div class="col-lg-5">
                <div class="pos-cart-panel shadow-sm">
                    <h5 class="fw-bold text-warning mb-3 pb-2 border-bottom border-warning">Current Transaction</h5>
                    <div id="posCartList" class="cart-list-box mb-3">
                        <p class="text-light text-center small my-5">No items added yet.</p>
                    </div>
                    <div class="p-3 rounded-2 border border-warning mb-3" style="background: rgba(0, 0, 0, 0.4);">
                        <div class="d-flex justify-content-between text-white fw-bold fs-5">
                            <span>Total Due:</span>
                            <span class="text-warning" id="posGrandTotal">₱0.00</span>
                        </div>
                    </div>
                    <form method="POST" onsubmit="return validateCashierCheckout(event)">
                        <input type="hidden" name="cart_json" id="posCartJson">
                        
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-white">Order Type</label>
                                <select name="dining_style" class="form-select form-select-sm fw-bold bg-dark text-white border-warning">
                                    <option value="Dine-In">Dine-In</option>
                                    <option value="Take-Out">Take-Out</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-white">Table / Name (Opt)</label>
                                <input type="text" name="customer_name" class="form-control form-control-sm bg-dark text-white border-warning" placeholder="e.g. Table 3">
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-white">Payment Method</label>
                                <select name="payment_method" id="paymentMethod" class="form-select form-select-sm bg-dark text-white border-warning" onchange="toggleCashInput()">
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                </select>
                            </div>
                        </div>

                        <div id="cashCalculatorBox" class="row g-2 mb-3 bg-transparent">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-white">Cash Tendered (₱)</label>
                                <input type="number" step="0.01" name="cash_tendered" id="cashTendered" class="form-control form-control-sm bg-dark text-white border-warning" oninput="calculateChange()">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-white">Change Due (₱)</label>
                                <input type="text" id="changeDue" class="form-control form-control-sm bg-dark text-white fw-bold border-secondary" readonly value="₱0.00">
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
        
        <div class="bottom-full-line">
            <span>&copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan • Cashier POS Terminal</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let posCart = [];

        function addPosItem(name, price) {
            let existing = posCart.find(item => item.name === name);
            if (existing) {
                existing.qty += 1;
            } else {
                posCart.push({ name: name, price: price, qty: 1, egg: 'Sunny Side Up', notes: '' });
            }
            renderPosCart();
        }

        function updatePosQty(index, delta) {
            posCart[index].qty += delta;
            if (posCart[index].qty <= 0) posCart.splice(index, 1);
            renderPosCart();
        }

        function updatePosEgg(index, val) { posCart[index].egg = val; }
        function updatePosNotes(index, val) { posCart[index].notes = val; }

        function renderPosCart() {
            let container = document.getElementById('posCartList');
            let totalEl = document.getElementById('posGrandTotal');
            if (posCart.length === 0) {
                container.innerHTML = '<p class="text-light text-center small my-5">No items added yet.</p>';
                totalEl.innerText = '₱0.00';
                calculateChange();
                return;
            }
            container.innerHTML = '';
            let total = 0;
            posCart.forEach((item, index) => {
                let subtotal = item.price * item.qty;
                total += subtotal;
                container.innerHTML += `
                    <div class="border-bottom border-secondary pb-2 mb-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-white">${item.name}</span>
                            <span class="text-warning fw-bold small">₱${subtotal.toFixed(2)}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <div class="d-flex gap-1 align-items-center">
                                <select onchange="updatePosEgg(${index}, this.value)" class="form-select form-select-sm fs-9 py-0 px-1 bg-dark text-white border-secondary" style="width: 95px;">
                                    <option value="Sunny Side Up" ${item.egg === 'Sunny Side Up' ? 'selected' : ''}>Sunny Up</option>
                                    <option value="Scrambled" ${item.egg === 'Scrambled' ? 'selected' : ''}>Scrambled</option>
                                    <option value="Well Done" ${item.egg === 'Well Done' ? 'selected' : ''}>Well Done</option>
                                    <option value="No Egg" ${item.egg === 'No Egg' ? 'selected' : ''}>No Egg</option>
                                </select>
                                <input type="text" placeholder="Note" value="${item.notes}" oninput="updatePosNotes(${index}, this.value)" class="form-control form-control-sm fs-9 py-0 px-1 bg-dark text-white border-secondary" style="width: 80px;">
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="qty-btn" onclick="updatePosQty(${index}, -1)">-</button>
                                <span class="small fw-bold px-1 text-white">${item.qty}</span>
                                <button type="button" class="qty-btn" onclick="updatePosQty(${index}, 1)">+</button>
                            </div>
                        </div>
                    </div>`;
            });
            totalEl.innerText = `₱${total.toFixed(2)}`;
            calculateChange();
        }

        function filterCat(cat, btn) {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.menu-item-box').forEach(box => {
                box.style.display = (cat === 'all' || box.dataset.category === cat) ? 'block' : 'none';
            });
        }

        function toggleCashInput() {
            let method = document.getElementById('paymentMethod').value;
            document.getElementById('cashCalculatorBox').style.display = (method === 'Cash') ? 'flex' : 'none';
        }

        function calculateChange() {
            let total = 0;
            posCart.forEach(item => total += item.price * item.qty);
            let tendered = parseFloat(document.getElementById('cashTendered').value) || 0;
            let change = tendered - total;
            let changeEl = document.getElementById('changeDue');
            if (change >= 0) {
                changeEl.value = `₱${change.toFixed(2)}`;
                changeEl.style.color = '#75b798';
            } else {
                changeEl.value = `₱0.00`;
                changeEl.style.color = '#ea868f';
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