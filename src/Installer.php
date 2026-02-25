<?php
namespace BookShare;

defined( 'ABSPATH' ) || exit;

class Installer {

    public static function run(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Authors table
        dbDelta( "CREATE TABLE {$wpdb->prefix}bs_authors (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name          VARCHAR(200) NOT NULL,
            bio           LONGTEXT,
            email         VARCHAR(200),
            website       VARCHAR(300),
            birth_date    DATE,
            nationality   VARCHAR(100),
            photo_url     VARCHAR(500),
            social_twitter VARCHAR(200),
            social_instagram VARCHAR(200),
            social_facebook  VARCHAR(200),
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name(100))
        ) $charset;" );

        // Publishers table
        dbDelta( "CREATE TABLE {$wpdb->prefix}bs_publishers (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name          VARCHAR(200) NOT NULL,
            description   LONGTEXT,
            email         VARCHAR(200),
            phone         VARCHAR(50),
            website       VARCHAR(300),
            address       TEXT,
            city          VARCHAR(100),
            country       VARCHAR(100),
            founded_year  YEAR,
            logo_url      VARCHAR(500),
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name(100))
        ) $charset;" );

        // Books catalog
        dbDelta( "CREATE TABLE {$wpdb->prefix}bs_books (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            unique_code   VARCHAR(20) NOT NULL,
            title         VARCHAR(300) NOT NULL,
            author_id     BIGINT(20) UNSIGNED,
            publisher_id  BIGINT(20) UNSIGNED,
            genre         VARCHAR(100),
            isbn          VARCHAR(30),
            published_year YEAR,
            description   LONGTEXT,
            cover_url     VARCHAR(500),
            language      VARCHAR(50) DEFAULT 'English',
            pages         SMALLINT UNSIGNED,
            added_by      BIGINT(20) UNSIGNED NOT NULL,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_code (unique_code),
            KEY author_id (author_id),
            KEY publisher_id (publisher_id)
        ) $charset;" );

        // User library
        dbDelta( "CREATE TABLE {$wpdb->prefix}bs_user_library (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    BIGINT(20) UNSIGNED NOT NULL,
            book_id    BIGINT(20) UNSIGNED NOT NULL,
            is_public  TINYINT(1) NOT NULL DEFAULT 1,
            condition_note VARCHAR(200),
            added_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_book (user_id, book_id),
            KEY book_id (book_id)
        ) $charset;" );

        // Rentals
        dbDelta( "CREATE TABLE {$wpdb->prefix}bs_rentals (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            book_id       BIGINT(20) UNSIGNED NOT NULL,
            owner_id      BIGINT(20) UNSIGNED NOT NULL,
            requester_id  BIGINT(20) UNSIGNED NOT NULL,
            status        ENUM('pending','approved','rejected','returned','cancelled') NOT NULL DEFAULT 'pending',
            message       TEXT,
            start_date    DATE,
            end_date      DATE,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY book_id (book_id),
            KEY owner_id (owner_id),
            KEY requester_id (requester_id),
            KEY status (status)
        ) $charset;" );

        update_option( 'bs_db_version', BS_VERSION );

        // Register CPTs so rewrite rules can be flushed
        PostTypes::register_cpts();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
