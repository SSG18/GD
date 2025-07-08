<?php
// add_initiatives_to_agenda.php
require_once 'includes/config.php';
require_once 'includes/functions.php';

checkPermission(['admin', 'chairman', 'vice_chairman']);

$meeting_id = (int)$_POST['meeting_id'];
$initiatives = $_POST['initiatives'] ?? [];
$formulas = $_POST['formula'] ?? [];

if (empty($initiatives)) {
    $_SESSION['error'] = "Не выбрано ни одной инициативы";
    header("Location: manage_meeting.php?id=$meeting_id&tab=questions");
    exit();
}

// Получаем текущий максимальный порядковый номер
$max_order = $conn->query("
    SELECT MAX(question_order) AS max_order 
    FROM meeting_questions 
    WHERE meeting_id = $meeting_id
")->fetch_assoc()['max_order'] ?? 0;

foreach ($initiatives as $initiative_id) {
    $initiative_id = (int)$initiative_id;
    $initiative = $conn->query("
        SELECT * FROM initiatives 
        WHERE id = $initiative_id AND status = 'pending'
    ")->fetch_assoc();
    
    if ($initiative) {
        $max_order++;
        $title = $conn->real_escape_string($initiative['title']);
        $description = $conn->real_escape_string($initiative['description']);
        $initiative_id_esc = $initiative['id'];
        $formula = $conn->real_escape_string($formulas[$initiative_id]);
        
        $conn->query("
            INSERT INTO meeting_questions 
            (meeting_id, title, description, question_order, initiative_id, votes_formula) 
            VALUES ($meeting_id, '$title', '$description', $max_order, $initiative_id_esc, '$formula')
        ");
    }
}

$_SESSION['success'] = "Инициативы успешно добавлены в повестку!";
header("Location: manage_meeting.php?id=$meeting_id&tab=questions");
exit();