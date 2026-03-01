/**
 * SEO AI Dashboard — Optimization Wizard & Activity Log.
 *
 * @since 0.1.0
 */
(function ($) {
	'use strict';

	var api = {
		get: function (endpoint, data) {
			return $.ajax({
				url: seoAi.restUrl + endpoint,
				method: 'GET',
				data: data || {},
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', seoAi.nonce);
				}
			});
		},
		post: function (endpoint, data) {
			return $.ajax({
				url: seoAi.restUrl + endpoint,
				method: 'POST',
				contentType: 'application/json',
				data: JSON.stringify(data || {}),
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', seoAi.nonce);
				}
			});
		}
	};

	/* =====================================================================
	 * Wizard State
	 * =================================================================== */

	var wizard = {
		step: 1,
		selectedPosts: {},   // { id: { id, title, post_type, seo_score } }
		selectedFields: [],
		isProcessing: false,
		pollTimer: null,
		currentPage: 1,
		totalPages: 1
	};

	/* =====================================================================
	 * Modal Controls
	 * =================================================================== */

	function openModal() {
		wizard.step = 1;
		wizard.selectedPosts = {};
		wizard.selectedFields = [];
		wizard.isProcessing = false;
		wizard.currentPage = 1;

		$('#seo-ai-optimize-modal').show();
		goToStep(1);
		loadPosts();
	}

	function closeModal() {
		if (wizard.isProcessing) {
			if (!confirm(seoAi.i18n && seoAi.i18n.cancelConfirm || 'Optimization is in progress. Are you sure you want to close?')) {
				return;
			}
			cancelQueue();
		}
		$('#seo-ai-optimize-modal').hide();
		stopPolling();
	}

	/* =====================================================================
	 * Step Navigation
	 * =================================================================== */

	function goToStep(n) {
		wizard.step = n;

		$('.seo-ai-wizard-step').hide();
		$('.seo-ai-wizard-step[data-step="' + n + '"]').show();

		// Update step indicator.
		$('.seo-ai-step').each(function () {
			var stepNum = parseInt($(this).data('step'), 10);
			$(this).removeClass('active completed');
			if (stepNum === n) {
				$(this).addClass('active');
			} else if (stepNum < n) {
				$(this).addClass('completed');
			}
		});

		// Update footer buttons.
		$('.seo-ai-btn-back').toggle(n > 1 && n < 4);
		$('.seo-ai-btn-next').toggle(n < 3);
		$('.seo-ai-btn-start').toggle(n === 3);
		$('.seo-ai-btn-cancel').toggle(n === 4 && wizard.isProcessing);
		$('.seo-ai-btn-close').toggle(n === 4 && !wizard.isProcessing);

		if (n === 3) {
			renderReview();
		}
	}

	/* =====================================================================
	 * Step 1: Post Selection
	 * =================================================================== */

	function loadPosts(page) {
		page = page || 1;
		wizard.currentPage = page;

		var $list = $('#seo-ai-post-list');
		$list.html('<div class="seo-ai-loading">Loading posts...</div>');

		api.get('queue/posts', {
			search: $('#seo-ai-post-search').val() || '',
			post_type: $('#seo-ai-post-type-filter').val() || '',
			filter: $('#seo-ai-status-filter').val() || 'all',
			page: page,
			per_page: 20
		}).done(function (res) {
			var data = res.data || {};
			var posts = data.posts || [];
			wizard.totalPages = data.pages || 1;

			if (posts.length === 0) {
				$list.html('<div class="seo-ai-empty">No posts found.</div>');
				$('#seo-ai-post-pagination').empty();
				return;
			}

			var html = '';
			for (var i = 0; i < posts.length; i++) {
				var p = posts[i];
				var checked = wizard.selectedPosts[p.id] ? ' checked' : '';
				var scoreClass = p.seo_score >= 70 ? 'good' : (p.seo_score >= 40 ? 'warning' : 'low');
				var scoreText = p.seo_score > 0 ? p.seo_score : '—';

				html += '<label class="seo-ai-post-item">';
				html += '<input type="checkbox" value="' + p.id + '"' + checked + ' data-post=\'' + JSON.stringify(p).replace(/'/g, '&#39;') + '\'>';
				html += '<span class="seo-ai-post-title">' + escapeHtml(p.title) + '</span>';
				html += '<span class="seo-ai-post-type">' + escapeHtml(p.post_type) + '</span>';
				html += '<span class="seo-ai-post-score seo-ai-score-' + scoreClass + '">' + scoreText + '</span>';
				html += '</label>';
			}
			$list.html(html);

			renderPagination(data.total || 0, page, wizard.totalPages);
			updateSelectedCount();
			syncSelectAll();
		}).fail(function () {
			$list.html('<div class="seo-ai-empty">Failed to load posts.</div>');
		});
	}

	function renderPagination(total, currentPage, totalPages) {
		var $pag = $('#seo-ai-post-pagination');
		if (totalPages <= 1) {
			$pag.empty();
			return;
		}
		var html = '<span class="seo-ai-pag-info">' + total + ' posts</span>';
		if (currentPage > 1) {
			html += '<button type="button" class="button button-small seo-ai-pag-btn" data-page="' + (currentPage - 1) + '">&laquo; Prev</button>';
		}
		html += '<span class="seo-ai-pag-current">Page ' + currentPage + ' of ' + totalPages + '</span>';
		if (currentPage < totalPages) {
			html += '<button type="button" class="button button-small seo-ai-pag-btn" data-page="' + (currentPage + 1) + '">Next &raquo;</button>';
		}
		$pag.html(html);
	}

	function updateSelectedCount() {
		var count = Object.keys(wizard.selectedPosts).length;
		$('#seo-ai-selected-count').text(count + ' selected');
	}

	function syncSelectAll() {
		var $checks = $('#seo-ai-post-list input[type="checkbox"]');
		var allChecked = $checks.length > 0 && $checks.filter(':checked').length === $checks.length;
		$('#seo-ai-select-all').prop('checked', allChecked);
	}

	/* =====================================================================
	 * Step 2: Read selected fields
	 * =================================================================== */

	function getSelectedFields() {
		var fields = [];
		$('.seo-ai-field-options input:checked').each(function () {
			fields.push($(this).val());
		});
		return fields;
	}

	/* =====================================================================
	 * Step 3: Review
	 * =================================================================== */

	function renderReview() {
		var posts = Object.values(wizard.selectedPosts);
		var fields = getSelectedFields();
		wizard.selectedFields = fields;

		$('#seo-ai-review-posts').text(posts.length);
		$('#seo-ai-review-fields').text(fields.length);
		$('#seo-ai-review-operations').text(posts.length * fields.length);

		var fieldLabels = {
			title: 'Meta Title',
			description: 'Meta Description',
			keyword: 'Focus Keyword',
			schema: 'Schema Type',
			og: 'Open Graph'
		};

		var fieldHtml = '';
		for (var i = 0; i < fields.length; i++) {
			fieldHtml += '<span class="seo-ai-badge seo-ai-badge-success">' + escapeHtml(fieldLabels[fields[i]] || fields[i]) + '</span> ';
		}
		$('#seo-ai-review-field-list').html(fieldHtml);

		var postHtml = '<ul class="seo-ai-review-post-titles">';
		var max = Math.min(posts.length, 20);
		for (var j = 0; j < max; j++) {
			postHtml += '<li>' + escapeHtml(posts[j].title) + ' <small>(' + escapeHtml(posts[j].post_type) + ')</small></li>';
		}
		if (posts.length > 20) {
			postHtml += '<li><em>... and ' + (posts.length - 20) + ' more</em></li>';
		}
		postHtml += '</ul>';
		$('#seo-ai-review-post-list').html(postHtml);
	}

	/* =====================================================================
	 * Step 4: Processing & Polling
	 * =================================================================== */

	function startOptimization() {
		var postIds = Object.keys(wizard.selectedPosts).map(Number);
		var fields = wizard.selectedFields;

		goToStep(4);
		wizard.isProcessing = true;

		$('.seo-ai-btn-cancel').show();
		$('.seo-ai-btn-close').hide();
		$('.seo-ai-btn-back').hide();

		$('#seo-ai-terminal').empty();
		$('#seo-ai-progress-fill').css('width', '0%');
		$('#seo-ai-progress-percent').text('0%');
		$('#seo-ai-progress-text').text('0 / ' + postIds.length);

		appendLog('info', 'Starting optimization of ' + postIds.length + ' posts...');

		api.post('queue/start', {
			post_ids: postIds,
			fields: fields
		}).done(function () {
			startPolling();
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to start optimization.';
			appendLog('error', msg);
			wizard.isProcessing = false;
			$('.seo-ai-btn-cancel').hide();
			$('.seo-ai-btn-close').show();
		});
	}

	function startPolling() {
		if (wizard.pollTimer) return;
		pollNext();
	}

	function pollNext() {
		if (!wizard.isProcessing) return;

		api.post('queue/process-next').done(function (res) {
			var data = res.data || {};

			if (data.log_entry) {
				appendLog(data.log_entry.level || 'info', data.log_entry.message || '');
			}

			$('#seo-ai-progress-fill').css('width', (data.progress || 0) + '%');
			$('#seo-ai-progress-percent').text((data.progress || 0) + '%');
			$('#seo-ai-progress-text').text((data.current || 0) + ' / ' + (data.total || 0));

			if (data.done) {
				appendLog('info', 'Optimization complete!');
				wizard.isProcessing = false;
				$('.seo-ai-btn-cancel').hide();
				$('.seo-ai-btn-close').show();
				return;
			}

			// Continue polling.
			wizard.pollTimer = setTimeout(pollNext, 500);
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Processing error.';
			appendLog('error', msg);
			wizard.isProcessing = false;
			$('.seo-ai-btn-cancel').hide();
			$('.seo-ai-btn-close').show();
		});
	}

	function stopPolling() {
		if (wizard.pollTimer) {
			clearTimeout(wizard.pollTimer);
			wizard.pollTimer = null;
		}
		wizard.isProcessing = false;
	}

	function cancelQueue() {
		stopPolling();
		api.post('queue/cancel');
		appendLog('warn', 'Optimization cancelled by user.');
		$('.seo-ai-btn-cancel').hide();
		$('.seo-ai-btn-close').show();
	}

	function appendLog(level, message) {
		var $terminal = $('#seo-ai-terminal');
		var time = new Date().toLocaleTimeString();
		var cls = 'seo-ai-log-' + level;
		$terminal.append(
			'<div class="seo-ai-log-line ' + cls + '">' +
			'<span class="seo-ai-log-time">[' + time + ']</span> ' +
			escapeHtml(message) +
			'</div>'
		);
		$terminal.scrollTop($terminal[0].scrollHeight);
	}

	/* =====================================================================
	 * Activity List (expand/collapse)
	 * =================================================================== */

	function initActivityList() {
		$(document).on('click', '.seo-ai-activity-expand', function () {
			var $btn = $(this);
			var $item = $btn.closest('.seo-ai-activity-item');
			var $detail = $item.find('.seo-ai-activity-detail');

			if ($detail.length) {
				$detail.toggle();
				return;
			}

			var ctx = $btn.data('context');
			if (typeof ctx === 'string') {
				try { ctx = JSON.parse(ctx); } catch (e) { /* ignore */ }
			}

			var json = JSON.stringify(ctx, null, 2);
			$item.append('<pre class="seo-ai-activity-detail">' + escapeHtml(json) + '</pre>');
		});
	}

	/* =====================================================================
	 * Helpers
	 * =================================================================== */

	function escapeHtml(text) {
		if (!text) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(text));
		return div.innerHTML;
	}

	/* =====================================================================
	 * Post Type Filter Population
	 * =================================================================== */

	function populatePostTypeFilter() {
		var postTypes = (seoAi.settings && seoAi.settings.postTypes) || [];
		var labels = seoAi.postTypeLabels || {};
		var $select = $('#seo-ai-post-type-filter');

		for (var i = 0; i < postTypes.length; i++) {
			var pt = postTypes[i];
			var label = labels[pt] || pt;
			$select.append('<option value="' + escapeHtml(pt) + '">' + escapeHtml(label) + '</option>');
		}
	}

	/* =====================================================================
	 * Event Bindings
	 * =================================================================== */

	$(function () {
		// Open modal.
		$('#seo-ai-start-optimize').on('click', openModal);

		// Close modal.
		$('.seo-ai-modal-close, .seo-ai-modal-overlay').on('click', closeModal);

		// Escape key.
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $('#seo-ai-optimize-modal').is(':visible')) {
				closeModal();
			}
		});

		// Navigation buttons.
		$('.seo-ai-btn-next').on('click', function () {
			if (wizard.step === 1) {
				if (Object.keys(wizard.selectedPosts).length === 0) {
					alert('Please select at least one post.');
					return;
				}
			}
			if (wizard.step === 2) {
				if (getSelectedFields().length === 0) {
					alert('Please select at least one field.');
					return;
				}
			}
			goToStep(wizard.step + 1);
		});

		$('.seo-ai-btn-back').on('click', function () {
			if (wizard.step > 1) {
				goToStep(wizard.step - 1);
			}
		});

		$('.seo-ai-btn-start').on('click', startOptimization);

		$('.seo-ai-btn-cancel').on('click', cancelQueue);

		$('.seo-ai-btn-close').on('click', closeModal);

		// Post selection.
		$(document).on('change', '#seo-ai-post-list input[type="checkbox"]', function () {
			var $cb = $(this);
			var post = $cb.data('post');
			if ($cb.is(':checked')) {
				wizard.selectedPosts[post.id] = post;
			} else {
				delete wizard.selectedPosts[post.id];
			}
			updateSelectedCount();
			syncSelectAll();
		});

		// Select all.
		$('#seo-ai-select-all').on('change', function () {
			var checked = $(this).is(':checked');
			$('#seo-ai-post-list input[type="checkbox"]').each(function () {
				$(this).prop('checked', checked).trigger('change');
			});
		});

		// Search / filter.
		var searchTimer;
		$('#seo-ai-post-search').on('input', function () {
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				loadPosts(1);
			}, 400);
		});
		$('#seo-ai-post-type-filter, #seo-ai-status-filter').on('change', function () {
			loadPosts(1);
		});

		// Pagination.
		$(document).on('click', '.seo-ai-pag-btn', function () {
			loadPosts(parseInt($(this).data('page'), 10));
		});

		// Resume queue button.
		$('#seo-ai-resume-queue').on('click', function () {
			openModal();
			goToStep(4);
			wizard.isProcessing = true;
			$('.seo-ai-btn-cancel').show();
			$('.seo-ai-btn-close').hide();
			$('.seo-ai-btn-back').hide();
			$('.seo-ai-btn-next').hide();
			$('.seo-ai-btn-start').hide();
			appendLog('info', 'Resuming optimization...');
			startPolling();
		});

		// Init.
		populatePostTypeFilter();
		initActivityList();
	});

})(jQuery);
