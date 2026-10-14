-- 느린 우체통 초기 스키마
-- 규칙: 시각은 모두 UTC DATETIME, 상태는 VARCHAR (ENUM 변경 ALTER 회피)
--       적용된 파일은 수정하지 않고 새 번호로 추가한다 (docs/03-architecture.md §7)

CREATE TABLE letters (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id             CHAR(32)        NOT NULL,
    recipient_email_enc   VARBINARY(512)  NULL,
    recipient_email_hash  BINARY(32)      NOT NULL,
    sender_email_enc      VARBINARY(512)  NULL,
    sender_email_hash     BINARY(32)      NULL,
    title_enc             VARBINARY(1024) NULL,
    body_enc              MEDIUMBLOB      NULL,
    body_format           VARCHAR(10)     NOT NULL DEFAULT 'text',
    key_version           SMALLINT UNSIGNED NOT NULL,
    deliver_on            DATE            NOT NULL,
    deliver_at            DATETIME        NOT NULL,
    status                VARCHAR(20)     NOT NULL,
    verify_token_hash     BINARY(32)      NULL,
    verify_expires_at     DATETIME        NULL,
    sealed_at             DATETIME        NULL,
    attempts              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at       DATETIME        NULL,
    locked_by             CHAR(16)        NULL,
    locked_at             DATETIME        NULL,
    last_error            VARCHAR(500)    NULL,
    delivered_at          DATETIME        NULL,
    purged_at             DATETIME        NULL,
    created_ip_hash       BINARY(32)      NOT NULL,
    created_at            DATETIME        NOT NULL,
    updated_at            DATETIME        NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_letters_public_id (public_id),
    UNIQUE KEY uq_letters_verify_token (verify_token_hash),
    KEY idx_letters_status_deliver (status, deliver_at),
    KEY idx_letters_recipient_status (recipient_email_hash, status),
    KEY idx_letters_status_created (status, created_at),
    KEY idx_letters_locked_by (locked_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    letter_id   BIGINT UNSIGNED NOT NULL,
    kind        VARCHAR(10)     NOT NULL,
    driver      VARCHAR(20)     NOT NULL,
    success     TINYINT(1)      NOT NULL,
    message_id  VARCHAR(255)    NULL,
    error       VARCHAR(1000)   NULL,
    created_at  DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_mail_log_letter (letter_id),
    CONSTRAINT fk_mail_log_letter FOREIGN KEY (letter_id) REFERENCES letters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    bucket        BINARY(32)   NOT NULL,
    window_start  DATETIME     NOT NULL,
    hits          INT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket, window_start),
    KEY idx_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
