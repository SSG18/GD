<?php
require_once 'config.php';

if (isset($_POST['login'])) {
    $login = $conn->real_escape_string($_POST['login']);
    $password = $_POST['password'];
    
    $result = $conn->query("SELECT * FROM users WHERE login = '$login'");
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['success'] = "Вы успешно авторизованы!";
            header("Location: dashboard.php");
            exit();
        }
    }
    
    $_SESSION['error'] = "Неверный логин или пароль";
    header("Location: index.php");
    exit();
}

if (isset($_POST['register'])) {
    // Регистрационная логика
}
?>