-- 자동 테스트용 DB (tests/run.php 가 매번 비우고 사용)
CREATE DATABASE IF NOT EXISTS slowmail_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON slowmail_test.* TO 'slowmail'@'%';
