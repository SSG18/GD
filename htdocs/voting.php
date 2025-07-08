<?php
// voting.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

checkPermission(['deputy']);
$conn->set_charset("utf8mb4");
date_default_timezone_set('Europe/Moscow');

$question_id = $_GET['question_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Проверка возможности голосования
$question = $conn->query("
    SELECT mq.*, m.status AS meeting_status
    FROM meeting_questions mq
    JOIN meetings m ON mq.meeting_id = m.id
    WHERE mq.id = $question_id
")->fetch_assoc();

if (!$question || $question['status'] !== 'voting' || $question['meeting_status'] !== 'active') {
    $_SESSION['error'] = "Голосование недоступно";
    header("Location: meetings.php");
    exit();
}

// Проверка регистрации на заседании
$attendance = $conn->query("
    SELECT * FROM meeting_attendance 
    WHERE meeting_id = {$question['meeting_id']} 
    AND user_id = $user_id
")->num_rows;

if (!$attendance) {
    $_SESSION['error'] = "Вы не зарегистрированы на этом заседании";
    header("Location: meetings.php");
    exit();
}

// Обработка голоса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vote = $_POST['vote'];
    $alternative_id = $_POST['alternative_id'] ?? null;
    
    $stmt = $conn->prepare("
        INSERT INTO votes (question_id, user_id, vote, alternative_id) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iisi", $question_id, $user_id, $vote, $alternative_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Ваш голос учтен!";
        header("Location: meetings.php");
        exit();
    }
}

// Получение альтернатив
$alternatives = $conn->query("
    SELECT * FROM question_alternatives 
    WHERE question_id = $question_id
    ORDER BY alternative_order
");

// Рассчитываем время окончания голосования, если установлена длительность
$end_time = null;
if ($question['voting_duration'] > 0) {
    $end_time = strtotime($question['voting_started_at'] . " + {$question['voting_duration']} seconds");
}
?>

<div class="voting-panel">
    <?php if ($end_time && $end_time > time()): ?>
        <div class="voting-timer">
            <div class="timer-text">Осталось времени:</div>
            <div class="timer-value" data-end="<?= $end_time ?>">
                <?= gmdate("i:s", $end_time - time()) ?>
            </div>
        </div>
    <?php elseif ($end_time): ?>
        <div class="alert alert-danger">Время голосования истекло</div>
    <?php endif; ?>
    
    <h2>Голосование по вопросу: <?= htmlspecialchars($question['title']) ?></h2>
    <p><?= htmlspecialchars($question['description']) ?></p>
    
    <form method="POST">
        <?php if ($alternatives->num_rows > 0): ?>
            <div class="form-group">
                <label>Выберите альтернативу:</label>
                <?php while ($alt = $alternatives->fetch_assoc()): ?>
                    <div class="question-alternative">
                        <input type="radio" name="alternative_id" 
                               value="<?= $alt['id'] ?>" required>
                        <strong><?= htmlspecialchars($alt['title']) ?></strong>
                        <p><?= htmlspecialchars($alt['description']) ?></p>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
        
        <div class="vote-buttons">
            <button type="submit" name="vote" value="for" class="vote-btn vote-for">За</button>
            <button type="submit" name="vote" value="against" class="vote-btn vote-against">Против</button>
            <button type="submit" name="vote" value="abstain" class="vote-btn vote-abstain">Воздержаться</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const timerElement = document.querySelector('.timer-value');
    if (timerElement) {
        const endTime = parseInt(timerElement.dataset.end);
        
        function updateTimer() {
            const now = Math.floor(Date.now() / 1000);
            const diff = endTime - now;
            
            if (diff <= 0) {
                timerElement.textContent = "00:00";
                clearInterval(timerInterval);
                alert('Время голосования истекло!');
                return;
            }
            
            const minutes = Math.floor(diff / 60);
            const seconds = diff % 60;
            timerElement.textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);
    }
});
</script>

<style>
.voting-panel {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.voting-timer {
    background: #343a40;
    color: white;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 20px;
}

.timer-text {
    font-size: 16px;
    margin-bottom: 5px;
}

.timer-value {
    font-size: 32px;
    font-weight: bold;
    font-family: monospace;
}

.vote-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 20px;
}

.vote-btn {
    padding: 15px 30px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
}

.vote-for {
    background-color: #28a745;
    color: white;
}

.vote-against {
    background-color: #dc3545;
    color: white;
}

.vote-abstain {
    background-color: #6c757d;
    color: white;
}

.question-alternative {
    margin-bottom: 15px;
    padding: 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}
</style>

<?php require_once 'includes/footer.php'; ?>