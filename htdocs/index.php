<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'includes/header.php';
?>

<div id="login-section">
    <div class="form-container">
        <h2 style="text-align: center; margin-bottom: 30px; color: #495057;">Вход в систему</h2>
        <form method="POST" action="">
            <input type="hidden" name="login_form" value="1">
            <div class="form-group">
                <label for="login">Логин:</label>
                <input type="text" id="login" name="login" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Войти в систему</button>
        </form>
        <div style="text-align: center; margin-top: 20px;">
            <small>Made by Валерий Зорькин. ds - treak_</small>
        </div>
</div>

<?php require_once 'includes/footer.php'; ?>