<?php
/**
 * Automatic SEO Optimization on Post Save/Publish.
 *
 * Triggers AI-powered SEO optimization when posts are saved or
 * published, based on global and per-post settings.
 *
 * @package SeoAi\Frontend
 * @since   1.0.0
 */

namespace SeoAi\Frontend;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Helpers\Post_Meta;
use SeoAi\Providers\Provider_Manager;

/**
 * Class Auto_SEO
 *
 * @since 1.0.0
 */
final class Auto_SEO {

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
	 * Provider manager.
	 *
	 * @var Provider_Manager
	 */
	private Provider_Manager $provider_manager;

	/**
	 * Static flag to prevent recursive optimization.
	 *
	 * @var bool
	 */
	private static bool $is_running = false;

	/**
	 * Constructor.
	 *
	 * @param Options          $options          Options helper instance.
	 * @param Post_Meta        $post_meta        Post meta helper instance.
	 * @param Provider_Manager $provider_manager Provider manager instance.
	 */
	public function __construct( Options $options, Post_Meta $post_meta, Provider_Manager $provider_manager ) {
		$this->options          = $options;
		$this->post_meta        = $post_meta;
		$this->provider_manager = $provider_manager;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'save_post', [ $this, 'maybe_optimize' ], 20, 3 );
		add_action( 'transition_post_status', [ $this, 'on_publish' ], 10, 3 );

		// Handle async optimization scheduled by bulk actions.
		add_action( 'seo_ai/async_optimize_post', [ $this, 'async_optimize' ], 10, 2 );
	}

	/**
	 * Conditionally optimize a post on save.
	 *
	 * Checks all preconditions before triggering AI optimization:
	 * global toggle, post type, per-post toggle, provider availability,
	 * autosave/revision status, and recursion guard.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id The post ID.
	 * @param \WP_Post $post    The post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function maybe_optimize( int $post_id, \WP_Post $post, bool $update ): void {
		// 1. Prevent recursion.
		if ( self::$is_running ) {
			return;
		}

		// 2. Skip autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// 3. Skip revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// 4. Check if auto-SEO is enabled globally.
		if ( ! $this->options->get( 'auto_seo_enabled', false ) ) {
			return;
		}

		// 5. Check if the post type is in the auto_seo_post_types list.
		$allowed_types = $this->options->get( 'auto_seo_post_types', [ 'post' ] );

		if ( ! is_array( $allowed_types ) || ! in_array( $post->post_type, $allowed_types, true ) ) {
			return;
		}

		// 6. Check per-post toggle.
		$per_post = $this->post_meta->get( $post_id, 'auto_seo', 'default' );

		if ( 'no' === $per_post ) {
			return;
		}

		// If set to 'default', the global setting already passed. If 'yes', proceed.

		// 7. Check if an AI provider is configured.
		$active_provider = $this->provider_manager->get_active_provider();

		if ( null === $active_provider ) {
			return;
		}

		// 8. Only optimize posts that have content.
		if ( empty( trim( $post->post_content ) ) ) {
			return;
		}

		// 9. Only optimize published or draft posts, not trashed, etc.
		if ( ! in_array( $post->post_status, [ 'publish', 'draft', 'pending', 'future' ], true ) ) {
			return;
		}

		// Run the optimization.
		$this->run_optimization( $post_id );
	}

	/**
	 * Handle post status transitions to 'publish'.
	 *
	 * Optimizes when a post transitions to 'publish' for the first time
	 * (e.g., from 'draft' or 'auto-draft').
	 *
	 * @since 1.0.0
	 *
	 * @param string   $new_status The new post status.
	 * @param string   $old_status The old post status.
	 * @param \WP_Post $post       The post object.
	 * @return void
	 */
	public function on_publish( string $new_status, string $old_status, \WP_Post $post ): void {
		// Only trigger on first publish transition.
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// Prevent double-fire with save_post (the save_post handler also fires on publish).
		// We use this hook only for first-publish scenarios where maybe_optimize
		// might not fire (e.g., REST API quick-publish without going through save_post).
		if ( self::$is_running ) {
			return;
		}

		// Check if SEO meta already exists (maybe_optimize already ran).
		$existing_title = $this->post_meta->get( $post->ID, 'title' );

		if ( ! empty( $existing_title ) ) {
			return;
		}

		// Delegate to maybe_optimize with the same checks.
		$this->maybe_optimize( $post->ID, $post, true );
	}

	/**
	 * Handle async optimization (scheduled via wp_schedule_single_event).
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id        The post ID to optimize.
	 * @param array $enabled_fields Fields to optimize.
	 * @return void
	 */
	public function async_optimize( int $post_id, array $enabled_fields ): void {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$this->run_optimization( $post_id, $enabled_fields );

		// Clear the pending status.
		$this->post_meta->delete( $post_id, 'optimization_status' );
	}

	/**
	 * Execute the AI optimization for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int        $post_id        The post ID to optimize.
	 * @param array|null $enabled_fields Optional specific fields to optimize.
	 * @return bool True if optimization ran successfully, false otherwise.
	 */
	private function run_optimization( int $post_id, ?array $enabled_fields = null ): bool {
		// Set recursion guard.
		self::$is_running = true;

		if ( null === $enabled_fields ) {
			$enabled_fields = $this->options->get(
				'auto_seo_fields',
				[ 'title', 'description', 'keyword', 'schema', 'og' ]
			);
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			self::$is_running = false;
			return false;
		}

		$success = false;

		try {
			// Attempt to use the AI optimizer if available.
			if ( class_exists( 'SeoAi\\Ai\\Ai_Optimizer' ) ) {
				/** @var \SeoAi\Ai\Ai_Optimizer $optimizer */
				$optimizer = new \SeoAi\Ai\Ai_Optimizer( $this->provider_manager, $this->options );
				$result    = $optimizer->optimize_post( $post_id, $enabled_fields );

				if ( is_array( $result ) && ! empty( $result ) ) {
					$this->save_optimization_result( $post_id, $result, $enabled_fields );
					$success = true;
				}
			} else {
				// Fallback: generate basic meta from content without AI.
				$result  = $this->generate_basic_meta( $post );
				$this->save_optimization_result( $post_id, $result, $enabled_fields );
				$success = true;
			}

			// Set transient notice for user feedback.
			if ( $success ) {
				$user_id = get_current_user_id();

				if ( $user_id > 0 ) {
					set_transient(
						"seo_ai_optimized_{$user_id}_{$post_id}",
						true,
						60
					);
				}
			}

			/**
			 * Fires after auto-SEO optimization completes.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $post_id The optimized post ID.
			 * @param bool  $success Whether the optimization succeeded.
			 * @param array $result  The optimization result data.
			 */
			do_action( 'seo_ai/auto_seo_completed', $post_id, $success, $result ?? [] );
		} catch ( \Throwable $e ) {
			// Log the error but do not break the save flow.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf(
					'[SEO AI] Auto-SEO optimization failed for post %d: %s',
					$post_id,
					$e->getMessage()
				) );
			}
		}

		self::$is_running = false;

		return $success;
	}

	/**
	 * Save AI optimization results to post meta.
	 *
	 * Only saves fields that were requested in the enabled_fields list.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id        The post ID.
	 * @param array $result         The optimization result data.
	 * @param array $enabled_fields Fields that should be saved.
	 * @return void
	 */
	private function save_optimization_result( int $post_id, array $result, array $enabled_fields ): void {
		$field_map = [
			'title'       => 'title',
			'description' => 'description',
			'keyword'     => 'focus_keyword',
			'keywords'    => 'focus_keywords',
			'schema'      => 'schema_type',
			'og'          => null, // Handled separately.
		];

		foreach ( $field_map as $field_key => $meta_key ) {
			if ( ! in_array( $field_key, $enabled_fields, true ) ) {
				continue;
			}

			if ( null === $meta_key ) {
				// Handle OpenGraph fields.
				if ( 'og' === $field_key ) {
					if ( isset( $result['og_title'] ) && ! empty( $result['og_title'] ) ) {
						$this->post_meta->set( $post_id, 'og_title', sanitize_text_field( $result['og_title'] ) );
					}
					if ( isset( $result['og_description'] ) && ! empty( $result['og_description'] ) ) {
						$this->post_meta->set( $post_id, 'og_description', sanitize_text_field( $result['og_description'] ) );
					}
				}
				continue;
			}

			if ( isset( $result[ $meta_key ] ) && ! empty( $result[ $meta_key ] ) ) {
				$value = $result[ $meta_key ];

				if ( is_string( $value ) ) {
					$value = sanitize_text_field( $value );
				}

				$this->post_meta->set( $post_id, $meta_key, $value );
			}
		}
	}

	/**
	 * Generate basic SEO meta from post content without AI.
	 *
	 * Used as a fallback when no AI provider is available or when the
	 * AI optimizer class is not loaded.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The post object.
	 * @return array Generated meta data.
	 */
	private function generate_basic_meta( \WP_Post $post ): array {
		$result = [];

		// Title: use post title with separator and site name.
		$separator = $this->options->get( 'title_separator', "\u{2013}" );
		$result['title'] = $post->post_title . " {$separator} " . get_bloginfo( 'name' );

		// Description: first 155 characters of content.
		$plain_content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$plain_content = preg_replace( '/\s+/', ' ', trim( $plain_content ) );

		if ( mb_strlen( $plain_content ) > 155 ) {
			$result['description'] = mb_substr( $plain_content, 0, 152 ) . '...';
		} else {
			$result['description'] = $plain_content;
		}

		// Focus keyword: extract the most prominent multi-word phrase from title.
		$title_words = array_filter( explode( ' ', strtolower( $post->post_title ) ), function ( string $word ): bool {
			return mb_strlen( $word ) > 3;
		} );

		if ( count( $title_words ) >= 2 ) {
			$result['focus_keyword'] = implode( ' ', array_slice( array_values( $title_words ), 0, 3 ) );
		} elseif ( ! empty( $title_words ) ) {
			$result['focus_keyword'] = reset( $title_words );
		}

		// OG fields: mirror title and description.
		$result['og_title']       = $post->post_title;
		$result['og_description'] = $result['description'];

		return $result;
	}
}
