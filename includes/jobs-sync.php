<?php
if (!defined('ABSPATH')) exit;

const NANNY_LEVER_ACCOUNT  = 'hellonanny';

/**
 * ----------------------------
 * Sync single Lever job
 * ----------------------------
 */
function nanny_lever_sync_job(array $job): int {
    $lever_id = isset($job['id']) ? (string) $job['id'] : '';
    if ($lever_id === '') {
        return 0;
    }

    $title    = trim((string)($job['text'] ?? ''));
    $location = trim((string)($job['categories']['location'] ?? ''));

    if ($title === '') {
        return 0;
    }

    $post_id = nanny_find_job_by_lever_id($lever_id);
    $slug = nanny_job_build_slug($title, $location);
    $content = nanny_job_sanitize_description($job);

    $postarr = [
        'ID'           => $post_id,
        'post_type'    => 'job',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_content' => $content
    ];

    if ($post_id) {
        $post_id = wp_update_post($postarr, true);
    } else {
        $post_id = wp_insert_post($postarr, true);
    }

    if (is_wp_error($post_id) || !$post_id) {
        return 0;
    }

	$meta_fields = [
        '_lever_id' => $lever_id,
		'_lever_apply_url' => esc_url_raw($job['applyUrl'] ?? ''),
        '_lever_team' => $job['categories']['team'] ?? '',
        '_lever_commitment' => $job['categories']['commitment'] ?? '',
		'_lever_employment_type' => nanny_extract_employment_type($job),
		'_job_status' => 'open'
	];

	foreach ( $meta_fields as $meta_key => $meta_value ) {
        update_post_meta($post_id, $meta_key, $meta_value);
	}
    delete_post_meta($post_id, '_job_closed_at');

    // Locations Taxonomy
    if (! empty($location)) {
        wp_set_object_terms($post_id, $location, 'job_location');
    }

	// Job Types Taxonomy
    $type = trim((string)($job['workplaceType'] ?? ''));
    if (!empty($type)) {
        wp_set_object_terms($post_id, $type, 'job_type');
    }

    return $post_id;
}

/**
 * ----------------------------
 * Main Sync runner
 * ----------------------------
 */
function nanny_lever_sync_jobs(): array {
    $jobs = nanny_lever_fetch_jobs(NANNY_LEVER_ACCOUNT);

    $synced_count = 0;
    $lever_ids_present  = [];

    foreach ($jobs as $job) {
        if (!is_array($job)) continue;

        $lever_id = (string) ($job['id'] ?? '');
        if ($lever_id !== '') {
            $lever_ids_present[] = $lever_id;
        }

        $post_id = nanny_lever_sync_job($job);
        if ($post_id) $synced_count++;
    }

    $closed = nanny_lever_close_missing_jobs($lever_ids_present);

    update_option('nanny_jobs_last_sync', time());

    return [
        'total_from_lever'     => count($jobs),
        'created_or_updated'   => $synced_count,
        'marked_closed'        => $closed,
        'timestamp'            => time(),
    ];
}

/**
 * ----------------------------
 * Mark missing jobs as closed
 * ----------------------------
 */
function nanny_lever_close_missing_jobs(array $current_lever_ids): int {
    $current_lever_ids = array_values(array_filter(array_map('strval', $current_lever_ids)));
    $closed_count = 0;

    $jobs = get_posts([
        'post_type'      => 'job',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => '_job_closed_at',
                'compare' => 'NOT EXISTS',
            ]
        ]
    ]);

    foreach ($jobs as $post_id) {
        $lever_id = (string) get_post_meta((int)$post_id, '_lever_id', true);
        if ($lever_id === '') continue;

        if (!in_array($lever_id, $current_lever_ids, true)) {
            update_post_meta((int)$post_id, '_job_status', 'closed');
            update_post_meta($post_id, '_job_closed_at', time());

            $closed_count++;
        }
    }

    return $closed_count;
}

/**
 * ----------------------------
 * Fetch jobs from Lever
 * ----------------------------
 */
function nanny_lever_fetch_jobs(string $account): array {
    $url = 'https://api.lever.co/v0/postings/' . rawurlencode($account) . '?mode=json';

    $response = wp_remote_get($url, [
        'timeout' => 20,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        return [];
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    if ($status_code < 200 || $status_code >= 300) {
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return [];
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
        return [];
    }

    return $data;
}

/**
 * ----------------------------
 * Helpers
 * ----------------------------
 */
function nanny_find_job_by_lever_id(string $lever_id): int {
    global $wpdb;

    if ($lever_id === '') {
        return 0;
    }

    $post_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            '_lever_id',
            $lever_id
        )
    );

    return $post_id ? (int) $post_id : 0;
}

function nanny_job_build_slug(string $title, string $location): string {
    if (! empty($location)) {
        $title .= '-' . $location;
    }

    return sanitize_title($title);
}

function nanny_job_sanitize_description(array $job): string {
    $html = (string) ($job['descriptionBody'] ?? '');
    $html = str_replace(["\r\n", "\r"], "\n", $html);

    return "<!-- wp:html -->\n{$html}\n<!-- /wp:html -->";
}

function nanny_extract_employment_type(array $job): string
{
    $html = (string) ($job['descriptionBody'] ?? '');

    if ($html === '') {
        return '';
    }

    if (preg_match('/<b>\s*Employment Type:\s*<\/b>\s*(.*?)<\/div>/i', $html, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

/**
 * ----------------------------
 * Cron scheduling
 * ----------------------------
 */
add_action('init', function () {
    if (!wp_next_scheduled('nanny_lever_jobs_sync_event')) {
        wp_schedule_event(time() + 60, 'hourly', 'nanny_lever_jobs_sync_event');
    }
});

add_action('nanny_lever_jobs_sync_event', function () {
    nanny_lever_sync_jobs();
});

add_action('after_switch_theme', function () {
    wp_clear_scheduled_hook('nanny_lever_jobs_sync_event');
    wp_schedule_event(time() + 60, 'hourly', 'nanny_lever_jobs_sync_event');
});

/**
 * ----------------------------
 * Manual sync page
 * ----------------------------
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=job',
        'Lever Jobs Sync',
        'Lever Jobs Sync',
        'manage_options',
        'lever-jobs-sync',
        'nanny_lever_jobs_sync_admin_page'
    );
});

function nanny_lever_jobs_sync_admin_page() {
    if (!current_user_can('manage_options')) return;

    $result = null;

    if (isset($_POST['lever_jobs_sync']) && check_admin_referer('lever_jobs_sync_action')) {
        $result = nanny_lever_sync_jobs();
    }

    ?>
    <div class="wrap">
        <h1>Lever Jobs Sync</h1>

        <p><strong>Account:</strong> <?php echo esc_html(NANNY_LEVER_ACCOUNT); ?></p>
	    <p>
		    <strong>Last Sync:</strong>
            <?php
            $last_sync = get_option('nanny_jobs_last_sync');

            if ($last_sync) {
                echo esc_html(
                    wp_date('Y-m-d H:i:s', $last_sync) .
                    ' (' . human_time_diff($last_sync) . ' ago)'
                );
            } else {
                echo 'Never';
            }
            ?>
	    </p>

        <?php if (is_array($result)) : ?>
            <div class="notice notice-success">
                <p>
                    Sync complete.
                    Total from Lever: <strong><?php echo (int)$result['total_from_lever']; ?></strong>,
                    Updated/Created: <strong><?php echo (int)$result['created_or_updated']; ?></strong>,
                    Marked closed: <strong><?php echo (int)$result['marked_closed']; ?></strong>.
                </p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('lever_jobs_sync_action'); ?>
            <p>
                <button type="submit" name="lever_jobs_sync" class="button button-primary">
                    Run Sync Now
                </button>
            </p>
        </form>
    </div>
    <?php
}