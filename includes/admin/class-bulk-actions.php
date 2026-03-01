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
		$actions['seo_ai_optimize']        = __( 'Optimize SEO with AI', 'seo-ai' );
		$actions['seo_ai_noindex']         = __( 'Set Noindex', 'seo-ai' );
		$actions['seo_ai_remove_noindex']  = __( 'Remove Noindex', 'seo-ai' );

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
				$count = $this->bulk_set_noindex( $post_ids, true );
				$redirect_to = add_query_arg( 'seo_ai_noindexed', $count, $redirect_to );
				break;

			case 'seo_ai_remove_noindex':
				$count = $this->bulk_set_noindex( $post_ids, false );
				$redirect_to = add_query_arg( 'seo_ai_noindex_removed', $count, $redirect_to );
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

		return $count;
	}

	/**
	 * Bulk set or remove noindex on selected posts.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $post_ids Array of post IDs.
	 * @param bool  $noindex  True to set noindex, false to remove it.
	 * @return int Number of posts updated.
	 */
	private function bulk_set_noindex( array $post_ids, bool $noindex ): int {
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

			if ( $noindex ) {
				if ( ! in_array( 'noindex', $robots, true ) ) {
					$robots[] = 'noindex';
				}
			} else {
				$robots = array_values( array_diff( $robots, [ 'noindex' ] ) );
			}

			$this->post_meta->set( $post_id, 'robots', $robots );
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
