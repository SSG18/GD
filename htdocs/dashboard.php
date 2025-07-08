<?php
// dashboard.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

checkPermission(['admin', 'chairman', 'vice_chairman', 'deputy']);
$conn->set_charset("utf8mb4");
// Функция для получения названия роли
function getRoleName($role) {
    $roles = [
        'admin' => 'Администратор',
        'chairman' => 'Председатель',
        'vice_chairman' => 'Зам. председателя',
        'deputy' => 'Депутат'
    ];
    return $roles[$role] ?? $role;
}

// Статистика
$active_meetings = $conn->query("SELECT COUNT(*) FROM meetings WHERE status = 'active'")->fetch_row()[0];
$planned_meetings = $conn->query("SELECT COUNT(*) FROM meetings WHERE status = 'planned'")->fetch_row()[0];
$closed_meetings = $conn->query("SELECT COUNT(*) FROM meetings WHERE status = 'closed'")->fetch_row()[0];
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
?>

<div id="dashboard-section">
    <div class="dashboard">
        <div class="dashboard-header">
            <h2>Панель управления</h2>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?> (<?= getRoleName($_SESSION['role'] ?? '') ?>)</span>
            </div>
        </div>

        <div class="features">
            <div class="feature-card">
                <div style="font-size: 3em; margin-bottom: 15px; color: #28a745;">🗳️</div>
                <h3>Активные заседания</h3>
                <p>Текущие заседания и голосования</p>
                <div style="font-size: 2em; color: #28a745; margin-top: 15px;" id="active-meetings">
                    <?= $active_meetings ?>
                </div>
            </div>
            
            <div class="feature-card">
                <div style="font-size: 3em; margin-bottom: 15px; color: #ffc107;">🗓️</div>
                <h3>Запланированные заседания</h3>
                <p>Предстоящие заседания</p>
                <div style="font-size: 2em; color: #ffc107; margin-top: 15px;" id="planned-meetings">
                    <?= $planned_meetings ?>
                </div>
            </div>
            
            <div class="feature-card">
                <div style="font-size: 3em; margin-bottom: 15px; color: #6c757d;">📁</div>
                <h3>Завершенные заседания</h3>
                <p>Архив заседаний</p>
                <div style="font-size: 2em; color: #6c757d; margin-top: 15px;" id="closed-meetings">
                    <?= $closed_meetings ?>
                </div>
            </div>
            
            <div class="feature-card">
                <div style="font-size: 3em; margin-bottom: 15px; color: #007bff;">👥</div>
                <h3>Пользователи</h3>
                <p>Зарегистрированные пользователи</p>
                <div style="font-size: 2em; color: #007bff; margin-top: 15px;" id="total-users">
                    <?= $total_users ?>
                </div>
            </div>
        </div>
        
        <?php if ($active_meetings > 0): ?>
            <div style="margin-top: 30px;">
                <h3>Активные заседания</h3>
                <div class="meetings-list">
                    <?php 
                    $meetings = $conn->query("SELECT * FROM meetings WHERE status = 'active' ORDER BY meeting_date DESC");
                    while ($meeting = $meetings->fetch_assoc()): 
                    ?>
                        <div class="meeting-item">
                            <div>
                                <h4><?= htmlspecialchars($meeting['title']) ?></h4>
                                <small>Дата: <?= date('d.m.Y H:i', strtotime($meeting['meeting_date'])) ?></small>
                            </div>
                            <div>
                                <a href="manage_meeting.php?id=<?= $meeting['id'] ?>" class="btn btn-small btn-info">
                                    Участвовать
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>