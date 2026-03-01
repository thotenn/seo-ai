<?php
/**
 * Quick Edit SEO Fields.
 *
 * Adds inline SEO editing fields to the WordPress quick edit panel
 * in post list tables.
 *
 * @package SeoAi\Admin
 * @since   0.4.0
 */

declare(strict_types=1);

namespace SeoAi\Admin;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Helpers\Post_Meta;

/**
 * Class Quick_Edit
 *
 * @since 0.4.0
 */
final class Quick_Edit {

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Post meta helper.
	 *
	 * @var Post_Meta
	 */
	private Post_Meta $post_meta;

	/**
	 * Constructor.
	 *
	 * @param Options   $options   Options helper instance.
	 * @param Post_Meta $post_meta Post meta helper instance.
	 */
	public function __construct( Options $options, Post_Meta $post_meta ) {
		$this->options   = $options;
		$this->post_meta = $post_meta;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$post_types = $this->get_supported_post_types();

		foreach ( $post_types as $post_type ) {
			add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'render_hidden_data' ], 20, 2 );
		}

		add_action( 'quick_edit_custom_box', [ $this, 'render_quick_edit_fields' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save_quick_edit' ] );
		add_action( 'admin_footer-edit.php', [ $this, 'render_quick_edit_script' ] );
	}

	/**
	 * Output hidden data attributes in the SEO score column for JS population.
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_hidden_data( string $column, int $post_id ): void {
		if ( 'seo_ai_score' !== $column ) {
			return;
		}

		$title       = $this->post_meta->get( $post_id, 'title' );
		$keyword     = $this->post_meta->get( $post_id, 'focus_keyword' );
		$schema      = $this->post_meta->get( $post_id, 'schema_type' );
		$robots      = $this->post_meta->get( $post_id, 'robots', [] );
		$noindex     = is_array( $robots ) && in_array( 'noindex', $robots, true ) ? '1' : '0';
		$cornerstone = $this->post_meta->get( $post_id, 'cornerstone', '0' );

		printf(
			'<div class="seo-ai-quick-data hidden" data-seo-title="%s" data-seo-keyword="%s" data-seo-schema="%s" data-seo-noindex="%s" data-seo-cornerstone="%s"></div>',
			esc_attr( $title ),
			esc_attr( $keyword ),
			esc_attr( $schema ),
			esc_attr( $noindex ),
			esc_attr( $cornerstone )
		);
	}

	/**
	 * Render the quick edit fields inside WordPress quick edit panel.
	 *
	 * @param string $column_name The column being processed.
	 * @param string $post_type   The current post type.
	 * @return void
	 */
	public function render_quick_edit_fields( string $column_name, string $post_type ): void {
		if ( 'seo_ai_score' !== $column_name ) {
			return;
		}

		$supported = $this->get_supported_post_types();

		if ( ! in_array( $post_type, $supported, true ) ) {
			return;
		}

		wp_nonce_field( 'seo_ai_quick_edit', 'seo_ai_quick_edit_nonce' );
		?>
		<fieldset class="inline-edit-col-right" style="clear:both;">
			<div class="inline-edit-col">
				<h4><?php esc_html_e( 'SEO AI', 'seo-ai' ); ?></h4>
				<label class="inline-edit-group">
					<span class="title" style="width:100px;"><?php esc_html_e( 'SEO Title', 'seo-ai' ); ?></span>
					<span class="input-text-wrap">
						<input type="text" name="seo_ai_qe[title]" class="seo-ai-qe-title" value="" />
					</span>
				</label>
				<label class="inline-edit-group">
					<span class="title" style="width:100px;"><?php esc_html_e( 'Keyword', 'seo-ai' ); ?></span>
					<span class="input-text-wrap">
						<input type="text" name="seo_ai_qe[focus_keyword]" class="seo-ai-qe-keyword" value="" />
					</span>
				</label>
				<label class="inline-edit-group">
					<span class="title" style="width:100px;"><?php esc_html_e( 'Schema', 'seo-ai' ); ?></span>
					<select name="seo_ai_qe[schema_type]" class="seo-ai-qe-schema">
						<option value=""><?php esc_html_e( 'Auto-detect', 'seo-ai' ); ?></option>
						<option value="Article">Article</option>
						<option value="BlogPosting">BlogPosting</option>
						<option value="WebPage">WebPage</option>
						<option value="FAQPage">FAQPage</option>
						<option value="HowTo">HowTo</option>
						<option value="Product">Product</option>
					</select>
				</label>
				<label class="inline-edit-group">
					<input type="checkbox" name="seo_ai_qe[noindex]" class="seo-ai-qe-noindex" value="1" />
					<span class="checkbox-title"><?php esc_html_e( 'Noindex', 'seo-ai' ); ?></span>
				</label>
				<label class="inline-edit-group">
					<input type="checkbox" name="seo_ai_qe[cornerstone]" class="seo-ai-qe-cornerstone" value="1" />
					<span class="checkbox-title"><?php esc_html_e( 'Cornerstone content', 'seo-ai' ); ?></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save quick edit data on post save.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_quick_edit( int $post_id ): void {
		if ( ! isset( $_POST['seo_ai_quick_edit_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['seo_ai_quick_edit_nonce'] ) ), 'seo_ai_quick_edit' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['seo_ai_qe'] ) || ! is_array( $_POST['seo_ai_qe'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = wp_unslash( $_POST['seo_ai_qe'] );

		// SEO title.
		if ( isset( $data['title'] ) ) {
			$this->post_meta->set( $post_id, 'title', sanitize_text_field( $data['title'] ) );
		}

		// Focus keyword.
		if ( isset( $data['focus_keyword'] ) ) {
			$this->post_meta->set( $post_id, 'focus_keyword', sanitize_text_field( $data['focus_keyword'] ) );
		}

		// Schema type.
		if ( isset( $data['schema_type'] ) ) {
			$this->post_meta->set( $post_id, 'schema_type', sanitize_text_field( $data['schema_type'] ) );
		}

		// Noindex (via robots array).
		$robots = $this->post_meta->get( $post_id, 'robots', [] );
		if ( ! is_array( $robots ) ) {
			$robots = [];
		}

		if ( ! empty( $data['noindex'] ) ) {
			if ( ! in_array( 'noindex', $robots, true ) ) {
				$robots[] = 'noindex';
			}
		} else {
			$robots = array_values( array_diff( $robots, [ 'noindex' ] ) );
		}
		$this->post_meta->set( $post_id, 'robots', $robots );

		// Cornerstone.
		$this->post_meta->set( $post_id, 'cornerstone', ! empty( $data['cornerstone'] ) ? '1' : '0' );
	}

	/**
	 * Output inline JS to populate quick edit fields when opened.
	 *
	 * @return void
	 */
	public function render_quick_edit_script(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		$supported = $this->get_supported_post_types();

		if ( ! in_array( $screen->post_type, $supported, true ) ) {
			return;
		}
		?>
		<script>
		(function($) {
			var origInlineEdit = inlineEditPost.edit;
			inlineEditPost.edit = function(id) {
				origInlineEdit.apply(this, arguments);

				if (typeof id === 'object') {
					id = this.getId(id);
				}

				var $row = $('#post-' + id);
				var $data = $row.find('.seo-ai-quick-data');
				if (!$data.length) return;

				var $editRow = $('#edit-' + id);
				$editRow.find('.seo-ai-qe-title').val($data.data('seo-title') || '');
				$editRow.find('.seo-ai-qe-keyword').val($data.data('seo-keyword') || '');
				$editRow.find('.seo-ai-qe-schema').val($data.data('seo-schema') || '');
				$editRow.find('.seo-ai-qe-noindex').prop('checked', $data.data('seo-noindex') === '1' || $data.data('seo-noindex') === 1);
				$editRow.find('.seo-ai-qe-cornerstone').prop('checked', $data.data('seo-cornerstone') === '1' || $data.data('seo-cornerstone') === 1);
			};
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Get the supported post types from options.
	 *
	 * @return string[]
	 */
	private function get_supported_post_types(): array {
		$default    = [ 'post', 'page' ];
		$configured = $this->options->get( 'analysis_post_types', $default );

		return (array) apply_filters( 'seo_ai/post_types', $configured );
	}
}
