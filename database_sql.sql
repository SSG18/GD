-- Создание базы данных с правильной кодировкой UTF-8 для русских символов
-- Подключение: sql305.infinityfree.com
-- База данных: if0_38379964_kongress
-- Пользователь: if0_38379964
-- Пароль: PkVpEd9XRo1b

-- Установка кодировки для сессии
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1. Таблица пользователей
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    full_name VARCHAR(200) NOT NULL,
    role ENUM('observer', 'deputy', 'vice_chairman', 'chairman', 'admin') NOT NULL DEFAULT 'observer',
    status ENUM('active', 'inactive', 'blocked') NOT NULL DEFAULT 'active',
    phone VARCHAR(20),
    department VARCHAR(200),
    position_title VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    password_reset_token VARCHAR(255) NULL,
    password_reset_expires TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    account_locked_until TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Таблица заседаний
CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(300) NOT NULL,
    description TEXT,
    meeting_type ENUM('regular', 'extraordinary', 'committee', 'working_group') NOT NULL DEFAULT 'regular',
    status ENUM('planned', 'active', 'voting', 'closed', 'cancelled') NOT NULL DEFAULT 'planned',
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME,
    location VARCHAR(200),
    is_remote BOOLEAN DEFAULT FALSE,
    quorum_required INT DEFAULT 226, -- Больше половины от 450 депутатов
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Таблица вопросов повестки дня
CREATE TABLE IF NOT EXISTS agenda_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    order_number INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    item_type ENUM('information', 'discussion', 'voting', 'decision') NOT NULL DEFAULT 'discussion',
    voting_type ENUM('simple_majority', 'qualified_majority', 'two_thirds', 'unanimous') DEFAULT 'simple_majority',
    status ENUM('pending', 'active', 'voting', 'completed', 'postponed') NOT NULL DEFAULT 'pending',
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    votes_required INT NULL, -- Количество голосов для принятия решения
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Таблица участников заседаний
CREATE TABLE IF NOT EXISTS meeting_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    user_id INT NOT NULL,
    participant_type ENUM('deputy', 'invited', 'observer') NOT NULL DEFAULT 'deputy',
    attendance_status ENUM('registered', 'present', 'absent', 'excused') NOT NULL DEFAULT 'registered',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    voting_rights BOOLEAN DEFAULT TRUE,
    UNIQUE KEY unique_participant (meeting_id, user_id),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Таблица голосований
CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agenda_item_id INT NOT NULL,
    user_id INT NOT NULL,
    vote_value ENUM('for', 'against', 'abstain') NOT NULL,
    vote_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    electronic_signature_hash VARCHAR(512),
    is_paper_vote BOOLEAN DEFAULT FALSE,
    paper_vote_comment TEXT,
    UNIQUE KEY unique_vote (agenda_item_id, user_id),
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Таблица документов
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT,
    agenda_item_id INT,
    title VARCHAR(300) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    document_type ENUM('agenda', 'protocol', 'draft_law', 'amendment', 'presentation', 'other') NOT NULL DEFAULT 'other',
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_public BOOLEAN DEFAULT TRUE,
    access_level ENUM('public', 'deputies_only', 'restricted') DEFAULT 'deputies_only',
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Таблица протоколов заседаний
CREATE TABLE IF NOT EXISTS protocols (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL UNIQUE,
    protocol_number VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(300) NOT NULL,
    content LONGTEXT NOT NULL,
    status ENUM('draft', 'published', 'approved') NOT NULL DEFAULT 'draft',
    created_by INT NOT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Таблица чата заседаний
CREATE TABLE IF NOT EXISTS meeting_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'system', 'file') NOT NULL DEFAULT 'text',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    edited_at TIMESTAMP NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Таблица уведомлений
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('meeting_started', 'meeting_published', 'voting_started', 'voting_reminder', 'protocol_published', 'system') NOT NULL,
    related_meeting_id INT NULL,
    related_agenda_item_id INT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (related_agenda_item_id) REFERENCES agenda_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Таблица системных настроек
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') NOT NULL DEFAULT 'string',
    description TEXT,
    updated_by INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Таблица логов действий пользователей
CREATE TABLE IF NOT EXISTS user_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    related_table VARCHAR(50),
    related_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Таблица сессий пользователей
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание индексов для оптимизации производительности
CREATE INDEX idx_meetings_status ON meetings(status);
CREATE INDEX idx_meetings_start_datetime ON meetings(start_datetime);
CREATE INDEX idx_agenda_items_meeting_id ON agenda_items(meeting_id);
CREATE INDEX idx_votes_agenda_item_id ON votes(agenda_item_id);
CREATE INDEX idx_votes_user_id ON votes(user_id);
CREATE INDEX idx_documents_meeting_id ON documents(meeting_id);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_is_read ON notifications(is_read);
CREATE INDEX idx_user_activity_logs_user_id ON user_activity_logs(user_id);
CREATE INDEX idx_user_activity_logs_created_at ON user_activity_logs(created_at);
CREATE INDEX idx_user_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_user_sessions_expires_at ON user_sessions(expires_at);

-- Вставка начальных данных

-- Вставка системных настроек
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, updated_by) VALUES
('system_name', 'Государственная дума РФ', 'string', 'Название системы', 1),
('quorum_percentage', '50', 'integer', 'Процент кворума от общего числа депутатов', 1),
('total_deputies', '450', 'integer', 'Общее количество депутатов', 1),
('voting_timeout_minutes', '30', 'integer', 'Время на голосование в минутах', 1),
('session_timeout_hours', '8', 'integer', 'Время жизни сессии в часах', 1),
('max_login_attempts', '5', 'integer', 'Максимальное количество попыток входа', 1),
('password_min_length', '8', 'integer', 'Минимальная длина пароля', 1),
('email_notifications_enabled', 'true', 'boolean', 'Включены ли email уведомления', 1);

-- Создание пользователя-администратора по умолчанию
-- Пароль: admin123 (в реальной системе должен быть более сложный и захеширован)
INSERT INTO users (username, password_hash, email, full_name, role, status, department, position_title) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@duma.gov.ru', 'Системный администратор', 'admin', 'active', 'IT отдел', 'Системный администратор'),
('chairman', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'chairman@duma.gov.ru', 'Иванов Иван Иванович', 'chairman', 'active', 'Аппарат Государственной думы', 'Председатель Государственной думы'),
('vice_chairman1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vice1@duma.gov.ru', 'Петров Петр Петрович', 'vice_chairman', 'active', 'Аппарат Государственной думы', 'Первый заместитель председателя'),
('deputy001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'deputy001@duma.gov.ru', 'Сидоров Алексей Михайлович', 'deputy', 'active', 'Комитет по бюджету и налогам', 'Депутат Государственной думы'),
('observer001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'observer@duma.gov.ru', 'Козлов Николай Сергеевич', 'observer', 'active', 'Пресс-служба', 'Наблюдатель');

-- Создание тестового заседания
INSERT INTO meetings (title, description, meeting_type, status, start_datetime, end_datetime, location, quorum_required, created_by) VALUES
('Пленарное заседание Государственной думы', 'Рассмотрение федерального бюджета на 2025 год и плановый период 2026 и 2027 годов', 'regular', 'planned', '2025-06-15 10:00:00', '2025-06-15 18:00:00', 'Зал заседаний Государственной думы', 226, 2),
('Заседание Комитета по бюджету и налогам', 'Предварительное рассмотрение поправок к бюджету', 'committee', 'planned', '2025-06-13 14:00:00', '2025-06-13 17:00:00', 'Комитетский зал №1', 15, 2);

-- Создание вопросов повестки дня
INSERT INTO agenda_items (meeting_id, order_number, title, description, item_type, voting_type, votes_required) VALUES
(1, 1, 'О федеральном бюджете на 2025 год', 'Рассмотрение в первом чтении федерального закона о федеральном бюджете на 2025 год и плановый период 2026 и 2027 годов', 'voting', 'simple_majority', 226),
(1, 2, 'О внесении изменений в Налоговый кодекс РФ', 'Рассмотрение законопроекта о налоговых льготах для малого и среднего бизнеса', 'voting', 'simple_majority', 226),
(1, 3, 'Информация о ходе реализации национальных проектов', 'Доклад Правительства РФ о ходе реализации национальных проектов', 'information', NULL, NULL),
(2, 1, 'Анализ поступлений в федеральный бюджет', 'Рассмотрение отчета о поступлениях в федеральный бюджет за первое полугодие 2025 года', 'discussion', NULL, NULL);

-- Добавление участников заседания
INSERT INTO meeting_participants (meeting_id, user_id, participant_type, voting_rights) VALUES
(1, 2, 'deputy', TRUE),  -- Председатель
(1, 3, 'deputy', TRUE),  -- Зам. председателя
(1, 4, 'deputy', TRUE),  -- Депутат
(1, 5, 'observer', FALSE), -- Наблюдатель
(2, 2, 'deputy', TRUE),
(2, 4, 'deputy', TRUE);

-- Создание представления для удобного просмотра активных заседаний
CREATE VIEW active_meetings AS
SELECT 
    m.id,
    m.title,
    m.meeting_type,
    m.status,
    m.start_datetime,
    m.end_datetime,
    m.location,
    m.quorum_required,
    COUNT(mp.id) as registered_participants,
    COUNT(CASE WHEN mp.attendance_status = 'present' THEN 1 END) as present_participants,
    u.full_name as created_by_name
FROM meetings m
LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
LEFT JOIN users u ON m.created_by = u.id
WHERE m.status IN ('planned', 'active', 'voting')
GROUP BY m.id
ORDER BY m.start_datetime;

-- Создание представления для статистики голосований
CREATE VIEW voting_statistics AS
SELECT 
    ai.id as agenda_item_id,
    ai.title as agenda_title,
    m.title as meeting_title,
    COUNT(v.id) as total_votes,
    COUNT(CASE WHEN v.vote_value = 'for' THEN 1 END) as votes_for,
    COUNT(CASE WHEN v.vote_value = 'against' THEN 1 END) as votes_against,
    COUNT(CASE WHEN v.vote_value = 'abstain' THEN 1 END) as votes_abstain,
    ai.votes_required,
    CASE 
        WHEN COUNT(CASE WHEN v.vote_value = 'for' THEN 1 END) >= ai.votes_required THEN 'Принято'
        WHEN ai.status = 'completed' THEN 'Отклонено'
        ELSE 'В процессе'
    END as decision_status
FROM agenda_items ai
LEFT JOIN votes v ON ai.id = v.agenda_item_id
LEFT JOIN meetings m ON ai.meeting_id = m.id
WHERE ai.item_type = 'voting'
GROUP BY ai.id
ORDER BY m.start_datetime, ai.order_number;

-- Создание функции для проверки кворума
DELIMITER //
CREATE FUNCTION check_quorum(meeting_id INT) 
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE present_count INT;
    DECLARE required_quorum INT;
    
    SELECT COUNT(*) INTO present_count
    FROM meeting_participants 
    WHERE meeting_id = meeting_id 
    AND attendance_status = 'present' 
    AND voting_rights = TRUE;
    
    SELECT quorum_required INTO required_quorum
    FROM meetings 
    WHERE id = meeting_id;
    
    RETURN present_count >= required_quorum;
END //
DELIMITER ;

-- Создание триггера для автоматического обновления статуса заседания
DELIMITER //
CREATE TRIGGER update_meeting_status 
AFTER UPDATE ON meeting_participants
FOR EACH ROW
BEGIN
    DECLARE present_count INT;
    DECLARE required_quorum INT;
    DECLARE meeting_status VARCHAR(20);
    
    -- Получаем текущий статус заседания
    SELECT status INTO meeting_status FROM meetings WHERE id = NEW.meeting_id;
    
    -- Проверяем кворум только для активных заседаний
    IF meeting_status = 'active' THEN
        SELECT COUNT(*) INTO present_count
        FROM meeting_participants 
        WHERE meeting_id = NEW.meeting_id 
        AND attendance_status = 'present' 
        AND voting_rights = TRUE;
        
        SELECT quorum_required INTO required_quorum
        FROM meetings 
        WHERE id = NEW.meeting_id;
        
        -- Если кворум набран, можно начинать голосование
        IF present_count >= required_quorum THEN
            UPDATE meetings 
            SET status = 'voting' 
            WHERE id = NEW.meeting_id AND status = 'active';
        END IF;
    END IF;
END //
DELIMITER ;

-- Создание хранимой процедуры для завершения голосования по вопросу
DELIMITER //
CREATE PROCEDURE complete_agenda_item_voting(IN item_id INT)
BEGIN
    DECLARE votes_for INT;
    DECLARE votes_required INT;
    DECLARE meeting_id INT;
    
    -- Подсчитываем голоса "за"
    SELECT COUNT(*) INTO votes_for
    FROM votes 
    WHERE agenda_item_id = item_id AND vote_value = 'for';
    
    -- Получаем необходимое количество голосов
    SELECT ai.votes_required, ai.meeting_id INTO votes_required, meeting_id
    FROM agenda_items ai
    WHERE ai.id = item_id;
    
    -- Обновляем статус вопроса
    UPDATE agenda_items 
    SET status = 'completed', end_time = NOW()
    WHERE id = item_id;
    
    -- Добавляем запись в лог
    INSERT INTO user_activity_logs (user_id, action, description, related_table, related_id)
    VALUES (1, 'voting_completed', 
            CONCAT('Завершено голосование по вопросу. Голосов "за": ', votes_for, ' из ', votes_required, ' необходимых'),
            'agenda_items', item_id);
            
END //
DELIMITER ;