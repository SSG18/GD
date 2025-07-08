<?php
// admin_codes.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

checkPermission(['admin', 'chairman']);

// Генерация нового кода
if (isset($_POST['generate_code'])) {
    $role = $_POST['role'];
    $fraction = $conn->real_escape_string($_POST['fraction']);
    $code = generateRandomString(7);
    $created_by = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        INSERT INTO activation_codes (code, role, fraction, created_by) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("sssi", $code, $role, $fraction, $created_by);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Код активации создан: $code";
    } else {
        $_SESSION['error'] = "Ошибка генерации кода";
    }
}

// Получение списка кодов
$codes = $conn->query("
    SELECT ac.*, u1.full_name AS created_by_name, u2.full_name AS used_by_name
    FROM activation_codes ac
    LEFT JOIN users u1 ON ac.created_by = u1.id
    LEFT JOIN users u2 ON ac.used_by = u2.id
    ORDER BY ac.created_at DESC
");
?>

<h2>Управление кодами активации</h2>

<div class="form-container">
    <form method="POST">
        <div class="form-group">
            <label>Роль для нового кода:</label>
            <select name="role" class="form-control" required>
                <option value="admin">Администратор</option>
                <option value="chairman">Председатель</option>
                <option value="vice_chairman">Зам. председателя</option>
                <option value="deputy">Депутат</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Фракция/Организация</label>
            <input type="text" name="fraction" class="form-control" required>
        </div>
        
        <button type="submit" name="generate_code" class="btn">Сгенерировать код</button>
    </form>
</div>

<h3>Список кодов</h3>
<table class="protocol-table">
    <thead>
        <tr>
            <th>Код</th>
            <th>Роль</th>
            <th>Фракция</th>
            <th>Создал</th>
            <th>Использован</th>
            <th>Кем использован</th>
            <th>Статус</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($code = $codes->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($code['code']) ?></td>
                <td><?= getRoleText($code['role']) ?></td>
                <td><?= htmlspecialchars($code['fraction']) ?></td>
                <td><?= htmlspecialchars($code['created_by_name']) ?></td>
                <td><?= $code['used'] ? date('d.m.Y H:i', strtotime($code['used_at'])) : 'Не использован' ?></td>
                <td><?= htmlspecialchars($code['used_by_name'] ?? '') ?></td>
                <td><?= $code['used'] ? 'Использован' : 'Активен' ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php require_once 'includes/footer.php'; ?>