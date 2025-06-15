<?php
$host = 'localhost';
$db = 'agrimarket';
$user = 'agrimarket_user';
$pass = 'agrimarket123'; // use your XAMPP password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
