<?php
// includes/navigation.php
function renderNavigation() {
    $role = $_SESSION['role'] ?? '';
    ?>
    <ul class="nav-menu">
        <li><a href="dashboard.php">Дашборд</a></li>
        <li><a href="meetings.php">Заседания</a></li>
        
        <?php if ($role === 'admin' || $role === 'chairman'): ?>
            <li class="nav-header">Администрирование</li>
            <li><a href="admin_users.php">Пользователи</a></li>
            <li><a href="admin_codes.php">Коды активации</a></li>
            <li><a href="admin_protocols.php">Протоколы</a></li>
        <?php endif; ?>
        
        <li><a href="logout.php">Выход</a></li>
    </ul>
    <?php
}
?>