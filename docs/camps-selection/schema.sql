-- ============================================================
-- Module Camps de Selection - Schema de base de donnees
-- Compatible MySQL 8.0+ / Cloud SQL
-- ============================================================

-- 1. Camps
CREATE TABLE camps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    sport VARCHAR(100) NOT NULL DEFAULT '',
    season VARCHAR(50) NOT NULL DEFAULT '',
    status ENUM('draft', 'active', 'completed', 'archived') NOT NULL DEFAULT 'draft',
    eval_mode ENUM('cumulative', 'independent') NOT NULL DEFAULT 'cumulative',
    rating_min INT NOT NULL DEFAULT 1,
    rating_max INT NOT NULL DEFAULT 5,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_camps_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Liens entre camps (pour transferts)
CREATE TABLE camp_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_source_id INT NOT NULL,
    camp_target_id INT NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_camp_links_source FOREIGN KEY (camp_source_id) REFERENCES camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_camp_links_target FOREIGN KEY (camp_target_id) REFERENCES camps(id) ON DELETE CASCADE,
    UNIQUE KEY uq_camp_link (camp_source_id, camp_target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Evaluateurs invites par camp
CREATE TABLE camp_evaluators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NOT NULL,
    user_id INT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('invited', 'active', 'revoked') NOT NULL DEFAULT 'invited',
    invited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME NULL,
    CONSTRAINT fk_camp_evaluators_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_camp_evaluators_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_camp_evaluator (camp_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Seances d'evaluation
CREATE TABLE camp_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    session_date DATE NULL,
    session_order INT NOT NULL DEFAULT 1,
    status ENUM('planned', 'in_progress', 'completed') NOT NULL DEFAULT 'planned',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Joueurs (entite globale, reutilisable entre camps)
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NULL,
    jersey_number VARCHAR(10) NULL,
    position VARCHAR(50) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Inscription joueur-camp
CREATE TABLE camp_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NOT NULL,
    player_id INT NOT NULL,
    status ENUM('active', 'cut', 'transferred') NOT NULL DEFAULT 'active',
    transferred_to_camp_id INT NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_camp_players_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_camp_players_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_camp_players_transfer FOREIGN KEY (transferred_to_camp_id) REFERENCES camps(id) ON DELETE SET NULL,
    UNIQUE KEY uq_camp_player (camp_id, player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Groupes dans un camp
CREATE TABLE camp_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_groups_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Joueurs dans un groupe
CREATE TABLE group_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    camp_player_id INT NOT NULL,
    CONSTRAINT fk_group_players_group FOREIGN KEY (group_id) REFERENCES camp_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_players_camp_player FOREIGN KEY (camp_player_id) REFERENCES camp_players(id) ON DELETE CASCADE,
    UNIQUE KEY uq_group_player (group_id, camp_player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Categories de competences (2 niveaux max)
CREATE TABLE skill_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NOT NULL,
    parent_id INT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_skill_cat_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_skill_cat_parent FOREIGN KEY (parent_id) REFERENCES skill_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Competences (feuilles de l'arbre, sous une categorie)
CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_skills_category FOREIGN KEY (category_id) REFERENCES skill_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Evaluations (notes)
CREATE TABLE evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    camp_player_id INT NOT NULL,
    skill_id INT NOT NULL,
    rating INT NOT NULL,
    comment TEXT NULL,
    evaluated_by INT NOT NULL,
    evaluated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_eval_session FOREIGN KEY (session_id) REFERENCES camp_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_camp_player FOREIGN KEY (camp_player_id) REFERENCES camp_players(id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_user FOREIGN KEY (evaluated_by) REFERENCES users(id),
    UNIQUE KEY uq_evaluation (session_id, camp_player_id, skill_id, evaluated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Index supplementaires pour performance
-- ============================================================
CREATE INDEX idx_camps_status ON camps(status);
CREATE INDEX idx_camps_created_by ON camps(created_by);
CREATE INDEX idx_camp_players_status ON camp_players(status);
CREATE INDEX idx_evaluations_session ON evaluations(session_id);
CREATE INDEX idx_evaluations_player ON evaluations(camp_player_id);
CREATE INDEX idx_skill_categories_camp ON skill_categories(camp_id);
CREATE INDEX idx_skill_categories_parent ON skill_categories(parent_id);
