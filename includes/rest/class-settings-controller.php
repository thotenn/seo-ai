<?php
/**
 * Settings REST Controller.
 *
 * Exposes endpoints for reading and writing the plugin's main settings
 * and AI provider configuration.
 *
 * @package SeoAi\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Rest;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Settings_Controller
 *
 * Handles the `/seo-ai/v1/settings` endpoints.
 *
 * @since 1.0.0
 */
final class Settings_Controller extends Rest_Controller {

	/**
	 * Option key for main plugin settings.
	 *
	 * @var string
	 */
	private const SETTINGS_KEY = 'seo_ai_settings';

	/**
	 * Option key for provider settings.
	 *
	 * @var string
	 */
	private const PROVIDERS_KEY = 'seo_ai_providers';

	/**
	 * Register routes for settings management.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET & POST /settings
		register_rest_route(
			$this->namespace,
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'settings' => [
							'type'     => 'object',
							'required' => true,
						],
					],
				],
			]
		);

		// POST /settings/providers
		register_rest_route(
			$this->namespace,
			'/settings/providers',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_providers' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'providers' => [
						'type'     => 'object',
						'required' => true,
					],
				],
			]
		);

		// POST /settings/reset
		register_rest_route(
			$this->namespace,
			'/settings/reset',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'reset_settings' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);
	}

	/**
	 * Retrieve all plugin settings and provider configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings  = get_option( self::SETTINGS_KEY, [] );
		$providers = get_option( self::PROVIDERS_KEY, [] );

		// Mask sensitive API keys in the response.
		$providers = $this->mask_provider_secrets( $providers );

		return $this->success( [
			'settings'  => is_array( $settings ) ? $settings : [],
			'providers' => is_array( $providers ) ? $providers : [],
		] );
	}

	/**
	 * Update main plugin settings.
	 *
	 * Merges the incoming settings into the existing option. Only keys
	 * present in the request are overwritten.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		$incoming = $request->get_param( 'settings' );

		if ( ! is_array( $incoming ) ) {
			return $this->error( __( 'Settings must be an object.', 'seo-ai' ) );
		}

		$current  = get_option( self::SETTINGS_KEY, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}

		$sanitized = $this->sanitize_settings( $incoming );
		$merged    = array_merge( $current, $sanitized );

		update_option( self::SETTINGS_KEY, $merged, false );

		// Flush the Options helper cache so subsequent reads see the new values.
		$this->plugin->options()->flush_cache();

		return $this->success(
			[ 'settings' => $merged ],
			__( 'Settings saved.', 'seo-ai' )
		);
	}

	/**
	 * Update AI provider settings.
	 *
	 * Replaces the entire `seo_ai_providers` option with the supplied data
	 * after sanitization.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_providers( WP_REST_Request $request ) {
		$incoming = $request->get_param( 'providers' );

		if ( ! is_array( $incoming ) ) {
			return $this->error( __( 'Providers must be an object.', 'seo-ai' ) );
		}

		$current = get_option( self::PROVIDERS_KEY, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}

		$sanitized = $this->sanitize_provider_settings( $incoming, $current );

		update_option( self::PROVIDERS_KEY, $sanitized, false );

		// Flush the Options helper cache so subsequent reads see the new values.
		$this->plugin->options()->flush_cache();

		return $this->success(
			[ 'providers' => $this->mask_provider_secrets( $sanitized ) ],
			__( 'Provider settings saved.', 'seo-ai' )
		);
	}

	/**
	 * Reset main plugin settings to their defaults.
	 *
	 * Provider settings are intentionally preserved so that API keys
	 * are not accidentally wiped.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function reset_settings( WP_REST_Request $request ): WP_REST_Response {
		$defaults = $this->get_default_settings();

		update_option( self::SETTINGS_KEY, $defaults, false );

		// Flush the Options helper cache.
		$this->plugin->options()->flush_cache();

		return $this->success(
			[ 'settings' => $defaults ],
			__( 'Settings reset to defaults.', 'seo-ai' )
		);
	}

	// -------------------------------------------------------------------------
	// Sanitization Helpers
	// -------------------------------------------------------------------------

	/**
	 * Sanitize incoming main settings.
	 *
	 * @param array $data Raw input data.
	 *
	 * @return array Sanitized settings.
	 */
	private function sanitize_settings( array $data ): array {
		$sanitized = [];

		// Boolean fields.
		$booleans = [
			'sitemap_enabled', 'sitemap_include_images', 'sitemap_ping_engines',
			'og_enabled', 'auto_redirect_slug_change', 'redirect_404_monitoring',
			'image_auto_alt', 'image_auto_title', 'breadcrumb_enabled',
			'breadcrumb_show_home', 'auto_seo_enabled', 'remove_shortlinks',
			'remove_rsd_link', 'remove_wlw_link', 'remove_generator_tag',
			'add_trailing_slash', 'strip_category_base',
			'pt_post_noindex', 'pt_page_noindex',
			'tax_category_noindex', 'tax_post_tag_noindex',
		];

		// Text fields.
		$text_fields = [
			'title_separator', 'default_title', 'default_description',
			'homepage_title', 'homepage_description',
			'pt_post_title', 'pt_post_description', 'pt_post_schema',
			'pt_page_title', 'pt_page_description', 'pt_page_schema',
			'tax_category_title', 'tax_post_tag_title',
			'schema_type', 'org_name', 'org_description', 'org_email',
			'org_phone', 'org_address', 'org_founding_date',
			'twitter_card_type', 'twitter_site', 'facebook_app_id',
			'image_alt_template', 'breadcrumb_separator', 'breadcrumb_home_text',
			'ai_prompt_title', 'ai_prompt_description', 'ai_prompt_optimization',
		];

		// URL fields.
		$url_fields = [ 'org_logo', 'org_url', 'og_default_image' ];

		// Integer fields.
		$int_fields = [
			'min_content_length', 'sitemap_max_entries', 'redirect_404_log_limit',
		];

		// Float fields.
		$float_fields = [ 'keyword_density_min', 'keyword_density_max' ];

		// Array fields (lists of strings).
		$array_fields = [
			'enabled_modules', 'analysis_post_types', 'sitemap_post_types',
			'sitemap_taxonomies', 'org_social_profiles', 'auto_seo_post_types',
			'auto_seo_fields',
		];

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $booleans, true ) ) {
				$sanitized[ $key ] = (bool) $value;
			} elseif ( in_array( $key, $text_fields, true ) ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			} elseif ( in_array( $key, $url_fields, true ) ) {
				$sanitized[ $key ] = esc_url_raw( (string) $value );
			} elseif ( in_array( $key, $int_fields, true ) ) {
				$sanitized[ $key ] = absint( $value );
			} elseif ( in_array( $key, $float_fields, true ) ) {
				$sanitized[ $key ] = (float) $value;
			} elseif ( in_array( $key, $array_fields, true ) ) {
				$sanitized[ $key ] = is_array( $value )
					? array_map( 'sanitize_text_field', $value )
					: [];
			}
			// Unknown keys are silently dropped to prevent injection.
		}

		return $sanitized;
	}

	/**
	 * Sanitize incoming provider settings.
	 *
	 * Preserves existing API keys when the incoming value is a masked
	 * placeholder or empty.
	 *
	 * @param array $incoming Raw input data.
	 * @param array $current  Current stored provider settings.
	 *
	 * @return array Sanitized provider settings.
	 */
	private function sanitize_provider_settings( array $incoming, array $current ): array {
		$sanitized = [];

		// Active provider.
		if ( isset( $incoming['active_provider'] ) ) {
			$sanitized['active_provider'] = sanitize_text_field( (string) $incoming['active_provider'] );
		} elseif ( isset( $current['active_provider'] ) ) {
			$sanitized['active_provider'] = $current['active_provider'];
		} else {
			$sanitized['active_provider'] = 'ollama';
		}

		// Provider-specific settings.
		$provider_ids = [ 'openai', 'claude', 'gemini', 'ollama', 'openrouter' ];

		foreach ( $provider_ids as $pid ) {
			$provider_data  = $incoming[ $pid ] ?? [];
			$existing_data  = $current[ $pid ] ?? [];

			if ( ! is_array( $provider_data ) ) {
				$sanitized[ $pid ] = $existing_data;
				continue;
			}

			$clean = [];

			// API key - keep existing if masked or empty.
			if ( isset( $provider_data['api_key'] ) ) {
				$key = trim( (string) $provider_data['api_key'] );
				if ( '' === $key || str_contains( $key, '***' ) ) {
					$clean['api_key'] = $existing_data['api_key'] ?? '';
				} else {
					$clean['api_key'] = sanitize_text_field( $key );
				}
			} elseif ( isset( $existing_data['api_key'] ) ) {
				$clean['api_key'] = $existing_data['api_key'];
			}

			// Base URL.
			if ( isset( $provider_data['base_url'] ) ) {
				$clean['base_url'] = esc_url_raw( (string) $provider_data['base_url'] );
			} elseif ( isset( $existing_data['base_url'] ) ) {
				$clean['base_url'] = $existing_data['base_url'];
			}

			// Model.
			if ( isset( $provider_data['model'] ) ) {
				$clean['model'] = sanitize_text_field( (string) $provider_data['model'] );
			} elseif ( isset( $existing_data['model'] ) ) {
				$clean['model'] = $existing_data['model'];
			}

			// Temperature.
			if ( isset( $provider_data['temperature'] ) ) {
				$clean['temperature'] = max( 0.0, min( 2.0, (float) $provider_data['temperature'] ) );
			} elseif ( isset( $existing_data['temperature'] ) ) {
				$clean['temperature'] = $existing_data['temperature'];
			}

			// Max tokens (optional, used by Claude).
			if ( isset( $provider_data['max_tokens'] ) ) {
				$clean['max_tokens'] = absint( $provider_data['max_tokens'] );
			} elseif ( isset( $existing_data['max_tokens'] ) ) {
				$clean['max_tokens'] = $existing_data['max_tokens'];
			}

			// Custom prompt.
			if ( isset( $provider_data['custom_prompt'] ) ) {
				$clean['custom_prompt'] = sanitize_textarea_field( (string) $provider_data['custom_prompt'] );
			} elseif ( isset( $existing_data['custom_prompt'] ) ) {
				$clean['custom_prompt'] = $existing_data['custom_prompt'];
			}

			// Cost per 1M tokens (input).
			if ( isset( $provider_data['cost_input'] ) ) {
				$clean['cost_input'] = max( 0.0, (float) $provider_data['cost_input'] );
			} elseif ( isset( $existing_data['cost_input'] ) ) {
				$clean['cost_input'] = $existing_data['cost_input'];
			}

			// Cost per 1M tokens (output).
			if ( isset( $provider_data['cost_output'] ) ) {
				$clean['cost_output'] = max( 0.0, (float) $provider_data['cost_output'] );
			} elseif ( isset( $existing_data['cost_output'] ) ) {
				$clean['cost_output'] = $existing_data['cost_output'];
			}

			$sanitized[ $pid ] = $clean;
		}

		return $sanitized;
	}

	/**
	 * Mask sensitive secrets (API keys) in provider settings for safe
	 * transmission over the REST API.
	 *
	 * @param array $providers Provider settings array.
	 *
	 * @return array Provider settings with masked API keys.
	 */
	private function mask_provider_secrets( array $providers ): array {
		$provider_ids = [ 'openai', 'claude', 'gemini', 'ollama', 'openrouter' ];

		foreach ( $provider_ids as $pid ) {
			if ( ! isset( $providers[ $pid ]['api_key'] ) ) {
				continue;
			}

			$key = (string) $providers[ $pid ]['api_key'];

			if ( '' === $key ) {
				continue;
			}

			// Show the first 4 and last 4 characters, mask the rest.
			$len = strlen( $key );
			if ( $len <= 8 ) {
				$providers[ $pid ]['api_key'] = str_repeat( '*', $len );
			} else {
				$providers[ $pid ]['api_key'] = substr( $key, 0, 4 )
					. str_repeat( '*', $len - 8 )
					. substr( $key, -4 );
			}
		}

		return $providers;
	}

	/**
	 * Get the default settings values.
	 *
	 * Mirrors the defaults defined in the Activator class.
	 *
	 * @return array
	 */
	private function get_default_settings(): array {
		return [
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

			'title_separator'      => "\u{2013}",
			'default_title'        => '%title% %sep% %sitename%',
			'default_description'  => '',
			'homepage_title'       => '%sitename% %sep% %tagline%',
			'homepage_description' => '',

			'pt_post_title'        => '%title% %sep% %sitename%',
			'pt_post_description'  => '%excerpt%',
			'pt_post_schema'       => 'Article',
			'pt_post_noindex'      => false,
			'pt_page_title'        => '%title% %sep% %sitename%',
			'pt_page_description'  => '%excerpt%',
			'pt_page_schema'       => 'WebPage',
			'pt_page_noindex'      => false,

			'tax_category_title'     => '%term_title% %sep% %sitename%',
			'tax_category_noindex'   => false,
			'tax_post_tag_title'     => '%term_title% %sep% %sitename%',
			'tax_post_tag_noindex'   => true,

			'analysis_post_types'  => [ 'post', 'page' ],
			'min_content_length'   => 300,
			'keyword_density_min'  => 1.0,
			'keyword_density_max'  => 3.0,

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

			'sitemap_enabled'        => true,
			'sitemap_post_types'     => [ 'post', 'page' ],
			'sitemap_taxonomies'     => [ 'category' ],
			'sitemap_max_entries'    => 1000,
			'sitemap_include_images' => true,
			'sitemap_ping_engines'   => true,

			'og_enabled'             => true,
			'og_default_image'       => '',
			'twitter_card_type'      => 'summary_large_image',
			'twitter_site'           => '',
			'facebook_app_id'        => '',

			'auto_redirect_slug_change' => true,
			'redirect_404_monitoring'   => true,
			'redirect_404_log_limit'    => 1000,

			'image_auto_alt'         => true,
			'image_alt_template'     => '%filename%',
			'image_auto_title'       => false,

			'breadcrumb_enabled'     => true,
			'breadcrumb_separator'   => "\u{00BB}",
			'breadcrumb_home_text'   => 'Home',
			'breadcrumb_show_home'   => true,

			'auto_seo_enabled'       => false,
			'auto_seo_post_types'    => [ 'post' ],
			'auto_seo_fields'        => [ 'title', 'description', 'keyword', 'schema', 'og' ],
			'ai_prompt_title'        => '',
			'ai_prompt_description'  => '',
			'ai_prompt_optimization' => '',

			'remove_shortlinks'      => true,
			'remove_rsd_link'        => true,
			'remove_wlw_link'        => true,
			'remove_generator_tag'   => true,
			'add_trailing_slash'     => true,
			'strip_category_base'    => false,
		];
	}
}
