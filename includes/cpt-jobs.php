<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_post_type('job', [
        'labels' => [
            'name'               => __('Jobs', 'nanny'),
            'singular_name'      => __('Job', 'nanny'),
            'add_new'            => __('Add Job', 'nanny'),
            'add_new_item'       => __('Add New Job', 'nanny'),
            'edit_item'          => __('Edit Job', 'nanny'),
            'new_item'           => __('New Job', 'nanny'),
            'view_item'          => __('View Job', 'nanny'),
            'search_items'       => __('Search Jobs', 'nanny'),
            'not_found'          => __('No jobs found', 'nanny'),
            'not_found_in_trash' => __('No jobs found in Trash', 'nanny'),
            'all_items'          => __('All Jobs', 'nanny'),
            'menu_name'          => __('Careers', 'nanny'),
        ],

        'public'              => true,
        'has_archive'         => true,
        'rewrite'             => ['slug' => 'careers', 'with_front' => false],

        'supports'            => ['title', 'editor', 'excerpt'],
        'show_in_rest'        => true,

        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'menu_position'       => 20,
        'menu_icon'           => 'dashicons-id',
    ]);

    register_taxonomy('job_location', ['job'], [
        'labels' => [
            'name'          => __('Locations', 'nanny'),
            'singular_name' => __('Location', 'nanny'),
            'menu_name'     => __('Locations', 'nanny'),
        ],
        'public'             => false,
        'show_ui'            => true,
        'show_admin_column'  => true,
        'show_in_rest'       => true,
        'hierarchical'       => true,

        'rewrite'            => false,
        'query_var'          => false,
    ]);

    register_taxonomy('job_type', ['job'], [
        'labels' => [
            'name'          => __('Job Types', 'nanny'),
            'singular_name' => __('Job Type', 'nanny'),
            'menu_name'     => __('Job Types', 'nanny'),
        ],
        'public'             => false,
        'show_ui'            => true,
        'show_admin_column'  => true,
        'show_in_rest'       => true,
        'hierarchical'       => false,

        'rewrite'            => false,
        'query_var'          => false,
    ]);
}, 0);

/**
 * Flush rewrite rules after theme switch (so /careers/ works immediately)
 */
add_action('after_switch_theme', function () {
    flush_rewrite_rules();
});