<?php
// 
// STEP 1: PHP SESSION INITIALIZATION & DATABASE CONFIGURATION
// 

// Step 1.1: Start or resume the active PHP session to track user state
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: homepage.php?auth_required=1');
    exit;
}

// Step 1.2: Include database connection configuration (PDO instance)
require_once 'config/db.php';

// 
// STEP 2: BACKEND POST / AJAX HANDLERS FOR AUTHENTICATION & ORDERS
// 

// Step 2.1: Intercept incoming POST requests containing an 'action' parameter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Set response content type to JSON for AJAX consumption
    header('Content-Type: application/json');

    // 
    // STEP 2.A: CUSTOMER SIGN UP HANDLER
    // 
    if ($_POST['action'] === 'signup') {
        try {
            global $pdo;
            // Step 2.A.1: Sanitize input parameters
            $fullname = trim($_POST['fullname'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            // Step 2.A.2: Validate non-empty fields
            if (empty($fullname) || empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            // Step 2.A.3: Check if email already exists in database
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email address is already in use.']);
                exit;
            }

            // Step 2.A.4: Hash password and insert customer record
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'customer')");
            $stmt->execute([$fullname, $email, $hashedPassword]);

            // Step 2.A.5: Set active user session
            $_SESSION['user_id']  = $pdo->lastInsertId();
            $_SESSION['fullname'] = $fullname;

            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 
    // STEP 2.B: CUSTOMER LOGIN HANDLER
    // 
    if ($_POST['action'] === 'login') {
        try {
            global $pdo;
            // Step 2.B.1: Clean user credentials
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            // Step 2.B.2: Validate empty inputs
            if (empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Please fill in both email and password.']);
                exit;
            }

            // Step 2.B.3: Fetch matching user profile
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Step 2.B.4: Verify password hash and set session
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            }
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 
    // STEP 2.C: LOGOUT HANDLER
    // 
    if ($_POST['action'] === 'logout') {
        // Step 2.C.1: Unset session variables and destroy active session
        session_unset();
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }

    // 
    // STEP 2.D: PLACE ORDER HANDLER (POS & KIOSK LIVE SYNC)
    // 
    if ($_POST['action'] === 'place_order') {
        try {
            global $pdo;
            // Step 2.D.1: Gather session customer name and submitted checkout payload
            $customer_name = $_SESSION['fullname'] ?? 'Customer';
            $phone         = trim($_POST['phone'] ?? '');
            $address       = trim($_POST['address'] ?? 'Pickup / C5 Taguig');
            $total_amount  = floatval($_POST['total_amount'] ?? 0);
            $cart          = $_SESSION['cart'] ?? [];

            // Step 2.D.2: Check if cart contains items
            if (empty($cart)) {
                echo json_encode(['success' => false, 'message' => 'Your order cart is empty.']);
                exit;
            }

            // Step 2.D.3: Generate daily sequential queue number (e.g. FP-001)
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM kiosk_orders WHERE DATE(created_at) = CURDATE()");
            $row = $stmt->fetch();
            $seq = ($row['count'] ?? 0) + 1;
            $queueNumber = 'FP-' . str_pad($seq, 3, '0', STR_PAD_LEFT);

            // Step 2.D.4: Begin SQL transaction
            $pdo->beginTransaction();

            // Step 2.D.5: Insert main order summary record into kiosk_orders table
            $sql = "INSERT INTO kiosk_orders (queue_number, customer_name, phone, table_tag, dining_style, food_subtotal, total_amount, payment_method, payment_status, created_at) 
                    VALUES (?, ?, ?, ?, 'Online Food Pack', ?, ?, 'GCash', 'paid', NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$queueNumber, $customer_name, $phone, $address, $total_amount, $total_amount]);
            $order_id = $pdo->lastInsertId();

            // Step 2.D.6: Insert itemized cart breakdown into kiosk_order_items table
            $item_stmt = $pdo->prepare("INSERT INTO kiosk_order_items (order_id, product_name, custom_options, quantity, price) VALUES (?, ?, ?, ?, ?)");
            foreach ($cart as $item) {
                $item_title = $item['title'] ?? $item['name'] ?? 'Custom Item';
                $item_details = $item['details'] ?? $item['notes'] ?? '';
                $item_qty = intval($item['qty'] ?? 1);
                $item_price = floatval($item['unit_price'] ?? $item['price'] ?? 0);

                $item_stmt->execute([
                    $order_id,
                    $item_title,
                    $item_details,
                    $item_qty,
                    $item_price
                ]);
            }

            // Step 2.D.7: Commit transaction
            $pdo->commit();

            // Step 2.D.8: Clear active order cart session after successful placement
            unset($_SESSION['cart']);

            echo json_encode(['success' => true, 'queue_number' => $queueNumber]);
            exit;
        } catch (Exception $e) {
            // Rollback transaction on failure
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to place order: ' . $e->getMessage()]);
            exit;
        }
    }
}

// 
// STEP 3: HANDLE GET ACTIONS (ADD CUSTOM FOOD PACK & CLEAR CART)
// 

// Step 3.1: Catch redirected custom food pack parameters from food_packs.php
if (isset($_GET['add_custom_pack']) && $_GET['add_custom_pack'] == '1') {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $newItem = [
        'title'       => $_GET['title'] ?? 'Custom Food Pack',
        'details'     => $_GET['details'] ?? '',
        'unit_price'  => floatval($_GET['unit_price'] ?? 120.00),
        'qty'         => intval($_GET['qty'] ?? 1),
        'total'       => floatval($_GET['unit_price'] ?? 120.00) * intval($_GET['qty'] ?? 1)
    ];

    $_SESSION['cart'][] = $newItem;
}

// Step 3.2: Catch Clear Cart request
if (isset($_GET['clear_cart'])) {
    unset($_SESSION['cart']);
    header('Location: order.php');
    exit;
}

// 
// STEP 4: CART & USER SESSION EVALUATION FOR UI RENDERING
// 

// Step 4.1: Check session user status and fetch cart items
$is_logged_in = isset($_SESSION['user_id']);
$cart_items = $_SESSION['cart'] ?? [];
$cart_total = 0;

// Step 4.2: Calculate total cart price
foreach ($cart_items as $ci) {
    if (is_array($ci)) {
        $item_price = floatval($ci['total'] ?? (($ci['price'] ?? 0) * ($ci['qty'] ?? 1)));
        $cart_total += $item_price;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!--  -->
    <!-- STEP 5: DOCUMENT METADATA & EXTERNAL CSS LIBRARIES -->
    <!--  -->
    <!-- Step 5.1: Set document charset, viewport, and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Checkout - Sairene TephTeph Tapsilogan</title>

    <!-- Step 5.2: External Stylesheets (Bootstrap 5 & FontAwesome Icons) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!--  -->
    <!-- STEP 6: EMBEDDED CSS STYLING RULES WITH STEP-BY-STEP COMMENTS -->
    <!--  -->
    <style>
        /* Step 6.1: Base page background and typography setup */
        body {
            background-color: #2b1d0c;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            position: relative;
            min-height: 100vh;
        }

        /* Step 6.2: Fixed Fullscreen Wooden Texture Overlay */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(0, 0, 0, 0.25), rgba(0, 0, 0, 0.35)), url(img/wood.jpg) no-repeat center center;
            background-size: cover;
            z-index: -1;
        }

        /* Step 6.3: Fixed floating top-left login / logout button */
        .top-left-login-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1050;
            background-color: #212529;
            color: #ffc107;
            border: 2px solid #ffc107;
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: bold;
            font-size: 0.88rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }

        /* Step 6.4: Hover transition state for top-left button */
        .top-left-login-btn:hover {
            background-color: #ffc107;
            color: #212529;
            border-color: #212529;
            transform: translateY(-2px);
        }

        /* Step 6.5: Large Outer Glassmorphism Frame Box */
        .page-outer-frame {
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            max-width: 1200px;
            margin: 40px auto 0 auto;
        }

        /* Step 6.6: Top Featured Header Banner Box */
        .top-banner-spot {
            position: relative;
            border: 2px solid #ffc107;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(6px);
            padding: 25px 30px;
            margin-bottom: 15px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: inset 0 0 15px rgba(255, 193, 7, 0.1);
        }

        /* Step 6.7: Circular Logo Avatar */
        .banner-logo-container {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            border: 3px solid #ffc107;
            background-color: #ffffff;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Step 6.8: Banner logo image sizing */
        .banner-logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Step 6.9: Banner text flex layout */
        .banner-text-content {
            flex-grow: 1;
            padding: 0 25px;
        }

        /* Step 6.10: Banner primary title styling with text-shadow */
        .banner-text-content h1 {
            color: #ffc107;
            font-weight: 900;
            font-size: 2.2rem;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.9), 0 0 12px rgba(0, 0, 0, 0.7);
        }

        /* Step 6.11: Badge row wrapper inside banner */
        .banner-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Step 6.12: Pill badge styling for key features */
        .banner-badge {
            background-color: #ffc107;
            color: #121212;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 50px;
            text-transform: uppercase;
        }

        /* Step 6.13: First sub-header divider line styling */
        .sub-header-line-1 {
            border: 1px solid #ffc107;
            background: rgba(0, 0, 0, 0.4);
            color: #ffffff;
            height: 36px;
            border-radius: 4px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Step 6.14: Second sub-header navigation line container */
        .sub-header-line-2 {
            border: 2px solid #ffc107;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 12px;
            padding: 10px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        /* Step 6.15: Custom navigation list menu layout */
        .nav-link-custom {
            display: flex;
            justify-content: center;
            gap: 25px;
            list-style: none;
            padding: 0;
            margin: 0;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Step 6.16: Custom navigation menu item links styling */
        .nav-link-custom a {
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

        /* Step 6.17: Custom navigation hover and active link highlight states */
        .nav-link-custom a:hover, .nav-link-custom a.active {
            color: #121212;
            background-color: #ffc107;
            border-color: #d4a000;
            box-shadow: 0 4px 10px rgba(255, 193, 7, 0.4);
        }

        /* Step 6.18: Active Order Summary Container Box */
        .order-summary-box {
            background: rgba(0, 0, 0, 0.6);
            border: 2px solid #ffc107;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
        }

        /* Step 6.19: Interactive Google Map responsive aspect ratio container */
        .map-responsive {
            overflow: hidden;
            padding-bottom: 70%;
            position: relative;
            height: 0;
            border-radius: 12px;
            border: 2px solid #ffc107;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        /* Step 6.20: Google Map iFrame absolute positioning */
        .map-responsive iframe {
            left: 0;
            top: 0;
            height: 100%;
            width: 100%;
            position: absolute;
        }

        /* Step 6.21: Delivery location sidebar details card styling */
        .location-card {
            background: rgba(0, 0, 0, 0.55);
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 25px;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        /* Step 6.22: Custom gold styled select dropdown element */
        .form-select-gold {
            background-color: #212529;
            color: #ffffff;
            border: 2px solid #ffc107;
            font-weight: bold;
            border-radius: 8px;
        }

        /* Step 6.23: Custom gold select dropdown focus state */
        .form-select-gold:focus {
            background-color: #212529;
            color: #ffffff;
            border-color: #d4a000;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.4);
        }

        /* Step 6.24: Bottom Full-Width Footer Line */
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
            font-weight: 500;
            margin-top: 25px;
        }

        /* Step 6.25: Mobile Breakpoint Adjustments */
        @media (max-width: 992px) {
            .top-banner-spot {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .banner-text-content { padding: 0; }
            .banner-badges { justify-content: center; }
        }
    </style>
</head>
<body>

    <!--  -->
    <!-- STEP 7: TOP-LEFT LOGIN / LOGOUT FLOATING BUTTON -->
    <!--  -->
    <?php if ($is_logged_in): ?>
        <!-- Step 7.1: Render Logout button if user session exists -->
        <button type="button" class="top-left-login-btn" onclick="confirmLogout()">
            <i class="fa-solid fa-right-from-bracket me-2"></i>Logout (<?= htmlspecialchars($_SESSION['fullname']) ?>)
        </button>
    <?php else: ?>
        <!-- Step 7.2: Render Login / Register trigger for guest users -->
        <button type="button" class="top-left-login-btn" onclick="openAuthModal('login')">
            <i class="fa-solid fa-user me-2"></i>Login / Register
        </button>
    <?php endif; ?>

    <!--  -->
    <!-- STEP 8: MAIN PAGE WRAPPER FRAME -->
    <!--  -->
    <div class="page-outer-frame">

        <!-- Step 8.1: Top Featured Brand Banner -->
        <section class="top-banner-spot">
            <div class="banner-logo-container">
                <img src="images/Logo.png" alt="Sairene TephTeph Logo" onerror="this.src='img/Logo.png';">
            </div>

            <div class="banner-text-content">
                <h1>Sairene TephTeph Tapsilogan</h1>
                <p class="text-light small mb-2 fw-semibold">Your Favorite Neighborhood Tapsilogan & Tambayan • Fresh Silog Meals Everyday</p>
                
                <div class="banner-badges">
                    <span class="banner-badge"><i class="fa-solid fa-clock me-1"></i> Open 24/7</span>
                    <span class="banner-badge"><i class="fa-solid fa-location-dot me-1"></i> MRB Condominium C5, Taguig</span>
                    <span class="banner-badge"><i class="fa-solid fa-wallet me-1"></i> GCash Ready</span>
                </div>
            </div>

            <div>
                <a href="food_packs.php" class="btn btn-warning fw-bold px-4 py-2 text-dark shadow-sm rounded-pill text-nowrap">
                    <i class="fa-solid fa-box-open me-1"></i> Build Food Pack
                </a>
            </div>
        </section>

        <!-- Step 8.2: Double Sub-Header Navigation -->
        <div class="sub-header-line-1">
            <span class="text-light small fw-bold"><i class="fa-solid fa-star text-warning me-1"></i> A Taste of Comfort • Hot & Fresh Silog Meals <i class="fa-solid fa-star text-warning ms-1"></i></span>
        </div>

        <div class="sub-header-line-2">
            <nav class="nav-link-custom">
                <a href="homepage.php" id="homeNavLink">HOME</a>
                <a href="menu.php" id="menuNavLink">MENU</a>
                <a href="order.php" id="orderNavLink" class="active">ORDER NOW</a>
                <a href="food_packs.php" id="foodPacksNavLink">FOOD PACKS</a>
                <a href="tambay_caps.php" id="tambayNavLink">TAMBAY CAPS</a>
            </nav>
        </div>

        <!-- Step 8.3: Active Cart Summary Table (Only displayed when cart is not empty) -->
        <?php if (!empty($cart_items)): ?>
            <section class="order-summary-box">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-warning pb-2">
                    <h3 class="h5 fw-bold text-warning mb-0">
                        <i class="fa-solid fa-receipt me-2"></i>Your Active Order Items
                    </h3>
                    <a href="order.php?clear_cart=1" class="btn btn-outline-danger btn-sm text-white fw-bold">
                        <i class="fa-solid fa-trash me-1"></i> Clear Cart
                    </a>
                </div>

                <!-- Itemized Cart Table -->
                <div class="table-responsive mb-3">
                    <table class="table table-dark table-hover border-warning align-middle mb-0">
                        <thead>
                            <tr class="text-warning">
                                <th>Item Title</th>
                                <th>Meal Configuration Details</th>
                                <th class="text-center">Pax / Qty</th>
                                <th class="text-end">Total Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                                <?php
                                    $title   = $item['title'] ?? $item['name'] ?? 'Food Item';
                                    $details = $item['details'] ?? $item['notes'] ?? 'N/A';
                                    $qty     = intval($item['qty'] ?? 1);
                                    $total   = floatval($item['total'] ?? (($item['price'] ?? 0) * $qty));
                                ?>
                                <tr>
                                    <td class="fw-bold text-warning"><?= htmlspecialchars($title) ?></td>
                                    <td class="small text-light"><?= htmlspecialchars($details) ?></td>
                                    <td class="text-center fw-bold"><?= htmlspecialchars($qty) ?> pack(s)</td>
                                    <td class="text-end fw-bold text-warning">₱<?= number_format($total, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Grand Order Subtotal Banner -->
                <div class="d-flex justify-content-between align-items-center bg-dark p-3 rounded border border-warning mb-3">
                    <span class="fs-5 fw-bold text-white">Grand Order Total:</span>
                    <span class="fs-3 fw-bold text-warning font-monospace">₱<?= number_format($cart_total, 2) ?></span>
                </div>

                <!-- Order Confirmation Button -->
                <div class="text-end">
                    <button type="button" class="btn btn-warning btn-lg fw-bold px-5 text-dark rounded-pill shadow-sm" onclick="handleCheckoutClick()">
                        <i class="fa-solid fa-check-circle me-2"></i>Confirm & Complete Order
                    </button>
                </div>
            </section>
        <?php endif; ?>

        <!-- Step 8.4: Interactive Map and Delivery Location Section -->
        <div class="my-4">
            <section class="text-center mb-4">
                <h2 class="h4 fw-bold text-warning text-uppercase">Delivery Location & Store Map</h2>
                <p class="text-light small">Select your delivery zone to check coverage and store distance.</p>
            </section>

            <div class="row g-4">
                <!-- Left Column: Google Map iFrame -->
                <div class="col-lg-7">
                    <div class="map-responsive">
                        <iframe 
                            id="locationMap"
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3862.270724816027!2d121.0560!3d14.5290!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c8c3683f80ad%3A0x89e2c69460db8b00!2sMRB%20Condominium%2C%20Taguig%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>

                <!-- Right Column: Delivery Area Dropdown Selector -->
                <div class="col-lg-5">
                    <div class="location-card">
                        <h5 class="text-warning fw-bold mb-3 border-bottom border-warning pb-2"><i class="fa-solid fa-map-pin text-warning me-2"></i>Choose Delivery Area</h5>
                        
                        <div class="mb-3">
                            <label for="locationSelect" class="form-label text-light small fw-bold">Select Location / Branch:</label>
                            <select id="locationSelect" class="form-select form-select-gold py-2" onchange="updateMapLocation(this.value)">
                                <option value="mrb_c5" selected>MRB Condominium C5, Taguig (Main Store / Pickup)</option>
                                <option value="taguig_proper">Taguig City Center / Lower Bicutan</option>
                                <option value="bgc_mckinley">BGC / Bonifacio Global City / McKinley</option>
                                <option value="pateros_pasig">Pateros / San Joaquin, Pasig Area</option>
                            </select>
                        </div>

                        <!-- Dynamic Delivery Information Badges -->
                        <div class="p-3 rounded bg-dark border border-warning mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-light small">Estimated Delivery Fee:</span>
                                <span id="feeBadge" class="badge bg-warning text-dark fw-bold fs-6">₱30 - ₱50</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-light small">Est. Delivery Time:</span>
                                <span id="timeBadge" class="text-warning fw-bold small">20 - 35 mins</span>
                            </div>
                        </div>

                        <!-- Operating Hours & Payment Account Details -->
                        <div class="border-top border-warning pt-3 mb-3">
                            <h6 class="text-warning fw-bold mb-2 small"><i class="fa-solid fa-clock me-2"></i>24/7 Operations</h6>
                            <p class="small text-light mb-2">Open all day & late nights for shift workers.</p>

                            <h6 class="text-warning fw-bold mb-2 small"><i class="fa-solid fa-wallet me-2"></i>GCash Details</h6>
                            <p class="small text-light mb-1"><strong>Number:</strong> 09270466231</p>
                            <p class="small text-light mb-0"><strong>Account Name:</strong> Sarippudin Aming</p>
                        </div>

                        <!-- Action Button to Custom Food Pack Page -->
                        <a href="food_packs.php" class="btn btn-warning w-100 fw-bold py-2 text-dark rounded-pill shadow-sm">
                            <i class="fa-solid fa-box-open me-2"></i>Build Custom Food Pack
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 8.5: Bottom Footer Bar -->
        <div class="bottom-full-line">
            <span>&copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan &bull; All Rights Reserved</span>
        </div>

    </div>

    <!--  -->
    <!-- STEP 9: ORDER CHECKOUT MODAL DIALOG -->
    <!--  -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-3 border-warning bg-dark text-white">
                <div class="modal-header border-warning">
                    <h5 class="modal-title fw-bold text-warning"><i class="fa-solid fa-receipt me-2"></i>Complete Order Checkout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Step 9.1: Form inputs for contact number and delivery address -->
                    <form id="checkoutForm" onsubmit="submitFinalOrder(event)">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Contact Phone Number</label>
                            <input type="tel" id="checkoutPhone" class="form-control bg-dark text-white border-warning" placeholder="0912 345 6789" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Delivery Address / Table Tag</label>
                            <textarea id="checkoutAddress" class="form-control bg-dark text-white border-warning" rows="2" placeholder="e.g. Unit 4B MRB Condominium C5 Taguig" required></textarea>
                        </div>

                        <!-- Step 9.2: GCash total payment summary box and submit action button -->
                        <div class="p-3 bg-secondary bg-opacity-25 rounded border border-warning mb-3">
                            <div class="d-flex justify-content-between text-warning fw-bold">
                                <span>Total Payment (GCash):</span>
                                <span>₱<?= number_format($cart_total, 2) ?></span>
                            </div>
                            <small class="text-light opacity-75 d-block mt-1">Pay via GCash: 09270466231 (Sarippudin Aming)</small>
                        </div>

                        <button type="submit" class="btn btn-warning w-100 fw-bold py-2 text-dark">
                            <i class="fa-solid fa-paper-plane me-1"></i> Submit Order
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!--  -->
    <!-- STEP 10: CUSTOMER AUTHENTICATION MODAL (LOGIN & SIGNUP) -->
    <!--  -->
    <div class="modal fade" id="authModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-3 border-warning bg-dark text-white">
                <div class="modal-header border-warning">
                    <h5 class="modal-title fw-bold text-warning" id="authModalTitle">Customer Login</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Step 10.1: Login Form Component -->
                    <form id="loginForm" onsubmit="handleAuthSubmit(event, 'login')">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Email Address</label>
                            <input type="email" id="loginEmail" class="form-control bg-dark text-white border-secondary" placeholder="name@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Password</label>
                            <input type="password" id="loginPassword" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold text-dark mb-3">Login</button>
                        <p class="small text-center mb-0">Don't have an account? <button type="button" onclick="toggleAuthForm('signup')" class="btn btn-link text-warning fw-bold p-0 text-decoration-none">Sign Up</button></p>
                    </form>

                    <!-- Step 10.2: Signup Form Component (Initially Hidden) -->
                    <form id="signupForm" class="d-none" onsubmit="handleAuthSubmit(event, 'signup')">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Full Name</label>
                            <input type="text" id="signupName" class="form-control bg-dark text-white border-secondary" placeholder="Juan Dela Cruz" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Email Address</label>
                            <input type="email" id="signupEmail" class="form-control bg-dark text-white border-secondary" placeholder="name@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Password</label>
                            <input type="password" id="signupPassword" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold text-dark mb-3">Create Account</button>
                        <p class="small text-center mb-0">Already have an account? <button type="button" onclick="toggleAuthForm('login')" class="btn btn-link text-warning fw-bold p-0 text-decoration-none">Login</button></p>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!--  -->
    <!-- STEP 11: FRONTEND JAVASCRIPT CONTROLLERS -->
    <!--  -->
    <script>
        // Check session login state
        const isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;

        // Step 11.1: Guard checkout click against guest users
        function handleCheckoutClick() {
            if (!isLoggedIn) {
                openAuthModal('login');
                return;
            }
            openCheckoutModal();
        }

        // Step 11.2: Display checkout modal instance
        function openCheckoutModal() {
            var modalEl = document.getElementById('checkoutModal');
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }

        // Step 11.3: Submit order request via AJAX POST
        function submitFinalOrder(e) {
            e.preventDefault();
            let formData = new FormData();
            formData.append('action', 'place_order');
            formData.append('phone', document.getElementById('checkoutPhone').value);
            formData.append('address', document.getElementById('checkoutAddress').value);
            formData.append('total_amount', '<?= $cart_total ?>');

            fetch('order.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(`Order Placed Successfully!\nYour Queue Number is: ${data.queue_number}`);
                    window.location.href = 'order.php';
                } else {
                    alert(data.message || 'Error placing order.');
                }
            })
            .catch(err => console.error(err));
        }

        // Step 11.4: Update interactive Google map iFrame source and fees based on selection
        function updateMapLocation(locationKey) {
            const mapIframe = document.getElementById('locationMap');
            const feeBadge = document.getElementById('feeBadge');
            const timeBadge = document.getElementById('timeBadge');

            if (locationKey === 'mrb_c5') {
                mapIframe.src = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3862.270724816027!2d121.0560!3d14.5290!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c8c3683f80ad%3A0x89e2c69460db8b00!2sMRB%20Condominium%2C%20Taguig%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph";
                feeBadge.innerText = "₱30 - ₱50";
                timeBadge.innerText = "20 - 35 mins";
            } else if (locationKey === 'taguig_proper') {
                mapIframe.src = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d61803.74312015822!2d121.0185121406606!3d14.51268689531525!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c88e332a688b%3A0xc345da58409ee42d!2sTaguig%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph";
                feeBadge.innerText = "₱50 - ₱70";
                timeBadge.innerText = "30 - 45 mins";
            } else if (locationKey === 'bgc_mckinley') {
                mapIframe.src = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15446.73507519967!2d121.04278479782522!3d14.545802280208166!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c8ed284c16a7%3A0xc6cb19283e3eb6f0!2sBonifacio%20Global%20City%2C%20Taguig%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph";
                feeBadge.innerText = "₱60 - ₱80";
                timeBadge.innerText = "25 - 40 mins";
            } else if (locationKey === 'pateros_pasig') {
                mapIframe.src = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15446.21639097728!2d121.06014454782635!3d14.548726530188092!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c8b0907e1cb5%3A0x7d6a5e1eb29ee0ff!2sPateros%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph";
                feeBadge.innerText = "₱70 - ₱90";
                timeBadge.innerText = "35 - 50 mins";
            }
        }

        // Step 11.5: Open authentication modal popup
        function openAuthModal(type) {
            toggleAuthForm(type);
            var modalEl = document.getElementById('authModal');
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }

        // Step 11.6: Toggle between login and signup form panels
        function toggleAuthForm(type) {
            let loginForm = document.getElementById('loginForm');
            let signupForm = document.getElementById('signupForm');
            let modalTitle = document.getElementById('authModalTitle');

            if (type === 'signup') {
                loginForm.classList.add('d-none');
                signupForm.classList.remove('d-none');
                modalTitle.innerText = 'Create Account';
            } else {
                signupForm.classList.add('d-none');
                loginForm.classList.remove('d-none');
                modalTitle.innerText = 'Customer Login';
            }
        }

        // Step 11.7: Process authentication form submission via AJAX
        function handleAuthSubmit(e, action) {
            e.preventDefault();
            let formData = new FormData();
            formData.append('action', action);

            if (action === 'login') {
                formData.append('email', document.getElementById('loginEmail').value);
                formData.append('password', document.getElementById('loginPassword').value);
            } else if (action === 'signup') {
                formData.append('fullname', document.getElementById('signupName').value);
                formData.append('email', document.getElementById('signupEmail').value);
                formData.append('password', document.getElementById('signupPassword').value);
            }

            fetch('order.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'An error occurred during authentication.');
                }
            })
            .catch(err => console.error(err));
        }

        // Step 11.8: Process customer logout
        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                let formData = new FormData();
                formData.append('action', 'logout');
                fetch('order.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) location.reload();
                });
            }
        }
    </script>
</body>
</html>
