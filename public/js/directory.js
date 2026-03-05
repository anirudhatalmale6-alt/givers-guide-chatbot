(function() {
    'use strict';

    if (typeof ggDirectory === 'undefined') return;

    var API = ggDirectory.ajaxUrl;
    var nonce = ggDirectory.nonce;

    // Find directory container
    var container = document.getElementById('gg-directory');
    if (!container) return;

    var perPage = parseInt(container.dataset.perPage) || 12;
    var initialRegion = container.dataset.region || '';
    var initialCategory = container.dataset.category || '';
    var dirType = container.dataset.type || 'resources';

    var searchInput = document.getElementById('gg-search-input');
    var regionSelect = document.getElementById('gg-filter-region');
    var categorySelect = document.getElementById('gg-filter-category');
    var resetBtn = document.getElementById('gg-filter-reset');
    var resultsContainer = document.getElementById('gg-directory-results');
    var resultsInfo = document.getElementById('gg-results-info');
    var pagination = document.getElementById('gg-pagination');

    var searchTimer = null;
    var currentPage = 1;

    // Load regions and categories
    loadFilters();
    loadResults();

    // Event listeners
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                currentPage = 1;
                loadResults();
            }, 400);
        });
    }

    if (regionSelect) {
        regionSelect.addEventListener('change', function() {
            currentPage = 1;
            // Reload categories for selected region
            loadCategories(this.value);
            loadResults();
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            currentPage = 1;
            loadResults();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            if (regionSelect) regionSelect.value = '';
            if (categorySelect) {
                categorySelect.innerHTML = '<option value="">All Categories</option>';
                categorySelect.value = '';
            }
            currentPage = 1;
            loadCategories('');
            loadResults();
        });
    }

    function loadFilters() {
        // Load regions
        fetch(API + 'regions', {
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (regionSelect && data.regions) {
                data.regions.forEach(function(r) {
                    if (r.slug === 'apps') return; // apps shown separately
                    var opt = document.createElement('option');
                    opt.value = r.slug;
                    opt.textContent = r.name;
                    if (r.slug === initialRegion) opt.selected = true;
                    regionSelect.appendChild(opt);
                });
            }
        });

        // Load categories
        loadCategories(initialRegion);
    }

    function loadCategories(region) {
        var url = API + 'categories';
        if (region) url += '?region=' + encodeURIComponent(region);

        fetch(url, {
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!categorySelect) return;
            categorySelect.innerHTML = '<option value="">All Categories</option>';
            if (data.categories) {
                data.categories.forEach(function(c) {
                    if (c.region === 'apps') return;
                    var opt = document.createElement('option');
                    opt.value = c.name;
                    opt.textContent = c.name;
                    if (c.name === initialCategory) opt.selected = true;
                    categorySelect.appendChild(opt);
                });
            }
        });
    }

    function loadResults() {
        var query = searchInput ? searchInput.value.trim() : '';
        var region = regionSelect ? regionSelect.value : initialRegion;
        var category = categorySelect ? categorySelect.value : initialCategory;

        showLoading();

        var endpoint = dirType === 'apps' ? 'apps' : 'search';
        var url = API + endpoint + '?page=' + currentPage + '&per_page=' + perPage;
        if (query) url += '&q=' + encodeURIComponent(query);
        if (region) url += '&region=' + encodeURIComponent(region);
        if (category) url += '&category=' + encodeURIComponent(category);

        fetch(url, {
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (dirType === 'apps') {
                renderApps(data.apps || []);
                resultsInfo.innerHTML = 'Showing <strong>' + (data.apps || []).length + '</strong> apps';
            } else {
                renderResources(data.resources || [], data.total || 0, data.total_pages || 1);
            }
        })
        .catch(function() {
            resultsContainer.innerHTML = '<div class="gg-no-results"><p>Error loading resources. Please try again.</p></div>';
        });
    }

    function renderResources(resources, total, totalPages) {
        if (resources.length === 0) {
            resultsContainer.innerHTML = '<div class="gg-no-results">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
                '<h3>No resources found</h3><p>Try different search terms or adjust your filters.</p></div>';
            resultsInfo.innerHTML = '';
            pagination.innerHTML = '';
            return;
        }

        var start = (currentPage - 1) * perPage + 1;
        var end = Math.min(start + resources.length - 1, total);
        resultsInfo.innerHTML = 'Showing <strong>' + start + '-' + end + '</strong> of <strong>' + total + '</strong> resources';

        var html = '';
        resources.forEach(function(r) {
            html += renderCard(r);
        });
        resultsContainer.innerHTML = html;

        renderPagination(totalPages);
        bindReportButtons();
    }

    function renderCard(r) {
        var regionClass = 'gg-region-' + (r.region || 'usa');
        var regionLabel = { usa: 'USA', israel: 'Israel', england: 'England' }[r.region] || r.region;

        var card = '<div class="gg-resource-card">';
        card += '<button class="gg-report-btn" data-id="' + r.id + '" data-type="resource" title="Report incorrect information">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg></button>';

        card += '<div class="gg-resource-card-header">';
        card += '<h3 class="gg-resource-name">' + escapeHtml(r.name) + '</h3>';
        card += '<span class="gg-resource-region ' + regionClass + '">' + escapeHtml(regionLabel) + '</span>';
        card += '</div>';

        if (r.type) card += '<div class="gg-resource-type">' + escapeHtml(r.type) + '</div>';
        if (r.category) card += '<span class="gg-resource-category">' + escapeHtml(r.category) + '</span>';
        if (r.description) card += '<p class="gg-resource-description">' + escapeHtml(r.description) + '</p>';

        card += '<div class="gg-resource-details">';

        if (r.phone) {
            card += '<div class="gg-detail-row">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>' +
                '<span>' + escapeHtml(r.phone) + '</span></div>';
        }

        if (r.email) {
            card += '<div class="gg-detail-row">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>' +
                '<a href="mailto:' + escapeHtml(r.email) + '">' + escapeHtml(r.email) + '</a></div>';
        }

        if (r.website) {
            var url = r.website;
            if (!url.match(/^https?:\/\//)) url = 'https://' + url;
            card += '<div class="gg-detail-row">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>' +
                '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">' + escapeHtml(r.website) + '</a></div>';
        }

        if (r.location) {
            card += '<div class="gg-detail-row">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' +
                '<span>' + escapeHtml(r.location) + '</span></div>';
        }

        if (r.director) {
            card += '<div class="gg-detail-row">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' +
                '<span>' + escapeHtml(r.director) + '</span></div>';
        }

        card += '</div></div>';
        return card;
    }

    function renderApps(apps) {
        if (apps.length === 0) {
            resultsContainer.innerHTML = '<div class="gg-no-results"><p>No apps found.</p></div>';
            return;
        }

        var html = '';
        apps.forEach(function(a) {
            html += '<div class="gg-resource-card">';
            html += '<h3 class="gg-resource-name">' + escapeHtml(a.title) + '</h3>';
            if (a.category) html += '<span class="gg-resource-category">' + escapeHtml(a.category) + '</span>';
            if (a.description) html += '<p class="gg-resource-description">' + escapeHtml(a.description) + '</p>';
            html += '<div class="gg-resource-details">';
            if (a.cost) html += '<div class="gg-detail-row"><strong>Cost:</strong>&nbsp;' + escapeHtml(a.cost) + '</div>';
            if (a.platform) html += '<div class="gg-detail-row"><strong>Platform:</strong>&nbsp;' + escapeHtml(a.platform) + '</div>';
            html += '</div></div>';
        });
        resultsContainer.innerHTML = html;
    }

    function renderPagination(totalPages) {
        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        var html = '';

        // Prev
        html += '<button class="gg-page-btn" data-page="' + (currentPage - 1) + '"' + (currentPage <= 1 ? ' disabled' : '') + '>&laquo; Prev</button>';

        // Page numbers
        var start = Math.max(1, currentPage - 2);
        var end = Math.min(totalPages, currentPage + 2);

        if (start > 1) {
            html += '<button class="gg-page-btn" data-page="1">1</button>';
            if (start > 2) html += '<span style="padding:0 4px;color:#9ca3af">...</span>';
        }

        for (var i = start; i <= end; i++) {
            html += '<button class="gg-page-btn' + (i === currentPage ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        if (end < totalPages) {
            if (end < totalPages - 1) html += '<span style="padding:0 4px;color:#9ca3af">...</span>';
            html += '<button class="gg-page-btn" data-page="' + totalPages + '">' + totalPages + '</button>';
        }

        // Next
        html += '<button class="gg-page-btn" data-page="' + (currentPage + 1) + '"' + (currentPage >= totalPages ? ' disabled' : '') + '>Next &raquo;</button>';

        pagination.innerHTML = html;

        // Bind page buttons
        var btns = pagination.querySelectorAll('.gg-page-btn');
        btns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (this.disabled) return;
                currentPage = parseInt(this.dataset.page);
                loadResults();
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    function bindReportButtons() {
        var btns = resultsContainer.querySelectorAll('.gg-report-btn');
        btns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                openReportModal(this.dataset.id, this.dataset.type || 'resource');
            });
        });
    }

    function openReportModal(resourceId, resourceType) {
        var modal = document.getElementById('gg-report-modal');
        var form = document.getElementById('gg-report-form');
        var success = document.getElementById('gg-report-success');

        document.getElementById('gg-report-resource-id').value = resourceId;
        document.getElementById('gg-report-resource-type').value = resourceType;

        form.style.display = 'block';
        success.style.display = 'none';
        modal.style.display = 'flex';

        // Close handlers
        var closeBtns = modal.querySelectorAll('.gg-modal-close, .gg-modal-overlay');
        closeBtns.forEach(function(el) {
            el.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        });

        // Submit
        form.onsubmit = function(e) {
            e.preventDefault();

            var data = {
                resource_id: document.getElementById('gg-report-resource-id').value,
                resource_type: document.getElementById('gg-report-resource-type').value,
                issue_type: document.getElementById('gg-report-issue').value,
                description: document.getElementById('gg-report-description').value,
                reporter_name: document.getElementById('gg-report-name').value,
                reporter_email: document.getElementById('gg-report-email').value,
            };

            fetch(API + 'report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify(data),
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    form.style.display = 'none';
                    success.style.display = 'block';
                    form.reset();
                }
            });
        };
    }

    function showLoading() {
        resultsContainer.innerHTML = '<div class="gg-loading"><div class="gg-spinner"></div><p>Loading resources...</p></div>';
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

})();
