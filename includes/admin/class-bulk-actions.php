<?php
/**
 * Bulk Actions for post list tables.
 *
 * Adds bulk SEO operations (AI optimization, noindex toggling) to
 * the admin post list screens.
 *
 * @package SeoAi\Admin
 * @since   1.0.0
 */

namespace SeoAi\Admin;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Helpers\Post_Meta;

/**
 * Class Bulk_Actions
 *
 * @since 1.0.0
 */
final class Bulk_Actions {

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
	 * Register hooks for each configured post type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$post_types = $this->get_supported_post_types();

		foreach ( $post_types as $post_type ) {
			add_filter( "bulk_actions-edit-{$post_type}", [ $this, 'add_bulk_actions' ] );
			add_filter( "handle_bulk_actions-edit-{$post_type}", [ $this, 'handle_bulk_action' ], 10, 3 );
		}

		add_action( 'admin_notices', [ $this, 'show_bulk_notices' ] );
	}

	/**
	 * Add SEO bulk actions to the dropdown.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $actions Existing bulk actions.
	 * @return string[] Modified bulk actions.
	 */
	public function add_bulk_actions( array $actions ): array {
		$actions['seo_ai_optimize']           = __( 'Optimize SEO with AI', 'seo-ai' );
		$actions['seo_ai_noindex']            = __( 'Set Noindex', 'seo-ai' );
		$actions['seo_ai_remove_noindex']     = __( 'Remove Noindex', 'seo-ai' );
		$actions['seo_ai_nofollow']           = __( 'Set Nofollow', 'seo-ai' );
		$actions['seo_ai_remove_nofollow']    = __( 'Remove Nofollow', 'seo-ai' );
		$actions['seo_ai_remove_canonical']   = __( 'Remove Custom Canonical', 'seo-ai' );
		$actions['seo_ai_set_schema_article'] = __( 'Set Schema: Article', 'seo-ai' );
		$actions['seo_ai_clear_seo_data']     = __( 'Clear All SEO Data', 'seo-ai' );
		$actions['seo_ai_reanalyze']          = __( 'Re-analyze SEO', 'seo-ai' );

		return $actions;
	}

	/**
	 * Handle bulk action execution.
	 *
	 * @since 1.0.0
	 *
	 * @param string $redirect_to The redirect URL after processing.
	 * @param string $action      The action being executed.
	 * @param int[]  $post_ids    Array of selected post IDs.
	 * @return string Modified redirect URL with result query args.
	 */
	public function handle_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		$count = 0;

		switch ( $action ) {
			case 'seo_ai_optimize':
				$count = $this->bulk_optimize( $post_ids );
				$redirect_to = add_query_arg( 'seo_ai_optimized', $count, $redirect_to );
				break;

			case 'seo_ai_noindex':
				$count = $this->bulk_set_robots( $post_ids, 'noindex', true );
				$redirect_to = add_query_arg( 'seo_ai_noindexed', $count, $redirect_to );
				break;

			case 'seo_ai_remove_noindex':
				$count = $this->bulk_set_robots( $post_ids, 'noindex', false );
				$redirect_to = add_query_arg( 'seo_ai_noindex_removed', $count, $redirect_to );
				break;

			case 'seo_ai_nofollow':
				$count = $this->bulk_set_robots( $post_ids, 'nofollow', true );
				$redirect_to = add_query_arg( 'seo_ai_bulk_updated', $count, $redirect_to );
				break;

			case 'seo_ai_remove_nofollow':
				$count = $this->bulk_set_robots( $post_ids, 'nofollow', false );
				$redirect_to = add_query_arg( 'seo_ai_bulk_updated', $count, $redirect_to );
				break;

			case 'seo_ai_remove_canonical':
				$count = $this->bulk_remove_meta( $post_ids, 'canonical' );
				$redirect_to = add_query_arg( 'seo_ai_bulk_updated', $count, $redirect_to );
				break;

			case 'seo_ai_set_schema_article':
				$count = $this->bulk_set_meta( $post_ids, 'schema_type', 'Article' );
				$redirect_to = add_query_arg( 'seo_ai_bulk_updated', $count, $redirect_to );
				break;

			case 'seo_ai_clear_seo_data':
				$count = $this->bulk_clear_seo_data( $post_ids );
				$redirect_to = add_query_arg( 'seo_ai_bulk_updated', $count, $redirect_to );
				break;

			case 'seo_ai_reanalyze':
				$count = $this->bulk_reanalyze( $post_ids );
				$redirect_to = add_query_arg( 'seo_ai_bulk_updated', $count, $redirect_to );
				break;
		}

		return $redirect_to;
	}

	/**
	 * Display admin notices for completed bulk actions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function show_bulk_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice display.

		if ( isset( $_GET['seo_ai_optimized'] ) ) {
			$count = (int) $_GET['seo_ai_optimized'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: %d: number of posts queued for optimization */
					_n(
						'%d post queued for AI SEO optimization.',
						'%d posts queued for AI SEO optimization.',
						$count,
						'seo-ai'
					),
					$count
				) )
			);
		}

		if ( isset( $_GET['seo_ai_noindexed'] ) ) {
			$count = (int) $_GET['seo_ai_noindexed'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: %d: number of posts set to noindex */
					_n(
						'Noindex set on %d post.',
						'Noindex set on %d posts.',
						$count,
						'seo-ai'
					),
					$count
				) )
			);
		}

		if ( isset( $_GET['seo_ai_noindex_removed'] ) ) {
			$count = (int) $_GET['seo_ai_noindex_removed'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: %d: number of posts with noindex removed */
					_n(
						'Noindex removed from %d post.',
						'Noindex removed from %d posts.',
						$count,
						'seo-ai'
					),
					$count
				) )
			);
		}

		if ( isset( $_GET['seo_ai_bulk_updated'] ) ) {
			$count = (int) $_GET['seo_ai_bulk_updated'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: %d: number of posts updated */
					_n(
						'SEO data updated on %d post.',
						'SEO data updated on %d posts.',
						$count,
						'seo-ai'
					),
					$count
				) )
			);
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Queue AI optimization for each selected post.
	 *
	 * Uses wp_schedule_single_event to avoid timeout for large batches.
	 * If the AI optimizer class is available, it can process immediately
	 * for small batches.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $post_ids Array of post IDs to optimize.
	 * @return int Number of posts successfully queued.
	 */
	private function bulk_optimize( array $post_ids ): int {
		$count          = 0;
		$enabled_fields = $this->options->get( 'auto_seo_fields', [ 'title', 'description', 'keyword', 'schema', 'og' ] );

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post || empty( $post->post_content ) ) {
				continue;
			}

			// Schedule the optimization as an async event to avoid timeouts.
			$scheduled = wp_schedule_single_event(
				time() + ( $count * 5 ), // Stagger by 5 seconds to respect rate limits.
				'seo_ai/async_optimize_post',
				[ $post_id, $enabled_fields ]
			);

			if ( false !== $scheduled ) {
				// Mark the post as pending optimization.
				$this->post_meta->set( $post_id, 'optimization_status', 'pending' );
				$count++;
			}
		}

		// Log the bulk operation.
		if ( $count > 0 && class_exists( 'SeoAi\\Activity_Log' ) ) {
			\SeoAi\Activity_Log::log( 'info', 'bulk_optimize', sprintf(
				/* translators: %d: number of posts */
				__( 'Queued %d posts for bulk AI optimization', 'seo-ai' ),
				$count
			), [
				'post_ids' => array_map( 'intval', $post_ids ),
				'count'    => $count,
			] );
		}

		return $count;
	}

	/**
	 * Bulk set or remove a robots directive on selected posts.
	 *
	 * @since 0.3.0
	 *
	 * @param int[]  $post_ids  Array of post IDs.
	 * @param string $directive The directive (e.g. 'noindex', 'nofollow').
	 * @param bool   $add       True to add, false to remove.
	 * @return int Number of posts updated.
	 */
	private function bulk_set_robots( array $post_ids, string $directive, bool $add ): int {
		$count = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$robots = $this->post_meta->get( $post_id, 'robots', [] );

			if ( ! is_array( $robots ) ) {
				$robots = [];
			}

			if ( $add ) {
				if ( ! in_array( $directive, $robots, true ) ) {
					$robots[] = $directive;
				}
			} else {
				$robots = array_values( array_diff( $robots, [ $directive ] ) );
			}

			$this->post_meta->set( $post_id, 'robots', $robots );
			$count++;
		}

		return $count;
	}

	/**
	 * Bulk set a specific meta value on selected posts.
	 *
	 * @since 0.3.0
	 *
	 * @param int[]  $post_ids Array of post IDs.
	 * @param string $key      Meta key (without prefix).
	 * @param mixed  $value    Value to set.
	 * @return int Number of posts updated.
	 */
	private function bulk_set_meta( array $post_ids, string $key, mixed $value ): int {
		$count = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$this->post_meta->set( $post_id, $key, $value );
			$count++;
		}

		return $count;
	}

	/**
	 * Bulk remove a specific meta value from selected posts.
	 *
	 * @since 0.3.0
	 *
	 * @param int[]  $post_ids Array of post IDs.
	 * @param string $key      Meta key (without prefix).
	 * @return int Number of posts updated.
	 */
	private function bulk_remove_meta( array $post_ids, string $key ): int {
		$count = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$this->post_meta->delete( $post_id, $key );
			$count++;
		}

		return $count;
	}

	/**
	 * Bulk clear all SEO data from selected posts.
	 *
	 * @since 0.3.0
	 *
	 * @param int[] $post_ids Array of post IDs.
	 * @return int Number of posts cleared.
	 */
	private function bulk_clear_seo_data( array $post_ids ): int {
		$count = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$this->post_meta->delete_all( $post_id );
			$count++;
		}

		return $count;
	}

	/**
	 * Bulk re-analyze SEO scores for selected posts.
	 *
	 * @since 0.3.0
	 *
	 * @param int[] $post_ids Array of post IDs.
	 * @return int Number of posts re-analyzed.
	 */
	private function bulk_reanalyze( array $post_ids ): int {
		$count    = 0;
		$analyzer = new \SeoAi\Modules\Content_Analysis\Analyzer();

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$result = $analyzer->analyze( $post_id );

			if ( isset( $result['seo']['score'] ) ) {
				$this->post_meta->set( $post_id, 'seo_score', $result['seo']['score'] );
			}

			if ( isset( $result['readability']['score'] ) ) {
				$this->post_meta->set( $post_id, 'readability_score', $result['readability']['score'] );
			}

			$count++;
		}

		return $count;
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
}
