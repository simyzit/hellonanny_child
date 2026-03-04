(function () {
    const $status = document.getElementById("jobsStatus");
    const $grid = document.getElementById("jobsGrid");
    if (!$grid) return;

    const $cards = Array.from($grid.querySelectorAll(".job-card"));
    const $type = document.getElementById("filterLocationType");
    const $loc = document.getElementById("filterLocation");
    const $reset = document.getElementById("jobsReset");

    const i18n = (window.NANNY_JOBS_FILTERS_I18N || {});
    const MSG_NO_JOBS = i18n.noJobsFound || "No jobs found";

    function normalize(v) {
        return String(v || "").trim().toLowerCase();
    }

    function setStatus(msg) {
        if ($status) $status.textContent = msg || "";
    }

    function getStateFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return {
            location_type: normalize(params.get("location_type")),
            location: normalize(params.get("location")),
        };
    }

    function setUrlFromState(state) {
        const url = new URL(window.location.href);
        const params = url.searchParams;

        if (state.location_type) params.set("location_type", state.location_type);
        else params.delete("location_type");

        if (state.location) params.set("location", state.location);
        else params.delete("location");

        window.history.replaceState({}, "", url.toString());
    }

    function applyFilters() {
        const typeVal = normalize($type?.value);
        const locVal = normalize($loc?.value);

        let visible = 0;

        $cards.forEach((card) => {
            const cardType = normalize(card.getAttribute("data-location-type"));
            const cardLoc = normalize(card.getAttribute("data-location"));

            let ok = true;
            if (typeVal && cardType !== typeVal) ok = false;
            if (locVal && cardLoc !== locVal) ok = false;

            card.style.display = ok ? "" : "none";
            if (ok) visible++;
        });

        const hasFilters = !!(typeVal || locVal);
        if ($reset) $reset.style.display = hasFilters ? "" : "none";

        setUrlFromState({ location_type: typeVal, location: locVal });

        if (visible === 0) setStatus(MSG_NO_JOBS);
        else setStatus("");
    }

    function resetFilters() {
        if ($type) $type.value = "";
        if ($loc) $loc.value = "";
        applyFilters();
    }

    // init from URL (shareable filtered link)
    const initial = getStateFromUrl();
    if ($type && initial.location_type) $type.value = initial.location_type;
    if ($loc && initial.location) $loc.value = initial.location;

    $type?.addEventListener("change", applyFilters);
    $loc?.addEventListener("change", applyFilters);
    $reset?.addEventListener("click", resetFilters);

    applyFilters();
})();