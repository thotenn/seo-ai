<?php
/**
 * Frontend Orchestrator.
 *
 * Coordinates all frontend SEO output by initializing and delegating
 * to individual modules (Meta_Tags, Open_Graph, Twitter_Cards, Schema).
 * Only loads modules that are enabled in settings.
 *
 * @package SeoAi\Frontend
 * @since   1.0.0
 */

namespace SeoAi\Frontend;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Frontend
 *
 * @since 1.0.0
 */
final class Frontend {

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Loaded module instances.
	 *
	 * @var object[]
	 */
	private array $modules = [];

	/**
	 * Constructor.
	 *
	 * @param Options $options Options helper instance.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register WordPress hooks and initialize enabled modules.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Do not run in admin or during REST requests to admin endpoints.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$this->remove_default_wp_actions();
		$this->init_modules();

		// Opening and closing HTML comments in wp_head.
		add_action( 'wp_head', [ $this, 'output_opening_comment' ], 1 );
		add_action( 'wp_head', [ $this, 'output_closing_comment' ], 99 );
	}

	/**
	 * Remove default WordPress actions that we replace with our own output.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function remove_default_wp_actions(): void {
		if ( $this->options->get( 'remove_generator_tag', true ) ) {
			remove_action( 'wp_head', 'wp_generator' );
		}

		if ( $this->options->get( 'remove_rsd_link', true ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( $this->options->get( 'remove_wlw_link', true ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		if ( $this->options->get( 'remove_shortlinks', true ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}
	}

	/**
	 * Initialize and register hooks for enabled frontend modules.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_modules(): void {
		$enabled_modules = $this->options->get( 'enabled_modules', [] );

		if ( ! is_array( $enabled_modules ) ) {
			$enabled_modules = [];
		}

		// Head coordinator - always loaded when meta_tags module is enabled.
		if ( empty( $enabled_modules ) || in_array( 'meta_tags', $enabled_modules, true ) ) {
			$head = new Head( $this->options );
			$head->register_hooks();
			$this->modules['head'] = $head;
		}

		// Open Graph.
		if ( empty( $enabled_modules ) || in_array( 'open_graph', $enabled_modules, true ) ) {
			if ( class_exists( 'SeoAi\\Modules\\Social\\Open_Graph' ) ) {
				$og = new \SeoAi\Modules\Social\Open_Graph();
				$og->register_hooks();
				$this->modules['open_graph'] = $og;
			}
		}

		// Twitter Cards.
		if ( empty( $enabled_modules ) || in_array( 'twitter_cards', $enabled_modules, true ) ) {
			if ( class_exists( 'SeoAi\\Modules\\Social\\Twitter_Cards' ) ) {
				$twitter = new \SeoAi\Modules\Social\Twitter_Cards();
				$twitter->register_hooks();
				$this->modules['twitter_cards'] = $twitter;
			}
		}

		// Schema / Structured Data.
		if ( empty( $enabled_modules ) || in_array( 'schema', $enabled_modules, true ) ) {
			if ( class_exists( 'SeoAi\\Modules\\Schema\\Schema_Manager' ) ) {
				$schema = new \SeoAi\Modules\Schema\Schema_Manager();
				$schema->register_hooks();
				$this->modules['schema'] = $schema;
			}
		}

		/**
		 * Fires after frontend modules have been initialized.
		 *
		 * Allows third-party code to add or modify frontend modules.
		 *
		 * @since 1.0.0
		 *
		 * @param Frontend $frontend The frontend instance.
		 * @param array    $modules  Currently loaded module instances.
		 */
		do_action( 'seo_ai/frontend_modules_loaded', $this, $this->modules );
	}

	/**
	 * Output the opening SEO AI HTML comment in wp_head.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function output_opening_comment(): void {
		echo "\n<!-- SEO AI v" . esc_html( SEO_AI_VERSION ) . " -->\n";
	}

	/**
	 * Output the closing SEO AI HTML comment in wp_head.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function output_closing_comment(): void {
		echo "<!-- /SEO AI -->\n\n";
	}

	/**
	 * Get a loaded module instance by key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Module key (e.g. 'head', 'open_graph', 'twitter_cards', 'schema').
	 * @return object|null The module instance, or null if not loaded.
	 */
	public function get_module( string $key ): ?object {
		return $this->modules[ $key ] ?? null;
	}

	/**
	 * Get all loaded module instances.
	 *
	 * @since 1.0.0
	 *
	 * @return object[]
	 */
	public function get_modules(): array {
		return $this->modules;
	}
}
