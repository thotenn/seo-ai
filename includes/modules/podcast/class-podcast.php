<?php
/**
 * Podcast Module.
 *
 * Registers an iTunes-compatible podcast RSS feed and adds
 * PodcastEpisode structured data to singular posts with audio.
 *
 * @package SeoAi\Modules\Podcast
 * @since   0.8.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Podcast;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Podcast
 *
 * Provides a custom `/feed/podcast/` RSS feed with iTunes namespace
 * and injects PodcastEpisode schema into the JSON-LD graph for
 * singular posts that have podcast audio meta.
 *
 * @since 0.8.0
 */
final class Podcast {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_feed' ] );
		add_filter( 'seo_ai/schema/graph', [ $this, 'add_schema' ], 20 );
	}

	/**
	 * Register the custom podcast feed at /feed/podcast/.
	 *
	 * @return void
	 */
	public function register_feed(): void {
		add_feed( 'podcast', [ $this, 'render_feed' ] );
	}

	/**
	 * Render the iTunes-compatible podcast RSS feed.
	 *
	 * Queries published posts that have `_seo_ai_podcast_audio` meta
	 * and outputs a full RSS 2.0 feed with iTunes extensions.
	 *
	 * @return void
	 */
	public function render_feed(): void {
		$options  = Options::instance();
		$title    = (string) $options->get( 'podcast_title', get_bloginfo( 'name' ) );
		$desc     = (string) $options->get( 'podcast_description', get_bloginfo( 'description' ) );
		$image    = (string) $options->get( 'podcast_image', '' );
		$category = (string) $options->get( 'podcast_category', '' );
		$language = (string) $options->get( 'podcast_language', '' );

		if ( '' === $language ) {
			$language = get_bloginfo( 'language' );
		}

		$episodes = new \WP_Query( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'meta_key'       => '_seo_ai_podcast_audio',
			'meta_compare'   => '!=',
			'meta_value'     => '',
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		header( 'Content-Type: application/rss+xml; charset=UTF-8' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		?>
<rss version="2.0"
	xmlns:itunes="http://www.itunes.apple.com/dtds/podcast-1.0.dtd"
	xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<title><?php echo esc_xml( $title ); ?></title>
	<link><?php echo esc_url( home_url( '/' ) ); ?></link>
	<description><?php echo esc_xml( $desc ); ?></description>
	<language><?php echo esc_xml( $language ); ?></language>
	<atom:link href="<?php echo esc_url( home_url( '/feed/podcast/' ) ); ?>" rel="self" type="application/rss+xml" />
<?php if ( '' !== $image ) : ?>
	<image>
		<url><?php echo esc_url( $image ); ?></url>
		<title><?php echo esc_xml( $title ); ?></title>
		<link><?php echo esc_url( home_url( '/' ) ); ?></link>
	</image>
	<itunes:image href="<?php echo esc_url( $image ); ?>" />
<?php endif; ?>
<?php if ( '' !== $category ) : ?>
	<itunes:category text="<?php echo esc_attr( $category ); ?>" />
<?php endif; ?>
	<itunes:author><?php echo esc_xml( $title ); ?></itunes:author>
	<itunes:summary><?php echo esc_xml( $desc ); ?></itunes:summary>
<?php
		if ( $episodes->have_posts() ) :
			while ( $episodes->have_posts() ) :
				$episodes->the_post();

				$post_id  = get_the_ID();
				$audio    = (string) get_post_meta( $post_id, '_seo_ai_podcast_audio', true );
				$duration = (string) get_post_meta( $post_id, '_seo_ai_podcast_duration', true );
				$episode  = (string) get_post_meta( $post_id, '_seo_ai_podcast_episode', true );
				$season   = (string) get_post_meta( $post_id, '_seo_ai_podcast_season', true );

				if ( '' === $audio ) {
					continue;
				}
				?>
	<item>
		<title><?php echo esc_xml( get_the_title() ); ?></title>
		<description><![CDATA[<?php echo wp_kses_post( get_the_excerpt() ); ?>]]></description>
		<link><?php echo esc_url( get_permalink() ); ?></link>
		<guid isPermaLink="false"><?php echo esc_url( get_permalink() ); ?></guid>
		<pubDate><?php echo esc_xml( get_the_date( 'r' ) ); ?></pubDate>
		<enclosure url="<?php echo esc_url( $audio ); ?>" type="audio/mpeg" />
<?php if ( '' !== $duration ) : ?>
		<itunes:duration><?php echo esc_xml( $duration ); ?></itunes:duration>
<?php endif; ?>
<?php if ( '' !== $episode ) : ?>
		<itunes:episode><?php echo esc_xml( $episode ); ?></itunes:episode>
<?php endif; ?>
<?php if ( '' !== $season ) : ?>
		<itunes:season><?php echo esc_xml( $season ); ?></itunes:season>
<?php endif; ?>
	</item>
<?php
			endwhile;
			wp_reset_postdata();
		endif;
		?>
</channel>
</rss>
<?php
	}

	/**
	 * Add PodcastEpisode schema to the JSON-LD graph.
	 *
	 * Only fires on singular posts that have `_seo_ai_podcast_audio` meta.
	 *
	 * @param array $graph The existing schema @graph.
	 * @return array Modified graph.
	 */
	public function add_schema( array $graph ): array {
		if ( ! is_singular() ) {
			return $graph;
		}

		$post_id = get_queried_object_id();
		$audio   = (string) get_post_meta( $post_id, '_seo_ai_podcast_audio', true );

		if ( '' === $audio ) {
			return $graph;
		}

		$post      = get_post( $post_id );
		$permalink = get_permalink( $post_id );
		$duration  = (string) get_post_meta( $post_id, '_seo_ai_podcast_duration', true );

		$schema = [
			'@type'         => 'PodcastEpisode',
			'@id'           => $permalink . '#podcastepisode',
			'name'          => $post ? $post->post_title : '',
			'url'           => $permalink,
			'datePublished' => $post ? get_the_date( 'c', $post ) : '',
		];

		// Associated media (AudioObject).
		$media = [
			'@type'      => 'AudioObject',
			'contentUrl' => esc_url( $audio ),
		];

		if ( '' !== $duration ) {
			$media['duration'] = $duration;
		}

		$schema['associatedMedia'] = $media;

		// Part of series.
		$options     = Options::instance();
		$series_name = (string) $options->get( 'podcast_title', get_bloginfo( 'name' ) );

		$schema['partOfSeries'] = [
			'@type' => 'PodcastSeries',
			'name'  => $series_name,
			'url'   => home_url( '/' ),
		];

		/**
		 * Filters the PodcastEpisode schema entity.
		 *
		 * @since 0.8.0
		 *
		 * @param array $schema  The PodcastEpisode schema.
		 * @param int   $post_id The post ID.
		 */
		$graph[] = (array) apply_filters( 'seo_ai/schema/podcast_episode', $schema, $post_id );

		return $graph;
	}
}
