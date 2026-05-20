<?php
require_once 'db.php';

// Save POST data into session so it persists if they go back
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['checkout_details'] = [
        'name'       => $_POST['name'] ?? '',
        'email'      => $_POST['email'] ?? '',
        'address'    => $_POST['address'] ?? '',
        'scout_name' => $_POST['scout_name'] ?? '',
        'payment'    => $_POST['payment'] ?? '',
        'comments'   => isset($_POST['comments']) ? trim($_POST['comments']) : ''
    ];
}

// If they didn't come from the checkout form, send them back
if (!isset($_POST['name'])) {
    header("Location: checkout.php");
    exit;
}

$grand_total = 0;
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Meat Sticks & Chocolate Fundraiser'; include 'header-html.php'; ?>
<div class="main-container">
    <h2 class="headings">Review Your Order</h2>
    <div class="review-section">
        <div class="review-label">Customer Name</div>
        <div class="review-value"><?php echo htmlspecialchars($_POST['name']); ?></div>
    </div>
    <div class="review-section">
        <div class="review-label">Delivery Address</div>
        <div class="review-value"><?php echo htmlspecialchars($_POST['address']); ?></div>
    </div>
    <div class="review-section">
        <div class="review-label">Email Address</div>
        <div class="review-value"><?php echo htmlspecialchars($_POST['email']); ?></div>
    </div>
    <div class="review-section">
        <div class="review-label">Credit to Scout</div>
        <div class="review-value"><?php echo htmlspecialchars($_POST['scout_name']); ?></div>
    </div>
    <div class="review-section">
        <div class="review-label">Comments</div>
        <div class="review-value"><?php echo htmlspecialchars($_POST['comments']); ?></div>
    </div>
    <div class="review-section">
        <div class="review-label">Payment Method</div>
        <div class="review-value"><?php echo htmlspecialchars($_POST['payment']); ?></div>
    </div>

    <h3>Items in Cart</h3>
    <?php $editable = false; include 'cart_items.php'; ?>

    <div class="btn-group">
        <a href="checkout.php" class="btn btn-back">Back to Cart</a>
        <form action="process_order.php" method="POST">
            <button type="submit" class="btn btn-confirm">Place Order</button>
        </form>
    </div>
</div>

</body>
</html>