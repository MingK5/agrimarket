<?php
session_start();
require '../includes/db.php';

$userType = $_SESSION['user']['userType_Id'] ?? 0;
$vendorId = $userType == 3 ? $_SESSION['user']['id'] : null;

if ($userType == 0) {
    header("Location: ../index.php");
    exit();
}

$productId = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM product WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: product_listing.php");
    exit();
}

// Map packaging to units
$packagingToUnit = [
    'Bag' => 'kg',
    'Box' => 'piece',
    'Piece' => 'piece',
    'Tray' => 'dozen',
    'Jar' => 'kg', // Adjust to 'liter' for liquids like yogurt if needed
    'Cup' => 'liter',
    'Pack' => 'kg',
    'Wheel' => 'kg',
    'Bottle' => 'liter',
    'Root' => 'kg',
    'Punnet' => 'kg',
    'Head' => 'piece',
    'Bunch' => 'piece',
    'Whole' => 'piece',
    'Cage' => 'piece',
    'Pen' => 'piece',
];
$unit = $packagingToUnit[$product['packaging']] ?? 'piece';

$descriptions = [
    'Chemical Fertilizers' => 'High-quality chemical fertilizers designed to boost crop yield, rich in nitrogen, phosphorus, and potassium. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for large-scale farming, ensuring robust plant growth.',
    'Organic Fertilizers' => 'Eco-friendly organic fertilizers made from natural compost and manure. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for sustainable agriculture, promoting soil health.',
    'Aquaculture Feed' => 'Premium feed formulated for aquaculture fish, packed with essential nutrients. Price: RM' . $product['price'] . '/' . $unit . '. Enhances fish growth and health in farming environments.',
    'Prawns' => 'Fresh prawns harvested from sustainable aquaculture farms. Price: RM' . $product['price'] . '/' . $unit . '. Known for their succulent taste, great for gourmet dishes.',
    'Catfish' => 'Fresh catfish caught from clean waters. Price: RM' . $product['price'] . '/' . $unit . '. A versatile fish, perfect for frying or grilling.',
    'Tilapia' => 'Fresh tilapia fish with a mild flavor. Price: RM' . $product['price'] . '/' . $unit . '. Widely used in various cuisines, rich in protein.',
    'Eggs' => 'Fresh farm eggs laid by healthy hens. Price: RM' . $product['price'] . '/' . $unit . '. Packed with vitamins, ideal for baking or breakfast.',
    'Beeswax' => 'Pure natural beeswax for crafting or skincare. Price: RM' . $product['price'] . '/' . $unit . '. Sourced sustainably, excellent for candles or balms.',
    'Honey' => 'Pure honey from beehives, rich in antioxidants. Price: RM' . $product['price'] . '/' . $unit . '. A natural sweetener with medicinal properties.',
    'Ghee' => 'Clarified butter from dairy, perfect for cooking. Price: RM' . $product['price'] . '/' . $unit . '. Adds a rich flavor to Indian dishes.',
    'Yogurt' => 'Natural yogurt with live cultures. Price: RM' . $product['price'] . '/' . $unit . '. Great for gut health and a delicious snack.',
    'Butter' => 'Fresh butter made from cream. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for baking or spreading on bread.',
    'Cheese' => 'Aged cheese with a sharp flavor. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for cheeseboards or melting.',
    'Fresh Milk' => 'Fresh dairy milk from local cows. Price: RM' . $product['price'] . '/' . $unit . '. Rich in calcium, suitable for all ages.',
    'Ginseng' => 'Medicinal ginseng root for health benefits. Price: RM' . $product['price'] . '/' . $unit . '. Known to boost energy and immunity.',
    'Moringa' => 'Moringa leaves and powder, nutrient-rich. Price: RM' . $product['price'] . '/' . $unit . '. A superfood for smoothies or teas.',
    'Shiitake' => 'Fresh shiitake mushrooms with earthy taste. Price: RM' . $product['price'] . '/' . $unit . '. Excellent in soups or stir-fries.',
    'Oyster' => 'Fresh oyster mushrooms, tender and flavorful. Price: RM' . $product['price'] . '/' . $unit . '. Great for vegetarian dishes.',
    'Raspberries' => 'Fresh raspberries, sweet and tart. Price: RM' . $product['price'] . '/' . $unit . '. Rich in vitamins, perfect for desserts.',
    'Blueberries' => 'Fresh blueberries, antioxidant-packed. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for snacks or baking.',
    'Cashews' => 'Raw cashew nuts, creamy and nutritious. Price: RM' . $product['price'] . '/' . $unit . '. Great for snacking or cooking.',
    'Walnuts' => 'Fresh walnuts, heart-healthy nuts. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for salads or baking.',
    'Almonds' => 'Raw almonds, rich in healthy fats. Price: RM' . $product['price'] . '/' . $unit . '. A great energy-boosting snack.',
    'Lettuce' => 'Fresh lettuce, crisp and green. Price: RM' . $product['price'] . '/' . $unit . '. Essential for fresh salads.',
    'Kale' => 'Fresh kale leaves, nutrient-dense. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for smoothies or sautéing.',
    'Spinach' => 'Fresh spinach, rich in iron. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for healthy meals.',
    'Sweet Potatoes' => 'Fresh sweet potatoes, sweet and starchy. Price: RM' . $product['price'] . '/' . $unit . '. Great for roasting or mashing.',
    'Potatoes' => 'Fresh potatoes, versatile and filling. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for fries or soups.',
    'Cassava' => 'Fresh cassava root, starchy and hearty. Price: RM' . $product['price'] . '/' . $unit . '. Used in traditional dishes.',
    'Avocado' => 'Fresh avocados, creamy and nutritious. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for guacamole or toast.',
    'Watermelon' => 'Fresh watermelon, juicy and refreshing. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for summer hydration.',
    'Passion Fruit' => 'Fresh passion fruit, tangy and exotic. Price: RM' . $product['price'] . '/' . $unit . '. Great for juices or desserts.',
    'Guava' => 'Fresh guava, sweet and aromatic. Price: RM' . $product['price'] . '/' . $unit . '. Rich in vitamin C.',
    'Papaya' => 'Fresh papaya, tropical and digestive-friendly. Price: RM' . $product['price'] . '/' . $unit . '. Excellent for smoothies.',
    'Pineapples' => 'Fresh pineapples, sweet and tangy. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for grilling or snacks.',
    'Mangoes' => 'Fresh mangoes, juicy and flavorful. Price: RM' . $product['price'] . '/' . $unit . '. A tropical delight.',
    'Bananas' => 'Fresh bananas, sweet and energy-rich. Price: RM' . $product['price'] . '/' . $unit . '. Great for breakfast.',
    'Cucumber' => 'Fresh cucumbers, crisp and hydrating. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for salads.',
    'Chili Peppers' => 'Fresh chili peppers, spicy and vibrant. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for seasoning.',
    'Pumpkin' => 'Fresh pumpkin, versatile and nutritious. Price: RM' . $product['price'] . '/' . $unit . '. Great for soups or pies.',
    'Eggplant' => 'Fresh eggplant, tender and flavorful. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for grilling.',
    'Okra' => 'Fresh okra, slimy and healthy. Price: RM' . $product['price'] . '/' . $unit . '. Used in stews or stir-fries.',
    'Carrots' => 'Fresh carrots, crunchy and vitamin-rich. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for snacking.',
    'Onions' => 'Fresh onions, pungent and essential. Price: RM' . $product['price'] . '/' . $unit . '. Great for cooking.',
    'Tomatoes' => 'Fresh tomatoes, juicy and versatile. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for sauces.',
    'Lentils' => 'Dried lentils, protein-packed. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for soups.',
    'Peas' => 'Dried peas, nutritious and filling. Price: RM' . $product['price'] . '/' . $unit . '. Great for side dishes.',
    'Soybeans' => 'Dried soybeans, rich in protein. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for tofu.',
    'Corn' => 'Dried corn, sweet and versatile. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for cornmeal.',
    'Barley' => 'Dried barley, hearty and nutritious. Price: RM' . $product['price'] . '/' . $unit . '. Great for soups.',
    'Rice' => 'Dried rice, staple grain. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for all meals.',
    'Wheat' => 'Dried wheat, versatile grain. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for baking.',
    'Rabbits' => 'Live rabbits for breeding, healthy stock. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for small farms.',
    'Sheep' => 'Live sheep, robust livestock. Price: RM' . $product['price'] . '/' . $unit . '. Great for wool and meat.',
    'Goats' => 'Live goats, hardy animals. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for dairy.',
    'Breeding Pigs' => 'Breeding pigs, high-quality stock. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for pork production.',
    'Piglets' => 'Young piglets, lively and healthy. Price: RM' . $product['price'] . '/' . $unit . '. Great for starting a herd.',
    'Turkeys' => 'Live turkeys, plump birds. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for holiday feasts.',
    'Chickens' => 'Live chickens, egg-laying breed. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for eggs.',
    'Ducks' => 'Live ducks, versatile poultry. Price: RM' . $product['price'] . '/' . $unit . '. Great for meat and eggs.',
    'Dairy Cows' => 'Dairy cows, high milk yield. Price: RM' . $product['price'] . '/' . $unit . '. Perfect for dairy farming.',
    'Beef Cattle' => 'Beef cattle, quality meat producers. Price: RM' . $product['price'] . '/' . $unit . '. Ideal for beef production.',
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Product Details</title>
    <style>
        .details { padding: 30px; max-width: 700px; margin: 0 auto; background: #f9f9f9; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .details img { max-width: 100%; height: 300px; object-fit: cover; border-radius: 5px; margin-bottom: 20px; }
        .details h2 { font-size: 28px; color: #2d6f2d; margin: 15px 0; font-family: 'Arial', sans-serif; }
        .details p { font-size: 16px; color: #444; line-height: 1.6; margin: 10px 0; }
        .details strong { color: #333; }
        .quantity { margin: 20px 0; }
        .quantity label { font-size: 16px; margin-right: 10px; }
        .quantity input { width: 80px; padding: 8px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 12px 25px; background-color: #3a8f3a; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #2d6f2d; }
        a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #ddd; color: #333; text-decoration: none; border-radius: 5px; }
        a:hover { background: #ccc; }
        /* Header styling to match Product Listing page */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: #fff; /* No grey box, match Product Listing's clean look */
            border-bottom: 1px solid #ddd;
        }
        nav .logo img {
            height: 40px; /* Adjust based on your logo size */
        }
        nav ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        nav ul li {
            margin-left: 20px;
        }
        nav ul li a {
            text-decoration: none;
            color: #2d6f2d;
            font-size: 16px;
            font-family: 'Arial', sans-serif;
        }
        nav ul li a:hover {
            color: #1a5c1a;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="details">
        <?php if ($product['image']): ?>
            <img src="../assets/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
        <?php endif; ?>
        <h2><?php echo htmlspecialchars($product['name']); ?></h2>
        <p><strong>Description:</strong> <?php echo htmlspecialchars($descriptions[$product['name']] ?? $product['description']); ?></p>
        <p><strong>Price:</strong> RM<?php echo htmlspecialchars($product['price']); ?>/<?php echo $unit; ?></p>
        <?php if ($_SESSION['user']['userType_Id'] == 4): ?>
            <div class="quantity">
                <label for="quantity">Quantity:</label>
                <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo htmlspecialchars($product['quantity']); ?>">
            </div>
            <form method="POST" action="shopping_cart.php">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                <button type="submit">Add to Cart</button>
            </form>
        <?php endif; ?>
        <a href="product_listing.php">Back to Listing</a>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>