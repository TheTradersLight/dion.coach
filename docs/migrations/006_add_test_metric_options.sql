-- ============================================================
-- Test Physique - Valeurs controlees pour certaines metriques
-- ============================================================

CREATE TABLE IF NOT EXISTS test_metric_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_id INT NOT NULL,
    value VARCHAR(100) NOT NULL,
    label VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_tmo_metric FOREIGN KEY (metric_id) REFERENCES test_metrics(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tmo_metric_value (metric_id, value),
    INDEX idx_tmo_metric (metric_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO test_metric_options (metric_id, value, label, sort_order, is_active)
SELECT tm.id, o.value, o.label, o.sort_order, 1
FROM test_metrics tm
JOIN test_types tt ON tt.id = tm.test_type_id
JOIN (
    SELECT 'Prise de mesure' AS test_name, 'sex' AS metric_name, 'M' AS value, 'M' AS label, 10 AS sort_order
    UNION ALL
    SELECT 'Prise de mesure', 'sex', 'F', 'F', 20
    UNION ALL
    SELECT 'Prise de mesure', 'sex', 'X', 'X', 30

    UNION ALL
    SELECT 'Prise de mesure', 'dominant_hand', 'D', 'Droite', 10
    UNION ALL
    SELECT 'Prise de mesure', 'dominant_hand', 'G', 'Gauche', 20
    UNION ALL
    SELECT 'Prise de mesure', 'dominant_hand', 'A', 'Ambidextre', 30
) o ON o.test_name = tt.name AND o.metric_name = tm.name
WHERE tt.camp_id IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM test_metric_options tmo
      WHERE tmo.metric_id = tm.id
        AND tmo.value = o.value
  );
