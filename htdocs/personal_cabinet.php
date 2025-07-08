<?php
// personal_cabinet.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

checkPermission(['admin', 'chairman', 'vice_chairman', 'deputy']);
$conn->set_charset("utf8mb4");

$user_id = $_SESSION['user_id'];

// Смена пароля
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $user = $conn->query("SELECT password FROM users WHERE id = $user_id")->fetch_assoc();
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password = '$hashed_password' WHERE id = $user_id");
            $_SESSION['success'] = "Пароль успешно изменен!";
            // Перезагружаем страницу, чтобы обновить состояние
            header("Location: personal_cabinet.php");
            exit();
        } else {
            $_SESSION['error'] = "Новые пароли не совпадают";
        }
    } else {
        $_SESSION['error'] = "Текущий пароль неверен";
    }
}

// Подача новой инициативы
if (isset($_POST['submit_initiative'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    
    // Получаем фракцию пользователя из базы данных
    $user_fraction = $conn->query("
        SELECT fraction FROM users 
        WHERE id = $user_id
    ")->fetch_assoc()['fraction'] ?? '';
    
    // Генерация номера инициативы
    $last_initiative = $conn->query("SELECT id FROM initiatives ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $next_id = $last_initiative ? $last_initiative['id'] + 1 : 1;
    $initiative_number = "ГД-" . str_pad($next_id, 3, '0', STR_PAD_LEFT);
    
    $stmt = $conn->prepare("
        INSERT INTO initiatives 
        (initiative_number, title, description, author_id, fraction) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssis", $initiative_number, $title, $description, $user_id, $user_fraction);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Инициатива успешно подана!";
    } else {
        $_SESSION['error'] = "Ошибка при подаче инициативы: " . $conn->error;
    }
}

// Получение инициатив пользователя
$user_initiatives = $conn->query("
    SELECT * FROM initiatives 
    WHERE author_id = $user_id
    ORDER BY created_at DESC
");

// Получение фракции пользователя
$user_fraction = $conn->query("
    SELECT fraction FROM users 
    WHERE id = $user_id
")->fetch_assoc()['fraction'] ?? '';
?>

<div class="personal-cabinet">
    <h2>Личный кабинет</h2>
    
    <div class="cabinet-section">
        <button id="changePasswordBtn" class="btn">Сменить пароль</button>
    </div>
    
    <div class="cabinet-section">
        <h3>Подача инициативы</h3>
        <form method="POST">
            <div class="form-group">
                <label>Название инициативы</label>
                <input type="text" name="title" required class="form-control">
            </div>
            
            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" rows="4" required class="form-control"></textarea>
            </div>
            
            <div class="form-group">
                <label>Фракция/Организация</label>
                <input type="text" value="<?= htmlspecialchars($user_fraction ? $user_fraction : 'нет данных') ?>" class="form-control" readonly>
                <small class="form-text text-muted">Фракция автоматически заполняется из вашего профиля</small>
            </div>
            
            <button type="submit" name="submit_initiative" class="btn">Подать инициативу</button>
        </form>
    </div>
    
    <div class="cabinet-section">
        <h3>Мои инициативы</h3>
        <?php if ($user_initiatives->num_rows === 0): ?>
            <div class="alert alert-info">У вас нет поданых инициатив</div>
        <?php else: ?>
            <table class="initiatives-table">
                <thead>
                    <tr>
                        <th>Номер</th>
                        <th>Название</th>
                        <th>Дата подачи</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($initiative = $user_initiatives->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($initiative['initiative_number']) ?></td>
                            <td><?= htmlspecialchars($initiative['title']) ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($initiative['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для смены пароля -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Смена пароля</h3>
        <form method="POST">
            <div class="form-group">
                <label>Текущий пароль</label>
                <input type="password" name="current_password" required class="form-control">
            </div>
            
            <div class="form-group">
                <label>Новый пароль</label>
                <input type="password" name="new_password" required class="form-control">
            </div>
            
            <div class="form-group">
                <label>Подтвердите пароль</label>
                <input type="password" name="confirm_password" required class="form-control">
            </div>
            
            <button type="submit" name="change_password" class="btn">Изменить пароль</button>
        </form>
    </div>
</div>

<style>
.personal-cabinet {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.cabinet-section {
    background: #fff;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}

.initiatives-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 15px;
}

.initiatives-table th, .initiatives-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

.initiatives-table th {
    background-color: #2c3e50;
    color: white;
    font-weight: 600;
}

.initiative-status {
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 14px;
}

.status-pending {
    background-color: #f39c12;
    color: #fff;
}

.status-accepted {
    background-color: #27ae60;
    color: white;
}

.status-rejected {
    background-color: #e74c3c;
    color: white;
}

.form-text {
    font-size: 14px;
    color: #7f8c8d;
}

.btn {
    background-color: #3498db;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
}

.btn:hover {
    background-color: #2980b9;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 15px;
    font-size: 16px;
}

.form-group {
    margin-bottom: 20px;
}

/* Стили для модального окна */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 0 20px rgba(0,0,0,0.2);
    width: 40%;
    max-width: 500px;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #000;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('passwordModal');
    const btn = document.getElementById('changePasswordBtn');
    const span = document.querySelector('.close');
    
    btn.onclick = function() {
        modal.style.display = 'block';
    }
    
    span.onclick = function() {
        modal.style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>