<?php
namespace SeoAi\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Post Meta helper for SEO AI post-level data.
 *
 * Provides a unified interface for reading and writing post meta
 * with the `_seo_ai_` prefix. Handles JSON encoding/decoding for
 * complex fields like focus_keywords, robots, and schema_data.
 *
 * @since 1.0.0
 */
class Post_Meta {

	/**
	 * Meta key prefix for all SEO AI post meta.
	 *
	 * @var string
	 */
	private const PREFIX = '_seo_ai_';

	/**
	 * Meta keys that store JSON-encoded data.
	 *
	 * @var string[]
	 */
	private const JSON_FIELDS = [
		'focus_keywords',
		'robots',
		'schema_data',
	];

	/**
	 * All recognized meta keys (without prefix).
	 *
	 * @var string[]
	 */
	private const KNOWN_KEYS = [
		'title',
		'description',
		'focus_keyword',
		'focus_keywords',
		'canonical',
		'robots',
		'og_title',
		'og_description',
		'og_image',
		'twitter_title',
		'twitter_description',
		'schema_type',
		'schema_data',
		'seo_score',
		'readability_score',
		'auto_seo',
		'cornerstone',
	];

	/**
	 * Get a single post meta value.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The meta key (without prefix).
	 * @param mixed  $default Default value if meta does not exist.
	 * @return mixed
	 */
	public function get( int $post_id, string $key, mixed $default = '' ): mixed {
		$full_key = self::PREFIX . $key;
		$value    = get_post_meta( $post_id, $full_key, true );

		if ( '' === $value && '' !== $default ) {
			return $default;
		}

		if ( in_array( $key, self::JSON_FIELDS, true ) && is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			return ( null !== $decoded ) ? $decoded : $default;
		}

		return $value;
	}

	/**
	 * Set a single post meta value.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The meta key (without prefix).
	 * @param mixed  $value   The value to store.
	 * @return bool True on success, false on failure.
	 */
	public function set( int $post_id, string $key, mixed $value ): bool {
		$full_key = self::PREFIX . $key;

		if ( in_array( $key, self::JSON_FIELDS, true ) && ( is_array( $value ) || is_object( $value ) ) ) {
			$value = wp_json_encode( $value );

			if ( false === $value ) {
				return false;
			}
		}

		$result = update_post_meta( $post_id, $full_key, $value );

		// update_post_meta returns meta_id (int) on first insert, true on update, false on failure.
		return false !== $result;
	}

	/**
	 * Delete a single post meta value.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The meta key (without prefix).
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $post_id, string $key ): bool {
		$full_key = self::PREFIX . $key;

		return delete_post_meta( $post_id, $full_key );
	}

	/**
	 * Get all SEO AI meta for a post.
	 *
	 * Returns an associative array of all known meta keys (without prefix)
	 * and their values. JSON fields are automatically decoded.
	 *
	 * @param int $post_id The post ID.
	 * @return array<string, mixed>
	 */
	public function get_all( int $post_id ): array {
		$meta = [];

		foreach ( self::KNOWN_KEYS as $key ) {
			$meta[ $key ] = $this->get( $post_id, $key );
		}

		return $meta;
	}

	/**
	 * Bulk-set multiple meta values for a post.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $data    Associative array of key => value pairs (keys without prefix).
	 * @return void
	 */
	public function set_many( int $post_id, array $data ): void {
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, self::KNOWN_KEYS, true ) ) {
				$this->set( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Delete all SEO AI meta for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function delete_all( int $post_id ): void {
		foreach ( self::KNOWN_KEYS as $key ) {
			$this->delete( $post_id, $key );
		}
	}

	/**
	 * Get the full prefixed meta key.
	 *
	 * Useful for register_meta() or direct WP_Query meta queries.
	 *
	 * @param string $key The meta key (without prefix).
	 * @return string
	 */
	public static function prefixed_key( string $key ): string {
		return self::PREFIX . $key;
	}

	/**
	 * Check whether a meta key is a JSON field.
	 *
	 * @param string $key The meta key (without prefix).
	 * @return bool
	 */
	public static function is_json_field( string $key ): bool {
		return in_array( $key, self::JSON_FIELDS, true );
	}
}
