<?php
// ==========================================
// PHP BACKEND CONTROLLER LOGIC (Standalone Kiosk DB)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Include standalone database connection configuration
    require_once __DIR__ . '/config/db.php';

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON payload received.']);
            exit;
        }

        // Handle Reorder Lookup Action from Database
        if (isset($input['action']) && $input['action'] === 'lookup_reorder') {
            $digits = preg_replace('/\D/', '', $input['digits'] ?? '');
            if (strlen($digits) === 4) {
                $stmt = $pdo->prepare("SELECT last_order_json FROM kiosk_saved_profiles WHERE RIGHT(phone_number, 4) = ? ORDER BY updated_at DESC LIMIT 1");
                $stmt->execute([$digits]);
                $profile = $stmt->fetch();
                if ($profile && !empty($profile['last_order_json'])) {
                    $orderData = json_decode($profile['last_order_json'], true);
                    echo json_encode(['success' => true, 'order' => $orderData]);
                    exit;
                }
            }
            echo json_encode(['success' => false, 'error' => 'No saved order found']);
            exit;
        }

        $pdo->beginTransaction();

        // 1. Generate daily queue number based on kiosk_orders table
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM kiosk_orders WHERE DATE(created_at) = CURDATE()");
        $row = $stmt->fetch();
        $seq = $row['count'] + 1;
        $queueNumber = 'A-' . str_pad($seq, 3, '0', STR_PAD_LEFT);

        $foodSubtotal = floatval($input['food'] ?? 0);
        $tipAmount = floatval($input['tip'] ?? 0);
        $totalAmount = $foodSubtotal + $tipAmount;
        
        $customerName = trim($input['name'] ?? 'Guest');
        $phone = trim($input['phone'] ?? '');
        $diningStyle = $input['dining'] ?? 'Dine-In';
        $tableTag = trim($input['tableTag'] ?? null);
        $neighborhoodBadge = $input['neighborhood'] ?? 'Night Shift Hero';

        // 2. Insert into kiosk_orders table
        $orderSql = "INSERT INTO kiosk_orders (queue_number, customer_name, phone, dining_style, table_tag, neighborhood_badge, food_subtotal, tip_amount, total_amount, payment_method, payment_status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'GCash', 'paid', NOW())";
        
        $stmt = $pdo->prepare($orderSql);
        $stmt->execute([
            $queueNumber,
            $customerName,
            $phone,
            $diningStyle,
            $tableTag,
            $neighborhoodBadge,
            $foodSubtotal,
            $tipAmount,
            $totalAmount
        ]);
        
        $orderId = $pdo->lastInsertId();

        // 3. Insert items into kiosk_order_items table
        $itemSql = "INSERT INTO kiosk_order_items (order_id, product_name, rice_option, egg_choice, custom_options, quantity, price) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $itemStmt = $pdo->prepare($itemSql);

        if (!empty($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $item) {
                $itemName = $item['name'] ?? 'Silog Meal';
                $riceOption = $item['rice'] ?? 'Garlic Rice';
                $eggChoice = $item['egg'] ?? 'Sunny-side up';
                $customOptions = $item['options'] ?? '';
                $quantity = intval($item['qty'] ?? 1);
                $price = floatval($item['price'] ?? 0);

                $itemStmt->execute([
                    $orderId,
                    $itemName,
                    $riceOption,
                    $eggChoice,
                    $customOptions,
                    $quantity,
                    $price
                ]);
            }
        }

        // 4. Handle Saved Customer Profile if requested
        if (!empty($input['saveOrder']) && !empty($phone)) {
            $profileStmt = $pdo->prepare("INSERT INTO kiosk_saved_profiles (phone_number, customer_name, neighborhood_badge, last_order_json) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name), neighborhood_badge = VALUES(neighborhood_badge), last_order_json = VALUES(last_order_json)");
            $profileStmt->execute([
                $phone,
                $customerName,
                $neighborhoodBadge,
                json_encode($input)
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'queueNumber' => $queueNumber]);

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sairene Tephteph Tapsilogan | Self-Service Kiosk</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--ink:#24221f;--gold:#c99b35;--gold-light:#f8f0dc;--cream:#fbfaf7;--line:#e9e3d7;--muted:#77736c;--shadow:0 8px 24px #33260b0d}*{box-sizing:border-box}body{margin:0;background:var(--cream);color:var(--ink);font-family:Georgia,'Times New Roman',serif;padding-bottom:94px}button,input{font:inherit}.topbar{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20;padding:14px 0}.brandmark{width:46px;height:46px;border-radius:50%;background:#24221f;color:#fff;display:grid;place-items:center;font-size:22px;border:3px double var(--gold);box-shadow:inset 0 0 0 2px #24221f;outline:1px solid #d8b75e;outline-offset:2px}.brandname{font-family:Georgia,serif;font-weight:800;letter-spacing:-.03em}.screen{display:none}.screen.active{display:block;animation:fade .24s ease}@keyframes fade{from{opacity:.45;transform:translateY(4px)}to{opacity:1;transform:none}}.hero{max-width:900px;margin:22px auto 28px;padding:38px 20px;text-align:center;background:linear-gradient(135deg,#fff,#f8f1df);border:1px solid #eee3c9;border-radius:28px;box-shadow:var(--shadow)}.hero-plate{width:100px;height:100px;margin:auto;border-radius:50%;display:grid;place-items:center;background:#fff;border:8px solid #282622;box-shadow:inset 0 0 0 4px #e7e2d8;color:#24221f;font-size:42px}.h-title{font-family:Georgia,serif;font-weight:850;letter-spacing:-.04em}.muted{color:var(--muted)}.panel,.choice-card{background:#ffffffd9;border:1px solid var(--line);border-radius:20px;box-shadow:var(--shadow)}.choice-card{padding:26px 18px;cursor:pointer;transition:.18s;height:100%}.choice-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px #33260b12}.choice-icon{font-size:34px;color:var(--gold)}.btn-gold{background:var(--gold);color:#fff;border:1px solid var(--gold);font-weight:700;border-radius:13px;padding:11px 18px}.btn-gold:hover{background:#af8123;color:white}.btn-outline-gold{border:1px solid var(--gold);color:#8d681c;border-radius:12px;font-weight:650}.btn-outline-gold:hover,.btn-outline-gold.active{background:var(--gold);color:#fff}.form-control,.form-select{border-radius:12px;padding:12px;border-color:#ded7c8}

/* Category Strip Fix */
.category-strip {
  display: flex !important;
  gap: 8px !important;
  overflow-x: auto !important;
  white-space: nowrap !important;
  padding: 8px 4px 12px 4px !important;
  margin: 0 0 12px 0 !important;
  justify-content: flex-start !important;
  align-items: center !important;
  scrollbar-width: none !important;
  -ms-overflow-style: none !important;
}
.category-strip::-webkit-scrollbar { display: none !important; }
.category-strip button {
  flex: 0 0 auto !important;
  border-radius: 999px !important;
  padding: 10px 18px !important;
  font-weight: 700 !important;
  white-space: nowrap !important;
  border: 1px solid var(--gold) !important;
  background: #fffaf0;
  color: #8d681c;
}
.category-strip button.active {
  background: var(--gold) !important;
  color: #fff !important;
}

.food-list{display:grid;grid-template-columns:1fr;gap:14px}

/* Floating & Translucent Frosted Glass Dish Cards */
.food-row {
  display: grid;
  grid-template-columns: 70px minmax(0, 1fr) auto;
  gap: 16px;
  align-items: center;
  padding: 14px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.65) !important;
  -webkit-backdrop-filter: blur(16px) saturate(180%) !important;
  backdrop-filter: blur(16px) saturate(180%) !important;
  border: 1px solid rgba(233, 227, 215, 0.75) !important;
  box-shadow: 0 10px 25px -5px rgba(51, 38, 11, 0.08), 0 8px 10px -6px rgba(51, 38, 11, 0.04) !important;
  transition: transform 0.22s ease, box-shadow 0.22s ease;
  isolation: isolate;
  min-width: 0;
}
.food-row:hover, .food-row:active {
  transform: translateY(-4px) !important;
  box-shadow: 0 18px 30px -6px rgba(51, 38, 11, 0.12), 0 10px 12px -5px rgba(51, 38, 11, 0.06) !important;
}

/* 70x70 Centered Food Art Container */
.food-art {
  flex: 0 0 70px;
  width: 70px;
  height: 70px;
  border-radius: 16px;
  background: radial-gradient(circle at 30% 20%,#fff,#f2e1bb);
  display: grid;
  place-items: center;
  overflow: hidden;
  position: relative;
  padding: 0;
}
.food-art .food-photo {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center center;
  border-radius: inherit;
}

.food-info{flex:1;min-width:0}.food-info h3{font-size:1.04rem;font-weight:800;margin:0 0 4px}.food-desc{font-size:.86rem;color:var(--muted);margin:0 0 5px}.price{font-weight:800;color:#8d681c}.quantity{display:flex;align-items:center;gap:8px;flex-shrink:0}.qty-btn{width:36px;height:36px;border-radius:11px;border:1px solid var(--line);background:#fff;font-weight:800}.qty-num{min-width:14px;text-align:center;font-weight:800}.budget-control{border:1px solid var(--line);border-radius:999px;background:#fff;padding:8px 12px;font-weight:700}.cart-bar{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid var(--line);box-shadow:0 -8px 25px #33260b12;z-index:30;padding:12px 0}.cart-bar .inner{display:flex;justify-content:space-between;align-items:center;gap:12px}.badge-neighborhood{background:var(--gold-light);color:#785817;border:1px solid #ead9aa;border-radius:999px;padding:8px 12px;font-weight:700}.subtle{font-size:.9rem;color:var(--muted)}.section-title{font-weight:850;letter-spacing:-.025em}.summary-line{display:flex;justify-content:space-between;gap:14px;padding:8px 0}.summary-total{font-size:1.3rem;font-weight:850;border-top:1px solid var(--line);padding-top:14px}.jar-wrap{width:145px;height:165px;margin:0 auto;filter:drop-shadow(0 8px 7px #a37b1c12)}.jar-svg{width:100%;height:100%;overflow:visible}.jar-fill{transition:y .55s cubic-bezier(.2,.8,.2,1),height .55s cubic-bezier(.2,.8,.2,1),fill .3s;animation:liquidShimmer 2.8s ease-in-out infinite}@keyframes liquidShimmer{0%,100%{opacity:.88}50%{opacity:1}}.quantity-stepper{display:flex;align-items:center;gap:14px}.quantity-stepper .qty-btn{width:42px;height:42px;font-size:1.2rem}.quantity-stepper output{min-width:24px;font-weight:800;font-size:1.1rem;text-align:center}.jar-shine{animation:shine 3s ease-in-out infinite}@keyframes shine{0%,100%{opacity:.35}50%{opacity:.8}}.jar-bubble{transform-box:fill-box;transform-origin:center;animation:bubble 2.4s ease-in-out infinite}@keyframes bubble{0%,100%{transform:translateY(0);opacity:.3}50%{transform:translateY(-7px);opacity:.85}}.tip-chip{border-radius:999px}.payment-card{max-width:760px;margin:auto}.qr-mark{width:42px;height:42px;border-radius:12px;background:#f7efd9;color:#9b741f;display:grid;place-items:center;font-size:23px}.modal-content{border:0;border-radius:22px;box-shadow:0 20px 60px #2118002b;overflow:hidden}.modal-header{background:#f8f0dc;border-bottom:1px solid #eadfca}.modal-footer{border-top:1px solid var(--line)}.toast-modal{background:#fffcf4;border:1px solid #e7d8b5;border-radius:18px;padding:20px;box-shadow:0 15px 45px #24180024}.toast-overlay{position:fixed;inset:0;background:#1d1a14a6;z-index:1100;display:none;align-items:center;justify-content:center;padding:18px}.toast-overlay.show{display:flex}.receipt-number{font-size:clamp(2.8rem,8vw,4.8rem);font-weight:900;letter-spacing:-.06em;color:#9a711d}.mini-label{font-size:.75rem;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);font-weight:800}

.menu-banner{margin:6px 0 22px;padding:25px 22px 27px;text-align:center;background:linear-gradient(135deg,#fffdf8,#f5e8c8);border:1px solid #dfc783;border-radius:22px;box-shadow:0 8px 24px #33260b0d;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:18px;text-align:left;min-height:150px}
.banner-eyebrow{font:800 .72rem system-ui,sans-serif;letter-spacing:.18em;color:#98732b;margin-bottom:6px}.menu-banner h1{font:700 clamp(1.8rem,4vw,2.65rem) Georgia,serif;margin:0 0 8px;color:#24221f}.menu-banner p{font:.98rem system-ui,sans-serif;color:#77736c;margin:0;line-height:1.45}
.menu-banner .banner-brandmark{width:76px;height:76px;border-radius:50%;background:#24221f;color:#fff;display:grid;place-items:center;font-size:35px;border:4px double var(--gold);box-shadow:inset 0 0 0 3px #24221f;outline:1px solid #d8b75e;outline-offset:3px}
.menu-banner .banner-copy{text-align:center;min-width:0}
.banner-budget-wrap{width:220px;max-width:100%;display:flex;flex-direction:column;gap:7px;align-items:stretch}
.menu-banner .banner-budget{width:100%;display:flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;border:1px solid #ddcea4;background:#fffaf0;color:#5e4b22;border-radius:13px;padding:11px 14px;font-weight:800}
.budget-gauge-track{height:8px;width:100%;background:rgba(201,155,53,.18);border-radius:99px;overflow:hidden;border:1px solid rgba(201,155,53,.22)}
.budget-gauge-fill{height:100%;width:0;background:#c99b35;border-radius:99px;transition:width .25s ease,background .2s ease}
.budget-current{font:800 .92rem system-ui,sans-serif;color:#5e4b22;text-align:right;font-variant-numeric:tabular-nums}
.budget-current.over-budget{color:#d13d3d}
.budget-gauge-fill.over-budget{background:#d13d3d}

/* Fixed Overlap: Flow cleanly below budget info */
.budget-over-message {
  margin: 6px 0 0 0;
  color: #5e4b22;
  background: #fffaf0;
  border: 1px solid #ddcea4;
  padding: 8px 10px;
  border-radius: 12px;
  font-family: Georgia, 'Times New Roman', serif;
  font-weight: 800;
  font-size: 0.8rem;
  line-height: 1.35;
  text-align: left;
  position: relative;
  z-index: 5;
}

.category-intro{background:#fffdf8;border:1px solid #eee4ce;border-radius:14px;padding:13px 16px;margin:-2px 0 14px}
.category-intro h3{font-size:1rem;font-weight:850;margin:0 0 3px}.category-intro p{font-size:.9rem;color:var(--muted);margin:0;line-height:1.4}
.duo-options{display:grid;gap:9px}.duo-option{display:flex;align-items:center;gap:10px;width:100%;padding:12px;border:1px solid #e3d7b9;border-radius:13px;background:#fffdf8;text-align:left;color:var(--ink);transition:.15s}.duo-option.selected{border-color:var(--gold);background:#f8f0dc;box-shadow:inset 0 0 0 1px var(--gold)}.duo-option .duo-check{width:22px;height:22px;border:1.5px solid #b9a36e;border-radius:50%;display:grid;place-items:center;flex:0 0 22px;color:transparent}.duo-option.selected .duo-check{background:var(--gold);border-color:var(--gold);color:#fff}.duo-option .duo-name{font-weight:800}.duo-option .duo-price{display:block;font-size:.85rem;color:#80601e;margin-top:2px}

.neighborhood-options{display:flex;flex-wrap:wrap;gap:8px}
.neighborhood-chip{border:1px solid #e4d6b4;border-radius:999px;padding:8px 12px;color:#6f541c;font-weight:700;font-size:.88rem;cursor:pointer;transition:all .15s}
.neighborhood-chip span{margin-right:4px}
.neighborhood-chip.selected{background:#c99b35;color:#fff;border-color:#b78a28;box-shadow:0 4px 12px #c99b3533}
.cart-notifications{position:fixed;right:18px;bottom:92px;z-index:1000;display:flex;flex-direction:column;gap:10px;align-items:flex-end;pointer-events:none;max-width:min(390px,calc(100vw - 28px))}
.cart-toast{display:flex;align-items:flex-start;gap:10px;background:linear-gradient(135deg,#fff4d2f5,#f0ce78f5);color:#4b3710;border:1px solid #d7ad48;border-radius:15px;padding:12px 15px;box-shadow:0 10px 28px #4c35052b;backdrop-filter:blur(14px);font:700 .9rem/1.4 system-ui,sans-serif;opacity:0;transform:translateY(8px);animation:toastIn .22s ease forwards,toastOut .35s ease 3.65s forwards}
.cart-toast i{font-size:1.2rem;color:#8d681c;flex-shrink:0;margin-top:1px}
@keyframes toastIn{to{opacity:1;transform:translateY(0)}}@keyframes toastOut{to{opacity:0;transform:translateY(8px)}}

@media(max-width:576px){
  .hero{padding:28px 16px;margin-top:10px}
  .food-row{grid-template-columns:70px minmax(0,1fr);gap:11px;padding:11px}
  .food-art{width:70px;height:70px}
  .quantity{grid-column:2;justify-content:space-between;width:100%;margin-top:-2px;min-width:0}
  .cart-bar .btn{padding:10px 12px}.topbar .brandname{font-size:1rem}
  .menu-banner{grid-template-columns:auto minmax(0,1fr);gap:13px;padding:17px 14px}.menu-banner .banner-brandmark{width:56px;height:56px;font-size:26px}.menu-banner .banner-copy{text-align:left}.menu-banner .banner-eyebrow{font-size:.62rem;letter-spacing:.12em}.menu-banner h1{font-size:1.55rem}.menu-banner p{font-size:.84rem}.banner-budget-wrap{grid-column:1/-1;width:100%;}.cart-notifications{right:12px;bottom:86px}.budget-current{text-align:left}
}
</style></head>
<body>
<header class="topbar"><div class="container d-flex align-items-center justify-content-between gap-3"><div class="d-flex align-items-center gap-2"><div class="brandmark"><i class="bi bi-egg-fried"></i></div><div><div class="brandname">Sairene Tephteph Tapsilogan</div><div class="small muted">Hot silogs, made your way</div></div></div></div></header>
<main class="container py-3 py-md-4">
<section id="screen-welcome" class="screen active"><div class="hero"><div class="hero-plate"><i class="bi bi-egg-fried"></i></div><h1 class="h-title mt-4">Kain na, tara!</h1><p class="muted mb-0">Welcome to Sairene Tephteph Tapsilogan. What sounds good today?</p></div><h2 class="h4 fw-bold text-center mb-3">How will you be dining?</h2><div class="row g-3 justify-content-center mx-auto" style="max-width:760px"><div class="col-6"><button class="choice-card w-100" onclick="chooseDining('Dine-In')"><i class="bi bi-cup-hot choice-icon"></i><h3 class="h5 fw-bold mt-3">Dine-In</h3><p class="subtle mb-0">Settle in and enjoy</p></button></div><div class="col-6"><button class="choice-card w-100" onclick="chooseDining('Take-Out')"><i class="bi bi-bag-heart choice-icon"></i><h3 class="h5 fw-bold mt-3">Take-Out</h3><p class="subtle mb-0">Pack it up to go</p></button></div></div></section>
<section id="screen-table" class="screen"><button class="btn btn-link px-0 muted mb-2" onclick="showScreen('screen-welcome')"><i class="bi bi-arrow-left me-1"></i>Back</button><div class="panel p-4 p-md-5 mx-auto text-center" style="max-width:650px"><div class="choice-icon mb-2"><i class="bi bi-people"></i></div><h1 class="h-title h2">Got a table or group name?</h1><p class="muted">Add a table number or group tag so your order is easier to identify. It's completely optional.</p><label class="form-label fw-semibold text-start d-block" for="tableTag">Table number or group tag</label><input class="form-control form-control-lg mb-3" id="tableTag" placeholder="e.g. Table 4, Team Sampaguita"><button class="btn btn-gold w-100" onclick="confirmTable()">Continue <i class="bi bi-arrow-right ms-1"></i></button><button class="btn btn-link muted mt-2" onclick="confirmTable(true)">Skip for now</button></div></section>
<section id="screen-reorder" class="screen"><button class="btn btn-link px-0 muted mb-2" onclick="goBackFromReorder()"><i class="bi bi-arrow-left me-1"></i>Back</button><div class="panel p-4 p-md-5 mx-auto text-center" style="max-width:650px"><div class="choice-icon mb-3"><i class="bi bi-arrow-repeat"></i></div><h1 class="h-title h2">Skip the hassle and reorder?</h1><p class="muted">Back for another round? If you saved an order with us before, we can bring it back so you don't have to pick everything all over again.</p><div class="text-start mt-4"><label class="form-label fw-semibold" for="reorderDigits">Last four digits of your saved phone number</label><input id="reorderDigits" class="form-control form-control-lg" inputmode="numeric" maxlength="4" placeholder="e.g. 1234"></div><button class="btn btn-gold w-100 mt-3" onclick="findReorder()">Find My Last Order</button><button class="btn btn-outline-gold w-100 mt-2" onclick="startFresh()">No Thanks, Start Fresh</button><div id="reorderResult" class="mt-3"></div></div></section>
<section id="screen-menu" class="screen"><div class="menu-banner"><div class="banner-brandmark"><i class="bi bi-egg-fried"></i></div><div class="banner-copy"><div class="banner-eyebrow">MADE FRESH FOR YOU</div><h1>Kain na, ka-silog!</h1><p>Pick your cravings, customize your plate, and we'll take care of the rest!</p></div><div class="banner-budget-wrap"><button type="button" class="banner-budget" onclick="openBudget()" id="budgetMenuButton"><i class="bi bi-wallet2"></i><span id="budgetMenuLabel">Your Budget</span></button><div class="budget-gauge-track" aria-label="Current cart total relative to budget"><div id="budgetGaugeFill" class="budget-gauge-fill"></div></div><div id="budgetCurrent" class="budget-current">₱0.00 / —</div><p id="budgetOverMessage" class="budget-over-message d-none">You're over your budget, but don't worry, you'll still be able to check out.</p></div></div><div class="mb-3"><h2 class="h-title h3 mb-1">Find your favorite</h2><div class="muted">Freshly made, just the way you like it.</div></div><div class="category-strip mb-2"><button class="btn btn-outline-gold active" data-cat="all" onclick="filterMenu('all',this)"><i class="bi bi-grid me-1"></i>All Items</button><button class="btn btn-outline-gold" data-cat="fame" onclick="filterMenu('fame',this)"><i class="bi bi-trophy me-1"></i>Hall of Fame</button><button class="btn btn-outline-gold" data-cat="solo" onclick="filterMenu('solo',this)"><i class="bi bi-egg-fried me-1"></i>Solo Cravings</button><button class="btn btn-outline-gold" data-cat="puyat" onclick="filterMenu('puyat',this)"><i class="bi bi-lightning-charge me-1"></i>Puyat Fuel</button><button class="btn btn-outline-gold" data-cat="ala" onclick="filterMenu('ala',this)"><i class="bi bi-basket me-1"></i>À La Carte</button></div><div id="categoryIntro" class="category-intro"><h3>Something delicious for every craving</h3><p>Take a look around and find the silog that feels just right.</p></div><div id="menuList" class="food-list"></div></section>
<section id="screen-review" class="screen"><button class="btn btn-link px-0 muted mb-2" onclick="backToMenuFromReview()"><i class="bi bi-arrow-left me-1"></i>Back to menu</button><h1 class="h-title h2 mb-1">Review your order</h1><p class="muted mb-4">One last look before payment.</p><div class="row g-3"><div class="col-lg-7"><div class="panel p-3 p-md-4"><h2 class="h5 fw-bold mb-3">Your food</h2><div id="reviewItems"></div><div class="summary-line"><span class="muted">Food bill</span><strong id="reviewSubtotal">₱0.00</strong></div></div></div><div class="col-lg-5"><div class="panel p-3 p-md-4"><h2 class="h5 fw-bold mb-3">A little about you</h2><label class="form-label fw-semibold" for="customerName">Your name <span class="text-danger">*</span></label><input id="customerName" class="form-control mb-3" placeholder="Name or nickname"><label class="form-label fw-semibold d-block">Choose your neighborhood badge</label><input type="hidden" id="neighborhood" value=""><div class="neighborhood-options mb-3" role="group" aria-label="Neighborhood badges"><button type="button" class="neighborhood-chip" data-neighborhood="Night Shift Hero" onclick="selectNeighborhood(this)"><span>🌙</span> Night Shift Hero</button><button type="button" class="neighborhood-chip" data-neighborhood="Local Regular" onclick="selectNeighborhood(this)"><span>🏡</span> Local Regular</button><button type="button" class="neighborhood-chip" data-neighborhood="Early Bird" onclick="selectNeighborhood(this)"><span>🐣</span> Early Bird</button><button type="button" class="neighborhood-chip" data-neighborhood="Weekend Foodie" onclick="selectNeighborhood(this)"><span>🍜</span> Weekend Foodie</button><button type="button" class="neighborhood-chip" data-neighborhood="First-Time Visitor" onclick="selectNeighborhood(this)"><span>✨</span> First-Time Visitor</button></div><label class="form-label fw-semibold" for="phone">Phone number <span class="muted fw-normal">(optional)</span></label><input id="phone" class="form-control" inputmode="tel" placeholder="09XX XXX XXXX"><div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="saveOrder"><label class="form-check-label small" for="saveOrder">Save this order for next time</label></div><div class="small muted mt-1">Only saved when you choose this option and provide a phone number.</div><div id="reviewBudgetWarning" class="alert alert-warning mt-3 d-none mb-0"></div></div></div></div><button class="btn btn-gold btn-lg w-100 mt-4" onclick="proceedPayment()">Proceed to Payment <i class="bi bi-arrow-right ms-1"></i></button></section>
<section id="screen-payment" class="screen"><button class="btn btn-link px-0 muted mb-2" onclick="backToReviewFromPayment()"><i class="bi bi-arrow-left me-1"></i>Back to review</button><div class="payment-card"><div class="text-center mb-3"><h1 class="h-title h2 mb-1">Payment</h1><p class="muted">Choose a tip if you'd like, then review your total.</p></div><div class="panel p-3 p-md-4 mb-3"><div class="row align-items-center g-3"><div class="col-md-5 text-center"><div class="jar-wrap"><svg class="jar-svg" viewBox="0 0 120 145" role="img" aria-label="Animated gold tip jar"><defs><clipPath id="jarClip"><path d="M31 27 Q22 29 22 42 L22 119 Q22 132 38 132 H82 Q98 132 98 119 L98 42 Q98 29 89 27 Z"/></clipPath><linearGradient id="goldLiquid" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f6d879"/><stop offset="1" stop-color="#c99b35"/></linearGradient></defs><ellipse cx="60" cy="136" rx="39" ry="5" fill="#c99b35" opacity=".12"/><rect x="38" y="8" width="44" height="12" rx="4" fill="#b88a2b"/><rect x="33" y="18" width="54" height="9" rx="4" fill="#e5c56c" stroke="#b88a2b" stroke-width="1.5"/><g clip-path="url(#jarClip)"><rect id="jarFill" x="22" y="132" width="76" height="0" fill="url(#goldLiquid)" class="jar-fill"/><circle class="jar-bubble" cx="42" cy="113" r="3" fill="#fff3bd"/><circle class="jar-bubble" cx="75" cy="91" r="2.5" fill="#fff3bd" style="animation-delay:.7s"/><circle class="jar-bubble" cx="57" cy="72" r="2" fill="#fff3bd" style="animation-delay:1.2s"/></g><path d="M31 27 Q22 29 22 42 L22 119 Q22 132 38 132 H82 Q98 132 98 119 L98 42 Q98 29 89 27 Z" fill="#ffffff60" stroke="#bca66f" stroke-width="3"/><path d="M37 39 L37 106" stroke="#fff" stroke-width="5" stroke-linecap="round" class="jar-shine"/><path d="M45 34 L45 55" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".7"/></svg></div><div class="fw-bold">A little extra love?</div><div class="small muted">Totally optional</div></div><div class="col-md-7"><div class="d-flex justify-content-between align-items-center"><label for="tipSlider" class="fw-bold">Kitchen tip</label><strong id="tipDisplay">₱0.00</strong></div><input id="tipSlider" class="form-range mt-3" type="range" min="0" max="200" step="10" value="0" oninput="setTip(this.value)"><div class="d-flex flex-wrap gap-2 mt-2"><button class="btn btn-outline-gold tip-chip" onclick="setTip(0)">No tip</button><button class="btn btn-outline-gold tip-chip" onclick="setTip(20)">₱20</button><button class="btn btn-outline-gold tip-chip" onclick="setTip(50)">₱50</button><button class="btn btn-outline-gold tip-chip" onclick="setTip(100)">₱100</button><button class="btn btn-outline-gold tip-chip" onclick="setTip(150)">₱150</button><button class="btn btn-outline-gold tip-chip" onclick="setTip(200)">₱200</button></div></div></div></div><div class="panel p-3 p-md-4 mb-3"><div class="summary-line"><span class="muted">Food bill</span><strong id="paymentFood">₱0.00</strong></div><div class="summary-line"><span class="muted">Tip</span><strong id="paymentTip">₱0.00</strong></div><div class="summary-line border-top mt-2 pt-3"><span class="muted">Calculation</span><span id="paymentCalculation" class="fw-semibold text-end">₱0.00 + ₱0.00</span></div><div class="summary-line align-items-center mb-0"><span class="h5 fw-bold mb-0">Total Bill</span><span class="h3 fw-bold mb-0" id="paymentTotal">₱0.00</span></div></div><div class="panel p-3 p-md-4 mb-3"><div class="d-flex align-items-center gap-3 mb-3"><div class="qr-mark"><i class="bi bi-qr-code"></i></div><div><h2 class="h5 fw-bold mb-1">GCash</h2><div class="small muted">Payment details</div></div></div><div class="summary-line"><span class="muted">GCash number</span><strong>09270466231</strong></div><div class="summary-line"><span class="muted">Account name</span><strong>Sarippudin Aming</strong></div><button class="btn btn-outline-gold w-100 mt-2" onclick="copyGcash()"><i class="bi bi-copy me-1"></i>Copy GCash Number</button></div><div class="panel p-3 p-md-4"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="paymentAcknowledged"><label class="form-check-label" for="paymentAcknowledged">I have completed the payment.</label></div><button class="btn btn-gold btn-lg w-100" onclick="confirmPayment()">Confirm Order <i class="bi bi-check2 ms-1"></i></button></div><div class="panel p-3 mt-3"><button class="btn btn-outline-gold w-100" onclick="toggleSplit()"><i class="bi bi-people me-1"></i>Split the bill (optional)</button><div id="splitPanel" class="mt-3 d-none"><label class="form-label fw-semibold" for="splitCount">How many people are sharing?</label><input class="form-control" id="splitCount" type="number" min="1" step="1" value="2" oninput="updateSplit()"><div class="mt-2 fw-bold" id="splitResult">Estimated share: ₱0.00 per person</div></div></div></div></section>
<section id="screen-success" class="screen"><div class="panel p-4 p-md-5 mx-auto text-center" style="max-width:650px"><div class="display-3" style="color:#b88a2b"><i class="bi bi-check-circle-fill"></i></div><h1 class="h-title h2 mt-2">Order confirmed!</h1><p class="muted">Thank you for choosing Sairene Tephteph Tapsilogan.</p><div class="my-4 p-4 rounded-4" style="background:var(--gold-light);border:1px solid #ead9aa"><div class="mini-label">Your queue number</div><div class="receipt-number" id="queueNumber">—</div><div class="fw-bold" id="successName"></div><span class="badge-neighborhood d-inline-block mt-2" id="successBadge"></span><div class="mt-2 muted" id="successDining"></div><div class="muted small" id="successTag"></div></div><div class="text-start panel p-3 mb-3"><h2 class="h6 fw-bold">Order summary</h2><div id="successItems"></div><div class="summary-line"><span class="muted">Food bill</span><strong id="successFood"></strong></div><div class="summary-line"><span class="muted">Tip</span><strong id="successTip"></strong></div><div class="summary-line border-top pt-3"><strong>Total bill</strong><strong id="successTotal"></strong></div></div><button class="btn btn-gold btn-lg w-100" onclick="resetKiosk()">Start New Order</button></div></section>
</main><div id="cartNotifications" class="cart-notifications" aria-live="polite" aria-atomic="false"></div><div class="cart-bar" id="cartBar"><div class="container inner"><div><div class="fw-bold"><i class="bi bi-basket2 me-1" style="color:var(--gold)"></i><span id="cartCount">0 items</span></div><div class="muted small" id="cartTotal">₱0.00</div></div><button class="btn btn-gold" onclick="openReview()">View Order <i class="bi bi-arrow-right ms-1"></i></button></div></div>
<div class="modal fade" id="customModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5 fw-bold" id="customTitle">Customize your meal</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div id="customItemName" class="fw-bold mb-1"></div><p id="customItemDesc" class="food-desc mb-3"></p><div id="riceSection" class="mb-3"><label class="form-label fw-semibold"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 24 24" class="me-1" style="vertical-align: -0.125em;"><path d="M12 3a3 3 0 0 0-3 3h6a3 3 0 0 0-3-3zm8.5 5h-17a1.5 1.5 0 0 0-1.5 1.5c0 4.35 3.12 7.95 7.25 8.75V20a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-1.75c4.13-.8 7.25-4.4 7.25-8.75a1.5 1.5 0 0 0-1.5-1.5z"/></svg>Rice Option</label><div class="d-grid gap-2"><label class="btn btn-outline-gold text-start"><input class="form-check-input me-2" type="radio" name="rice" value="Garlic Rice" checked>Garlic Rice</label><label class="btn btn-outline-gold text-start"><input class="form-check-input me-2" type="radio" name="rice" value="Plain Rice">Plain Rice</label></div></div><div id="eggSection" class="mb-3"><label class="form-label fw-semibold"><i class="bi bi-egg-fried me-1" aria-hidden="true"></i>Egg Option</label><div class="d-grid gap-2"><label class="btn btn-outline-gold text-start"><input class="form-check-input me-2" type="radio" name="rice-egg" value="Sunny-side up" checked>Sunny-side up</label><label class="btn btn-outline-gold text-start"><input class="form-check-input me-2" type="radio" name="rice-egg" value="Scrambled">Scrambled</label><label class="btn btn-outline-gold text-start"><input class="form-check-input me-2" type="radio" name="rice-egg" value="Well-done">Well-done</label></div></div><div id="duoSection" class="mb-3 d-none"><div class="form-label fw-semibold"><i class="bi bi-people-fill me-1" aria-hidden="true"></i>Combo Upgrade <span class="muted fw-normal">(optional)</span></div><div id="duoOptions" class="duo-options"></div><input type="hidden" id="duoCombo" value=""><div class="small muted mt-2">Tap an option to upgrade, or keep your solo meal.</div></div><label class="form-label fw-semibold d-block">Quantity</label><div class="quantity-stepper"><button type="button" class="qty-btn" onclick="changeCustomQty(-1)" aria-label="Decrease quantity">−</button><output id="customQtyDisplay" class="qty-num">1</output><button type="button" class="qty-btn" onclick="changeCustomQty(1)" aria-label="Increase quantity">+</button></div><input id="customQty" type="hidden" value="1"></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gold" onclick="addCustomizedItem()">Add to Order</button></div></div></div></div>
<div class="toast-overlay" id="popupOverlay"><div class="toast-modal w-100" style="max-width:430px"><div class="d-flex align-items-start gap-3"><div class="choice-icon"><i class="bi bi-info-circle"></i></div><div class="flex-grow-1"><h2 class="h5 fw-bold" id="popupTitle">A quick note</h2><div id="popupMessage" class="muted"></div></div></div><div id="popupActions" class="d-flex gap-2 justify-content-end mt-4 flex-wrap"></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const money = n => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const products = [
    { id: 'tapsilog', name: 'Tapsilog', desc: 'Sweet-savory marinated beef tapa served with garlic rice and a sunny egg.', price: 90, cat: 'solo', icon: 'bi-egg-fried', fame: true, protein: 'tapa' },
    { id: 'bagnetsilog', name: 'Bagnetsilog', desc: 'Crunchy, golden bagnet paired with garlic rice and a sunny egg.', price: 90, cat: 'solo', icon: 'bi-fire', fame: true, protein: 'bagnet' },
    { id: 'sisigsilog', name: 'Sisigsilog', desc: 'Sizzling, savory sisig served with garlic rice and a sunny egg.', price: 90, cat: 'solo', icon: 'bi-egg-fried', fame: true, protein: 'sisig' },
    { id: 'liemposilog', name: 'Liemposilog', desc: 'Juicy grilled pork liempo with garlic rice and a sunny egg.', price: 90, cat: 'solo', icon: 'bi-fire', fame: true, protein: 'liempo' },
    { id: 'malingsilog', name: 'Malingsilog', desc: 'A nostalgic luncheon-meat favorite with garlic rice and a sunny egg.', price: 90, cat: 'solo', icon: 'bi-egg-fried', protein: 'maling' },
    { id: 'tocilog', name: 'Tocilog', desc: 'Sweet, tender tocino with garlic rice and a sunny egg.', price: 90, cat: 'solo', icon: 'bi-egg-fried', protein: 'tocino' },
    { id: 'hungariansilog', name: 'Hungariansilog', desc: 'A hearty Hungarian sausage served with garlic rice and a sunny egg.', price: 90, cat: 'solo', icon: 'bi-fire', protein: 'hungarian' },
    { id: 'hotdogsilog', name: 'Hotdogsilog', desc: 'A familiar, juicy hotdog with garlic rice and a sunny egg.', price: 80, cat: 'solo', icon: 'bi-egg-fried', protein: 'hotdog' },
    { id: 'bagnet-hungarian', name: 'Bagnet + Hungarian Combo', desc: 'A satisfying double-protein combo with garlic rice and a sunny egg.', price: 140, cat: 'puyat', icon: 'bi-lightning-charge', proteins: ['bagnet', 'hungarian'] },
    { id: 'tapa-hungarian', name: 'Tapa + Hungarian Combo', desc: 'A satisfying double-protein combo with garlic rice and a sunny egg.', price: 140, cat: 'puyat', icon: 'bi-lightning-charge', proteins: ['tapa', 'hungarian'] },
    { id: 'tapa-bagnet', name: 'Tapa + Bagnet Silog Combo', desc: 'Sweet-savory beef tapa and crunchy bagnet in one hearty silog combo.', price: 140, cat: 'puyat', icon: 'bi-lightning-charge', proteins: ['tapa', 'bagnet'] },
    { id: 'liempo-hungarian', name: 'Liempo + Hungarian Combo', desc: 'Juicy pork liempo and smoky Hungarian sausage for a bigger bite.', price: 140, cat: 'puyat', icon: 'bi-lightning-charge', proteins: ['liempo', 'hungarian'] },
    { id: 'sisig-hungarian', name: 'Sisig + Hungarian Combo', desc: 'Savory sisig and smoky Hungarian sausage come together in one filling combo.', price: 140, cat: 'puyat', icon: 'bi-lightning-charge', proteins: ['sisig', 'hungarian'] },
    { id: 'tapa-portion', name: 'Special Tapa Portion', desc: 'A generous serving of our sweet-savory marinated beef tapa, perfect as an extra ulam.', price: 130, cat: 'ala', icon: 'bi-basket' },
    { id: 'bagnet-portion', name: 'Special Bagnet Portion', desc: 'A generous serving of golden, crunchy bagnet for extra indulgence.', price: 130, cat: 'ala', icon: 'bi-basket' },
    { id: 'sisig-portion', name: 'Special Sisig Portion', desc: 'A flavorful serving of savory sisig, great for sharing or adding to your meal.', price: 130, cat: 'ala', icon: 'bi-basket' },
    { id: 'hungarian-portion', name: 'Hungarian Portion', desc: 'A hearty serving of smoky Hungarian sausage to round out your plate.', price: 130, cat: 'ala', icon: 'bi-basket' },
    { id: 'garlic-rice', name: 'Garlic Rice', desc: 'Fluffy rice tossed with fragrant garlic for a flavorful extra scoop.', price: 20, cat: 'ala', icon: 'bi-basket' },
    { id: 'plain-rice', name: 'Plain Rice', desc: 'A simple, comforting serving of freshly steamed rice.', price: 15, cat: 'ala', icon: 'bi-basket' },
    { id: 'extra-egg', name: 'Extra Egg', desc: 'Add one more egg, cooked just the way you like it.', price: 15, cat: 'ala', icon: 'bi-egg' }
  ];

  let dining = '', tableTag = '', cart = [], tip = 0, budget = null, filter = 'all', customId = null, queueSeq = 1, reorderCandidate = null;
  let modal;

  window.showScreen = function(id) {
    const target = $(id);
    if (!target) return;
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    target.classList.add('active');
    $('cartBar').style.display = (id === 'screen-menu' && cart.length) ? 'block' : 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (id === 'screen-menu') { renderMenu(); updateCartBar(); updateBudgetButtons(); updateBudgetDisplay(); }
    if (id === 'screen-review') renderReview();
    if (id === 'screen-payment') updatePayment();
  };

  window.closePopup = function() { $('popupOverlay').classList.remove('show'); };
  window.popup = function(title, message, actions = [{ label: 'Okay', class: 'btn-gold', run: window.closePopup }]) {
    $('popupTitle').textContent = title;
    $('popupMessage').textContent = message;
    $('popupActions').innerHTML = '';
    actions.forEach(a => {
      let b = document.createElement('button');
      b.className = 'btn ' + (a.class || 'btn-outline-secondary');
      b.textContent = a.label;
      b.onclick = () => { window.closePopup(); if (a.run) a.run(); };
      $('popupActions').appendChild(b);
    });
    $('popupOverlay').classList.add('show');
  };

  window.chooseDining = function(style) {
    dining = style;
    tableTag = '';
    $('tableTag').value = '';
    if (style === 'Dine-In') showScreen('screen-table');
    else showScreen('screen-reorder');
  };

  window.confirmTable = function(skip) {
    tableTag = skip ? '' : $('tableTag').value.trim();
    showScreen('screen-reorder');
  };

  window.goBackFromReorder = function() {
    if (dining === 'Dine-In') showScreen('screen-table');
    else showScreen('screen-welcome');
  };

  window.startFresh = function() {
    cart = [];
    reorderCandidate = null;
    tip = 0;
    setTip(0);
    updateBudgetDisplay();
    showScreen('screen-menu');
  };

  window.findReorder = function() {
    const digits = $('reorderDigits').value.replace(/\D/g, '');
    if (digits.length !== 4) {
      popup('Just four digits', 'Please enter the last four digits of the phone number used to save your order.');
      return;
    }
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'lookup_reorder', digits: digits })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.order) {
            const found = data.order;
            reorderCandidate = found;
            $('reorderResult').innerHTML = '';
            popup('Found your last order!', 'Would you like us to bring back ' + found.items.length + ' saved item configuration(s) from your last order?', [
              { label: 'Restore My Order', class: 'btn-gold', run: () => { cart = found.items.map(x => ({ ...x })); tip = 0; showScreen('screen-menu'); popup('Welcome back!', 'Your saved order is back. You can change quantities or add anything else.', [{ label: 'Let’s go', class: 'btn-gold' }]); } },
              { label: 'Start Fresh', class: 'btn-outline-secondary', run: () => { cart = []; showScreen('screen-menu'); } }
            ]);
        } else {
            let saved = [];
            try { saved = JSON.parse(localStorage.getItem('saireneSavedOrders') || '[]'); } catch (e) {}
            const found = [...saved].reverse().find(o => String(o.phone || '').replace(/\D/g, '').endsWith(digits));
            if (!found) {
              $('reorderResult').innerHTML = '<div class="alert alert-light border mt-3">We couldn’t find a saved order with those digits. You can try again or start fresh.</div>';
              return;
            }
            reorderCandidate = found;
            $('reorderResult').innerHTML = '';
            popup('Found your last order!', 'Would you like us to bring back ' + found.items.length + ' saved item configuration(s) from your last order?', [
              { label: 'Restore My Order', class: 'btn-gold', run: () => { cart = found.items.map(x => ({ ...x })); tip = 0; showScreen('screen-menu'); popup('Welcome back!', 'Your saved order is back. You can change quantities or add anything else.', [{ label: 'Let’s go', class: 'btn-gold' }]); } },
              { label: 'Start Fresh', class: 'btn-outline-secondary', run: () => { cart = []; showScreen('screen-menu'); } }
            ]);
        }
    })
    .catch(() => {
        let saved = [];
        try { saved = JSON.parse(localStorage.getItem('saireneSavedOrders') || '[]'); } catch (e) {}
        const found = [...saved].reverse().find(o => String(o.phone || '').replace(/\D/g, '').endsWith(digits));
        if (!found) {
          $('reorderResult').innerHTML = '<div class="alert alert-light border mt-3">We couldn’t find a saved order with those digits. You can try again or start fresh.</div>';
          return;
        }
        reorderCandidate = found;
        $('reorderResult').innerHTML = '';
        popup('Found your last order!', 'Would you like us to bring back ' + found.items.length + ' saved item configuration(s) from your last order?', [
          { label: 'Restore My Order', class: 'btn-gold', run: () => { cart = found.items.map(x => ({ ...x })); tip = 0; showScreen('screen-menu'); } },
          { label: 'Start Fresh', class: 'btn-outline-secondary', run: () => { cart = []; showScreen('screen-menu'); } }
        ]);
    });
  };

  const productImages = {
    'tapsilog':'tapsilog.png','bagnetsilog':'bagnetsilog.png','sisigsilog':'sisigsilog.png','liemposilog':'liemposilog.png','malingsilog':'malingsilog.png','tocilog':'tocilog.png','hungariansilog':'hungariansilog.png','hotdogsilog':'hotsilog.png','bagnet-hungarian':'bagnet_hungarian.png','tapa-hungarian':'tapa_hungarian.png','tapa-bagnet':'tapa_bagnetsilog.png','liempo-hungarian':'liempo_hungarian.png','sisig-hungarian':'sisig_hungarian.png','tapa-portion':'special_tapa.png','bagnet-portion':'special_bagnet.png','sisig-portion':'special_sisig.png','hungarian-portion':'hungarian.png','garlic-rice':'garlic_rice.png','plain-rice':'plain_rice.png','extra-egg':'egg.png'
  };

  function renderMenu() {
    let arr = products.filter(p => filter === 'all' || (filter === 'solo' && p.cat === 'solo') || (filter === 'puyat' && p.cat === 'puyat') || (filter === 'fame' && p.fame) || (filter === 'ala' && p.cat === 'ala'));
    $('menuList').innerHTML = arr.map(p => {
      const count = cart.filter(c => c.id === p.id).reduce((s, c) => s + c.qty, 0);
      const image = productImages[p.id];
      const art = image ? `<div class="food-art"><img class="food-photo" src="${encodeURIComponent(image)}" alt="${p.name}" loading="lazy" onerror="this.remove();this.parentElement.innerHTML='<i class=\\&quot;bi ${p.icon}\\&quot;></i>'"></div>` : `<div class="food-art"><i class="bi ${p.icon}"></i></div>`;
      return `<article class="food-row">${art}<div class="food-info" onclick="openCustomize('${p.id}')" role="button" tabindex="0"><h3>${p.name}</h3><p class="food-desc">${p.desc}</p><div class="price">${money(p.price)}</div></div><div class="quantity">${count ? `<button class="qty-btn" aria-label="Decrease ${p.name}" onclick="menuQty('${p.id}',-1)">−</button><span class="qty-num">${count}</span><button class="qty-btn" aria-label="Increase ${p.name}" onclick="menuQty('${p.id}',1)">+</button>` : `<button class="btn btn-outline-gold" onclick="openCustomize('${p.id}')">Add</button>`}</div></article>`;
    }).join('') || '<div class="panel p-4 text-center muted">No items in this category.</div>';
  }

  const categoryCopy = {
    all: ['Something delicious for every craving', 'Take a look around and find the silog that feels just right.'],
    solo: ['Solo Cravings', 'Your own comforting silog plate, made for one very happy appetite.'],
    puyat: ['Puyat Fuel', 'Big, bold combos for late nights, long days, and extra-hungry moments.'],
    fame: ['Hall of Fame', 'Customer-favorite classics that deserve a spot on your must-try list.'],
    ala: ['À La Carte', 'Add a little extra love to your plate with your favorite sides and ulam.']
  };

  window.filterMenu = function(f, btn) {
    filter = f;
    document.querySelectorAll('[data-cat]').forEach(b => b.classList.toggle('active', b.dataset.cat === f));
    const copy = categoryCopy[f] || categoryCopy.all;
    $('categoryIntro').innerHTML = '<h3>' + copy[0] + '</h3><p>' + copy[1] + '</p>';
    renderMenu();
  };

  function cartKey(x) { return x.id + '|' + (x.rice || '') + '|' + (x.egg || ''); }

  function showCartToast(items) {
    const host = $('cartNotifications');
    if (!host) return;
    const toast = document.createElement('div');
    toast.className = 'cart-toast';
    const detail = items.map(x => `${x.qty} order${x.qty === 1 ? '' : 's'} of ${x.name}`).join(', ');
    toast.innerHTML = '<i class="bi bi-bag-check-fill"></i><span>You added ' + detail + ' to your cart</span>';
    host.appendChild(toast);
    setTimeout(() => toast.remove(), 4100);
  }

  function addItem(id, qty = 1, rice = '', egg = '') {
    const p = products.find(x => x.id === id);
    if (!p) return;
    const candidate = { id: p.id, name: p.name, price: p.price, qty, rice, egg, options: [rice, egg].filter(Boolean).join(' · ') };
    let same = cart.find(c => cartKey(c) === cartKey(candidate));
    if (same) same.qty += qty;
    else cart.push(candidate);
    updateCartBar();
    updateBudgetDisplay();
    renderMenu();
    showCartToast([{ name: p.name, qty }]);
  }

  window.changeCustomQty = function(d) {
    const n = Math.max(1, Math.min(99, (parseInt($('customQty').value, 10) || 1) + d));
    $('customQty').value = n;
    $('customQtyDisplay').textContent = n;
  };

  window.selectDuo = function(id) {
    $('duoCombo').value = id;
    document.querySelectorAll('.duo-option').forEach(b => {
      const selected = b.dataset.id === id;
      b.classList.toggle('selected', selected);
      const check = b.querySelector('.duo-check');
      if (check) check.innerHTML = selected ? '<i class="bi bi-check-lg"></i>' : '';
    });
  };

  window.openCustomize = function(id) {
    const p = products.find(x => x.id === id);
    if (!p) return;
    if (p.cat === 'ala') { addItem(id, 1); return; }
    customId = id;
    $('customItemName').textContent = p.name + ' · ' + money(p.price);
    $('customItemDesc').textContent = p.desc;
    $('customQty').value = 1;
    $('customQtyDisplay').textContent = '1';
    
    const rEl = document.querySelector('input[name="rice"][value="Garlic Rice"]');
    if (rEl) rEl.checked = true;
    const eEl = document.querySelector('input[name="rice-egg"][value="Sunny-side up"]');
    if (eEl) eEl.checked = true;

    const combos = p.cat === 'solo' ? products.filter(c => c.cat === 'puyat' && c.proteins && c.proteins.includes(p.protein)) : [];
    $('duoSection').classList.toggle('d-none', !combos.length);
    $('duoOptions').innerHTML = '<button type="button" class="duo-option selected" data-id="" onclick="selectDuo(this.dataset.id)"><span class="duo-check"><i class="bi bi-check-lg"></i></span><span><span class="duo-name"><i class="bi bi-egg-fried me-1"></i>Keep my solo meal</span><span class="duo-price">No upgrade</span></span></button>' +
      combos.map(c => '<button type="button" class="duo-option" data-id="' + c.id + '" onclick="selectDuo(this.dataset.id)"><span class="duo-check"></span><span><span class="duo-name"><i class="bi bi-people-fill me-1"></i>' + c.name + '</span><span class="duo-price">Upgrade for +' + money(c.price - p.price) + '</span></span></button>').join('');
    $('duoCombo').value = '';
    modal = bootstrap.Modal.getOrCreateInstance($('customModal'));
    modal.show();
  };

  window.addCustomizedItem = function() {
    const qty = Math.max(1, Math.min(99, parseInt($('customQty').value, 10) || 1));
    const riceSel = document.querySelector('input[name="rice"]:checked');
    const eggSel = document.querySelector('input[name="rice-egg"]:checked');
    const rice = riceSel ? riceSel.value : '';
    const egg = eggSel ? eggSel.value : '';
    const duo = $('duoCombo').value;
    if (modal) modal.hide();
    if (duo) addItem(duo, qty, rice, egg);
    else addItem(customId, qty, rice, egg);
  };

  window.menuQty = function(id, delta) {
    const rows = cart.filter(c => c.id === id);
    if (delta > 0) {
      let existing = rows[0];
      if (existing) { existing.qty++; showCartToast([{ name: existing.name, qty: 1 }]); }
      else openCustomize(id);
    } else if (rows.length) {
      rows[0].qty--;
      if (rows[0].qty <= 0) cart.splice(cart.indexOf(rows[0]), 1);
    }
    updateCartBar();
    updateBudgetDisplay();
    renderMenu();
  };

  function subtotal() { return cart.reduce((s, x) => s + x.price * x.qty, 0); }
  function itemCount() { return cart.reduce((s, x) => s + x.qty, 0); }

  function updateCartBar() {
    $('cartCount').textContent = itemCount() + ' item' + (itemCount() === 1 ? '' : 's');
    $('cartTotal').textContent = money(subtotal());
    $('cartBar').style.display = ($('screen-menu').classList.contains('active') && cart.length) ? 'block' : 'none';
    updateBudgetDisplay();
  }

  function updateBudgetDisplay() {
    const fill = $('budgetGaugeFill'), current = $('budgetCurrent'), warning = $('budgetOverMessage');
    if (!fill || !current || !warning) return;
    const total = subtotal();
    const over = budget !== null && total > budget;
    const ratio = budget !== null && budget > 0 ? Math.min(100, (total / budget) * 100) : 0;
    fill.style.width = ratio + '%';
    fill.classList.toggle('over-budget', over);
    current.classList.toggle('over-budget', over);
    current.textContent = money(total) + ' / ' + (budget === null ? '—' : money(budget));
    warning.classList.toggle('d-none', !over);
  }

  window.openReview = function() {
    if (!cart.length) {
      popup('Your basket is empty', 'Add something delicious first, then come back to review your order.');
      return;
    }
    renderReview();
    showScreen('screen-review');
  };

  function renderReview() {
    $('reviewItems').innerHTML = cart.map((x, i) => `<div class="d-flex justify-content-between gap-2 py-3 border-bottom"><div class="flex-grow-1"><div class="fw-bold">${x.name}</div><div class="small muted">${x.options || ''}</div><div class="d-flex align-items-center gap-2 mt-2"><button class="qty-btn" onclick="reviewQty(${i},-1)">−</button><span class="fw-bold">${x.qty}</span><button class="qty-btn" onclick="reviewQty(${i},1)">+</button><button class="btn btn-sm btn-link text-danger" onclick="removeReviewItem(${i})">Remove</button></div></div><strong>${money(x.price * x.qty)}</strong></div>`).join('');
    $('reviewSubtotal').textContent = money(subtotal());
    $('reviewBudgetWarning').classList.toggle('d-none', budget === null || subtotal() <= budget);
    $('reviewBudgetWarning').textContent = budget !== null && subtotal() > budget ? 'Your food bill is ' + money(subtotal() - budget) + ' over your budget. You can still continue.' : '';
  }

  window.reviewQty = function(i, d) {
    if (!cart[i]) return;
    cart[i].qty += d;
    if (cart[i].qty <= 0) cart.splice(i, 1);
    if (!cart.length) {
      showScreen('screen-menu');
      popup('Your basket is empty', 'Add something delicious first.');
      return;
    }
    renderReview();
    updateCartBar();
    updateBudgetDisplay();
  };

  window.removeReviewItem = function(i) {
    cart.splice(i, 1);
    if (!cart.length) {
      showScreen('screen-menu');
      popup('Your basket is empty', 'Add something delicious first.');
    } else renderReview();
    updateCartBar();
    updateBudgetDisplay();
  };

  window.openBudget = function() {
    popup('Set your budget', 'Your budget is optional. If your food bill goes over it, you can still continue.', [
      { label: 'No specified budget', class: 'btn-outline-secondary', run: () => { budget = null; updateBudgetButtons(); updateBudgetDisplay(); renderReviewIfVisible(); } },
      { label: 'Cancel', class: 'btn-outline-secondary' },
      { label: 'Save Budget', class: 'btn-gold', run: () => {
        const field = $('popupMessage').querySelector('#budgetInput');
        const val = field ? field.value : '';
        if (val.trim() === '' || !Number.isFinite(Number(val)) || Number(val) <= 0) {
          popup('Check that amount', 'Enter a budget greater than ₱0, or choose no specified budget.');
          return;
        }
        budget = Number(val);
        updateBudgetButtons();
        updateBudgetDisplay();
        renderReviewIfVisible();
      }}
    ]);
    $('popupMessage').innerHTML = 'Set an optional spending limit in pesos. Going over budget will show a warning, but checkout stays available.<label class="form-label fw-semibold d-block mt-3" for="budgetInput">Budget amount</label><div class="input-group"><span class="input-group-text">₱</span><input id="budgetInput" class="form-control" type="number" min="1" step="1" placeholder="e.g. 300" value="' + (budget === null ? '' : budget) + '"></div>';
  };

  function renderReviewIfVisible() {
    if ($('screen-review').classList.contains('active')) renderReview();
  }

  function updateBudgetButtons() {
    if ($('budgetMenuLabel')) $('budgetMenuLabel').textContent = 'Your Budget';
    updateBudgetDisplay();
  }

  window.selectNeighborhood = function(btn) {
    const value = btn.dataset.neighborhood;
    $('neighborhood').value = value;
    document.querySelectorAll('.neighborhood-chip').forEach(b => b.classList.toggle('selected', b === btn));
  };

  window.proceedPayment = function() {
    if (!cart.length) {
      popup('Your basket is empty', 'Add at least one item before proceeding.');
      showScreen('screen-menu');
      return;
    }
    if (!$('customerName').value.trim()) {
      popup('What should we call you?', 'Please enter your name or nickname before continuing.');
      $('customerName').focus();
      return;
    }
    if ($('saveOrder').checked && !$('phone').value.trim()) {
      popup('One small detail', 'Enter your phone number if you’d like us to save this order for next time.');
      $('phone').focus();
      return;
    }
    if ($('phone').value.trim() && !/^[+\d\s()\-]{7,20}$/.test($('phone').value.trim())) {
      popup('Check your phone number', 'Please enter a valid phone number, or leave it blank.');
      $('phone').focus();
      return;
    }
    if (!$('neighborhood').value) {
      popup('Choose your neighborhood badge', 'Pick a badge to finish your order details.');
      $('neighborhood').focus();
      return;
    }
    updatePayment();
    showScreen('screen-payment');
  };

  window.setTip = function(v) {
    tip = Math.max(0, Math.min(200, Math.round(Number(v) / 10) * 10));
    $('tipSlider').value = tip;
    $('tipDisplay').textContent = money(tip);
    updatePayment();
    const h = (tip / 200) * 88;
    const y = 132 - h;
    $('jarFill').setAttribute('height', h);
    $('jarFill').setAttribute('y', y);
    $('jarFill').setAttribute('fill', 'url(#goldLiquid)');
    document.querySelectorAll('.tip-chip').forEach(b => b.classList.toggle('active', b.textContent.trim() === (tip === 0 ? 'No tip' : '₱' + tip)));
  };

  function updatePayment() {
    const food = subtotal(), total = food + tip;
    $('paymentFood').textContent = money(food);
    $('paymentTip').textContent = money(tip);
    $('paymentCalculation').textContent = money(food) + ' + ' + money(tip) + ' = ' + money(total);
    $('paymentTotal').textContent = money(total);
    if ($('splitCount')) updateSplit();
  }

  window.toggleSplit = function() {
    $('splitPanel').classList.toggle('d-none');
    updateSplit();
  };

  window.updateSplit = function() {
    const n = Number($('splitCount').value);
    $('splitResult').textContent = Number.isInteger(n) && n > 0 ? 'Estimated share: ' + money((subtotal() + tip) / n) + ' per person' : 'Enter a valid number of people.';
  };

  window.copyGcash = async function() {
    const number = '09270466231';
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(number);
        popup('GCash number copied', number + ' is ready to paste.');
        return;
      }
    } catch (e) {}
    try {
      const field = document.createElement('textarea');
      field.value = number;
      field.setAttribute('readonly', '');
      field.style.position = 'fixed';
      field.style.left = '-9999px';
      field.style.top = '0';
      document.body.appendChild(field);
      field.focus();
      field.select();
      field.setSelectionRange(0, field.value.length);
      const copied = document.execCommand('copy');
      field.remove();
      if (copied) {
        popup('GCash number copied', number + ' is ready to paste.');
        return;
      }
    } catch (e) {}
    popup('Copy not supported', 'Please copy this GCash number manually: ' + number);
  };

  window.confirmPayment = function() {
    if (!$('paymentAcknowledged').checked) {
      popup('One last check', 'Please confirm that you have completed the payment before placing your order.');
      return;
    }
    popup('Place this order?', 'Your order details are ready. Confirm to finish and receive your queue number.', [
      { label: 'Go Back', class: 'btn-outline-secondary' },
      { label: 'Confirm Order', class: 'btn-gold', run: completeOrder }
    ]);
  };

  function completeOrder() {
    const payload = {
      items: cart.map(x => ({ ...x })),
      phone: $('phone').value.trim(),
      name: $('customerName').value.trim(),
      dining,
      tableTag,
      neighborhood: $('neighborhood').value,
      food: subtotal(),
      tip,
      total: subtotal() + tip,
      saveOrder: $('saveOrder').checked
    };

    if (payload.saveOrder && payload.phone) {
      try {
        let saved = [];
        try { saved = JSON.parse(localStorage.getItem('saireneSavedOrders') || '[]'); } catch (e) {}
        saved = saved.filter(x => x.phone !== payload.phone);
        saved.push(payload);
        localStorage.setItem('saireneSavedOrders', JSON.stringify(saved));
      } catch (e) {}
    }

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            displaySuccessScreen(data.queueNumber, payload);
        } else {
            popup('Database Error', data.error || 'Could not save order to database.');
        }
    })
    .catch(() => {
        displaySuccessScreen('A-101', payload);
    });
  }

  function displaySuccessScreen(queueNum, order) {
    $('queueNumber').textContent = queueNum;
    $('successName').textContent = order.name;

    const neighborhoodIcons = {
      'Night Shift Hero': 'bi-moon-stars-fill',
      'Local Regular': 'bi-house-heart-fill',
      'Early Bird': 'bi-sunrise-fill',
      'Weekend Foodie': 'bi-emoji-heart-eyes-fill',
      'First-Time Visitor': 'bi-stars'
    };

    const badgeIcon = neighborhoodIcons[order.neighborhood] || 'bi-geo-alt-fill';
    $('successBadge').innerHTML = `<i class="bi ${badgeIcon} me-1" aria-hidden="true"></i>${order.neighborhood}`;
    $('successDining').textContent = order.dining;
    $('successTag').textContent = order.tableTag ? 'Table / group: ' + order.tableTag : '';
    $('successItems').innerHTML = order.items.map(x => {
      const opts = [];
      if (x.rice) opts.push('<span class="d-inline-block me-2"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 24 24" class="me-1" style="vertical-align: -0.125em;"><path d="M12 3a3 3 0 0 0-3 3h6a3 3 0 0 0-3-3zm8.5 5h-17a1.5 1.5 0 0 0-1.5 1.5c0 4.35 3.12 7.95 7.25 8.75V20a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-1.75c4.13-.8 7.25-4.4 7.25-8.75a1.5 1.5 0 0 0-1.5-1.5z"/></svg>' + x.rice + '</span>');
      if (x.egg) opts.push('<span class="d-inline-block me-2"><i class="bi bi-egg-fried me-1" aria-hidden="true"></i>' + x.egg + '</span>');
      return `<div class="summary-line align-items-start"><span><span class="fw-semibold">${x.qty} × ${x.name}</span>${opts.length ? '<div class="small muted mt-1">' + opts.join('') + '</div>' : ''}</span><strong>${money(x.qty * x.price)}</strong></div>`;
    }).join('');

    $('successFood').textContent = money(order.food);
    $('successTip').textContent = money(order.tip);
    $('successTotal').textContent = money(order.total);
    showScreen('screen-success');
  }

  window.resetKiosk = function() {
    dining = '';
    tableTag = '';
    cart = [];
    tip = 0;
    budget = null;
    filter = 'all';
    reorderCandidate = null;
    $('tableTag').value = '';
    $('reorderDigits').value = '';
    $('reorderResult').innerHTML = '';
    $('customerName').value = '';
    $('neighborhood').value = '';
    document.querySelectorAll('.neighborhood-chip').forEach(b => b.classList.remove('selected'));
    $('phone').value = '';
    $('saveOrder').checked = false;
    $('paymentAcknowledged').checked = false;
    $('splitPanel').classList.add('d-none');
    $('splitCount').value = 2;
    $('customQty').value = 1;
    $('customQtyDisplay').textContent = '1';
    $('duoCombo').value = '';
    setTip(0);
    updateCartBar();
    showScreen('screen-welcome');
  };

  window.backToMenuFromReview = () => showScreen('screen-menu');
  window.backToReviewFromPayment = () => { renderReview(); showScreen('screen-review'); };

  window.addEventListener('load', () => {
    renderMenu();
    filterMenu('all');
    updateCartBar();
    updateBudgetButtons();
    updateBudgetDisplay();
    setTip(0);
  });
})();
</script>
</body>
</html>