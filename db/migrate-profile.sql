ALTER TABLE users MODIFY username VARCHAR(254) NOT NULL;
ALTER TABLE users ADD COLUMN email VARCHAR(254) NULL AFTER username, ADD UNIQUE KEY uq_users_email (email), ADD COLUMN full_name VARCHAR(120) NOT NULL DEFAULT '' AFTER email, ADD COLUMN profile_image CHAR(36) NULL AFTER password_hash;
