<?php
require_once 'db.php'; // Includes session_start and PDO connection

// Get the saved details if they exist
$saved = $_SESSION['checkout_details'] ?? [];
$selectedPayment = $saved['payment'] ?? '';

// 1. Handle "Remove" Action
if (isset($_GET['remove'])) {
    $id_to_remove = $_GET['remove'];
    unset($_SESSION['cart'][$id_to_remove]);
    header("Location: checkout.php");
    exit;
}

// 2. Handle "Update Quantity" Action
if (isset($_POST['update_qty'])) {
    $id = $_POST['product_id'];
    $new_qty = (int)$_POST['quantity'];
    if ($new_qty >= 1 && $new_qty <= 9) {
        $_SESSION['cart'][$id] = $new_qty;
    } elseif ($new_qty <= 0) {
        unset($_SESSION['cart'][$id]);
    }
    header("Location: checkout.php");
    exit;
}

$grand_total = 0;
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Plant Sales'; include 'header-html.php'; ?>

<div class="main-container">    
    <h2 class="headings">Your Cart</h2>


    <?php if (empty($_SESSION['cart'])): ?>
        <p>Your cart is empty.</p><br/>
        <a href="index.php" class="btn btn-back">Back Home</a>
        <br/><br/>
    <?php else: ?>
        <?php $editable = true; include 'cart_items.php'; ?>
        <form action="confirmation.php" method="POST">
            <h2 class="headings">Checkout Information</h2>
            <div class="form-group">
                <label>How will you pay?</label>
                <select name="payment" required>
                    <option value="Venmo" <?php echo ($selectedPayment === 'Venmo') ? 'selected' : ''; ?>>Venmo</option>
                    <option value="Cash" <?php echo ($selectedPayment === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="Check" <?php echo ($selectedPayment === 'Check') ? 'selected' : ''; ?>>Check</option>
                </select>
            </div>
            <div class="form-group">
                <label>Your Name</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($saved['name'] ?? ''); ?>" placeholder="" >
            </div>
            <div class="form-group">
                <label>Delivery Address</label>
                <input type="text" name="address" required value="<?php echo htmlspecialchars($saved['address'] ?? ''); ?>" placeholder="">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($saved['email'] ?? ''); ?>" placeholder="">
            </div>
            <div class="form-group">
                <label>Scout's Name</label>
                <input type="text" name="scout_name" required value="<?php echo htmlspecialchars($saved['scout_name'] ?? ''); ?>" placeholder="">
            </div>
            <div class="form-group">
                <label>Comments (Optional)</label>
                <input type="text" name="comments" id="comments" value="<?php echo htmlspecialchars($saved['comments'] ?? ''); ?>" placeholder="">
            </div>            
            <div class="btn-group">
                <a href="index.php" class="btn btn-back">Back Home</a>
                <button type="submit" class="btn btn-confirm">Review Order</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
let itemToDelete = null;

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('ajax-remove')) {
        e.preventDefault();
        itemToDelete = e.target.dataset.id;
        document.getElementById('custom-modal').style.display = 'block';
    }

    if (e.target.id === 'modal-cancel') {
        document.getElementById('custom-modal').style.display = 'none';
        itemToDelete = null;
    }

    if (e.target.id === 'modal-confirm') {
        if (itemToDelete) {
            updateCart(itemToDelete, 0, 'remove');
            document.getElementById('custom-modal').style.display = 'none';
        }
    }
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('ajax-qty')) {
        updateCart(e.target.dataset.id, e.target.value, 'update');
    }
});

function updateCart(pId, qty, action) {

    if (qty <= 0 && action === 'update') {
        action = 'remove';
    }
    
    // If user typed something huge, cap it at 9 so it doesn't break PHP logic
    if (qty > 9) {
        qty = 9;
        document.querySelector(`.ajax-qty[data-id="${pId}"]`).value = 9;
    }

    const formData = new FormData();
    formData.append('product_id', pId);
    formData.append('quantity', qty);
    formData.append('action', action);

    fetch(`update_cart_ajax.php`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            // 1. Handle Removal
            if (action === 'remove' || qty <= 0) {
                const row = document.getElementById(`row-${pId}`);
                if (row) row.remove();
            } else {
                // 2. Handle Subtotal Update (This was the missing piece!)
                const subtotalDisplay = document.getElementById(`subtotal-${pId}`);
                if (subtotalDisplay) {
                    subtotalDisplay.innerText = '$' + data.new_subtotal;
                }
            }
            
            // 3. Update Grand Total
            const totalDisplay = document.getElementById('grand-total-display');
            if (totalDisplay) {
                totalDisplay.innerText = data.grand_total;
            }

            if (data.cart_empty) {
                location.reload(); 
            }
        }
    })
    .catch(err => console.error('Fetch error:', err));
}
</script>

<?php 
$modal_message = "Remove this item from your cart?";
$modal_confirm_btn = "Remove";
$modal_cancel_btn = "Cancel";
include 'modal.php'; 
?>
</body>
</html>