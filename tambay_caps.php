<?php
session_start();

// Database connection
require_once 'config/db.php'; 

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle Cart Actions (Add, Update, Remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $product_id = intval($_POST['product_id']);
        $quantity = max(1, intval($_POST['quantity']));

        $stmt = $pdo->prepare("SELECT * FROM tambay_caps WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        // Check fallback items if not in DB
        if (!$product) {
            $fallback_caps = [
                101 => ['id' => 101, 'product_name' => 'Tambay Classic Black Cap', 'price' => 350.00, 'image_url' => 'tambay5.png'],
                102 => ['id' => 102, 'product_name' => 'Tambay Streetwear Cap', 'price' => 380.00, 'image_url' => 'tambay6.png'],
                103 => ['id' => 103, 'product_name' => 'Tambay Special Edition Cap', 'price' => 399.00, 'image_url' => 'tambay5.png']
            ];
            $product = $fallback_caps[$product_id] ?? null;
        }

        if ($product) {
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'id' => $product['id'],
                    'name' => $product['product_name'],
                    'price' => floatval($product['price']),
                    'image' => $product['image_url'],
                    'quantity' => $quantity
                ];
            }
        }
        header("Location: tambay_caps.php");
        exit();
    }

    if ($action === 'update') {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);

        if (isset($_SESSION['cart'][$product_id])) {
            if ($quantity > 0) {
                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            } else {
                unset($_SESSION['cart'][$product_id]);
            }
        }
        header("Location: tambay_caps.php");
        exit();
    }

    if ($action === 'remove') {
        $product_id = intval($_POST['product_id']);
        unset($_SESSION['cart'][$product_id]);
        header("Location: tambay_caps.php");
        exit();
    }
}

// Fetch all available caps from database
$caps = [];
try {
    $stmt = $pdo->query("SELECT * FROM tambay_caps");
    $caps = $stmt->fetchAll();
} catch (PDOException $e) {
    $caps = [];
}

// Default items using tambay5.png and tambay6.png
if (empty($caps)) {
    $caps = [
        [
            'id' => 101,
            'product_name' => 'Tambay Classic Black',
            'description' => 'Classic snapback cap with embroidered Tambay signature.',
            'price' => 350.00,
            'stock' => 15,
            'image_url' => 'tambay5.png',
            'back_image_url' => 'tambay6.png'
        ],
        [
            'id' => 102,
            'product_name' => 'Tambay Streetwear Cap',
            'description' => 'Vibrant urban style cap with premium adjustable strap.',
            'price' => 380.00,
            'stock' => 10,
            'image_url' => 'tambay6.png',
            'back_image_url' => 'tambay5.png'
        ],
        [
            'id' => 103,
            'product_name' => 'Tambay Special Edition',
            'description' => 'Special edition cap for everyday tambayan.',
            'price' => 399.00,
            'stock' => 8,
            'image_url' => 'tambay5.png',
            'back_image_url' => 'tambay6.png'
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sairene TephTeph Tapsilogan - Tambay Caps</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        body {
            background-color: #fcfcfc;
            color: #333333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 30px 20px;
        }

        /* Top Navigation Box */
        .top-nav-box {
            background: #ffffff;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 12px 20px;
            max-width: 950px;
            margin: 0 auto 30px auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }

        .nav-links {
            display: flex;
            justify-content: center;
            gap: 35px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-links a {
            text-decoration: none;
            color: #222222;
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            transition: color 0.2s;
        }

        .nav-links a:hover, .nav-links a.active {
            color: #d39e00;
        }

        /* Main Wrapper Container */
        .main-wrapper-box {
            background: #ffffff;
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 40px 30px;
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .page-title {
            color: #e0a800;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        /* Left Selection Container */
        .selection-container {
            background-color: #ffffff;
            border: 2.5px dashed #ffc107;
            border-radius: 12px;
            padding: 25px;
            min-height: 520px;
        }

        /* Right Summary Container */
        .cart-summary-panel {
            background-color: #ffffff;
            border: 2.5px solid #ffc107;
            border-radius: 12px;
            padding: 25px;
            min-height: 520px;
            display: flex;
            flex-direction: column;
        }

        /* Product Card */
        .product-card-item {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: all 0.2s;
        }

        .product-card-item:hover {
            border-color: #ffc107;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.15);
        }

        /* 3D FLIP CONTAINER STYLES */
        .flip-card {
            background-color: transparent;
            height: 160px;
            perspective: 1000px;
            cursor: pointer;
            margin-bottom: 12px;
            position: relative;
        }

        .flip-card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            text-align: center;
            transition: transform 0.6s cubic-bezier(0.4, 0.2, 0.2, 1);
            transform-style: preserve-3d;
            border-radius: 6px;
        }

        .flip-card.flipped .flip-card-inner {
            transform: rotateY(180deg);
        }

        .flip-card-front, .flip-card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #eeeeee;
        }

        .flip-card-front {
            background-color: #f8f9fa;
        }

        .flip-card-back {
            background-color: #212529;
            transform: rotateY(180deg);
        }

        .flip-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .turn-hint-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            z-index: 10;
            background-color: rgba(255, 193, 7, 0.9);
            color: #121212;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            pointer-events: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* Cart Item Box */
        .cart-item-row {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
        }

        footer {
            text-align: center;
            color: #777777;
            font-size: 0.85rem;
            margin-top: 35px;
        }
    </style>
</head>
<body>

    <!-- Top Navigation Box -->
    <nav class="top-nav-box">
        <ul class="nav-links">
            <li><a href="home_page.php">HOME</a></li>
            <li><a href="menu.php">MENU</a></li>
            <li><a href="order.php">ORDER NOW</a></li>
            <li><a href="tambay_caps.php" class="active">TAMBAY CAPS</a></li>
        </ul>
    </nav>

    <!-- Main Wrapper Box -->
    <main class="main-wrapper-box">
        
        <!-- Header Section -->
        <section class="text-center mb-4">
            <h1 class="display-6 page-title">Tambay Caps Collection</h1>
            <p class="text-black small mt-1">Click on any cap image to turn it around and check the alternate view!</p>
        </section>

        <hr class="border-warning opacity-25 my-4">

        <!-- Two-Column Layout -->
        <div class="row g-4">
            
            <!-- Left Column: Cap Selection Area (~7 cols) -->
            <div class="col-lg-7">
                <div class="selection-container">
                   <h5 class="fw-bold text-dark mb-3 d-flex align-items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="46" height="38" viewBox="0 0 240 180" class="me-2">
                            <path d="M52 108 C 52 60, 92 32, 142 32 C 188 32, 212 65, 212 102 Z" fill="#000000" stroke="#000000" stroke-width="4"/>
                            <path d="M15 122 C 20 122, 60 148, 125 142 C 175 137, 200 115, 215 100 C 195 107, 150 122, 112 118 C 65 113, 30 102, 15 122 Z" fill="#000000"/>
                            <text x="125" y="85" font-family="'Segoe UI', Tahoma, sans-serif" font-weight="900" font-size="26" fill="#ffffff" text-anchor="middle" transform="rotate(-6 125 85)" letter-spacing="1">TAMBAY</text>
                        </svg>
                        Available Caps
                    </h5>
                    
                    <div class="row row-cols-1 row-cols-md-2 g-3 mt-1">
                        <?php foreach ($caps as $cap): ?>
                            <?php 
                                $back_img = !empty($cap['back_image_url']) ? $cap['back_image_url'] : ($cap['image_url'] === 'tambay5.png' ? 'tambay6.png' : 'tambay5.png');
                            ?>
                            <div class="col">
                                <div class="product-card-item">
                                    
                                    <!-- Interactive 3D Flip Image Box -->
                                    <div class="flip-card" onclick="this.classList.toggle('flipped')" title="Click to Turn Image">
                                        <span class="turn-hint-badge"><i class="fa-solid fa-rotate me-1"></i>Click to Turn</span>
                                        <div class="flip-card-inner">
                                            <!-- Front Side Image -->
                                            <div class="flip-card-front">
                                                <img src="<?php echo htmlspecialchars($cap['image_url']); ?>" alt="<?php echo htmlspecialchars($cap['product_name']); ?> Front View">
                                            </div>
                                            <!-- Back Side Image -->
                                            <div class="flip-card-back">
                                                <img src="<?php echo htmlspecialchars($back_img); ?>" alt="<?php echo htmlspecialchars($cap['product_name']); ?> Back View">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;"><?php echo htmlspecialchars($cap['product_name']); ?></h6>
                                        <span class="badge bg-light text-dark border border-secondary" style="font-size: 0.7rem;">Stock: <?php echo $cap['stock']; ?></span>
                                    </div>
                                    <p class="text-muted small mb-2 flex-grow-1" style="font-size: 0.8rem;"><?php echo htmlspecialchars($cap['description']); ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top border-light">
                                        <span class="text-dark fw-bold font-monospace">₱<?php echo number_format($cap['price'], 2); ?></span>
                                        
                                        <form action="tambay_caps.php" method="POST" class="d-flex align-items-center gap-1">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="product_id" value="<?php echo $cap['id']; ?>">
                                            <input type="number" name="quantity" value="1" min="1" max="<?php echo max(1, $cap['stock']); ?>" class="form-control form-control-sm text-center border-secondary" style="width: 45px; height: 30px; font-size: 0.85rem;">
                                            <button type="submit" class="btn btn-warning btn-sm fw-bold px-2 py-1 text-dark" title="Add to Order">
                                                <i class="fa-solid fa-cart-plus"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Cart Summary Panel (~5 cols) -->
            <div class="col-lg-5">
                <div class="cart-summary-panel">
                    <h5 class="fw-bold text-dark mb-3">
                        <i class="fa-solid fa-bag-shopping text-warning me-2"></i> Your Cap Order Summary
                    </h5>

                    <div class="cart-items-list mb-3 flex-grow-1" style="max-height: 340px; overflow-y: auto;">
                        <?php if (empty($_SESSION['cart'])): ?>
                            <div class="text-center py-5 text-muted my-auto">
                                <i class="fa-solid fa-basket-shopping fa-3x mb-3 text-secondary opacity-50"></i>
                                <p class="mb-0 small">Your cart is currently empty.<br>Select a cap to start your order!</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $grand_total = 0;
                            foreach ($_SESSION['cart'] as $item): 
                                $item_total = $item['price'] * $item['quantity'];
                                $grand_total += $item_total;
                            ?>
                                <div class="cart-item-row">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <span class="fw-bold text-dark small"><?php echo htmlspecialchars($item['name']); ?></span>
                                        <form action="tambay_caps.php" method="POST" class="m-0">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-link text-danger p-0 small" title="Remove"><i class="fa-solid fa-trash-can"></i></button>
                                        </form>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="text-muted small font-monospace">₱<?php echo number_format($item['price'], 2); ?> each</span>
                                        
                                        <form action="tambay_caps.php" method="POST" class="d-flex align-items-center gap-1 m-0">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="form-control form-control-sm text-center border-secondary p-0" style="width: 45px; height: 26px; font-size: 0.85rem;" onchange="this.form.submit()">
                                        </form>
                                        
                                        <span class="fw-bold text-dark small font-monospace">₱<?php echo number_format($item_total, 2); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Total Price Box -->
                    <div class="bg-light p-3 rounded border border-warning mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-dark fw-bold">Order Total:</span>
                            <span class="text-dark fw-bold fs-5 font-monospace">
                                ₱<?php echo isset($grand_total) ? number_format($grand_total, 2) : '0.00'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Proceed Button -->
                    <a href="order.php" class="btn btn-warning w-100 fw-bold py-2 text-dark shadow-sm">
                        <i class="fa-solid fa-bag-shopping me-2"></i> Proceed to Order
                    </a>

                </div>
            </div>

        </div>

    </main>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan &bull; Tambay Caps Collection</p>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/bootstrap.bundle.min.js"></script>
</body>
</html>
