SET @has_login_attempt_expiry_index := (
 SELECT COUNT(*) FROM information_schema.statistics
 WHERE table_schema=DATABASE() AND table_name='login_attempts' AND index_name='idx_login_attempts_expiry'
);
SET @create_expiry_index := IF(@has_login_attempt_expiry_index=0,
 'ALTER TABLE login_attempts ADD INDEX idx_login_attempts_expiry (attempted_at)',
 'SELECT 1');
PREPARE expiry_index_stmt FROM @create_expiry_index;
EXECUTE expiry_index_stmt;
DEALLOCATE PREPARE expiry_index_stmt;
CREATE TABLE IF NOT EXISTS request_limits(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 scope VARCHAR(32) NOT NULL,
 key_hash CHAR(64) NOT NULL,
 occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(scope,key_hash,occurred_at),
 INDEX(occurred_at)
) ENGINE=InnoDB;
