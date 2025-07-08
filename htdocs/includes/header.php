<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Государственная дума - Система электронного голосования</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏛️ Государственная дума</h1>
            <p>Система электронного голосования и управления заседаниями</p>
        </div>

        <div class="main-content">
            <div class="sidebar">
                <ul class="nav-menu">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <li><a href="/index.php">🔐 Авторизация</a></li>
                        <li><a href="/register.php">📝 Регистрация</a></li>
                        <li><a href="/deputies.php">📑 Состав парламента</a></li>
                        <li><a href="/civil_initiatives.php">📌 Гражданская инициатива</a></li>
                    <?php else: ?>
                        <li><a href="/dashboard.php">📊 Панель управления</a></li>
                        <li><a href="/meetings.php">📅 Заседания</a></li>
                        <li><a href="/initiatives.php">📜 Инициативы</a></li>
                        <li><a href="/personal_cabinet.php">💼 Личный кабинет</a></li>
                        <li><a href="/deputies.php">📑 Состав парламента</a></li>
                        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'chairman'): ?>
                            <li><a href="/admin.php">🛠️ Администрирование</a></li>
                        <?php endif; ?>
                        <li><a href="/civil_initiatives.php">📌 Гражданская инициатива</a></li>
                        <li><a href="/logout.php">🚪 Выход</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="content-area">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>