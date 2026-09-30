<?php

$host = "localhost";
$dbname = "barangay_information_system";
$port = "3306";
$username = "root";
$password = "Gymxx#2005.";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
<?php
require_once "config/database.php";

$stmt = $pdo->query("SELECT * FROM residents");
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>