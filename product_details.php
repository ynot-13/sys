<?php

$page_title = "Product Details"; 
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';


if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
    error_log("Product Details Error: Database connection failed. Error: " . ($mysqli ? $mysqli->connect_error : 'mysqli object not available'));
    set_message("A critical database error occurred. Please try again later.", "error");
   
    if (file_exists('includes/header.php')) {
        require_once 'includes/header.php';
        echo "<div class='container main-content'>"; display_message(); echo "</div>";
        require_once 'includes/footer.php';
    } else {
        die("Database connection error. Please try again later.");
    }
    exit;
}


$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id || $product_id <= 0) {
    set_message("Invalid Product ID specified.", "error");
    redirect("products.php");
}

$product = null;
$sql_product = "SELECT p.*, c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                WHERE p.product_id = ? AND p.is_active = 1"; 
$stmt_product = $mysqli->prepare($sql_product);

if ($stmt_product) {
    $stmt_product->bind_param("i", $product_id);
    if ($stmt_product->execute()) {
        $result_product = $stmt_product->get_result();
        if ($result_product->num_rows === 1) {
            $product = $result_product->fetch_assoc();
            $page_title = htmlspecialchars($product['name']); 
        } else {
            set_message("Product not found or is currently unavailable.", "error");
            redirect("products.php");
        }
        if(isset($result_product)) $result_product->free();
    } else {
        set_message("Error fetching product data.", "error");
        error_log("Product detail fetch EXECUTE error (ID: $product_id): " . $stmt_product->error);
        redirect("products.php");
    }
    $stmt_product->close();
} else {
    set_message("Error preparing product data query.", "error");
    error_log("Product detail fetch PREPARE error: " . $mysqli->error . " SQL: " . $sql_product);
    redirect("products.php");
}


$related_products = [];
if ($product && isset($product['category_id'])) { 
    $sql_related = "SELECT product_id, name, price, image_url FROM products
                    WHERE category_id = ? AND product_id != ? AND is_active = 1
                    ORDER BY RAND() LIMIT 4";
    $stmt_related = $mysqli->prepare($sql_related);
    if ($stmt_related) {
        $stmt_related->bind_param("ii", $product['category_id'], $product_id);
        if($stmt_related->execute()){
            $result_related = $stmt_related->get_result();
            while($row = $result_related->fetch_assoc()){ $related_products[] = $row; }
            if(isset($result_related)) $result_related->free();
        } else { error_log("Related products execute error: ".$stmt_related->error); }
        $stmt_related->close();
    } else { error_log("Related products prepare error: ".$mysqli->error); }
}


$reviews = [];
$sql_reviews = "SELECT r.feedback_id, r.rating, r.comment, r.submitted_at, u.username
                FROM feedback r
                JOIN users u ON r.user_id = u.user_id
                WHERE r.product_id = ? AND r.is_approved = 1
                ORDER BY r.submitted_at DESC";
$stmt_reviews = $mysqli->prepare($sql_reviews);

$debug_reviews_sql_error = null; 
if ($stmt_reviews) {
    $stmt_reviews->bind_param("i", $product_id);
    if($stmt_reviews->execute()){
        $result_reviews = $stmt_reviews->get_result();
        while($row = $result_reviews->fetch_assoc()){
            $reviews[] = $row;
        }
        if(isset($result_reviews)) $result_reviews->free();
    } else {
        error_log("Product reviews EXECUTE error (Product ID: $product_id): ".$stmt_reviews->error);
        $debug_reviews_sql_error = "Execute Error: " . $stmt_reviews->error;
        
    }
    $stmt_reviews->close();
} else {
    error_log("Product reviews PREPARE error: ".$mysqli->error . " SQL: " . $sql_reviews);
    $debug_reviews_sql_error = "Prepare Error: " . $mysqli->error;
    
}


require_once 'includes/header.php'; 
?>

<div class="product-details-page"> 

  
    <div class="product-gallery">
        <img src="<?php echo BASE_URL . htmlspecialchars($product['image_url'] ?: 'img/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
    </div>

 
    <div class="product-info-main">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                <?php if (isset($product['category_name'])): ?>
                    <li class="breadcrumb-item"><a href="products.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>

        <h1><?php echo htmlspecialchars($product['name']); ?></h1>

    
        <div class="product-rating">
            <?php
            $average_rating = 0;
            $rated_reviews_count = 0;
            if (!empty($reviews)) {
                $total_rating_sum = 0;
                foreach ($reviews as $r) {
                    if (isset($r['rating']) && is_numeric($r['rating']) && $r['rating'] > 0) { 
                        $total_rating_sum += (int)$r['rating'];
                        $rated_reviews_count++;
                    }
                }
                if ($rated_reviews_count > 0) {
                    $average_rating = round($total_rating_sum / $rated_reviews_count, 1);
                }
            }

            if ($average_rating > 0) {
                for ($i = 1; $i <= 5; $i++) {
                    echo '<i class="fas fa-star" style="color: ' . ($i <= floor($average_rating) ? 'var(--warning-color)' : '#e0e0e0') . ';"></i>';
                }
                echo '<span class="rating-text">' . $average_rating . ' (' . count($reviews) . ' review' . (count($reviews) > 1 ? 's' : '') . ')</span>';
            } elseif (count($reviews) > 0) {
                 echo '<span class="text-muted rating-text">(' . count($reviews) . ' comment' . (count($reviews) > 1 ? 's' : '') . ' - No star ratings yet)</span>';
            } else { 
                 echo '<span class="text-muted rating-text">No reviews yet.</span>';
            }
            ?>
        </div>

        <p class="product-price"><?php echo formatCurrency($product['price']); ?></p>

        <p class="product-stock">
            <?php if ($product['stock'] > 10): ?> <span class="text-success"><i class="fas fa-check-circle"></i> In Stock</span>
            <?php elseif ($product['stock'] > 0): ?> <span class="text-warning"><i class="fas fa-exclamation-circle"></i> Low Stock (<?php echo $product['stock']; ?> left)</span>
            <?php else: ?> <span class="text-danger"><i class="fas fa-times-circle"></i> Out of Stock</span>
            <?php endif; ?>
        </p>

        <form action="add_to_cart.php" method="post" class="add-to-cart-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
            <div class="form-group quantity-control">
                <label for="quantity">Quantity:</label>
                <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?php echo $product['stock'] > 0 ? $product['stock'] : 1; ?>" class="form-control" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                <i class="fas fa-cart-plus"></i> Add to Cart
            </button>
        </form>

        <div class="social-share">
            <strong>Share:</strong>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(BASE_URL . 'product_details.php?id=' . $product_id); ?>" target="_blank" class="btn btn-sm btn-social facebook" title="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(BASE_URL . 'product_details.php?id=' . $product_id); ?>&text=<?php echo urlencode("Check out " . $product['name']); ?>" target="_blank" class="btn btn-sm btn-social twitter" title="Share on Twitter"><i class="fab fa-twitter"></i></a>
        </div>
    </div>

    <!-- Product Description & Reviews Tabs -->
    <div class="product-tabs">
        <ul class="nav nav-tabs" id="productTab" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab" aria-controls="description" aria-selected="true">Description</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" aria-controls="reviews" aria-selected="false">Reviews (<?php echo count($reviews); ?>)</button></li>
        </ul>
        <div class="tab-content" id="productTabContent">
            <div class="tab-pane fade show active" id="description" role="tabpanel" aria-labelledby="description-tab">
                <h4>Product Description</h4>
                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
            <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                <h4>Customer Reviews</h4>

            
                <?php if (defined('DEBUG_MODE') && DEBUG_MODE === true): ?>
                    <pre style="background:#f0f0f0; border:1px solid #ccc; padding:10px; margin:15px 0; font-size:0.9em; white-space: pre-wrap; word-break: break-all;">
DEBUG INFO (product_details.php - Reviews):
Product ID for Reviews Query: <?php echo htmlspecialchars($product_id); ?>

SQL Error (if any): <?php echo htmlspecialchars($debug_reviews_sql_error ?: 'None'); ?>

Number of Approved Reviews Fetched: <?php echo count($reviews); ?>

<?php if (!empty($reviews)): ?>
First Review Data:
<?php print_r($reviews[0]); ?>
<?php else: ?>
The \$reviews array is EMPTY after fetching.
Possible reasons:
1. No feedback rows in 'feedback' table match product_id=<?php echo htmlspecialchars($product_id); ?> AND is_approved=1.
2. The feedback rows exist, but their 'rating' column is NULL (AVG(NULL) is NULL).
3. Database query to fetch reviews failed (check server error log for details if $debug_reviews_sql_error shows an error).
<?php endif; ?>
                    </pre>
                <?php endif; ?>
               


                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review">
                             <div class="review-rating">
                                <?php if (isset($review['rating']) && $review['rating'] > 0): ?>
                                    <?php for($i=1; $i<=5; $i++): ?>
                                         <i class="fas fa-star" style="color: <?php echo ($i <= (int)$review['rating']) ? 'var(--warning-color)' : '#e0e0e0'; ?>;"></i>
                                     <?php endfor; ?>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.9em;">(No star rating)</span>
                                <?php endif; ?>
                            </div>
                            <p class="review-author">
                                <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                                <small class="text-muted"> - <?php echo date("M d, Y", strtotime($review['submitted_at'])); ?></small>
                            </p>
                            <p class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">There are no approved reviews for this product yet.</p>
                <?php endif; ?>

                 <div class="write-review-link">
                     <?php if (isLoggedIn()): ?>
                         <a href="feedback.php?product_id=<?php echo $product_id; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-pencil-alt"></i> Write a Review</a>
                     <?php else: ?>
                         <p><a href="login.php?redirect=<?php echo urlencode('product_details.php?id='.$product_id);?>">Log in</a> to write a review.</p>
                     <?php endif; ?>
                 </div>
            </div>
        </div>
    </div>

    <?php if (!empty($related_products)): ?>
    <div class="related-products">
        <h3>You Might Also Like</h3>
        <div class="product-grid">
             <?php foreach ($related_products as $related_product): ?>
                <div class="product-card">
                    <div class="product-image">
                         <a href="product_details.php?id=<?php echo $related_product['product_id']; ?>">
                            <img src="<?php echo BASE_URL . htmlspecialchars($related_product['image_url'] ?: 'img/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($related_product['name']); ?>">
                         </a>
                    </div>
                    <div class="product-info">
                        <h4><a href="product_details.php?id=<?php echo $related_product['product_id']; ?>"><?php echo htmlspecialchars($related_product['name']); ?></a></h4>
                        <p class="product-price"><?php echo formatCurrency($related_product['price']); ?></p>
                        <div class="product-actions">
                             <a href="product_details.php?id=<?php echo $related_product['product_id']; ?>" class="btn btn-sm btn-secondary">View</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>


<script>  </script>

<?php require_once 'includes/footer.php'; ?>