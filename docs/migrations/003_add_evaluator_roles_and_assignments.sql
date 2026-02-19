ALTER TABLE camp_evaluators
    ADD COLUMN role ENUM('manager', 'evaluator', 'super_evaluator') NOT NULL DEFAULT 'evaluator';

CREATE TABLE IF NOT EXISTS camp_evaluator_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NOT NULL,
    camp_evaluator_id INT NOT NULL,
    group_id INT NOT NULL,
    CONSTRAINT fk_ceg_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_ceg_evaluator FOREIGN KEY (camp_evaluator_id) REFERENCES camp_evaluators(id) ON DELETE CASCADE,
    CONSTRAINT fk_ceg_group FOREIGN KEY (group_id) REFERENCES camp_groups(id) ON DELETE CASCADE,
    UNIQUE KEY uq_ceg (camp_evaluator_id, group_id),
    INDEX idx_ceg_camp (camp_id),
    INDEX idx_ceg_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS camp_evaluator_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NOT NULL,
    camp_evaluator_id INT NOT NULL,
    session_id INT NOT NULL,
    CONSTRAINT fk_ces_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_ces_evaluator FOREIGN KEY (camp_evaluator_id) REFERENCES camp_evaluators(id) ON DELETE CASCADE,
    CONSTRAINT fk_ces_session FOREIGN KEY (session_id) REFERENCES camp_sessions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_ces (camp_evaluator_id, session_id),
    INDEX idx_ces_camp (camp_id),
    INDEX idx_ces_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
