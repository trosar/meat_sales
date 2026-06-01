<?php 
require_once 'db.php'; 

// Handle Add to Cart Logic
if (isset($_POST['add_to_cart'])) {
    $p_id = $_POST['product_id'];
    $qty = (int)$_POST['quantity'];
    if ($qty >= 1 && $qty <= 99) {
        $_SESSION['cart'][$p_id] = ($_SESSION['cart'][$p_id] ?? 0) + $qty;
    }
    header("Location: index.php");
    exit;
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$products = $pdo->query("SELECT * FROM {$tab_prefix}_products order by price desc")->fetchAll();
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Meat Sticks & Chocolate Fundraiser'; include 'header-html.php'; ?>

<div class="main-container">

    <div class="fundraiser-info">
        <p>
            We are selling Meat Sticks & Gourmet Chocolate <br/>to raise money to enable us to participate in adventures during the year, including summer camp.
            <br/>Thanks for your support!
            <br/>

        </p>
        <!-- <h4>Sponsor:
            <a href="https://www.stadiumflowers.com/" target="_blank"><img class="sponsor-logo" src="media/Stadium_Flowers_Logo.png" alt="Stadium Flowers Logo"></a>
        </h4> -->
        <h3>Delivery will be made every weekend in June and July, 2026.</h3>

    </div>

<?php
echo "<!-- STORE_IS_OPEN: " . ($store_is_open ? 'true' : 'false') . " -->";
if ($store_is_open) {
?>
    
    <div class="cart-header">
        <a href="checkout.php" class="cart-badge" id="cart-anchor">
            View Cart (<span id="cart-qty"><?php echo $cart_count; ?></span>)
        </a>
    </div>
    <div class="grid">
        <?php foreach ($products as $p): ?>
        <div class="product-card">
            <img src="images/<?php echo htmlspecialchars($p['image_url']); ?>" alt="product">
            <h3><?php echo htmlspecialchars($p['name']); ?></h3>
            <span class="price">$<?php echo number_format($p['price'], 2); ?></span>
            
            <form class="ajax-form">
                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                <input type="number" name="quantity" value="1" min="1" max="99" class="qty-input">
                <button type="submit" class="btn btn-primary">Add to Cart</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
<?php
}
?>

    <div class="footer-nav">
        <div class="footer-links">
            <small>
                <span id="thanks">Thanks for your support!</span><br/>    
                <a href="view_order.php">Look Up Your Orders</a>
                |
                <a href="admin.php">Admin Login</a>
            </small>
        </div>    

        <!-- <h3>Products are Sponsored By: 
            <a href="https://www.stadiumflowers.com/" target="_blank">
                <img class="sponsor-logo" src="media/Stadium_Flowers_Logo.png" alt="Stadium Flowers Logo">
            </a>
        </h3> -->
    </div>
    <div id="credit-popup" class="credit-popup" style="display:none;">
        Built with ❤️ by Alan Rosario
    </div>

</div>
<script>
document.querySelectorAll('.ajax-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = this.querySelector('button');
        const cart = document.querySelector('#cart-anchor');
        const formData = new FormData(this);

        // 1. Send the data to the server
        fetch('add_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(cartCount => {
            // 2. Create the "Flying" element
            const flyer = document.createElement('div');
            flyer.innerText = ' Add to Cart '; // You can change this to any icon
            flyer.style.cssText = `
                position: fixed;
                z-index: 9999;
                left: ${btn.getBoundingClientRect().left}px;
                top: ${btn.getBoundingClientRect().top}px;
                transition: all 0.8s cubic-bezier(0.42, 0, 0.58, 1);
                font-size: 24px;
                pointer-events: none;
            `;
            document.body.appendChild(flyer);

            // 3. Trigger the animation to the cart badge position
            setTimeout(() => {
                flyer.style.left = `${cart.getBoundingClientRect().left}px`;
                flyer.style.top = `${cart.getBoundingClientRect().top}px`;
                flyer.style.opacity = '0';
                flyer.style.transform = 'scale(0.5)';
            }, 10);

            // 4. Update the number and clean up
            setTimeout(() => {
                document.getElementById('cart-qty').innerText = cartCount.trim();
                flyer.remove();
                
                // A little "pop" effect on the cart
                cart.style.transform = 'scale(1.2)';
                setTimeout(() => { cart.style.transform = 'scale(1)'; }, 200);
            }, 800);
        });
    });
});

let tapCount = 0;
document.getElementById('thanks').addEventListener('click', function() {
    tapCount++;
    if (tapCount === 3) { // Shows up after 3 fast clicks
        const popup = document.getElementById('credit-popup');
        popup.style.display = 'block';
        setTimeout(() => { popup.style.display = 'none'; tapCount = 0; }, 3000);
    }
});
</script>
</body>
</html>