<?php
require_once 'db.php';

// 1. Get details from the Session (saved by confirmation.php)
$details = $_SESSION['checkout_details'] ?? null;

// Security Check: If the cart is empty or the session details are missing, kick back to cart
if ($store_is_prod) {
    if (empty($_SESSION['cart']) || !$details) {
        header("Location: checkout.php");
        exit;
    }
}

// Extract variables from the session array
$name    = htmlspecialchars($details['name']);
$email   = htmlspecialchars($details['email']);
$scout_name   = htmlspecialchars($details['scout_name']);
$payment = $details['payment'];
$address = htmlspecialchars($details['address']);
$comments = isset($details['comments']) ? htmlspecialchars($details['comments']) : 'N/A';

// 2. Calculate Grand Total and Prepare Items
$grand_total = 0;
$items_to_save = [];

foreach ($_SESSION['cart'] as $id => $qty) {
    $stmt = $pdo->prepare("SELECT name, price FROM {$tab_prefix}_products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if ($product) {
        $subtotal = $product['price'] * $qty;
        $grand_total += $subtotal;
        
        $items_to_save[] = [
            'name' => $product['name'],
            'qty' => $qty,
            'price' => $product['price'],
            'subtotal' => $subtotal
        ];
    }
}

if ($store_is_prod) {
    // 3. Insert Main Order
    $sqlOrder = "INSERT INTO {$tab_prefix}_orders (customer_name, address, email, scout_name, payment_mode, total_amount, comments) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmtOrder = $pdo->prepare($sqlOrder);
    $stmtOrder->execute([$name, $address, $email, $scout_name, $payment, $grand_total, $comments]);

    $newOrderId = $pdo->lastInsertId(); 

    // 4. Insert Individual Items
    $sqlItems = "INSERT INTO {$tab_prefix}_order_items (order_id, product_name, quantity, price_per_item, subtotal) 
                VALUES (?, ?, ?, ?, ?)";
    $stmtItems = $pdo->prepare($sqlItems);

    foreach ($items_to_save as $item) {
        $stmtItems->execute([
            $newOrderId, 
            $item['name'], 
            $item['qty'], 
            $item['price'], 
            $item['subtotal']
        ]);
    }

    // 5. Clear the Cart and the temporary checkout session
    unset($_SESSION['cart']); 
    unset($_SESSION['checkout_details']);
}
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Meat Sticks & Chocolate Fundraiser'; include 'header-html.php'; ?>

    <div class="main-container">
        <h2 class="headings">Order Placed</h2>
        
        <div class="large-emoji">🫡🔥⛺</div>
        <h1>Thanks <?php echo htmlspecialchars($name); ?>!</h1>
        <p>Your order for has been received. Order number is <b>#<?php echo $newOrderId; ?></b></p>
        <div class="no-print">
            <p>Please print this page for your records. 
                You can lookup your order details in <a href="view_order.php?email=<?php echo htmlspecialchars($email); ?>" target="_blank">this page</a></p>
        </div>        
        
        <div class="order-summary">
            <h3>Total Amount: $<?php echo number_format($grand_total, 2); ?></h3>
            <p>Payment Method Selected: <strong><?php echo htmlspecialchars($payment); ?></strong></p>
        <?php if ($payment === 'Venmo'): ?>
            <p><a href="https://account.venmo.com/pay?amount=<?php echo rawurlencode($grand_total); ?>&note=Plant%20Sales%20<?php echo rawurlencode($scout_name); ?>&recipients=troop60" target="_blank">Click here</a> to pay now</p>
        <?php endif; ?>
            <p>Please follow the Troop's standard instructions for your payment.</p>
        </div>
        <div class="order-summary">
            <p>
            For Venmo payments, You are in the right place if it says, "<b>Greg LeBlanc</b> @Troop60". Please indicate in the comment of your payment what and who it is for. For instance, you can mention the fundraiser and/or the scouts name.
            </p>
            <p>
            <a href="https://venmo.com/troop60" target="_blank">
                <img src="media/Troop_60_Venmo.png" alt="Troop 60 Venmo" class="venmo-logo">
            </a>
            </p>
        </div>
        <div class="no-print">
            <a href="index.php" class="btn">Return Home</a>
        </div>        
    </div>
</body>
</html>