<?php
// admin.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

checkPermission(['admin', 'chairman']);

// Статистика для админ-панели
$users_count = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$meetings_count = $conn->query("SELECT COUNT(*) FROM meetings")->fetch_row()[0];
$active_codes = $conn->query("SELECT COUNT(*) FROM activation_codes WHERE used = 0")->fetch_row()[0];
?>

<h2>Административная панель</h2>

<div class="dashboard">
    <div class="features">
        <div class="feature-card">
            <h3>Пользователи</h3>
            <p>Всего: <?= $users_count ?></p>
            <a href="admin_users.php" class="btn btn-small">Управление</a>
        </div>
        
        <div class="feature-card">
            <h3>Заседания</h3>
            <p>Всего: <?= $meetings_count ?></p>
            <a href="meetings.php" class="btn btn-small">Управление</a>
        </div>
        
        <div class="feature-card">
            <h3>Коды активации</h3>
            <p>Активные: <?= $active_codes ?></p>
            <a href="admin_codes.php" class="btn btn-small">Управление</a>
        </div>
        
        <div class="feature-card">
            <h3>Протоколы</h3>
            <p>Архив документов</p>
            <a href="admin_protocols.php" class="btn btn-small">Просмотр</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>