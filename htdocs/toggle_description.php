<?php
// toggle_description.php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_id = (int)$_POST['question_id'];
    
    $conn->query("UPDATE meeting_questions SET show_description = 1 WHERE id = $question_id");
    
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false]);