<?php
session_start();

// Redirect user to homepage if they are not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: homepage.php?auth_required=1');
    exit;
}

require_once 'config/db.php';

// Ensure all necessary columns exist in website_orders table safely
try {
    $pdo->exec("ALTER TABLE website_orders ADD COLUMN user_id INT DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE website_orders ADD COLUMN queue_number VARCHAR(20) DEFAULT 'W-001'");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE website_orders ADD COLUMN food_subtotal DECIMAL(10,2) DEFAULT 0.00, ADD COLUMN tip_amount DECIMAL(10,2) DEFAULT 0.00");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE website_orders ADD COLUMN inventory_deducted INT DEFAULT 0");
} catch (PDOException $e) {}

// --- AJAX / POST HANDLERS (Website Orders Only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // 1. Place Order Handler
    if ($_POST['action'] === 'place_order') {
        try {
            global $pdo;
            $user_id = $_SESSION['user_id'] ?? NULL;
            $customer_name = $_POST['customer_name'] ?? 'Guest';
            $phone = $_POST['phone'] ?? '';
            $delivery_area = $_POST['delivery_area'] ?? 'Taguig';
            $address = $_POST['address'] ?? 'N/A';
            $food_subtotal = floatval($_POST['food_subtotal'] ?? 0);
            $tip_amount = floatval($_POST['tip_amount'] ?? 0);
            $total_amount = $food_subtotal + $tip_amount;
            $cart = json_decode($_POST['cart_json'], true);

            if (empty($cart) || !is_array($cart)) {
                echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
                exit;
            }

            // Comprehensive Stock Validation Check
            $requiredIngredients = [];
            foreach ($cart as $item) {
                $itemName = strtolower($item['name'] ?? '');
                $reqQty = intval($item['qty'] ?? 1);
                
                if (strpos($itemName, 'tapa') !== false || strpos($itemName, 'tapsilog') !== false) {
                    $requiredIngredients['Tapa Portion'] = ($requiredIngredients['Tapa Portion'] ?? 0) + $reqQty;
                }
                if (strpos($itemName, 'bagnet') !== false) {
                    $requiredIngredients['Pork Bagnet'] = ($requiredIngredients['Pork Bagnet'] ?? 0) + $reqQty;
                }
                if (strpos($itemName, 'sisig') !== false) {
                    $requiredIngredients['Sisig Portion'] = ($requiredIngredients['Sisig Portion'] ?? 0) + $reqQty;
                }
                if (strpos($itemName, 'liempo') !== false || strpos($itemName, 'porkchop') !== false) {
                    $requiredIngredients['Porkchop Portion'] = ($requiredIngredients['Porkchop Portion'] ?? 0) + $reqQty;
                }
                if (strpos($itemName, 'tocino') !== false) {
                    $requiredIngredients['Tocino Portion'] = ($requiredIngredients['Tocino Portion'] ?? 0) + $reqQty;
                }
                if (strpos($itemName, 'hungarian') !== false) {
                    $requiredIngredients['Hungarian Sausage'] = ($requiredIngredients['Hungarian Sausage'] ?? 0) + $reqQty;
                }
                if (strpos($itemName, 'hotdog') !== false) {
                    $requiredIngredients['Hotdog'] = ($requiredIngredients['Hotdog'] ?? 0) + $reqQty;
                }
                if (strpos($itemName, 'maling') !== false) {
                    $requiredIngredients['Maling'] = ($requiredIngredients['Maling'] ?? 0) + $reqQty;
                }
                if (strpos($itemName, 'silog') !== false || strpos($itemName, 'combo') !== false || strpos($itemName, 'rice') !== false) {
                    $requiredIngredients['Garlic Rice (Portions)'] = ($requiredIngredients['Garlic Rice (Portions)'] ?? 0) + $reqQty;
                }
                if ((strpos($itemName, 'silog') !== false || strpos($itemName, 'combo') !== false || strpos($itemName, 'egg') !== false) && (!isset($item['egg']) || $item['egg'] !== 'No Egg')) {
                    $requiredIngredients['Eggs (Fresh Batch)'] = ($requiredIngredients['Eggs (Fresh Batch)'] ?? 0) + $reqQty;
                }
            }

            foreach ($requiredIngredients as $ingName => $neededQty) {
                $stockStmt = $pdo->prepare("SELECT name, stock FROM inventory_items WHERE name = ? LIMIT 1");
                $stockStmt->execute([$ingName]);
                $invRow = $stockStmt->fetch(PDO::FETCH_ASSOC);

                if ($invRow) {
                    $availableStock = intval($invRow['stock']);
                    if ($availableStock < $neededQty) {
                        echo json_encode(['success' => false, 'message' => "Stock Alert: Insufficient stock for {$ingName}! Required: {$neededQty}, Available: {$availableStock}."]);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => "Database Error: Inventory item '{$ingName}' not found."]);
                    exit;
                }
            }

            $pdo->beginTransaction();

            // Generate daily queue number W-001, W-002, etc.
            $stmt_q = $pdo->query("SELECT COUNT(*) as count FROM website_orders WHERE DATE(created_at) = CURDATE()");
            $row_q = $stmt_q->fetch(PDO::FETCH_ASSOC);
            $seq = ($row_q['count'] ?? 0) + 1;
            $queue_number = 'W-' . str_pad($seq, 3, '0', STR_PAD_LEFT);

            $payment_proof_path = '';
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $filename = 'proof_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $payment_proof_path = $upload_dir . $filename;
                move_uploaded_file($_FILES['payment_proof']['tmp_name'], $payment_proof_path);
            }

            // Insert into website_orders table with user_id and inventory_deducted = 1
            $stmt = $pdo->prepare("INSERT INTO website_orders (user_id, customer_name, phone, delivery_area, address, queue_number, food_subtotal, tip_amount, total_amount, payment_method, payment_status, order_status, payment_proof, inventory_deducted, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'GCash', 'paid', 'preparing', ?, 1, NOW())");
            $stmt->execute([$user_id, $customer_name, $phone, $delivery_area, $address, $queue_number, $food_subtotal, $tip_amount, $total_amount, $payment_proof_path]);
            $order_id = $pdo->lastInsertId();

            // Insert into website_order_items table
            $stmt_item = $pdo->prepare("INSERT INTO website_order_items (order_id, item_name, egg_choice, notes, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
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

            // Deduct actual stock from inventory_items table
            if (!empty($requiredIngredients)) {
                $updateStockStmt = $pdo->prepare("UPDATE inventory_items SET stock = stock - ? WHERE name = ?");
                foreach ($requiredIngredients as $ingName => $neededQty) {
                    $updateStockStmt->execute([$neededQty, $ingName]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'order_id' => $order_id, 'queue_number' => $queue_number]);
            exit;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    // 2. Complete Order Handler
    if ($_POST['action'] === 'complete_order') {
        try {
            global $pdo;
            $order_id = intval($_POST['order_id']);
            $stmt = $pdo->prepare("UPDATE website_orders SET order_status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$order_id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Fetch active & completed website orders for public display in modal tabs
$ongoing_orders = [];
$past_orders = [];

try {
    global $pdo;
    if (isset($pdo)) {
        $ongoing_raw = $pdo->query("SELECT * FROM website_orders WHERE order_status != 'completed' ORDER BY id DESC LIMIT 20");
        foreach ($ongoing_raw->fetchAll(PDO::FETCH_ASSOC) as $ord) {
            $items_stmt = $pdo->prepare("SELECT * FROM website_order_items WHERE order_id = ?");
            $items_stmt->execute([$ord['id']]);
            $ord['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            $ongoing_orders[] = $ord;
        }

        $past_raw = $pdo->query("SELECT * FROM website_orders WHERE order_status = 'completed' ORDER BY id DESC LIMIT 10");
        foreach ($past_raw->fetchAll(PDO::FETCH_ASSOC) as $ord) {
            $items_stmt = $pdo->prepare("SELECT * FROM website_order_items WHERE order_id = ?");
            $items_stmt->execute([$ord['id']]);
            $ord['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            $past_orders[] = $ord;
        }
    }
} catch (Exception $e) {
    // Graceful fallback if database connection is temporary offline
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sairene TephTeph Tapsilogan - Digital Menu</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        body {
            background-color: #2b1d0c;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 30px 20px;
            position: relative;
            min-height: 100vh;
        }

        /* Fixed Fullscreen Wooden Texture Overlay */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(0, 0, 0, 0.25), rgba(0, 0, 0, 0.35)), url('images/Wood .jpg') no-repeat center center;
            background-size: cover;
            z-index: -1;
        }

        /* Top Navigation Box */
        .top-nav-box {
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 14px 24px;
            max-width: 950px;
            margin: 0 auto 30px auto;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            position: relative;
            z-index: 1090;
        }

        .nav-links {
            display: flex;
            justify-content: center;
            gap: 35px;
            list-style: none;
            padding: 0;
            margin: 0;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #ffffff;
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            transition: all 0.25s ease;
            padding: 6px 16px;
            border-radius: 50px;
            border: 1px solid transparent;
        }

        .nav-links a:hover, .nav-links a.active {
            color: #121212;
            background-color: #ffc107;
            border-color: #d4a000;
            box-shadow: 0 4px 10px rgba(255, 193, 7, 0.4);
        }

        /* Bring Modals Forward Above Nav Bar */
        .modal {
            z-index: 2000 !important;
        }
        .modal-backdrop {
            z-index: 1999 !important;
        }
        
        /* Ensure specific modal priority if multiple are open */
        #popupModal {
            z-index: 2050 !important;
        }
        #popupModal + .modal-backdrop {
            z-index: 2049 !important;
        }

        /* Main Wrapper Container */
        .main-wrapper-box {
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 2px solid #ffc107;
            border-radius: 16px;
            padding: 40px 30px;
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .page-title {
            color: #ffc107;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
        }

        .btn-gold {
            background: linear-gradient(180deg, #ffca28 0%, #ffb300 100%);
            color: #121212;
            border: 2px solid #d4a000;
            font-weight: bold;
            border-radius: 50px;
            padding: 8px 22px;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .btn-gold:hover {
            background: linear-gradient(180deg, #ffc107 0%, #ffa000 100%);
            color: #121212;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-gold.active {
            background: #212529;
            color: #ffc107;
            border-color: #ffc107;
            box-shadow: 0 0 12px rgba(255, 193, 7, 0.6);
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
            border-radius: 14px;
            border: 3px solid #ffc107;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            background-color: transparent;
        }

        .menu-board-img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 11px;
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

        .hotspot:hover, .hotspot:focus {
            box-shadow: 0 0 18px rgba(255, 193, 7, 0.9);
            transform: scale(1.05);
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
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            font-weight: bold;
        }

        #toast-container {
            position: fixed;
            bottom: 25px;
            right: 25px;
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

        .cart-modal-content {
            background: #1a1a1a !important;
            border: 3px solid #ffc107;
            color: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .cart-tab-btn {
            flex: 1;
            text-align: center;
            border-radius: 8px;
            padding: 10px;
            color: #ffffff;
            background: #2a2a2a;
            border: 1px solid #ffc107;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .cart-tab-btn.active {
            background: #ffc107;
            color: #121212;
            font-weight: 800;
            border-color: #d4a000;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.4);
        }

        .cart-item-card {
            background: #252525;
            border: 1px solid #ffc107;
            border-radius: 10px;
            margin-bottom: 8px;
            padding: 12px;
            user-select: none;
            touch-action: pan-y;
            transition: transform 0.2s ease-out;
            position: relative;
        }

        .subtext-info {
            font-size: 0.78rem;
            color: #b0b0b0;
            display: block;
            margin-top: 2px;
        }

        .qty-btn {
            background: transparent;
            border: 1px solid #ffc107;
            color: #ffc107;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-weight: bold;
        }

        /* Tip Jar Animations */
        .jar-wrap{width:120px;height:140px;margin:0 auto;filter:drop-shadow(0 6px 5px rgba(0,0,0,0.5));position:relative}
        .jar-svg{width:100%;height:100%;overflow:visible}
        .jar-fill{transition:y .55s cubic-bezier(.2,.8,.2,1),height .55s cubic-bezier(.2,.8,.2,1),fill .3s}
        .jar-shine{animation:shine 3s ease-in-out infinite}
        .jar-bubble{transform-box:fill-box;transform-origin:center;animation:bubble 2.4s ease-in-out infinite}
        .falling-coin { animation: dropCoin 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards; }
        @keyframes dropCoin {
            0% { transform: translate(60px, -30px) scale(0.6) rotate(0deg); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: translate(60px, 70px) scale(1) rotate(360deg); opacity: 0; }
        }
        @keyframes shine { 0%,100%{opacity:.3} 50%{opacity:.8} }
        @keyframes bubble { 0%,100%{transform:translateY(0) scale(1);opacity:.4} 50%{transform:translateY(-12px) scale(1.2);opacity:.9} }

        .tip-chip {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid #ffc107;
            color: #ffffff;
            font-weight: bold;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tip-chip.active, .tip-chip:hover {
            background-color: #ffc107;
            color: #121212;
        }

        footer {
            text-align: center;
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 35px;
        }

        /*  TABLET VIEW  */
        @media (max-width: 992px) and (min-width: 601px) {
        
            .top-nav-box {
                max-width: 100% !important;            
                width: calc(100% - 40px) !important;    
                margin-bottom: 15px !important;        
                padding: 12px 20px !important;        
            }
        
            /* Nav links: magkakatabi, mas masikip ang pagitan */
            .nav-links {
                gap: 12px !important;                   /* pagitan ng links (base ay 35px) */
            }
        
            /* Bawat link: mas maliit para magkasya sa tablet */
            .nav-links a {
                font-size: 0.85rem !important;          /* mas maliit na text */
                padding: 6px 14px !important;           /* sikip na capsule sa hover/active */
                white-space: nowrap !important;         /* bawal mag-wrap ang text ng link */
            }
        
            /* Cart button: nasa pagitan ng nav box at main box, naka-center */
            .floating-cart-btn {
                position: static !important;            /* tanggal ang fixed: hindi na lumulutang o sumusunod sa scroll, at hindi na matatabunan ng nav */
                display: flex !important;               /* block-level flex para gumana ang margin auto (pang-center) */
                width: fit-content !important;          /* sakto lang sa laman ang lapad */
                margin: 0 auto 20px auto !important;    /* center + 20px gap papunta sa main box */
                padding: 8px 24px !important;           /* saktong laki ng button */
                font-size: 0.9rem !important;           /* saktong laki ng text */
            }
        }
        
        
        /*  PHONE VIEW  */
        @media (max-width: 600px) {
        
           
            .top-nav-box {
                max-width: 100% !important;             
                width: calc(100% - 16px) !important;    
                margin: 0 auto 15px auto !important;   
                padding: 8px 20px !important;         
                box-sizing: border-box !important;   
            }
        
            /* Nav links: kompakt, puwedeng bumaba kung sobrang liit ng screen */
            .nav-links {
                flex-wrap: wrap !important;             /* bababa ang link kapag hindi na kasya */
                gap: 5px !important;                    /* mas malapit na pagitan ng bawat capsule */
            }
        
            /* Bawat link: pinaliit para kasya lahat */
            .nav-links a {
                font-size: 0.7rem !important;           /* mas maliit na text */
                padding: 5px 7px !important;            /* mas manipis na capsule */
                white-space: nowrap !important;         /* bawal mag-wrap ang text ng link */
            }
        
            /* Cart button: nasa pagitan ng nav box at main box, naka-center */
            .floating-cart-btn {
                position: static !important;            /* tanggal ang fixed: hindi na lumulutang o sumusunod sa scroll, at hindi na matatabunan ng nav */
                display: flex !important;               /* block-level flex para gumana ang margin auto (pang-center) */
                width: fit-content !important;          /* sakto lang sa laman ang lapad */
                margin: 0 auto 20px auto !important;    /* center + 20px gap papunta sa main box */
                padding: 8px 24px !important;           /* saktong laki ng button */
                font-size: 0.9rem !important;           /* saktong laki ng text */
            }
        
            /* Main box: mas masikip ang padding */
            .main-wrapper-box {
                padding: 10px 10px !important;          /* maliit na padding sa loob ng main box */
            }
        
            /* Title ng page */
            .page-title {
                font-size: 1.8rem !important;           /* mas maliit na title */
            }
        
            /* Tagline sa ilalim ng title */
            header p {
                font-size: 0.68rem !important;          /* maliit na text */
            }
        
            /* Copyright sa footer */
            footer p {
                font-size: 0.72rem !important;          /* maliit na text */
                opacity: 0.75;                          /* medyo malabo para di agaw-pansin */
            }
        }



    </style>
</head>
<body>

    <!-- Top Navigation Box -->
    <nav class="top-nav-box">
        <ul class="nav-links">
            <li><a href="homepage.php" id="homeNavLink">HOME</a></li>
            <li><a href="menu.php" id="menuNavLink" class="active">MENU</a></li>
            <li><a href="order.php" id="orderNavLink">ORDER NOW</a></li>
            <li><a href="food_packs.php" id="foodPacksNavLink">FOOD PACKS</a></li>
            <li><a href="tambay_caps.php" id="tambayNavLink">TAMBAY CAPS</a></li>
        </ul>
    </nav>

    <!-- Sticky Upper Right Floating Cart Button -->
    <button class="btn btn-gold floating-cart-btn" onclick="toggleCartModal()">
        <i class="fa-solid fa-cart-shopping me-2"></i>Cart (<span id="cart-count">0</span>)
    </button>

    <!-- Lower Right Notification Container -->
    <div id="toast-container"></div>

    <!-- Main Wrapper Box -->
    <main class="main-wrapper-box">
        
        <!-- Header -->
        <header class="text-center mb-4">
            <h1 class="display-6 page-title">Sairene Tephteph Tapsilogan</h1>
            <p class="text-light small mt-1 fw-bold">A Taste of Comfort • Interactive Digital Menu</p>
        </header>

        <hr class="border-warning opacity-75 my-4">

        <!-- Instant Page Switch Buttons -->
        <div class="d-flex justify-content-center align-items-center gap-3 mb-4">
            <button type="button" id="btn-page-1" class="btn btn-gold active" onclick="switchMenuPage(1)" aria-label="Page 1">
                <i class="fa-solid fa-chevron-left fs-5"></i>
            </button>
            <button type="button" id="btn-page-2" class="btn btn-gold" onclick="switchMenuPage(2)" aria-label="Page 2">
                <i class="fa-solid fa-chevron-right fs-5"></i>
            </button>
        </div>

        <!-- Menu Container -->
        <div class="menu-wrapper">
            
            <!-- PAGE 1: SILOG MEALS -->
            <div id="menu-page-1" class="menu-board-container">
                <img src="images/menu_page_1.png" alt="Sairene Tapsilogan Silog Meals" class="img-fluid menu-board-img">

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

            <!-- PAGE 2: COMBO MEALS & ALA CARTE -->
            <div id="menu-page-2" class="menu-board-container d-none">
                <img src="images/menu_page_2.png" alt="Sairene Tapsilogan Combos and Ala Carte" class="img-fluid menu-board-img">

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

        </div>

    </main>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan &bull; Digital Menu</p>
    </footer>

    <!-- MIDDLE POPUP MODAL FOR ITEM CUSTOMIZATION -->
    <div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content cart-modal-content p-2">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title fw-bold text-warning" id="modalItemName">Dish Name</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-light mb-2"><strong>Ingredients:</strong> <span id="modalItemDescription">Description of ingredients...</span></p>
                    <p class="fs-5 text-warning fw-bold">Price: ₱<span id="modalItemPrice">0</span></p>
                    
                    <!-- Choose Your Egg Section -->
                    <div class="mb-3" id="eggSection">
                        <label class="form-label fw-semibold text-warning">Choose Your Egg:</label>
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
                        <label class="form-label fw-semibold text-warning">Quantity</label>
                        <div class="input-group" style="width: 140px;">
                            <button class="btn btn-outline-warning btn-sm text-light" type="button" onclick="adjustModalQty(-1)">-</button>
                            <input type="text" id="modalItemQty" class="form-control bg-dark text-light text-center border-secondary form-control-sm" value="1" readonly>
                            <button class="btn btn-outline-warning btn-sm text-light" type="button" onclick="adjustModalQty(1)">+</button>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-warning">Notes / Special Instructions</label>
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

    <!-- GENERIC POPUP MODAL FOR ALERTS & SUCCESS -->
    <div class="modal fade" id="popupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content cart-modal-content p-3 text-center">
                <div class="modal-header border-0 pb-0 justify-content-center">
                    <h5 class="modal-title fw-bold text-warning" id="popupModalTitle">Notification</h5>
                </div>
                <div class="modal-body">
                    <p id="popupModalMessage" class="text-light mb-0">Message goes here...</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-gold px-4" data-bs-dismiss="modal" id="popupModalBtn">Okay</button>
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
                        <p class="text-muted small mb-2"><i class="fa-solid fa-info-circle me-1"></i> Tip: Slide item left by 1/3 or use the delete button to remove.</p>
                        <div id="cart-items-list" class="mb-3" style="max-height: 220px; overflow-y: auto;">
                            <!-- Dynamic Cart Items -->
                        </div>
                        
                        <!-- TIP JAR SECTION -->
                        <div class="p-3 bg-dark border border-warning rounded mb-3">
                            <div class="row align-items-center g-3">
                                <div class="col-4 text-center">
                                    <div class="jar-wrap">
                                        <svg class="jar-svg" viewBox="0 0 120 145" role="img" aria-label="Animated gold tip jar">
                                            <defs>
                                                <linearGradient id="goldLiquid" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#fde047"/>
                                                    <stop offset="100%" stop-color="#ca8a04"/>
                                                </linearGradient>
                                                <clipPath id="jarClip">
                                                    <path d="M31 27 Q22 29 22 42 L22 119 Q22 132 38 132 H82 Q98 132 98 119 L98 42 Q98 29 89 27 Z"/>
                                                </clipPath>
                                            </defs>
                                            <path d="M36 18 H84 Q89 18 89 23 T84 28 H36 Q31 28 31 23 T36 18 Z" fill="#d4d4d4" stroke="#737373" stroke-width="2"/>
                                            <path d="M31 27 Q22 29 22 42 L22 119 Q22 132 38 132 H82 Q98 132 98 119 L98 42 Q98 29 89 27 Z" fill="rgba(255,255,255,0.06)" stroke="#facc15" stroke-width="3"/>
                                            <g clip-path="url(#jarClip)">
                                                <rect id="jarFill" x="22" y="132" width="76" height="0" fill="url(#goldLiquid)" class="jar-fill"/>
                                                <circle class="jar-bubble" cx="42" cy="113" r="3" fill="#fff3bd"/>
                                                <circle class="jar-bubble" cx="75" cy="91" r="2.5" fill="#fff3bd" style="animation-delay:.7s"/>
                                                <circle class="jar-bubble" cx="57" cy="72" r="2" fill="#fff3bd" style="animation-delay:1.2s"/>
                                            </g>
                                            <g id="coinDropContainer"></g>
                                            <path d="M37 39 L37 106" stroke="#fff" stroke-width="5" stroke-linecap="round" class="jar-shine"/>
                                            <path d="M31 27 Q22 29 22 42 L22 119 Q22 132 38 132 H82 Q98 132 98 119 L98 42 Q98 29 89 27 Z" fill="none" stroke="#ca8a04" stroke-width="2.5"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="col-8">
                                    <label for="tipSlider" class="fw-bold text-warning small mb-1 d-flex justify-content-between">
                                        <span>Support the Kitchen Tip Jar</span>
                                        <span id="tipDisplay" class="font-monospace">₱0.00</span>
                                    </label>
                                    <input type="range" id="tipSlider" class="form-range" min="0" max="200" step="5" value="0" oninput="setTip(this.value)">
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        <button type="button" class="tip-chip active" onclick="setTip(0)">₱0</button>
                                        <button type="button" class="tip-chip" onclick="setTip(20)">₱20</button>
                                        <button type="button" class="tip-chip" onclick="setTip(50)">₱50</button>
                                        <button type="button" class="tip-chip" onclick="setTip(100)">₱100</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-top border-secondary pt-3">
                            <div class="d-flex justify-content-between fs-6 text-light mb-1">
                                <span>Food Subtotal:</span>
                                <span>₱<span id="cart-food-subtotal">0.00</span></span>
                            </div>
                            <div class="d-flex justify-content-between fs-6 text-light mb-1">
                                <span>Kitchen Tip:</span>
                                <span class="text-warning">₱<span id="cart-tip-amount">0.00</span></span>
                            </div>
                            <div class="d-flex justify-content-between fs-4 fw-bold text-warning mb-3">
                                <span>Total Bill:</span>
                                <span>₱<span id="cart-total">0.00</span></span>
                            </div>

                            <div id="checkout-section">
                                <button type="button" class="btn btn-gold w-100 fw-bold py-2" onclick="proceedToCheckoutForm()">Order Now</button>
                            </div>

                            <div id="payment-delivery-form" class="d-none mt-3 p-3 bg-dark border border-warning rounded">
                                <h6 class="fw-bold text-warning mb-3"><i class="fa-solid fa-wallet me-2"></i>Checkout & GCash Payment</h6>
                                
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
                                        <label class="form-label text-light small mb-1">Upload GCash Payment Screenshot Proof</label>
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
                        <?php if (empty($ongoing_orders)): ?>
                            <p class="text-muted text-center my-4">No ongoing active orders found.</p>
                        <?php else: ?>
                            <?php foreach ($ongoing_orders as $ord): ?>
                                <div class="card bg-dark border border-warning mb-3 p-3 text-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2" style="cursor: pointer;" onclick="toggleOrderDetails(<?= $ord['id'] ?>)">
                                        <div>
                                            <span class="badge bg-warning text-dark me-1"><?= htmlspecialchars($ord['queue_number'] ?? 'W-001') ?></span>
                                            <span class="fw-bold text-warning">Order #<?= $ord['id'] ?> - <?= htmlspecialchars($ord['customer_name']) ?></span>
                                            <div class="small text-muted">Placed: <?= $ord['created_at'] ?? 'Just now' ?></div>
                                        </div>
                                        <div>
                                            <span class="badge bg-warning text-dark me-2"><?= strtoupper($ord['order_status']) ?></span>
                                            <i class="fa-solid fa-chevron-down text-warning small"></i>
                                        </div>
                                    </div>

                                    <div id="orderDetails<?= $ord['id'] ?>" class="d-none border-top border-warning pt-2 mt-2 small text-light">
                                        <p class="mb-1"><strong>Delivery Area:</strong> <?= htmlspecialchars($ord['delivery_area']) ?></p>
                                        <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars($ord['address']) ?></p>
                                        <p class="mb-1"><strong>Contact:</strong> <?= htmlspecialchars($ord['phone']) ?></p>
                                        <p class="mb-2 text-warning fw-bold">Items Ordered:</p>
                                        <ul class="ps-3 mb-2 text-light">
                                            <?php foreach ($ord['items'] as $it): ?>
                                                <li><?= htmlspecialchars($it['item_name']) ?> (x<?= $it['quantity'] ?>) - ₱<?= number_format($it['price'] * $it['quantity'], 2) ?> 
                                                    <?php if(!empty($it['egg_choice'])): ?><span class="text-muted">[Egg: <?= htmlspecialchars($it['egg_choice']) ?>]</span><?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if (isset($ord['tip_amount']) && $ord['tip_amount'] > 0): ?>
                                            <p class="mb-1 text-light">Food Subtotal: ₱<?= number_format($ord['food_subtotal'] ?? $ord['total_amount'] - $ord['tip_amount'], 2) ?></p>
                                            <p class="mb-1 text-warning">Kitchen Tip: ₱<?= number_format($ord['tip_amount'], 2) ?></p>
                                        <?php endif; ?>
                                        <p class="mb-0 fw-bold text-warning">Total Amount: ₱<?= number_format($ord['total_amount'], 2) ?></p>
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
                        <?php if (empty($past_orders)): ?>
                            <p class="text-muted text-center my-4">No past completed orders found.</p>
                        <?php else: ?>
                            <?php foreach ($past_orders as $ord): ?>
                                <div class="card bg-dark border border-secondary mb-3 p-3 text-light">
                                    <div class="d-flex justify-content-between align-items-center mb-1" style="cursor: pointer;" onclick="toggleOrderDetails(<?= $ord['id'] ?>)">
                                        <div>
                                            <span class="badge bg-secondary text-white me-1"><?= htmlspecialchars($ord['queue_number'] ?? 'W-001') ?></span>
                                            <span class="fw-bold text-warning">Order #<?= $ord['id'] ?> - <?= htmlspecialchars($ord['customer_name']) ?></span>
                                            <div class="small text-muted">Placed: <?= $ord['created_at'] ?? 'Recently' ?></div>
                                        </div>
                                        <div>
                                            <span class="badge bg-success me-2">COMPLETED</span>
                                            <i class="fa-solid fa-chevron-down text-warning small"></i>
                                        </div>
                                    </div>

                                    <div id="orderDetails<?= $ord['id'] ?>" class="d-none border-top border-warning pt-2 mt-2 small text-light">
                                        <p class="mb-1"><strong>Received Time:</strong> <?= $ord['updated_at'] ?? $ord['created_at'] ?? 'Completed' ?></p>
                                        <p class="mb-1"><strong>Delivery Area:</strong> <?= htmlspecialchars($ord['delivery_area']) ?></p>
                                        <p class="mb-2 text-warning fw-bold">Order Summary:</p>
                                        <ul class="ps-3 mb-2 text-light">
                                            <?php foreach ($ord['items'] as $it): ?>
                                                <li><?= htmlspecialchars($it['item_name']) ?> (x<?= $it['quantity'] ?>) - ₱<?= number_format($it['price'] * $it['quantity'], 2) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <p class="mb-0 fw-bold text-warning">Total Paid: ₱<?= number_format($ord['total_amount'], 2) ?></p>
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
        let tip = 0;
        let currentItemData = { name: '', price: 0, hasEgg: false, description: '' };

        const itemModal = new bootstrap.Modal(document.getElementById('itemModal'));
        const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
        const popupModal = new bootstrap.Modal(document.getElementById('popupModal'));

        function showPopupModal(title, message, callback = null) {
            document.getElementById('popupModalTitle').innerText = title;
            document.getElementById('popupModalMessage').innerText = message;
            const btn = document.getElementById('popupModalBtn');
            
            // Remove previous click handlers
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);

            newBtn.addEventListener('click', () => {
                popupModal.hide();
                if (callback) callback();
            });

            popupModal.show();
        }

        // Instant Menu Page Toggle Function
        function switchMenuPage(pageNum) {
            const page1 = document.getElementById('menu-page-1');
            const page2 = document.getElementById('menu-page-2');
            const btn1 = document.getElementById('btn-page-1');
            const btn2 = document.getElementById('btn-page-2');

            if (pageNum === 1) {
                page1.classList.remove('d-none');
                page2.classList.add('d-none');
                btn1.classList.add('active');
                btn2.classList.remove('active');
            } else {
                page1.classList.add('d-none');
                page2.classList.remove('d-none');
                btn1.classList.remove('active');
                btn2.classList.add('active');
            }
        }

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

        function subtotal() {
            return cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        }

        function money(amt) {
            return '₱' + amt.toFixed(2);
        }

        function triggerCoinDrop() {
            const container = document.getElementById('coinDropContainer');
            if (!container) return;
            for (let i = 0; i < 3; i++) {
                let xPos = 40 + Math.random() * 40;
                const coinG = document.createElementNS("http://www.w3.org/2000/svg", "g");
                coinG.setAttribute("class", "falling-coin");
                coinG.style.animationDelay = (i * 0.12) + "s";
                coinG.innerHTML = `<circle cx="${xPos}" cy="10" r="5" fill="#fde047" stroke="#b45309" stroke-width="1"/><circle cx="${xPos}" cy="10" r="2.5" fill="#fef08a"/>`;
                container.appendChild(coinG);
                setTimeout(() => coinG.remove(), 900);
            }
        }

        function setTip(v) {
            const oldTip = tip;
            tip = Math.max(0, Math.min(200, Math.round(Number(v) / 5) * 5));
            if (tip > oldTip) {
                triggerCoinDrop();
            }
            document.getElementById('tipSlider').value = tip;
            document.getElementById('tipDisplay').textContent = money(tip);

            // Liquid height calculation for tip jar SVG
            const h = (tip / 200) * 88;
            const y = 132 - h;
            const fill = document.getElementById('jarFill');
            if (fill) {
                fill.setAttribute('height', h);
                fill.setAttribute('y', y);
                fill.setAttribute('fill', 'url(#goldLiquid)');
            }

            document.querySelectorAll('.tip-chip').forEach(b => {
                b.classList.toggle('active', parseInt(b.textContent.replace('₱', '')) === tip);
            });

            updateCartUI();
        }

        function updateCartUI() {
            const countSpan = document.getElementById('cart-count');
            const listDiv = document.getElementById('cart-items-list');
            const foodSubtotalSpan = document.getElementById('cart-food-subtotal');
            const tipSpan = document.getElementById('cart-tip-amount');
            const totalSpan = document.getElementById('cart-total');

            let totalQty = 0;
            let foodTotal = subtotal();
            listDiv.innerHTML = '';

            if (cart.length === 0) {
                listDiv.innerHTML = '<p class="text-muted text-center my-3">Your cart is empty.</p>';
            } else {
                cart.forEach((item, index) => {
                    totalQty += item.qty;
                    let itemSubtotal = item.price * item.qty;

                    let subTextParts = [];
                    if (item.egg) subTextParts.push(`Egg: ${item.egg}`);
                    if (item.notes) subTextParts.push(`Note: ${item.notes}`);
                    const subtextFormatted = subTextParts.length > 0 ? `<span class="subtext-info">${subTextParts.join(' | ')}</span>` : '';

                    const row = document.createElement('div');
                    row.className = 'cart-item-card d-flex justify-content-between align-items-center';
                    row.innerHTML = `
                        <div>
                            <h6 class="fw-bold text-warning mb-0">${item.name}</h6>
                            ${subtextFormatted}
                            <div class="text-light small fw-bold mt-1">
                                ₱${item.price.toFixed(2)} x ${item.qty} = ₱${itemSubtotal.toFixed(2)}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="qty-btn" onclick="updateQty(${index}, -1)">-</button>
                            <span class="text-light fw-bold px-1 small">${item.qty}</span>
                            <button class="qty-btn" onclick="updateQty(${index}, 1)">+</button>
                            <button class="btn btn-outline-danger btn-sm ms-2" onclick="removeFromCart(${index}, true)" title="Delete Item"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    `;

                    attachSlideToDelete(row, index);
                    listDiv.appendChild(row);
                });
            }

            countSpan.innerText = totalQty;
            foodSubtotalSpan.innerText = foodTotal.toFixed(2);
            tipSpan.innerText = tip.toFixed(2);
            totalSpan.innerText = (foodTotal + tip).toFixed(2);
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

        function proceedToCheckoutForm() {
            if (cart.length === 0) {
                showPopupModal('Notice', 'Your cart is empty!');
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
                showPopupModal('Missing Details', 'Please provide your customer name, contact number, and address.');
                return;
            }

            if (proofInput.files.length === 0) {
                showPopupModal('Payment Proof Required', 'Please upload your GCash payment screenshot proof.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'place_order');
            formData.append('customer_name', name);
            formData.append('phone', phone);
            formData.append('delivery_area', document.getElementById('custArea').value.trim() || 'Taguig');
            formData.append('address', address);
            formData.append('food_subtotal', subtotal().toFixed(2));
            formData.append('tip_amount', tip.toFixed(2));
            formData.append('total_amount', (subtotal() + tip).toFixed(2));
            formData.append('cart_json', JSON.stringify(cart));
            formData.append('payment_proof', proofInput.files[0]);

            fetch('menu.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    cartModal.hide();
                    showPopupModal('Order Confirmed!', 'Order finished and submitted successfully!\nQueue Number: ' + data.queue_number + ' (Order ID: #' + data.order_id + ')', () => {
                        cart = [];
                        tip = 0;
                        setTip(0);
                        updateCartUI();
                        location.reload();
                    });
                } else {
                    showPopupModal('Order Blocked', data.message);
                }
            })
            .catch(err => {
                console.error(err);
                showPopupModal('System Error', 'An error occurred while submitting your order.');
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
            .then(res => res.text())
            .then(text => {
                try {
                    let data = JSON.parse(text);
                    if (data.success) {
                        showToast('Order marked as received and completed!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showPopupModal('Error', 'Error updating order status.');
                    }
                } catch(e) {
                    console.error('Response text:', text);
                    showPopupModal('Error', 'Server error occurred.');
                }
            })
            .catch(err => console.error(err));
        }
    </script>
</body>
</html>