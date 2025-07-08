<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

checkPermission(['admin', 'chairman', 'vice_chairman', 'deputy']);

$meeting_id = $_GET['id'] ?? 0;
$tab = $_GET['tab'] ?? 'info';

// Получаем информацию о заседании
$meeting = $conn->query("SELECT * FROM meetings WHERE id = $meeting_id")->fetch_assoc();
$conn->set_charset("utf8mb4");

if (!$meeting) {
    $_SESSION['error'] = "Заседание не найдено";
    header("Location: meetings.php");
    exit();
}

// Проверка и автоматическое завершение голосований с истекшим временем
$now = time();
$active_votings = $conn->query("
    SELECT id, UNIX_TIMESTAMP(voting_started_at) as started, voting_duration 
    FROM meeting_questions 
    WHERE status = 'voting' 
      AND voting_duration > 0
");
while ($voting = $active_votings->fetch_assoc()) {
    $end_time = $voting['started'] + $voting['voting_duration'];
    if ($now >= $end_time) {
        $question_id = $voting['id'];
        // Завершаем голосование
        $conn->query("UPDATE meeting_questions SET status = 'completed', voting_ended_at = NOW() WHERE id = $question_id");
        // Рассчитываем результаты
        calculateVotingResults($question_id);
        $_SESSION['success'] = "Голосование #$question_id завершено автоматически по истечении времени";
    }
}

// Обработка управления статусом
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $update_query = "UPDATE meetings SET status = '$new_status'";
    
    if ($new_status === 'active') {
        $update_query .= ", started_at = NOW()";
    } elseif ($new_status === 'closed') {
        $update_query .= ", ended_at = NOW()";
    }
    
    $update_query .= " WHERE id = $meeting_id";
    $conn->query($update_query);
    $_SESSION['success'] = "Статус заседания обновлен!";
    header("Location: manage_meeting.php?id=$meeting_id");
    exit();
}

// Обработка открытия/закрытия регистрации
if (isset($_POST['open_registration'])) {
    $conn->query("UPDATE meetings SET registration_open = 1 WHERE id = $meeting_id");
    $_SESSION['success'] = "Регистрация присутствия открыта!";
    header("Location: manage_meeting.php?id=$meeting_id&tab=attendance");
    exit();
}

if (isset($_POST['close_registration'])) {
    $conn->query("UPDATE meetings SET registration_open = 0 WHERE id = $meeting_id");
    $_SESSION['success'] = "Регистрация присутствия закрыта!";
    header("Location: manage_meeting.php?id=$meeting_id&tab=attendance");
    exit();
}

// Обработка регистрации присутствия (для всех ролей)
if (isset($_POST['register_attendance'])) {
    $user_id = $_SESSION['user_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("REPLACE INTO meeting_attendance 
                          (meeting_id, user_id, status) 
                          VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $meeting_id, $user_id, $status);
    $stmt->execute();
    
    $_SESSION['success'] = "Ваше присутствие зарегистрировано!";
    header("Location: manage_meeting.php?id=$meeting_id&tab=attendance");
    exit();
}

// Обработка голосования по вопросам
if (isset($_POST['submit_vote'])) {
    $question_id = (int)$_POST['question_id'];
    $vote = $_POST['vote'];
    
    // Проверяем, зарегистрирован ли пользователь на заседании
    $attendance = $conn->query("
        SELECT * FROM meeting_attendance 
        WHERE meeting_id = $meeting_id 
        AND user_id = {$_SESSION['user_id']}
    ")->num_rows;
    
    if (!$attendance) {
        $_SESSION['error'] = "Вы не зарегистрированы на этом заседании!";
        header("Location: manage_meeting.php?id=$meeting_id&tab=voting");
        exit();
    }
    
    // Проверяем, не голосовал ли уже
    $existing_vote = $conn->query("
        SELECT * FROM votes 
        WHERE question_id = $question_id 
        AND user_id = {$_SESSION['user_id']}
    ")->num_rows;
    
    if ($existing_vote) {
        $_SESSION['error'] = "Вы уже проголосовали по этому вопросу!";
        header("Location: manage_meeting.php?id=$meeting_id&tab=voting");
        exit();
    }
    
    // Сохраняем голос
    $stmt = $conn->prepare("
        INSERT INTO votes (question_id, user_id, vote) 
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $question_id, $_SESSION['user_id'], $vote);
    $stmt->execute();
    
    $_SESSION['success'] = "Ваш голос учтен!";
    header("Location: manage_meeting.php?id=$meeting_id&tab=voting");
    exit();
}

// Обработка добавления вопроса
if (isset($_POST['add_question'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $formula = $conn->real_escape_string($_POST['votes_formula']);
    
    // Автоматическое определение порядкового номера
    $max_order = $conn->query("SELECT MAX(question_order) as max_order FROM meeting_questions WHERE meeting_id = $meeting_id")->fetch_assoc()['max_order'];
    $order = $max_order ? $max_order + 1 : 1;
    
    $stmt = $conn->prepare("
        INSERT INTO meeting_questions 
        (meeting_id, title, description, question_order, votes_formula) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issis", 
        $meeting_id, $title, $description, $order, $formula
    );
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Вопрос добавлен в повестку!";
    } else {
        $_SESSION['error'] = "Ошибка при добавлении вопроса: " . $conn->error;
    }
}

// Обработка обновления статуса вопроса
if (isset($_POST['update_question_status'])) {
    $question_id = (int)$_POST['question_id'];
    $status = $conn->real_escape_string($_POST['status']);
    $is_secret = isset($_POST['is_secret']) ? 1 : 0;
    $voting_duration = (int)($_POST['voting_duration'] ?? 0);
    
    $update_query = "UPDATE meeting_questions SET status = '$status', is_secret = $is_secret, voting_duration = $voting_duration";
    
    if ($status === 'voting') {
        $update_query .= ", voting_started_at = NOW()";
    } elseif ($status === 'completed') {
        $update_query .= ", voting_ended_at = NOW()";
        // Автоматический расчет результатов при завершении
        calculateVotingResults($question_id);
    }
    
    $update_query .= " WHERE id = $question_id";
    $conn->query($update_query);
}

// Обработка исключения участника
if (isset($_POST['remove_attendance'])) {
    $user_id = (int)$_POST['user_id'];
    $conn->query("DELETE FROM meeting_attendance WHERE meeting_id = $meeting_id AND user_id = $user_id");
    $_SESSION['success'] = "Участник исключен из присутствующих!";
    header("Location: manage_meeting.php?id=$meeting_id&tab=attendance");
    exit();
}

// Получаем список вопросов для заседания
$questions = $conn->query("
    SELECT * FROM meeting_questions 
    WHERE meeting_id = $meeting_id 
    ORDER BY question_order
");
?>

<h2>Управление заседанием: <?= htmlspecialchars($meeting['title']) ?></h2>

<!-- Вкладки управления -->
<div class="tabs">
    <a href="?id=<?= $meeting_id ?>&tab=info" 
       class="tab <?= $tab === 'info' ? 'active' : '' ?>">Информация</a>
    <a href="?id=<?= $meeting_id ?>&tab=attendance" 
       class="tab <?= $tab === 'attendance' ? 'active' : '' ?>">Присутствие</a>
    <a href="?id=<?= $meeting_id ?>&tab=questions" 
       class="tab <?= $tab === 'questions' ? 'active' : '' ?>">Вопросы</a>
    <a href="?id=<?= $meeting_id ?>&tab=voting" 
       class="tab <?= $tab === 'voting' ? 'active' : '' ?>">Голосование</a>
    <a href="?id=<?= $meeting_id ?>&tab=results" 
       class="tab <?= $tab === 'results' ? 'active' : '' ?>">Результаты</a>
</div>

<!-- Контент вкладок -->
<?php if ($tab === 'info'): ?>
    <div class="meeting-info">
        <p><strong>Описание:</strong> <?= htmlspecialchars($meeting['description']) ?></p>
        <p><strong>Дата проведения:</strong> 
            <?= date('d.m.Y H:i', strtotime($meeting['meeting_date'])) ?></p>
        <p><strong>Статус:</strong> 
            <span class="meeting-status status-<?= $meeting['status'] ?>">
                <?= getStatusText($meeting['status']) ?>
            </span>
        </p>
        
        <?php if ($_SESSION['role'] === 'chairman' || $_SESSION['role'] === 'admin'): ?>
            <form method="POST" class="meeting-controls">
                <div class="form-group">
                    <label>Изменить статус:</label>
                    <select name="status" class="form-control">
                        <option value="planned" <?= $meeting['status'] === 'planned' ? 'selected' : '' ?>>Запланировано</option>
                        <option value="active" <?= $meeting['status'] === 'active' ? 'selected' : '' ?>>Начать заседание</option>
                        <option value="paused" <?= $meeting['status'] === 'paused' ? 'selected' : '' ?>>Приостановить</option>
                        <option value="closed" <?= $meeting['status'] === 'closed' ? 'selected' : '' ?>>Завершить</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="btn">Обновить статус</button>
                
                <?php if ($meeting['status'] === 'active'): ?>
                    <div style="margin-top: 15px;">
                        <?php if ($meeting['registration_open']): ?>
                            <button type="submit" name="close_registration" class="btn btn-danger">Завершить регистрацию</button>
                        <?php else: ?>
                            <button type="submit" name="open_registration" class="btn btn-success">Начать регистрацию</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
            
            <!-- Кнопка удаления заседания -->
            <?php if ($meeting['status'] === 'planned'): ?>
                <form method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить это заседание?');" style="margin-top: 20px;">
                    <button type="submit" name="delete_meeting" class="btn btn-danger">Удалить заседание</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'attendance'): ?>
    <h3>Регистрация присутствия</h3>
    
    <?php 
    // Проверяем, открыта ли регистрация
    $registration_open = ($meeting['status'] === 'active' && $meeting['registration_open']);
    
    // Разрешаем регистрацию для всех ролей
    if ($registration_open): 
    ?>
        <?php
        // Проверяем, отметился ли уже пользователь
        $user_attendance = $conn->query("
            SELECT status FROM meeting_attendance 
            WHERE meeting_id = $meeting_id AND user_id = {$_SESSION['user_id']}
        ");
        
        if ($user_attendance->num_rows > 0) {
            $att = $user_attendance->fetch_assoc();
            echo "<div class='alert alert-success'>Вы отметились: ".getAttendanceStatusText($att['status'])."</div>";
        } else {
        ?>
            <form method="POST" class="attendance-controls">
                <input type="hidden" name="register_attendance" value="1">
                <div class="form-group">
                    <label>Ваш статус участия:</label>
                    <div>
                        <button type="submit" name="status" value="present" class="btn">Присутствую</button>
                    </div>
                </div>
            </form>
        <?php } ?>
    <?php elseif ($meeting['status'] !== 'active'): ?>
        <div class="alert alert-warning">Заседание еще не начато</div>
    <?php elseif (!$meeting['registration_open']): ?>
        <div class="alert alert-warning">Регистрация присутствия закрыта</div>
    <?php endif; ?>
    
    <!-- Управление регистрацией для председателя -->
    <?php if ($_SESSION['role'] === 'chairman' || $_SESSION['role'] === 'admin'): ?>
        <div class="meeting-controls">
            <form method="POST">
                <?php if ($meeting['registration_open']): ?>
                    <button type="submit" name="close_registration" class="btn btn-danger">Завершить регистрацию</button>
                <?php else: ?>
                    <button type="submit" name="open_registration" class="btn btn-success">Начать регистрацию</button>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
    
    <?php
    // Считаем количество присутствующих
    $present_count = 0;
    $attendance_data = $conn->query("
        SELECT u.id AS user_id, u.full_name, a.status 
        FROM meeting_attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.meeting_id = $meeting_id
    ");
    
    // Сохраняем данные для повторного использования
    $attendance_list = [];
    while ($row = $attendance_data->fetch_assoc()) {
        $attendance_list[] = $row;
        if ($row['status'] === 'present') {
            $present_count++;
        }
    }
    ?>
    
    <h4>Список присутствующих (<?= $present_count ?> депутатов)</h4>
    
    <!-- Плашка с информацией о кворуме -->
    <?php if ($present_count > 10): ?>
        <div class="alert alert-success">Кворум собран</div>
    <?php else: ?>
        <div class="alert alert-danger">Кворум не собран (требуется >10 депутатов)</div>
    <?php endif; ?>
    
    <div class="attendance-list">
        <?php foreach ($attendance_list as $row): ?>
            <div class="attendance-item <?= $row['status'] ?>">
                <?= htmlspecialchars($row['full_name']) ?> 
                (<?= getAttendanceStatusText($row['status']) ?>)
                
                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'chairman' || $_SESSION['role'] === 'vice_chairman'): ?>
                    <form method="POST" style="display:inline-block; margin-left:10px;">
                        <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                        <button type="submit" name="remove_attendance" class="btn btn-small btn-danger">Исключить</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($tab === 'questions'): ?>
    <h3>Управление повесткой дня</h3>
    
    <!-- Секция для добавления инициатив -->
    <?php if ($_SESSION['role'] === 'chairman' || $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'vice_chairman'): ?>
        <div class="form-container" style="margin-bottom: 30px;">
            <h4>Добавить инициативы в повестку</h4>
            <?php
            // Выбираем только инициативы со статусом 'pending', которые еще не добавлены в повестку
            $pending_initiatives = $conn->query("
                SELECT i.*, u.full_name AS author_name 
                FROM initiatives i
                JOIN users u ON i.author_id = u.id
                WHERE i.status = 'pending'
                    AND NOT EXISTS (
                        SELECT 1 FROM meeting_questions mq 
                        WHERE mq.initiative_id = i.id
                    )
                ORDER BY i.created_at
            ");
            ?>
            
            <?php if ($pending_initiatives->num_rows > 0): ?>
                <div class="table-container">
                    <form method="POST" action="add_initiatives_to_agenda.php">
                        <input type="hidden" name="meeting_id" value="<?= $meeting_id ?>">
                        <table class="initiatives-table">
                            <thead>
                                <tr>
                                    <th>Выбрать</th>
                                    <th>Номер</th>
                                    <th>Название</th>
                                    <th>Автор</th>
                                    <th>Фракция</th>
                                    <th>Описание</th>
                                    <th>Формула голосования</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($initiative = $pending_initiatives->fetch_assoc()): 
                                    $fraction = !empty($initiative['fraction']) ? $initiative['fraction'] : 'не указано';
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="initiatives[]" value="<?= $initiative['id'] ?>">
                                        </td>
                                        <td><?= htmlspecialchars($initiative['initiative_number']) ?></td>
                                        <td><?= htmlspecialchars($initiative['title']) ?></td>
                                        <td><?= htmlspecialchars($initiative['author_name']) ?></td>
                                        <td><?= htmlspecialchars($fraction) ?></td>
                                        <td>
                                            <button type="button" class="btn-details" 
                                                    data-description="<?= htmlspecialchars($initiative['description']) ?>">
                                                Показать описание
                                            </button>
                                        </td>
                                        <td>
                                            <select name="formula[<?= $initiative['id'] ?>]" class="form-control" required>
                                                <option value="majority">Большинство</option>
                                                <option value="two_thirds">2/3 голосов</option>
                                                <option value="three_quarters">3/4 голосов</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <div class="form-group" style="margin-top: 15px;">
                            <button type="submit" class="btn">Добавить выбранные в повестку</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Нет инициатив на рассмотрении для добавления</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <!-- Кнопка для показа формы ручного добавления -->
    <div class="manual-question-toggle">
        <button id="toggleManualForm" class="btn">➕ Добавить вопрос вручную</button>
    </div>
    
    <!-- Форма ручного добавления вопросов (скрыта по умолчанию) -->
    <div id="manualQuestionForm" style="display: none; margin-bottom: 30px;">
        <div class="form-container">
            <h4>Добавить вопрос вручную</h4>
            <form method="POST">
                <div class="form-group">
                    <label>Название вопроса</label>
                    <input type="text" name="title" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Описание</label>
                    <textarea name="description" rows="3" class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Формула голосования</label>
                    <select name="votes_formula" class="form-control" required>
                        <option value="majority">Большинство</option>
                        <option value="two_thirds">2/3 голосов</option>
                        <option value="three_quarters">3/4 голосов</option>
                    </select>
                </div>
                
                <button type="submit" name="add_question" class="btn"
                     <?php if ($_SESSION['role'] !== 'chairman' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'vice_chairman'): ?>
                    onclick="alert('У вас недостаточно прав для этого действия'); return false;"
                <?php endif; ?>>
            Добавить вопрос
</button>
            </form>
        </div>
    </div>
    
    <!-- Список вопросов -->
    <h4>Вопросы в повестке</h4>
    <?php 
    $active_questions = $conn->query("
        SELECT * FROM meeting_questions 
        WHERE meeting_id = $meeting_id 
        AND status NOT IN ('completed', 'cancelled')
        ORDER BY question_order
    ");
    
    $completed_questions = $conn->query("
        SELECT * FROM meeting_questions 
        WHERE meeting_id = $meeting_id 
        AND status IN ('completed', 'cancelled')
        ORDER BY question_order DESC
    ");
    ?>
    
    <!-- Активные вопросы -->
    <?php if ($active_questions->num_rows > 0): ?>
        <?php while ($question = $active_questions->fetch_assoc()): ?>
            <div class="question-card">
                <div class="question-header">
                    <h4>#<?= $question['question_order'] ?>. <?= htmlspecialchars($question['title']) ?></h4>
                    <?php if (!empty($question['initiative_id'])): 
                        $initiative = $conn->query("SELECT initiative_number FROM initiatives WHERE id = {$question['initiative_id']}")->fetch_assoc();
                    ?>
                        <p><strong>Номер инициативы:</strong> <?= htmlspecialchars($initiative['initiative_number']) ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars($question['description']) ?></p>
                </div>
                
                <div class="question-meta">
                    <?php
                    $formula_text = '';
                    switch ($question['votes_formula']) {
                        case 'majority': 
                            $formula_text = 'Большинство (>50%)'; 
                            break;
                        case 'two_thirds': 
                            $formula_text = '2/3 присутствующих'; 
                            break;
                        case 'three_quarters': 
                            $formula_text = '3/4 присутствующих'; 
                            break;
                    }
                    ?>
                    <p><strong>Формула:</strong> <?= $formula_text ?></p>
                    
                    <p><strong>Статус:</strong> 
                        <?= getQuestionStatusText($question['status']) ?>
                        <?php if ($question['is_secret']): ?>
                            <span class="quorum-indicator">Тайное голосование</span>
                        <?php endif; ?>
                </div>
                
                <?php if ($_SESSION['role'] === 'chairman' || $_SESSION['role'] === 'admin'): ?>
                    <form method="POST" class="voting-controls">
                        <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                        
                        <div class="form-group">
                            <label>Изменить статус:</label>
                            <div class="voting-actions">
                                <?php if ($question['status'] === 'pending'): ?>
                                    <button type="submit" name="start_voting" class="btn">Начать голосование</button>
                                    <button type="submit" name="start_secret_voting" class="btn">Начать тайное голосование</button>
                                    
                                    <div class="timed-voting">
                                        <select name="voting_duration" class="form-control">
                                            <option value="0">Без таймера</option>
                                            <option value="30">30 секунд</option>
                                            <option value="60">1 минута</option>
                                            <option value="120">2 минуты</option>
                                            <option value="300">5 минут</option>
                                        </select>
                                        <button type="submit" name="start_timed_voting" class="btn">С таймером</button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($question['status'] === 'voting'): ?>
                                    <button type="submit" name="end_voting" class="btn btn-end">Завершить голосование</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-info">Нет активных вопросов</div>
    <?php endif; ?>
    
    <!-- Завершенные вопросы (свернуты) -->
    <?php if ($completed_questions->num_rows > 0): ?>
        <div class="completed-questions">
            <h5>Завершенные вопросы <button type="button" class="toggle-completed">Показать</button></h5>
            <div class="completed-list" style="display: none;">
                <?php while ($question = $completed_questions->fetch_assoc()): ?>
                    <div class="question-card completed">
                        <div class="question-header">
                            <h4>#<?= $question['question_order'] ?>. <?= htmlspecialchars($question['title']) ?></h4>
                            <?php if (!empty($question['initiative_id'])): 
                                $initiative = $conn->query("SELECT initiative_number FROM initiatives WHERE id = {$question['initiative_id']}")->fetch_assoc();
                            ?>
                                <p><strong>Номер инициативы:</strong> <?= htmlspecialchars($initiative['initiative_number']) ?></p>
                            <?php endif; ?>
                            <p><?= htmlspecialchars($question['description']) ?></p>
                        </div>
                        <div class="question-meta">
                            <p><strong>Статус:</strong> 
                                <?= getQuestionStatusText($question['status']) ?>
                                <?php if ($question['is_secret']): ?>
                                    <span class="quorum-indicator">Тайное голосование</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <script>
    // Переключение формы ручного добавления
    document.getElementById('toggleManualForm').addEventListener('click', function() {
        const form = document.getElementById('manualQuestionForm');
        if (form.style.display === 'none') {
            form.style.display = 'block';
            this.textContent = '✖ Скрыть форму';
        } else {
            form.style.display = 'none';
            this.textContent = '➕ Добавить вопрос вручную';
        }
    });
    
    // Переключение завершенных вопросов
    document.querySelector('.toggle-completed')?.addEventListener('click', function() {
        const list = document.querySelector('.completed-list');
        if (list.style.display === 'none') {
            list.style.display = 'block';
            this.textContent = 'Скрыть';
        } else {
            list.style.display = 'none';
            this.textContent = 'Показать';
        }
    });
    </script>
    
    <style>
    .manual-question-toggle {
        margin-bottom: 20px;
    }
    
    #toggleManualForm {
        background-color: #3498db;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        transition: background-color 0.3s;
    }
    
    #toggleManualForm:hover {
        background-color: #2980b9;
    }
    
    /* Стили для контейнера таблицы */
    .table-container {
        overflow-x: auto;
        margin-bottom: 20px;
        background: #fff;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        width: 100%;
    }
    
    /* Стили для таблицы инициатив */
    .initiatives-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        margin: 0 auto;
    }
    
    .initiatives-table th, .initiatives-table td {
        padding: 12px 15px;
        border: 1px solid #e0e0e0;
        text-align: left;
    }
    
    .initiatives-table th {
        background-color: #2c3e50;
        color: white;
        font-weight: 600;
    }
    
    .initiatives-table tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    
    .initiatives-table tr:hover {
        background-color: #f1f1f1;
    }
    
    .initiatives-table input[type="checkbox"] {
        transform: scale(1.2);
    }
    
    .initiatives-table .form-control {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .initiatives-table .btn-details {
        background-color: #3498db;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .initiatives-table .btn-details:hover {
        background-color: #2980b9;
    }
    </style>
<?php elseif ($tab === 'voting'): ?>
    <h3>Голосование</h3>
    
    <?php
    $current_question = $conn->query("
        SELECT * FROM meeting_questions 
        WHERE meeting_id = $meeting_id 
        AND status = 'voting'
        ORDER BY question_order
        LIMIT 1
    ");
    
    if ($current_question->num_rows === 0): ?>
        <div class="alert alert-info">
            В настоящее время нет активных голосований.
            <?php if ($_SESSION['role'] === 'chairman' || $_SESSION['role'] === 'admin'): ?>
                Вы можете начать голосование на вкладке "Вопросы".
            <?php endif; ?>
        </div>
    <?php else: 
        $question = $current_question->fetch_assoc();
        
        // Проверка регистрации на заседании
        $attendance = $conn->query("
            SELECT * FROM meeting_attendance 
            WHERE meeting_id = $meeting_id 
            AND user_id = {$_SESSION['user_id']}
        ")->num_rows;
        
        if (!$attendance) {
            echo '<div class="alert alert-error">Вы не зарегистрированы на этом заседании!</div>';
        } else {
            // Проверка, голосовал ли уже
            $voted = $conn->query("
                SELECT * FROM votes 
                WHERE question_id = {$question['id']} 
                AND user_id = {$_SESSION['user_id']}
            ")->num_rows;
            
            if ($voted) {
                echo '<div class="alert alert-success">Вы уже проголосовали по этому вопросу!</div>';
            } else {
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
                    
                    <h4>#<?= $question['question_order'] ?>. <?= htmlspecialchars($question['title']) ?></h4>
                    <p><?= htmlspecialchars($question['description']) ?></p>
                    
                    <form method="POST">
                        <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                        <input type="hidden" name="submit_vote" value="1">
                        
                        <div class="vote-buttons">
                            <button type="submit" name="vote" value="for" class="vote-btn vote-for">За</button>
                            <button type="submit" name="vote" value="against" class="vote-btn vote-against">Против</button>
                            <button type="submit" name="vote" value="abstain" class="vote-btn vote-abstain">Воздержаться</button>
                        </div>
                    </form>
                </div>
    <?php 
            }
        }
    endif; ?>

<?php elseif ($tab === 'results'): ?>
<h3>Результаты голосования</h3>
<?php
$questions = $conn->query("
    SELECT mq.*, vr.*, i.initiative_number
    FROM meeting_questions mq
    LEFT JOIN voting_results vr ON vr.question_id = mq.id
    LEFT JOIN initiatives i ON mq.initiative_id = i.id
    WHERE mq.meeting_id = $meeting_id
    AND mq.status = 'completed'
    ORDER BY mq.question_order
");
?>
<?php if ($questions->num_rows === 0): ?>
<div class="alert alert-warning">Нет завершенных голосований</div>
<?php else: ?>
<div class="protocol-table-container">
<table class="protocol-table">
<thead>
<tr>
<th>Вопрос</th>
<th>Присутствовало</th>
<th>За</th>
<th>Против</th>
<th>Воздержались</th>
<th>Не голосовало</th>
<th>Результат</th>
<th>Детали</th>
</tr>
</thead>
<tbody>
<?php while ($question = $questions->fetch_assoc()):
$not_voted = $question['total_present'] - ($question['votes_for'] + $question['votes_against'] + $question['votes_abstain']);

// Получаем правильный текст результата
$result_text = '';
$result_class = '';

if ($question['total_present']) {
    // Используем нашу логику для определения результата
    $votes_for = $question['votes_for'] ?? 0;
    $votes_against = $question['votes_against'] ?? 0;
    $votes_abstain = $question['votes_abstain'] ?? 0;
    $total_present = $question['total_present'];
    
    $abstain_threshold = floor($total_present / 2) + 1;
    
    if ($votes_abstain >= $abstain_threshold) {
        $result_text = 'Не принято';
        $result_class = 'not-passed';
    } elseif ($votes_for > $votes_against) {
        // Проверяем формулу голосования
        $formula = strtolower($question['votes_formula'] ?? 'majority');
        
        switch ($formula) {
            case 'majority':
                $result_text = 'Принято';
                $result_class = 'passed';
                break;
                
            case 'two_thirds':
                $votes_required = ceil($total_present * 2 / 3);
                if ($votes_for >= $votes_required) {
                    $result_text = 'Принято';
                    $result_class = 'passed';
                } else {
                    $result_text = 'Не принято';
                    $result_class = 'not-passed';
                }
                break;
                
            case 'three_quarters':
                $votes_required = ceil($total_present * 3 / 4);
                if ($votes_for >= $votes_required) {
                    $result_text = 'Принято';
                    $result_class = 'passed';
                } else {
                    $result_text = 'Не принято';
                    $result_class = 'not-passed';
                }
                break;
                
            default:
                $result_text = 'Принято';
                $result_class = 'passed';
                break;
        }
    } elseif ($votes_against > $votes_for) {
        $result_text = 'Отклонено';
        $result_class = 'rejected';
    } else {
        // $votes_for == $votes_against
        $result_text = 'Не принято';
        $result_class = 'not-passed';
    }
} else {
    $result_text = 'Кворум не собран';
    $result_class = 'no-quorum';
}
?>
<tr>
<td>
<strong>#<?= $question['question_order'] ?>.</strong>
<?php if (!empty($question['initiative_number'])): ?>
    (<?= htmlspecialchars($question['initiative_number']) ?>)
<?php endif; ?>
<?= htmlspecialchars($question['title']) ?>
</td>
<td><?= $question['total_present'] ?? 0 ?></td>
<td><?= $question['votes_for'] ?? 0 ?></td>
<td><?= $question['votes_against'] ?? 0 ?></td>
<td><?= $question['votes_abstain'] ?? 0 ?></td>
<td><?= $not_voted ?></td>
<td>
<span class="result-badge <?= $result_class ?>"><?= $result_text ?></span>
</td>
<td>
<?php if (!$question['is_secret']): ?>
<a href="voting_details.php?id=<?= $question['id'] ?>" class="btn btn-small">Подробнее</a>
<?php else: ?>
    Тайное голосование
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
<?php endif; ?>
    
    <style>
    .result-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 4px;
        font-weight: bold;
    }
    
    .result-badge.passed {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
        border: 1px solid #28a745;
    }
    
    .result-badge.rejected {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: 1px solid #dc3545;
    }
    </style>
<?php endif; ?>

<style>
/* Стили для таймера */
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

/* Стили для кнопок голосования */
.voting-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.timed-voting {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-end {
    background-color: #dc3545;
    color: white;
}

/* Стили для завершенных вопросов */
.completed-questions {
    margin-top: 30px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.completed-list {
    margin-top: 15px;
}

.question-card.completed {
    opacity: 0.7;
    background-color: #f8f9fa;
}

.toggle-completed {
    margin-left: 10px;
    padding: 3px 8px;
    font-size: 12px;
}
</style>

<script>
// Скрипт для таймера
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

<!-- Модальное окно для описания инициативы -->
<div id="descriptionModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Описание инициативы</h3>
        <div id="modal-description" style="white-space: pre-line;"></div>
    </div>
</div>

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

<style>
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
</style>


<?php require_once 'includes/footer.php'; ?>