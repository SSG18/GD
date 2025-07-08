<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

checkPermission(['admin', 'chairman']);

// Обработка действий
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $user_id = (int)$_GET['id'];

    if ($action === 'delete') {
        // Удаление пользователя
        $conn->query("DELETE FROM users WHERE id = $user_id");
        $_SESSION['success'] = "Пользователь удален!";
        header("Location: admin_users.php");
        exit();
    } elseif ($action === 'toggle_active') {
        // Переключение активности
        $conn->query("UPDATE users SET is_active = NOT is_active WHERE id = $user_id");
        $_SESSION['success'] = "Статус пользователя изменен!";
        header("Location: admin_users.php");
        exit();
    } elseif ($action === 'change_role') {
        // Изменение роли
        $new_role = $_POST['new_role'];
        $conn->query("UPDATE users SET role = '$new_role' WHERE id = $user_id");
        $_SESSION['success'] = "Роль пользователя изменена!";
        header("Location: admin_users.php");
        exit();
    }
}

// Получение списка пользователей
$users = $conn->query("SELECT * FROM users ORDER BY role, full_name");
?>

<h2>Управление пользователями</h2>

<table class="protocol-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Логин</th>
            <th>ФИО</th>
            <th>Роль</th>
            <th>Статус</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($user = $users->fetch_assoc()): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['login']) ?></td>
                <td><?= htmlspecialchars($user['full_name']) ?></td>
                <td><?= getRoleText($user['role']) ?></td>
                <td><?= $user['is_active'] ? 'Активен' : 'Заблокирован' ?></td>
                <td>
                    <a href="?action=toggle_active&id=<?= $user['id'] ?>" class="btn btn-small <?= $user['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                        <?= $user['is_active'] ? 'Заблокировать' : 'Активировать' ?>
                    </a>
                    
                    <button type="button" class="btn btn-small btn-info" 
                        onclick="document.getElementById('role-form-<?= $user['id'] ?>').style.display='block'">
                        Изменить роль
                    </button>
                    
                    <a href="?action=delete&id=<?= $user['id'] ?>" class="btn btn-small btn-danger" onclick="return confirm('Вы уверены?')">Удалить</a>
                    
                    <!-- Форма для изменения роли -->
                    <div id="role-form-<?= $user['id'] ?>" style="display: none; margin-top: 10px;">
                        <form method="POST" action="?action=change_role&id=<?= $user['id'] ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <select name="new_role" class="form-control" required>
                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Администратор</option>
                                        <option value="chairman" <?= $user['role'] === 'chairman' ? 'selected' : '' ?>>Председатель</option>
                                        <option value="vice_chairman" <?= $user['role'] === 'vice_chairman' ? 'selected' : '' ?>>Зам. председателя</option>
                                        <option value="deputy" <?= $user['role'] === 'deputy' ? 'selected' : '' ?>>Депутат</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-small">Сохранить</button>
                                    <button type="button" class="btn btn-small btn-danger" 
                                        onclick="document.getElementById('role-form-<?= $user['id'] ?>').style.display='none'">
                                        Отмена
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php require_once 'includes/footer.php'; ?>