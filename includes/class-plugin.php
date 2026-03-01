<?php
namespace SeoAi;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Helpers\Post_Meta;
use SeoAi\Helpers\Capability;
use SeoAi\Providers\Provider_Manager;
use SeoAi\Modules\Module_Manager;

/**
 * Main Plugin singleton class.
 *
 * Bootstraps the SEO AI plugin by initializing all core services,
 * registering WordPress hooks, and providing access to sub-services.
 * Admin functionality is delegated to Admin\Admin. Frontend output is
 * delegated to Frontend\Frontend.
 *
 * @since 1.0.0
 */
final class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/** @var Options|null */
	private ?Options $options = null;

	/** @var Post_Meta|null */
	private ?Post_Meta $post_meta = null;

	/** @var Capability|null */
	private ?Capability $capability = null;

	/** @var Provider_Manager|null */
	private ?Provider_Manager $provider_manager = null;

	/** @var Module_Manager|null */
	private ?Module_Manager $module_manager = null;

	/** @var Admin\Admin|null */
	private ?Admin\Admin $admin = null;

	/**
	 * REST controllers to register.
	 *
	 * @var string[]
	 */
	private array $rest_controllers = [
		'SeoAi\\Rest\\Analysis_Controller',
		'SeoAi\\Rest\\Ai_Controller',
		'SeoAi\\Rest\\Settings_Controller',
		'SeoAi\\Rest\\Redirect_Controller',
		'SeoAi\\Rest\\Provider_Controller',
		'SeoAi\\Rest\\Queue_Controller',
		'SeoAi\\Rest\\Log_Controller',
		'SeoAi\\Rest\\Inline_Ai_Controller',
		'SeoAi\\Rest\\Competitor_Controller',
	];

	/**
	 * Returns the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {
		$this->init_helpers();
		$this->init_providers();
		$this->init_modules();
		$this->register_hooks();
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new \RuntimeException( 'Cannot unserialize singleton.' );
	}

	/**
	 * Initialize helper services.
	 */
	private function init_helpers(): void {
		$this->options    = new Options();
		$this->post_meta  = new Post_Meta();
		$this->capability = new Capability();
	}

	/**
	 * Initialize the AI provider manager.
	 */
	private function init_providers(): void {
		$this->provider_manager = new Provider_Manager();
	}

	/**
	 * Initialize the module manager.
	 */
	private function init_modules(): void {
		$this->module_manager = new Module_Manager( $this->options );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks(): void {
		add_action( 'init', [ $this, 'on_init' ] );
		add_action( 'rest_api_init', [ $this, 'on_rest_api_init' ] );

		// Admin layer — delegated to Admin\Admin.
		add_action( 'plugins_loaded', [ $this, 'init_admin' ], 20 );

		// Frontend layer.
		add_action( 'wp', [ $this, 'init_frontend' ] );
	}

	/**
	 * Runs on the `init` hook.
	 */
	public function on_init(): void {
		$this->load_textdomain();
		$this->module_manager->register_modules();

		// Search Intent detection (needs Provider_Manager, not a toggleable module).
		if ( class_exists( 'SeoAi\\Modules\\Content_Analysis\\Search_Intent' ) ) {
			$search_intent = new Modules\Content_Analysis\Search_Intent( $this->provider_manager );
			$search_intent->register_hooks();
		}

		// Link Suggestions (needs Provider_Manager).
		if ( class_exists( 'SeoAi\\Modules\\Content_Analysis\\Link_Suggestions' ) ) {
			$link_suggestions = new Modules\Content_Analysis\Link_Suggestions( $this->provider_manager );
			$link_suggestions->register_hooks();
		}

		// Schema Builder (advanced schema types: Recipe, JobPosting).
		if ( class_exists( 'SeoAi\\Modules\\Schema\\Schema_Builder' ) ) {
			$schema_builder = new Modules\Schema\Schema_Builder();
			$schema_builder->register_hooks();
		}

		// Content Extractor (ACF, Elementor, Divi content aggregation).
		if ( class_exists( 'SeoAi\\Integrations\\Content_Extractor' ) ) {
			$content_extractor = new Integrations\Content_Extractor();
			$content_extractor->register_hooks();
		}

		// bbPress forum integration (QAPage schema).
		if ( class_exists( 'SeoAi\\Integrations\\Bbpress' ) ) {
			$bbpress = new Integrations\Bbpress();
			$bbpress->register_hooks();
		}

		/**
		 * Fires after the SEO AI plugin is fully initialized.
		 *
		 * @since 1.0.0
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'seo_ai/init', $this );
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'seo-ai',
			false,
			dirname( SEO_AI_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register all REST API controllers.
	 */
	public function on_rest_api_init(): void {
		foreach ( $this->rest_controllers as $controller_class ) {
			if ( class_exists( $controller_class ) ) {
				$controller = new $controller_class( $this );
				$controller->register_routes();
			}
		}
	}

	/**
	 * Initialize the admin layer.
	 *
	 * Creates the Admin, Columns, and Bulk_Actions instances and registers
	 * their hooks. Only runs in admin context.
	 *
	 * @since 1.0.0
	 */
	public function init_admin(): void {
		if ( ! is_admin() ) {
			return;
		}

		$this->admin = new Admin\Admin( $this->options, $this->post_meta, $this->capability );
		$this->admin->register_hooks();

		if ( class_exists( 'SeoAi\\Admin\\Columns' ) ) {
			$columns = new Admin\Columns( $this->options, $this->post_meta );
			$columns->register_hooks();
		}

		if ( class_exists( 'SeoAi\\Admin\\Bulk_Actions' ) ) {
			$bulk_actions = new Admin\Bulk_Actions( $this->options, $this->post_meta );
			$bulk_actions->register_hooks();
		}

		if ( class_exists( 'SeoAi\\Admin\\Post_Filters' ) ) {
			$post_filters = new Admin\Post_Filters( $this->options );
			$post_filters->register_hooks();
		}

		if ( class_exists( 'SeoAi\\Admin\\Quick_Edit' ) ) {
			$quick_edit = new Admin\Quick_Edit( $this->options, $this->post_meta );
			$quick_edit->register_hooks();
		}

		if ( class_exists( 'SeoAi\\Admin\\Csv_Import_Export' ) ) {
			$csv = new Admin\Csv_Import_Export( $this->options, $this->post_meta );
			$csv->register_hooks();
		}
	}

	/**
	 * Initialize the frontend layer.
	 *
	 * Creates the Frontend instance which handles its own internal hook
	 * registration for outputting SEO data in the document head.
	 *
	 * @since 1.0.0
	 */
	public function init_frontend(): void {
		if ( is_admin() ) {
			return;
		}

		if ( class_exists( 'SeoAi\\Frontend\\Frontend' ) ) {
			$frontend = new Frontend\Frontend( $this->options );
			$frontend->register_hooks();
		}
	}

	/**
	 * Get the post types that SEO AI supports.
	 *
	 * @return string[]
	 */
	public function get_supported_post_types(): array {
		$default    = [ 'post', 'page' ];
		$configured = $this->options->get( 'analysis_post_types', $default );

		/** @since 1.0.0 */
		return (array) apply_filters( 'seo_ai/post_types', $configured );
	}

	public static function activate(): void {
		Activator::activate();
	}

	public static function deactivate(): void {
		Deactivator::deactivate();
	}

	public function options(): Options {
		return $this->options;
	}

	public function post_meta(): Post_Meta {
		return $this->post_meta;
	}

	public function capability(): Capability {
		return $this->capability;
	}

	public function provider_manager(): Provider_Manager {
		return $this->provider_manager;
	}

	public function module_manager(): Module_Manager {
		return $this->module_manager;
	}

	/**
	 * Get the Admin instance (null if not in admin context).
	 *
	 * @return Admin\Admin|null
	 */
	public function admin(): ?Admin\Admin {
		return $this->admin;
	}
}
