<?php
get_header();

$q = new WP_Query([
    'post_type'      => 'job',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [
        [
            'key'   => '_job_status',
            'value' => 'open',
        ],
    ],
]);

$type_terms = get_terms([
    'taxonomy'   => 'job_type',
	'hide_empty' => false,
]);

$location_terms = get_terms([
    'taxonomy'   => 'job_location'
]);

?>

<div class="placements">
    <h1 class="placements__title color-accent">Placements</h1>
    <div class="placements__description">
        <p>Professional Hiring Tools, Templates &amp; Services for Nannies and Household Staff Nationwide</p>
    </div>
    <section class="jobs">
        <?php if ($q->have_posts()): ?>
	        <form class="jobs-filters" id="jobsFilters" onsubmit="return false;">
		        <label>
			        <select name="location_type" id="filterLocationType">
				        <option value=""><?php echo esc_html__('Location Type', 'nanny'); ?></option>
                        <?php foreach ($type_terms as $term) : ?>
					        <option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
			        </select>
		        </label>

		        <label>
			        <select name="location" id="filterLocation">
				        <option value=""><?php echo esc_html__('Location', 'nanny'); ?></option>
                        <?php foreach ($location_terms as $term) : ?>
					        <option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
			        </select>
		        </label>

		        <button type="button" class="jobs-filters__reset" id="jobsReset" style="display:none;">
                    <?php echo esc_html__('Reset', 'nanny'); ?>
		        </button>
	        </form>

            <div class="jobs-status" id="jobsStatus"></div>

            <div class="jobs-grid" id="jobsGrid">
                <?php while ($q->have_posts()): $q->the_post();
                    $job_id = get_the_ID();

                    $type_slugs = wp_get_post_terms($job_id, 'job_type', ['fields' => 'slugs']);
                    $type_slug  = (!is_wp_error($type_slugs) && !empty($type_slugs)) ? $type_slugs[0] : '';

                    $loc_terms_post = wp_get_post_terms($job_id, 'job_location', ['fields' => 'all']);
                    $loc_slug = (!is_wp_error($loc_terms_post) && !empty($loc_terms_post)) ? $loc_terms_post[0]->slug : '';
                    $loc_name = (!is_wp_error($loc_terms_post) && !empty($loc_terms_post)) ? $loc_terms_post[0]->name : '';

                    $employment_type = (string) get_post_meta($job_id, '_lever_employment_type', true);

                    $permalink = get_permalink($job_id);
                    ?>
                    <article class="job-card" data-location-type="<?php echo esc_attr($type_slug); ?>" data-location="<?php echo esc_attr($loc_slug); ?>">
                        <h2 class="job-title">
                            <a class="job-link" href="<?php echo esc_url($permalink); ?>">
                                <?php the_title(); ?>
                            </a>
                        </h2>

                        <div class="job-meta">
                            <?php if ($employment_type): ?>
                                <div class="job-commitment"><?php echo esc_html($employment_type); ?></div>
                            <?php endif; ?>

                            <?php if ($loc_name): ?>
                                <div class="job-location"><?php echo esc_html($loc_name); ?></div>
                            <?php endif; ?>
                        </div>

                        <p class="job-actions">
                            <a class="job-cta elementor-button" href="<?php echo esc_url($permalink); ?>">
                                View Now
                            </a>
                        </p>
                    </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else: ?>
            <div class="jobs-status"><?php echo esc_html__('No jobs found', 'nanny'); ?></div>
        <?php endif; ?>
    </section>
</div>

<?php get_footer(); ?>