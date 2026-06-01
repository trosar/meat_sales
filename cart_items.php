<?php
// IMPORTANT: Set $editable BEFORE including this file
// Usage: < ?php $editable = true; include 'cart_items.php'; ? >

$editable = $editable ?? false; // Default to false if not set
?>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th class="right-align">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($_SESSION['cart'] as $id => $qty): 
                    $stmt = $pdo->prepare("SELECT name, price FROM {$tab_prefix}_products WHERE id = ?");
                    $stmt->execute([$id]);
                    $product = $stmt->fetch();
                    if ($product):
                        $subtotal = $product['price'] * $qty;
                        $grand_total += $subtotal;
                ?>
                <tr id="row-<?php echo $id; ?>">
                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong><br><small>$<?php echo number_format($product['price'], 2); ?> ea</small></td>
                    <td>
                        <div class="qty-controls">
                            <?php if ($editable): ?>
                                <nobr>
                                    <input type="number" value="<?php echo $qty; ?>" min="1" max="99" 
                                        class="qty-input ajax-qty" data-id="<?php echo $id; ?>">
                                    <a href="#" class="btn-remove ajax-remove" data-id="<?php echo $id; ?>">❌</a>
                                </nobr>
                            <?php else: ?>
                                <span><?php echo $qty; ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="right-align" id="subtotal-<?php echo $id; ?>">$<?php echo number_format($subtotal, 2); ?></td>
                </tr>
                <?php endif; endforeach; ?>
            </tbody>
        </table>

        <div class="total-row">Grand Total: $<span id="grand-total-display"><?php echo number_format($grand_total, 2); ?></span></div>
