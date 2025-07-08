<?php
require_once 'includes/config.php';

// Не подключаем auth.php, чтобы избежать редиректов
//session_start();

if (isset($_POST['register'])) {
    $login = $conn->real_escape_string($_POST['login']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $code = $conn->real_escape_string($_POST['code']);
    
    // Проверка кода активации
    $stmt = $conn->prepare("SELECT * FROM activation_codes WHERE code = ? AND used = 0");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $code_result = $stmt->get_result();
    
    if ($code_result->num_rows == 1) {
        $code_data = $code_result->fetch_assoc();
        $role = $code_data['role'];
        $fraction = $code_data['fraction']; // Получаем фракцию из кода
        
        // Создание пользователя с фракцией
        $stmt = $conn->prepare("INSERT INTO users (login, password, role, full_name, fraction) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $login, $password, $role, $full_name, $fraction);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // Пометить код как использованный
            $stmt = $conn->prepare("UPDATE activation_codes SET used = 1, used_by = ?, used_at = NOW() WHERE code = ?");
            $stmt->bind_param("is", $user_id, $code);
            $stmt->execute();
            
            $_SESSION['success'] = "Регистрация успешна! Теперь вы можете войти в систему.";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Ошибка при создании пользователя: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Неверный или уже использованный код активации";
    }
}

require_once 'includes/header.php';
?>

<div class="form-container">
    <h2 style="text-align: center; margin-bottom: 30px; color: #495057;">Регистрация</h2>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="reg-login">Логин:</label>
            <input type="text" id="reg-login" name="login" required class="form-control">
        </div>
        <div class="form-group">
            <label for="reg-password">Пароль:</label>
            <input type="password" id="reg-password" name="password" required class="form-control">
        </div>
        <div class="form-group">
            <label for="full-name">Полное имя:</label>
            <input type="text" id="full-name" name="full_name" required class="form-control">
        </div>
        <div class="form-group">
            <label for="code">Код активации (7 символов):</label>
            <input type="text" id="code" name="code" maxlength="7" required class="form-control">
            <small style="color: #6c757d;">Код активации выдается администратором</small>
        </div>
        <button type="submit" name="register" class="btn">Зарегистрироваться</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>