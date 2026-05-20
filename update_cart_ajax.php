<?php
$is_ajax = true;
require_once 'db.php';

$response = ['status' => 'error', 'grand_total' => '0.00'];

if (isset($_POST['action'])) {
    $id = $_POST['product_id'];
    $new_subtotal = 0; // Initialize variable
    
    // 1. Update the Session
    if ($_POST['action'] === 'update') {
        $qty = (int)$_POST['quantity'];
        if ($qty >= 1 && $qty <= 9) {
            $_SESSION['cart'][$id] = $qty;
        } else {
            unset($_SESSION['cart'][$id]);
        }
    } elseif ($_POST['action'] === 'remove') {
        unset($_SESSION['cart'][$id]);
    }

    // 2. Calculate the NEW subtotal for this specific item
    // We do this so the JavaScript doesn't have to guess the price
    if (isset($_SESSION['cart'][$id])) {
        $stmt = $pdo->prepare("SELECT price FROM {$tab_prefix}_products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if ($product) {
            $new_subtotal = $product['price'] * $_SESSION['cart'][$id];
        }
    }

    // 3. Calculate the new Grand Total for the whole cart
    $total = 0;
    foreach ($_SESSION['cart'] as $pid => $pqty) {
        $stmt = $pdo->prepare("SELECT price FROM {$tab_prefix}_products WHERE id = ?");
        $stmt->execute([$pid]);
        if ($res = $stmt->fetch()) { 
            $total += ($res['price'] * $pqty); 
        }
    }

    // 4. Send EVERYTHING back to the JavaScript
    $response = [
        'status' => 'success',
        'grand_total' => number_format($total, 2),
        'new_subtotal' => number_format($new_subtotal, 2), // The JS needs this!
        'cart_empty' => empty($_SESSION['cart'])
    ];
}

header('Content-Type: application/json');
echo json_encode($response);