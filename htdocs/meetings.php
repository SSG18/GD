<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

date_default_timezone_set('Europe/Moscow');
checkPermission(['admin', 'chairman', 'vice_chairman', 'deputy']);

// Обработка создания заседания
if (isset($_POST['create_meeting'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $meeting_date = $conn->real_escape_string($_POST['meeting_date']);
    $created_by = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO meetings (title, description, meeting_date, created_by) 
                 VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $title, $description, $meeting_date, $created_by);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Заседание успешно создано!";
        header("Location: meetings.php");
        exit();
    } else {
        $_SESSION['error'] = "Ошибка при создании заседания: " . $conn->error;
    }
}

$meetings = $conn->query("SELECT * FROM meetings ORDER BY meeting_date DESC");
?>

<h2 style="margin-bottom: 30px; color: #495057;">Заседания Государственной думы</h2>

<?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'chairman'): ?>
    <a href="create_meeting.php" class="btn">Создать новое заседание</a>
<?php endif; ?>

<div id="meetings-list" class="meetings-list">
    <?php if ($meetings->num_rows === 0): ?>
        <div class="alert alert-warning">Нет запланированных заседаний</div>
    <?php else: ?>
        <?php while ($meeting = $meetings->fetch_assoc()): ?>
            <?php
            $status_class = '';
            $status_text = '';
            switch ($meeting['status']) {
                case 'planned': 
                    $status_class = 'status-planned';
                    $status_text = 'Запланировано';
                    break;
                case 'active': 
                    $status_class = 'status-active';
                    $status_text = 'Идет';
                    break;
                case 'paused': 
                    $status_class = 'status-paused';
                    $status_text = 'Приостановлено';
                    break;
                case 'closed': 
                    $status_class = 'status-closed';
                    $status_text = 'Завершено';
                    break;
            }
            
            // Проверка на пустые значения
            $title = isset($meeting['title']) ? htmlspecialchars($meeting['title']) : '';
            $description = isset($meeting['description']) ? htmlspecialchars($meeting['description']) : '';
            $meeting_date = isset($meeting['meeting_date']) ? date('d.m.Y H:i', strtotime($meeting['meeting_date'])) : 'Дата не указана';
            ?>
            <div class="meeting-item">
                <div>
                    <h3><?= $title ?></h3>
                    <p><?= $description ?></p>
                    <small>Дата: <?= $meeting_date ?></small>
                </div>
                <div>
                    <span class="meeting-status <?= $status_class ?>"><?= $status_text ?></span>
                    <a href="manage_meeting.php?id=<?= $meeting['id'] ?>" 
                       class="btn btn-small <?= $meeting['status'] === 'active' ? 'btn-info' : 'btn-success' ?>">
                        <?= $meeting['status'] === 'active' ? 'Управление' : 'Перейти' ?>
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>