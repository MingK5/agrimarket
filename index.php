<?php include 'includes/header.php'; ?>

<style>
    .hero-section {
        background-image: url('assets/farm.jpeg');
        background-size: cover;
        background-position: center;
        height: 700px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        padding: 0 40px;
    }

    .welcome-box {
        background-color: rgba(255, 255, 255, 0.9);
        padding: 20px 30px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        max-width: 600px;
        text-align: center;
        border: 1px solid #ccc;
        transform: translateY(-40px);
    }

    .welcome-box h1 {
        margin-top: 0;
        font-size: 28px;
        color: #2e7d32;
    }

    .welcome-box p {
        font-size: 16px;
        color: #333;
    }
</style>

<div class="hero-section">
    <div class="welcome-box">
        <h1>Welcome to AgriMarket Solutions</h1>
        <p>
            Your trusted digital marketplace for farmers and vendors. Discover, trade, and learn everything about agriculture—from livestock and crops to modern farming insights and analytics.
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>