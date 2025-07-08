<?php
// deputies.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$conn->set_charset("utf8mb4");

// Получаем председателя
$chairman = $conn->query("
    SELECT * FROM users 
    WHERE role = 'chairman'
")->fetch_assoc();

// Получаем заместителей
$vice_chairmen = $conn->query("
    SELECT * FROM users 
    WHERE role = 'vice_chairman'
    ORDER BY full_name
");

// Получаем депутатов
$deputies = $conn->query("
    SELECT * FROM users 
    WHERE role = 'deputy'
    ORDER BY full_name
");
?>

<div class="deputies-container">
    <h2>Состав Государственной Думы</h2>
    
    <!-- Председатель -->
    <?php if ($chairman): ?>
        <div class="position-section">
            <h3>Председатель</h3>
            <div class="deputy-card chairman">
                <div class="deputy-avatar">
                    <div class="avatar-placeholder">👑</div>
                </div>
                <div class="deputy-info">
                    <h4><?= htmlspecialchars($chairman['full_name']) ?></h4>
                    <p>Председатель Государственной Думы</p>
                    <?php if (!empty($chairman['fraction'])): ?>
                        <p><strong>Фракция:</strong> <?= htmlspecialchars($chairman['fraction']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Заместители -->
    <?php if ($vice_chairmen->num_rows > 0): ?>
        <div class="position-section">
            <h3>Заместители председателя</h3>
            <div class="deputies-grid">
                <?php while ($vice = $vice_chairmen->fetch_assoc()): ?>
                    <div class="deputy-card vice-chairman">
                        <div class="deputy-avatar">
                            <div class="avatar-placeholder">⭐</div>
                        </div>
                        <div class="deputy-info">
                            <h4><?= htmlspecialchars($vice['full_name']) ?></h4>
                            <p>Заместитель председателя</p>
                            <?php if (!empty($vice['fraction'])): ?>
                                <p><strong>Фракция:</strong> <?= htmlspecialchars($vice['fraction']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Депутаты -->
    <?php if ($deputies->num_rows > 0): ?>
        <div class="position-section">
            <h3>Депутаты</h3>
            <div class="deputies-grid">
                <?php while ($deputy = $deputies->fetch_assoc()): ?>
                    <div class="deputy-card deputy">
                        <div class="deputy-avatar">
                            <div class="avatar-placeholder">👤</div>
                        </div>
                        <div class="deputy-info">
                            <h4><?= htmlspecialchars($deputy['full_name']) ?></h4>
                            <p>Депутат Государственной Думы</p>
                            <?php if (!empty($deputy['fraction'])): ?>
                                <p><strong>Фракция:</strong> <?= htmlspecialchars($deputy['fraction']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.deputies-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.position-section {
    margin-bottom: 40px;
}

.deputies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.deputy-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    display: flex;
    transition: transform 0.3s, box-shadow 0.3s;
}

.deputy-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.deputy-avatar {
    margin-right: 20px;
    display: flex;
    align-items: center;
}

.avatar-placeholder {
    font-size: 40px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.deputy-info {
    flex: 1;
}

.deputy-info h4 {
    margin: 0 0 5px 0;
    font-size: 18px;
}

.deputy-info p {
    margin: 5px 0;
    color: #555;
    font-size: 14px;
}

.chairman {
    border-left: 4px solid #ffc107;
}

.vice-chairman {
    border-left: 4px solid #17a2b8;
}

.deputy {
    border-left: 4px solid #6c757d;
}
</style>

<?php require_once 'includes/footer.php'; ?>