<?php
/**
 * bbPress Integration — QAPage Schema.
 *
 * Adds QAPage structured data to bbPress forum topics,
 * including Question/Answer schema with solved-reply support.
 *
 * @package SeoAi\Integrations
 * @since   0.7.0
 */

declare(strict_types=1);

namespace SeoAi\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Bbpress
 *
 * Only activates if bbPress is installed and active.
 * Hooks into schema generation and adds QAPage schema to forum topics.
 *
 * @since 0.7.0
 */
final class Bbpress {

	/**
	 * Maximum number of suggested answers to include in schema.
	 *
	 * @var int
	 */
	private const MAX_SUGGESTED_ANSWERS = 10;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! class_exists( 'bbPress' ) ) {
			return;
		}

		add_filter( 'seo_ai/schema/graph', [ $this, 'add_qa_schema' ], 10, 2 );
	}

	/**
	 * Add QAPage schema to bbPress topic pages.
	 *
	 * Builds a QAPage entity with a Question as mainEntity,
	 * including acceptedAnswer (if marked solved) and suggestedAnswer
	 * entries for other replies.
	 *
	 * @param array $graph   The existing schema @graph.
	 * @param int   $post_id The current post ID.
	 * @return array Modified graph.
	 */
	public function add_qa_schema( array $graph, int $post_id ): array {
		if ( bbp_get_topic_post_type() !== get_post_type( $post_id ) ) {
			return $graph;
		}

		$topic = get_post( $post_id );
		if ( ! $topic ) {
			return $graph;
		}

		$permalink    = get_permalink( $post_id );
		$reply_count  = (int) bbp_get_topic_reply_count( $post_id );
		$solved_reply = (int) get_post_meta( $post_id, '_seo_ai_solved_reply', true );

		// Build the Question entity.
		$question = [
			'@type'       => 'Question',
			'name'        => get_the_title( $post_id ),
			'text'        => wp_strip_all_tags( $topic->post_content ),
			'answerCount' => $reply_count,
			'dateCreated' => get_the_date( 'c', $post_id ),
			'author'      => [
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) $topic->post_author ),
			],
		];

		// Collect replies for the topic.
		$reply_args = [
			'post_type'      => bbp_get_reply_post_type(),
			'post_parent'    => $post_id,
			'posts_per_page' => self::MAX_SUGGESTED_ANSWERS,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'ASC',
		];

		$replies = get_posts( $reply_args );

		$accepted_answer   = null;
		$suggested_answers = [];

		foreach ( $replies as $reply ) {
			$answer = $this->build_answer( $reply );

			if ( 0 < $solved_reply && $solved_reply === $reply->ID ) {
				$accepted_answer = $answer;
			} else {
				$suggested_answers[] = $answer;
			}
		}

		if ( null !== $accepted_answer ) {
			$question['acceptedAnswer'] = $accepted_answer;
		}

		if ( ! empty( $suggested_answers ) ) {
			$question['suggestedAnswer'] = $suggested_answers;
		}

		// Build the QAPage entity.
		$qa_page = [
			'@type'      => 'QAPage',
			'@id'        => $permalink . '#qapage',
			'mainEntity' => $question,
		];

		$graph[] = $qa_page;

		return $graph;
	}

	/**
	 * Mark a reply as the solved/accepted answer for a topic.
	 *
	 * @param int $topic_id The topic post ID.
	 * @param int $reply_id The reply post ID to mark as solved.
	 * @return void
	 */
	public function mark_as_solved( int $topic_id, int $reply_id ): void {
		update_post_meta( $topic_id, '_seo_ai_solved_reply', $reply_id );
	}

	/**
	 * Remove the solved/accepted answer mark from a topic.
	 *
	 * @param int $topic_id The topic post ID.
	 * @return void
	 */
	public function unmark_solved( int $topic_id ): void {
		delete_post_meta( $topic_id, '_seo_ai_solved_reply' );
	}

	/**
	 * Build an Answer schema entity from a reply post.
	 *
	 * @param \WP_Post $reply The reply post object.
	 * @return array Answer schema entity.
	 */
	private function build_answer( \WP_Post $reply ): array {
		return [
			'@type'       => 'Answer',
			'text'        => wp_strip_all_tags( $reply->post_content ),
			'dateCreated' => get_the_date( 'c', $reply->ID ),
			'author'      => [
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) $reply->post_author ),
			],
			'upvoteCount' => 0,
		];
	}
}
