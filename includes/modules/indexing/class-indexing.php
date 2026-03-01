<?php
/**
 * Instant Indexing via IndexNow and Bing API.
 *
 * Submits URLs to search engines for immediate crawling when
 * posts are published or updated, supporting both the IndexNow
 * protocol and the Bing URL Submission API.
 *
 * @package SeoAi\Modules\Indexing
 * @since   0.5.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Indexing;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Activity_Log;

/**
 * Class Indexing
 *
 * Handles automatic and manual URL submission to IndexNow and Bing
 * Webmaster Tools for faster search engine indexing.
 *
 * @since 0.5.0
 */
class Indexing {

	/**
	 * IndexNow API endpoint.
	 *
	 * @var string
	 */
	private const INDEXNOW_API_URL = 'https://api.indexnow.org/indexnow';

	/**
	 * Bing URL Submission API endpoint.
	 *
	 * @var string
	 */
	private const BING_API_URL = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrl';

	/**
	 * Options helper instance.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Options $options Options helper instance.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register WordPress hooks for indexing functionality.
	 *
	 * Hooks into post status transitions for automatic submission
	 * and admin_init for manual indexing requests.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'transition_post_status', [ $this, 'on_post_publish' ], 10, 3 );
		add_action( 'admin_init', [ $this, 'handle_manual_indexing' ] );
	}

	/**
	 * Submit a URL to the IndexNow API.
	 *
	 * Sends the URL along with the site's IndexNow key for instant
	 * indexing across participating search engines.
	 *
	 * @since 0.5.0
	 *
	 * @param string $url The URL to submit for indexing.
	 * @return array{success: bool, message: string}
	 */
	public function submit_url( string $url ): array {
		$key      = $this->get_indexnow_key();
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$endpoint = self::INDEXNOW_API_URL;

		$body = wp_json_encode( [
			'host'   => $host,
			'key'    => $key,
			'urlList' => [ $url ],
		] );

		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8',
			],
			'body'    => $body,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return [
				'success' => true,
				'message' => sprintf(
					/* translators: %s: submitted URL */
					__( 'URL submitted to IndexNow successfully: %s', 'seo-ai' ),
					$url
				),
			];
		}

		return [
			'success' => false,
			'message' => sprintf(
				/* translators: 1: HTTP status code, 2: response body */
				__( 'IndexNow API returned HTTP %1$d: %2$s', 'seo-ai' ),
				$code,
				wp_remote_retrieve_body( $response )
			),
		];
	}

	/**
	 * Submit a URL to the Bing URL Submission API.
	 *
	 * Requires a valid Bing Webmaster Tools API key configured
	 * in the plugin settings.
	 *
	 * @since 0.5.0
	 *
	 * @param string $url The URL to submit for indexing.
	 * @return array{success: bool, message: string}
	 */
	public function submit_to_bing( string $url ): array {
		$api_key = $this->options->get( 'bing_api_key', '' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'message' => __( 'Bing API key is not configured.', 'seo-ai' ),
			];
		}

		$site_url = home_url( '/' );
		$endpoint = add_query_arg( 'apikey', $api_key, self::BING_API_URL );

		$body = wp_json_encode( [
			'siteUrl' => $site_url,
			'url'     => $url,
		] );

		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8',
			],
			'body'    => $body,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return [
				'success' => true,
				'message' => sprintf(
					/* translators: %s: submitted URL */
					__( 'URL submitted to Bing successfully: %s', 'seo-ai' ),
					$url
				),
			];
		}

		return [
			'success' => false,
			'message' => sprintf(
				/* translators: 1: HTTP status code, 2: response body */
				__( 'Bing API returned HTTP %1$d: %2$s', 'seo-ai' ),
				$code,
				wp_remote_retrieve_body( $response )
			),
		];
	}

	/**
	 * Get the IndexNow verification key.
	 *
	 * Returns the stored key from plugin options, generating and
	 * persisting a new random 32-character hex string if none exists.
	 *
	 * @since 0.5.0
	 *
	 * @return string The IndexNow key.
	 */
	public function get_indexnow_key(): string {
		$key = $this->options->get( 'indexnow_key', '' );

		if ( ! empty( $key ) ) {
			return $key;
		}

		$key = bin2hex( random_bytes( 16 ) );

		$this->options->set( 'indexnow_key', $key )->save();

		return $key;
	}

	/**
	 * Handle automatic URL submission on post publish or update.
	 *
	 * Fires on the `transition_post_status` hook. Only processes
	 * transitions to 'publish' for supported post types when
	 * auto-submit is enabled in settings.
	 *
	 * @since 0.5.0
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 */
	private function on_post_publish( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'publish' !== $new_status ) {
			return;
		}

		$supported_types = $this->options->get( 'analysis_post_types', [ 'post', 'page' ] );

		if ( ! is_array( $supported_types ) ) {
			$supported_types = [ 'post', 'page' ];
		}

		if ( ! in_array( $post->post_type, $supported_types, true ) ) {
			return;
		}

		if ( ! $this->options->get( 'indexing_auto_submit', false ) ) {
			return;
		}

		$url = get_permalink( $post );

		if ( ! $url ) {
			return;
		}

		// Submit to IndexNow.
		$result = $this->submit_url( $url );

		$this->log_result( 'IndexNow', $url, $result );

		// Submit to Bing if API key is configured.
		$bing_api_key = $this->options->get( 'bing_api_key', '' );

		if ( ! empty( $bing_api_key ) ) {
			$bing_result = $this->submit_to_bing( $url );

			$this->log_result( 'Bing', $url, $bing_result );
		}
	}

	/**
	 * Handle manual indexing request from admin.
	 *
	 * Processes the `seo_ai_index_url` GET parameter to manually
	 * submit a URL for indexing from the WordPress admin.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function handle_manual_indexing(): void {
		if ( ! isset( $_GET['seo_ai_index_url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$url = esc_url_raw( wp_unslash( $_GET['seo_ai_index_url'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $url ) ) {
			return;
		}

		$result = $this->submit_url( $url );

		$this->log_result( 'IndexNow', $url, $result );

		// Submit to Bing if API key is configured.
		$bing_api_key = $this->options->get( 'bing_api_key', '' );

		if ( ! empty( $bing_api_key ) ) {
			$bing_result = $this->submit_to_bing( $url );

			$this->log_result( 'Bing', $url, $bing_result );
		}
	}

	/**
	 * Log the result of a URL submission to the Activity Log.
	 *
	 * Only logs if the Activity_Log class is available.
	 *
	 * @since 0.5.0
	 *
	 * @param string $service Service name (e.g., 'IndexNow', 'Bing').
	 * @param string $url     The submitted URL.
	 * @param array  $result  Result array with 'success' and 'message' keys.
	 * @return void
	 */
	private function log_result( string $service, string $url, array $result ): void {
		if ( ! class_exists( Activity_Log::class ) ) {
			return;
		}

		if ( $result['success'] ) {
			Activity_Log::log(
				'info',
				'indexing',
				sprintf( 'Submitted URL to %s', $service ),
				[
					'url'     => $url,
					'service' => $service,
				]
			);
		} else {
			Activity_Log::log(
				'error',
				'indexing',
				sprintf( 'Failed to submit URL to %s: %s', $service, $result['message'] ),
				[
					'url'     => $url,
					'service' => $service,
					'error'   => $result['message'],
				]
			);
		}
	}
}
