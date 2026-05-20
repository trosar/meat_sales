<?php
$is_ajax = true; // Tell db.php not to redirect
require_once 'db.php';
if (isset($_POST['product_id'])) {
    $p_id = $_POST['product_id'];
    $qty = (int)$_POST['quantity'];
    $_SESSION['cart'][$p_id] = ($_SESSION['cart'][$p_id] ?? 0) + $qty;
    echo array_sum($_SESSION['cart']); // Return new total count
}