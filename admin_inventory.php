<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
include "config.php";

if (!isset($_SESSION['user_id'])) die("Login first");

$uid = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
if ($user['role'] != 'admin') die("Access denied");

// ── DELETE PRODUCT ──────────────────────────────────────────────────────────
if (isset($_POST['delete_product'])) {
    $product_id = intval($_POST['product_id']);
    if ($product_id > 0) $conn->query("DELETE FROM products WHERE id=$product_id");
    header("Location: admin_inventory.php"); exit();
}

// ── UPDATE STOCK ────────────────────────────────────────────────────────────
if (isset($_POST['update_stock'])) {
    $product_id = intval($_POST['product_id']);
    $new_stock  = intval($_POST['new_stock']);
    $conn->query("UPDATE products SET stock=$new_stock WHERE id=$product_id");
    header("Location: admin_inventory.php"); exit();
}

// ── EDIT PRODUCT (AJAX) ─────────────────────────────────────────────────────
if (isset($_POST['update_product_ajax'])) {
    $product_id  = intval($_POST['product_id']);
    $name        = $_POST['name'];
    $description = $_POST['description'];
    $price       = floatval($_POST['price']);
    $category_id = intval($_POST['category_id']);
    $stock       = intval($_POST['stock']);
    $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, category_id=?, stock=? WHERE id=?");
    $stmt->bind_param("ssdiii", $name, $description, $price, $category_id, $stock, $product_id);
    echo json_encode($stmt->execute() ? ['success'=>true] : ['success'=>false,'error'=>$stmt->error]);
    exit();
}

// ── ADD PRODUCT (AJAX) ──────────────────────────────────────────────────────
if (isset($_POST['add_product_ajax'])) {
    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price       = floatval($_POST['price']);
    $category_id = intval($_POST['category_id']);
    $stock       = intval($_POST['stock']);
    $image       = 'placeholder.png';
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $image = uniqid('prod_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], "images/$image");
        }
    }
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, category_id, stock, image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdiss", $name, $description, $price, $category_id, $stock, $image);
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        $cat   = $conn->query("SELECT name FROM categories WHERE id=$category_id")->fetch_assoc();
        echo json_encode(['success'=>true,'id'=>$newId,'name'=>$name,'description'=>$description,'price'=>$price,'category_id'=>$category_id,'category_name'=>$cat['name']??'','stock'=>$stock,'image'=>$image]);
    } else { echo json_encode(['success'=>false,'error'=>$stmt->error]); }
    exit();
}

// ── LOAD ─────────────────────────────────────────────────────────────────────
$categoriesResult = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$categories = [];
if ($categoriesResult) while ($cat = $categoriesResult->fetch_assoc()) $categories[] = $cat;
if (empty($categories)) $categories = [['id'=>1,'name'=>'Fresh'],['id'=>2,'name'=>'Crochet'],['id'=>3,'name'=>'Fuzzywire'],['id'=>4,'name'=>'Plastic'],['id'=>5,'name'=>'Readymade'],['id'=>6,'name'=>'Satin']];

$category_filter = $_GET['category'] ?? '';
if ($category_filter && $category_filter !== 'all') {
    $stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE c.name=? ORDER BY p.id DESC");
    $stmt->bind_param("s", $category_filter);
    $stmt->execute();
    $products = $stmt->get_result();
} else {
    $products = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
}
$productList = [];
while ($p = $products->fetch_assoc()) $productList[] = $p;
$totalProducts = count($productList);

// Category icons map
$catIcons = ['Crochet'=>'🧶','Fresh'=>'🌿','Fuzzywire'=>'✨','Fuzzy Wire'=>'✨','Glitter'=>'💎','Plastic'=>'🌺','Readymade'=>'💐','Satin'=>'🎀'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory — Precious Satin Bouquet</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<style>
:root {
    --blush:#fff3ed; --petal:#ffc9a0; --rose:#f08050; --deep-rose:#d05a28;
    --cream:#fff8f4; --white:#ffffff; --ink:#4a2c1e; --muted:#9a6b5a;
    --border:rgba(220,120,70,0.22);
    --shadow-sm:0 2px 12px rgba(208,90,40,0.10);
    --shadow-md:0 8px 32px rgba(208,90,40,0.14);
    --shadow-lg:0 20px 60px rgba(208,90,40,0.18);
    --radius:18px; --radius-sm:10px;
    --danger:#e05c5c; --danger-bg:#fdeaea;
    --nav-h:62px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;background:var(--cream);font-family:'Jost',sans-serif;color:var(--ink);}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 60% 40% at 90% 10%,rgba(255,200,160,0.3) 0%,transparent 70%),radial-gradient(ellipse 50% 60% at 5% 80%,rgba(255,215,185,0.4) 0%,transparent 65%);pointer-events:none;z-index:0;}

/* ── Navbar styles live in admin_navbar.php ── */

/* LANDSCAPE SHELL */
.ls-shell{position:fixed;top:var(--nav-h);left:0;right:0;bottom:0;display:flex;}

/* LEFT PANE */
.ls-left{width:340px;flex-shrink:0;display:flex;flex-direction:column;border-right:1px solid var(--border);background:rgba(255,248,242,0.85);backdrop-filter:blur(10px);}
.ls-left-head{padding:16px 18px 12px;border-bottom:1px solid var(--border);background:var(--blush);flex-shrink:0;}
.ls-title-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.ls-eyebrow{font-size:9px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:var(--muted);margin-bottom:3px;}
.ls-title{font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:700;color:var(--deep-rose);line-height:1;}
.btn-add{display:inline-flex;align-items:center;gap:5px;background:linear-gradient(135deg,#f08050,#d05a28);color:#fff;border:none;border-radius:50px;font-family:'Jost',sans-serif;font-size:12px;font-weight:600;padding:8px 14px;cursor:pointer;white-space:nowrap;transition:all 0.2s;box-shadow:0 3px 10px rgba(208,90,40,0.28);flex-shrink:0;}
.btn-add:hover{transform:translateY(-1px);box-shadow:0 5px 14px rgba(208,90,40,0.38);}
.ls-search-row{display:flex;gap:8px;align-items:center;}
.search-wrap{position:relative;flex:1;}
.search-wrap input{width:100%;padding:8px 12px 8px 34px;border:1px solid var(--border);border-radius:50px;background:var(--white);font-family:'Jost',sans-serif;font-size:13px;color:var(--ink);outline:none;transition:border-color 0.2s;}
.search-wrap input:focus{border-color:var(--rose);}
.search-wrap::before{content:'🔍';position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:12px;pointer-events:none;}

/* ── GIRLY CUSTOM DROPDOWN ── */
.custom-select-wrap{position:relative;flex-shrink:0;}
.custom-select-trigger{display:flex;align-items:center;gap:6px;padding:7px 11px 7px 10px;background:#fff0f5;border:1.5px solid #f4b8cc;border-radius:50px;cursor:pointer;font-family:'Jost',sans-serif;font-size:12px;font-weight:600;color:#c0476b;transition:all 0.2s;user-select:none;white-space:nowrap;box-shadow:0 2px 8px rgba(210,80,120,0.12);min-width:110px;}
.custom-select-trigger:hover{background:#fde0ea;border-color:#e87ca0;box-shadow:0 4px 14px rgba(210,80,120,0.20);}
.custom-select-trigger.open{background:#fde0ea;border-color:#e87ca0;}
.trigger-icon{font-size:13px;line-height:1;flex-shrink:0;}
.trigger-text{flex:1;}
.trigger-chevron{font-size:9px;color:#e87ca0;transition:transform 0.25s cubic-bezier(.34,1.56,.64,1);line-height:1;flex-shrink:0;}
.custom-select-trigger.open .trigger-chevron{transform:rotate(180deg);}

.custom-dropdown-menu{display:none;position:absolute;top:calc(100% + 6px);right:0;background:#fffaf9;border:1.5px solid #f4b8cc;border-radius:18px;box-shadow:0 10px 32px rgba(210,80,120,0.20),0 2px 8px rgba(210,80,120,0.10);z-index:999;overflow:hidden;padding:6px 0;min-width:150px;animation:bloomIn 0.22s cubic-bezier(.34,1.56,.64,1);}
.custom-dropdown-menu.open{display:block;}
@keyframes bloomIn{from{opacity:0;transform:translateY(-8px) scale(0.96);}to{opacity:1;transform:translateY(0) scale(1);}}

.dropdown-item{display:flex;align-items:center;gap:9px;padding:9px 16px;font-family:'Jost',sans-serif;font-size:12px;font-weight:600;color:#7a3552;cursor:pointer;transition:background 0.13s;position:relative;}
.dropdown-item:hover{background:#fde8f0;color:#c0476b;}
.dropdown-item.selected{background:#fcd6e6;color:#b03060;}
.dropdown-item.selected::after{content:'✓';position:absolute;right:14px;font-size:11px;color:#e87ca0;}
.dropdown-item-icon{font-size:14px;line-height:1;flex-shrink:0;}
.dropdown-divider{height:1px;background:linear-gradient(90deg,transparent,#f4b8cc 30%,#f4b8cc 70%,transparent);margin:4px 12px;}
/* ── END DROPDOWN ── */

.ls-list-count{padding:7px 18px;font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);background:rgba(255,243,237,0.5);flex-shrink:0;}
.ls-list{flex:1;overflow-y:auto;}
.ls-list::-webkit-scrollbar{width:4px;}.ls-list::-webkit-scrollbar-track{background:transparent;}.ls-list::-webkit-scrollbar-thumb{background:var(--petal);border-radius:4px;}

.product-item{display:flex;align-items:center;gap:12px;padding:12px 18px;cursor:pointer;border-bottom:1px solid rgba(220,120,70,0.10);transition:background 0.15s;}
.product-item:hover{background:var(--blush);}
.product-item.active{background:linear-gradient(90deg,rgba(240,128,80,0.13),rgba(240,128,80,0.04));border-right:3px solid var(--rose);}
.product-item.active .pi-name{color:var(--deep-rose);}
.product-thumb{width:44px;height:44px;border-radius:var(--radius-sm);object-fit:cover;border:1px solid var(--border);background:var(--blush);flex-shrink:0;}
.pi-info{flex:1;min-width:0;}
.pi-name{font-size:14px;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pi-cat{font-size:11px;color:var(--muted);margin-top:2px;}
.pi-badge{flex-shrink:0;}

.stock-badge{display:inline-block;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;letter-spacing:0.5px;text-transform:uppercase;white-space:nowrap;}
.stock-badge.in-stock{background:#e6f9f0;color:#1a7a4a;border:1px solid #b2e8cf;}
.stock-badge.low-stock{background:#fff8e6;color:#a0740a;border:1px solid #f5d97a;}
.stock-badge.out-of-stock{background:var(--danger-bg);color:#a02020;border:1px solid #f5b2b2;}
.ls-list-empty{padding:48px 24px;text-align:center;color:var(--muted);font-size:14px;}

/* RIGHT PANE */
.ls-right{flex:1;display:flex;flex-direction:column;overflow:hidden;}
.ls-detail-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--muted);gap:10px;}
.ls-detail-empty .empty-icon{font-size:48px;opacity:0.3;}
.ls-detail-empty p{font-size:14px;}

.ls-detail{display:none;flex-direction:column;height:100%;overflow:hidden;animation:panelIn 0.2s ease;}
.ls-detail.visible{display:flex;}
@keyframes panelIn{from{opacity:0;transform:translateX(8px);}to{opacity:1;transform:translateX(0);}}

.detail-head{display:flex;align-items:center;gap:16px;padding:20px 28px 18px;border-bottom:1px solid var(--border);background:var(--blush);flex-shrink:0;}
.detail-img{width:72px;height:72px;border-radius:var(--radius-sm);object-fit:cover;border:1.5px solid var(--border);background:var(--cream);flex-shrink:0;}
.detail-head-info{flex:1;min-width:0;}
.detail-name{font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:700;color:var(--ink);line-height:1.1;}
.detail-cat{font-size:12px;color:var(--muted);margin-top:4px;}
.detail-head-actions{display:flex;gap:8px;flex-shrink:0;}

.action-btn{width:36px;height:36px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--white);color:var(--muted);cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;transition:all 0.2s;}
.action-btn:hover{background:var(--rose);border-color:var(--rose);color:#fff;}
.action-btn.danger:hover{background:var(--danger);border-color:var(--danger);color:#fff;}

.detail-body{flex:1;overflow-y:auto;padding:24px 28px 32px;}
.detail-body::-webkit-scrollbar{width:4px;}.detail-body::-webkit-scrollbar-track{background:transparent;}.detail-body::-webkit-scrollbar-thumb{background:var(--petal);border-radius:4px;}

.detail-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px 18px;box-shadow:var(--shadow-sm);}
.stat-label{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
.stat-value{font-family:'Cormorant Garamond',serif;font-size:28px;font-weight:700;color:var(--deep-rose);line-height:1;}
.stat-sub{font-size:11px;color:var(--muted);margin-top:4px;}

.detail-section{margin-bottom:22px;}
.detail-section-label{font-size:10px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:var(--muted);margin-bottom:8px;}
.detail-desc{font-size:14px;color:var(--ink);line-height:1.7;background:var(--white);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;}
.detail-desc.empty{color:var(--muted);font-style:italic;}

.stock-update-row{display:flex;align-items:center;gap:10px;background:var(--white);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;}
.stock-update-row input{width:90px;padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-family:'Jost',sans-serif;font-size:14px;color:var(--ink);outline:none;background:var(--cream);transition:border-color 0.2s;}
.stock-update-row input:focus{border-color:var(--rose);}
.btn-update{padding:8px 18px;background:linear-gradient(135deg,#f08050,#d05a28);color:#fff;border:none;border-radius:var(--radius-sm);font-family:'Jost',sans-serif;font-size:11px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;transition:all 0.2s;}
.btn-update:hover{box-shadow:0 3px 10px rgba(208,90,40,0.3);transform:translateY(-1px);}
.stock-current{font-size:13px;color:var(--muted);}

/* ══ REDESIGNED MODALS ══════════════════════════════════════════════════════ */
.modal-overlay{
  display:none;position:fixed;inset:0;z-index:500;
  align-items:center;justify-content:center;
  background:rgba(60,20,30,0.45);
  backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
}
.modal-overlay.active{display:flex;}

/* Card */
.modal{
  position:relative;
  background:var(--white);
  border-radius:28px;
  border:1px solid rgba(240,128,80,0.18);
  box-shadow:0 32px 80px rgba(180,60,20,0.22),0 4px 16px rgba(180,60,20,0.10);
  width:100%;max-width:500px;max-height:92vh;
  overflow-y:auto;overflow-x:hidden;
  padding:0;
  margin:16px;
  animation:modalBloom 0.32s cubic-bezier(.34,1.56,.64,1);
}
.modal::-webkit-scrollbar{width:4px;}
.modal::-webkit-scrollbar-thumb{background:var(--petal);border-radius:4px;}

@keyframes modalBloom{
  from{opacity:0;transform:scale(0.92) translateY(18px);}
  to  {opacity:1;transform:scale(1)    translateY(0);}
}

/* Decorative header band */
.modal-header{
  position:relative;
  padding:28px 32px 22px;
  background:linear-gradient(135deg,#fff6f0 0%,#fff0ea 100%);
  border-bottom:1px solid rgba(240,128,80,0.14);
  overflow:hidden;
}
.modal-header::before{
  content:'';position:absolute;top:-30px;right:-30px;
  width:120px;height:120px;border-radius:50%;
  background:radial-gradient(circle,rgba(240,128,80,0.18) 0%,transparent 70%);
  pointer-events:none;
}
.modal-header::after{
  content:'';position:absolute;bottom:-20px;left:20px;
  width:80px;height:80px;border-radius:50%;
  background:radial-gradient(circle,rgba(255,180,130,0.14) 0%,transparent 70%);
  pointer-events:none;
}
.modal-title{
  font-family:'Cormorant Garamond',serif;
  font-size:30px;font-weight:700;
  color:var(--deep-rose);
  line-height:1;margin:0;
}
.modal-title-sub{
  font-size:11px;font-weight:500;letter-spacing:2.5px;text-transform:uppercase;
  color:var(--muted);margin-top:5px;
}

/* Body */
.modal-body{padding:26px 32px 30px;}

/* Field group */
.field-group{
  position:relative;
  margin-bottom:18px;
}
.field-group:last-of-type{margin-bottom:0;}

/* Labels */
.modal label,.field-label{
  display:block;
  font-size:10px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;
  color:var(--muted);margin-bottom:7px;margin-top:0;
}
.field-icon-wrap{position:relative;}
.field-icon{
  position:absolute;left:14px;top:50%;transform:translateY(-50%);
  font-size:14px;pointer-events:none;opacity:0.6;z-index:2;
}
.field-icon-area{top:16px;transform:none;}

/* Inputs */
.modal input,.modal textarea,.modal select{
  width:100%;
  padding:12px 14px 12px 40px;
  border:1.5px solid rgba(220,140,90,0.22);
  border-radius:14px;
  font-family:'Jost',sans-serif;font-size:14px;
  color:var(--ink);outline:none;
  transition:border-color 0.2s,box-shadow 0.2s,background 0.2s;
  background:rgba(255,248,244,0.7);
  box-sizing:border-box;
}
.modal input:focus,.modal textarea:focus,.modal select:focus{
  border-color:var(--rose);
  box-shadow:0 0 0 4px rgba(240,128,80,0.12);
  background:var(--white);
}
.modal input::placeholder,.modal textarea::placeholder{
  color:rgba(160,112,128,0.45);font-size:13px;
}
.modal textarea{min-height:90px;resize:vertical;padding-top:12px;line-height:1.6;}
/* no-icon variant */
.modal input.no-icon,.modal textarea.no-icon{padding-left:14px;}

/* Image upload area */
.img-upload-area{
  border:2px dashed rgba(240,128,80,0.30);
  border-radius:14px;padding:18px 16px;
  text-align:center;cursor:pointer;
  background:rgba(255,243,237,0.5);
  transition:border-color 0.2s,background 0.2s;
  position:relative;overflow:hidden;
}
.img-upload-area:hover{border-color:var(--rose);background:rgba(255,230,215,0.4);}
.img-upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.img-upload-hint{font-size:12px;color:var(--muted);margin-top:4px;}
.img-upload-icon{font-size:22px;margin-bottom:4px;}
.img-preview-wrap{margin-top:12px;display:flex;justify-content:center;}
.img-preview-wrap img{width:90px;height:90px;object-fit:cover;border-radius:14px;border:2px solid var(--border);display:none;box-shadow:var(--shadow-sm);}

/* Divider */
.modal-divider{height:1px;background:linear-gradient(90deg,transparent,rgba(240,128,80,0.18),transparent);margin:4px 0 20px;}

/* Action buttons */
.modal-actions{display:flex;gap:10px;margin-top:24px;}
.btn-save{
  flex:1;padding:13px;
  background:linear-gradient(135deg,#f08050 0%,#d05a28 100%);
  color:#fff;border:none;border-radius:14px;
  font-family:'Jost',sans-serif;font-size:12px;font-weight:700;
  letter-spacing:2px;text-transform:uppercase;
  cursor:pointer;transition:all 0.22s;
  box-shadow:0 4px 16px rgba(208,90,40,0.28);
}
.btn-save:hover{
  transform:translateY(-2px);
  box-shadow:0 8px 24px rgba(208,90,40,0.38);
}
.btn-save:active{transform:translateY(0);}
.btn-save.danger{background:linear-gradient(135deg,#f47070,#c03030);box-shadow:0 4px 16px rgba(200,50,50,0.25);}
.btn-cancel{
  flex:1;padding:13px;
  background:transparent;
  color:var(--muted);
  border:1.5px solid rgba(220,140,90,0.28);
  border-radius:14px;
  font-family:'Jost',sans-serif;font-size:12px;font-weight:600;
  letter-spacing:2px;text-transform:uppercase;
  cursor:pointer;transition:all 0.18s;
}
.btn-cancel:hover{background:var(--blush);color:var(--ink);border-color:var(--petal);}

/* Warning box */
.modal-warning{
  background:linear-gradient(135deg,#fff0f0,#fde8e8);
  color:#a02020;font-size:13px;
  padding:13px 16px;border-radius:12px;
  margin-bottom:20px;
  border:1px solid rgba(240,100,100,0.22);
  display:flex;align-items:center;gap:10px;
  line-height:1.5;
}
.modal-warning::before{content:'⚠️';font-size:16px;flex-shrink:0;}

/* Modal subtitle (delete) */
.modal-subtitle{
  font-size:14px;color:var(--muted);
  text-align:center;margin-bottom:4px;font-weight:500;
}
/* ══ END MODAL CSS ══════════════════════════════════════════════════════════ */
/* ── MODAL CUSTOM SELECT ── */
.modal-select-wrap{position:relative;width:100%;margin-top:0;}
.modal-select-trigger{display:flex;align-items:center;gap:8px;width:100%;padding:12px 14px;background:rgba(255,248,244,0.7);border:1.5px solid rgba(220,140,90,0.22);border-radius:14px;cursor:pointer;font-family:'Jost',sans-serif;font-size:14px;font-weight:500;color:var(--ink);transition:border-color 0.2s,box-shadow 0.2s,background 0.2s;user-select:none;box-sizing:border-box;}
.modal-select-trigger:hover,.modal-select-trigger.open{border-color:var(--rose);box-shadow:0 0 0 4px rgba(240,128,80,0.12);background:var(--white);}
.modal-trigger-icon{font-size:16px;flex-shrink:0;line-height:1;}
.modal-trigger-text{flex:1;text-align:left;}
.modal-trigger-chevron{font-size:10px;color:var(--muted);transition:transform 0.22s cubic-bezier(.34,1.56,.64,1);flex-shrink:0;}
.modal-select-trigger.open .modal-trigger-chevron{transform:rotate(180deg);}
.modal-dropdown-menu{display:none;position:absolute;top:calc(100% + 6px);left:0;right:0;background:var(--white);border:1.5px solid rgba(240,128,80,0.18);border-radius:14px;box-shadow:0 12px 36px rgba(180,60,20,0.16);z-index:9999;overflow:hidden;padding:6px 0;animation:bloomIn 0.2s cubic-bezier(.34,1.56,.64,1);}
.modal-dropdown-menu.open{display:block;}
.modal-drop-item{display:flex;align-items:center;gap:10px;padding:11px 18px;font-family:'Jost',sans-serif;font-size:14px;font-weight:500;color:var(--ink);cursor:pointer;transition:background 0.13s;position:relative;}
.modal-drop-item:hover{background:var(--blush);color:var(--deep-rose);}
.modal-drop-item.selected{background:#fde0ea;color:var(--deep-rose);font-weight:600;}
.modal-drop-item.selected::after{content:'✓';position:absolute;right:16px;font-size:12px;color:var(--rose);}
.modal-drop-item-icon{font-size:15px;flex-shrink:0;line-height:1;}
/* ── END MODAL SELECT ── */
@keyframes rowHighlight{from{background:rgba(220,120,70,0.15);}to{background:transparent;}}
.product-item.new-item{animation:rowHighlight 2.5s ease forwards;}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<?php $activePage = 'inventory'; include 'admin_navbar.php'; ?>

<!-- LANDSCAPE SHELL -->
<div class="ls-shell">

  <!-- LEFT: product list -->
  <div class="ls-left">
    <div class="ls-left-head">
      <div class="ls-title-row">
        <div>
          <div class="ls-eyebrow">Store Management</div>
          <div class="ls-title">Inventory</div>
        </div>
        <button class="btn-add" onclick="openAddModal()">+ Add Product</button>
      </div>
      <div class="ls-search-row">
        <div class="search-wrap">
          <input type="text" id="inventorySearch" placeholder="Search products…">
        </div>

        <!-- ✨ GIRLY CUSTOM DROPDOWN ✨ -->
        <?php
          $activeIcon = '🌸';
          $activeLabel = 'All Categories';
          if ($category_filter && $category_filter !== 'all') {
            $activeIcon = $catIcons[$category_filter] ?? '🌸';
            $activeLabel = $category_filter;
          }
        ?>
        <div class="custom-select-wrap" id="catSelectWrap">
          <div class="custom-select-trigger" id="catSelectTrigger" onclick="toggleCatDropdown()">
            <span class="trigger-icon" id="catTriggerIcon"><?= $activeIcon ?></span>
            <span class="trigger-text" id="catTriggerText"><?= htmlspecialchars($activeLabel) ?></span>
            <span class="trigger-chevron">▾</span>
          </div>
          <div class="custom-dropdown-menu" id="catDropdownMenu">
            <div class="dropdown-item <?= (!$category_filter || $category_filter === 'all') ? 'selected' : '' ?>"
                 data-value="all" data-icon="🌸"
                 onclick="selectCat(this)">
              <span class="dropdown-item-icon">🌸</span> All Categories
            </div>
            <div class="dropdown-divider"></div>
            <?php foreach($categories as $cat):
              $icon = $catIcons[$cat['name']] ?? '🌸';
              $isSel = $category_filter === $cat['name'] ? 'selected' : '';
            ?>
            <div class="dropdown-item <?= $isSel ?>"
                 data-value="<?= htmlspecialchars($cat['name']) ?>"
                 data-icon="<?= $icon ?>"
                 onclick="selectCat(this)">
              <span class="dropdown-item-icon"><?= $icon ?></span>
              <?= htmlspecialchars($cat['name']) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <!-- END DROPDOWN -->

      </div>
    </div>

    <div class="ls-list-count" id="listCount"><?= $totalProducts ?> product<?= $totalProducts !== 1 ? 's' : '' ?></div>

    <div class="ls-list" id="productList">
      <?php if (!empty($productList)): ?>
        <?php foreach($productList as $i => $product):
          $stock = isset($product['stock']) ? intval($product['stock']) : 0;
          $sc = $stock > 10 ? 'in-stock' : ($stock > 0 ? 'low-stock' : 'out-of-stock');
          $st = $stock > 10 ? 'In Stock' : ($stock > 0 ? 'Low Stock' : 'Out of Stock');
          $img = htmlspecialchars($product['image'] ?: 'placeholder.png');
        ?>
        <div class="product-item <?= $i === 0 ? 'active' : '' ?>"
             data-id="<?= $product['id'] ?>"
             data-name="<?= strtolower(htmlspecialchars($product['name'])) ?>"
             onclick="selectProduct(this)">
          <img class="product-thumb" src="images/<?= $img ?>" onerror="this.src='images/placeholder.png'" alt="<?= htmlspecialchars($product['name']) ?>">
          <div class="pi-info">
            <div class="pi-name"><?= htmlspecialchars($product['name']) ?></div>
            <div class="pi-cat"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorised') ?></div>
          </div>
          <div class="pi-badge"><span class="stock-badge <?= $sc ?>"><?= $st ?></span></div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="ls-list-empty" id="emptyState">No products found 🌱</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT: detail panel -->
  <div class="ls-right" id="detailPane">
    <?php if (!empty($productList)): ?>
      <?php foreach($productList as $i => $product):
        $stock = isset($product['stock']) ? intval($product['stock']) : 0;
        $sc = $stock > 10 ? 'in-stock' : ($stock > 0 ? 'low-stock' : 'out-of-stock');
        $st = $stock > 10 ? 'In Stock' : ($stock > 0 ? 'Low Stock' : 'Out of Stock');
        $img = htmlspecialchars($product['image'] ?: 'placeholder.png');
        $sN = htmlspecialchars(addslashes($product['name']));
        $sD = htmlspecialchars(addslashes($product['description'] ?? ''));
        $sCat = htmlspecialchars($product['category_name'] ?? '');
      ?>
      <div class="ls-detail <?= $i === 0 ? 'visible' : '' ?>" id="detail-<?= $product['id'] ?>">
        <div class="detail-head">
          <img class="detail-img" src="images/<?= $img ?>" onerror="this.src='images/placeholder.png'" alt="<?= htmlspecialchars($product['name']) ?>">
          <div class="detail-head-info">
            <div class="detail-name"><?= htmlspecialchars($product['name']) ?></div>
            <div class="detail-cat"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorised') ?> &nbsp;·&nbsp; <span class="stock-badge <?= $sc ?>"><?= $st ?></span></div>
          </div>
          <div class="detail-head-actions">
            <button class="action-btn" title="Edit" onclick="openEditModal(<?= $product['id'] ?>,'<?= $sN ?>','<?= $sD ?>',<?= $product['price'] ?>,<?= intval($product['category_id']) ?>,'<?= $sCat ?>',<?= $stock ?>)">✎</button>
            <button class="action-btn danger" title="Delete" onclick="confirmDeleteProduct(<?= $product['id'] ?>,'<?= $sN ?>')">🗑️</button>
          </div>
        </div>
        <div class="detail-body">
          <div class="detail-stats">
            <div class="stat-card">
              <div class="stat-label">Price</div>
              <div class="stat-value">₱<?= number_format($product['price'], 2) ?></div>
              <div class="stat-sub">per unit</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Stock</div>
              <div class="stat-value"><?= $stock ?></div>
              <div class="stat-sub">units remaining</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Category</div>
              <div class="stat-value" style="font-size:20px;"><?= htmlspecialchars($product['category_name'] ?? '—') ?></div>
              <div class="stat-sub">product type</div>
            </div>
          </div>
          <div class="detail-section">
            <div class="detail-section-label">Description</div>
            <div class="detail-desc <?= empty($product['description']) ? 'empty' : '' ?>">
              <?= !empty($product['description']) ? nl2br(htmlspecialchars($product['description'])) : 'No description provided.' ?>
            </div>
          </div>
          <div class="detail-section">
            <div class="detail-section-label">Update Stock</div>
            <form method="POST" action="admin_inventory.php">
              <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
              <input type="hidden" name="update_stock" value="1">
              <div class="stock-update-row">
                <span class="stock-current">Current: <strong><?= $stock ?></strong></span>
                <input type="number" name="new_stock" value="<?= $stock ?>" min="0" placeholder="New qty" required>
                <button type="submit" class="btn-update">Save Stock</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="ls-detail-empty">
        <div class="empty-icon">🗂️</div>
        <p>No products yet. Add one to get started.</p>
      </div>
    <?php endif; ?>
  </div>

</div><!-- /ls-shell -->

<!-- Add Product Modal -->
<div id="addProductModal" class="modal-overlay">
  <div class="modal">
    <!-- Header -->
    <div class="modal-header">
      <div class="modal-title">Add Product</div>
      <div class="modal-title-sub">Fill in the details below</div>
    </div>
    <!-- Body -->
    <div class="modal-body">
    <form id="addProductForm" enctype="multipart/form-data">
      <input type="hidden" name="add_product_ajax" value="1">

      <div class="field-group">
        <label>Product Name</label>
        <div class="field-icon-wrap">
          <span class="field-icon">🪷</span>
          <input type="text" name="name" id="addName" placeholder="e.g. Rose Bouquet" required>
        </div>
      </div>

      <div class="field-group">
        <label>Description</label>
        <div class="field-icon-wrap">
          <span class="field-icon field-icon-area">📝</span>
          <textarea name="description" id="addDescription" placeholder="Describe this product…" required></textarea>
        </div>
      </div>

      <div class="field-group">
        <label>Price (₱)</label>
        <div class="field-icon-wrap">
          <span class="field-icon">₱</span>
          <input type="number" name="price" id="addPrice" step="0.01" min="0" placeholder="0.00" required>
        </div>
      </div>

      <div class="field-group">
        <label>Category</label>
        <input type="hidden" name="category_id" id="addCategory" required>
        <?php
          $firstAddCat = $categories[0] ?? null;
          $firstAddIcon = $firstAddCat ? ($catIcons[$firstAddCat['name']] ?? '🌸') : '🌸';
          $firstAddName = $firstAddCat ? htmlspecialchars($firstAddCat['name']) : 'Select';
        ?>
        <div class="modal-select-wrap" id="addCatWrap">
          <div class="modal-select-trigger" id="addCatTrigger" onclick="toggleModalDrop('addCat')">
            <span class="modal-trigger-icon" id="addCatIcon"><?= $firstAddIcon ?></span>
            <span class="modal-trigger-text" id="addCatText"><?= $firstAddName ?></span>
            <span class="modal-trigger-chevron">▾</span>
          </div>
          <div class="modal-dropdown-menu" id="addCatMenu">
            <?php foreach($categories as $idx => $cat):
              $icon = $catIcons[$cat['name']] ?? '🌸';
            ?>
            <div class="modal-drop-item <?= $idx===0?'selected':'' ?>"
                 data-id="<?= intval($cat['id']) ?>"
                 data-icon="<?= $icon ?>"
                 data-name="<?= htmlspecialchars($cat['name']) ?>"
                 onclick="pickModalCat('addCat','<?= intval($cat['id']) ?>','<?= $icon ?>','<?= htmlspecialchars($cat['name']) ?>')">
              <span class="modal-drop-item-icon"><?= $icon ?></span>
              <?= htmlspecialchars($cat['name']) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="field-group">
        <label>Stock</label>
        <div class="field-icon-wrap">
          <span class="field-icon">📦</span>
          <input type="number" name="stock" id="addStock" min="0" placeholder="0" required>
        </div>
      </div>

      <div class="field-group">
        <label>Product Image</label>
        <div class="img-upload-area">
          <input type="file" name="image" id="addImage" accept="image/*" onchange="previewAddImage(this)">
          <div class="img-upload-icon">🖼️</div>
          <div style="font-size:13px;font-weight:600;color:var(--muted);">Click to upload image</div>
          <div class="img-upload-hint">JPG, PNG, WEBP — max 5MB</div>
        </div>
        <div class="img-preview-wrap"><img id="addImagePreview" src="" alt="Preview"></div>
      </div>

      <div class="modal-divider"></div>
      <div class="modal-actions">
        <button type="submit" class="btn-save">✦ Add Product</button>
        <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
      </div>
    </form>
    </div><!-- /modal-body -->
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteProductModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header" style="background:linear-gradient(135deg,#fff0f0,#fde8e8);">
      <div class="modal-title" style="color:#c03030;">Delete Product</div>
      <div class="modal-title-sub">This action cannot be undone</div>
    </div>
    <div class="modal-body">
      <div class="modal-warning">
        <span id="deleteProductNameText" style="font-weight:600;"></span>
        &nbsp;will be permanently removed from your inventory.
      </div>
      <form method="POST" action="admin_inventory.php">
        <input type="hidden" name="product_id" id="deleteProductId">
        <input type="hidden" name="delete_product" value="1">
        <div class="modal-actions">
          <button type="submit" class="btn-save danger">🗑️ Delete</button>
          <button type="button" class="btn-cancel" onclick="closeDeleteProductModal()">Keep It</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Edit Product</div>
      <div class="modal-title-sub">Update product details</div>
    </div>
    <div class="modal-body">
    <form id="editProductForm">
      <input type="hidden" name="product_id" id="editProductId">
      <input type="hidden" name="update_product_ajax" value="1">

      <div class="field-group">
        <label>Product Name</label>
        <div class="field-icon-wrap">
          <span class="field-icon">🪷</span>
          <input type="text" name="name" id="editName" placeholder="Product name" required>
        </div>
      </div>

      <div class="field-group">
        <label>Description</label>
        <div class="field-icon-wrap">
          <span class="field-icon field-icon-area">📝</span>
          <textarea name="description" id="editDescription" placeholder="Product description…" required></textarea>
        </div>
      </div>

      <div class="field-group">
        <label>Price (₱)</label>
        <div class="field-icon-wrap">
          <span class="field-icon">₱</span>
          <input type="number" name="price" id="editPrice" step="0.01" placeholder="0.00" required>
        </div>
      </div>

      <div class="field-group">
        <label>Category</label>
        <input type="hidden" name="category_id" id="editCategory" required>
        <div class="modal-select-wrap" id="editCatWrap">
          <div class="modal-select-trigger" id="editCatTrigger" onclick="toggleModalDrop('editCat')">
            <span class="modal-trigger-icon" id="editCatIcon">🌸</span>
            <span class="modal-trigger-text" id="editCatText">Select Category</span>
            <span class="modal-trigger-chevron">▾</span>
          </div>
          <div class="modal-dropdown-menu" id="editCatMenu">
            <?php foreach($categories as $cat):
              $icon = $catIcons[$cat['name']] ?? '🌸';
            ?>
            <div class="modal-drop-item"
                 data-id="<?= intval($cat['id']) ?>"
                 data-icon="<?= $icon ?>"
                 data-name="<?= htmlspecialchars($cat['name']) ?>"
                 onclick="pickModalCat('editCat','<?= intval($cat['id']) ?>','<?= $icon ?>','<?= htmlspecialchars($cat['name']) ?>')">
              <span class="modal-drop-item-icon"><?= $icon ?></span>
              <?= htmlspecialchars($cat['name']) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="field-group">
        <label>Stock</label>
        <div class="field-icon-wrap">
          <span class="field-icon">📦</span>
          <input type="number" name="stock" id="editStock" min="0" placeholder="0" required>
        </div>
      </div>

      <div class="modal-divider"></div>
      <div class="modal-actions">
        <button type="submit" class="btn-save">✦ Save Changes</button>
        <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
      </div>
    </form>
    </div>
  </div>
</div>

<script>
// ✨ Girly dropdown logic
function toggleCatDropdown() {
  var t=document.getElementById('catSelectTrigger');
  var m=document.getElementById('catDropdownMenu');
  var open=m.classList.toggle('open');
  t.classList.toggle('open',open);
}

function selectCat(el) {
  document.querySelectorAll('#catDropdownMenu .dropdown-item').forEach(function(i){i.classList.remove('selected');});
  el.classList.add('selected');
  var val=el.getAttribute('data-value');
  var icon=el.getAttribute('data-icon');
  document.getElementById('catTriggerText').textContent = val==='all' ? 'All Categories' : val;
  document.getElementById('catTriggerIcon').textContent = icon;
  document.getElementById('catDropdownMenu').classList.remove('open');
  document.getElementById('catSelectTrigger').classList.remove('open');
  filterCategory(val);
}

// Close dropdown when clicking outside
document.addEventListener('click',function(e){
  var wrap=document.getElementById('catSelectWrap');
  if(wrap && !wrap.contains(e.target)){
    document.getElementById('catDropdownMenu').classList.remove('open');
    document.getElementById('catSelectTrigger').classList.remove('open');
  }
});

// Category filter (navigate)
function filterCategory(cat){
  window.location.href = cat==='all' ? 'admin_inventory.php' : 'admin_inventory.php?category='+encodeURIComponent(cat);
}

// Live search
document.getElementById('inventorySearch').addEventListener('input',function(){
  var f=this.value.toLowerCase().trim(), v=0;
  document.querySelectorAll('.product-item').forEach(function(item){
    var m=item.getAttribute('data-name').includes(f);
    item.style.display=m?'flex':'none';
    if(m)v++;
  });
  document.getElementById('listCount').textContent=v+' product'+(v!==1?'s':'');
});

// Select product (left → right)
function selectProduct(el){
  document.querySelectorAll('.product-item').forEach(function(i){i.classList.remove('active');});
  document.querySelectorAll('.ls-detail').forEach(function(d){d.classList.remove('visible');});
  el.classList.add('active');
  var detail=document.getElementById('detail-'+el.getAttribute('data-id'));
  if(detail) detail.classList.add('visible');
}

// Modal custom dropdown helpers
function toggleModalDrop(prefix) {
  var trigger = document.getElementById(prefix+'Trigger');
  var menu    = document.getElementById(prefix+'Menu');
  var isOpen  = menu.classList.toggle('open');
  trigger.classList.toggle('open', isOpen);
}
function pickModalCat(prefix, id, icon, name) {
  // update hidden input
  document.getElementById(prefix.replace('Cat','')+'Category').value = id;
  // update trigger display
  document.getElementById(prefix+'Icon').textContent = icon;
  document.getElementById(prefix+'Text').textContent = name;
  // mark selected
  document.querySelectorAll('#'+prefix+'Menu .modal-drop-item').forEach(function(i){i.classList.remove('selected');});
  event.currentTarget.classList.add('selected');
  // close
  document.getElementById(prefix+'Menu').classList.remove('open');
  document.getElementById(prefix+'Trigger').classList.remove('open');
}
// Close modal dropdowns on outside click
document.addEventListener('click', function(e) {
  ['addCat','editCat'].forEach(function(prefix){
    var wrap = document.getElementById(prefix+'Wrap');
    if(wrap && !wrap.contains(e.target)){
      document.getElementById(prefix+'Menu').classList.remove('open');
      document.getElementById(prefix+'Trigger').classList.remove('open');
    }
  });
});
// Set add category default on modal open
function openAddModal(){
  document.getElementById('addProductForm').reset();
  document.getElementById('addImagePreview').style.display='none';
  // reset custom dropdown to first category
  var first = document.querySelector('#addCatMenu .modal-drop-item');
  if(first){
    document.getElementById('addCategory').value = first.getAttribute('data-id');
    document.getElementById('addCatIcon').textContent = first.getAttribute('data-icon');
    document.getElementById('addCatText').textContent = first.getAttribute('data-name');
    document.querySelectorAll('#addCatMenu .modal-drop-item').forEach(function(i){i.classList.remove('selected');});
    first.classList.add('selected');
  }
  document.getElementById('addProductModal').classList.add('active');
}

// Edit modal
function openEditModal(id,name,desc,price,catId,catName,stock){
  document.getElementById('editProductId').value=id;
  document.getElementById('editName').value=name;
  document.getElementById('editDescription').value=desc;
  document.getElementById('editPrice').value=price;
  document.getElementById('editCategory').value=catId;
  document.getElementById('editStock').value=stock;
  // sync custom dropdown
  var icon = '🌸';
  document.querySelectorAll('#editCatMenu .modal-drop-item').forEach(function(i){
    i.classList.remove('selected');
    if(i.getAttribute('data-id')==catId){
      i.classList.add('selected');
      icon = i.getAttribute('data-icon');
    }
  });
  document.getElementById('editCatIcon').textContent = icon;
  document.getElementById('editCatText').textContent = catName;
  document.getElementById('editModal').classList.add('active');
}
function closeEditModal(){document.getElementById('editModal').classList.remove('active');}

// Delete modal
function confirmDeleteProduct(id,name){
  document.getElementById('deleteProductId').value=id;
  document.getElementById('deleteProductNameText').textContent=name;
  document.getElementById('deleteProductModal').classList.add('active');
}
function closeDeleteProductModal(){document.getElementById('deleteProductModal').classList.remove('active');}

// Edit AJAX
document.getElementById('editProductForm').addEventListener('submit',function(e){
  e.preventDefault();
  fetch('admin_inventory.php',{method:'POST',body:new FormData(this)})
    .then(function(r){return r.json();})
    .then(function(d){if(d.success){closeEditModal();location.reload();}else alert('Error: '+d.error);})
    .catch(function(){alert('An error occurred.');});
});

// Backdrop close
['deleteProductModal','editModal','addProductModal'].forEach(function(id){
  document.getElementById(id).addEventListener('click',function(e){if(e.target===this)this.classList.remove('active');});
});

// Add modal (defined above)
function closeAddModal(){document.getElementById('addProductModal').classList.remove('active');}

function previewAddImage(input){
  var preview=document.getElementById('addImagePreview');
  if(input.files&&input.files[0]){
    var r=new FileReader();
    r.onload=function(e){preview.src=e.target.result;preview.style.display='block';};
    r.readAsDataURL(input.files[0]);
  } else {preview.style.display='none';}
}

// Add AJAX
document.getElementById('addProductForm').addEventListener('submit',function(e){
  e.preventDefault();
  var btn=this.querySelector('.btn-save');
  btn.textContent='Adding…'; btn.disabled=true;
  fetch('admin_inventory.php',{method:'POST',body:new FormData(this)})
    .then(function(r){return r.json();})
    .then(function(data){
      btn.textContent='Add Product'; btn.disabled=false;
      if(!data.success){alert('Error: '+data.error);return;}
      closeAddModal();
      var stock=data.stock;
      var sc=stock>10?'in-stock':(stock>0?'low-stock':'out-of-stock');
      var st=stock>10?'In Stock':(stock>0?'Low Stock':'Out of Stock');
      var imgSrc='images/'+data.image;
      var sN=data.name.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
      var sD=data.description.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
      var sCat=(data.category_name||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'");

      // Add list item
      var li=document.createElement('div');
      li.className='product-item new-item';
      li.setAttribute('data-id',data.id);
      li.setAttribute('data-name',data.name.toLowerCase());
      li.setAttribute('onclick','selectProduct(this)');
      li.innerHTML='<img class="product-thumb" src="'+imgSrc+'" onerror="this.src=\'images/placeholder.png\'" alt="'+data.name+'">'
        +'<div class="pi-info"><div class="pi-name">'+data.name+'</div><div class="pi-cat">'+(data.category_name||'Uncategorised')+'</div></div>'
        +'<div class="pi-badge"><span class="stock-badge '+sc+'">'+st+'</span></div>';
      var list=document.getElementById('productList');
      var es=document.getElementById('emptyState'); if(es)es.remove();
      var first=list.querySelector('.product-item');
      first?list.insertBefore(li,first):list.appendChild(li);

      // Add detail panel
      var dp=document.createElement('div');
      dp.className='ls-detail'; dp.id='detail-'+data.id;
      dp.innerHTML='<div class="detail-head">'
        +'<img class="detail-img" src="'+imgSrc+'" onerror="this.src=\'images/placeholder.png\'" alt="'+data.name+'">'
        +'<div class="detail-head-info"><div class="detail-name">'+data.name+'</div>'
        +'<div class="detail-cat">'+(data.category_name||'Uncategorised')+' &nbsp;·&nbsp; <span class="stock-badge '+sc+'">'+st+'</span></div></div>'
        +'<div class="detail-head-actions">'
        +'<button class="action-btn" title="Edit" onclick="openEditModal('+data.id+',\''+sN+'\',\''+sD+'\','+data.price+','+data.category_id+',\''+sCat+'\','+stock+')">✎</button>'
        +'<button class="action-btn danger" title="Delete" onclick="confirmDeleteProduct('+data.id+',\''+sN+'\')">🗑️</button>'
        +'</div></div>'
        +'<div class="detail-body">'
        +'<div class="detail-stats">'
        +'<div class="stat-card"><div class="stat-label">Price</div><div class="stat-value">₱'+parseFloat(data.price).toFixed(2)+'</div><div class="stat-sub">per unit</div></div>'
        +'<div class="stat-card"><div class="stat-label">Stock</div><div class="stat-value">'+stock+'</div><div class="stat-sub">units remaining</div></div>'
        +'<div class="stat-card"><div class="stat-label">Category</div><div class="stat-value" style="font-size:20px;">'+(data.category_name||'—')+'</div><div class="stat-sub">product type</div></div>'
        +'</div>'
        +'<div class="detail-section"><div class="detail-section-label">Description</div>'
        +'<div class="detail-desc'+(data.description?'':' empty')+'">'+(data.description||'No description provided.')+'</div></div>'
        +'<div class="detail-section"><div class="detail-section-label">Update Stock</div>'
        +'<form method="POST" action="admin_inventory.php">'
        +'<input type="hidden" name="product_id" value="'+data.id+'">'
        +'<input type="hidden" name="update_stock" value="1">'
        +'<div class="stock-update-row">'
        +'<span class="stock-current">Current: <strong>'+stock+'</strong></span>'
        +'<input type="number" name="new_stock" value="'+stock+'" min="0" placeholder="New qty" required>'
        +'<button type="submit" class="btn-update">Save Stock</button>'
        +'</div></form></div>'
        +'</div>';
      document.getElementById('detailPane').appendChild(dp);

      // Update count
      var ce=document.getElementById('listCount');
      var n=(parseInt(ce.textContent)||0)+1;
      ce.textContent=n+' product'+(n!==1?'s':'');
      selectProduct(li);
    })
    .catch(function(){btn.textContent='Add Product';btn.disabled=false;alert('An error occurred.');});
});
// Initialize add-modal hidden input to first category on page load
(function(){
  var first = document.querySelector('#addCatMenu .modal-drop-item');
  if(first) document.getElementById('addCategory').value = first.getAttribute('data-id');
})();
// Hardened product click (works for old and newly added rows)
document.getElementById('productList').addEventListener('click', function (e) {
  var item = e.target.closest('.product-item');
  if (!item) return;
  selectProduct(item);
});
window.addEventListener('error', function (ev) {
  console.log('Page error:', ev.message, 'line', ev.lineno);
});
</script>
</body>
</html>