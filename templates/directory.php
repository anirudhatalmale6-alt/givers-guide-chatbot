<?php if (!defined('ABSPATH')) exit; ?>

<div id="gg-directory" class="gg-directory" data-region="<?php echo esc_attr($atts['region']); ?>" data-category="<?php echo esc_attr($atts['category']); ?>" data-per-page="<?php echo esc_attr($atts['per_page']); ?>" data-type="<?php echo esc_attr($atts['type']); ?>">

    <?php if ($atts['show_search'] === 'yes'): ?>
    <!-- Search Bar -->
    <div class="gg-directory-search">
        <div class="gg-search-box">
            <svg class="gg-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="gg-search-input" class="gg-search-input" placeholder="Search resources by name, type, location..." />
        </div>
    </div>
    <?php endif; ?>

    <?php if ($atts['show_filters'] === 'yes'): ?>
    <!-- Filters -->
    <div class="gg-directory-filters">
        <div class="gg-filter-group">
            <label for="gg-filter-region">Region</label>
            <select id="gg-filter-region" class="gg-filter-select">
                <option value="">All Regions</option>
            </select>
        </div>
        <div class="gg-filter-group">
            <label for="gg-filter-category">Category</label>
            <select id="gg-filter-category" class="gg-filter-select">
                <option value="">All Categories</option>
            </select>
        </div>
        <button id="gg-filter-reset" class="gg-filter-reset">Clear Filters</button>
    </div>
    <?php endif; ?>

    <!-- Results Count -->
    <div id="gg-results-info" class="gg-results-info"></div>

    <!-- Resource Cards Grid -->
    <div id="gg-directory-results" class="gg-directory-results">
        <div class="gg-loading">
            <div class="gg-spinner"></div>
            <p>Loading resources...</p>
        </div>
    </div>

    <!-- Pagination -->
    <div id="gg-pagination" class="gg-pagination"></div>

    <!-- Report Modal -->
    <div id="gg-report-modal" class="gg-modal" style="display:none">
        <div class="gg-modal-overlay"></div>
        <div class="gg-modal-content">
            <div class="gg-modal-header">
                <h3>Report Incorrect Information</h3>
                <button class="gg-modal-close" aria-label="Close">&times;</button>
            </div>
            <form id="gg-report-form" class="gg-report-form">
                <input type="hidden" id="gg-report-resource-id" name="resource_id" />
                <input type="hidden" id="gg-report-resource-type" name="resource_type" value="resource" />

                <div class="gg-form-group">
                    <label for="gg-report-issue">What's incorrect? *</label>
                    <select id="gg-report-issue" name="issue_type" required>
                        <option value="">Select an issue...</option>
                        <option value="wrong_phone">Wrong phone number</option>
                        <option value="wrong_email">Wrong email address</option>
                        <option value="wrong_address">Wrong address/location</option>
                        <option value="wrong_website">Wrong website URL</option>
                        <option value="closed">Organization is closed</option>
                        <option value="outdated">Information is outdated</option>
                        <option value="other">Other issue</option>
                    </select>
                </div>

                <div class="gg-form-group">
                    <label for="gg-report-description">Please describe the issue *</label>
                    <textarea id="gg-report-description" name="description" rows="3" required placeholder="Tell us what's wrong and what the correct information should be..."></textarea>
                </div>

                <div class="gg-form-group">
                    <label for="gg-report-name">Your Name (optional)</label>
                    <input type="text" id="gg-report-name" name="reporter_name" placeholder="Your name" />
                </div>

                <div class="gg-form-group">
                    <label for="gg-report-email">Your Email (optional)</label>
                    <input type="email" id="gg-report-email" name="reporter_email" placeholder="your@email.com" />
                </div>

                <div class="gg-form-actions">
                    <button type="button" class="gg-btn-cancel gg-modal-close">Cancel</button>
                    <button type="submit" class="gg-btn-submit">Submit Report</button>
                </div>
            </form>
            <div id="gg-report-success" class="gg-report-success" style="display:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h3>Thank you!</h3>
                <p>Your report has been submitted. We'll review it shortly.</p>
                <button class="gg-btn-submit gg-modal-close">Close</button>
            </div>
        </div>
    </div>
</div>
