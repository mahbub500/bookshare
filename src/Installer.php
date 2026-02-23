<?php
namespace BookShare;

/**
 * Handles plugin activation: creates all database tables.
 */
class Installer {

    public static function activate(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // ── Central book catalog ──────────────────────────────────────
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bs_books (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            unique_code   VARCHAR(20)     NOT NULL UNIQUE,
            title         VARCHAR(255)    NOT NULL,
            author        VARCHAR(255)    NOT NULL,
            publisher     VARCHAR(255)    DEFAULT '',
            isbn          VARCHAR(20)     DEFAULT '',
            cover_url     TEXT            DEFAULT '',
            description   TEXT            DEFAULT '',
            genre         VARCHAR(100)    DEFAULT '',
            created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_unique_code (unique_code)
        ) $charset;" );

        // ── User libraries ────────────────────────────────────────────
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bs_user_library (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            book_id     BIGINT UNSIGNED NOT NULL,
            is_public   TINYINT(1)      NOT NULL DEFAULT 1,
            available   TINYINT(1)      NOT NULL DEFAULT 1,
            added_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_book (user_id, book_id),
            KEY idx_user   (user_id),
            KEY idx_book   (book_id),
            KEY idx_public (is_public)
        ) $charset;" );

        // ── Rental requests ───────────────────────────────────────────
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bs_rentals (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            book_id         BIGINT UNSIGNED NOT NULL,
            owner_id        BIGINT UNSIGNED NOT NULL,
            requester_id    BIGINT UNSIGNED NOT NULL,
            status          ENUM('pending','approved','returned','rejected') NOT NULL DEFAULT 'pending',
            requested_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approved_at     DATETIME DEFAULT NULL,
            returned_at     DATETIME DEFAULT NULL,
            message         TEXT DEFAULT '',
            PRIMARY KEY (id),
            KEY idx_owner     (owner_id),
            KEY idx_requester (requester_id),
            KEY idx_status    (status)
        ) $charset;" );

        update_option( 'bookshare_db_version', BOOKSHARE_VERSION );
    }

    public static function deactivate(): void {
        // Tables are kept on deactivation; only removed on uninstall.
    }
}