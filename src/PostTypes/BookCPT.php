<?php
namespace BookShare\PostTypes;

/**
 * Registers the 'bs_book' Custom Post Type.
 * Only admins (manage_options) can create/edit books.
 */
class BookCPT {

    const SLUG = 'bs_book';

    public function register(): void {
        register_post_type( self::SLUG, [
            'label'               => __( 'Books', 'bookshare' ),
            'labels'              => [
                'name'               => __( 'Books',           'bookshare' ),
                'singular_name'      => __( 'Book',            'bookshare' ),
                'add_new_item'       => __( 'Add New Book',    'bookshare' ),
                'edit_item'          => __( 'Edit Book',       'bookshare' ),
                'new_item'           => __( 'New Book',        'bookshare' ),
                'view_item'          => __( 'View Book',       'bookshare' ),
                'search_items'       => __( 'Search Books',    'bookshare' ),
                'not_found'          => __( 'No books found.', 'bookshare' ),
                'menu_name'          => __( 'Books',           'bookshare' ),
                'all_items'          => __( 'All Books',       'bookshare' ),
            ],
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => 'bookshare',      // nested under our custom menu
            'show_in_rest'        => true,
            'capability_type'     => 'post',
            'capabilities'        => [
                'create_posts'       => 'manage_options',  // admin only
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options',
                'delete_posts'       => 'manage_options',
            ],
            'map_meta_cap'        => false,
            'hierarchical'        => false,
            'has_archive'         => true,
            'rewrite'             => [ 'slug' => 'books' ],
            'supports'            => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
            'menu_icon'           => 'dashicons-book-alt',
        ] );
    }
}