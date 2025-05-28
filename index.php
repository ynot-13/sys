<?php
$page_title = "Welcome";
require_once 'includes/header.php'; 
?>

<section class="hero-section">
    <img src="<?php echo BASE_URL; ?>img/med.png" alt="Wellness Products Banner" class="hero-background-image">
    <div class="hero-overlay-content">
        <h1>Discover Your Path to Wellness</h1>
        <p>Shop high-quality health products, supplements, and fitness gear at <?php echo SITE_NAME; ?>.</p>
        <a href="products.php" class="btn btn-lg">Shop Now <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="hero-overlay-background"></div>
</section>


<section class="flash-sale-section homepage-section">
    <div class="container">
        <h2 class="homepage-section-title"> Flash Sale! Limited Time Offers </h2>
        <div class="product-grid">
            <?php
            $sql_flash_sale = "SELECT p.product_id, p.name, p.price, p.image_url, p.image_alt, 
                                      p.flash_sale_price, p.flash_sale_end_date
                               FROM products p
                               WHERE p.is_active = 1 
                                 AND p.is_on_flash_sale = 1
                                 AND p.stock > 0
                                 AND (p.flash_sale_start_date IS NULL OR p.flash_sale_start_date <= NOW())
                                 AND (p.flash_sale_end_date IS NULL OR p.flash_sale_end_date >= NOW())
                               ORDER BY p.flash_sale_end_date ASC, p.created_at DESC 
                               LIMIT 10";

            $result_flash_sale = $mysqli->query($sql_flash_sale);
            $has_flash_sale_items = false; 

            if ($result_flash_sale && $result_flash_sale->num_rows > 0) {
                $has_flash_sale_items = true; 
                while ($product = $result_flash_sale->fetch_assoc()) {
                    $image_path = $product['image_url'] ?? 'img/placeholder.png';
                    $image_alt = !empty($product['image_alt']) ? $product['image_alt'] : $product['name'];
                    $current_price = $product['flash_sale_price'];
                    $original_price = $product['price'];
            ?>
                    <div class="product-card flash-sale-item">
                        <div class="product-image">
                            <a href="product_details.php?id=<?php echo $product['product_id']; ?>">
                                <img src="<?php echo BASE_URL . htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($image_alt); ?>">
                                <span class="sale-badge">SALE!</span>
                            </a>
                        </div>
                        <div class="product-info">
                            <h3><a href="product_details.php?id=<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a></h3>
                            <p class="product-price">
                                <span class="flash-sale-price"><?php echo function_exists('formatCurrency') ? formatCurrency($current_price) : (defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '$') . number_format($current_price, 2); ?></span>
                                <del class="original-price"><?php echo function_exists('formatCurrency') ? formatCurrency($original_price) : (defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '$') . number_format($original_price, 2); ?></del>
                            </p>
                            
                            <?php ?>
                            <?php if ($product['flash_sale_end_date']): ?>
                                <p class="flash-sale-ends" style="font-size: 0.8em; color: #555; margin-bottom: 2px;">
                                    Ends: <?php echo date("M j, Y g:i A", strtotime($product['flash_sale_end_date'])); ?>
                                </p>
                                <div class="flash-sale-countdown" 
                                     data-end-time="<?php echo htmlspecialchars(date("Y-m-d\TH:i:s", strtotime($product['flash_sale_end_date']))); ?>"
                                     id="countdown-<?php echo $product['product_id']; ?>"
                                     style="font-size: 0.9em; color: #e74c3c; font-weight:bold; margin-bottom: 8px;">
                                    Time remaining: <span class="time-left">Calculating...</span>
                                </div>
                            <?php else: ?>
                                <p class="flash-sale-ends" style="font-size: 0.8em; color: #555; margin-bottom: 8px;">Special offer!</p>
                            <?php endif; ?>
                            <?php  ?>

                            <div class="product-actions">
                                <form action="buy_now_process.php" method="post" style="display: inline-block; margin-right: 5px;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-bolt"></i> Buy Now</button>
                                </form>
                                <form action="add_to_cart.php" method="post" class="add-to-cart-form" style="display: inline-block;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <button type="submit" class="btn btn-sm">Add to Cart <i class="fas fa-cart-plus"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
            <?php
                }
            }
            
            if (!$has_flash_sale_items) { 
                echo "<p style='text-align: center; grid-column: 1 / -1;'>No active flash sales at the moment. Check back soon!</p>";
            }
            ?>
        </div>
         <?php 
         if ($has_flash_sale_items): 
         ?>
       
        <?php 
        endif; 
        
        if (isset($result_flash_sale) && $result_flash_sale instanceof mysqli_result) {
            $result_flash_sale->free();
        }
        ?>
    </div>
</section>


<section class="featured-categories homepage-section">
    <div class="container">
        <h2 class="homepage-section-title">Shop by Category</h2>
        <div class="category-grid">
            <?php
            $sql_cat = "SELECT category_id, name, image_url FROM categories ORDER BY name ASC LIMIT 5";
            $result_cat = $mysqli->query($sql_cat);
            if ($result_cat && $result_cat->num_rows > 0) {
                while ($category = $result_cat->fetch_assoc()) {
                    echo '<div class="category-card">';
                    echo '<a href="products.php?category=' . htmlspecialchars($category['category_id']) . '">';
                    $image_path = $category['image_url'] ?? 'assets/images/placeholder_category.png';
                    $full_image_path = $_SERVER['DOCUMENT_ROOT'] . rtrim(parse_url(BASE_URL, PHP_URL_PATH), '/') . '/' . ltrim($image_path, '/');

                    if (!empty($category['image_url']) && file_exists($full_image_path)) {
                        echo '<img src="' . BASE_URL . htmlspecialchars($image_path) . '" alt="' . htmlspecialchars($category['name']) . '">';
                    } else {
                        echo '<i class="fas fa-tags category-icon-placeholder"></i>';
                    }
                    echo '<h3>' . htmlspecialchars($category['name']) . '</h3>';
                    echo '</a>';
                    echo '</div>';
                }
                if (isset($result_cat) && $result_cat instanceof mysqli_result) {
                    $result_cat->free();
                }
            } else {
                echo "<p style='text-align: center; grid-column: 1 / -1;'>No categories found.</p>";
                if (isset($result_cat) && $result_cat instanceof mysqli_result) {
                    $result_cat->free();
                }
            }
            ?>
            <div class="category-card all-categories-card">
                <a href="products.php">
                    <i class="fas fa-th-large"></i>
                    <h3>View All Categories</h3>
                </a>
            </div>
        </div>
    </div>
</section>

<section class="new-arrivals-section homepage-section">
    <div class="container">
        <h2 class="homepage-section-title">New Arrivals</h2>
        <div class="product-grid">
            <?php
            $sql_new = "SELECT p.product_id, p.name, p.price, p.image_url, p.image_alt
                        FROM products p
                        WHERE p.is_active = 1 AND p.stock > 0
                          AND NOT (
                            p.is_on_flash_sale = 1
                            AND (p.flash_sale_start_date IS NULL OR p.flash_sale_start_date <= NOW())
                            AND (p.flash_sale_end_date IS NULL OR p.flash_sale_end_date >= NOW())
                          )
                        ORDER BY p.created_at DESC LIMIT 4";
            $result_new = $mysqli->query($sql_new);
            if ($result_new && $result_new->num_rows > 0) {
                while ($product = $result_new->fetch_assoc()) {
                    $image_path = $product['image_url'] ?? 'assets/images/placeholder_product.png';
                    $image_alt = !empty($product['image_alt']) ? $product['image_alt'] : $product['name'];
            ?>
                    <div class="product-card">
                        <div class="product-image">
                            <a href="product_details.php?id=<?php echo $product['product_id']; ?>">
                                <img src="<?php echo BASE_URL . htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($image_alt); ?>">
                            </a>
                        </div>
                        <div class="product-info">
                            <h3><a href="product_details.php?id=<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a></h3>
                            <p class="product-price"><?php echo function_exists('formatCurrency') ? formatCurrency($product['price']) : (defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '$') . number_format($product['price'], 2); ?></p>
                            <div class="product-actions">
                                <form action="buy_now_process.php" method="post" style="display: inline-block; margin-right: 5px;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-bolt"></i> Buy Now</button>
                                </form>
                                <form action="add_to_cart.php" method="post" class="add-to-cart-form" style="display: inline-block;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <button type="submit" class="btn btn-sm">Add to Cart <i class="fas fa-cart-plus"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
            <?php
                }
                 if (isset($result_new) && $result_new instanceof mysqli_result) {
                    $result_new->free();
                }
            } else {
                echo "<p style='text-align: center; grid-column: 1 / -1;'>No new products found (that are not on sale).</p>";
                if (isset($result_new) && $result_new instanceof mysqli_result) {
                    $result_new->free();
                }
            }
            ?>
        </div>
        <div class="view-all-btn-container">
            <a href="products.php" class="btn">View All Products <i class="fas fa-angle-double-right"></i></a>
        </div>
    </div>
</section>

<section class="value-prop-section">
    <div class="container">
        <h2 class="homepage-section-title">Why Shop With Us?</h2>
        <div class="value-prop-grid">
            <div class="value-prop-item">
                <i class="fas fa-leaf"></i>
                <h3>Premium Quality</h3>
                <p>Carefully selected health and wellness products.</p>
            </div>
            <div class="value-prop-item">
                <i class="fas fa-shipping-fast"></i>
                <h3>Fast Shipping</h3>
                <p>Quick and reliable delivery to your doorstep.</p>
            </div>
            <div class="value-prop-item">
                <i class="fas fa-lock"></i>
                <h3>Secure Checkout</h3>
                <p>Your information is safe and protected.</p>
            </div>
            <div class="value-prop-item">
                <i class="fas fa-headset"></i>
                <h3>Expert Support</h3>
                <p>Friendly customer service ready to help.</p>
            </div>
        </div>
    </div>
</section>
<?php
require_once 'includes/footer.php';
?>