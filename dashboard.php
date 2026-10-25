<?php
// Include database connection (Root Level Path)
require_once 'config/db.php';

$message = '';
$message_type = '';

// Handle Notification Messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'stock_updated') {
        $message = "Ingredient stock updated successfully!";
        $message_type = "success";
    } elseif ($_GET['msg'] === 'cap_updated') {
        $message = "Tambay Cap stock updated successfully!";
        $message_type = "success";
    }
}

// 1. DIRECT DATABASE UPDATE: RESTOCK INGREDIENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_ingredient'])) {
    $id = (int)$_POST['ingredient_id'];
    $add_qty = (float)$_POST['add_quantity'];
    
    try {
        $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
        $stmt->execute([$add_qty, $id]);
        header("Location: dashboard.php?msg=stock_updated");
        exit();
    } catch (PDOException $e) {
        $message = "Database Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// 2. DIRECT DATABASE UPDATE: RESTOCK TAMBAY CAP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_cap'])) {
    $id = (int)$_POST['cap_id'];
    $add_qty = (int)$_POST['add_stock'];
    
    try {
        $stmt = $pdo->prepare("UPDATE tambay_caps SET stock = stock + ? WHERE id = ?");
        $stmt->execute([$add_qty, $id]);
        header("Location: dashboard.php?msg=cap_updated");
        exit();
    } catch (PDOException $e) {
        $message = "Database Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Initialize arrays
$menu_items = [];
$inventory  = [];
$caps       = [];
$db_error   = null;

// Fetch Records from Database
try {
    $menu_items = $pdo->query("SELECT * FROM menu ORDER BY item_name ASC")->fetchAll();
    $inventory  = $pdo->query("SELECT * FROM inventory ORDER BY item_name ASC")->fetchAll();
    $caps       = $pdo->query("SELECT * FROM tambay_caps ORDER BY product_name ASC")->fetchAll();
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sairene Tapsilogan - Inventory Control</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        .sketch-box {
            position: relative;
            height: 170px;
            border: 3px solid #212529;
            border-radius: 10px;
            background: #ffffff;
            transition: all 0.2s ease;
            cursor: pointer;
            overflow: hidden;
        }
        .sketch-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.2);
            border-color: #ffc107;
        }

        .restock-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.90);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 15px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .sketch-box:hover .restock-overlay,
        .sketch-box:focus-within .restock-overlay {
            opacity: 1;
            visibility: visible;
        }

        .sketch-header {
            border-bottom: 3px solid #212529;
            background-color: #fff;
        }
        .logo-circle {
            width: 60px;
            height: 60px;
            border: 3px solid #212529;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            background: #ffc107;
        }
    </style>
</head>
<body class="bg-light">

    <!-- TOP HEADER NAVBAR -->
    <header class="sketch-header py-3 px-4 mb-4 shadow-sm">
        <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between gap-3">
            
            <!-- LOGO CIRCLE -->
            <div class="d-flex align-items-center gap-3">
                <div class="logo-circle text-center">LOGO</div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">SAIRENE TEPHTEPH</h5>
                    <small class="text-muted fw-bold">Tapsilogan Inventory System</small>
                </div>
            </div>

            <!-- FILTERS & SEARCH BAR -->
            <div class="d-flex align-items-center flex-wrap gap-3">
                
                <!-- Filter Tabs -->
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-dark fw-bold active" onclick="filterCategory('all')">ALL</button>
                    <button type="button" class="btn btn-outline-dark fw-bold" onclick="filterCategory('raw')">inventory</button>
                    <button type="button" class="btn btn-outline-dark fw-bold" onclick="filterCategory('caps')">TAMBAY caps</button>
                </div>

                <!-- SEARCH BAR -->
                <div class="input-group" style="width: 220px;">
                    <span class="input-group-text bg-dark text-warning border-dark"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="searchInput" onkeyup="searchCards()" class="form-control border-dark" placeholder="SEARCH BAR">
                </div>

                <a href="Home_Page.php" class="btn btn-warning fw-bold text-dark border-dark"><i class="fa-solid fa-globe me-1"></i> Site</a>
            </div>

        </div>
    </header>

    <div class="container-fluid px-4">

        <?php if ($db_error): ?>
            <div class="alert alert-danger shadow-sm border-dark mb-4">
                <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i>Database Error</h6>
                <small><?= htmlspecialchars($db_error) ?></small>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show shadow-sm border-dark">
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- VISUAL SKETCH BOXES GRID -->
        <div class="row g-4" id="inventoryGrid">

            <!-- 1. FOOD MENU BOXES -->
            <?php foreach ($menu_items as $item): ?>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 item-card" data-category="food" data-name="<?= strtolower($item['item_name']) ?>">
                    <div class="sketch-box d-flex flex-column justify-content-between p-3 text-center">
                        <span class="badge bg-warning text-dark border border-dark align-self-start fw-bold">FOOD</span>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($item['item_name']) ?></h6>
                            <span class="fs-5 fw-bold text-success">₱<?= number_format($item['price'], 2) ?></span>
                        </div>
                        <small class="text-muted fw-semibold"><?= strtoupper($item['category']) ?></small>

                        <div class="restock-overlay">
                            <span class="text-warning fw-bold small mb-2"><?= htmlspecialchars($item['item_name']) ?></span>
                            <span class="badge bg-success">Menu Product</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- 2. RAW INGREDIENT BOXES -->
            <?php foreach ($inventory as $item): ?>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 item-card" data-category="raw" data-name="<?= strtolower($item['item_name']) ?>">
                    <div class="sketch-box d-flex flex-column justify-content-between p-3 text-center">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <span class="badge bg-dark">RAW</span>
                            <?php if ($item['quantity'] <= $item['reorder_level']): ?>
                                <span class="badge bg-danger">LOW</span>
                            <?php else: ?>
                                <span class="badge bg-success">OK</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($item['item_name']) ?></h6>
                            <span class="fs-4 fw-bold text-primary"><?= number_format($item['quantity'], 1) ?> <small class="fs-6 text-dark"><?= $item['unit'] ?></small></span>
                        </div>
                        <small class="text-muted fw-semibold">Limit: <?= $item['reorder_level'] ?></small>

                        <!-- Hover Restock Form -->
                        <div class="restock-overlay">
                            <span class="text-warning fw-bold small mb-2">+ Add Restock</span>
                            <form method="POST" action="dashboard.php" class="w-100">
                                <input type="hidden" name="ingredient_id" value="<?= $item['id'] ?>">
                                <input type="number" step="0.01" name="add_quantity" class="form-control form-control-sm text-center mb-2" placeholder="Qty" required>
                                <button type="submit" name="restock_ingredient" class="btn btn-warning btn-sm w-100 fw-bold border-dark">Restock</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- 3. TAMBAY CAPS BOXES -->
            <?php foreach ($caps as $cap): ?>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 item-card" data-category="caps" data-name="<?= strtolower($cap['product_name']) ?>">
                    <div class="sketch-box d-flex flex-column justify-content-between p-3 text-center">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <span class="badge bg-info text-dark border border-dark">CAP</span>
                            <span class="badge bg-dark">₱<?= number_format($cap['price'], 0) ?></span>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($cap['product_name']) ?></h6>
                            <span class="fs-4 fw-bold text-dark"><?= $cap['stock'] ?> <small class="fs-6">pcs</small></span>
                        </div>
                        <small class="text-muted fw-semibold">Tambay Merch</small>

                        <!-- Hover Restock Form -->
                        <div class="restock-overlay">
                            <span class="text-warning fw-bold small mb-2">+ Restock Caps</span>
                            <form method="POST" action="dashboard.php" class="w-100">
                                <input type="hidden" name="cap_id" value="<?= $cap['id'] ?>">
                                <input type="number" min="1" name="add_stock" class="form-control form-control-sm text-center mb-2" placeholder="Stock" required>
                                <button type="submit" name="restock_cap" class="btn btn-warning btn-sm w-100 fw-bold border-dark">Restock</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>

    </div>

    <!-- 1. BOOTSTRAP BUNDLE JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/bootstrap.bundle.min.js"></script>

    <!-- 2. FILTER & SEARCH JAVASCRIPT -->
    <script>
        function filterCategory(cat) {
            let cards = document.querySelectorAll('.item-card');
            cards.forEach(c => {
                if (cat === 'all' || c.getAttribute('data-category') === cat) {
                    c.style.display = 'block';
                } else {
                    c.style.display = 'none';
                }
            });
        }

        function searchCards() {
            let val = document.getElementById('searchInput').value.toLowerCase();
            let cards = document.querySelectorAll('.item-card');
            cards.forEach(c => {
                let name = c.getAttribute('data-name');
                if (name.includes(val)) {
                    c.style.display = 'block';
                } else {
                    c.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>