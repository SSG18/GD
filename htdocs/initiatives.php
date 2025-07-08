<?php
// initiatives.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

checkPermission(['admin', 'chairman', 'vice_chairman', 'deputy']);
$conn->set_charset("utf8mb4");

// Получение всех инициатив
$query = "
    SELECT i.*, u.full_name AS author_name 
    FROM initiatives i
    JOIN users u ON i.author_id = u.id
    ORDER BY i.created_at DESC
";

$initiatives = $conn->query($query);
?>

<div class="initiatives-container">
    <h2>Все инициативы</h2>
    
    <?php if ($initiatives->num_rows === 0): ?>
        <div class="alert alert-info">Инициативы не найдены</div>
    <?php else: ?>
        <div class="initiatives-list">
            <table class="initiatives-table">
                <thead>
                    <tr>
                        <th>Номер</th>
                        <th>Название</th>
                        <th>Автор</th>
                        <th>Фракция</th>
                        <th>Дата подачи</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($initiative = $initiatives->fetch_assoc()): 
                        $fraction = !empty($initiative['fraction']) ? $initiative['fraction'] : 'не указано';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($initiative['initiative_number']) ?></td>
                            <td><?= htmlspecialchars($initiative['title']) ?></td>
                            <td><?= htmlspecialchars($initiative['author_name']) ?></td>
                            <td><?= htmlspecialchars($fraction) ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($initiative['created_at'])) ?></td>
                            <td>
                                <button class="btn-details" 
                                        data-description="<?= htmlspecialchars($initiative['description']) ?>">
                                    Подробнее
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно для описания инициативы -->
<div id="descriptionModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Описание инициативы</h3>
        <div id="modal-description"></div>
    </div>
</div>

<style>
.initiatives-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.initiatives-list {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}

.initiatives-table {
    width: 100%;
    border-collapse: collapse;
}

.initiatives-table th, .initiatives-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

.initiatives-table th {
    background-color: #343a40;
    color: white;
}

.initiative-status {
    padding: 5px 10px;
    border-radius: 4px;
    font-weight: bold;
}

.status-pending {
    background-color: #ffc107;
    color: #333;
}

.status-accepted {
    background-color: #28a745;
    color: white;
}

.status-rejected {
    background-color: #dc3545;
    color: white;
}

.btn-details {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-details:hover {
    background-color: #0069d9;
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обработка открытия модального окна
    document.querySelectorAll('.btn-details').forEach(button => {
        button.addEventListener('click', function() {
            const description = this.getAttribute('data-description');
            document.getElementById('modal-description').textContent = description;
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