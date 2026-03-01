<?php
/**
 * Video XML Sitemap.
 *
 * Generates a video XML sitemap by detecting video embeds in post content
 * and outputting them in the Google Video Sitemap format.
 *
 * @package SeoAi\Modules\Sitemap
 * @since   0.5.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Sitemap;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Video_Sitemap
 *
 * Hooks into WordPress to serve a dedicated video XML sitemap,
 * register its rewrite rule, and add it to the main sitemap index.
 *
 * @since 0.5.0
 */
final class Video_Sitemap {

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
	 * @param Options|null $options Options helper instance.
	 */
	public function __construct( ?Options $options = null ) {
		$this->options = $options ?? Options::instance();
	}

	/**
	 * Register WordPress hooks for video sitemap functionality.
	 *
	 * Bails early if the video_sitemap_enabled option is not true.
	 * Otherwise registers rewrite rules, request handling, query vars,
	 * and hooks into the sitemap index entries filter.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! $this->options->get( 'video_sitemap_enabled', false ) ) {
			return;
		}

		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ] );
		add_filter( 'seo_ai/sitemap/index_entries', [ $this, 'add_to_index' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
	}

	/**
	 * Add rewrite rule for the video sitemap URL.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^video-sitemap\.xml$',
			'index.php?seo_ai_sitemap=video',
			'top'
		);
	}

	/**
	 * Register custom query variables for video sitemap routing.
	 *
	 * @since 0.5.0
	 *
	 * @param array $vars Existing query variables.
	 * @return array Modified query variables.
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'seo_ai_sitemap';

		return $vars;
	}

	/**
	 * Intercept video sitemap requests and render the XML response.
	 *
	 * Checks the seo_ai_sitemap query variable for the value 'video'.
	 * If matched, renders the video sitemap and exits.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function handle_request(): void {
		$sitemap = get_query_var( 'seo_ai_sitemap' );

		if ( 'video' !== $sitemap ) {
			return;
		}

		$this->render();
	}

	/**
	 * Add the video sitemap entry to the main sitemap index.
	 *
	 * @since 0.5.0
	 *
	 * @param array $entries Existing sitemap index entries.
	 * @return array Modified sitemap index entries with video sitemap added.
	 */
	public function add_to_index( array $entries ): array {
		$entries[] = [
			'loc'     => home_url( '/video-sitemap.xml' ),
			'lastmod' => '',
		];

		return $entries;
	}

	/**
	 * Query posts, detect video embeds, build and output the video sitemap XML.
	 *
	 * Queries all published posts from configured post types, detects video
	 * embeds in their content, and outputs the video sitemap XML with proper
	 * HTTP headers.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	private function render(): void {
		$post_types = $this->options->get( 'sitemap_post_types', [ 'post', 'page' ] );

		if ( ! is_array( $post_types ) ) {
			$post_types = [ 'post', 'page' ];
		}

		$args = [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		];

		$query   = new \WP_Query( $args );
		$entries = [];

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$videos = $this->detect_videos( $post->post_content );

				if ( empty( $videos ) ) {
					continue;
				}

				$description = $post->post_excerpt;
				if ( empty( $description ) ) {
					$description = wp_strip_all_tags( $post->post_content );
					$description = mb_substr( $description, 0, 200 );
				}

				$video_data = [];
				foreach ( $videos as $video ) {
					$thumbnail = $this->get_video_thumbnail( $video['provider'], $video['id'] );

					$video_data[] = [
						'url'           => $video['url'],
						'title'         => get_the_title( $post ),
						'description'   => $description,
						'thumbnail_loc' => $thumbnail,
						'player_loc'    => $video['url'],
					];
				}

				$entries[] = [
					'loc'    => get_permalink( $post ),
					'videos' => $video_data,
				];
			}

			wp_reset_postdata();
		}

		$xml = $this->build_video_xml( $entries );

		status_header( 200 );
		header( 'Content-Type: text/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is pre-escaped.
		echo $xml;
		die();
	}

	/**
	 * Detect video embeds in post content using regex patterns.
	 *
	 * Searches for YouTube embeds, Vimeo embeds, HTML5 video tags,
	 * and WordPress video shortcodes.
	 *
	 * @since 0.5.0
	 *
	 * @param string $content The post content to search.
	 * @return array Array of detected videos with 'url', 'provider', and 'id' keys.
	 */
	private function detect_videos( string $content ): array {
		$videos = [];
		$seen   = [];

		// YouTube embed URLs: youtube.com/embed/{id}
		if ( preg_match_all( '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				if ( isset( $seen[ 'youtube_' . $id ] ) ) {
					continue;
				}
				$seen[ 'youtube_' . $id ] = true;
				$videos[] = [
					'url'      => 'https://www.youtube.com/embed/' . $id,
					'provider' => 'youtube',
					'id'       => $id,
				];
			}
		}

		// YouTube watch URLs: youtube.com/watch?v={id}
		if ( preg_match_all( '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				if ( isset( $seen[ 'youtube_' . $id ] ) ) {
					continue;
				}
				$seen[ 'youtube_' . $id ] = true;
				$videos[] = [
					'url'      => 'https://www.youtube.com/embed/' . $id,
					'provider' => 'youtube',
					'id'       => $id,
				];
			}
		}

		// YouTube short URLs: youtu.be/{id}
		if ( preg_match_all( '/youtu\.be\/([a-zA-Z0-9_-]+)/', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				if ( isset( $seen[ 'youtube_' . $id ] ) ) {
					continue;
				}
				$seen[ 'youtube_' . $id ] = true;
				$videos[] = [
					'url'      => 'https://www.youtube.com/embed/' . $id,
					'provider' => 'youtube',
					'id'       => $id,
				];
			}
		}

		// Vimeo URLs: vimeo.com/{id}
		if ( preg_match_all( '/vimeo\.com\/(\d+)/', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				if ( isset( $seen[ 'vimeo_' . $id ] ) ) {
					continue;
				}
				$seen[ 'vimeo_' . $id ] = true;
				$videos[] = [
					'url'      => 'https://player.vimeo.com/video/' . $id,
					'provider' => 'vimeo',
					'id'       => $id,
				];
			}
		}

		// Vimeo player URLs: player.vimeo.com/video/{id}
		if ( preg_match_all( '/player\.vimeo\.com\/video\/(\d+)/', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				if ( isset( $seen[ 'vimeo_' . $id ] ) ) {
					continue;
				}
				$seen[ 'vimeo_' . $id ] = true;
				$videos[] = [
					'url'      => 'https://player.vimeo.com/video/' . $id,
					'provider' => 'vimeo',
					'id'       => $id,
				];
			}
		}

		// HTML5 <video> tags: extract src attribute.
		if ( preg_match_all( '/<video[^>]+src=["\']([^"\']+)["\']/', $content, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				if ( isset( $seen[ 'html5_' . $src ] ) ) {
					continue;
				}
				$seen[ 'html5_' . $src ] = true;
				$videos[] = [
					'url'      => $src,
					'provider' => 'html5',
					'id'       => basename( $src ),
				];
			}
		}

		// WordPress [video] shortcode: extract src attribute.
		if ( preg_match_all( '/\[video[^\]]+src=["\']([^"\']+)["\']/', $content, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				if ( isset( $seen[ 'wordpress_' . $src ] ) ) {
					continue;
				}
				$seen[ 'wordpress_' . $src ] = true;
				$videos[] = [
					'url'      => $src,
					'provider' => 'wordpress',
					'id'       => basename( $src ),
				];
			}
		}

		return $videos;
	}

	/**
	 * Get the thumbnail URL for a video based on its provider and ID.
	 *
	 * @since 0.5.0
	 *
	 * @param string $provider The video provider ('youtube', 'vimeo', 'html5', 'wordpress').
	 * @param string $id       The video identifier.
	 * @return string The thumbnail URL, or empty string if unavailable.
	 */
	private function get_video_thumbnail( string $provider, string $id ): string {
		if ( 'youtube' === $provider ) {
			return 'https://img.youtube.com/vi/' . $id . '/maxresdefault.jpg';
		}

		// Vimeo requires an API call to get thumbnails; return empty.
		// HTML5 and WordPress videos have no standard thumbnail source.
		return '';
	}

	/**
	 * Build the complete video sitemap XML string.
	 *
	 * Generates a urlset XML document with the Google Video Sitemap namespace.
	 * Each entry contains a <url> element with <loc> and one or more
	 * <video:video> child elements.
	 *
	 * @since 0.5.0
	 *
	 * @param array $entries Array of sitemap entries with 'loc' and 'videos' keys.
	 * @return string The complete video sitemap XML.
	 */
	private function build_video_xml( array $entries ): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
		$xml .= "\n        " . 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"';
		$xml .= ">\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( $entry['loc'] ) . "</loc>\n";

			if ( ! empty( $entry['videos'] ) ) {
				foreach ( $entry['videos'] as $video ) {
					$xml .= "\t\t<video:video>\n";

					if ( ! empty( $video['thumbnail_loc'] ) ) {
						$xml .= "\t\t\t<video:thumbnail_loc>" . esc_url( $video['thumbnail_loc'] ) . "</video:thumbnail_loc>\n";
					}

					$xml .= "\t\t\t<video:title>" . esc_xml( $video['title'] ) . "</video:title>\n";
					$xml .= "\t\t\t<video:description>" . esc_xml( $video['description'] ) . "</video:description>\n";

					if ( ! empty( $video['player_loc'] ) ) {
						$xml .= "\t\t\t<video:player_loc>" . esc_url( $video['player_loc'] ) . "</video:player_loc>\n";
					}

					if ( ! empty( $video['url'] ) && empty( $video['player_loc'] ) ) {
						$xml .= "\t\t\t<video:content_loc>" . esc_url( $video['url'] ) . "</video:content_loc>\n";
					}

					$xml .= "\t\t</video:video>\n";
				}
			}

			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		return $xml;
	}
}
