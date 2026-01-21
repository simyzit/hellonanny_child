<?php
/*
Template Name: Lever Jobs
*/

get_header();

$account  = 'hellonanny';
$per_page = 9;

function lever_fetch_jobs(string $account): array {
	$cache_key = 'lever_jobs_' . md5($account);
	$cached = get_transient($cache_key);
	if (false !== $cached && is_array($cached)) {
		return $cached;
	}

	$url = 'https://api.lever.co/v0/postings/' . rawurlencode($account) . '?mode=json';
	$res = wp_remote_get($url, [
		'timeout' => 15,
		'headers' => [
			'Accept' => 'application/json',
		],
	]);

	if (is_wp_error($res)) {
		return [];
	}

	$code = wp_remote_retrieve_response_code($res);
	$body = wp_remote_retrieve_body($res);

	if ($code < 200 || $code >= 300 || empty($body)) {
		return [];
	}

	$data = json_decode($body, true);
	if (!is_array($data)) {
		return [];
	}

	set_transient($cache_key, $data, 10 * MINUTE_IN_SECONDS);

	return $data;
}

$jobs = lever_fetch_jobs($account);

if ( ! empty( $_GET['id'] ) ) {

    $job_id = sanitize_text_field( wp_unslash( $_GET['id'] ) );

    $job = null;
    foreach ( $jobs as $item ) {
        if ( isset( $item['id'] ) && $item['id'] === $job_id ) {
            $job = $item;
            break;
        }
    }

    if ( $job ) {

        $title = $job['text'] ?? '';
        $location = $job['categories']['location'] ?? '';
        $team = $job['categories']['team'] ?? '';
        $department = $job['categories']['department'] ?? '';
        $apply_url = $job['applyUrl'] ?? $job['hostedUrl'] ?? '#';

        $description = $job['descriptionBody']
            ?? $job['description']
            ?? nl2br( esc_html( $job['descriptionPlain'] ?? '' ) );
        ?>

        <section class="job-single">
            <div class="job-single__header">
                <h1 class="job-single__title color-accent"><?php echo esc_html( $title ); ?></h1>
                <div class="job-single__location">
                    <?php echo esc_html( $location ); ?>
                </div>
                <div class="job-single__department">
                    <?php
                    echo esc_html(
                        implode(
                            ' / ',
                            array_filter( [ $team, $department, 'On-Site' ] )
                        )
                    );
                    ?>
                </div>
                <div class="job-single__apply">
                    <a class="elementor-button" href="<?php echo esc_url( $apply_url ); ?>" target="_blank" rel="noopener">
                        Apply for this Job
                    </a>
                </div>
            </div>
            <div class="job-description">
                <?php echo $description; ?>
            </div>
            <div class="job-single__apply">
                <a class="elementor-button" href="<?php echo esc_url( $apply_url ); ?>" target="_blank" rel="noopener">
                    Apply for this Job
                </a>
            </div>
        </section>
    <?php }
} else { ?>
    <div class="placements">
        <h1 class="placements__title color-accent"><?php the_title(); ?></h1>
        <div class="placements__description"><?php the_content(); ?></div>
        <section class="jobs">
            <form class="jobs-filters" id="jobsFilters">
                <label>
                    <select name="location_type" id="filterLocationType">
                        <option value="">Location Type</option>
                        <option value="onsite">On-site</option>
                        <option value="hybrid">Hybrid</option>
                        <option value="remote">Remote</option>
                    </select>
                </label>

                <label>
                    <select name="location" id="filterLocation">
                        <option value="">All</option>
                    </select>
                </label>
            </form>

            <div class="jobs-status" id="jobsStatus"></div>

            <div class="jobs-grid" id="jobsGrid"></div>

            <nav class="jobs-pagination" id="jobsPagination" aria-label="Jobs pagination"></nav>

            <script>
                window.LEVER_JOBS_CONFIG = {
                    perPage: <?php echo $per_page; ?>,
                    jobs: <?php echo wp_json_encode($jobs); ?>
                };
            </script>

            <script>
                (function () {
                    const cfg = window.LEVER_JOBS_CONFIG || {};
                    const allJobs = Array.isArray(cfg.jobs) ? cfg.jobs : [];
                    const perPage = Number(cfg.perPage || 6);

                    console.log(allJobs)

                    const $status = document.getElementById('jobsStatus');
                    const $grid = document.getElementById('jobsGrid');
                    const $pagination = document.getElementById('jobsPagination');
                    const $locationType = document.getElementById('filterLocationType');
                    const $location = document.getElementById('filterLocation');

                    let state = {
                        page: 1,
                        locationType: '',
                        location: ''
                    };

                    function normalize(str) {
                        return String(str || '').trim();
                    }

                    function jobLocation(job) {
                        return normalize(job?.categories?.location || job?.location || '');
                    }

                    function jobCommitment(job) {
                        const text = normalize(
                            job?.descriptionPlain ||
                            job?.descriptionBodyPlain ||
                            job?.description ||
                            ''
                        );

                        const m =
                            text.match(/Employment Type:\s*([^\n\r]+)/i) ||
                            text.match(/\bType:\s*([^\n\r]+)/i);

                        if (m && m[1]) return normalize(m[1]);

                        return '';
                    }

                    function jobTitle(job) {
                        return normalize(job?.text || job?.title || '');
                    }

                    function jobUrl(job) {
                        return normalize(job?.hostedUrl || job?.applyUrl || job?.url || '');
                    }

                    function jobInternalUrl(job) {
                        if (!job?.id) return '';

                        const url = new URL(window.location.href);
                        url.searchParams.set('id', job.id);

                        return normalize(url.toString());
                    }

                    function guessLocationType(job) {
                        const text = (jobTitle(job) + ' ' + jobLocation(job)).toLowerCase();
                        if (text.includes('remote')) return 'remote';
                        if (text.includes('hybrid')) return 'hybrid';
                        return 'onsite';
                    }

                    function getUniqueLocations(jobs) {
                        const set = new Set();
                        jobs.forEach(j => {
                            const loc = jobLocation(j);
                            if (loc) set.add(loc);
                        });
                        return Array.from(set).sort((a, b) => a.localeCompare(b));
                    }

                    function setStatus(msg) {
                        $status.textContent = msg || '';
                    }

                    function renderOptions() {
                        const locations = getUniqueLocations(allJobs);

                        const current = $location.value;
                        $location.innerHTML = '';
                        const optAll = document.createElement('option');
                        optAll.value = '';
                        optAll.textContent = 'Location';
                        $location.appendChild(optAll);

                        locations.forEach(loc => {
                            const opt = document.createElement('option');
                            opt.value = loc;
                            opt.textContent = loc;
                            $location.appendChild(opt);
                        });

                        if (current && locations.includes(current)) {
                            $location.value = current;
                        }
                    }

                    function filterJobs() {
                        const type = state.locationType;
                        const loc = state.location;

                        return allJobs.filter(job => {
                            const lt = guessLocationType(job);
                            const jl = jobLocation(job);

                            if (type && lt !== type) return false;
                            if (loc && jl !== loc) return false;

                            return true;
                        });
                    }

                    function paginate(items) {
                        const total = items.length;
                        const pages = Math.max(1, Math.ceil(total / perPage));
                        const page = Math.min(Math.max(1, state.page), pages);
                        state.page = page;

                        const start = (page - 1) * perPage;
                        const slice = items.slice(start, start + perPage);

                        return { slice, total, pages, page };
                    }

                    function renderGrid(items) {
                        $grid.innerHTML = '';

                        if (!items.length) {
                            setStatus('No jobs found');
                            return;
                        }

                        setStatus('');

                        items.forEach(job => {
                            const article = document.createElement('article');
                            article.className = 'job-card';
                            article.setAttribute('data-id', normalize(job?.id || ''));
                            article.setAttribute('data-location-type', guessLocationType(job));
                            article.setAttribute('data-location', jobLocation(job));

                            const h3 = document.createElement('h3');
                            h3.className = 'job-title';

                            const aTitle = document.createElement('a');
                            aTitle.className = 'job-link';
                            aTitle.href = jobInternalUrl(job) || '#';
                            aTitle.textContent = jobTitle(job) || 'Job';

                            h3.appendChild(aTitle);

                            const meta = document.createElement('div');
                            meta.className = 'job-meta';

                            const commitment = document.createElement('div');
                            commitment.className = 'job-commitment';
                            commitment.textContent = jobCommitment(job);

                            const location = document.createElement('div');
                            location.className = 'job-location';
                            location.textContent = jobLocation(job);

                            meta.appendChild(commitment);
                            meta.appendChild(location);

                            const actions = document.createElement('p');
                            actions.className = 'job-actions';

                            const cta = document.createElement('a');
                            cta.className = 'job-cta elementor-button';
                            cta.href = jobInternalUrl(job) || '#';
                            cta.textContent = 'View Now';

                            actions.appendChild(cta);

                            article.appendChild(h3);
                            article.appendChild(meta);
                            article.appendChild(actions);

                            $grid.appendChild(article);
                        });
                    }

                    function renderPagination(pages, page) {
                        $pagination.innerHTML = '';

                        if (pages <= 1) return;

                        for (let i = 1; i <= pages; i++) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'page-btn';
                            btn.setAttribute('data-page', String(i));
                            btn.textContent = String(i);
                            if (i === page) {
                                btn.setAttribute('aria-current', 'page');
                            }
                            btn.addEventListener('click', function () {
                                const p = Number(this.getAttribute('data-page'));
                                if (!Number.isFinite(p)) return;
                                state.page = p;
                                update();
                            });
                            $pagination.appendChild(btn);
                        }
                    }

                    function syncStateFromForm() {
                        state.locationType = normalize($locationType.value);
                        state.location = normalize($location.value);
                        state.page = 1;
                    }

                    function update() {
                        const filtered = filterJobs();
                        const pg = paginate(filtered);
                        renderGrid(pg.slice);
                        renderPagination(pg.pages, pg.page);
                    }

                    if (!allJobs.length) {
                        setStatus('No jobs found');
                        return;
                    }

                    renderOptions();
                    update();

                    function onFiltersChange() {
                        syncStateFromForm();
                        update();
                    }
                    $locationType.addEventListener('change', onFiltersChange);
                    $location.addEventListener('change', onFiltersChange);
                })();
            </script>
        </section>
    </div>
<?php }

get_footer();
