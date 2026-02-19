-- ============================================================
-- Test Physique - Champs fixes (prise de mesure)
-- ============================================================

INSERT INTO test_types (camp_id, name, description, sort_order, is_active)
SELECT NULL, 'Prise de mesure', 'Champs fixes pour profil physique', 5, 1
WHERE NOT EXISTS (
    SELECT 1 FROM test_types WHERE camp_id IS NULL AND name = 'Prise de mesure'
);

INSERT INTO test_metrics (test_type_id, name, label, unit, value_type, calc_rule, is_output, sort_order)
SELECT tt.id, m.name, m.label, m.unit, m.value_type, m.calc_rule, m.is_output, m.sort_order
FROM test_types tt
JOIN (
    SELECT 'Prise de mesure' AS test_name, 'sex' AS name, 'Sexe' AS label, 'text' AS unit, 'text' AS value_type, 'none' AS calc_rule, 0 AS is_output, 10 AS sort_order
    UNION ALL
    SELECT 'Prise de mesure', 'age', 'Age', 'ans', 'integer', 'none', 0, 20
    UNION ALL
    SELECT 'Prise de mesure', 'dominant_hand', 'Main dominante', 'text', 'text', 'none', 0, 30
    UNION ALL
    SELECT 'Prise de mesure', 'height_cm', 'Grandeur', 'cm', 'number', 'none', 0, 40
    UNION ALL
    SELECT 'Prise de mesure', 'vertical_reach_cm', 'Amplitude verticale', 'cm', 'number', 'none', 0, 50
    UNION ALL
    SELECT 'Prise de mesure', 'horizontal_reach_cm', 'Amplitude horizontale', 'cm', 'number', 'none', 0, 60
) m ON m.test_name = tt.name
WHERE tt.camp_id IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM test_metrics tm
      WHERE tm.test_type_id = tt.id
        AND tm.name = m.name
  );
