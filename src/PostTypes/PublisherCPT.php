<?php
namespace BookShare\PostTypes;

/**
 * Registers the 'bs_publisher' Custom Post Type.
 */
class PublisherCPT {

    const SLUG = 'bs_publisher';

    public function register(): void {
        register_post_type( self::SLUG, [
            'label'              => __( 'Publishers', 'bookshare' ),
            'labels'             => [
                'name'          => __( 'Publishers',        'bookshare' ),
                'singular_name' => __( 'Publisher',         'bookshare' ),
                'add_new_item'  => __( 'Add New Publisher', 'bookshare' ),
                'edit_item'     => __( 'Edit Publisher',    'bookshare' ),
                'menu_name'     => __( 'Publishers',        'bookshare' ),
                'all_items'     => __( 'All Publishers',    'bookshare' ),
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
            'rewrite'            => [ 'slug' => 'book-publishers' ],
            'supports'           => [ 'title', 'editor', 'thumbnail' ],
            'menu_icon'          => 'dashicons-building',
        ] );
    }
}