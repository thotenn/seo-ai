<?php
/**
 * WooCommerce Integration Module.
 *
 * Enhances SEO for WooCommerce product pages with rich Product schema,
 * GTIN/ISBN/MPN fields, and product visibility awareness.
 *
 * @package SeoAi\Modules\Woocommerce
 * @since   0.7.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Woocommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class Woocommerce
 *
 * Only activates if WooCommerce is installed and active.
 * Hooks into schema generation and adds product-specific fields.
 *
 * @since 0.7.0
 */
final class Woocommerce {

	/**
	 * GTIN field meta keys.
	 *
	 * @var string[]
	 */
	private const IDENTIFIER_FIELDS = [
		'gtin'  => 'GTIN',
		'isbn'  => 'ISBN',
		'mpn'   => 'MPN',
		'brand' => 'Brand',
	];

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Only activate if WooCommerce is present.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'seo_ai/schema/product', [ $this, 'enhance_product_schema' ], 10, 2 );
		add_filter( 'seo_ai/schema/graph', [ $this, 'add_woocommerce_schemas' ], 25 );
		add_action( 'add_meta_boxes', [ $this, 'register_product_meta_fields' ] );
		add_action( 'save_post_product', [ $this, 'save_product_meta_fields' ] );
		add_filter( 'wp_robots', [ $this, 'noindex_hidden_products' ] );

		// Remove WooCommerce's built-in structured data to avoid duplicates.
		add_action( 'init', [ $this, 'remove_wc_structured_data' ], 99 );
	}

	/**
	 * Remove WooCommerce's built-in structured data output.
	 *
	 * @return void
	 */
	public function remove_wc_structured_data(): void {
		if ( class_exists( 'WC_Structured_Data' ) ) {
			remove_action( 'wp_footer', [ \WC()->structured_data, 'output_structured_data' ], 10 );
		}
	}

	/**
	 * Enhance Product schema with WooCommerce product data.
	 *
	 * Pulls price, stock, images, categories, and reviews from WC_Product.
	 *
	 * @param array    $schema The existing Product schema entity.
	 * @param \WP_Post $post   The product post object.
	 * @return array Enhanced Product schema.
	 */
	public function enhance_product_schema( array $schema, \WP_Post $post ): array {
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return $schema;
		}

		// GTIN/ISBN/MPN from custom meta.
		$gtin = get_post_meta( $post->ID, '_seo_ai_gtin', true );
		if ( '' !== $gtin ) {
			$schema['gtin'] = sanitize_text_field( $gtin );
		}

		$isbn = get_post_meta( $post->ID, '_seo_ai_isbn', true );
		if ( '' !== $isbn ) {
			$schema['isbn'] = sanitize_text_field( $isbn );
		}

		$mpn = get_post_meta( $post->ID, '_seo_ai_mpn', true );
		if ( '' !== $mpn ) {
			$schema['mpn'] = sanitize_text_field( $mpn );
		}

		$brand = get_post_meta( $post->ID, '_seo_ai_brand', true );
		if ( '' !== $brand ) {
			$schema['brand'] = [
				'@type' => 'Brand',
				'name'  => sanitize_text_field( $brand ),
			];
		}

		// SKU from WooCommerce.
		$sku = $product->get_sku();
		if ( $sku ) {
			$schema['sku'] = $sku;
		}

		// Enhanced Offers from WooCommerce.
		$schema['offers'] = $this->build_offers( $product );

		// Product images.
		$gallery_ids = $product->get_gallery_image_ids();
		if ( ! empty( $gallery_ids ) ) {
			$images = [];
			foreach ( $gallery_ids as $img_id ) {
				$url = wp_get_attachment_url( $img_id );
				if ( $url ) {
					$images[] = $url;
				}
			}
			if ( ! empty( $images ) ) {
				$schema['image'] = $images;
			}
		}

		// Reviews and ratings from WooCommerce.
		if ( $product->get_review_count() > 0 ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'reviewCount' => $product->get_review_count(),
				'bestRating'  => '5',
				'worstRating' => '1',
			];
		}

		// Product category.
		$categories = wp_get_post_terms( $post->ID, 'product_cat' );
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			$schema['category'] = $categories[0]->name;
		}

		// Weight.
		if ( $product->has_weight() ) {
			$schema['weight'] = [
				'@type'    => 'QuantitativeValue',
				'value'    => $product->get_weight(),
				'unitCode' => get_option( 'woocommerce_weight_unit', 'kg' ),
			];
		}

		return $schema;
	}

	/**
	 * Add WooCommerce-specific schemas to the graph.
	 *
	 * Handles variable products (ProductGroup) and shop/category archive pages.
	 *
	 * @param array $graph The schema @graph.
	 * @return array Modified graph.
	 */
	public function add_woocommerce_schemas( array $graph ): array {
		if ( ! function_exists( 'is_product' ) ) {
			return $graph;
		}

		// Variable product → ProductGroup schema.
		if ( is_product() ) {
			$post_id = get_queried_object_id();
			$product = wc_get_product( $post_id );

			if ( $product && $product->is_type( 'variable' ) ) {
				$group = $this->get_product_group_schema( $product );
				if ( $group ) {
					$graph[] = $group;
				}
			}
		}

		// Shop/category page → ItemList schema.
		if ( is_shop() || is_product_category() ) {
			$list = $this->get_product_list_schema();
			if ( $list ) {
				$graph[] = $list;
			}
		}

		return $graph;
	}

	/**
	 * Register product identifier fields in the product metabox.
	 *
	 * @return void
	 */
	public function register_product_meta_fields(): void {
		add_meta_box(
			'seo-ai-product-identifiers',
			__( 'SEO AI — Product Identifiers', 'seo-ai' ),
			[ $this, 'render_product_meta_fields' ],
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render the product identifier fields.
	 *
	 * @param \WP_Post $post The product post.
	 * @return void
	 */
	public function render_product_meta_fields( \WP_Post $post ): void {
		wp_nonce_field( 'seo_ai_product_meta', 'seo_ai_product_meta_nonce' );

		foreach ( self::IDENTIFIER_FIELDS as $key => $label ) {
			$value = get_post_meta( $post->ID, '_seo_ai_' . $key, true );
			printf(
				'<p><label for="seo_ai_%1$s"><strong>%2$s</strong></label><br>'
				. '<input type="text" id="seo_ai_%1$s" name="seo_ai_%1$s" value="%3$s" class="widefat" /></p>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( $value )
			);
		}
	}

	/**
	 * Save product identifier fields.
	 *
	 * @param int $post_id The product post ID.
	 * @return void
	 */
	public function save_product_meta_fields( int $post_id ): void {
		if ( ! isset( $_POST['seo_ai_product_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['seo_ai_product_meta_nonce'] ) ),
			'seo_ai_product_meta'
		) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( array_keys( self::IDENTIFIER_FIELDS ) as $key ) {
			$field_name = 'seo_ai_' . $key;
			if ( isset( $_POST[ $field_name ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) );
				update_post_meta( $post_id, '_seo_ai_' . $key, $value );
			}
		}
	}

	/**
	 * Auto-noindex hidden/private WooCommerce products.
	 *
	 * @param array $robots The robots directives array.
	 * @return array Modified robots directives.
	 */
	public function noindex_hidden_products( array $robots ): array {
		if ( ! is_singular( 'product' ) ) {
			return $robots;
		}

		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product ) {
			return $robots;
		}

		$visibility = $product->get_catalog_visibility();
		if ( in_array( $visibility, [ 'hidden' ], true ) ) {
			$robots['noindex'] = true;
		}

		return $robots;
	}

	/**
	 * Build Offers schema from WC_Product.
	 *
	 * @param \WC_Product $product The WooCommerce product.
	 * @return array Offer or AggregateOffer schema.
	 */
	private function build_offers( \WC_Product $product ): array {
		$availability_map = [
			'instock'     => 'https://schema.org/InStock',
			'outofstock'  => 'https://schema.org/OutOfStock',
			'onbackorder' => 'https://schema.org/BackOrder',
		];

		$stock  = $product->get_stock_status();
		$avail  = $availability_map[ $stock ] ?? 'https://schema.org/InStock';
		$url    = get_permalink( $product->get_id() );

		if ( $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( true );
			return [
				'@type'         => 'AggregateOffer',
				'lowPrice'      => min( $prices['price'] ) ?: '0',
				'highPrice'     => max( $prices['price'] ) ?: '0',
				'priceCurrency' => get_woocommerce_currency(),
				'offerCount'    => count( $prices['price'] ),
				'availability'  => $avail,
				'url'           => $url,
			];
		}

		$offer = [
			'@type'         => 'Offer',
			'price'         => $product->get_price() ?: '0',
			'priceCurrency' => get_woocommerce_currency(),
			'availability'  => $avail,
			'url'           => $url,
		];

		$sale_end = $product->get_date_on_sale_to();
		if ( $sale_end ) {
			$offer['priceValidUntil'] = $sale_end->date( 'Y-m-d' );
		}

		return $offer;
	}

	/**
	 * Build ProductGroup schema for variable products.
	 *
	 * @param \WC_Product $product Variable product.
	 * @return array|null ProductGroup schema or null.
	 */
	private function get_product_group_schema( \WC_Product $product ): ?array {
		if ( ! $product->is_type( 'variable' ) ) {
			return null;
		}

		$variations = $product->get_available_variations();
		if ( empty( $variations ) ) {
			return null;
		}

		$permalink = get_permalink( $product->get_id() );

		$schema = [
			'@type'            => 'ProductGroup',
			'@id'              => $permalink . '#productgroup',
			'name'             => $product->get_name(),
			'url'              => $permalink,
			'productGroupID'   => (string) $product->get_id(),
			'hasVariant'       => [],
		];

		// Determine variesBy attributes.
		$attributes = $product->get_variation_attributes();
		$varies_by  = [];
		foreach ( array_keys( $attributes ) as $attr ) {
			$varies_by[] = str_replace( 'pa_', '', $attr );
		}
		if ( ! empty( $varies_by ) ) {
			$schema['variesBy'] = $varies_by;
		}

		// Add variant products (limit to 10 to avoid huge schemas).
		$count = 0;
		foreach ( $variations as $variation ) {
			if ( $count >= 10 ) {
				break;
			}

			$var_product = wc_get_product( $variation['variation_id'] );
			if ( ! $var_product ) {
				continue;
			}

			$variant = [
				'@type' => 'Product',
				'name'  => $var_product->get_name(),
				'sku'   => $var_product->get_sku() ?: (string) $var_product->get_id(),
				'offers' => $this->build_offers( $var_product ),
			];

			$image = wp_get_attachment_url( $var_product->get_image_id() );
			if ( $image ) {
				$variant['image'] = $image;
			}

			$schema['hasVariant'][] = $variant;
			$count++;
		}

		return $schema;
	}

	/**
	 * Build ItemList schema for product archive pages.
	 *
	 * @return array|null ItemList schema or null.
	 */
	private function get_product_list_schema(): ?array {
		global $wp_query;

		if ( empty( $wp_query->posts ) ) {
			return null;
		}

		$items = [];
		$pos   = 1;

		foreach ( $wp_query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}

			$items[] = [
				'@type'    => 'ListItem',
				'position' => $pos++,
				'url'      => get_permalink( $post->ID ),
				'name'     => $product->get_name(),
			];

			if ( $pos > 20 ) {
				break;
			}
		}

		if ( empty( $items ) ) {
			return null;
		}

		$page_url = is_shop() ? wc_get_page_permalink( 'shop' ) : '';
		if ( ! $page_url && is_product_category() ) {
			$term     = get_queried_object();
			$page_url = $term ? get_term_link( $term ) : '';
		}

		return [
			'@type'           => 'ItemList',
			'@id'             => ( $page_url ?: home_url() ) . '#itemlist',
			'itemListElement' => $items,
		];
	}
}
