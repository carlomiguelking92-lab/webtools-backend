<?php
session_start();
require_once 'db.php';

// --- AJAX / POST HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // 1. Sign Up Handler
    if ($_POST['action'] === 'signup') {
        try {
            global $pdo;
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($fullname) || empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email address is already in use.']);
                exit;
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'customer')");
            $stmt->execute([$fullname, $email, $hashedPassword]);

            // Auto login upon successful signup
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['fullname'] = $fullname;

            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    // 2. Login Handler
    if ($_POST['action'] === 'login') {
        try {
            global $pdo;
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            }
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    // 3. Logout Handler
    if ($_POST['action'] === 'logout') {
        session_unset();
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }

    // 4. Place Order Handler (Restricted to Logged-In Users)
    if ($_POST['action'] === 'place_order') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'You must be logged in to place an order.']);
            exit;
        }

        try {
            global $pdo;
            $user_id = $_SESSION['user_id'];
            $customer_name = $_POST['customer_name'] ?? 'Guest';
            $phone = $_POST['phone'] ?? '';
            $delivery_area = $_POST['delivery_area'] ?? 'Taguig';
            $address = $_POST['address'] ?? 'N/A';
            $total_amount = floatval($_POST['total_amount'] ?? 0);
            $cart = json_decode($_POST['cart_json'], true);

            $payment_proof_path = '';
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $filename = 'proof_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $payment_proof_path = $upload_dir . $filename;
                move_uploaded_file($_FILES['payment_proof']['tmp_name'], $payment_proof_path);
            }

            $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, phone, delivery_area, address, total_amount, payment_method, payment_status, order_status, payment_proof) VALUES (?, ?, ?, ?, ?, ?, 'GCash', 'paid', 'preparing', ?)");
            $stmt->execute([$user_id, $customer_name, $phone, $delivery_area, $address, $total_amount, $payment_proof_path]);
            $order_id = $pdo->lastInsertId();

            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, item_name, egg_choice, notes, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($cart as $item) {
                $stmt_item->execute([
                    $order_id,
                    $item['name'],
                    $item['egg'] ?? '',
                    $item['notes'] ?? '',
                    $item['qty'],
                    $item['price']
                ]);
            }

            echo json_encode(['success' => true, 'order_id' => $order_id]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    // 5. Complete Order Handler
    if ($_POST['action'] === 'complete_order') {
        try {
            global $pdo;
            $order_id = intval($_POST['order_id']);
            $stmt = $pdo->prepare("UPDATE orders SET order_status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$order_id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Fetch ongoing & past orders specifically for the logged-in user
$ongoing_orders = [];
$past_orders = [];
$is_logged_in = isset($_SESSION['user_id']);
$logged_in_user_id = $is_logged_in ? $_SESSION['user_id'] : 0;

try {
    global $pdo;
    if (isset($pdo) && $is_logged_in) {
        $ongoing_raw = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND order_status != 'completed' ORDER BY id DESC");
        $ongoing_raw->execute([$logged_in_user_id]);
        foreach ($ongoing_raw->fetchAll(PDO::FETCH_ASSOC) as $ord) {
            $items_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $items_stmt->execute([$ord['id']]);
            $ord['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            $ongoing_orders[] = $ord;
        }

        $past_raw = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND order_status = 'completed' ORDER BY id DESC LIMIT 10");
        $past_raw->execute([$logged_in_user_id]);
        foreach ($past_raw->fetchAll(PDO::FETCH_ASSOC) as $ord) {
            $items_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $items_stmt->execute([$ord['id']]);
            $ord['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            $past_orders[] = $ord;
        }
    }
} catch (Exception $e) {
    // Database tables might not be fully migrated yet
}

// Page Selection
$page = isset($_GET['page']) && $_GET['page'] == 2 ? 2 : 1; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sairene Tephteph Tapsilogan - Digital Menu</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS Part -->
    <style>
        body {
            background-color: #121212;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #f8f9fa;
        }

        .text-gold {
            color: #ffc107;
        }

        .btn-gold {
            background-color: #ffc107;
            color: #000;
            border: none;
            font-weight: bold;
            border-radius: 50px;
            padding: 10px 18px;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-gold:hover {
            background-color: #e0a800;
            color: #000;
            transform: scale(1.05);
        }

        .btn-gold.active {
            background-color: #ffffff;
            color: #000;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
        }

        /* Navigation Bar Styles */
        .main-navbar {
            background-color: rgba(20, 20, 20, 0.95);
            border: 1px solid #ffc107;
            border-radius: 50px;
            padding: 8px 16px;
            max-width: 900px;
            margin: 0 auto 25px auto;
        }

        .main-navbar .nav-link {
            color: #ffffff;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 25px;
            transition: all 0.2s ease-in-out;
        }

        .main-navbar .nav-link:hover {
            color: #ffc107;
            background-color: rgba(255, 193, 7, 0.15);
        }

        .main-navbar .nav-link.active {
            color: #000000;
            background-color: #ffc107;
        }

        /* Menu Container */
        .menu-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .menu-board-container {
            position: relative;
            width: 100%;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8);
        }

        .menu-board-img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Invisible Hotspots */
        .hotspot {
            position: absolute;
            background: transparent;
            border: none;
            outline: none;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        /* Hover Glow Effect */
        .hotspot:hover, .hotspot:focus {
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.6);
            transform: scale(1.03);
        }

        /* Popup Cards */
        .popover-detail-card {
            display: none;
            position: absolute;
            bottom: 105%;
            left: 50%;
            transform: translateX(-50%);
            width: 220px;
            background-color: rgba(20, 20, 20, 0.95);
            color: #ffffff;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #ffc107;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.7);
            z-index: 20;
            pointer-events: none;
            text-align: center;
        }

        .popover-detail-card h5 {
            font-size: 0.95rem;
            font-weight: bold;
            color: #ffc107;
            margin-bottom: 6px;
        }

        .popover-detail-card p {
            font-size: 0.8rem;
            margin-bottom: 0;
            color: #dddddd;
        }

        .hotspot:hover .popover-detail-card,
        .hotspot:focus .popover-detail-card {
            display: block;
        }

        /* Floating Cart Button (Upper Right Sticky) */
        .floating-cart-btn {
            position: fixed;
            top: 25px;
            right: 25px;
            z-index: 1050;
            border-radius: 50px;
            padding: 10px 22px;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.4);
            font-weight: bold;
        }

        /* Floating Auth Button (Upper Left Sticky) */
        .floating-auth-btn {
            position: fixed;
            top: 25px;
            left: 25px;
            z-index: 1050;
            border-radius: 50px;
            padding: 10px 22px;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.4);
            font-weight: bold;
        }

        /* Floating Toast Notification Containers */
        #toast-container {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 1100;
        }

        #auth-toast-container {
            position: fixed;
            bottom: 25px;
            left: 25px;
            z-index: 1100;
        }

        .custom-toast {
            background-color: #198754;
            color: #ffffff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.4);
            font-weight: 600;
            margin-top: 10px;
            opacity: 1;
            transition: opacity 1s ease-in-out;
        }

        .custom-toast.fade-out {
            opacity: 0;
        }

        /* Cart & Auth Modal Blur Backdrop & Transparency */
        .modal-backdrop.show {
            backdrop-filter: blur(5px);
            background-color: rgba(0, 0, 0, 0.6);
        }

        .cart-modal-content {
            background-color: rgba(20, 20, 20, 0.92) !important;
            border: 1px solid #ffc107;
            color: #ffffff;
            border-radius: 12px;
        }

        /* Cart Tabs */
        .cart-tab-btn {
            flex: 1;
            text-align: center;
            border-radius: 8px;
            padding: 8px;
            color: #e0e0e0;
            background: #212529;
            border: 1px solid #343a40;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .cart-tab-btn.active {
            background: #ffc107;
            color: #000;
            font-weight: bold;
            border-color: #ffc107;
        }

        /* Slide-to-Delete Cart Item Card */
        .cart-item-card {
            background: #212529;
            border: 1px solid #343a40;
            border-radius: 8px;
            margin-bottom: 8px;
            padding: 12px;
            user-select: none;
            touch-action: pan-y;
            transition: transform 0.2s ease-out;
            position: relative;
        }

        .subtext-info {
            font-size: 0.78rem;
            color: #9ca3af;
            display: block;
            margin-top: 2px;
        }

        .qty-btn {
            background: transparent;
            border: 1px solid #ffc107;
            color: #ffc107;
            width: 26px;
            height: 26px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-dark text-light">

    <!-- Sticky Upper Left Floating Auth Button -->
    <?php if ($is_logged_in): ?>
        <button class="btn btn-outline-warning floating-auth-btn" onclick="confirmLogout()">
            <i class="fa-solid fa-right-from-bracket me-2"></i>Logout (<?= htmlspecialchars($_SESSION['fullname']) ?>)
        </button>
    <?php else: ?>
        <button class="btn btn-gold floating-auth-btn" onclick="openAuthModal('login')">
            <i class="fa-solid fa-user me-2"></i>Login
        </button>
    <?php endif; ?>

    <!-- Sticky Upper Right Floating Cart Button -->
    <button class="btn btn-gold floating-cart-btn" onclick="toggleCartModal()">
        <i class="fa-solid fa-cart-shopping me-2"></i>Cart (<span id="cart-count">0</span>)
    </button>

    <!-- Lower Right Notification Container (Cart / Orders) -->
    <div id="toast-container"></div>
    <!-- Lower Left Notification Container (Auth) -->
    <div id="auth-toast-container"></div>

    <div class="container py-4">
        <!-- Navigation Bar -->
        <nav class="main-navbar">
            <ul class="nav justify-content-center gap-1">
                <li class="nav-item">
                    <a class="nav-link" href="home_page.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="menu.php">Menu</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="milktea.php">Milk Tea</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tambay_caps.php">Tambay Caps</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="food_packs.php">Food Packs</a>
                </li>
            </ul>
        </nav>

        <!-- Header -->
        <header class="text-center mb-4">
            <h1 class="display-5 fw-bold text-gold">SAIRENE TEPHTEPH TAPSILOGAN</h1>
            <p class="lead text-light">A Taste of Comfort • Interactive Menu</p>
        </header>

        <!-- Arrow Buttons -->
        <div class="d-flex justify-content-center align-items-center gap-3 mb-4">
            <a href="menu.php?page=1" class="btn btn-gold <?php echo $page == 1 ? 'active' : ''; ?>" aria-label="Previous Page">
                <i class="fa-solid fa-chevron-left fs-5"></i>
            </a>
            
            <a href="menu.php?page=2" class="btn btn-gold <?php echo $page == 2 ? 'active' : ''; ?>" aria-label="Next Page">
                <i class="fa-solid fa-chevron-right fs-5"></i>
            </a>
        </div>

        <!-- Menu Container -->
        <div class="menu-wrapper">
            <?php if ($page == 1): ?>
                <!-- PAGE 1: SILOG MEALS -->
                <div id="menu-page-1" class="menu-board-container">
                    <img src="menu_page_1.png" alt="Sairene Tapsilogan Silog Meals" class="img-fluid menu-board-img">

                    <!-- Row 1 -->
                    <div class="hotspot" style="top: 40.85%; left: 9.03%; width: 16.97%; height: 11.99%;" tabindex="0" onclick="openItemModal('Tapsilog', 90, true)">
                        <div class="popover-detail-card">
                            <h5>Tapsilog (₱90)</h5>
                            <p>Marinated Beef Tapa, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 40.85%; left: 39.81%; width: 16.76%; height: 11.97%;" tabindex="0" onclick="openItemModal('Malingsilog', 90, true)">
                        <div class="popover-detail-card">
                            <h5>Malingsilog (₱90)</h5>
                            <p>Fried Luncheon Meat, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 40.80%; left: 70.27%; width: 17.08%; height: 12.08%;" tabindex="0" onclick="openItemModal('Liemposilog', 90, true)">
                        <div class="popover-detail-card">
                            <h5>Liemposilog (₱90)</h5>
                            <p>Grilled Pork Belly, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <!-- Row 2 -->
                    <div class="hotspot" style="top: 60.00%; left: 9.19%; width: 16.70%; height: 11.84%;" tabindex="0" onclick="openItemModal('Bagnetsilog', 90, true)">
                        <div class="popover-detail-card">
                            <h5>Bagnetsilog (₱90)</h5>
                            <p>Crispy Pork Bagnet, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 59.94%; left: 39.48%; width: 16.92%; height: 12.04%;" tabindex="0" onclick="openItemModal('Tocilog', 90, true)">
                        <div class="popover-detail-card">
                            <h5>Tocilog (₱90)</h5>
                            <p>Sweet Pork Tocino, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 59.89%; left: 72.10%; width: 16.76%; height: 12.03%;" tabindex="0" onclick="openItemModal('Hungariansilog', 90, true)">
                        <div class="popover-detail-card">
                            <h5>Hungariansilog (₱90)</h5>
                            <p>Hungarian Sausage, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <!-- Row 3 -->
                    <div class="hotspot" style="top: 75.44%; left: 24.74%; width: 16.76%; height: 12%;" tabindex="0" onclick="openItemModal('Hotdogsilog', 80, true)">
                        <div class="popover-detail-card">
                            <h5>Hotdogsilog (₱80)</h5>
                            <p>Juicy Hotdog, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 76.01%; left: 56.57%; width: 16.76%; height: 12%;" tabindex="0" onclick="openItemModal('Sisigsilog', 90, true)">
                        <div class="popover-detail-card">
                            <h5>Sisigsilog (₱90)</h5>
                            <p>Savory Pork Sisig, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>
                </div>

            <?php else: ?>

                <!-- PAGE 2: COMBO MEALS & ALA CARTE -->
                <div id="menu-page-2" class="menu-board-container">
                    <img src="menu_page_2.png" alt="Sairene Tapsilogan Combos and Ala Carte" class="img-fluid menu-board-img">

                    <!-- Combo Meals Row 1 -->
                    <div class="hotspot" style="top: 14.25%; left: 7.41%; width: 19.02%; height: 13.45%;" tabindex="0" onclick="openItemModal('Bagnet + Hungarian', 140, true)">
                        <div class="popover-detail-card">
                            <h5>Bagnet + Hungarian (₱140)</h5>
                            <p>Crispy Bagnet, Hungarian Sausage, Rice, Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 13.79%; left: 36.66%; width: 19.34%; height: 13.68%;" tabindex="0" onclick="openItemModal('Tapa + Hungarian', 140, true)">
                        <div class="popover-detail-card">
                            <h5>Tapa + Hungarian (₱140)</h5>
                            <p>Beef Tapa, Hungarian Sausage, Rice, Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 13.39%; left: 66.80%; width: 19.18%; height: 13.56%;" tabindex="0" onclick="openItemModal('Tapa + Bagnet Silog', 140, true)">
                        <div class="popover-detail-card">
                            <h5>Tapa + Bagnet Silog (₱140)</h5>
                            <p>Beef Tapa, Crispy Bagnet, Rice, Egg.</p>
                        </div>
                    </div>

                    <!-- Combo Meals Row 2 -->
                    <div class="hotspot" style="top: 32.02%; left: 19.82%; width: 20.79%; height: 14.70%;" tabindex="0" onclick="openItemModal('Liempo + Hungarian', 140, true)">
                        <div class="popover-detail-card">
                            <h5>Liempo + Hungarian (₱140)</h5>
                            <p>Grilled Liempo, Hungarian Sausage, Rice, Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 32.99%; left: 53.99%; width: 20.31%; height: 14.36%;" tabindex="0" onclick="openItemModal('Sisig + Hungarian', 140, true)">
                        <div class="popover-detail-card">
                            <h5>Sisig + Hungarian (₱140)</h5>
                            <p>Pork Sisig, Hungarian Sausage, Rice, Egg.</p>
                        </div>
                    </div>

                    <!-- Ala Carte Row 1 -->
                    <div class="hotspot" style="top: 56.30%; left: 7.74%; width: 17.41%; height: 12.31%;" tabindex="0" onclick="openItemModal('Special Tapa', 130, false)">
                        <div class="popover-detail-card">
                            <h5>Special Tapa (₱130)</h5>
                            <p>Ala Carte Portion Marinated Beef Tapa.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 56.58%; left: 34.73%; width: 17.57%; height: 12.42%;" tabindex="0" onclick="openItemModal('Special Bagnet', 130, false)">
                        <div class="popover-detail-card">
                            <h5>Special Bagnet (₱130)</h5>
                            <p>Ala Carte Serving Crispy Pork Bagnet.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 57.21%; left: 67.85%; width: 17.41%; height: 12.31%;" tabindex="0" onclick="openItemModal('Special Sisig', 130, false)">
                        <div class="popover-detail-card">
                            <h5>Special Sisig (₱130)</h5>
                            <p>Ala Carte Serving Savory Pork Sisig.</p>
                        </div>
                    </div>

                    <!-- Ala Carte Row 2 -->
                    <div class="hotspot" style="top: 75.33%; left: 8.14%; width: 16.92%; height: 11.97%;" tabindex="0" onclick="openItemModal('Hungarian Sausage', 130, false)">
                        <div class="popover-detail-card">
                            <h5>Hungarian Sausage (₱130)</h5>
                            <p>Single Hungarian Sausage Portion.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 75.84%; left: 35.29%; width: 16.60%; height: 11.74%;" tabindex="0" onclick="openItemModal('Plain Rice', 15, false)">
                        <div class="popover-detail-card">
                            <h5>Plain Rice (₱15)</h5>
                            <p>Steamed White Rice.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 75.38%; left: 58.66%; width: 16.76%; height: 11.85%;" tabindex="0" onclick="openItemModal('Garlic Rice', 20, false)">
                        <div class="popover-detail-card">
                            <h5>Garlic Rice (₱20)</h5>
                            <p>Garlic Fried Rice.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 75.21%; left: 78.97%; width: 16.92%; height: 11.97%;" tabindex="0" onclick="openItemModal('Extra Egg', 15, false)">
                        <div class="popover-detail-card">
                            <h5>Extra Egg (₱15)</h5>
                            <p>Fried Egg (Sunny Side Up / Well Done / Scrambled).</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer class="text-center mt-4 text-light small">
            <p class="mb-1">MRB Condominium C5, Taguig City</p>
            <p class="mb-0">GCash Payments Accepted: 09270466231 (Sarippudin Aming)</p>
        </footer>
    </div>

    <!-- MIDDLE POPUP MODAL FOR ITEM CUSTOMIZATION -->
    <div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content cart-modal-content p-2">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-gold fw-bold" id="modalItemName">Dish Name</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-light mb-2"><strong class="text-gold">Ingredients:</strong> <span id="modalItemDescription">Description of ingredients...</span></p>
                    <p class="fs-5 text-white fw-bold">Price: ₱<span id="modalItemPrice">0</span></p>
                    
                    <!-- Choose Your Egg Section -->
                    <div class="mb-3" id="eggSection">
                        <label class="form-label text-gold fw-semibold">Choose Your Egg:</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="eggChoice" id="eggSunny" value="Sunny Side Up" checked>
                                <label class="form-check-label text-light" for="eggSunny">Sunny Side Up</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="eggChoice" id="eggScrambled" value="Scrambled">
                                <label class="form-check-label text-light" for="eggScrambled">Scrambled</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="eggChoice" id="eggWellDone" value="Well Done">
                                <label class="form-check-label text-light" for="eggWellDone">Well Done</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="eggChoice" id="eggNone" value="No Egg">
                                <label class="form-check-label text-light" for="eggNone">No Egg / Don't Want Egg</label>
                            </div>
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div class="mb-3">
                        <label class="form-label text-gold fw-semibold">Quantity</label>
                        <div class="input-group" style="width: 140px;">
                            <button class="btn btn-outline-warning btn-sm" type="button" onclick="adjustModalQty(-1)">-</button>
                            <input type="text" id="modalItemQty" class="form-control bg-dark text-light text-center border-secondary form-control-sm" value="1" readonly>
                            <button class="btn btn-outline-warning btn-sm" type="button" onclick="adjustModalQty(1)">+</button>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label class="form-label text-gold fw-semibold">Notes / Special Instructions</label>
                        <textarea id="modalItemNotes" class="form-control bg-dark text-light border-secondary" rows="2" placeholder="e.g. Extra spicy, separate sauce..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-gold" onclick="addCurrentItemToCart()">Add to Cart</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LOGIN / SIGNUP MODAL -->
    <div class="modal fade" id="authModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content cart-modal-content p-3">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-gold fw-bold" id="authModalTitle">Login to Your Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Login Form -->
                    <form id="loginForm" onsubmit="submitAuthForm(event, 'login')">
                        <div class="mb-3">
                            <label class="form-label text-light small">Email Address</label>
                            <input type="email" id="loginEmail" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-light small">Password</label>
                            <input type="password" id="loginPassword" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <button type="submit" class="btn btn-gold w-100 fw-bold mb-2">Login</button>
                        <p class="text-center text-light small mb-0">Don't have an account? <a href="#" class="text-gold text-decoration-none" onclick="switchAuthMode('signup')">Sign Up</a></p>
                    </form>

                    <!-- Sign Up Form -->
                    <form id="signupForm" class="d-none" onsubmit="submitAuthForm(event, 'signup')">
                        <div class="mb-3">
                            <label class="form-label text-light small">Full Name</label>
                            <input type="text" id="signupFullname" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-light small">Email Address</label>
                            <input type="email" id="signupEmail" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-light small">Password</label>
                            <input type="password" id="signupPassword" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <button type="submit" class="btn btn-gold w-100 fw-bold mb-2">Sign Up</button>
                        <p class="text-center text-light small mb-0">Already have an account? <a href="#" class="text-gold text-decoration-none" onclick="switchAuthMode('login')">Login</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- CART & ORDER MANAGEMENT MODAL -->
    <div class="modal fade" id="cartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content cart-modal-content">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex gap-2 w-100 me-2">
                        <div class="cart-tab-btn active" id="tab-btn-current" onclick="switchCartTab('current')">Current Cart</div>
                        <div class="cart-tab-btn" id="tab-btn-ongoing" onclick="switchCartTab('ongoing')">Ongoing Orders</div>
                        <div class="cart-tab-btn" id="tab-btn-past" onclick="switchCartTab('past')">Past Orders</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    
                    <!-- TAB 1: CURRENT CART -->
                    <div id="tab-current" class="cart-tab-content">
                        <p class="text-light small mb-2"><i class="fa-solid fa-info-circle me-1"></i> Tip: Slide item left by 1/3 or use the delete button to remove.</p>
                        <div id="cart-items-list" class="mb-3" style="max-height: 250px; overflow-y: auto;">
                            <!-- Dynamic Cart Items -->
                        </div>
                        
                        <div class="border-top border-secondary pt-3">
                            <div class="d-flex justify-content-between fs-4 fw-bold text-gold mb-3">
                                <span>Total Bill:</span>
                                <span>₱<span id="cart-total">0.00</span></span>
                            </div>

                            <div id="checkout-section">
                                <button type="button" class="btn btn-gold w-100 fw-bold py-2" onclick="proceedToCheckoutForm()">Order Now</button>
                            </div>

                            <div id="payment-delivery-form" class="d-none mt-3 p-3 bg-secondary bg-opacity-10 border border-secondary rounded">
                                <h6 class="text-gold fw-bold mb-3"><i class="fa-solid fa-wallet me-2"></i>Checkout & GCash Payment</h6>
                                
                                <div class="alert alert-warning py-2 small mb-3">
                                    <strong>GCash Details:</strong> 09270466231 (Sarippudin Aming)<br>
                                    Please send your payment via GCash, then fill out details below and upload your payment proof screenshot.
                                </div>

                                <form id="orderSubmitForm" class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label text-light small mb-1">Customer Name</label>
                                        <input type="text" id="custName" class="form-control form-control-sm bg-dark text-light border-secondary" required placeholder="Juan Dela Cruz">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-light small mb-1">Contact Number</label>
                                        <input type="text" id="custPhone" class="form-control form-control-sm bg-dark text-light border-secondary" required placeholder="09123456789">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-light small mb-1">Delivery Area</label>
                                        <input type="text" id="custArea" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="e.g. Taguig">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-light small mb-1">Address / Landmark</label>
                                        <input type="text" id="custAddress" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Unit / Street / Subdivision" required>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="form-label text-gold small mb-1">Upload GCash Payment Screenshot Proof</label>
                                        <input type="file" id="paymentProof" class="form-control form-control-sm bg-dark text-light border-secondary" accept="image/*" required>
                                    </div>
                                    <div class="col-12 mt-3">
                                        <button type="button" class="btn btn-gold w-100 fw-bold" onclick="submitFinalOrder()">Finish Order</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: ONGOING ORDERS -->
                    <div id="tab-ongoing" class="cart-tab-content d-none">
                        <?php if (!$is_logged_in): ?>
                            <p class="text-light text-center my-4">Please <a href="#" class="text-gold" onclick="openAuthModal('login')">login</a> to view your ongoing orders.</p>
                        <?php elseif (empty($ongoing_orders)): ?>
                            <p class="text-light text-center my-4">No ongoing active orders found.</p>
                        <?php else: ?>
                            <?php foreach ($ongoing_orders as $ord): ?>
                                <div class="card bg-dark border border-secondary mb-3 p-3 text-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2" style="cursor: pointer;" onclick="toggleOrderDetails(<?= $ord['id'] ?>)">
                                        <div>
                                            <span class="fw-bold text-gold">Order #<?= $ord['id'] ?> - <?= htmlspecialchars($ord['customer_name']) ?></span>
                                            <div class="small text-light">Placed: <?= $ord['created_at'] ?? 'Just now' ?></div>
                                        </div>
                                        <div>
                                            <span class="badge bg-warning text-dark me-2"><?= strtoupper($ord['order_status']) ?></span>
                                            <i class="fa-solid fa-chevron-down text-gold small"></i>
                                        </div>
                                    </div>

                                    <!-- Collapsible Detail View -->
                                    <div id="orderDetails<?= $ord['id'] ?>" class="d-none border-top border-secondary pt-2 mt-2 small text-light">
                                        <p class="mb-1"><strong>Delivery Area:</strong> <?= htmlspecialchars($ord['delivery_area']) ?></p>
                                        <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars($ord['address']) ?></p>
                                        <p class="mb-1"><strong>Contact:</strong> <?= htmlspecialchars($ord['phone']) ?></p>
                                        <p class="mb-2 text-gold"><strong>Items Ordered:</strong></p>
                                        <ul class="ps-3 mb-2 text-light">
                                            <?php foreach ($ord['items'] as $it): ?>
                                                <li><?= htmlspecialchars($it['item_name']) ?> (x<?= $it['quantity'] ?>) - ₱<?= number_format($it['price'] * $it['quantity'], 2) ?> 
                                                    <?php if(!empty($it['egg_choice'])): ?><span class="text-gold">[Egg: <?= htmlspecialchars($it['egg_choice']) ?>]</span><?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <p class="mb-0 fw-bold text-gold">Total Amount: ₱<?= number_format($ord['total_amount'], 2) ?></p>
                                    </div>

                                    <div class="form-check form-switch mt-2 border-top border-secondary pt-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="receivedOrder<?= $ord['id'] ?>" onchange="markOrderCompleted(<?= $ord['id'] ?>)">
                                        <label class="form-check-label text-light small fw-semibold" for="receivedOrder<?= $ord['id'] ?>">Toggle when you've already gotten your order</label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 3: PAST ORDERS -->
                    <div id="tab-past" class="cart-tab-content d-none">
                        <?php if (!$is_logged_in): ?>
                            <p class="text-light text-center my-4">Please <a href="#" class="text-gold" onclick="openAuthModal('login')">login</a> to view your past orders.</p>
                        <?php elseif (empty($past_orders)): ?>
                            <p class="text-light text-center my-4">No past completed orders found.</p>
                        <?php else: ?>
                            <?php foreach ($past_orders as $ord): ?>
                                <div class="card bg-dark border border-secondary mb-3 p-3 text-light">
                                    <div class="d-flex justify-content-between align-items-center mb-1" style="cursor: pointer;" onclick="toggleOrderDetails(<?= $ord['id'] ?>)">
                                        <div>
                                            <span class="fw-bold text-light">Order #<?= $ord['id'] ?> - <?= htmlspecialchars($ord['customer_name']) ?></span>
                                            <div class="small text-light">Placed: <?= $ord['created_at'] ?? 'Recently' ?></div>
                                        </div>
                                        <div>
                                            <span class="badge bg-success me-2">COMPLETED</span>
                                            <i class="fa-solid fa-chevron-down text-gold small"></i>
                                        </div>
                                    </div>

                                    <!-- Collapsible Detail View with High Contrast Text -->
                                    <div id="orderDetails<?= $ord['id'] ?>" class="d-none border-top border-secondary pt-2 mt-2 small text-light">
                                        <p class="mb-1"><strong>Received Time:</strong> <?= $ord['updated_at'] ?? $ord['created_at'] ?? 'Completed' ?></p>
                                        <p class="mb-1"><strong>Delivery Area:</strong> <?= htmlspecialchars($ord['delivery_area']) ?></p>
                                        <p class="mb-2 text-gold"><strong>Order Summary:</strong></p>
                                        <ul class="ps-3 mb-2 text-light">
                                            <?php foreach ($ord['items'] as $it): ?>
                                                <li><?= htmlspecialchars($it['item_name']) ?> (x<?= $it['quantity'] ?>) - ₱<?= number_format($it['price'] * $it['quantity'], 2) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <p class="mb-0 fw-bold text-gold">Total Paid: ₱<?= number_format($ord['total_amount'], 2) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cart = [];
        let currentItemData = { name: '', price: 0, hasEgg: false, description: '' };
        const isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;

        const itemModal = new bootstrap.Modal(document.getElementById('itemModal'));
        const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
        const authModal = new bootstrap.Modal(document.getElementById('authModal'));

        const itemDescriptions = {
            'Tapsilog': 'Marinated Beef Tapa, Garlic Rice, Fried Egg.',
            'Malingsilog': 'Fried Luncheon Meat, Garlic Rice, Fried Egg.',
            'Liemposilog': 'Grilled Pork Belly, Garlic Rice, Fried Egg.',
            'Bagnetsilog': 'Crispy Pork Bagnet, Garlic Rice, Fried Egg.',
            'Tocilog': 'Sweet Pork Tocino, Garlic Rice, Fried Egg.',
            'Hungariansilog': 'Hungarian Sausage, Garlic Rice, Fried Egg.',
            'Hotdogsilog': 'Juicy Hotdog, Garlic Rice, Fried Egg.',
            'Sisigsilog': 'Savory Pork Sisig, Garlic Rice, Fried Egg.',
            'Bagnet + Hungarian': 'Crispy Bagnet, Hungarian Sausage, Rice, Egg.',
            'Tapa + Hungarian': 'Beef Tapa, Hungarian Sausage, Rice, Egg.',
            'Tapa + Bagnet Silog': 'Beef Tapa, Crispy Bagnet, Rice, Egg.',
            'Liempo + Hungarian': 'Grilled Liempo, Hungarian Sausage, Rice, Egg.',
            'Sisig + Hungarian': 'Pork Sisig, Hungarian Sausage, Rice, Egg.',
            'Special Tapa': 'Ala Carte Portion Marinated Beef Tapa.',
            'Special Bagnet': 'Ala Carte Serving Crispy Pork Bagnet.',
            'Special Sisig': 'Ala Carte Serving Savory Pork Sisig.',
            'Hungarian Sausage': 'Single Hungarian Sausage Portion.',
            'Plain Rice': 'Steamed White Rice.',
            'Garlic Rice': 'Garlic Fried Rice.',
            'Extra Egg': 'Fried Egg (Sunny Side Up / Well Done / Scrambled).'
        };

        function openItemModal(name, price, hasEgg) {
            currentItemData = { name, price, hasEgg, description: itemDescriptions[name] || 'Delicious meal item.' };
            
            document.getElementById('modalItemName').innerText = name;
            document.getElementById('modalItemDescription').innerText = currentItemData.description;
            document.getElementById('modalItemPrice').innerText = price;
            document.getElementById('modalItemQty').value = 1;
            document.getElementById('modalItemNotes').value = '';

            const eggSection = document.getElementById('eggSection');
            if (hasEgg) {
                eggSection.style.display = 'block';
                document.getElementById('eggSunny').checked = true;
            } else {
                eggSection.style.display = 'none';
            }

            itemModal.show();
        }

        function adjustModalQty(amount) {
            const qtyInput = document.getElementById('modalItemQty');
            let val = parseInt(qtyInput.value) + amount;
            if (val < 1) val = 1;
            qtyInput.value = val;
        }

        function addCurrentItemToCart() {
            const qty = parseInt(document.getElementById('modalItemQty').value);
            const notes = document.getElementById('modalItemNotes').value.trim();
            let egg = '';

            if (currentItemData.hasEgg) {
                const selectedEgg = document.querySelector('input[name="eggChoice"]:checked');
                if (selectedEgg) {
                    egg = selectedEgg.value;
                }
            }

            cart.push({
                name: currentItemData.name,
                price: currentItemData.price,
                qty: qty,
                egg: egg,
                notes: notes
            });

            updateCartUI();
            itemModal.hide();

            let orderWord = qty === 1 ? 'order' : 'orders';
            showToast(`You added ${qty} ${orderWord} of ${currentItemData.name}`);
        }

        function showToast(message) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'custom-toast';
            toast.innerText = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('fade-out');
                setTimeout(() => toast.remove(), 1000);
            }, 3000);
        }

        function showAuthToast(message) {
            const container = document.getElementById('auth-toast-container');
            const toast = document.createElement('div');
            toast.className = 'custom-toast';
            toast.innerText = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('fade-out');
                setTimeout(() => toast.remove(), 1000);
            }, 3000);
        }

        function updateCartUI() {
            const countSpan = document.getElementById('cart-count');
            const listDiv = document.getElementById('cart-items-list');
            const totalSpan = document.getElementById('cart-total');

            let totalQty = 0;
            let totalPrice = 0;
            listDiv.innerHTML = '';

            if (cart.length === 0) {
                listDiv.innerHTML = '<p class="text-light text-center my-3">Your cart is empty.</p>';
            } else {
                cart.forEach((item, index) => {
                    totalQty += item.qty;
                    let itemSubtotal = item.price * item.qty;
                    totalPrice += itemSubtotal;

                    let subTextParts = [];
                    if (item.egg) subTextParts.push(`Egg: ${item.egg}`);
                    if (item.notes) subTextParts.push(`Note: ${item.notes}`);
                    const subtextFormatted = subTextParts.length > 0 ? `<span class="subtext-info">${subTextParts.join(' | ')}</span>` : '';

                    const row = document.createElement('div');
                    row.className = 'cart-item-card d-flex justify-content-between align-items-center';
                    row.innerHTML = `
                        <div>
                            <h6 class="fw-bold text-white mb-0">${item.name}</h6>
                            ${subtextFormatted}
                            <div class="text-gold small fw-bold mt-1">
                                ₱${item.price.toFixed(2)} x ${item.qty} = ₱${itemSubtotal.toFixed(2)}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="qty-btn" onclick="updateQty(${index}, -1)">-</button>
                            <span class="text-white fw-bold px-1 small">${item.qty}</span>
                            <button class="qty-btn" onclick="updateQty(${index}, 1)">+</button>
                            <button class="btn btn-outline-danger btn-sm ms-2" onclick="removeFromCart(${index}, true)" title="Delete Item"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    `;

                    attachSlideToDelete(row, index);
                    listDiv.appendChild(row);
                });
            }

            countSpan.innerText = totalQty;
            totalSpan.innerText = totalPrice.toFixed(2);
        }

        function updateQty(index, delta) {
            cart[index].qty += delta;
            if (cart[index].qty <= 0) {
                cart.splice(index, 1);
            }
            updateCartUI();
        }

        function removeFromCart(index, manual = false) {
            let removedItem = cart[index];
            cart.splice(index, 1);
            updateCartUI();
            if (manual && removedItem) {
                showToast(`Removed ${removedItem.name} from cart`);
            }
        }

        function attachSlideToDelete(element, index) {
            let startX = 0;
            let currentX = 0;
            let isDragging = false;

            element.addEventListener('mousedown', (e) => {
                if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
                isDragging = true;
                startX = e.clientX;
            });

            element.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                currentX = e.clientX - startX;
                if (currentX < 0) {
                    element.style.transform = `translateX(${currentX}px)`;
                }
            });

            element.addEventListener('mouseup', () => {
                if (!isDragging) return;
                isDragging = false;
                let threshold = element.offsetWidth / 3;
                if (currentX < -threshold) {
                    removeFromCart(index, true);
                } else {
                    element.style.transform = 'translateX(0)';
                }
            });

            element.addEventListener('mouseleave', () => {
                if (isDragging) {
                    isDragging = false;
                    element.style.transform = 'translateX(0)';
                }
            });

            element.addEventListener('touchstart', (e) => {
                if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
                startX = e.touches[0].clientX;
            });

            element.addEventListener('touchmove', (e) => {
                currentX = e.touches[0].clientX - startX;
                if (currentX < 0) {
                    element.style.transform = `translateX(${currentX}px)`;
                }
            });

            element.addEventListener('touchend', () => {
                let threshold = element.offsetWidth / 3;
                if (currentX < -threshold) {
                    removeFromCart(index, true);
                } else {
                    element.style.transform = 'translateX(0)';
                }
            });
        }

        function toggleCartModal() {
            cartModal.show();
        }

        function switchCartTab(tabName) {
            document.querySelectorAll('.cart-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.cart-tab-content').forEach(content => content.classList.add('d-none'));

            document.getElementById(`tab-btn-${tabName}`).classList.add('active');
            document.getElementById(`tab-${tabName}`).classList.remove('d-none');
        }

        function toggleOrderDetails(orderId) {
            const detailDiv = document.getElementById(`orderDetails${orderId}`);
            if (detailDiv) {
                detailDiv.classList.toggle('d-none');
            }
        }

        // Auth Modal Controls
        function openAuthModal(mode) {
            switchAuthMode(mode);
            authModal.show();
        }

        function switchAuthMode(mode) {
            const loginForm = document.getElementById('loginForm');
            const signupForm = document.getElementById('signupForm');
            const title = document.getElementById('authModalTitle');

            if (mode === 'login') {
                loginForm.classList.remove('d-none');
                signupForm.classList.add('d-none');
                title.innerText = 'Login to Your Account';
            } else {
                loginForm.classList.add('d-none');
                signupForm.classList.remove('d-none');
                title.innerText = 'Create an Account';
            }
        }

        function submitAuthForm(event, action) {
            event.preventDefault();
            const formData = new FormData();
            formData.append('action', action);

            if (action === 'login') {
                formData.append('email', document.getElementById('loginEmail').value.trim());
                formData.append('password', document.getElementById('loginPassword').value);
            } else {
                formData.append('fullname', document.getElementById('signupFullname').value.trim());
                formData.append('email', document.getElementById('signupEmail').value.trim());
                formData.append('password', document.getElementById('signupPassword').value);
            }

            fetch('menu.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    authModal.hide();
                    let successMsg = action === 'login' ? 'Logged in successfully!' : 'Account created & logged in successfully!';
                    showAuthToast(successMsg);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Authentication request failed.');
            });
        }

        function confirmLogout() {
            if (confirm('Are you sure you really want to log out?')) {
                const formData = new FormData();
                formData.append('action', 'logout');

                fetch('menu.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAuthToast('Logged out successfully!');
                        setTimeout(() => location.reload(), 1500);
                    }
                });
            }
        }

        function proceedToCheckoutForm() {
            if (!isLoggedIn) {
                cartModal.hide();
                alert('Please log in or sign up first before placing an order.');
                openAuthModal('login');
                return;
            }

            if (cart.length === 0) {
                alert('Your cart is empty!');
                return;
            }
            document.getElementById('checkout-section').classList.add('d-none');
            document.getElementById('payment-delivery-form').classList.remove('d-none');
        }

        function submitFinalOrder() {
            const name = document.getElementById('custName').value.trim();
            const phone = document.getElementById('custPhone').value.trim();
            const address = document.getElementById('custAddress').value.trim();
            const proofInput = document.getElementById('paymentProof');

            if (!name || !phone || !address) {
                alert('Please provide your customer name, contact number, and address.');
                return;
            }

            if (proofInput.files.length === 0) {
                alert('Please upload your GCash payment screenshot proof.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'place_order');
            formData.append('customer_name', name);
            formData.append('phone', phone);
            formData.append('delivery_area', document.getElementById('custArea').value.trim() || 'Taguig');
            formData.append('address', address);
            formData.append('total_amount', document.getElementById('cart-total').innerText);
            formData.append('cart_json', JSON.stringify(cart));
            formData.append('payment_proof', proofInput.files[0]);

            fetch('menu.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Order finished and submitted successfully! Order ID: #' + data.order_id);
                    cart = [];
                    updateCartUI();
                    location.reload();
                } else {
                    alert('Failed to submit order: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred while submitting your order.');
            });
        }

        function markOrderCompleted(orderId) {
            const formData = new FormData();
            formData.append('action', 'complete_order');
            formData.append('order_id', orderId);

            fetch('menu.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Order marked as received and completed!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Error updating order status.');
                }
            })
            .catch(err => console.error(err));
        }
    </script>
</body>
</html>