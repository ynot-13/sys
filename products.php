<?php
$page_title = "Products";
require_once 'includes/header.php';

$products_per_page = defined('PRODUCTS_PER_PAGE') ? PRODUCTS_PER_PAGE : 9;
$default_sort_option = 'name_asc';

$selected_category_id = isset($_GET['category']) ? filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) : null;
$search_term = isset($_GET['search']) ? sanitize_input(trim($_GET['search'])) : '';
$current_page = isset($_GET['page']) ? filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : 1;
if ($current_page === false || $current_page === null) $current_page = 1;

$sort_by = isset($_GET['sort_by']) ? sanitize_input(trim($_GET['sort_by'])) : $default_sort_option;

$allowed_sort_options = [
    'name_asc' => 'p.name ASC',
    'name_desc' => 'p.name DESC',
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'rating_desc' => 'average_rating IS NULL ASC, average_rating DESC',
    'newest' => 'p.created_at DESC'
];
if (!array_key_exists($sort_by, $allowed_sort_options)) {
    $sort_by = $default_sort_option;
}
$sql_order_by_clause = $allowed_sort_options[$sort_by];

$categories = [];
$sql_categories = "SELECT category_id, name FROM categories ORDER BY name ASC";
$result_categories = $mysqli->query($sql_categories);
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) { $categories[] = $row; }
    $result_categories->free();
}

$sql_base = " FROM products p ";
$sql_joins = " LEFT JOIN categories c ON p.category_id = c.category_id ";
$sql_where_conditions = "";
$params = [];
$types = '';
$where_clauses = [];

if ($selected_category_id) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = $selected_category_id;
    $types .= 'i';
}
if (!empty($search_term)) {
    $where_clauses[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'ss';
}

$where_clauses[] = "p.is_active = 1";

if (!empty($where_clauses)) {
    $sql_where_conditions = " WHERE " . implode(" AND ", $where_clauses);
}

$total_products = 0;
$sql_count = "SELECT COUNT(p.product_id)" . $sql_base . $sql_joins . $sql_where_conditions;
$stmt_count = $mysqli->prepare($sql_count);
if ($stmt_count) {
    if (!empty($params)) { $stmt_count->bind_param($types, ...$params); }
    if($stmt_count->execute()) { $stmt_count->bind_result($count_val); $stmt_count->fetch(); $total_products = $count_val; }
    else { error_log("Product Count Execute Error: ".$stmt_count->error); }
    $stmt_count->close();
} else { error_log("Product Count Prepare Error: ".$mysqli->error); }

$total_pages = $total_products > 0 ? ceil($total_products / $products_per_page) : 1;
if ($current_page > $total_pages) { $current_page = $total_pages; }
$offset = ($current_page - 1) * $products_per_page;

function build_link_query_string(array $custom_params, string $default_sort_val): string {
    global $selected_category_id, $search_term, $sort_by, $current_page;

    $query_args = [];

    $query_args['category'] = $custom_params['category'] ?? $selected_category_id;
    $query_args['search'] = $custom_params['search'] ?? $search_term;
    $query_args['sort_by'] = $custom_params['sort_by'] ?? $sort_by;
    $query_args['page'] = $custom_params['page'] ?? $current_page;

    if (empty($query_args['category'])) unset($query_args['category']);
    if (empty($query_args['search'])) unset($query_args['search']);
    if (empty($query_args['sort_by']) || $query_args['sort_by'] === $default_sort_val) unset($query_args['sort_by']);
    if (empty($query_args['page']) || $query_args['page'] <= 1) unset($query_args['page']);

    return !empty($query_args) ? http_build_query($query_args) : '';
}

?>

<section class="category-section">
    <h2>Product Categories</h2>
    <?php if (!empty($categories)): ?>
        <?php $total_categories = count($categories); ?>
        <div class="category-grid-container" style="display: flex; flex-direction: column; gap: 10px;">
            <?php
            $approx_cats_per_row = 5;
            $num_rows = ($total_categories > $approx_cats_per_row) ? ceil($total_categories / $approx_cats_per_row) : 1;
            if ($num_rows == 0 && $total_categories > 0) $num_rows = 1;
            $chunk_size = ($num_rows > 0) ? ceil($total_categories / $num_rows) : $total_categories;
            if ($chunk_size == 0 && $total_categories > 0) $chunk_size = $total_categories;

            $category_chunks = ($total_categories > 0 && $chunk_size > 0) ? array_chunk($categories, $chunk_size) : [$categories];
            if (empty($categories)) $category_chunks = [];
            ?>
            <?php foreach ($category_chunks as $chunk): ?>
            <div class="category-grid">
                <?php foreach ($chunk as $category_item): ?>
                 <div class="category-card <?php echo ($category_item['category_id'] == $selected_category_id) ? 'active' : ''; ?>">
                     <?php
                        $cat_link_query_params = ['category' => $category_item['category_id'], 'page' => 1];
                        $query_string = build_link_query_string($cat_link_query_params, $default_sort_option);
                     ?>
                     <a href="products.php<?php echo $query_string ? '?' . $query_string : ''; ?>">
                         <h3><?php echo htmlspecialchars($category_item['name']); ?></h3>
                     </a>
                 </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
         <?php if ($selected_category_id || !empty($search_term) || $sort_by !== $default_sort_option): ?>
             <div style="margin-top: 20px;"> <a href="products.php" class="btn btn-secondary btn-sm">View All Products & Clear All Filters</a> </div>
         <?php endif; ?>
    <?php else: ?> <p>No product categories found.</p> <?php endif; ?>
</section>
<hr style="margin: 30px 0; border-color: var(--border-color);">

<section class="product-filters" style="margin-bottom: 30px; padding: 20px; background-color: var(--light-bg-alt, #f9f9f9); border-radius: var(--border-radius); box-shadow: var(--box-shadow-light);">
    <form action="products.php" method="get" id="filter-sort-form">
        <?php if ($selected_category_id): ?>
            <input type="hidden" name="category" value="<?php echo $selected_category_id; ?>">
        <?php endif; ?>
        <input type="hidden" name="page" value="1">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; align-items: flex-end;">
            <div class="form-group">
                <label for="filter_search_term" style="display: block; margin-bottom: 5px; font-weight: 500;">Search</label>
                <input type="search" name="search" id="filter_search_term" class="form-control" placeholder="Product name/description" value="<?php echo htmlspecialchars($search_term); ?>">
            </div>
            <div class="form-group">
                <label for="filter_sort_by" style="display: block; margin-bottom: 5px; font-weight: 500;">Sort By</label>
                <select name="sort_by" id="filter_sort_by" class="form-control">
                    <option value="name_asc" <?php if ($sort_by == 'name_asc') echo 'selected'; ?>>Name (A-Z)</option>
                    <option value="name_desc" <?php if ($sort_by == 'name_desc') echo 'selected'; ?>>Name (Z-A)</option>
                    <option value="price_asc" <?php if ($sort_by == 'price_asc') echo 'selected'; ?>>Price (Low to High)</option>
                    <option value="price_desc" <?php if ($sort_by == 'price_desc') echo 'selected'; ?>>Price (High to Low)</option>
                    <option value="rating_desc" <?php if ($sort_by == 'rating_desc') echo 'selected'; ?>>Rating (Highest First)</option>
                    <option value="newest" <?php if ($sort_by == 'newest') echo 'selected'; ?>>Newest Arrivals</option>
                </select>
            </div>
            <div class="form-group" style="grid-column: 1 / -1; display: flex; flex-wrap:wrap; gap: 10px; margin-top:10px;">
                 <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
                 <?php
                    $reset_params = ['page' => 1];
                    if ($selected_category_id) $reset_params['category'] = $selected_category_id;
                    $reset_query_string = build_link_query_string($reset_params, $default_sort_option);
                 ?>
                 <a href="products.php<?php echo $reset_query_string ? '?' . $reset_query_string : ''; ?>" class="btn btn-outline-secondary">Reset Current View Filters</a>
            </div>
        </div>
    </form>
</section>

<section class="product-listing">
    <div style="margin-bottom: 20px;">
        <h2>
            <?php
                $title_parts = [];
                if ($selected_category_id) {
                    $category_name_title = "Products";
                    foreach ($categories as $cat) { if ($cat['category_id'] == $selected_category_id) { $category_name_title = htmlspecialchars($cat['name']); break; } }
                    $title_parts[] = $category_name_title;
                }
                if (!empty($search_term)) { $title_parts[] = "Search: \"" . htmlspecialchars($search_term) . "\""; }

                if (empty($title_parts)) { echo "All Products"; }
                else { echo implode(" <span style='color: var(--text-muted);'>|</span> ", $title_parts); }

                if ($total_products > 0) { echo " <span style='font-size: 0.8em; color: var(--text-color-secondary);'>({$total_products} items)</span>"; }
                else if (!empty($search_term) || $selected_category_id) {
                     echo " <span style='font-size: 0.8em; color: var(--text-color-secondary);'>(0 items found)</span>";
                }
            ?>
        </h2>
    </div>

    <div class="product-grid">
        <?php
        $select_created_at = ($sort_by === 'newest') ? ", p.created_at" : "";

        $sql_select_fields = "p.product_id, p.name, p.price, p.image_url, p.description {$select_created_at},
                              (SELECT ROUND(AVG(rating), 1) FROM feedback WHERE product_id = p.product_id AND is_approved = 1) AS average_rating";

        $sql_products = "SELECT " . $sql_select_fields
                      . $sql_base . $sql_joins . $sql_where_conditions
                      . " ORDER BY " . $sql_order_by_clause . " LIMIT ? OFFSET ?";

        $product_params_final = $params;
        $product_params_final[] = $products_per_page;
        $product_params_final[] = $offset;
        $product_types_final = $types . 'ii';

        $stmt_products = $mysqli->prepare($sql_products);
        $products_found = false;

        if ($stmt_products) {
            $stmt_products->bind_param($product_types_final, ...$product_params_final);

            if ($stmt_products->execute()) {
                $result_products = $stmt_products->get_result();
                if ($result_products->num_rows > 0) {
                    $products_found = true;
                    while ($product = $result_products->fetch_assoc()) {
                        ?>
                        <div class="product-card">
                            <div class="product-image">
                                <a href="product_details.php?id=<?php echo $product['product_id']; ?>">
                                    <img src="<?php echo BASE_URL . htmlspecialchars($product['image_url'] ?: 'img/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                </a>
                            </div>
                            <div class="product-info">
                                <h3><a href="product_details.php?id=<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a></h3>

                                <?php if (!is_null($product['average_rating'])): ?>
                                    <div class="product-rating" style="color: #FFD700; font-size: 1.2em; margin-bottom: 10px;">
                                        <?php $rating = round($product['average_rating']); for ($s = 1; $s <= 5; $s++) { echo $s <= $rating ? '★' : '☆'; } ?>
                                        <span style="font-size: 0.8em; color: #666; margin-left: 5px;"><?php echo $product['average_rating']; ?> / 5</span>
                                    </div>
                                <?php else: ?>
                                    <div class="product-rating" style="color: #666; font-size: 0.9em; margin-bottom: 10px;">No reviews yet.</div>
                                <?php endif; ?>

                                <p class="product-description" style="font-size: 0.9rem; color: #666; margin-bottom: 10px; height: 3.6em; overflow: hidden;">
                                    <?php echo htmlspecialchars(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : ''); ?>
                                </p>
                                <p class="product-price"><?php echo formatCurrency($product['price']); ?></p>
                                <div class="product-actions">
                                    <form action="buy_now_process.php" method="post" style="display: inline-block;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-bolt"></i> Buy Now</button>
                                    </form>
                                    <form action="add_to_cart.php" method="post" style="display: inline-block;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <button type="submit" class="btn btn-sm"><i class="fas fa-cart-plus"></i> Add</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    $result_products->free();
                }
            } else { error_log("Product Fetch Execute Error: ".$stmt_products->error. " SQL: " . $sql_products); }
            $stmt_products->close();
        } else { error_log("Product Fetch Prepare Error: ".$mysqli->error . " SQL: " . $sql_products); }

        if (!$products_found) {
            echo "<p style='grid-column: 1 / -1; text-align: center; margin-top: 30px;'>No products found matching your criteria.</p>";
        }
        ?>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav aria-label="Product Pagination" class="pagination-nav" style="margin-top: 40px; text-align: center;">
            <ul class="pagination" style="display: inline-flex; list-style: none; padding: 0; border-radius: var(--border-radius); overflow: hidden; box-shadow: var(--box-shadow-light); border: 1px solid var(--border-color); background-color: var(--white-color);">
                <?php if ($current_page > 1): ?>
                    <?php $prev_page_q = build_link_query_string(['page' => $current_page - 1], $default_sort_option); ?>
                    <li class="page-item"><a class="page-link" href="products.php<?php echo $prev_page_q ? '?' . $prev_page_q : ''; ?>" aria-label="Previous"><span aria-hidden="true">«</span> Prev</a></li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link" aria-hidden="true">«</span> Prev</li>
                <?php endif; ?>

                <?php $pagination_range = 2; $start_page = max(1, $current_page - $pagination_range); $end_page = min($total_pages, $current_page + $pagination_range); ?>
                <?php if ($start_page > 1): ?>
                    <?php $first_page_q = build_link_query_string(['page' => 1], $default_sort_option); ?>
                    <li class="page-item"><a class="page-link" href="products.php<?php echo $first_page_q ? '?' . $first_page_q : ''; ?>">1</a></li>
                    <?php if ($start_page > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                    <?php $page_q = build_link_query_string(['page' => $p], $default_sort_option); ?>
                    <li class="page-item <?php echo ($p == $current_page) ? 'active' : ''; ?>"><a class="page-link" href="products.php<?php echo $page_q ? '?' . $page_q : ''; ?>"><?php echo $p; ?></a></li>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                    <?php $last_page_q = build_link_query_string(['page' => $total_pages], $default_sort_option); ?>
                    <li class="page-item"><a class="page-link" href="products.php<?php echo $last_page_q ? '?' . $last_page_q : ''; ?>"><?php echo $total_pages; ?></a></li>
                <?php endif; ?>

                <?php if ($current_page < $total_pages): ?>
                    <?php $next_page_q = build_link_query_string(['page' => $current_page + 1], $default_sort_option); ?>
                    <li class="page-item"><a class="page-link" href="products.php<?php echo $next_page_q ? '?' . $next_page_q : ''; ?>" aria-label="Next">Next <span aria-hidden="true">»</span></a></li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link">Next <span aria-hidden="true">»</span></span></li>
                <?php endif; ?>
            </ul>
        </nav>
         <style>
             .pagination-nav .pagination { margin: 0; }
             .pagination .page-item { margin: 0; }
             .pagination .page-link { color: var(--primary-color); padding: 10px 15px; border: none; border-right: 1px solid var(--border-color); text-decoration: none; background: var(--white-color); transition: background-color 0.3s ease, color 0.3s ease; font-size: 0.9rem;}
             .pagination .page-item:last-child .page-link { border-right: none; }
             .pagination .page-link:hover { background-color: var(--lightest-bg, #f8f9fa); color: var(--primary-dark-color); z-index: 2;}
             .pagination .page-item.active .page-link { z-index: 3; color: var(--white-color); background-color: var(--primary-color); border-color: var(--primary-color); }
             .pagination .page-item.disabled .page-link { color: #6c757d; pointer-events: none; background-color: var(--white-color); }
             .form-group label { font-weight: 500; }
             .category-grid { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 10px; }
             .category-card { background-color: var(--white-color); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 15px; text-align: center; transition: all 0.3s ease; box-shadow: var(--box-shadow-light); flex: 1 1 150px; min-width: 120px;}
             .category-card:hover { transform: translateY(-3px); box-shadow: var(--box-shadow-medium); }
             .category-card.active { border-color: var(--primary-color); background-color: var(--primary-light-color); }
             .category-card a { text-decoration: none; color: var(--text-color); }
             .category-card h3 { margin: 0; font-size: 1rem; }
         </style>
    <?php endif; ?>

</section>

<?php
require_once 'includes/footer.php';
?>