<?php
function email_verification_schema(): array {return ["CREATE TABLE IF NOT EXISTS welcome_email_verifications (id VARCHAR(64) PRIMARY KEY, email VARCHAR(254), token_hash VARCHAR(64) UNIQUE, expires_at VARCHAR(19), sent_at VARCHAR(19), verified_at VARCHAR(19), window_start VARCHAR(19) NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, created_at VARCHAR(19) NOT NULL)"];}
function email_verification_migrate_up(): void {
    if(db()->inTransaction())throw new LogicException('Migrate outside transactions.');
    foreach(email_verification_schema() as $sql)db()->exec($sql.(env('DB_DRIVER')==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
}
function email_verification_migrate_down(): void {
    if(!email_verification_ready())return;
    if((int)query('SELECT COUNT(*) FROM welcome_email_verifications')->fetchColumn())throw new DomainException('Email verification history exists. Preserve it instead of rolling back.');
    query('DROP TABLE welcome_email_verifications');
}
