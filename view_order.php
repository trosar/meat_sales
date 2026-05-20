<?php
require_once 'db.php';
date_default_timezone_set('America/Los_Angeles');

$email_query = $_GET['email'] ?? '';
$email_query = trim($email_query);
$orders = [];

if ($email_query) {
    $stmt = $pdo->prepare("SELECT id, scout_name, status, order_date, total_amount FROM {$tab_prefix}_orders WHERE email = ? ORDER BY order_date DESC");
    $stmt->execute([$email_query]);
    $orders = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Meat Sticks & Chocolate Fundraiser'; include 'header-html.php'; ?>


<div class="main-container">
    <h2 class="headings">Lookup Your Orders</h2>
    
    <div class="view-order-search-box">
        <form method="GET" action="view_order.php">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" 
                    required value="<?php echo htmlspecialchars($email_query); ?>" 
                    placeholder="Enter the email you used to place your order(s)">
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-confirm">Find My Orders</button>
                <a href="index.php" class="btn btn-back">Back Home</a>
            </div>

        </form>
    </div>

    <?php if ($email_query): ?>
        <?php if (empty($orders)): ?>
            <p class="error-message">Sorry, We couldn't find any orders associated with this email address.</p>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <strong>Order #<?php echo $order['id']; ?></strong><br>
                            <small><?php echo formatLocalDate($order['order_date'], 'M j, Y'); ?></small>
                        </div>
                        <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                            <?php echo strtoupper($order['status']); ?>
                        </span>
                    </div>
                    
                    <p><strong>Scout:</strong> <?php echo htmlspecialchars($order['scout_name']); ?></p>
                    
                    <div class="view-order-items">
                        <?php
                        $itemStmt = $pdo->prepare("SELECT product_name, quantity, subtotal FROM {$tab_prefix}_order_items WHERE order_id = ?");
                        $itemStmt->execute([$order['id']]);
                        while ($item = $itemStmt->fetch()):
                        ?>
                            <div class="view-order-item-row">
                                <span><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['product_name']); ?></span>
                                <span>$<?php echo number_format($item['subtotal'], 2); ?></span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <div class="total-row">
                        Total: $<?php echo number_format($order['total_amount'], 2); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>