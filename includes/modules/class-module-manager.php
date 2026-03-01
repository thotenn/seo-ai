<?php
/**
 * Module Manager.
 *
 * Manages the registration, initialization, and lifecycle of plugin modules.
 * Each module represents a discrete feature that can be enabled or disabled
 * independently through the plugin settings.
 *
 * @package SeoAi\Modules
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Module_Manager
 *
 * Provides a registry of available modules, their enabled/disabled state,
 * and orchestrates their instantiation and WordPress hook registration
 * during plugin initialization.
 *
 * @since 1.0.0
 */
class Module_Manager {

	/**
	 * Options helper instance.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Collection of instantiated module objects keyed by module ID.
	 *
	 * @var array<string, object|object[]>
	 */
	private array $active_modules = [];

	/**
	 * Registry of all available module definitions.
	 *
	 * @var array<int, array{id: string, name: string, description: string, default: bool, classes: string[]}>
	 */
	private array $module_definitions = [];

	/**
	 * Constructor.
	 *
	 * @param Options $options Options helper instance.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
		$this->build_module_definitions();
	}

	/**
	 * Build the internal registry of module definitions.
	 *
	 * @return void
	 */
	private function build_module_definitions(): void {
		$this->module_definitions = [
			[
				'id'          => 'meta_tags',
				'name'        => __( 'Meta Tags', 'seo-ai' ),
				'description' => __( 'Manage SEO title, description, and other meta tags for posts and pages.', 'seo-ai' ),
				'default'     => true,
				'classes'     => [
					'SeoAi\\Modules\\Meta_Tags\\Meta_Tags',
				],
			],
			[
				'id'          => 'schema',
				'name'        => __( 'Schema Markup', 'seo-ai' ),
				'description' => __( 'Add structured data (JSON-LD) to improve search engine understanding of your content.', 'seo-ai' ),
				'default'     => true,
				'classes'     => [
					'SeoAi\\Modules\\Schema\\Schema_Manager',
				],
			],
			[
				'id'          => 'sitemap',
				'name'        => __( 'XML Sitemap', 'seo-ai' ),
				'description' => __( 'Generate and manage XML sitemaps for search engine crawling.', 'seo-ai' ),
				'default'     => true,
				'classes'     => [
					'SeoAi\\Modules\\Sitemap\\Sitemap_Manager',
				],
			],
			[
				'id'          => 'redirects',
				'name'        => __( 'Redirects & 404 Monitor', 'seo-ai' ),
				'description' => __( 'Create URL redirects and monitor 404 errors to maintain link equity.', 'seo-ai' ),
				'default'     => true,
				'classes'     => [
					'SeoAi\\Modules\\Redirects\\Redirect_Handler',
					'SeoAi\\Modules\\Redirects\\Monitor_404',
				],
			],
			[
				'id'          => 'open_graph',
				'name'        => __( 'Open Graph', 'seo-ai' ),
				'description' => __( 'Add Open Graph meta tags for rich previews on Facebook and other platforms.', 'seo-ai' ),
				'default'     => true,
				'classes'     => [
					'SeoAi\\Modules\\Social\\Open_Graph',
				],
			],
			[
				'id'          => 'twitter_cards',
				'name'        => __( 'Twitter Cards', 'seo-ai' ),
				'description' => __( 'Add Twitter Card meta tags for rich previews on Twitter/X.', 'seo-ai' ),
				'default'     => true,
				'classes'     => [
					'SeoAi\\Modules\\Social\\Twitter_Cards',
				],
			],
			[
				'id'          => 'image_seo',
				'name'        => __( 'Image SEO', 'seo-ai' ),
				'description' => __( 'Automatically optimize image alt text and file names for better SEO.', 'seo-ai' ),
				'default'     => true,
				'classes'     => [
					'SeoAi\\Modules\\Image_Seo\\Image_Seo',
				],
			],
			[
				'id'          => 'breadcrumbs',
				'name'        => __( 'Breadcrumbs', 'seo-ai' ),
				'description' => __( 'Add breadcrumb navigation with structured data support.', 'seo-ai' ),
				'default'     => false,
				'classes'     => [
					'SeoAi\\Modules\\Breadcrumbs\\Breadcrumbs',
				],
			],
			[
				'id'          => 'robots_txt',
				'name'        => __( 'Robots.txt', 'seo-ai' ),
				'description' => __( 'Customize your robots.txt file to control search engine crawling.', 'seo-ai' ),
				'default'     => true,
				'classes'     => [
					'SeoAi\\Modules\\Robots\\Robots_Txt',
				],
			],
			[
				'id'          => 'indexing',
				'name'        => __( 'Instant Indexing', 'seo-ai' ),
				'description' => __( 'Submit URLs to IndexNow and Bing for faster indexing on publish.', 'seo-ai' ),
				'default'     => false,
				'classes'     => [
					'SeoAi\\Modules\\Indexing\\Indexing',
				],
			],
			[
				'id'          => 'video_sitemap',
				'name'        => __( 'Video Sitemap', 'seo-ai' ),
				'description' => __( 'Generate a dedicated video XML sitemap for embedded YouTube, Vimeo, and HTML5 videos.', 'seo-ai' ),
				'default'     => false,
				'classes'     => [
					'SeoAi\\Modules\\Sitemap\\Video_Sitemap',
				],
			],
			[
				'id'          => 'news_sitemap',
				'name'        => __( 'News Sitemap', 'seo-ai' ),
				'description' => __( 'Generate a Google News compliant sitemap for articles published in the last 48 hours.', 'seo-ai' ),
				'default'     => false,
				'classes'     => [
					'SeoAi\\Modules\\Sitemap\\News_Sitemap',
				],
			],
		];
	}

	/**
	 * Register and initialize all enabled modules.
	 *
	 * Called from Plugin::on_init(). Iterates through the module definitions,
	 * checks which modules are enabled, instantiates their classes, and calls
	 * register_hooks() on each instance.
	 *
	 * @return void
	 */
	public function register_modules(): void {
		foreach ( $this->module_definitions as $module ) {
			if ( ! $this->is_module_enabled( $module['id'] ) ) {
				continue;
			}

			$instances = [];

			foreach ( $module['classes'] as $class_name ) {
				if ( ! class_exists( $class_name ) ) {
					continue;
				}

				$instance = new $class_name();

				if ( method_exists( $instance, 'register_hooks' ) ) {
					$instance->register_hooks();
				}

				$instances[] = $instance;
			}

			if ( ! empty( $instances ) ) {
				$this->active_modules[ $module['id'] ] = count( $instances ) === 1
					? $instances[0]
					: $instances;
			}
		}

		/**
		 * Fires after all enabled modules have been registered and initialized.
		 *
		 * @since 1.0.0
		 *
		 * @param Module_Manager $module_manager The module manager instance.
		 */
		do_action( 'seo_ai/modules_loaded', $this );
	}

	/**
	 * Get all registered module definitions.
	 *
	 * @return array<int, array{id: string, name: string, description: string, default: bool}>
	 */
	public function get_registered_modules(): array {
		$modules = [];

		foreach ( $this->module_definitions as $definition ) {
			$modules[] = [
				'id'          => $definition['id'],
				'name'        => $definition['name'],
				'description' => $definition['description'],
				'default'     => $definition['default'],
			];
		}

		return $modules;
	}

	/**
	 * Check whether a given module is currently enabled.
	 *
	 * @param string $id Module identifier.
	 * @return bool
	 */
	public function is_module_enabled( string $id ): bool {
		$enabled_modules = $this->get_enabled_modules_option();

		if ( null !== $enabled_modules ) {
			return in_array( $id, $enabled_modules, true );
		}

		return $this->get_module_default( $id );
	}

	/**
	 * Enable a module.
	 *
	 * @param string $id Module identifier.
	 * @return bool True if the module was enabled.
	 */
	public function enable_module( string $id ): bool {
		if ( ! $this->is_valid_module( $id ) ) {
			return false;
		}

		$enabled = $this->get_enabled_modules_option() ?? $this->get_default_enabled_ids();

		if ( ! in_array( $id, $enabled, true ) ) {
			$enabled[] = $id;
		}

		$this->options->set( 'enabled_modules', array_values( $enabled ) );

		return true;
	}

	/**
	 * Disable a module.
	 *
	 * @param string $id Module identifier.
	 * @return bool True if the module was disabled.
	 */
	public function disable_module( string $id ): bool {
		if ( ! $this->is_valid_module( $id ) ) {
			return false;
		}

		$enabled = $this->get_enabled_modules_option() ?? $this->get_default_enabled_ids();
		$enabled = array_filter( $enabled, fn( string $module_id ): bool => $module_id !== $id );

		$this->options->set( 'enabled_modules', array_values( $enabled ) );

		return true;
	}

	/**
	 * Get an active (instantiated) module by ID.
	 *
	 * @param string $id Module identifier.
	 * @return object|object[]|null
	 */
	public function get_active_module( string $id ): object|array|null {
		return $this->active_modules[ $id ] ?? null;
	}

	/**
	 * Get all active (instantiated) module instances.
	 *
	 * @return array<string, object|object[]>
	 */
	public function get_active_modules(): array {
		return $this->active_modules;
	}

	/**
	 * Get the stored enabled modules option.
	 *
	 * @return string[]|null
	 */
	private function get_enabled_modules_option(): ?array {
		$value = $this->options->get( 'enabled_modules', null );

		if ( null === $value || ! is_array( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Get the default enabled state for a specific module.
	 *
	 * @param string $id Module identifier.
	 * @return bool
	 */
	private function get_module_default( string $id ): bool {
		foreach ( $this->module_definitions as $definition ) {
			if ( $definition['id'] === $id ) {
				return $definition['default'];
			}
		}

		return false;
	}

	/**
	 * Get the list of module IDs that are enabled by default.
	 *
	 * @return string[]
	 */
	private function get_default_enabled_ids(): array {
		$ids = [];

		foreach ( $this->module_definitions as $definition ) {
			if ( $definition['default'] ) {
				$ids[] = $definition['id'];
			}
		}

		return $ids;
	}

	/**
	 * Check whether the given ID corresponds to a registered module.
	 *
	 * @param string $id Module identifier.
	 * @return bool
	 */
	private function is_valid_module( string $id ): bool {
		foreach ( $this->module_definitions as $definition ) {
			if ( $definition['id'] === $id ) {
				return true;
			}
		}

		return false;
	}
}
