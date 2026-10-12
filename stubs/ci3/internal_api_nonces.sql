CREATE TABLE internal_api_nonces (
    nonce_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX internal_api_nonces_expiry (expires_at)
) ENGINE=InnoDB;

-- Run periodically on the master connection:
-- DELETE FROM internal_api_nonces WHERE expires_at <= UNIX_TIMESTAMP();
