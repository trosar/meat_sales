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

    if (isset($_POST['save_venmo'])) {
        $stmt = $pdo->prepare("DELETE FROM {$tab_prefix}_shift_venmo_only WHERE shift_date = ? AND shift_time = ?");
        $stmt->execute([$_POST['shift_date'], $_POST['shift_time']]);

        $stmt = $pdo->prepare("INSERT INTO {$tab_prefix}_shift_venmo_only (shift_date, shift_time, venmo_total, comments) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['shift_date'], $_POST['shift_time'], $_POST['venmo_total'], $_POST['comments']]);
        $_SESSION['success_msg'] = "Venmo amount saved for " . $_POST['shift_time'];
    }

    header("Location: shift_sales.php");
    exit;
}

$sales = $pdo->query("SELECT * FROM {$tab_prefix}_shift_sales ORDER BY shift_date ASC, shift_time ASC, product_name DESC")->fetchAll();

$finalGrandVenmo = 0;
$finalGrandCash = 0;
$finalGrandTotal = 0;

$venmo_raw = $pdo->query("SELECT * FROM {$tab_prefix}_shift_venmo_only")->fetchAll();
$venmo_map = [];
foreach ($venmo_raw as $v) {
    $d = date('Y-m-d', strtotime($v['shift_date']));
    $venmo_map[$d . '|' . $v['shift_time']] = $v;
}

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
                        <th class="right-align">Total</th>
                        <th class="right-align">Venmo</th>
                        <th class="right-align">Cash</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr><td colspan="9" style="text-align:center; padding: 20px; color: #888;">No shift sales records found.</td></tr>
                    <?php endif; ?>

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
                            $vKey = date('Y-m-d', strtotime($lastDate)) . '|' . $lastTime;
                            $vRec = $venmo_map[$vKey] ?? null;
                            $vTotal = $vRec['venmo_total'] ?? 0;
                            $grandSub = $subSales + $subDonations;
                            $cTotal = $grandSub - $vTotal;

                            $finalGrandVenmo += $vTotal;
                            $finalGrandCash += $cTotal;
                            $finalGrandTotal += $grandSub;
                    ?>
                        <tr class="subtotal-row">
                            <td data-label="Summary" colspan="2" style="text-align: right;">Time Slot Subtotal:</td>
                            <td data-label="Subtotal Qty" class="right-align"><?php echo $subQty; ?></td>
                            <td data-label="Subtotal Sales" class="right-align">$<?php echo number_format($subSales, 2); ?></td>
                            <td data-label="Subtotal Donations" class="right-align">$<?php echo number_format($subDonations, 2); ?></td>
                            <td data-label="Subtotal Total" class="right-align" style="color: var(--primary-color); font-weight: bold;">$<?php echo number_format($grandSub, 2); ?></td>
                            <td data-label="Subtotal Venmo" class="right-align">$<?php echo number_format($vTotal, 2); ?></td>
                            <td data-label="Subtotal Cash" class="right-align">$<?php echo number_format($cTotal, 2); ?></td>
                            <td data-label="Action">
                                <button type="button" class="btn-edit" 
                                        style="font-size: 0.75rem; border:none; background:none; cursor:pointer;"
                                        onclick="openVenmoModal('<?php echo $lastDate; ?>', '<?php echo htmlspecialchars($lastTime); ?>', '<?php echo $vTotal; ?>', '<?php echo htmlspecialchars($vRec['comments'] ?? ''); ?>', '<?php echo date('l, F j, Y', strtotime($lastDate)); ?>')">
                                        Breakdown
                                </button>
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
                            <td colspan="9">
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
                            <td data-label="Total" class="right-align">$<?php echo number_format($row['total_sales'] + $row['total_donations'], 2); ?></td>
                            <td class="right-align text-muted">—</td>
                            <td class="right-align text-muted">—</td>
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

                                <form method="POST" onsubmit="return confirm('Delete this record?');" style="display: inline;">
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
                            $vKey = date('Y-m-d', strtotime($lastDate)) . '|' . $lastTime;
                            $vRec = $venmo_map[$vKey] ?? null;
                            $vTotal = $vRec['venmo_total'] ?? 0;
                            $grandSub = $subSales + $subDonations;
                            $cTotal = $grandSub - $vTotal;

                            $finalGrandVenmo += $vTotal;
                            $finalGrandCash += $cTotal;
                            $finalGrandTotal += $grandSub;
                    ?>
                        <tr class="subtotal-row">
                            <td data-label="Summary" colspan="2" style="text-align: right;">Time Slot Subtotal:</td>
                            <td data-label="Subtotal Qty" class="right-align"><?php echo $subQty; ?></td>
                            <td data-label="Subtotal Sales" class="right-align">$<?php echo number_format($subSales, 2); ?></td>
                            <td data-label="Subtotal Donations" class="right-align">$<?php echo number_format($subDonations, 2); ?></td>
                            <td data-label="Subtotal Total" class="right-align" style="color: var(--primary-color); font-weight: bold;">$<?php echo number_format($grandSub, 2); ?></td>
                            <td data-label="Subtotal Venmo" class="right-align">$<?php echo number_format($vTotal, 2); ?></td>
                            <td data-label="Subtotal Cash" class="right-align">$<?php echo number_format($cTotal, 2); ?></td>
                            <td data-label="Action">
                                <button type="button" class="btn-edit" 
                                        style="font-size: 0.75rem; border:none; background:none; cursor:pointer;"
                                        onclick="openVenmoModal('<?php echo $lastDate; ?>', '<?php echo htmlspecialchars($lastTime); ?>', '<?php echo $vTotal; ?>', '<?php echo htmlspecialchars($vRec['comments'] ?? ''); ?>', '<?php echo date('l, F j, Y', strtotime($lastDate)); ?>')">
                                    Breakdown
                                </button>
                            </td>
                        </tr>
                    <?php
                        endif;
                    endforeach; 
                    
                    if (!empty($sales)): ?>
                    <tr style="background: #eee; font-weight: bold; border-top: 2px solid #333;">
                        <td colspan="5" data-label="Summary" style="text-align: right; padding: 15px;">GRAND TOTALS:</td>
                        <td data-label="Grand Total" class="right-align" style="color: var(--primary-color);">$<?php echo number_format($finalGrandTotal, 2); ?></td>
                        <td data-label="Grand Venmo" class="right-align">$<?php echo number_format($finalGrandVenmo, 2); ?></td>
                        <td data-label="Grand Cash" class="right-align">$<?php echo number_format($finalGrandCash, 2); ?></td>
                        <td data-label="Action"></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Venmo Breakdown Modal -->
<div id="venmo_modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="width: 320px;">
        <h3 id="venmo_modal_title">Venmo Amount</h3>
        <form method="POST">
            <input type="hidden" name="shift_date" id="venmo_date">
            <input type="hidden" name="shift_time" id="venmo_time">
            <div class="form-group" style="text-align: left;">
                <label>Date & Time Slot</label>
                <div id="venmo_display_label" style="font-size: 0.9rem; margin-bottom: 10px; color: #666;"></div>
            </div>
            <div class="form-group" style="text-align: left;">
                <label>Venmo Total ($)</label>
                <input type="number" step="0.01" name="venmo_total" id="venmo_total_input" required>
            </div>
            <div class="form-group" style="text-align: left;">
                <label>Comments</label>
                <input type="text" name="comments" id="venmo_comments_input">
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" name="save_venmo" class="btn btn-confirm">Save</button>
                <button type="button" onclick="document.getElementById('venmo_modal').style.display='none'" class="btn btn-back">Close</button>
            </div>
        </form>
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

    function openVenmoModal(date, time, venmo, comments, formattedDate) {
        document.getElementById('venmo_date').value = date;
        document.getElementById('venmo_time').value = time;
        document.getElementById('venmo_display_label').innerText = formattedDate + ' (' + time + ')';
        document.getElementById('venmo_total_input').value = venmo;
        document.getElementById('venmo_comments_input').value = comments;
        document.getElementById('venmo_modal').style.display = 'block';
    }
</script>
</body>
</html>