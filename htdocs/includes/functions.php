<?php
// includes/functions.php
function getAttendanceStatusText($status) {
    $statuses = [
        'present' => 'Присутствует',
        'remote' => 'Удаленно',
        'absent' => 'Отсутствует'
    ];
    return $statuses[$status] ?? $status;
}

function getQuestionStatusText($status) {
    $statuses = [
        'pending' => 'Ожидает',
        'voting' => 'Голосование',
        'completed' => 'Завершено',
        'cancelled' => 'Отменено'
    ];
    return $statuses[$status] ?? $status;
}

function checkPermission($allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: index.php");
        exit();
    }
}

function generateRandomString($length = 7) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $result;
}

function getStatusText($status) {
    $statuses = [
        'planned' => 'Запланировано',
        'active' => 'Идет',
        'paused' => 'Приостановлено',
        'closed' => 'Завершено'
    ];
    return $statuses[$status] ?? $status;
}

function getRoleOptions($current_role) {
    $roles = [
        'admin' => 'Администратор',
        'chairman' => 'Председатель',
        'vice_chairman' => 'Зам. председателя',
        'deputy' => 'Депутат'
    ];
    
    $options = '';
    foreach ($roles as $value => $label) {
        $selected = $current_role === $value ? 'selected' : '';
        $options .= "<option value=\"$value\" $selected>$label</option>";
    }
    
    return $options;
}

function getRoleText($role) {
    $roles = [
        'admin' => 'Администратор',
        'chairman' => 'Председатель',
        'vice_chairman' => 'Зам. председателя',
        'deputy' => 'Депутат'
    ];
    return $roles[$role] ?? $role;
}

// Обработка начала голосования
if (isset($_POST['start_voting'])) {
    $question_id = (int)$_POST['question_id'];
    $is_secret = isset($_POST['is_secret']) ? 1 : 0;
    
    $conn->query("
        UPDATE meeting_questions 
        SET status = 'voting', 
            voting_started_at = NOW(),
            is_secret = $is_secret
        WHERE id = $question_id
    ");
    $_SESSION['success'] = "Голосование начато!";
}

// Обработка завершения голосования
if (isset($_POST['end_voting'])) {
    $question_id = (int)$_POST['question_id'];
    
    $conn->query("
        UPDATE meeting_questions 
        SET status = 'completed', 
            voting_ended_at = NOW()
        WHERE id = $question_id
    ");
    $_SESSION['success'] = "Голосование завершено!";
    
    // Автоматический расчет результатов при завершении
    calculateVotingResults($question_id);
}

// Функция подсчета результатов голосования
/*function calculateVotingResults($question_id) {
    global $conn;
    
    // Получаем данные вопроса
    $question = $conn->query("
        SELECT * FROM meeting_questions 
        WHERE id = $question_id
    ")->fetch_assoc();
    
    // Подсчет присутствующих
    $total_present = $conn->query("
        SELECT COUNT(*) 
        FROM meeting_attendance 
        WHERE meeting_id = {$question['meeting_id']} 
        AND status IN ('present', 'remote')
    ")->fetch_row()[0];
    
    // Проверка кворума
    $quorum_met = ($total_present >= $question['quorum_required']) ? 1 : 0;
    
    if (!$quorum_met) {
        // Если кворум не собран
        $conn->query("
            INSERT INTO voting_results 
            (question_id, total_present, votes_required, is_passed)
            VALUES ($question_id, $total_present, 0, 0)
        ");
        return;
    }
    
    // Подсчет голосов
    $votes = $conn->query("
        SELECT vote, COUNT(*) as count 
        FROM votes 
        WHERE question_id = $question_id
        GROUP BY vote
    ");
    
    $votes_for = 0;
    $votes_against = 0;
    $votes_abstain = 0;
    
    while ($row = $votes->fetch_assoc()) {
        switch ($row['vote']) {
            case 'for': $votes_for = $row['count']; break;
            case 'against': $votes_against = $row['count']; break;
            case 'abstain': $votes_abstain = $row['count']; break;
        }
    }
    
    // Расчет необходимых голосов
    $votes_required = 0;
    switch ($question['votes_formula']) {
        case 'majority':
            $votes_required = ceil($total_present / 2);
            break;
        case 'two_thirds':
            $votes_required = ceil($total_present * 2 / 3);
            break;
        case 'three_quarters':
            $votes_required = ceil($total_present * 3 / 4);
            break;
    }
    
    // Определение результата
    $is_passed = ($votes_for >= $votes_required) ? 1 : 0;
    
    // Сохранение результатов
    $conn->query("
        INSERT INTO voting_results 
        (question_id, total_present, votes_for, votes_against, votes_abstain, 
         votes_required, is_passed)
        VALUES ($question_id, $total_present, $votes_for, $votes_against, $votes_abstain,
                $votes_required, $is_passed)
    ");
}*/


function calculateVotingResults($question_id) {
    global $conn;
    
    // Получаем данные вопроса
    $question = $conn->query("
        SELECT * FROM meeting_questions 
        WHERE id = $question_id
    ")->fetch_assoc();
    
    // Подсчет присутствующих
    $total_present = $conn->query("
        SELECT COUNT(*) 
        FROM meeting_attendance 
        WHERE meeting_id = {$question['meeting_id']} 
        AND status IN ('present', 'remote')
    ")->fetch_row()[0];
    
    // Проверка кворума
    $quorum_met = ($total_present >= $question['quorum_required']) ? 1 : 0;
    
    if (!$quorum_met) {
        // Если кворум не собран
        $conn->query("
            INSERT INTO voting_results 
            (question_id, total_present, votes_required, is_passed)
            VALUES ($question_id, $total_present, 0, 0)
        ");
        return;
    }
    
    // Подсчет голосов
    $votes = $conn->query("
        SELECT vote, COUNT(*) as count 
        FROM votes 
        WHERE question_id = $question_id
        GROUP BY vote
    ");
    
    $votes_for = 0;
    $votes_against = 0;
    $votes_abstain = 0;
    
    while ($row = $votes->fetch_assoc()) {
        switch ($row['vote']) {
            case 'for': $votes_for = $row['count']; break;
            case 'against': $votes_against = $row['count']; break;
            case 'abstain': $votes_abstain = $row['count']; break;
        }
    }
    
    // Расчет необходимых голосов для справки
    $votes_required = 0;
    $formula = strtolower($question['votes_formula']);
    
    switch ($formula) {
        case 'majority':
            $votes_required = ceil($total_present / 2);
            break;
        case 'two_thirds':
            $votes_required = ceil($total_present * 2 / 3);
            break;
        case 'three_quarters':
            $votes_required = ceil($total_present * 3 / 4);
            break;
    }
    
    // Определение результата по новой логике
    $is_passed = 0;
    
    // Проверяем, воздержалось ли больше половины + 1
    $abstain_threshold = floor($total_present / 2) + 1;
    
    if ($votes_abstain >= $abstain_threshold) {
        // Если воздержалось больше половины + 1 - не принято
        $is_passed = 0;
    } elseif ($votes_for > $votes_against) {
        // Если за больше чем против
        switch ($formula) {
            case 'majority':
                // Для простого большинства достаточно, что за > против
                $is_passed = 1;
                break;
                
            case 'two_thirds':
            case 'three_quarters':
                // Для квалифицированного большинства нужно достичь порога
                $is_passed = ($votes_for >= $votes_required) ? 1 : 0;
                break;
        }
    } elseif ($votes_against > $votes_for) {
        // Если против больше чем за - отклонено
        $is_passed = 0;
    } else {
        // Если за = против - не принято
        $is_passed = 0;
    }
    
    // Сохранение результатов
    $conn->query("
        INSERT INTO voting_results 
        (question_id, total_present, votes_for, votes_against, votes_abstain, 
         votes_required, is_passed)
        VALUES ($question_id, $total_present, $votes_for, $votes_against, $votes_abstain,
                $votes_required, $is_passed)
    ");
}

// Функция для получения текста результата голосования
function getVotingResultText($question_id) {
    global $conn;
    
    // Получаем данные о голосовании и вопросе
    $result = $conn->query("
        SELECT vr.votes_for, vr.votes_against, vr.votes_abstain, vr.total_present, 
               vr.is_passed, vr.votes_required, mq.votes_formula 
        FROM voting_results vr
        JOIN meeting_questions mq ON vr.question_id = mq.id
        WHERE vr.question_id = $question_id
    ")->fetch_assoc();
    
    if (!$result) {
        return 'Результат не определен';
    }
    
    $votes_for = $result['votes_for'];
    $votes_against = $result['votes_against'];
    $votes_abstain = $result['votes_abstain'];
    $total_present = $result['total_present'];
    $is_passed = $result['is_passed'];
    $votes_required = $result['votes_required'];
    $formula = strtolower($result['votes_formula']);
    
    // Проверяем кворум - если присутствовало меньше требуемого
    if ($total_present == 0) {
        return 'Кворум не собран';
    }
    
    $abstain_threshold = floor($total_present / 2) + 1;
    
    // Определяем текст результата
    if ($votes_abstain >= $abstain_threshold) {
        return 'Не принято (воздержалось большинство)';
    } elseif ($votes_for > $votes_against) {
        if ($is_passed) {
            return 'Принято';
        } else {
            // Случай когда за > против, но не достигнут порог для квалифицированного большинства
            return 'Не принято (недостаточно голосов за)';
        }
    } elseif ($votes_against > $votes_for) {
        return 'Отклонено';
    } else {
        // $votes_for == $votes_against
        return 'Не принято (равенство голосов)';
    }
}

// Функция для получения детальных результатов голосования
function getDetailedVotingResults($question_id) {
    global $conn;
    
    $result = $conn->query("
        SELECT vr.*, mq.title, mq.votes_formula 
        FROM voting_results vr
        JOIN meeting_questions mq ON vr.question_id = mq.id
        WHERE vr.question_id = $question_id
    ")->fetch_assoc();
    
    if (!$result) {
        return null;
    }
    
    return [
        'title' => $result['title'],
        'total_present' => $result['total_present'],
        'votes_for' => $result['votes_for'],
        'votes_against' => $result['votes_against'],
        'votes_abstain' => $result['votes_abstain'],
        'votes_required' => $result['votes_required'],
        'formula' => $result['votes_formula'],
        'is_passed' => $result['is_passed'],
        'result_text' => getVotingResultText($question_id)
    ];
}


// Обработка начала голосования с таймером
if (isset($_POST['start_timed_voting'])) {
    $question_id = (int)$_POST['question_id'];
    $duration = (int)$_POST['voting_duration'];
    $is_secret = 0;
    
    $conn->query("
        UPDATE meeting_questions 
        SET status = 'voting', 
            voting_started_at = NOW(),
            voting_duration = $duration,
            is_secret = $is_secret
        WHERE id = $question_id
    ");
    $_SESSION['success'] = "Голосование начато с таймером!";
}

// Обработка начала тайного голосования
if (isset($_POST['start_secret_voting'])) {
    $question_id = (int)$_POST['question_id'];
    $is_secret = 1;
    
    $conn->query("
        UPDATE meeting_questions 
        SET status = 'voting', 
            voting_started_at = NOW(),
            is_secret = $is_secret
        WHERE id = $question_id
    ");
    $_SESSION['success'] = "Тайное голосование начато!";
}