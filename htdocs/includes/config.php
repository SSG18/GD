<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Установка временной зоны
date_default_timezone_set('Europe/Moscow');

$host = "sql305.infinityfree.com";
$username = "if0_38379964";
$password = "PkVpEd9XRo1b";
$dbname = "if0_38379964_kongress";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Добавление администратора по умолчанию
$result = $conn->query("SELECT id FROM users WHERE login = 'admin'");
if ($result->num_rows == 0) {
    $hashed_password = password_hash('password', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (login, password, role, full_name) VALUES 
        ('admin', '$hashed_password', 'admin', 'Системный администратор')");
}
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+03:00'");
?>