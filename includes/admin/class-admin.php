<?php
/**
 * Main Admin Controller.
 *
 * Registers menu pages, enqueues assets, handles metabox rendering
 * and saving, and provides page-rendering callbacks.
 *
 * @package SeoAi\Admin
 * @since   1.0.0
 */

namespace SeoAi\Admin;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Helpers\Post_Meta;
use SeoAi\Helpers\Capability;
use SeoAi\Modules\Content_Analysis\Score;

/**
 * Class Admin
 *
 * @since 1.0.0
 */
final class Admin {

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
	 * Capability helper.
	 *
	 * @var Capability
	 */
	private Capability $capability;

	/**
	 * Admin page hook suffixes for identification.
	 *
	 * @var string[]
	 */
	private array $page_hooks = [];

	/**
	 * Constructor.
	 *
	 * @param Options    $options    Options helper instance.
	 * @param Post_Meta  $post_meta  Post meta helper instance.
	 * @param Capability $capability Capability helper instance.
	 */
	public function __construct( Options $options, Post_Meta $post_meta, Capability $capability ) {
		$this->options    = $options;
		$this->post_meta  = $post_meta;
		$this->capability = $capability;
	}

	/**
	 * Register all WordPress admin hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_menu_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'plugin_action_links_' . SEO_AI_BASENAME, [ $this, 'add_plugin_links' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
		add_action( 'save_post', [ $this, 'save_metabox' ] );
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
	}

	/**
	 * Register the admin menu and submenu pages.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_menu_pages(): void {
		$manage_cap   = Capability::MANAGE_SETTINGS;
		$redirect_cap = Capability::MANAGE_REDIRECTS;

		// Main menu page.
		$this->page_hooks['dashboard'] = add_menu_page(
			__( 'SEO AI', 'seo-ai' ),
			__( 'SEO AI', 'seo-ai' ),
			$manage_cap,
			'seo-ai',
			[ $this, 'render_dashboard_page' ],
			'dashicons-search',
			80
		);

		// Dashboard submenu (replaces the auto-generated first item).
		$this->page_hooks['dashboard_sub'] = add_submenu_page(
			'seo-ai',
			__( 'Dashboard', 'seo-ai' ),
			__( 'Dashboard', 'seo-ai' ),
			$manage_cap,
			'seo-ai',
			[ $this, 'render_dashboard_page' ]
		);

		// Settings submenu.
		$this->page_hooks['settings'] = add_submenu_page(
			'seo-ai',
			__( 'Settings', 'seo-ai' ),
			__( 'Settings', 'seo-ai' ),
			$manage_cap,
			'seo-ai-settings',
			[ $this, 'render_settings_page' ]
		);

		// Redirects submenu.
		$this->page_hooks['redirects'] = add_submenu_page(
			'seo-ai',
			__( 'Redirects', 'seo-ai' ),
			__( 'Redirects', 'seo-ai' ),
			$redirect_cap,
			'seo-ai-redirects',
			[ $this, 'render_redirects_page' ]
		);

		// 404 Log submenu.
		$this->page_hooks['404_log'] = add_submenu_page(
			'seo-ai',
			__( '404 Log', 'seo-ai' ),
			__( '404 Log', 'seo-ai' ),
			$redirect_cap,
			'seo-ai-404-log',
			[ $this, 'render_404_page' ]
		);
	}

	/**
	 * Enqueue admin CSS and JS assets.
	 *
	 * Only loads on plugin admin pages and post edit screens.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$plugin_pages = [
			'toplevel_page_seo-ai',
			'seo-ai_page_seo-ai-settings',
			'seo-ai_page_seo-ai-redirects',
			'seo-ai_page_seo-ai-404-log',
		];

		$is_plugin_page = in_array( $hook, $plugin_pages, true );
		$is_editor      = in_array( $hook, [ 'post.php', 'post-new.php' ], true );

		if ( ! $is_plugin_page && ! $is_editor ) {
			return;
		}

		// ---- Core admin assets (all our pages + editor) ----

		wp_enqueue_style(
			'seo-ai-admin',
			SEO_AI_URL . 'assets/css/admin.css',
			[],
			SEO_AI_VERSION
		);

		wp_enqueue_script(
			'seo-ai-admin',
			SEO_AI_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			SEO_AI_VERSION,
			true
		);

		wp_localize_script( 'seo-ai-admin', 'seoAi', [
			'restUrl'  => esc_url_raw( rest_url( 'seo-ai/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'adminUrl' => esc_url_raw( admin_url() ),
			'version'  => SEO_AI_VERSION,
			'settings' => $this->get_script_settings(),
		] );

		// ---- Settings page ----

		if ( 'seo-ai_page_seo-ai-settings' === $hook ) {
			wp_enqueue_style(
				'seo-ai-settings',
				SEO_AI_URL . 'assets/css/settings.css',
				[ 'seo-ai-admin' ],
				SEO_AI_VERSION
			);

			wp_enqueue_script(
				'seo-ai-settings',
				SEO_AI_URL . 'assets/js/settings.js',
				[ 'seo-ai-admin' ],
				SEO_AI_VERSION,
				true
			);
		}

		// ---- Post editor metabox ----

		if ( $is_editor ) {
			wp_enqueue_style(
				'seo-ai-metabox',
				SEO_AI_URL . 'assets/css/metabox.css',
				[ 'seo-ai-admin' ],
				SEO_AI_VERSION
			);

			wp_enqueue_script(
				'seo-ai-metabox',
				SEO_AI_URL . 'assets/js/metabox.js',
				[ 'seo-ai-admin', 'wp-element', 'wp-data', 'wp-components' ],
				SEO_AI_VERSION,
				true
			);

			global $post;

			if ( $post instanceof \WP_Post ) {
				wp_localize_script( 'seo-ai-metabox', 'seoAiPost', [
					'postId'    => $post->ID,
					'postType'  => $post->post_type,
					'postTitle' => $post->post_title,
					'postUrl'   => get_permalink( $post->ID ) ?: '',
					'meta'      => $this->post_meta->get_all( $post->ID ),
				] );
			}
		}
	}

	/**
	 * Register plugin settings with the WordPress Settings API.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'seo_ai_settings', 'seo_ai_settings', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_settings' ],
		] );
	}

	/**
	 * Sanitize plugin settings on save.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Raw settings input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$clean = [];

		foreach ( $input as $key => $value ) {
			if ( is_array( $value ) ) {
				$clean[ $key ] = array_map( 'sanitize_text_field', $value );
			} elseif ( is_bool( $value ) || in_array( $value, [ '0', '1', 'true', 'false' ], true ) ) {
				$clean[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			} elseif ( is_numeric( $value ) ) {
				$clean[ $key ] = is_float( $value + 0 ) ? (float) $value : (int) $value;
			} else {
				$clean[ $key ] = sanitize_text_field( $value );
			}
		}

		return $clean;
	}

	/**
	 * Register the SEO AI metabox on configured post types.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_metabox(): void {
		$post_types = $this->get_supported_post_types();

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'seo-ai-metabox',
				__( 'SEO AI', 'seo-ai' ),
				[ $this, 'render_metabox' ],
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the SEO AI metabox.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'seo_ai_metabox', 'seo_ai_metabox_nonce' );

		$view = SEO_AI_PATH . 'includes/admin/views/metabox/main.php';

		if ( file_exists( $view ) ) {
			$meta      = $this->post_meta->get_all( $post->ID );
			$options   = $this->options;
			$post_meta = $this->post_meta;
			include $view;
		}
	}

	/**
	 * Save metabox data on post save.
	 *
	 * Verifies nonce, capabilities, and autosave/revision status before
	 * sanitizing and persisting all `_seo_ai_*` fields.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public function save_metabox( int $post_id ): void {
		// Verify nonce.
		if ( ! isset( $_POST['seo_ai_metabox_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['seo_ai_metabox_nonce'] ) ), 'seo_ai_metabox' ) ) {
			return;
		}

		// Skip autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check capability.
		$post_type = get_post_type( $post_id );

		if ( 'page' === $post_type ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Verify our data is present.
		if ( ! isset( $_POST['seo_ai'] ) || ! is_array( $_POST['seo_ai'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per-field below.
		$data = wp_unslash( $_POST['seo_ai'] );

		$sanitized = $this->sanitize_metabox_data( $data );

		// Persist each field.
		foreach ( $sanitized as $key => $value ) {
			$this->post_meta->set( $post_id, $key, $value );
		}

		// Trigger content analysis and cache scores.
		$this->update_post_scores( $post_id );

		/**
		 * Fires after SEO AI metabox data has been saved.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $post_id   The post ID.
		 * @param array $sanitized The sanitized meta data that was saved.
		 */
		do_action( 'seo_ai/metabox_saved', $post_id, $sanitized );
	}

	/**
	 * Add a "Settings" link on the Plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $links Existing plugin action links.
	 * @return string[] Modified links.
	 */
	public function add_plugin_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=seo-ai-settings' ) ),
			esc_html__( 'Settings', 'seo-ai' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Render the Dashboard admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		$options = $this->options;
		$view    = SEO_AI_PATH . 'includes/admin/views/dashboard/main.php';

		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	/**
	 * Render the Settings admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$options = $this->options;
		$view    = SEO_AI_PATH . 'includes/admin/views/settings/main.php';

		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	/**
	 * Render the Redirects admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_redirects_page(): void {
		$options = $this->options;
		$view    = SEO_AI_PATH . 'includes/admin/views/redirects/list.php';

		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	/**
	 * Render the 404 Log admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_404_page(): void {
		$options = $this->options;
		$view    = SEO_AI_PATH . 'includes/admin/views/redirects/404-log.php';

		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	/**
	 * Register the SEO AI dashboard widget.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_dashboard_widget(): void {
		if ( ! current_user_can( Capability::VIEW_REPORTS ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'seo_ai_dashboard_widget',
			__( 'SEO AI Overview', 'seo-ai' ),
			[ $this, 'render_dashboard_widget' ]
		);
	}

	/**
	 * Render the dashboard widget content.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_dashboard_widget(): void {
		$view = SEO_AI_PATH . 'includes/admin/views/dashboard/widget.php';

		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	/**
	 * Sanitize metabox field data.
	 *
	 * Each field is sanitized according to its expected type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw metabox data from $_POST['seo_ai'].
	 * @return array Sanitized data keyed without the prefix.
	 */
	private function sanitize_metabox_data( array $data ): array {
		$sanitized = [];

		// Text fields.
		$text_fields = [ 'title', 'description', 'focus_keyword', 'canonical', 'og_title', 'og_description', 'twitter_title', 'twitter_description' ];

		foreach ( $text_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// URL fields.
		if ( isset( $data['og_image'] ) ) {
			$sanitized['og_image'] = esc_url_raw( $data['og_image'] );
		}

		if ( isset( $data['canonical'] ) ) {
			$sanitized['canonical'] = esc_url_raw( $data['canonical'] );
		}

		// Array/JSON fields.
		if ( isset( $data['focus_keywords'] ) ) {
			if ( is_string( $data['focus_keywords'] ) ) {
				$decoded = json_decode( $data['focus_keywords'], true );
				$sanitized['focus_keywords'] = is_array( $decoded )
					? array_map( 'sanitize_text_field', $decoded )
					: [];
			} elseif ( is_array( $data['focus_keywords'] ) ) {
				$sanitized['focus_keywords'] = array_map( 'sanitize_text_field', $data['focus_keywords'] );
			}
		}

		if ( isset( $data['robots'] ) ) {
			if ( is_string( $data['robots'] ) ) {
				$decoded = json_decode( $data['robots'], true );
				$sanitized['robots'] = is_array( $decoded )
					? array_map( 'sanitize_text_field', $decoded )
					: [];
			} elseif ( is_array( $data['robots'] ) ) {
				$sanitized['robots'] = array_map( 'sanitize_text_field', $data['robots'] );
			}
		}

		// Schema fields.
		if ( isset( $data['schema_type'] ) ) {
			$sanitized['schema_type'] = sanitize_text_field( $data['schema_type'] );
		}

		if ( isset( $data['schema_data'] ) ) {
			if ( is_string( $data['schema_data'] ) ) {
				$decoded = json_decode( $data['schema_data'], true );
				$sanitized['schema_data'] = is_array( $decoded ) ? $decoded : [];
			} elseif ( is_array( $data['schema_data'] ) ) {
				$sanitized['schema_data'] = $data['schema_data'];
			}
		}

		// Toggle fields.
		if ( isset( $data['auto_seo'] ) ) {
			$allowed = [ 'yes', 'no', 'default' ];
			$sanitized['auto_seo'] = in_array( $data['auto_seo'], $allowed, true )
				? $data['auto_seo']
				: 'default';
		}

		return $sanitized;
	}

	/**
	 * Trigger content analysis and cache score in post meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function update_post_scores( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$content       = $post->post_content;
		$focus_keyword = $this->post_meta->get( $post_id, 'focus_keyword' );
		$title         = $this->post_meta->get( $post_id, 'title' );
		$description   = $this->post_meta->get( $post_id, 'description' );

		// Build a basic checks array for scoring.
		$checks = [];

		// Title check.
		$title_for_check = $title ?: $post->post_title;
		$title_length    = mb_strlen( $title_for_check );
		$checks[] = [
			'score'  => ( $title_length >= 30 && $title_length <= 60 ) ? 100 : ( $title_length > 0 ? 50 : 0 ),
			'weight' => 3,
		];

		// Description check.
		$desc_length = mb_strlen( $description );
		$checks[] = [
			'score'  => ( $desc_length >= 120 && $desc_length <= 160 ) ? 100 : ( $desc_length > 0 ? 50 : 0 ),
			'weight' => 3,
		];

		// Content length check.
		$word_count = str_word_count( wp_strip_all_tags( $content ) );
		$min_words  = (int) $this->options->get( 'min_content_length', 300 );
		$checks[] = [
			'score'  => $word_count >= $min_words ? 100 : (int) min( 100, ( $word_count / max( 1, $min_words ) ) * 100 ),
			'weight' => 2,
		];

		// Focus keyword presence check.
		if ( ! empty( $focus_keyword ) ) {
			$has_in_title   = mb_stripos( $title_for_check, $focus_keyword ) !== false;
			$has_in_desc    = mb_stripos( $description, $focus_keyword ) !== false;
			$has_in_content = mb_stripos( $content, $focus_keyword ) !== false;

			$keyword_score = 0;
			if ( $has_in_title ) {
				$keyword_score += 40;
			}
			if ( $has_in_desc ) {
				$keyword_score += 30;
			}
			if ( $has_in_content ) {
				$keyword_score += 30;
			}

			$checks[] = [
				'score'  => $keyword_score,
				'weight' => 4,
			];
		}

		$seo_score = Score::calculate( $checks );
		$this->post_meta->set( $post_id, 'seo_score', $seo_score );
	}

	/**
	 * Get the supported post types from options.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	private function get_supported_post_types(): array {
		$default    = [ 'post', 'page' ];
		$configured = $this->options->get( 'analysis_post_types', $default );

		/** This filter is documented in includes/class-plugin.php */
		return (array) apply_filters( 'seo_ai/post_types', $configured );
	}

	/**
	 * Get settings to pass to admin JavaScript.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function get_script_settings(): array {
		return [
			'postTypes'          => $this->get_supported_post_types(),
			'titleSeparator'     => $this->options->get( 'title_separator', "\u{2013}" ),
			'autoSeoEnabled'     => (bool) $this->options->get( 'auto_seo_enabled', false ),
			'keywordDensityMin'  => (float) $this->options->get( 'keyword_density_min', 1.0 ),
			'keywordDensityMax'  => (float) $this->options->get( 'keyword_density_max', 3.0 ),
			'minContentLength'   => (int) $this->options->get( 'min_content_length', 300 ),
		];
	}
}
