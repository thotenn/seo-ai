<?php
/**
 * Schema Manager Module.
 *
 * Manages JSON-LD structured data output on the WordPress frontend.
 * Builds a Schema.org @graph containing Website, Organization/Person,
 * BreadcrumbList, and content-type schemas (Article, FAQ, HowTo, Product).
 *
 * @package SeoAi\Modules\Schema
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SeoAi\Modules\Schema;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Schema_Manager
 *
 * Outputs a single <script type="application/ld+json"> block in wp_head
 * containing a @graph of all applicable structured data entities.
 *
 * @since 1.0.0
 */
final class Schema_Manager {

	/**
	 * Options helper instance.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->options = Options::instance();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_head', [ $this, 'output_schema' ], 10 );
	}

	/**
	 * Output the JSON-LD structured data block in the document head.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function output_schema(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$graph = $this->build_graph();

		if ( empty( $graph ) ) {
			return;
		}

		$schema = [
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		];

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		if ( ! $json ) {
			return;
		}

		echo "\n<!-- SEO AI Schema -->\n";
		echo '<script type="application/ld+json">' . "\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD must be raw.
		echo $json . "\n";
		echo '</script>' . "\n";
		echo "<!-- / SEO AI Schema -->\n\n";
	}

	/**
	 * Build the @graph array with all relevant schema entities.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of schema entities for the @graph.
	 */
	public function build_graph(): array {
		$graph = [];

		// Always include WebSite and Organization/Person.
		$website = $this->get_website_schema();

		if ( $website ) {
			$graph[] = $website;
		}

		$organization = $this->get_organization_schema();

		if ( $organization ) {
			$graph[] = $organization;
		}

		// Singular content schemas.
		if ( is_singular() ) {
			$post_id = get_queried_object_id();

			if ( $post_id > 0 ) {
				$breadcrumb = $this->get_breadcrumb_schema( $post_id );

				if ( $breadcrumb ) {
					$graph[] = $breadcrumb;
				}

				$post_schemas = $this->get_post_schema( $post_id );

				foreach ( $post_schemas as $schema ) {
					$graph[] = $schema;
				}
			}
		}

		/**
		 * Filters the complete Schema.org @graph array.
		 *
		 * Allows third-party code to add, modify, or remove schema entities
		 * before they are encoded and output.
		 *
		 * @since 1.0.0
		 *
		 * @param array $graph Array of schema entity arrays.
		 */
		$graph = (array) apply_filters( 'seo_ai/schema/graph', $graph );

		return $graph;
	}

	/**
	 * Get the WebSite schema entity.
	 *
	 * Includes a SearchAction for sitelinks search.
	 *
	 * @since 1.0.0
	 *
	 * @return array WebSite schema entity.
	 */
	public function get_website_schema(): array {
		$site_url = trailingslashit( home_url() );

		$schema = [
			'@type'           => 'WebSite',
			'@id'             => $site_url . '#website',
			'url'             => $site_url,
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'publisher'       => [
				'@id' => $site_url . '#organization',
			],
			'potentialAction' => [
				[
					'@type'       => 'SearchAction',
					'target'      => [
						'@type'       => 'EntryPoint',
						'urlTemplate' => home_url( '/?s={search_term_string}' ),
					],
					'query-input' => 'required name=search_term_string',
				],
			],
		];

		/**
		 * Filters the WebSite schema entity.
		 *
		 * @since 1.0.0
		 *
		 * @param array $schema The WebSite schema.
		 */
		return (array) apply_filters( 'seo_ai/schema/website', $schema );
	}

	/**
	 * Get the Organization or Person schema entity.
	 *
	 * Reads the entity type and details from plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array Organization or Person schema entity.
	 */
	public function get_organization_schema(): array {
		$site_url    = trailingslashit( home_url() );
		$schema_type = (string) $this->options->get( 'schema_type', 'Organization' );

		// Normalize to either Organization or Person.
		$type = in_array( $schema_type, [ 'Person', 'person' ], true ) ? 'Person' : 'Organization';

		$name = (string) $this->options->get( 'org_name', '' );

		if ( '' === $name ) {
			$name = get_bloginfo( 'name' );
		}

		$schema = [
			'@type' => $type,
			'@id'   => $site_url . '#organization',
			'name'  => $name,
			'url'   => (string) $this->options->get( 'org_url', '' ) ?: $site_url,
		];

		// Description.
		$description = (string) $this->options->get( 'org_description', '' );

		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		// Logo (Organization only; Person uses 'image').
		$logo = (string) $this->options->get( 'org_logo', '' );

		if ( '' !== $logo ) {
			if ( 'Person' === $type ) {
				$schema['image'] = $this->build_image_object( $logo, $name . ' photo' );
			} else {
				$schema['logo'] = $this->build_image_object( $logo, $name . ' logo' );
			}
		}

		// Contact info.
		$email = (string) $this->options->get( 'org_email', '' );

		if ( '' !== $email ) {
			$schema['email'] = $email;
		}

		$phone = (string) $this->options->get( 'org_phone', '' );

		if ( '' !== $phone ) {
			$schema['telephone'] = $phone;
		}

		// Founding date (Organization only).
		if ( 'Organization' === $type ) {
			$founding_date = (string) $this->options->get( 'org_founding_date', '' );

			if ( '' !== $founding_date ) {
				$schema['foundingDate'] = $founding_date;
			}
		}

		// Address.
		$address = (string) $this->options->get( 'org_address', '' );

		if ( '' !== $address ) {
			$schema['address'] = [
				'@type'         => 'PostalAddress',
				'streetAddress' => $address,
			];
		}

		// Social profiles (sameAs).
		$social_profiles = $this->options->get( 'org_social_profiles', [] );

		if ( is_array( $social_profiles ) && ! empty( $social_profiles ) ) {
			// Filter out empty strings.
			$profiles = array_values( array_filter( $social_profiles, function ( $url ) {
				return is_string( $url ) && '' !== trim( $url );
			} ) );

			if ( ! empty( $profiles ) ) {
				$schema['sameAs'] = $profiles;
			}
		}

		/**
		 * Filters the Organization/Person schema entity.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $schema The Organization/Person schema.
		 * @param string $type   The entity type ('Organization' or 'Person').
		 */
		return (array) apply_filters( 'seo_ai/schema/organization', $schema, $type );
	}

	/**
	 * Get the BreadcrumbList schema entity for a post.
	 *
	 * Builds breadcrumb items from the homepage through parent pages
	 * (for hierarchical post types) or category (for posts).
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return array|null BreadcrumbList schema entity, or null if not applicable.
	 */
	public function get_breadcrumb_schema( int $post_id ): ?array {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$items    = [];
		$position = 1;

		// Home.
		$items[] = [
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => __( 'Home', 'seo-ai' ),
			'item'     => home_url( '/' ),
		];

		// Hierarchical post types: add ancestors.
		if ( is_post_type_hierarchical( $post->post_type ) ) {
			$ancestors = array_reverse( get_post_ancestors( $post_id ) );

			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_post( $ancestor_id );

				if ( $ancestor instanceof \WP_Post ) {
					$items[] = [
						'@type'    => 'ListItem',
						'position' => $position++,
						'name'     => $ancestor->post_title,
						'item'     => get_permalink( $ancestor_id ),
					];
				}
			}
		} else {
			// Non-hierarchical: use the primary category (first category).
			$categories = get_the_category( $post_id );

			if ( ! empty( $categories ) ) {
				$category = $categories[0];

				// Add parent categories.
				$cat_ancestors = [];
				$parent_id     = $category->parent;

				while ( $parent_id > 0 ) {
					$parent_cat = get_category( $parent_id );

					if ( $parent_cat instanceof \WP_Term && ! is_wp_error( $parent_cat ) ) {
						$cat_ancestors[] = $parent_cat;
						$parent_id       = $parent_cat->parent;
					} else {
						break;
					}
				}

				// Add parent categories in order (from root to immediate parent).
				foreach ( array_reverse( $cat_ancestors ) as $ancestor_cat ) {
					$cat_link = get_term_link( $ancestor_cat );

					if ( ! is_wp_error( $cat_link ) ) {
						$items[] = [
							'@type'    => 'ListItem',
							'position' => $position++,
							'name'     => $ancestor_cat->name,
							'item'     => $cat_link,
						];
					}
				}

				// Add the primary category itself.
				$cat_link = get_term_link( $category );

				if ( ! is_wp_error( $cat_link ) ) {
					$items[] = [
						'@type'    => 'ListItem',
						'position' => $position++,
						'name'     => $category->name,
						'item'     => $cat_link,
					];
				}
			}
		}

		// Current post (last item).
		$items[] = [
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $post->post_title,
			'item'     => get_permalink( $post_id ),
		];

		$schema = [
			'@type'           => 'BreadcrumbList',
			'@id'             => get_permalink( $post_id ) . '#breadcrumb',
			'itemListElement' => $items,
		];

		/**
		 * Filters the BreadcrumbList schema entity.
		 *
		 * @since 1.0.0
		 *
		 * @param array $schema  The BreadcrumbList schema.
		 * @param int   $post_id The post ID.
		 */
		return (array) apply_filters( 'seo_ai/schema/breadcrumb', $schema, $post_id );
	}

	/**
	 * Get the schema entities for a post based on its configured schema type.
	 *
	 * Dispatches to type-specific builders (Article, FAQ, HowTo, Product).
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return array Array of schema entities.
	 */
	public function get_post_schema( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return [];
		}

		$schemas = [];

		// Determine schema type: per-post override or post-type default.
		$schema_type = (string) get_post_meta( $post_id, '_seo_ai_schema_type', true );

		if ( '' === $schema_type ) {
			$schema_type = (string) $this->options->get( "pt_{$post->post_type}_schema", '' );
		}

		if ( '' === $schema_type ) {
			$schema_type = 'post' === $post->post_type ? 'Article' : 'WebPage';
		}

		switch ( $schema_type ) {
			case 'Article':
			case 'NewsArticle':
			case 'BlogPosting':
				$schemas[] = $this->get_article_schema( $post, $schema_type );
				break;

			case 'FAQPage':
				$schemas[] = $this->get_faq_schema( $post );
				break;

			case 'HowTo':
				$schemas[] = $this->get_howto_schema( $post );
				break;

			case 'Product':
				$schemas[] = $this->get_product_schema( $post );
				break;

			case 'Recipe':
			case 'JobPosting':
				// Handled by Schema_Builder via the seo_ai/schema/graph filter.
				$schemas[] = $this->get_webpage_schema( $post );
				break;

			case 'WebPage':
			default:
				$schemas[] = $this->get_webpage_schema( $post );
				break;
		}

		// Filter out empty/null entries.
		return array_filter( $schemas );
	}

	/**
	 * Get the Article schema entity (Article, BlogPosting, or NewsArticle).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post        The post object.
	 * @param string   $article_type The article @type (Article, NewsArticle, BlogPosting).
	 * @return array Article schema entity.
	 */
	public function get_article_schema( \WP_Post $post, string $article_type = 'Article' ): array {
		$site_url  = trailingslashit( home_url() );
		$permalink = get_permalink( $post );

		$schema = [
			'@type'            => $article_type,
			'@id'              => $permalink . '#article',
			'isPartOf'         => [ '@id' => $permalink . '#webpage' ],
			'headline'         => mb_substr( $post->post_title, 0, 110, 'UTF-8' ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'mainEntityOfPage' => [ '@id' => $permalink . '#webpage' ],
			'wordCount'        => str_word_count( wp_strip_all_tags( $post->post_content ) ),
			'inLanguage'       => get_bloginfo( 'language' ),
		];

		// Author.
		$author = get_userdata( (int) $post->post_author );

		if ( $author instanceof \WP_User ) {
			$schema['author'] = [
				'@type' => 'Person',
				'name'  => $author->display_name,
				'url'   => get_author_posts_url( $author->ID ),
			];
		}

		// Publisher references the Organization.
		$schema['publisher'] = [ '@id' => $site_url . '#organization' ];

		// Featured image.
		$image = $this->get_featured_image_schema( $post->ID );

		if ( $image ) {
			$schema['image'] = $image;
		}

		// Description.
		$description = (string) get_post_meta( $post->ID, '_seo_ai_description', true );

		if ( '' === $description ) {
			$description = $post->post_excerpt ?: wp_trim_words( $post->post_content, 55, '...' );
		}

		if ( '' !== $description ) {
			$schema['description'] = wp_strip_all_tags( $description );
		}

		// Keywords from focus keyword meta.
		$keywords = (string) get_post_meta( $post->ID, '_seo_ai_focus_keyword', true );

		if ( '' !== $keywords ) {
			$schema['keywords'] = $keywords;
		}

		// Add the WebPage entity that this article is part of.
		$webpage = $this->get_webpage_schema( $post );

		/**
		 * Filters the Article schema entity.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $schema The Article schema.
		 * @param \WP_Post $post   The post object.
		 */
		return (array) apply_filters( 'seo_ai/schema/article', $schema, $post );
	}

	/**
	 * Get the FAQPage schema entity.
	 *
	 * Reads FAQ data from `_seo_ai_schema_faq` post meta.
	 * Expected format: JSON array of {question, answer} objects.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The post object.
	 * @return array|null FAQPage schema entity, or null if no FAQ data.
	 */
	public function get_faq_schema( \WP_Post $post ): ?array {
		$raw = get_post_meta( $post->ID, '_seo_ai_schema_faq', true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$faq_data = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$faq_data = $raw;
		} else {
			$faq_data = null;
		}

		if ( ! is_array( $faq_data ) || empty( $faq_data ) ) {
			return null;
		}

		$questions = [];

		foreach ( $faq_data as $item ) {
			if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
				continue;
			}

			$questions[] = [
				'@type'          => 'Question',
				'name'           => sanitize_text_field( $item['question'] ),
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => wp_kses_post( $item['answer'] ),
				],
			];
		}

		if ( empty( $questions ) ) {
			return null;
		}

		$schema = [
			'@type'            => 'FAQPage',
			'@id'              => get_permalink( $post ) . '#faq',
			'mainEntity'       => $questions,
			'mainEntityOfPage' => [ '@id' => get_permalink( $post ) . '#webpage' ],
		];

		/**
		 * Filters the FAQPage schema entity.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $schema The FAQPage schema.
		 * @param \WP_Post $post   The post object.
		 */
		return (array) apply_filters( 'seo_ai/schema/faq', $schema, $post );
	}

	/**
	 * Get the HowTo schema entity.
	 *
	 * Reads HowTo data from `_seo_ai_schema_howto` post meta.
	 * Expected format: JSON object with 'name', 'description', 'totalTime',
	 * and 'steps' array of {name, text, image?} objects.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The post object.
	 * @return array|null HowTo schema entity, or null if no HowTo data.
	 */
	public function get_howto_schema( \WP_Post $post ): ?array {
		$raw = get_post_meta( $post->ID, '_seo_ai_schema_howto', true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$howto_data = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$howto_data = $raw;
		} else {
			$howto_data = null;
		}

		if ( ! is_array( $howto_data ) ) {
			return null;
		}

		$steps_data = $howto_data['steps'] ?? [];

		if ( ! is_array( $steps_data ) || empty( $steps_data ) ) {
			return null;
		}

		$steps = [];

		foreach ( $steps_data as $index => $step ) {
			if ( empty( $step['text'] ) ) {
				continue;
			}

			$step_schema = [
				'@type' => 'HowToStep',
				'text'  => wp_kses_post( $step['text'] ),
			];

			if ( ! empty( $step['name'] ) ) {
				$step_schema['name'] = sanitize_text_field( $step['name'] );
			}

			if ( ! empty( $step['image'] ) ) {
				$step_schema['image'] = esc_url( $step['image'] );
			}

			$step_schema['position'] = $index + 1;

			$steps[] = $step_schema;
		}

		if ( empty( $steps ) ) {
			return null;
		}

		$schema = [
			'@type'            => 'HowTo',
			'@id'              => get_permalink( $post ) . '#howto',
			'name'             => ! empty( $howto_data['name'] )
				? sanitize_text_field( $howto_data['name'] )
				: $post->post_title,
			'step'             => $steps,
			'mainEntityOfPage' => [ '@id' => get_permalink( $post ) . '#webpage' ],
		];

		if ( ! empty( $howto_data['description'] ) ) {
			$schema['description'] = wp_kses_post( $howto_data['description'] );
		}

		if ( ! empty( $howto_data['totalTime'] ) ) {
			$schema['totalTime'] = sanitize_text_field( $howto_data['totalTime'] );
		}

		// Featured image.
		$image = $this->get_featured_image_schema( $post->ID );

		if ( $image ) {
			$schema['image'] = $image;
		}

		/**
		 * Filters the HowTo schema entity.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $schema The HowTo schema.
		 * @param \WP_Post $post   The post object.
		 */
		return (array) apply_filters( 'seo_ai/schema/howto', $schema, $post );
	}

	/**
	 * Get the Product schema entity.
	 *
	 * Reads Product data from `_seo_ai_schema_product` post meta.
	 * Expected format: JSON object with 'name', 'description', 'sku',
	 * 'brand', 'price', 'currency', 'availability', 'review' fields.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The post object.
	 * @return array|null Product schema entity, or null if no Product data.
	 */
	public function get_product_schema( \WP_Post $post ): ?array {
		$raw = get_post_meta( $post->ID, '_seo_ai_schema_product', true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$product_data = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$product_data = $raw;
		} else {
			$product_data = null;
		}

		if ( ! is_array( $product_data ) ) {
			return null;
		}

		$schema = [
			'@type'            => 'Product',
			'@id'              => get_permalink( $post ) . '#product',
			'name'             => ! empty( $product_data['name'] )
				? sanitize_text_field( $product_data['name'] )
				: $post->post_title,
			'mainEntityOfPage' => [ '@id' => get_permalink( $post ) . '#webpage' ],
		];

		if ( ! empty( $product_data['description'] ) ) {
			$schema['description'] = wp_kses_post( $product_data['description'] );
		}

		if ( ! empty( $product_data['sku'] ) ) {
			$schema['sku'] = sanitize_text_field( $product_data['sku'] );
		}

		if ( ! empty( $product_data['brand'] ) ) {
			$schema['brand'] = [
				'@type' => 'Brand',
				'name'  => sanitize_text_field( $product_data['brand'] ),
			];
		}

		// Featured image.
		$image = $this->get_featured_image_schema( $post->ID );

		if ( $image ) {
			$schema['image'] = $image;
		}

		// Offers (price information).
		if ( ! empty( $product_data['price'] ) ) {
			$offer = [
				'@type'         => 'Offer',
				'price'         => (string) $product_data['price'],
				'priceCurrency' => ! empty( $product_data['currency'] )
					? strtoupper( sanitize_text_field( $product_data['currency'] ) )
					: 'USD',
				'url'           => get_permalink( $post ),
			];

			if ( ! empty( $product_data['availability'] ) ) {
				$availability_map = [
					'in_stock'     => 'https://schema.org/InStock',
					'out_of_stock' => 'https://schema.org/OutOfStock',
					'pre_order'    => 'https://schema.org/PreOrder',
					'discontinued' => 'https://schema.org/Discontinued',
				];

				$availability_key = strtolower( str_replace( ' ', '_', $product_data['availability'] ) );

				$offer['availability'] = $availability_map[ $availability_key ]
					?? 'https://schema.org/InStock';
			}

			$schema['offers'] = $offer;
		}

		// Aggregate rating.
		if ( ! empty( $product_data['rating_value'] ) && ! empty( $product_data['rating_count'] ) ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product_data['rating_value'],
				'reviewCount' => (int) $product_data['rating_count'],
			];
		}

		// Single review.
		if ( ! empty( $product_data['review'] ) && is_array( $product_data['review'] ) ) {
			$review = $product_data['review'];

			if ( ! empty( $review['author'] ) && ! empty( $review['rating'] ) ) {
				$schema['review'] = [
					'@type'        => 'Review',
					'author'       => [
						'@type' => 'Person',
						'name'  => sanitize_text_field( $review['author'] ),
					],
					'reviewRating' => [
						'@type'       => 'Rating',
						'ratingValue' => (string) $review['rating'],
					],
				];

				if ( ! empty( $review['body'] ) ) {
					$schema['review']['reviewBody'] = wp_kses_post( $review['body'] );
				}
			}
		}

		/**
		 * Filters the Product schema entity.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $schema The Product schema.
		 * @param \WP_Post $post   The post object.
		 */
		return (array) apply_filters( 'seo_ai/schema/product', $schema, $post );
	}

	// -------------------------------------------------------------------------
	// Private helpers.
	// -------------------------------------------------------------------------

	/**
	 * Get the WebPage schema entity for a post.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array WebPage schema entity.
	 */
	private function get_webpage_schema( \WP_Post $post ): array {
		$site_url  = trailingslashit( home_url() );
		$permalink = get_permalink( $post );

		$schema = [
			'@type'         => 'WebPage',
			'@id'           => $permalink . '#webpage',
			'url'           => $permalink,
			'name'          => $post->post_title,
			'isPartOf'      => [ '@id' => $site_url . '#website' ],
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
			'inLanguage'    => get_bloginfo( 'language' ),
		];

		// Description.
		$description = (string) get_post_meta( $post->ID, '_seo_ai_description', true );

		if ( '' === $description ) {
			$description = $post->post_excerpt ?: wp_trim_words( $post->post_content, 55, '...' );
		}

		if ( '' !== $description ) {
			$schema['description'] = wp_strip_all_tags( $description );
		}

		// Breadcrumb reference.
		$schema['breadcrumb'] = [ '@id' => $permalink . '#breadcrumb' ];

		// Featured image.
		$image = $this->get_featured_image_schema( $post->ID );

		if ( $image ) {
			$schema['primaryImageOfPage'] = $image;
		}

		/**
		 * Filters the WebPage schema entity.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $schema The WebPage schema.
		 * @param \WP_Post $post   The post object.
		 */
		return (array) apply_filters( 'seo_ai/schema/webpage', $schema, $post );
	}

	/**
	 * Get the featured image as an ImageObject schema.
	 *
	 * @param int $post_id The post ID.
	 * @return array|null ImageObject schema array, or null if no featured image.
	 */
	private function get_featured_image_schema( int $post_id ): ?array {
		$thumbnail_id = get_post_thumbnail_id( $post_id );

		if ( ! $thumbnail_id ) {
			return null;
		}

		$image_url = wp_get_attachment_url( (int) $thumbnail_id );

		if ( ! $image_url ) {
			return null;
		}

		$metadata = wp_get_attachment_metadata( (int) $thumbnail_id );

		$image = [
			'@type' => 'ImageObject',
			'url'   => $image_url,
		];

		if ( is_array( $metadata ) ) {
			if ( ! empty( $metadata['width'] ) ) {
				$image['width'] = (int) $metadata['width'];
			}

			if ( ! empty( $metadata['height'] ) ) {
				$image['height'] = (int) $metadata['height'];
			}
		}

		$alt = get_post_meta( (int) $thumbnail_id, '_wp_attachment_image_alt', true );

		if ( is_string( $alt ) && '' !== $alt ) {
			$image['caption'] = $alt;
		}

		return $image;
	}

	/**
	 * Build an ImageObject from a URL or attachment ID.
	 *
	 * @param string $url_or_id  Image URL or attachment ID.
	 * @param string $caption    Optional caption/alt text.
	 * @return array ImageObject schema array.
	 */
	private function build_image_object( string $url_or_id, string $caption = '' ): array {
		$image = [
			'@type' => 'ImageObject',
		];

		// Check if it's a numeric attachment ID.
		if ( is_numeric( $url_or_id ) ) {
			$url = wp_get_attachment_url( (int) $url_or_id );

			if ( $url ) {
				$image['url'] = $url;
				$metadata     = wp_get_attachment_metadata( (int) $url_or_id );

				if ( is_array( $metadata ) ) {
					if ( ! empty( $metadata['width'] ) ) {
						$image['width'] = (int) $metadata['width'];
					}
					if ( ! empty( $metadata['height'] ) ) {
						$image['height'] = (int) $metadata['height'];
					}
				}
			} else {
				$image['url'] = $url_or_id;
			}
		} else {
			$image['url'] = esc_url( $url_or_id );
		}

		if ( '' !== $caption ) {
			$image['caption'] = $caption;
		}

		return $image;
	}
}
