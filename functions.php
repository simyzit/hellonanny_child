<?php

if (!defined('ABSPATH')) exit;

require_once get_stylesheet_directory() . '/includes/cpt-jobs.php';
require_once get_stylesheet_directory() . '/includes/jobs-sync.php';

add_action('wp_enqueue_scripts', function () {
    if (!is_post_type_archive('job')) {
        return;
    }

    wp_enqueue_script(
        'nanny-jobs-archive-filters',
        get_stylesheet_directory_uri() . '/assets/js/jobs-archive-filters.js',
        [],
        filemtime(get_stylesheet_directory() . '/assets/js/jobs-archive-filters.js'),
        true
    );

    wp_localize_script('nanny-jobs-archive-filters', 'NANNY_JOBS_FILTERS_I18N', [
        'noJobsFound' => __('No jobs found', 'nanny'),
    ]);
});

add_filter('rank_math/frontend/robots', function (array $robots): array {

    if (!is_singular('job')) {
        return $robots;
    }

    $post_id = get_queried_object_id();
    $status  = get_post_meta($post_id, '_job_status', true);

    if ($status === 'closed') {
        $robots['index']  = 'noindex';
        $robots['follow'] = 'follow';
    }

    return $robots;
});
