<?php
/**
 * Конфигурация базы данных для системы "Государственная дума"
 * InfinityFree hosting configuration
 */

// Настройки базы данных
define('DB_HOST', 'sql305.infinityfree.com');
define('DB_NAME', 'if0_38379964_kongress');
define('DB_USER', 'if0_38379964');
define('DB_PASS', 'PkVpEd9XRo1b');
define('DB_CHARSET', 'utf8mb4');

// Настройки сессии
define('SESSION_TIMEOUT', 28800); // 8 часов
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 минут

// Класс для работы с базой данных
class Database {
    private $connection;
    private static $instance = null;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}

// Класс для управления пользователями
class UserManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Аутентификация пользователя
     */
    public function authenticate($username, $password) {
        try {
            // Проверяем блокировку аккаунта
            if ($this->isAccountLocked($username)) {
                return ['success' => false, 'message' => 'Аккаунт заблокирован из-за множественных неудачных попыток входа'];
            }
            
            $stmt = $this->db->prepare("
                SELECT id, username, password_hash, full_name, role, status, failed_login_attempts 
                FROM users 
                WHERE username = ? AND status = 'active'
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Успешная аутентификация
                $this->resetFailedAttempts($user['id']);
                $this->updateLastLogin($user['id']);
                $this->logUserActivity($user['id'], 'login', 'Успешный вход в систему');
                
                return [
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'full_name' => $user['full_name'],
                        'role' => $user['role']
                    ]
                ];
            } else {
                // Неудачная попытка входа
                if ($user) {
                    $this->incrementFailedAttempts($user['id']);
                }
                return ['success' => false, 'message' => 'Неверный логин или пароль'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка при аутентификации'];
        }
    }
    
    /**
     * Создание нового пользователя
     */
    public function createUser($userData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password_hash, email, full_name, role, phone, department, position_title)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            $stmt->execute([
                $userData['username'],
                $passwordHash,
                $userData['email'],
                $userData['full_name'],
                $userData['role'],
                $userData['phone'] ?? null,
                $userData['department'] ?? null,
                $userData['position_title'] ?? null
            ]);
            
            $userId = $this->db->lastInsertId();
            $this->logUserActivity($userId, 'user_created', 'Создан новый пользователь');
            
            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка при создании пользователя: ' . $e->getMessage()];
        }
    }
    
    /**
     * Получение информации о пользователе
     */
    public function getUserById($userId) {
        $stmt = $this->db->prepare("
            SELECT id, username, email, full_name, role, status, phone, department, position_title, 
                   created_at, last_login
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Проверка блокировки аккаунта
     */
    private function isAccountLocked($username) {
        $stmt = $this->db->prepare("
            SELECT account_locked_until 
            FROM users 
            WHERE username = ? AND account_locked_until > NOW()
        ");
        $stmt->execute([$username]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Увеличение счетчика неудачных попыток входа
     */
    private function incrementFailedAttempts($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1,
                account_locked_until = CASE 
                    WHEN failed_login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE account_locked_until
                END
            WHERE id = ?
        ");
        $stmt->execute([MAX_LOGIN_ATTEMPTS, $userId]);
    }
    
    /**
     * Сброс счетчика неудачных попыток
     */
    private function resetFailedAttempts($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_login_attempts = 0, account_locked_until = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    /**
     * Обновление времени последнего входа
     */
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    /**
     * Логирование активности пользователя
     */
    public function logUserActivity($userId, $action, $description, $relatedTable = null, $relatedId = null) {
        $stmt = $this->db->prepare("
            INSERT INTO user_activity_logs (user_id, action, description, ip_address, user_agent, related_table, related_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $relatedTable,
            $relatedId
        ]);
    }
}

// Класс для управления заседаниями
class MeetingManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Создание нового заседания
     */
    public function createMeeting($meetingData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO meetings (title, description, meeting_type, start_datetime, end_datetime, 
                                    location, is_remote, quorum_required, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $meetingData['title'],
                $meetingData['description'],
                $meetingData['meeting_type'],
                $meetingData['start_datetime'],
                $meetingData['end_datetime'] ?? null,
                $meetingData['location'] ?? null,
                $meetingData['is_remote'] ?? false,
                $meetingData['quorum_required'] ?? 226,
                $meetingData['created_by']
            ]);
            
            $meetingId = $this->db->lastInsertId();
            
            // Логируем создание заседания
            $userManager = new UserManager();
            $userManager->logUserActivity(
                $meetingData['created_by'], 
                'meeting_created', 
                'Создано новое заседание: ' . $meetingData['title'],
                'meetings',
                $meetingId
            );
            
            return ['success' => true, 'meeting_id' => $meetingId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка при создании заседания: ' . $e->getMessage()];
        }
    }
    
    /**
     * Получение списка заседаний
     */
    public function getMeetings($status = null, $limit = 50, $offset = 0) {
        $sql = "
            SELECT m.*, u.full_name as created_by_name,
                   COUNT(mp.id) as participants_count
            FROM meetings m
            LEFT JOIN users u ON m.created_by = u.id
            LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
        ";
        
        $params = [];
        if ($status) {
            $sql .= " WHERE m.status = ?";
            $params[] = $status;
        }
        
        $sql .= " GROUP BY m.id ORDER BY m.start_datetime DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Получение заседания по ID
     */
    public function getMeetingById($meetingId) {
        $stmt = $this->db->prepare("
            SELECT m.*, u.full_name as created_by_name
            FROM meetings m
            LEFT JOIN users u ON m.created_by = u.id
            WHERE m.id = ?
        ");
        $stmt->execute([$meetingId]);
        return $stmt->fetch();
    }
    
    /**
     * Добавление участника к заседанию
     */
    public function addParticipant($meetingId, $userId, $participantType = 'deputy', $votingRights = true) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO meeting_participants (meeting_id, user_id, participant_type, voting_rights)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE participant_type = VALUES(participant_type), voting_rights = VALUES(voting_rights)
            ");
            
            $stmt->execute([$meetingId, $userId, $participantType, $votingRights]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка при добавлении участника: ' . $e->getMessage()];
        }
    }
    
    /**
     * Регистрация присутствия участника
     */
    public function markAttendance($meetingId, $userId, $status = 'present') {
        try {
            $stmt = $this->db->prepare("
                UPDATE meeting_participants 
                SET attendance_status = ?,
                    check_in_time = CASE WHEN ? = 'present' THEN NOW() ELSE check_in_time END
                WHERE meeting_id = ? AND user_id = ?
            ");
            
            $stmt->execute([$status, $status, $meetingId, $userId]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка при отметке присутствия: ' . $e->getMessage()];
        }
    }
    
    /**
     * Проверка кворума заседания
     */
    public function checkQuorum($meetingId) {
        $stmt = $this->db->prepare("
            SELECT m.quorum_required,
                   COUNT(CASE WHEN mp.attendance_status = 'present' AND mp.voting_rights = 1 THEN 1 END) as present_count
            FROM meetings m
            LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
            WHERE m.id = ?
            GROUP BY m.id
        ");
        $stmt->execute([$meetingId]);
        $result = $stmt->fetch();
        
        if ($result) {
            return [
                'has_quorum' => $result['present_count'] >= $result['quorum_required'],
                'present_count' => $result['present_count'],
                'required_count' => $result['quorum_required']
            ];
        }
        
        return ['has_quorum' => false, 'present_count' => 0, 'required_count' => 0];
    }
}

// Класс для управления голосованиями
class VotingManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Регистрация голоса
     */
    public function castVote($agendaItemId, $userId, $voteValue, $electronicSignature = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO votes (agenda_item_id, user_id, vote_value, ip_address, user_agent, electronic_signature_hash)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    vote_value = VALUES(vote_value),
                    vote_time = NOW(),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent),
                    electronic_signature_hash = VALUES(electronic_signature_hash)
            ");
            
            $stmt->execute([
                $agendaItemId,
                $userId,
                $voteValue,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $electronicSignature
            ]);
            
            // Логируем голосование
            $userManager = new UserManager();
            $userManager->logUserActivity(
                $userId, 
                'vote_cast', 
                "Проголосовал: {$voteValue}",
                'votes',
                $agendaItemId
            );
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка при голосовании: ' . $e->getMessage()];
        }
    }
    
    /**
     * Получение результатов голосования
     */
    public function getVotingResults($agendaItemId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(CASE WHEN vote_value = 'for' THEN 1 END) as votes_for,
                COUNT(CASE WHEN vote_value = 'against' THEN 1 END) as votes_against,
                COUNT(CASE WHEN vote_value = 'abstain' THEN 1 END) as votes_abstain,
                COUNT(*) as total_votes
            FROM votes 
            WHERE agenda_item_id = ?
        ");
        $stmt->execute([$agendaItemId]);
        return $stmt->fetch();
    }
}

// Функции для работы с сессиями
function startSecureSession() {
    session_start();
    
    // Проверяем время жизни сессии
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function hasRole($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $roleHierarchy = ['observer' => 1, 'deputy' => 2, 'vice_chairman' => 3, 'chairman' => 4, 'admin' => 5];
    $userRole = $_SESSION['user_role'] ?? 'observer';
    
    return $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
}

// Функция для безопасного вывода данных
function escape($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Функция для генерации CSRF токена
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token