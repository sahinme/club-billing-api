-- All money columns are BIGINT minor units (cents). Never FLOAT/DOUBLE for money.
-- All timestamps are UTC (the app sets time_zone = '+00:00' on every connection).

CREATE TABLE clubs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120)    NOT NULL,
    created_at DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    club_id       BIGINT UNSIGNED NOT NULL,
    email         VARCHAR(254)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    role          VARCHAR(32)     NOT NULL,
    created_at    DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT fk_users_club FOREIGN KEY (club_id) REFERENCES clubs (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- We store only the SHA-256 of each token. A leaked database does not leak usable tokens.
CREATE TABLE access_tokens (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64)        NOT NULL,
    expires_at DATETIME(6)     NOT NULL,
    revoked_at DATETIME(6)     NULL,
    created_at DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_access_tokens_hash (token_hash),
    CONSTRAINT fk_access_tokens_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE invoices (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    club_id      BIGINT UNSIGNED NOT NULL,
    member_id    BIGINT UNSIGNED NOT NULL,
    description  VARCHAR(255)    NOT NULL,
    currency     CHAR(3)         NOT NULL,
    amount_total BIGINT UNSIGNED NOT NULL,
    amount_paid  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status       VARCHAR(16)     NOT NULL DEFAULT 'open',
    created_at   DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at   DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY idx_invoices_club_status (club_id, status),
    CONSTRAINT fk_invoices_club FOREIGN KEY (club_id) REFERENCES clubs (id),
    CONSTRAINT fk_invoices_member FOREIGN KEY (member_id) REFERENCES users (id),
    -- Last line of defence: the database itself refuses an overpaid invoice.
    CONSTRAINT chk_invoices_not_overpaid CHECK (amount_paid <= amount_total),
    CONSTRAINT chk_invoices_status CHECK (status IN ('open', 'paid', 'void'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE payments (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    invoice_id         BIGINT UNSIGNED NOT NULL,
    club_id            BIGINT UNSIGNED NOT NULL,
    amount             BIGINT UNSIGNED NOT NULL,
    currency           CHAR(3)         NOT NULL,
    status             VARCHAR(16)     NOT NULL,
    gateway_reference  VARCHAR(64)     NULL,
    failure_reason     VARCHAR(64)     NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at         DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at         DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY idx_payments_invoice_status (invoice_id, status),
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id),
    CONSTRAINT fk_payments_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id),
    CONSTRAINT chk_payments_amount_positive CHECK (amount > 0),
    CONSTRAINT chk_payments_status CHECK (status IN ('pending', 'succeeded', 'failed'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- One row per (user, Idempotency-Key). The unique key is what makes retries safe:
-- a second request with the same key can never create a second payment.
CREATE TABLE idempotency_keys (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id             BIGINT UNSIGNED NOT NULL,
    idempotency_key     VARCHAR(64)     NOT NULL,
    request_fingerprint CHAR(64)        NOT NULL,
    payment_id          BIGINT UNSIGNED NULL,
    created_at          DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_idempotency_user_key (user_id, idempotency_key),
    CONSTRAINT fk_idempotency_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_idempotency_payment FOREIGN KEY (payment_id) REFERENCES payments (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Transactional outbox: events are written in the same transaction as the business change,
-- and a separate worker (bin/outbox-relay.php) publishes them to Kafka.
CREATE TABLE outbox_messages (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id     CHAR(36)        NOT NULL,
    event_type   VARCHAR(64)     NOT NULL,
    message_key  VARCHAR(64)     NOT NULL,
    payload      JSON            NOT NULL,
    created_at   DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    published_at DATETIME(6)     NULL,
    UNIQUE KEY uq_outbox_event_id (event_id),
    KEY idx_outbox_unpublished (published_at, id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
