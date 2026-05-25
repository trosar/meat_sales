<?php
require_once 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_purchase'])) {
        $stmt = $pdo->prepare("INSERT INTO {$tab_prefix}_purchases (purchase_date, purchaser_name, product_name, unit_purchase_price, unit_sale_price, qty_purchased, comments) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['purchase_date'], $_POST['purchaser_name'], $_POST['product_name'], $_POST['unit_purchase_price'], $_POST['unit_sale_price'], $_POST['qty_purchased'], $_POST['comments']]);
        $_SESSION['success_msg'] = "Purchase recorded for " . $_POST['product_name'];
    } elseif (isset($_POST['update_purchase'])) {
        $stmt = $pdo->prepare("UPDATE {$tab_prefix}_purchases 
                               SET purchase_date = ?, purchaser_name = ?, product_name = ?, unit_purchase_price = ?, unit_sale_price = ?, qty_purchased = ?, comments = ? 
                               WHERE purchase_date = ? AND purchaser_name = ? AND product_name = ? LIMIT 1");
        $stmt->execute([
            $_POST['purchase_date'], $_POST['purchaser_name'], $_POST['product_name'], $_POST['unit_purchase_price'], $_POST['unit_sale_price'], $_POST['qty_purchased'], $_POST['comments'],
            $_POST['orig_purchase_date'], $_POST['orig_purchaser_name'], $_POST['orig_product_name']
        ]);
        $_SESSION['success_msg'] = "Purchase record updated.";
    } elseif (isset($_POST['delete_purchase'])) {
        $stmt = $pdo->prepare("DELETE FROM {$tab_prefix}_purchases WHERE purchase_date = ? AND purchaser_name = ? AND product_name = ? LIMIT 1");
        $stmt->execute([$_POST['purchase_date'], $_POST['purchaser_name'], $_POST['product_name']]);
        $_SESSION['success_msg'] = "Purchase record deleted.";
    }
    header("Location: purchases.php");
    exit;
}

$purchases = $pdo->query("SELECT * FROM {$tab_prefix}_purchases ORDER BY purchase_date DESC")->fetchAll();
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Manage Purchases'; include 'header-html.php'; ?>

<div class="main-container">
    <?php include 'menu.php'; ?>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="success-message">
            ✅ <?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
        </div>
    <?php endif; ?>

    <div class="order-card">
        <h2 class="headings" id="form_title">Log New Purchase</h2>
        <form id="edit_form" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <input type="hidden" name="orig_purchase_date">
            <input type="hidden" name="orig_purchaser_name">
            <input type="hidden" name="orig_product_name">

            <!-- Header fields grouped to match 3 per row alignment -->
            <div style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Purchaser</label>
                    <input type="text" name="purchaser_name" placeholder="Name" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Product</label>
                    <input type="text" name="product_name" required>
                </div>
            </div>

            <!-- Financial fields grouped to stay on one line on desktop -->
            <div style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Unit Cost ($)</label>
                    <input type="number" step="0.01" name="unit_purchase_price" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Suggested Sale ($)</label>
                    <input type="number" step="0.01" name="unit_sale_price">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Qty Purchased</label>
                    <input type="number" name="qty_purchased" required min="1">
                </div>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Comments</label>
                <input type="text" name="comments">
            </div>
            <div style="grid-column: 1 / -1; text-align: right;">
                <button type="button" id="cancel_btn" onclick="cancelEdit()" class="btn btn-back" style="display:none;">Cancel Edit</button>
                <button type="submit" id="submit_btn" name="add_purchase" class="btn btn-confirm">Log Purchase</button>
            </div>
        </form>
    </div>

    <div class="order-card">
        <h2 class="headings">Purchase History</h2>
        <div style="overflow-x: auto;">
            <table class="stack-mobile">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Purchaser</th>
                        <th class="right-align">Qty</th>
                        <th class="right-align">Unit Cost</th>
                        <th class="right-align">Total Cost</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $row): ?>
                        <tr>
                            <td data-label="Date"><strong><?php echo date('M j, Y', strtotime($row['purchase_date'])); ?></strong></td>
                            <td data-label="Product"><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td data-label="Purchaser"><?php echo htmlspecialchars($row['purchaser_name']); ?></td>
                            <td data-label="Qty" class="right-align"><?php echo $row['qty_purchased']; ?></td>
                            <td data-label="Unit Cost" class="right-align">$<?php echo number_format($row['unit_purchase_price'], 2); ?></td>
                            <td data-label="Total Cost" class="right-align" style="font-weight: bold;">
                                $<?php echo number_format($row['qty_purchased'] * $row['unit_purchase_price'], 2); ?>
                            </td>
                            <td data-label="Action" style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn-edit" 
                                        style="border:none; background:none; cursor:pointer;"
                                        onclick="editPurchase(this)"
                                        data-date="<?php echo date('Y-m-d', strtotime($row['purchase_date'])); ?>"
                                        data-purchaser="<?php echo htmlspecialchars($row['purchaser_name']); ?>"
                                        data-product="<?php echo htmlspecialchars($row['product_name']); ?>"
                                        data-unit-cost="<?php echo $row['unit_purchase_price']; ?>"
                                        data-sale-price="<?php echo $row['unit_sale_price']; ?>"
                                        data-qty="<?php echo $row['qty_purchased']; ?>"
                                        data-comments="<?php echo htmlspecialchars($row['comments']); ?>">
                                    Edit
                                </button>

                                <form method="POST" onsubmit="return confirm('Delete this record?');">
                                    <input type="hidden" name="purchase_date" value="<?php echo $row['purchase_date']; ?>">
                                    <input type="hidden" name="purchaser_name" value="<?php echo htmlspecialchars($row['purchaser_name']); ?>">
                                    <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($row['product_name']); ?>">
                                    <button type="submit" name="delete_purchase" class="btn-remove" style="border:none; background:none; cursor:pointer;">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.success-message').forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s ease';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }, 5000);
    });

    function editPurchase(btn) {
        const data = btn.dataset;
        document.getElementById('form_title').innerText = "Update Purchase Record";
        document.getElementById('submit_btn').innerText = "Save Changes";
        document.getElementById('submit_btn').name = "update_purchase";
        document.getElementById('cancel_btn').style.display = "inline-block";
        
        const form = document.getElementById('edit_form');
        form.purchase_date.value = data.date;
        form.purchaser_name.value = data.purchaser;
        form.product_name.value = data.product;
        form.unit_purchase_price.value = data.unitCost;
        form.unit_sale_price.value = data.salePrice;
        form.qty_purchased.value = data.qty;
        form.comments.value = data.comments;

        form.orig_purchase_date.value = data.date;
        form.orig_purchaser_name.value = data.purchaser;
        form.orig_product_name.value = data.product;

        window.scrollTo({ top: 0, behavior: 'smooth' });
        form.purchaser_name.focus();
    }

    function cancelEdit() {
        document.getElementById('edit_form').reset();
        document.getElementById('form_title').innerText = "Log New Purchase";
        document.getElementById('submit_btn').innerText = "Log Purchase";
        document.getElementById('submit_btn').name = "add_purchase";
        document.getElementById('cancel_btn').style.display = "none";
    }
</script>
</body>
</html>