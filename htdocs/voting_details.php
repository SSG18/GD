<?php
// voting_details.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

checkPermission(['admin', 'chairman', 'vice_chairman', 'deputy']);
$conn->set_charset("utf8mb4");
date_default_timezone_set('Europe/Moscow');

// Получаем ID вопроса из параметра
$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$question_id) {
    $_SESSION['error'] = "Не указан идентификатор вопроса";
    header("Location: meetings.php");
    exit();
}

// Запрос для получения информации о вопросе
$question_query = "
    SELECT 
        q.title AS question_title,
        q.description AS question_description,
        q.question_order,
        q.is_secret,
        q.status,
        m.title AS meeting_title,
        m.id AS meeting_id
    FROM meeting_questions q
    INNER JOIN meetings m ON q.meeting_id = m.id
    WHERE q.id = $question_id
";

$question_result = $conn->query($question_query);
$question = $question_result->fetch_assoc();

if (!$question) {
    $_SESSION['error'] = "Вопрос не найден в базе данных";
    header("Location: meetings.php");
    exit();
}

// Проверяем, тайное ли голосование
if ($question['is_secret']) {
    $_SESSION['error'] = "Детали голосования недоступны для тайного голосования";
    header("Location: manage_meeting.php?id={$question['meeting_id']}&tab=results");
    exit();
}

// Запрос для получения всех присутствовавших на заседании и их голосов (если есть)
$attendance_query = "
    SELECT 
        u.full_name,
        u.fraction,
        IFNULL(v.vote, 'not_voted') AS vote,
        IFNULL(v.voted_at, 'Не голосовал') AS voted_at
    FROM meeting_attendance a
    INNER JOIN users u ON a.user_id = u.id
    LEFT JOIN votes v ON v.question_id = $question_id AND v.user_id = u.id
    WHERE a.meeting_id = {$question['meeting_id']}
        AND a.status IN ('present', 'remote')
    ORDER BY v.voted_at DESC
";
$attendance_result = $conn->query($attendance_query);
?>

<div class="content-container">
    <h2>Детали голосования</h2>
    
    <div class="question-info">
        <h3>Заседание: <?= htmlspecialchars($question['meeting_title']) ?></h3>
        <h4>Вопрос #<?= $question['question_order'] ?>: <?= htmlspecialchars($question['question_title']) ?></h4>
        
        <?php if (!empty($question['question_description'])): ?>
            <div class="description-box">
                <strong>Описание вопроса:</strong>
                <p><?= nl2br(htmlspecialchars($question['question_description'])) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="voting-results">
        <h4>Результаты голосования:</h4>
        
        <?php
        // Подсчет общего числа присутствующих
        $total_present = $conn->query("
            SELECT COUNT(*) 
            FROM meeting_attendance 
            WHERE meeting_id = {$question['meeting_id']} 
            AND status IN ('present', 'remote')
        ")->fetch_row()[0];
        
        // Число проголосовавших
        $voted_count = $conn->query("
            SELECT COUNT(*) 
            FROM votes 
            WHERE question_id = $question_id
        ")->fetch_row()[0];
        $not_voted = $total_present - $voted_count;
        ?>
        
        <div class="summary-stats">
            <div class="stat-item">
                <span class="stat-label">Присутствовало:</span>
                <span class="stat-value"><?= $total_present ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Проголосовало:</span>
                <span class="stat-value"><?= $voted_count ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Не голосовало:</span>
                <span class="stat-value"><?= $not_voted ?></span>
            </div>
        </div>
        
        <div class="navigation-actions">
            <button id="showVotersBtn" class="btn">Показать поименный список</button>
            <a href="manage_meeting.php?id=<?= $question['meeting_id'] ?>&tab=results" class="back-button">
                ← Вернуться к результатам заседания
            </a>
        </div>
    </div>
</div>

<!-- Модальное окно с поименным списком -->
<div id="votersModal" class="modal">
    <div class="modal-content" style="width: 80%; max-width: 1000px;">
        <span class="close">&times;</span>
        <h3>Поименный список голосования</h3>
        <div class="results-container">
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Депутат</th>
                        <th>Фракция</th>
                        <th>Голос</th>
                        <th>Время голосования</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $attendance_result->fetch_assoc()): 
                        $fraction = !empty($row['fraction']) ? $row['fraction'] : 'не указано';
                        $vote = $row['vote'];
                        $voted_at = $row['voted_at'];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($fraction) ?></td>
                            <td>
                                <div class="vote-badge <?= $vote ?>">
                                    <?php 
                                    switch ($vote) {
                                        case 'for': echo 'За'; break;
                                        case 'against': echo 'Против'; break;
                                        case 'abstain': echo 'Воздержался'; break;
                                        case 'not_voted': echo 'Не голосовал'; break;
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <?= ($voted_at !== 'Не голосовал') ? date('d.m.Y H:i:s', strtotime($voted_at)) : $voted_at ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.content-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.question-info {
    background-color: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 20px;
    margin-bottom: 25px;
    border-radius: 0 4px 4px 0;
}

.description-box {
    background-color: #e9ecef;
    padding: 12px;
    border-radius: 4px;
    margin-top: 10px;
}

.voting-results {
    margin-top: 20px;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}

.summary-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 50px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    justify-content: center;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 150px;
    padding: 10px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-label {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 5px;
}

.stat-value {
    font-size: 20px;
    font-weight: bold;
    color: #212529;
}

.results-container {
    overflow-x: auto;
    margin-top: 30px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}

.results-table {
    width: 100%;
    border-collapse: collapse;
}

.results-table th {
    background-color: #343a40;
    color: white;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
}

.results-table td {
    padding: 10px 15px;
    border-bottom: 1px solid #dee2e6;
}

.results-table tr:last-child td {
    border-bottom: none;
}

.results-table tr:nth-child(even) {
    background-color: #f8f9fa;
}

.results-table tr:hover {
    background-color: #e9ecef;
}

.vote-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: bold;
    text-align: center;
    min-width: 80px;
}

.vote-badge.for {
    background-color: #28a745;
    color: white;
}

.vote-badge.against {
    background-color: #dc3545;
    color: white;
}

.vote-badge.abstain {
    background-color: #6c757d;
    color: white;
}

.vote-badge.not_voted {
    background-color: #f39c12;
    color: white;
}

.navigation-actions {
    margin-top: 75px;
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: center;
}

.back-button {
    display: inline-block;
    padding: 10px 20px;
    background-color: #6c757d;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.back-button:hover {
    background-color: #5a6268;
    text-decoration: none;
    color: white;
}

#showVotersBtn {
    background-color: #3498db;
    color: white;
    margin-top: 100px;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
}

#showVotersBtn:hover {
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
    margin: 5% auto;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 0 20px rgba(0,0,0,0.2);
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('votersModal');
    const btn = document.getElementById('showVotersBtn');
    const span = document.querySelector('.close');
    
    btn.onclick = function() {
        modal.style.display = 'block';
    }
    
    span.onclick = function() {
        modal.style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>