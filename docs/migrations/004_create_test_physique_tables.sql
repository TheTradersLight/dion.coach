-- ============================================================
-- Test Physique - Types de tests, metriques et resultats
-- ============================================================

CREATE TABLE IF NOT EXISTS test_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_test_types_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_type_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    label VARCHAR(255) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    value_type ENUM('number', 'integer', 'text') NOT NULL DEFAULT 'number',
    calc_rule ENUM('none', 'min', 'max') NOT NULL DEFAULT 'none',
    is_output TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_test_metrics_type FOREIGN KEY (test_type_id) REFERENCES test_types(id) ON DELETE CASCADE,
    UNIQUE KEY uq_test_metric (test_type_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NOT NULL,
    player_id INT unsigned NOT NULL,
    test_type_id INT NOT NULL,
    session_id INT NULL,
    metric_id INT NOT NULL,
    value_number DECIMAL(10, 3) NULL,
    value_text VARCHAR(255) NULL,
    created_by INT unsigned NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_test_results_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_test_results_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_test_results_type FOREIGN KEY (test_type_id) REFERENCES test_types(id) ON DELETE CASCADE,
    CONSTRAINT fk_test_results_session FOREIGN KEY (session_id) REFERENCES camp_sessions(id) ON DELETE SET NULL,
    CONSTRAINT fk_test_results_metric FOREIGN KEY (metric_id) REFERENCES test_metrics(id) ON DELETE CASCADE,
    CONSTRAINT fk_test_results_user FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY uq_test_result (camp_id, player_id, test_type_id, session_id, metric_id, created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Donnees de reference (globales) - Test Physique
-- ============================================================

INSERT INTO test_types (camp_id, name, description, sort_order, is_active)
VALUES
    (NULL, 'Sprint 20 metres', '2 tentatives, garder le meilleur temps', 10, 1),
    (NULL, 'Saut hauteur', 'Meilleur resultat avec et sans elan', 20, 1),
    (NULL, 'Relais 60 verges', '2 tentatives, garder le meilleur temps', 30, 1),
    (NULL, 'Saut longueur sans elan', '2 tentatives, garder la meilleure tentative', 40, 1),
    (NULL, 'Parcours 3 cones', '2 tentatives, garder le meilleur temps', 50, 1),
    (NULL, 'Relais 25 verges', '2 tentatives, garder le meilleur temps', 60, 1),
    (NULL, 'Test des Bip', 'Noter le palier obtenu', 70, 1);

INSERT INTO test_metrics (test_type_id, name, label, unit, value_type, calc_rule, is_output, sort_order)
SELECT tt.id, m.name, m.label, m.unit, m.value_type, m.calc_rule, m.is_output, m.sort_order
FROM test_types tt
JOIN (
    SELECT 'Sprint 20 metres' AS test_name, 'attempt_1' AS name, 'Tentative 1' AS label, 's' AS unit, 'number' AS value_type, 'none' AS calc_rule, 0 AS is_output, 10 AS sort_order
    UNION ALL
    SELECT 'Sprint 20 metres', 'attempt_2', 'Tentative 2', 's', 'number', 'none', 0, 20
    UNION ALL
    SELECT 'Sprint 20 metres', 'best_time', 'Meilleur temps', 's', 'number', 'min', 1, 30

    UNION ALL
    SELECT 'Saut hauteur', 'with_approach', 'Avec elan', 'po', 'number', 'max', 0, 10
    UNION ALL
    SELECT 'Saut hauteur', 'without_approach', 'Sans elan', 'po', 'number', 'max', 0, 20

    UNION ALL
    SELECT 'Relais 60 verges', 'attempt_1', 'Tentative 1', 's', 'number', 'none', 0, 10
    UNION ALL
    SELECT 'Relais 60 verges', 'attempt_2', 'Tentative 2', 's', 'number', 'none', 0, 20
    UNION ALL
    SELECT 'Relais 60 verges', 'best_time', 'Meilleur temps', 's', 'number', 'min', 1, 30

    UNION ALL
    SELECT 'Saut longueur sans elan', 'attempt_1', 'Tentative 1', 'cm', 'number', 'none', 0, 10
    UNION ALL
    SELECT 'Saut longueur sans elan', 'attempt_2', 'Tentative 2', 'cm', 'number', 'none', 0, 20
    UNION ALL
    SELECT 'Saut longueur sans elan', 'best_distance', 'Meilleure tentative', 'cm', 'number', 'max', 1, 30

    UNION ALL
    SELECT 'Parcours 3 cones', 'attempt_1', 'Tentative 1', 's', 'number', 'none', 0, 10
    UNION ALL
    SELECT 'Parcours 3 cones', 'attempt_2', 'Tentative 2', 's', 'number', 'none', 0, 20
    UNION ALL
    SELECT 'Parcours 3 cones', 'best_time', 'Meilleur temps', 's', 'number', 'min', 1, 30

    UNION ALL
    SELECT 'Relais 25 verges', 'attempt_1', 'Tentative 1', 's', 'number', 'none', 0, 10
    UNION ALL
    SELECT 'Relais 25 verges', 'attempt_2', 'Tentative 2', 's', 'number', 'none', 0, 20
    UNION ALL
    SELECT 'Relais 25 verges', 'best_time', 'Meilleur temps', 's', 'number', 'min', 1, 30

    UNION ALL
    SELECT 'Test des Bip', 'level', 'Palier', 'palier', 'integer', 'none', 0, 10
) m ON m.test_name = tt.name
WHERE tt.camp_id IS NULL;
