<?php
require_once 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

// Fetch consolidated inventory data
// We base the product list on the purchases table
$sql = "SELECT 
            p.product_name,
            p.total_purchased,
            COALESCE(ss.shift_sold, 0) as shift_sold,
            COALESCE(indiv.indiv_paid, 0) as indiv_paid,
            COALESCE(indiv.indiv_unpaid, 0) as indiv_unpaid
        FROM (
            SELECT product_name, SUM(qty_purchased) as total_purchased
            FROM {$tab_prefix}_purchases
            GROUP BY product_name
        ) p
        LEFT JOIN (
            SELECT product_name, SUM(qty_sold) as shift_sold 
            FROM {$tab_prefix}_shift_sales 
            GROUP BY product_name
        ) ss ON p.product_name = ss.product_name
        LEFT JOIN (
            SELECT pr.internal_name, 
                SUM(CASE WHEN o.status = 'Paid' THEN oi.quantity ELSE 0 END) as indiv_paid,
                SUM(CASE WHEN o.status != 'Paid' THEN oi.quantity ELSE 0 END) as indiv_unpaid
            FROM {$tab_prefix}_order_items oi
            JOIN {$tab_prefix}_orders o ON oi.order_id = o.id
            JOIN {$tab_prefix}_products pr ON oi.product_name = pr.name
            WHERE o.status != 'Cancelled'
            GROUP BY pr.internal_name
        ) indiv ON p.product_name = indiv.internal_name
        ORDER BY p.product_name ASC";

$inventory = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Inventory Report'; include 'header-html.php'; ?>

<div class="main-container">
    <?php 
        $nav_no_print = true; 
        include 'menu.php'; 
    ?>

    <div class="order-card">
        <h2 class="headings">Product Inventory Status</h2>
        <div style="overflow-x: auto;">
            <table class="stack-mobile">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th class="right-align">Purchased</th>
                        <th class="right-align">Shift Sales</th>
                        <th class="right-align">Indiv. (Paid)</th>
                        <th class="right-align">Indiv. (Unpaid)</th>
                        <th class="right-align">Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                        <tr><td colspan="6" style="text-align:center; padding: 20px;">No inventory data available.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($inventory as $row): 
                        $remaining = $row['total_purchased'] - $row['shift_sold'] - $row['indiv_paid'] - $row['indiv_unpaid'];
                        $status_color = ($remaining <= 0) ? '#d32f2f' : (($remaining < 10) ? '#f57c00' : 'inherit');
                    ?>
                        <tr>
                            <td data-label="Product"><strong><?php echo htmlspecialchars($row['product_name']); ?></strong></td>
                            <td data-label="Purchased" class="right-align"><?php echo number_format($row['total_purchased']); ?></td>
                            <td data-label="Shift Sales" class="right-align"><?php echo number_format($row['shift_sold']); ?></td>
                            <td data-label="Indiv. (Paid)" class="right-align"><?php echo number_format($row['indiv_paid']); ?></td>
                            <td data-label="Indiv. (Unpaid)" class="right-align"><?php echo number_format($row['indiv_unpaid']); ?></td>
                            <td data-label="Remaining" class="right-align" style="font-weight:bold; color: <?php echo $status_color; ?>;">
                                <?php echo number_format($remaining); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>