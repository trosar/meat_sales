<?php
require_once 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_sale'])) {
        $stmt = $pdo->prepare("INSERT INTO {$tab_prefix}_shift_sales (shift_date, shift_time, product_name, qty_sold, total_sales, total_donations, comments) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['shift_date'], $_POST['shift_time'], $_POST['product_name'], $_POST['qty_sold'], $_POST['total_sales'], $_POST['total_donations'], $_POST['comments']]);
        $_SESSION['success_msg'] = "Shift sale recorded for " . $_POST['product_name'];
    } elseif (isset($_POST['update_sale'])) {
        $stmt = $pdo->prepare("UPDATE {$tab_prefix}_shift_sales SET shift_date = ?, shift_time = ?, product_name = ?, qty_sold = ?, total_sales = ?, total_donations = ?, comments = ? WHERE shift_date = ? AND shift_time = ? AND product_name = ? LIMIT 1");
        $stmt->execute([
            $_POST['shift_date'], $_POST['shift_time'], $_POST['product_name'], $_POST['qty_sold'], $_POST['total_sales'], $_POST['total_donations'], $_POST['comments'],
            $_POST['orig_shift_date'], $_POST['orig_shift_time'], $_POST['orig_product_name']
        ]);
        $_SESSION['success_msg'] = "Shift sale updated.";
    } elseif (isset($_POST['delete_sale'])) {
        // Log the record before deletion
        $fetchStmt = $pdo->prepare("SELECT * FROM {$tab_prefix}_shift_sales WHERE shift_date = ? AND shift_time = ? AND product_name = ? LIMIT 1");
        $fetchStmt->execute([$_POST['shift_date'], $_POST['shift_time'], $_POST['product_name']]);
        $record = $fetchStmt->fetch();
        if ($record) {
            $logStmt = $pdo->prepare("INSERT INTO {$tab_prefix}_delete_log (log_timestamp, page, log_message) VALUES (CURRENT_TIMESTAMP, 'shift_sales.php', ?)");
            $logStmt->execute([generateInsertSql("{$tab_prefix}_shift_sales", $record, $pdo)]);
        }

        $stmt = $pdo->prepare("DELETE FROM {$tab_prefix}_shift_sales WHERE shift_date = ? AND shift_time = ? AND product_name = ? LIMIT 1");
        $stmt->execute([$_POST['shift_date'], $_POST['shift_time'], $_POST['product_name']]);
        $_SESSION['success_msg'] = "Shift sale record deleted.";
    }
    header("Location: shift_sales.php");
    exit;
}

$sales = $pdo->query("SELECT * FROM {$tab_prefix}_shift_sales ORDER BY shift_date ASC, shift_time ASC")->fetchAll();
$products_list = $pdo->query("SELECT product_name, MAX(unit_sale_price) as unit_sale_price FROM {$tab_prefix}_purchases GROUP BY product_name ORDER BY product_name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Manage Shift Sales'; include 'header-html.php'; ?>

<div class="main-container">
    <?php $nav_no_print = true; include 'menu.php'; ?>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="success-message no-print">
            ✅ <?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
        </div>
    <?php endif; ?>

    <div class="order-card no-print">
        <h2 class="headings" id="form_title">Record New Shift Sale</h2>
        <form id="edit_form" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <input type="hidden" name="orig_shift_date">
            <input type="hidden" name="orig_shift_time">
            <input type="hidden" name="orig_product_name">

            <!-- Header fields grouped to match financial fields alignment -->
            <div style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Date</label>
                    <input type="date" name="shift_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Time Slot</label>
                    <select name="shift_time" required>
                        <option value="">-- Select Time Slot --</option>
                        <option value="11am to 1pm">11am to 1pm</option>
                        <option value="1pm to 3pm">1pm to 3pm</option>
                        <option value="3pm to 5pm">3pm to 5pm</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Product</label>
                    <select name="product_name" required onchange="calculateTotal()">
                        <option value="" data-price="0">-- Select Product --</option>
                        <?php foreach ($products_list as $p): ?>
                            <option value="<?php echo htmlspecialchars($p['product_name']); ?>" data-price="<?php echo $p['unit_sale_price']; ?>">
                                <?php echo htmlspecialchars($p['product_name']); ?> ($<?php echo number_format($p['unit_sale_price'], 2); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Financial fields grouped to stay on one line on desktop -->
            <div style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Qty Sold</label>
                    <input type="number" name="qty_sold" value="0" min="0" oninput="calculateTotal()">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Total Sales ($)</label>
                    <input type="number" step="0.01" name="total_sales" value="0.00" readonly style="background: #eee; cursor: not-allowed;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Donations ($)</label>
                    <input type="number" step="0.01" name="total_donations" value="0.00">
                </div>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Comments</label>
                <input type="text" name="comments">
            </div>
            <div style="grid-column: 1 / -1; text-align: right;">
                <button type="button" id="cancel_btn" onclick="cancelEdit()" class="btn btn-back" style="display:none;">Cancel Edit</button>
                <button type="submit" id="submit_btn" name="add_sale" class="btn btn-confirm">Add Sale Record</button>
            </div>
        </form>
    </div>

    <div class="order-card">
        <h2 class="headings">Shift Sales History</h2>
        <div style="overflow-x: auto;">
            <table class="stack-mobile">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Product</th>
                        <th class="right-align">Qty</th>
                        <th class="right-align">Sales</th>
                        <th class="right-align">Donations</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $lastDate = null;
                    $lastTime = null;
                    $subQty = 0;
                    $subSales = 0;
                    $subDonations = 0;
                    $count = count($sales);

                    foreach ($sales as $index => $row): 
                        $isNewDate = ($row['shift_date'] !== $lastDate);
                        $isNewTimeSlot = ($row['shift_time'] !== $lastTime);

                        // If new slot/date (and not first row), print subtotal for the previous group
                        if ($index > 0 && ($isNewDate || $isNewTimeSlot)):
                    ?>
                        <tr class="subtotal-row">
                            <td data-label="Summary" colspan="2" style="text-align: right;">Time Slot Subtotal:</td>
                            <td data-label="Subtotal Qty" class="right-align"><?php echo $subQty; ?></td>
                            <td data-label="Subtotal Sales" class="right-align">$<?php echo number_format($subSales, 2); ?></td>
                            <td data-label="Subtotal Donations" class="right-align">$<?php echo number_format($subDonations, 2); ?></td>
                            <td data-label="Total Earned" class="right-align" style="color: var(--primary-color);">
                                $<?php echo number_format($subSales + $subDonations, 2); ?>
                            </td>
                        </tr>
                    <?php 
                            $subQty = 0; $subSales = 0; $subDonations = 0;
                        endif;

                        if ($isNewDate):
                            $lastDate = $row['shift_date'];
                            $lastTime = $row['shift_time'];
                    ?>
                        <tr class="date-divider">
                            <td colspan="6">
                                📅 <?php echo date('l, F j, Y', strtotime($row['shift_date'])); ?>
                            </td>
                        </tr>
                    <?php 
                        elseif ($isNewTimeSlot):
                            $lastTime = $row['shift_time'];
                        endif; 

                        // Accumulate for subtotals
                        $subQty += $row['qty_sold'];
                        $subSales += $row['total_sales'];
                        $subDonations += $row['total_donations'];

                        // Only show time-divider if it's a new slot within the same day
                        $rowClass = ($isNewTimeSlot && !$isNewDate) ? 'class="time-divider"' : '';
                    ?>
                        <tr <?php echo $rowClass; ?>>
                            <td data-label="Date/Time">
                                <strong><?php echo htmlspecialchars($row['shift_time']); ?></strong>
                            </td>
                            <td data-label="Product"><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td data-label="Qty" class="right-align"><?php echo $row['qty_sold']; ?></td>
                            <td data-label="Sales" class="right-align">$<?php echo number_format($row['total_sales'], 2); ?></td>
                            <td data-label="Donations" class="right-align">$<?php echo number_format($row['total_donations'], 2); ?></td>
                            <td data-label="Action" style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn-edit" 
                                        style="border:none; background:none; cursor:pointer;"
                                        onclick="editSale(this)"
                                        data-date="<?php echo date('Y-m-d', strtotime($row['shift_date'])); ?>"
                                        data-time="<?php echo htmlspecialchars($row['shift_time']); ?>"
                                        data-product="<?php echo htmlspecialchars($row['product_name']); ?>"
                                        data-qty="<?php echo $row['qty_sold']; ?>"
                                        data-sales="<?php echo $row['total_sales']; ?>"
                                        data-donations="<?php echo $row['total_donations']; ?>"
                                        data-comments="<?php echo htmlspecialchars($row['comments']); ?>">
                                    Edit
                                </button>

                                <form method="POST" onsubmit="return confirm('Delete this record?');">
                                    <input type="hidden" name="shift_date" value="<?php echo $row['shift_date']; ?>">
                                    <input type="hidden" name="shift_time" value="<?php echo htmlspecialchars($row['shift_time']); ?>">
                                    <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($row['product_name']); ?>">
                                    <button type="submit" name="delete_sale" class="btn-remove" style="border:none; background:none; cursor:pointer;">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php 
                        // End of loop: print the final group subtotal
                        if ($index === $count - 1):
                    ?>
                        <tr class="subtotal-row">
                            <td data-label="Summary" colspan="2" style="text-align: right;">Time Slot Subtotal:</td>
                            <td data-label="Subtotal Qty" class="right-align"><?php echo $subQty; ?></td>
                            <td data-label="Subtotal Sales" class="right-align">$<?php echo number_format($subSales, 2); ?></td>
                            <td data-label="Subtotal Donations" class="right-align">$<?php echo number_format($subDonations, 2); ?></td>
                            <td data-label="Total Earned" class="right-align" style="color: var(--primary-color);">
                                $<?php echo number_format($subSales + $subDonations, 2); ?>
                            </td>
                        </tr>
                    <?php
                        endif;
                    endforeach; ?>
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

    function calculateTotal() {
        const form = document.getElementById('edit_form');
        const productSelect = form.product_name;
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        const qty = parseInt(form.qty_sold.value) || 0;
        
        form.total_sales.value = (price * qty).toFixed(2);
    }

    function editSale(btn) {
        const data = btn.dataset;
        document.getElementById('form_title').innerText = "Update Shift Sale";
        document.getElementById('submit_btn').innerText = "Save Changes";
        document.getElementById('submit_btn').name = "update_sale";
        document.getElementById('cancel_btn').style.display = "inline-block";
        
        const form = document.getElementById('edit_form');
        form.shift_date.value = data.date;
        form.shift_time.value = data.time;
        form.product_name.value = data.product;
        form.qty_sold.value = data.qty;
        form.total_sales.value = data.sales;
        form.total_donations.value = data.donations;
        form.comments.value = data.comments;

        form.orig_shift_date.value = data.date;
        form.orig_shift_time.value = data.time;
        form.orig_product_name.value = data.product;

        window.scrollTo({ top: 0, behavior: 'smooth' });
        form.shift_date.focus();
        calculateTotal();
    }

    function cancelEdit() {
        document.getElementById('edit_form').reset();
        document.getElementById('form_title').innerText = "Record New Shift Sale";
        document.getElementById('submit_btn').innerText = "Add Sale Record";
        document.getElementById('submit_btn').name = "add_sale";
        document.getElementById('cancel_btn').style.display = "none";
    }
</script>
</body>
</html>