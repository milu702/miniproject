<?php
session_start();
require_once 'config.php';

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

// Define products array with categories
$products = [
    'fertilizers' => [
        [
            'id' => 'F1',
            'name' => 'Premium NPK 6:6:20',
            'description' => 'Specialized NPK blend for cardamom cultivation',
            'price' => 1200,
            'unit' => 'per 50kg bag',
            'stock' => 50,
            'image' => 'img/npk.jpg'
        ],
        [
            'id' => 'F2',
            'name' => 'Organic Manure Plus',
            'description' => 'Well-decomposed organic matter enriched with beneficial microbes',
            'price' => 800,
            'unit' => 'per 25kg bag',
            'stock' => 100,
            'image' => 'img/organic manure plus.jpg'
        ],
        [
            'id' => 'F3',
            'name' => 'Super Phosphate',
            'description' => 'High-grade phosphate fertilizer for root development',
            'price' => 950,
            'unit' => 'per 50kg bag',
            'stock' => 75,
            'image' => 'img/Super Phosphate.jpg'
        ]
    ],
    'pesticides' => [
        [
            'id' => 'P1',
            'name' => 'Neem Oil Organic',
            'description' => 'Natural pest control solution, safe for cardamom',
            'price' => 450,
            'unit' => 'per 1L bottle',
            'stock' => 200,
            'image' => 'img/neemoil.jpg'
        ],
        [
            'id' => 'P2',
            'name' => 'Bio-Fungicide',
            'description' => 'Organic fungal disease control for cardamom plants',
            'price' => 600,
            'unit' => 'per 500ml bottle',
            'stock' => 150,
            'image' => 'img/bio.jpg'
        ]
    ],
    'tools' => [
        [
            'id' => 'T1',
            'name' => 'Sprayer Premium',
            'description' => 'High-quality sprayer for pesticide application',
            'price' => 1500,
            'unit' => 'per piece',
            'stock' => 30,
            'image' => 'img/sprayer.jpg'
        ],
        [
            'id' => 'T2',
            'name' => 'Safety Kit',
            'description' => 'Complete safety gear for pesticide application',
            'price' => 800,
            'unit' => 'per kit',
            'stock' => 50,
            'image' => 'img/Safety Kit.jpg'
        ]
    ]
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide Store - Agricultural Products</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-green: #2e7d32;
            --light-green: #4caf50;
            --pale-green: #e8f5e9;
            --hover-green: #1b5e20;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }

        .store-header {
            background: linear-gradient(135deg, var(--primary-green), var(--light-green));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .store-nav {
            background: white;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .category-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .category-btn {
            background: none;
            border: 2px solid var(--primary-green);
            color: var(--primary-green);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .category-btn:hover,
        .category-btn.active {
            background: var(--primary-green);
            color: white;
        }

        .products-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .category-section {
            margin-bottom: 3rem;
        }

        .category-title {
            color: var(--primary-green);
            border-bottom: 2px solid var(--light-green);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-name {
            font-size: 1.2rem;
            color: var(--primary-green);
            margin: 0 0 0.5rem 0;
        }

        .product-description {
            color: #666;
            margin-bottom: 1rem;
        }

        .product-price {
            font-size: 1.25rem;
            color: var(--primary-green);
            font-weight: bold;
        }

        .product-unit {
            color: #666;
            font-size: 0.9rem;
        }

        .stock-status {
            color: var(--primary-green);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .buy-btn {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 0.8rem 1rem;
            border-radius: 25px;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .buy-btn:hover {
            background: var(--hover-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .buy-btn i {
            font-size: 1rem;
        }

        .back-button {
            background: var(--primary-green);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 20px;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: var(--hover-green);
            transform: translateX(-5px);
        }

        @media (max-width: 768px) {
            .category-buttons {
                flex-wrap: wrap;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            opacity: 1;
        }

        .modal-content {
            position: relative;
            background: white;
            width: 90%;
            max-width: 800px;
            margin: 50px auto;
            border-radius: 15px;
            padding: 20px;
            transform: translateY(-50px);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--primary-green);
        }

        .modal-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .product-detail-image {
            border-radius: 10px;
            overflow: hidden;
        }

        .product-detail-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-detail-image img:hover {
            transform: scale(1.05);
        }

        .product-detail-info {
            padding: 20px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
            padding: 10px;
            background: var(--pale-green);
            border-radius: 8px;
        }

        .detail-row i {
            color: var(--primary-green);
            font-size: 1.2rem;
        }

        .price-block {
            font-size: 1.5rem;
            color: var(--primary-green);
            font-weight: bold;
        }

        .unit {
            font-size: 0.9rem;
            color: #666;
        }

        .usage-instructions {
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .modal-buy-btn {
            margin-top: 20px;
        }

        /* Make product cards clickable */
        .product-card {
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .modal-body {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <a href="farmer.php" class="back-button">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="store-header">
        <h1><i class="fas fa-store"></i> GrowGuide Agricultural Store</h1>
        <p>Quality products for your cardamom cultivation</p>
    </div>

    <nav class="store-nav">
        <div class="category-buttons">
            <button class="category-btn active" onclick="filterProducts('all')">
                <i class="fas fa-th-large"></i> All Products
            </button>
            <button class="category-btn" onclick="filterProducts('fertilizers')">
                <i class="fas fa-leaf"></i> Fertilizers
            </button>
            <button class="category-btn" onclick="filterProducts('pesticides')">
                <i class="fas fa-shield-alt"></i> Pesticides
            </button>
            <button class="category-btn" onclick="filterProducts('tools')">
                <i class="fas fa-tools"></i> Tools & Equipment
            </button>
        </div>
    </nav>

    <div class="products-container">
        <?php foreach ($products as $category => $items): ?>
        <div class="category-section" id="<?php echo $category; ?>">
            <h2 class="category-title">
                <?php echo ucfirst($category); ?>
            </h2>
            <div class="products-grid">
                <?php foreach ($items as $product): ?>
                <div class="product-card">
                    <img src="<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" class="product-image">
                    <div class="product-info">
                        <h3 class="product-name"><?php echo $product['name']; ?></h3>
                        <p class="product-description"><?php echo $product['description']; ?></p>
                        <div class="product-price">
                            ₹<?php echo number_format($product['price']); ?>
                            <span class="product-unit"><?php echo $product['unit']; ?></span>
                        </div>
                        <div class="stock-status">
                            <i class="fas fa-box"></i> In Stock: <?php echo $product['stock']; ?> units
                        </div>
                        <button class="buy-btn" onclick="buyNow('<?php echo $product['id']; ?>', '<?php echo $product['name']; ?>', <?php echo $product['price']; ?>)">
                            <i class="fas fa-shopping-bag"></i> Buy Now
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="modal-body">
                <div class="product-detail-image">
                    <img id="modalImage" src="" alt="">
                </div>
                <div class="product-detail-info">
                    <h2 id="modalTitle"></h2>
                    <p id="modalDescription"></p>
                    <div class="detail-row">
                        <i class="fas fa-tag"></i>
                        <div class="price-block">
                            <span id="modalPrice"></span>
                            <span id="modalUnit" class="unit"></span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <i class="fas fa-box"></i>
                        <span id="modalStock"></span>
                    </div>
                    <div class="detail-row">
                        <i class="fas fa-info-circle"></i>
                        <div class="usage-instructions"></div>
                    </div>
                    <button class="buy-btn modal-buy-btn" onclick="buyNow(currentProduct.id, currentProduct.name, currentProduct.price)">
                        <i class="fas fa-shopping-bag"></i> Buy Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function filterProducts(category) {
            const sections = document.querySelectorAll('.category-section');
            const buttons = document.querySelectorAll('.category-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            if (category === 'all') {
                sections.forEach(section => section.style.display = 'block');
            } else {
                sections.forEach(section => {
                    section.style.display = section.id === category ? 'block' : 'none';
                });
            }
        }

        function buyNow(productId, productName, price) {
            if (confirm(`Confirm purchase of ${productName} for ₹${price}?`)) {
                // You can redirect to a payment gateway or order processing page
                alert('Thank you for your purchase! Our team will contact you shortly for delivery details.');
                
                // Optional: You can redirect to a thank you page or order confirmation page
                // window.location.href = `order_confirmation.php?product=${productId}`;
            }
        }

        let currentProduct = null;

        // Add click event to product cards
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Don't trigger if clicking the buy button
                if (e.target.classList.contains('buy-btn') || e.target.closest('.buy-btn')) {
                    return;
                }
                
                const productId = this.querySelector('.buy-btn').getAttribute('onclick').split("'")[1];
                showProductDetails(productId);
            });
        });

        function showProductDetails(productId) {
            // Find the product details from the PHP products array
            <?php
            echo "const allProducts = " . json_encode($products) . ";\n";
            ?>
            
            // Find the product in the allProducts object
            for (const category in allProducts) {
                const product = allProducts[category].find(p => p.id === productId);
                if (product) {
                    currentProduct = product;
                    break;
                }
            }

            if (currentProduct) {
                // Get usage instructions based on product category
                const usageInstructions = getUsageInstructions(currentProduct.id);
                
                // Update modal content
                document.getElementById('modalImage').src = currentProduct.image;
                document.getElementById('modalTitle').textContent = currentProduct.name;
                document.getElementById('modalDescription').textContent = currentProduct.description;
                document.getElementById('modalPrice').textContent = `₹${currentProduct.price.toLocaleString()}`;
                document.getElementById('modalUnit').textContent = currentProduct.unit;
                document.getElementById('modalStock').textContent = `In Stock: ${currentProduct.stock} units`;
                document.querySelector('.usage-instructions').innerHTML = usageInstructions;

                // Show modal with animation
                const modal = document.getElementById('productModal');
                modal.style.display = 'block';
                setTimeout(() => modal.classList.add('show'), 10);
            }
        }

        function getUsageInstructions(productId) {
            const category = productId.charAt(0);
            let instructions = '';
            
            switch(category) {
                case 'F':
                    instructions = `
                        <h4>Application Instructions:</h4>
                        <ul>
                            <li>Best applied during early morning or evening</li>
                            <li>Mix thoroughly with soil around the plant base</li>
                            <li>Water immediately after application</li>
                            <li>Reapply every 3-4 months for best results</li>
                        </ul>`;
                    break;
                case 'P':
                    instructions = `
                        <h4>Safety Instructions:</h4>
                        <ul>
                            <li>Wear protective gear during application</li>
                            <li>Avoid application during windy conditions</li>
                            <li>Keep away from children and pets</li>
                            <li>Store in a cool, dry place</li>
                        </ul>`;
                    break;
                case 'T':
                    instructions = `
                        <h4>Usage Guidelines:</h4>
                        <ul>
                            <li>Read instruction manual before use</li>
                            <li>Clean after each use</li>
                            <li>Store in dry conditions</li>
                            <li>Regular maintenance recommended</li>
                        </ul>`;
                    break;
            }
            return instructions;
        }

        // Close modal when clicking the close button or outside the modal
        document.querySelector('.close-modal').addEventListener('click', closeModal);
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        function closeModal() {
            const modal = document.getElementById('productModal');
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html> 