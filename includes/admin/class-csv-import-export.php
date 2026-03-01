<?php
/**
 * CSV Import/Export for SEO Data.
 *
 * Allows bulk export and import of post SEO metadata via CSV files.
 *
 * @package SeoAi\Admin
 * @since   0.4.0
 */

declare(strict_types=1);

namespace SeoAi\Admin;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Helpers\Post_Meta;

/**
 * Class Csv_Import_Export
 *
 * @since 0.4.0
 */
final class Csv_Import_Export {

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
	 * Exportable SEO fields.
	 *
	 * @var string[]
	 */
	private const EXPORT_FIELDS = [
		'title',
		'description',
		'focus_keyword',
		'canonical',
		'robots',
		'schema_type',
		'og_title',
		'og_description',
		'og_image',
		'twitter_title',
		'twitter_description',
		'cornerstone',
		'seo_score',
	];

	/**
	 * Importable SEO fields (subset of export fields — excludes computed values).
	 *
	 * @var string[]
	 */
	private const IMPORT_FIELDS = [
		'title',
		'description',
		'focus_keyword',
		'canonical',
		'robots',
		'schema_type',
		'og_title',
		'og_description',
		'og_image',
		'twitter_title',
		'twitter_description',
		'cornerstone',
	];

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
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'handle_export' ] );
		add_action( 'admin_init', [ $this, 'handle_import' ] );
	}

	/**
	 * Handle CSV export request.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! isset( $_GET['seo_ai_csv_export'] ) || '1' !== $_GET['seo_ai_csv_export'] ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'seo_ai_csv_export' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_type = sanitize_text_field( wp_unslash( $_GET['seo_ai_csv_post_type'] ?? 'post' ) );

		$this->export_csv( $post_type );
	}

	/**
	 * Handle CSV import request.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		if ( ! isset( $_POST['seo_ai_csv_import'] ) || '1' !== $_POST['seo_ai_csv_import'] ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'seo_ai_csv_import' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'seo-ai' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seo-ai' ) );
		}

		if ( ! isset( $_FILES['seo_ai_csv_file'] ) || UPLOAD_ERR_OK !== $_FILES['seo_ai_csv_file']['error'] ) {
			add_settings_error( 'seo_ai_csv', 'upload_error', __( 'CSV file upload failed.', 'seo-ai' ), 'error' );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File path from PHP upload.
		$file = $_FILES['seo_ai_csv_file']['tmp_name'];
		$result = $this->import_csv( $file );

		if ( is_wp_error( $result ) ) {
			add_settings_error( 'seo_ai_csv', 'import_error', $result->get_error_message(), 'error' );
		} else {
			$message = sprintf(
				/* translators: 1: updated count, 2: skipped count, 3: error count */
				__( 'Import complete: %1$d updated, %2$d skipped, %3$d errors.', 'seo-ai' ),
				$result['updated'],
				$result['skipped'],
				$result['errors']
			);
			add_settings_error( 'seo_ai_csv', 'import_success', $message, 'success' );
		}

		set_transient( 'seo_ai_csv_import_result', get_settings_errors( 'seo_ai_csv' ), 30 );

		wp_safe_redirect( admin_url( 'admin.php?page=seo-ai-settings&tab=advanced&csv_imported=1' ) );
		exit;
	}

	/**
	 * Export SEO data as CSV.
	 *
	 * @param string $post_type Post type to export.
	 * @return void
	 */
	private function export_csv( string $post_type ): void {
		$posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );

		$filename = 'seo-ai-export-' . $post_type . '-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			return;
		}

		// Header row.
		$headers = array_merge( [ 'post_id', 'post_title', 'post_url' ], self::EXPORT_FIELDS );
		fputcsv( $output, $headers );

		foreach ( $posts as $post ) {
			$row = [
				$post->ID,
				$post->post_title,
				get_permalink( $post->ID ),
			];

			foreach ( self::EXPORT_FIELDS as $field ) {
				$value = $this->post_meta->get( $post->ID, $field );

				// Convert arrays to comma-separated for CSV.
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}

				$row[] = (string) $value;
			}

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Import SEO data from CSV.
	 *
	 * @param string $file Path to the uploaded CSV file.
	 * @return array{updated: int, skipped: int, errors: int}|\WP_Error
	 */
	private function import_csv( string $file ): array|\WP_Error {
		$handle = fopen( $file, 'r' );

		if ( false === $handle ) {
			return new \WP_Error( 'file_error', __( 'Could not open CSV file.', 'seo-ai' ) );
		}

		// Read header row.
		$headers = fgetcsv( $handle );

		if ( false === $headers || ! is_array( $headers ) ) {
			fclose( $handle );
			return new \WP_Error( 'invalid_csv', __( 'Invalid CSV file: no header row found.', 'seo-ai' ) );
		}

		// Normalize headers.
		$headers = array_map( 'trim', $headers );
		$headers = array_map( 'strtolower', $headers );

		// Require post_id column.
		$id_index = array_search( 'post_id', $headers, true );

		if ( false === $id_index ) {
			fclose( $handle );
			return new \WP_Error( 'missing_id', __( 'CSV must contain a "post_id" column.', 'seo-ai' ) );
		}

		// Map header indices to importable fields.
		$field_map = [];
		foreach ( self::IMPORT_FIELDS as $field ) {
			$index = array_search( $field, $headers, true );
			if ( false !== $index ) {
				$field_map[ $field ] = $index;
			}
		}

		if ( empty( $field_map ) ) {
			fclose( $handle );
			return new \WP_Error( 'no_fields', __( 'CSV does not contain any recognized SEO fields.', 'seo-ai' ) );
		}

		$result = [ 'updated' => 0, 'skipped' => 0, 'errors' => 0 ];

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( ! isset( $row[ $id_index ] ) || ! is_numeric( $row[ $id_index ] ) ) {
				++$result['errors'];
				continue;
			}

			$post_id = (int) $row[ $id_index ];
			$post    = get_post( $post_id );

			if ( ! $post instanceof \WP_Post ) {
				++$result['skipped'];
				continue;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				++$result['skipped'];
				continue;
			}

			foreach ( $field_map as $field => $index ) {
				if ( ! isset( $row[ $index ] ) ) {
					continue;
				}

				$value = trim( $row[ $index ] );

				// Handle robots field: convert comma-separated string back to array.
				if ( 'robots' === $field ) {
					$value = '' !== $value
						? array_map( 'trim', explode( ',', $value ) )
						: [];
				}

				$this->post_meta->set( $post_id, $field, sanitize_text_field( $value ) );
			}

			++$result['updated'];
		}

		fclose( $handle );

		return $result;
	}

	/**
	 * Get the available post types for the CSV export dropdown.
	 *
	 * @return array<string, string>
	 */
	public function get_export_post_types(): array {
		$default    = [ 'post', 'page' ];
		$configured = $this->options->get( 'analysis_post_types', $default );
		$post_types = (array) apply_filters( 'seo_ai/post_types', $configured );
		$labels     = [];

		foreach ( $post_types as $pt ) {
			$obj = get_post_type_object( $pt );
			$labels[ $pt ] = $obj ? $obj->labels->singular_name : $pt;
		}

		return $labels;
	}
}
