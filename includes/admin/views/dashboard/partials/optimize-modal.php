<?php
/**
 * Optimization Wizard Modal.
 *
 * 4-step wizard: Select Posts → Configure Fields → Review → Progress.
 *
 * @package SeoAi\Admin\Views
 * @since   0.1.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="seo-ai-optimize-modal" class="seo-ai-modal" style="display:none;">
	<div class="seo-ai-modal-overlay"></div>
	<div class="seo-ai-modal-content">
		<div class="seo-ai-modal-header">
			<h2><?php esc_html_e( 'Optimization Wizard', 'seo-ai' ); ?></h2>
			<button type="button" class="seo-ai-modal-close" aria-label="<?php esc_attr_e( 'Close', 'seo-ai' ); ?>">&times;</button>
		</div>

		<!-- Step Indicator -->
		<div class="seo-ai-steps">
			<div class="seo-ai-step active" data-step="1">
				<span class="seo-ai-step-dot">1</span>
				<span class="seo-ai-step-label"><?php esc_html_e( 'Select Posts', 'seo-ai' ); ?></span>
			</div>
			<div class="seo-ai-step-line"></div>
			<div class="seo-ai-step" data-step="2">
				<span class="seo-ai-step-dot">2</span>
				<span class="seo-ai-step-label"><?php esc_html_e( 'Fields', 'seo-ai' ); ?></span>
			</div>
			<div class="seo-ai-step-line"></div>
			<div class="seo-ai-step" data-step="3">
				<span class="seo-ai-step-dot">3</span>
				<span class="seo-ai-step-label"><?php esc_html_e( 'Review', 'seo-ai' ); ?></span>
			</div>
			<div class="seo-ai-step-line"></div>
			<div class="seo-ai-step" data-step="4">
				<span class="seo-ai-step-dot">4</span>
				<span class="seo-ai-step-label"><?php esc_html_e( 'Progress', 'seo-ai' ); ?></span>
			</div>
		</div>

		<!-- Step 1: Select Posts -->
		<div class="seo-ai-wizard-step" data-step="1">
			<div class="seo-ai-wizard-filters">
				<input type="text" id="seo-ai-post-search" class="regular-text"
					placeholder="<?php esc_attr_e( 'Search posts...', 'seo-ai' ); ?>">
				<select id="seo-ai-post-type-filter">
					<option value=""><?php esc_html_e( 'All Post Types', 'seo-ai' ); ?></option>
				</select>
				<select id="seo-ai-status-filter">
					<option value="all"><?php esc_html_e( 'All', 'seo-ai' ); ?></option>
					<option value="unoptimized"><?php esc_html_e( 'Unoptimized', 'seo-ai' ); ?></option>
					<option value="optimized"><?php esc_html_e( 'Optimized', 'seo-ai' ); ?></option>
				</select>
			</div>
			<div class="seo-ai-post-list-header">
				<label>
					<input type="checkbox" id="seo-ai-select-all">
					<strong><?php esc_html_e( 'Select All', 'seo-ai' ); ?></strong>
				</label>
				<span id="seo-ai-selected-count">0 <?php esc_html_e( 'selected', 'seo-ai' ); ?></span>
			</div>
			<div id="seo-ai-post-list" class="seo-ai-post-list">
				<div class="seo-ai-loading"><?php esc_html_e( 'Loading posts...', 'seo-ai' ); ?></div>
			</div>
			<div id="seo-ai-post-pagination" class="seo-ai-pagination"></div>
		</div>

		<!-- Step 2: Configure Fields -->
		<div class="seo-ai-wizard-step" data-step="2" style="display:none;">
			<p><?php esc_html_e( 'Select which SEO fields to optimize:', 'seo-ai' ); ?></p>
			<div class="seo-ai-field-options">
				<label class="seo-ai-field-option">
					<input type="checkbox" name="seo_ai_fields[]" value="title" checked>
					<span class="seo-ai-field-name"><?php esc_html_e( 'Meta Title', 'seo-ai' ); ?></span>
					<span class="seo-ai-field-desc"><?php esc_html_e( 'SEO-optimized page title for search results', 'seo-ai' ); ?></span>
				</label>
				<label class="seo-ai-field-option">
					<input type="checkbox" name="seo_ai_fields[]" value="description" checked>
					<span class="seo-ai-field-name"><?php esc_html_e( 'Meta Description', 'seo-ai' ); ?></span>
					<span class="seo-ai-field-desc"><?php esc_html_e( 'Compelling description snippet for search results', 'seo-ai' ); ?></span>
				</label>
				<label class="seo-ai-field-option">
					<input type="checkbox" name="seo_ai_fields[]" value="keyword">
					<span class="seo-ai-field-name"><?php esc_html_e( 'Focus Keyword', 'seo-ai' ); ?></span>
					<span class="seo-ai-field-desc"><?php esc_html_e( 'AI-suggested primary keyword for the content', 'seo-ai' ); ?></span>
				</label>
				<label class="seo-ai-field-option">
					<input type="checkbox" name="seo_ai_fields[]" value="schema">
					<span class="seo-ai-field-name"><?php esc_html_e( 'Schema Type', 'seo-ai' ); ?></span>
					<span class="seo-ai-field-desc"><?php esc_html_e( 'Detect and set the best Schema.org structured data type', 'seo-ai' ); ?></span>
				</label>
				<label class="seo-ai-field-option">
					<input type="checkbox" name="seo_ai_fields[]" value="og">
					<span class="seo-ai-field-name"><?php esc_html_e( 'Open Graph', 'seo-ai' ); ?></span>
					<span class="seo-ai-field-desc"><?php esc_html_e( 'Social media title and description for sharing', 'seo-ai' ); ?></span>
				</label>
			</div>
		</div>

		<!-- Step 3: Review -->
		<div class="seo-ai-wizard-step" data-step="3" style="display:none;">
			<div class="seo-ai-review-summary">
				<div class="seo-ai-review-stat">
					<span class="seo-ai-review-number" id="seo-ai-review-posts">0</span>
					<span><?php esc_html_e( 'Posts', 'seo-ai' ); ?></span>
				</div>
				<span class="seo-ai-review-x">&times;</span>
				<div class="seo-ai-review-stat">
					<span class="seo-ai-review-number" id="seo-ai-review-fields">0</span>
					<span><?php esc_html_e( 'Fields', 'seo-ai' ); ?></span>
				</div>
				<span class="seo-ai-review-x">=</span>
				<div class="seo-ai-review-stat">
					<span class="seo-ai-review-number" id="seo-ai-review-operations">0</span>
					<span><?php esc_html_e( 'AI Operations', 'seo-ai' ); ?></span>
				</div>
			</div>
			<div class="seo-ai-review-fields" id="seo-ai-review-field-list"></div>
			<div class="seo-ai-review-posts" id="seo-ai-review-post-list"></div>
			<div class="seo-ai-review-warning">
				<strong><?php esc_html_e( 'Note:', 'seo-ai' ); ?></strong>
				<?php esc_html_e( 'This will overwrite existing values for the selected fields. This action cannot be undone.', 'seo-ai' ); ?>
			</div>
		</div>

		<!-- Step 4: Progress -->
		<div class="seo-ai-wizard-step" data-step="4" style="display:none;">
			<div class="seo-ai-progress-header">
				<span id="seo-ai-progress-text">0 / 0</span>
				<span id="seo-ai-progress-percent">0%</span>
			</div>
			<div class="seo-ai-progress-bar">
				<div class="seo-ai-progress-fill" id="seo-ai-progress-fill" style="width:0%"></div>
			</div>
			<div class="seo-ai-terminal" id="seo-ai-terminal"></div>
		</div>

		<!-- Footer -->
		<div class="seo-ai-modal-footer">
			<button type="button" class="button seo-ai-btn-back" style="display:none;">
				<?php esc_html_e( 'Back', 'seo-ai' ); ?>
			</button>
			<div class="seo-ai-modal-footer-right">
				<button type="button" class="button seo-ai-btn-cancel" style="display:none;">
					<?php esc_html_e( 'Cancel', 'seo-ai' ); ?>
				</button>
				<button type="button" class="button button-primary seo-ai-btn-next">
					<?php esc_html_e( 'Next', 'seo-ai' ); ?>
				</button>
				<button type="button" class="button button-primary seo-ai-btn-start" style="display:none;">
					<?php esc_html_e( 'Start Optimization', 'seo-ai' ); ?>
				</button>
				<button type="button" class="button button-primary seo-ai-btn-close" style="display:none;">
					<?php esc_html_e( 'Close', 'seo-ai' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
