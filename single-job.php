<?php
get_header();

the_post();

$job_id = get_the_ID();

$status    = (string) get_post_meta($job_id, '_job_status', true); // open|closed
$closed_at = (int) get_post_meta($job_id, '_job_closed_at', true);

$keep_days      = (int) apply_filters('theme_jobs_keep_closed_days', 30);
$keep_seconds   = $keep_days * DAY_IN_SECONDS;

if ($status === 'closed' && $closed_at > 0 && (time() - $closed_at) > $keep_seconds) {
    wp_safe_redirect(get_post_type_archive_link('job'), 301);
    exit;
}

$title = get_the_title();

$apply_url = (string) get_post_meta($job_id, '_lever_apply_url', true);
$employment_type = (string) get_post_meta($job_id, '_lever_employment_type', true);
$team = (string) get_post_meta($job_id, '_lever_team', true);
$commitment = (string) get_post_meta($job_id, '_lever_commitment', true);

$loc_terms = get_the_terms($job_id, 'job_location');
$location = (!is_wp_error($loc_terms) && !empty($loc_terms)) ? $loc_terms[0]->name : '';

$type_names = wp_get_post_terms($job_id, 'job_type', ['fields' => 'names']);
$type_line  = (!is_wp_error($type_names) && !empty($type_names)) ? implode(', ', $type_names) : '';
?>
<article class="job-single">
    <div class="job-single__header">
        <h1 class="job-single__title color-accent"><?php echo esc_html($title); ?></h1>

        <?php if ($location): ?>
            <div class="job-single__location">
                <?php echo esc_html( $location ); ?>
            </div>
        <?php endif; ?>

        <div class="job-single__department">
            <?php
            echo esc_html(
                implode(
                    ' / ',
                    array_filter( [ $team, $commitment, $type_line ] )
                )
            );
            ?>
        </div>

        <?php if (has_excerpt()): ?>
            <div class="job-single__intro">
                <?php echo wp_kses_post(wpautop(get_the_excerpt())); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === 'closed'): ?>
            <div class="job-single__notice job-single__notice--closed">
                This position has been filled. Please see other open roles below.
            </div>
        <?php endif; ?>

        <?php if ($status === 'open' && $apply_url): ?>
            <div class="job-single__apply">
                <a class="elementor-button" href="<?php echo esc_url($apply_url); ?>" target="_blank" rel="noopener">
                    Apply for this Job
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="job-description">
        <?php the_content(); ?>
    </div>

    <?php if ($status === 'open' && $apply_url): ?>
        <div class="job-single__apply job-single__apply--bottom">
            <a class="elementor-button" href="<?php echo esc_url($apply_url); ?>" target="_blank" rel="noopener">
                Apply for this Job
            </a>
        </div>
    <?php endif; ?>

    <?php
    $related_tax = 'job_location';
    $terms = get_the_terms($job_id, $related_tax);

    $term_ids = (!is_wp_error($terms) && !empty($terms)) ? wp_list_pluck($terms, 'term_id') : [];

    $related_args = [
        'post_type'      => 'job',
        'post_status'    => 'publish',
        'posts_per_page' => 6,
        'post__not_in'   => [$job_id],
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'   => '_job_status',
                'value' => 'open',
            ],
        ],
    ];

    if (!empty($term_ids)) {
        $related_args['tax_query'] = [
            [
                'taxonomy' => $related_tax,
                'field'    => 'term_id',
                'terms'    => $term_ids,
            ],
        ];
    }

    $related = new WP_Query($related_args);
    ?>

    <?php if ($related->have_posts()): ?>
        <section class="related-jobs">
            <h2 class="related-jobs__title">Related jobs</h2>

            <div class="jobs-grid">
                <?php while ($related->have_posts()): $related->the_post(); ?>
                    <article class="job-card">
                        <h3 class="job-card__title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>
                    </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>

            <p class="related-jobs__all">
                <a href="<?php echo esc_url(get_post_type_archive_link('job')); ?>">
                    View all jobs
                </a>
            </p>
        </section>
    <?php endif; ?>
</article>

<?php
function nanny_parse_employment_type(string $value): array {
    $value = strtolower(trim($value));
    if ($value === '') {
        return ['FULL_TIME'];
    }

    $map = [
        'full'      => 'FULL_TIME',
        'part'      => 'PART_TIME',
        'contract'  => 'CONTRACTOR',
        'temporary' => 'TEMPORARY',
        'temp'      => 'TEMPORARY',
        'intern'    => 'INTERN',
        'volunteer' => 'VOLUNTEER',
        'per diem'  => 'PER_DIEM',
    ];

    $types = [];

    foreach (explode(',', $value) as $part) {
        $part = trim($part);

        if (str_contains($part, 'live-in') || str_contains($part, 'live out') || str_contains($part, 'live-out') || str_contains($part, 'live in')) {
            continue;
        }

        foreach ($map as $needle => $schema) {
            if (str_contains($part, $needle)) {
                $types[] = $schema;
                break;
            }
        }
    }

    return $types ? array_values(array_unique($types)) : ['FULL_TIME'];
}

if ($status === 'open'):

    $schema = [
        "@context" => "https://schema.org",
        "@type" => "JobPosting",
        "title" => $title,
        "description" => wp_strip_all_tags(get_the_content()),
        "datePosted" => get_the_date('c'),
        "employmentType" => nanny_parse_employment_type($employment_type),
        "hiringOrganization" => [
            "@type" => "Organization",
            "name" => "Hello Nanny",
            "sameAs" => home_url(),
        ],
        "jobLocation" => [
            "@type" => "Place",
            "address" => [
                "@type" => "PostalAddress",
                "addressLocality" => $location,
                "addressCountry" => "US"
            ]
        ],
        "applicantLocationRequirements" => [
            "@type" => "Country",
            "name" => "US"
        ],
        "directApply" => true,
        "url" => get_permalink(),
    ];
    ?>

	<script type="application/ld+json">
		<?php echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
	</script>

<?php endif; ?>

<?php get_footer(); ?>