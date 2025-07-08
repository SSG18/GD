<?php
// civil_initiatives.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$conn->set_charset("utf8mb4");

// Обработка подачи гражданской инициативы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_civil_initiative'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $passport_number = $conn->real_escape_string($_POST['passport_number']);
    $description = $conn->real_escape_string($_POST['description']);
    
    // Генерация номера инициативы
    $last_initiative = $conn->query("SELECT id FROM civil_initiatives ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $next_id = $last_initiative ? $last_initiative['id'] + 1 : 1;
    $initiative_number = "ГИ-" . str_pad($next_id, 3, '0', STR_PAD_LEFT);
    
    $stmt = $conn->prepare("
        INSERT INTO civil_initiatives 
        (initiative_number, full_name, passport_number, description) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("ssss", $initiative_number, $full_name, $passport_number, $description);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Инициатива успешно подана! Номер вашей инициативы: $initiative_number";
    } else {
        $_SESSION['error'] = "Ошибка при подаче инициативы: " . $conn->error;
    }
}

// Обработка удаления инициативы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_initiative'])) {
    // Проверка прав: только администратор, председатель или зам. председателя
    if (in_array($_SESSION['role'], ['admin', 'chairman', 'vice_chairman'])) {
        $initiative_id = (int)$_POST['initiative_id'];
        $conn->query("DELETE FROM civil_initiatives WHERE id = $initiative_id");
        $_SESSION['success'] = "Инициатива успешно удалена!";
        // Перезагружаем страницу
        header("Location: civil_initiatives.php");
        exit();
    } else {
        $_SESSION['error'] = "У вас нет прав для удаления инициатив";
        header("Location: civil_initiatives.php");
        exit();
    }
}

// Получение гражданских инициатив
$initiatives = $conn->query("
    SELECT * FROM civil_initiatives 
    ORDER BY created_at DESC
");
?>

<div class="civil-initiatives-container">
    <h2>Гражданские инициативы</h2>
    
    <div class="form-container">
        <h3>Подать гражданскую инициативу</h3>
        <form method="POST">
            <div class="form-group">
                <label>Фамилия и Имя</label>
                <input type="text" name="full_name" required class="form-control">
            </div>
            
            <div class="form-group">
                <label>Номер паспорта</label>
                <input type="text" name="passport_number" required class="form-control">
            </div>
            
            <div class="form-group">
                <label>Описание инициативы</label>
                <textarea name="description" rows="5" required class="form-control"></textarea>
            </div>
            
            <button type="submit" name="submit_civil_initiative" class="btn">Подать инициативу</button>
        </form>
    </div>
    
    <div class="initiatives-list">
        <h3>Ознакомиться с гражданскими инициативами</h3>
        <?php if ($initiatives->num_rows === 0): ?>
            <div class="alert alert-info">Гражданские инициативы не найдены</div>
        <?php else: ?>
            <table class="initiatives-table">
                <thead>
                    <tr>
                        <th>Номер</th>
                        <th>ФИО</th>
                        <th>Дата подачи</th>
                        <th>Описание</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($initiative = $initiatives->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($initiative['initiative_number']) ?></td>
                            <td><?= htmlspecialchars($initiative['full_name']) ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($initiative['created_at'])) ?></td>
                            <td>
                                <button class="btn-details" 
                                        data-id="<?= $initiative['id'] ?>"
                                        data-description="<?= htmlspecialchars($initiative['description']) ?>">
                                    Подробнее
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для описания инициативы -->
<div id="descriptionModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Описание инициативы</h3>
        <div id="modal-description" style="white-space: pre-line;"></div>
        
        <!-- Форма для удаления инициативы (доступна только для админов) -->
        <?php if (in_array($_SESSION['role'], ['admin', 'chairman', 'vice_chairman'])): ?>
            <form method="POST" id="deleteForm" style="margin-top: 20px;">
                <input type="hidden" name="initiative_id" id="modal-initiative-id">
                <button type="submit" name="delete_initiative" class="btn btn-danger">Удалить инициативу</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
.civil-initiatives-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.form-container {
    background: white;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 40px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}

.initiatives-list {
    background: white;
    border-radius: 8px;
    padding: 25px;
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
    background-color: #343a40;
    color: white;
    font-weight: 600;
}

.btn-details {
    background-color: #3498db;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-details:hover {
    background-color: #2980b9;
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
    width: 60%;
    max-width: 800px;
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

#modal-description {
    margin-top: 20px;
    line-height: 1.6;
    font-size: 16px;
    white-space: pre-line;
}

.btn-danger {
    background-color: #e74c3c;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
}

.btn-danger:hover {
    background-color: #c0392b;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обработка открытия модального окна
    document.querySelectorAll('.btn-details').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const description = this.getAttribute('data-description');
            document.getElementById('modal-description').textContent = description;
            document.getElementById('modal-initiative-id').value = id;
            document.getElementById('descriptionModal').style.display = 'block';
        });
    });

    // Закрытие модального окна
    document.querySelector('.close').addEventListener('click', function() {
        document.getElementById('descriptionModal').style.display = 'none';
    });

    // Закрытие при клике вне окна
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('descriptionModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>