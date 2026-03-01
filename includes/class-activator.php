<?php
namespace SeoAi;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Capability;
use SeoAi\Activity_Log;

/**
 * Plugin activation handler.
 *
 * Creates custom database tables, sets default options,
 * registers capabilities, and flushes rewrite rules.
 *
 * @since 1.0.0
 */
class Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::set_default_options();
		self::store_version();
		Capability::grant_defaults();
		flush_rewrite_rules();

		Activity_Log::log( 'info', 'settings_change', 'Plugin activated', [
			'version' => SEO_AI_VERSION,
		] );

		/**
		 * Fires after the SEO AI plugin is activated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'seo_ai/activate' );
	}

	/**
	 * Create custom database tables.
	 *
	 * Uses dbDelta for safe table creation and upgrades.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$redirects_table = $wpdb->prefix . 'seo_ai_redirects';
		$log_table       = $wpdb->prefix . 'seo_ai_404_log';

		$sql_redirects = "CREATE TABLE {$redirects_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_url varchar(2048) NOT NULL,
			target_url varchar(2048) NOT NULL DEFAULT '',
			type smallint(4) NOT NULL DEFAULT 301,
			is_regex tinyint(1) NOT NULL DEFAULT 0,
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY source_url (source_url(191)),
			KEY status (status),
			KEY type (type)
		) {$charset_collate};";

		$sql_404_log = "CREATE TABLE {$log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url varchar(2048) NOT NULL,
			referrer varchar(2048) DEFAULT '',
			user_agent varchar(512) DEFAULT '',
			ip_address varchar(45) DEFAULT '',
			hits int(11) unsigned NOT NULL DEFAULT 1,
			last_hit datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY url (url(191)),
			KEY last_hit (last_hit)
		) {$charset_collate};";

		$activity_table = $wpdb->prefix . 'seo_ai_activity_log';

		$sql_activity_log = "CREATE TABLE {$activity_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL DEFAULT 'info',
			operation varchar(100) NOT NULL,
			message text NOT NULL,
			context longtext,
			user_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY operation (operation),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_redirects );
		dbDelta( $sql_404_log );
		dbDelta( $sql_activity_log );
	}

	/**
	 * Set default plugin options if they do not already exist.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		// Main settings.
		if ( false === get_option( 'seo_ai_settings' ) ) {
			$defaults = [
				// Enabled modules.
				'enabled_modules'     => [
					'content-analysis',
					'meta-tags',
					'schema',
					'sitemap',
					'social',
					'redirects',
					'image-seo',
					'breadcrumbs',
					'robots-txt',
				],

				// Title & Meta.
				'title_separator'      => "\u{2013}",
				'default_title'        => '%title% %sep% %sitename%',
				'default_description'  => '',
				'homepage_title'       => '%sitename% %sep% %tagline%',
				'homepage_description' => '',

				// Post type defaults.
				'pt_post_title'        => '%title% %sep% %sitename%',
				'pt_post_description'  => '%excerpt%',
				'pt_post_schema'       => 'Article',
				'pt_post_noindex'      => false,
				'pt_page_title'        => '%title% %sep% %sitename%',
				'pt_page_description'  => '%excerpt%',
				'pt_page_schema'       => 'WebPage',
				'pt_page_noindex'      => false,

				// Taxonomy defaults.
				'tax_category_title'     => '%term_title% %sep% %sitename%',
				'tax_category_noindex'   => false,
				'tax_post_tag_title'     => '%term_title% %sep% %sitename%',
				'tax_post_tag_noindex'   => true,

				// Content Analysis.
				'analysis_post_types'  => [ 'post', 'page' ],
				'min_content_length'   => 300,
				'keyword_density_min'  => 1.0,
				'keyword_density_max'  => 3.0,

				// Schema / Knowledge Graph.
				'schema_type'            => 'Organization',
				'org_name'               => '',
				'org_description'        => '',
				'org_logo'               => '',
				'org_url'                => '',
				'org_email'              => '',
				'org_phone'              => '',
				'org_address'            => '',
				'org_founding_date'      => '',
				'org_social_profiles'    => [],

				// Sitemap.
				'sitemap_enabled'        => true,
				'sitemap_post_types'     => [ 'post', 'page' ],
				'sitemap_taxonomies'     => [ 'category' ],
				'sitemap_max_entries'    => 1000,
				'sitemap_include_images' => true,
				'sitemap_ping_engines'   => true,

				// Social.
				'og_enabled'             => true,
				'og_default_image'       => '',
				'twitter_card_type'      => 'summary_large_image',
				'twitter_site'           => '',
				'facebook_app_id'        => '',

				// Redirects.
				'auto_redirect_slug_change' => true,
				'redirect_404_monitoring'   => true,
				'redirect_404_log_limit'    => 1000,

				// Image SEO.
				'image_auto_alt'         => true,
				'image_alt_template'     => '%filename%',
				'image_auto_title'       => false,

				// Breadcrumbs.
				'breadcrumb_enabled'     => true,
				'breadcrumb_separator'   => "\u{00BB}",
				'breadcrumb_home_text'   => 'Home',
				'breadcrumb_show_home'   => true,

				// AI / Auto-SEO.
				'auto_seo_enabled'       => false,
				'auto_seo_post_types'    => [ 'post' ],
				'auto_seo_fields'        => [ 'title', 'description', 'keyword', 'schema', 'og' ],
				'ai_prompt_title'        => '',
				'ai_prompt_description'  => '',
				'ai_prompt_optimization' => '',

				// Advanced.
				'remove_shortlinks'      => true,
				'remove_rsd_link'        => true,
				'remove_wlw_link'        => true,
				'remove_generator_tag'   => true,
				'add_trailing_slash'     => true,
				'strip_category_base'    => false,
			];

			add_option( 'seo_ai_settings', $defaults, '', 'no' );
		}

		// Provider settings.
		if ( false === get_option( 'seo_ai_providers' ) ) {
			$provider_defaults = [
				'active_provider' => 'ollama',
				'openai'          => [
					'api_key'     => '',
					'base_url'    => 'https://api.openai.com',
					'model'       => 'gpt-4o-mini',
					'temperature' => 0.3,
				],
				'claude'          => [
					'api_key'     => '',
					'base_url'    => 'https://api.anthropic.com',
					'model'       => 'claude-sonnet-4-5-20250929',
					'temperature' => 0.3,
					'max_tokens'  => 4096,
				],
				'gemini'          => [
					'api_key'     => '',
					'model'       => 'gemini-2.0-flash',
					'temperature' => 0.3,
				],
				'ollama'          => [
					'base_url'    => 'http://localhost:11434',
					'model'       => 'llama3.2',
					'temperature' => 0.3,
				],
				'openrouter'      => [
					'api_key'     => '',
					'model'       => 'anthropic/claude-sonnet-4-5-20250929',
					'temperature' => 0.3,
				],
			];

			add_option( 'seo_ai_providers', $provider_defaults, '', 'no' );
		}
	}

	/**
	 * Store the current plugin version.
	 *
	 * @return void
	 */
	private static function store_version(): void {
		update_option( 'seo_ai_version', SEO_AI_VERSION, 'no' );
	}
}
