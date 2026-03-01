<?php
namespace SeoAi;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin deactivation handler.
 *
 * Cleans up transients and flushes rewrite rules on deactivation.
 *
 * @since 1.0.0
 */
class Deactivator {

	/**
	 * Transient prefix used by the plugin.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'seo_ai_';

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		self::cleanup_transients();
		flush_rewrite_rules();

		/**
		 * Fires after the SEO AI plugin is deactivated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'seo_ai/deactivate' );
	}

	/**
	 * Delete all transients created by the plugin.
	 *
	 * Handles both standard and site transients. Works with
	 * both database-stored and external object cache transients.
	 *
	 * @return void
	 */
	private static function cleanup_transients(): void {
		global $wpdb;

		// Delete standard transients stored in the options table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::TRANSIENT_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::TRANSIENT_PREFIX ) . '%'
			)
		);

		// Delete site transients for multisite installations.
		if ( is_multisite() ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
					$wpdb->esc_like( '_site_transient_' . self::TRANSIENT_PREFIX ) . '%',
					$wpdb->esc_like( '_site_transient_timeout_' . self::TRANSIENT_PREFIX ) . '%'
				)
			);
		}

		// If an external object cache is in use, delete known transient keys.
		if ( wp_using_ext_object_cache() ) {
			$known_transients = [
				'seo_ai_sitemap_index',
				'seo_ai_sitemap_post',
				'seo_ai_sitemap_page',
				'seo_ai_sitemap_category',
				'seo_ai_schema_cache',
				'seo_ai_provider_models',
			];

			foreach ( $known_transients as $transient ) {
				delete_transient( $transient );
			}
		}
	}
}
