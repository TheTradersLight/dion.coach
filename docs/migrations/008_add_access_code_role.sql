-- ============================================================
-- Access code role (station/admin)
-- ============================================================

ALTER TABLE camp_access_codes
    ADD COLUMN role ENUM('station', 'admin') NOT NULL DEFAULT 'station';
