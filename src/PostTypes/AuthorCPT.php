<?php
namespace BookShare\PostTypes;

/**
 * Registers the 'bs_author' Custom Post Type.
 */
class AuthorCPT {

    const SLUG = 'bs_author';

    public function register(): void {
        register_post_type( self::SLUG, [
            'label'              => __( 'Authors', 'bookshare' ),
            'labels'             => [
                'name'          => __( 'Authors',        'bookshare' ),
                'singular_name' => __( 'Author',         'bookshare' ),
                'add_new_item'  => __( 'Add New Author', 'bookshare' ),
                'edit_item'     => __( 'Edit Author',    'bookshare' ),
                'menu_name'     => __( 'Authors',        'bookshare' ),
                'all_items'     => __( 'All Authors',    'bookshare' ),
            ],
            'public'             => true,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'bookshare',
            'show_in_rest'       => true,
            'capability_type'    => 'post',
            'capabilities'       => [
                'create_posts'       => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options',
                'delete_posts'       => 'manage_options',
            ],
            'map_meta_cap'       => false,
            'hierarchical'       => false,
            'has_archive'        => false,
            'rewrite'            => [ 'slug' => 'book-authors' ],
            'supports'           => [ 'title', 'editor', 'thumbnail' ],
            'menu_icon'          => 'dashicons-admin-users',
        ] );
    }
}