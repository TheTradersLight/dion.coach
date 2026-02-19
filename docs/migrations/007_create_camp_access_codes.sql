-- ============================================================
-- Access codes for evaluator (passwordless) access
-- ============================================================

CREATE TABLE IF NOT EXISTS camp_access_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NOT NULL,
    test_type_id INT NULL,
    user_id INT unsigned NOT NULL,
    code_hash CHAR(64) NOT NULL,
    status ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
    expires_at DATETIME NULL,
    created_by INT unsigned NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cac_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_cac_test_type FOREIGN KEY (test_type_id) REFERENCES test_types(id) ON DELETE SET NULL,
    CONSTRAINT fk_cac_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_cac_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY uq_cac_hash (camp_id, code_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS camp_access_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    access_code_id INT NOT NULL,
    camp_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    CONSTRAINT fk_cat_code FOREIGN KEY (access_code_id) REFERENCES camp_access_codes(id) ON DELETE CASCADE,
    CONSTRAINT fk_cat_camp FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE,
    UNIQUE KEY uq_cat_hash (camp_id, token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
