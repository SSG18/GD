<?php
// create_meeting.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

date_default_timezone_set('Europe/Moscow');
checkPermission(['admin', 'chairman']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
?>

<h2>Создание нового заседания</h2>

<form method="POST" class="form-container">
    <div class="form-group">
        <label>Название заседания</label>
        <input type="text" name="title" required class="form-control">
    </div>
    
    <div class="form-group">
        <label>Описание</label>
        <textarea name="description" rows="4" class="form-control"></textarea>
    </div>
    
    <div class="form-group">
        <label>Дата и время проведения</label>
        <input type="datetime-local" name="meeting_date" required class="form-control">
    </div>
    
    <div class="form-actions">
        <button type="submit" name="create_meeting" class="btn">Создать заседание</button>
        <a href="meetings.php" class="btn btn-danger">Отмена</a>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>