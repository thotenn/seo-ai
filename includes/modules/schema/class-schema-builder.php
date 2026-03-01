<?php
/**
 * Schema Builder for Advanced Schema Types.
 *
 * Adds Recipe and JobPosting schema types to the Schema Manager.
 * Reads structured data from post meta and generates valid JSON-LD.
 *
 * @package SeoAi\Modules\Schema
 * @since   0.6.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema_Builder
 *
 * Hooks into the Schema Manager's graph filter to add Recipe and
 * JobPosting schemas when the corresponding post meta is present.
 *
 * @since 0.6.0
 */
final class Schema_Builder {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'seo_ai/schema/graph', [ $this, 'add_advanced_schemas' ], 20 );
	}

	/**
	 * Add advanced schema types to the @graph if applicable.
	 *
	 * @param array $graph The existing schema @graph.
	 * @return array Modified graph.
	 */
	public function add_advanced_schemas( array $graph ): array {
		if ( ! is_singular() ) {
			return $graph;
		}

		$post_id     = get_queried_object_id();
		$schema_type = (string) get_post_meta( $post_id, '_seo_ai_schema_type', true );

		if ( 'Recipe' === $schema_type ) {
			$recipe = $this->get_recipe_schema( $post_id );
			if ( $recipe ) {
				$graph[] = $recipe;
			}
		}

		if ( 'JobPosting' === $schema_type ) {
			$job = $this->get_job_schema( $post_id );
			if ( $job ) {
				$graph[] = $job;
			}
		}

		return $graph;
	}

	/**
	 * Build Recipe schema from post meta.
	 *
	 * Meta key: `_seo_ai_schema_recipe` (JSON object)
	 * Expected fields: name, description, prep_time, cook_time, total_time,
	 * yield, category, cuisine, calories, ingredients (array), instructions (array of {name, text}),
	 * image
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Recipe schema entity or null.
	 */
	private function get_recipe_schema( int $post_id ): ?array {
		$raw = get_post_meta( $post_id, '_seo_ai_schema_recipe', true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$data = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$data = $raw;
		} else {
			return null;
		}

		if ( ! is_array( $data ) ) {
			return null;
		}

		$post      = get_post( $post_id );
		$permalink = get_permalink( $post_id );

		$schema = [
			'@type'            => 'Recipe',
			'@id'              => $permalink . '#recipe',
			'name'             => ! empty( $data['name'] )
				? sanitize_text_field( $data['name'] )
				: ( $post ? $post->post_title : '' ),
			'mainEntityOfPage' => [ '@id' => $permalink . '#webpage' ],
		];

		// Description.
		if ( ! empty( $data['description'] ) ) {
			$schema['description'] = wp_kses_post( $data['description'] );
		}

		// Author.
		if ( $post ) {
			$author = get_userdata( (int) $post->post_author );
			if ( $author instanceof \WP_User ) {
				$schema['author'] = [
					'@type' => 'Person',
					'name'  => $author->display_name,
				];
			}
		}

		// Date.
		if ( $post ) {
			$schema['datePublished'] = get_the_date( 'c', $post );
		}

		// Image.
		if ( ! empty( $data['image'] ) ) {
			$schema['image'] = esc_url( $data['image'] );
		} elseif ( $post && has_post_thumbnail( $post_id ) ) {
			$schema['image'] = get_the_post_thumbnail_url( $post_id, 'full' );
		}

		// Times (ISO 8601 duration, e.g., PT30M).
		if ( ! empty( $data['prep_time'] ) ) {
			$schema['prepTime'] = sanitize_text_field( $data['prep_time'] );
		}
		if ( ! empty( $data['cook_time'] ) ) {
			$schema['cookTime'] = sanitize_text_field( $data['cook_time'] );
		}
		if ( ! empty( $data['total_time'] ) ) {
			$schema['totalTime'] = sanitize_text_field( $data['total_time'] );
		}

		// Yield.
		if ( ! empty( $data['yield'] ) ) {
			$schema['recipeYield'] = sanitize_text_field( $data['yield'] );
		}

		// Category & Cuisine.
		if ( ! empty( $data['category'] ) ) {
			$schema['recipeCategory'] = sanitize_text_field( $data['category'] );
		}
		if ( ! empty( $data['cuisine'] ) ) {
			$schema['recipeCuisine'] = sanitize_text_field( $data['cuisine'] );
		}

		// Nutrition.
		if ( ! empty( $data['calories'] ) ) {
			$schema['nutrition'] = [
				'@type'    => 'NutritionInformation',
				'calories' => sanitize_text_field( $data['calories'] ),
			];
		}

		// Ingredients.
		if ( ! empty( $data['ingredients'] ) && is_array( $data['ingredients'] ) ) {
			$schema['recipeIngredient'] = array_map( 'sanitize_text_field', $data['ingredients'] );
		}

		// Instructions.
		if ( ! empty( $data['instructions'] ) && is_array( $data['instructions'] ) ) {
			$steps = [];
			foreach ( $data['instructions'] as $index => $step ) {
				if ( empty( $step['text'] ) ) {
					continue;
				}
				$step_schema = [
					'@type'    => 'HowToStep',
					'position' => $index + 1,
					'text'     => wp_kses_post( $step['text'] ),
				];
				if ( ! empty( $step['name'] ) ) {
					$step_schema['name'] = sanitize_text_field( $step['name'] );
				}
				if ( ! empty( $step['image'] ) ) {
					$step_schema['image'] = esc_url( $step['image'] );
				}
				$steps[] = $step_schema;
			}
			if ( ! empty( $steps ) ) {
				$schema['recipeInstructions'] = $steps;
			}
		}

		// Rating.
		if ( ! empty( $data['rating_value'] ) && ! empty( $data['rating_count'] ) ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $data['rating_value'],
				'reviewCount' => (int) $data['rating_count'],
			];
		}

		/**
		 * Filters the Recipe schema entity.
		 *
		 * @since 0.6.0
		 *
		 * @param array $schema  The Recipe schema.
		 * @param int   $post_id The post ID.
		 */
		return (array) apply_filters( 'seo_ai/schema/recipe', $schema, $post_id );
	}

	/**
	 * Build JobPosting schema from post meta.
	 *
	 * Meta key: `_seo_ai_schema_job` (JSON object)
	 * Expected fields: title, description, employer_name, employer_url,
	 * employer_logo, location, remote, salary_min, salary_max, salary_currency,
	 * salary_unit, employment_type, date_posted, valid_through, qualifications
	 *
	 * @param int $post_id Post ID.
	 * @return array|null JobPosting schema entity or null.
	 */
	private function get_job_schema( int $post_id ): ?array {
		$raw = get_post_meta( $post_id, '_seo_ai_schema_job', true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$data = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$data = $raw;
		} else {
			return null;
		}

		if ( ! is_array( $data ) ) {
			return null;
		}

		$post      = get_post( $post_id );
		$permalink = get_permalink( $post_id );

		$schema = [
			'@type'            => 'JobPosting',
			'@id'              => $permalink . '#jobposting',
			'title'            => ! empty( $data['title'] )
				? sanitize_text_field( $data['title'] )
				: ( $post ? $post->post_title : '' ),
			'mainEntityOfPage' => [ '@id' => $permalink . '#webpage' ],
		];

		// Description.
		if ( ! empty( $data['description'] ) ) {
			$schema['description'] = wp_kses_post( $data['description'] );
		} elseif ( $post ) {
			$schema['description'] = wp_trim_words( wp_strip_all_tags( $post->post_content ), 100, '...' );
		}

		// Date posted.
		if ( ! empty( $data['date_posted'] ) ) {
			$schema['datePosted'] = sanitize_text_field( $data['date_posted'] );
		} elseif ( $post ) {
			$schema['datePosted'] = get_the_date( 'Y-m-d', $post );
		}

		// Valid through.
		if ( ! empty( $data['valid_through'] ) ) {
			$schema['validThrough'] = sanitize_text_field( $data['valid_through'] );
		}

		// Employer / Hiring Organization.
		if ( ! empty( $data['employer_name'] ) ) {
			$employer = [
				'@type' => 'Organization',
				'name'  => sanitize_text_field( $data['employer_name'] ),
			];
			if ( ! empty( $data['employer_url'] ) ) {
				$employer['sameAs'] = esc_url( $data['employer_url'] );
			}
			if ( ! empty( $data['employer_logo'] ) ) {
				$employer['logo'] = esc_url( $data['employer_logo'] );
			}
			$schema['hiringOrganization'] = $employer;
		}

		// Job Location.
		if ( ! empty( $data['location'] ) ) {
			$schema['jobLocation'] = [
				'@type'   => 'Place',
				'address' => [
					'@type'         => 'PostalAddress',
					'streetAddress' => sanitize_text_field( $data['location'] ),
				],
			];
		}

		// Remote work.
		if ( ! empty( $data['remote'] ) ) {
			$schema['jobLocationType']   = 'TELECOMMUTE';
			$schema['applicantLocationRequirements'] = [
				'@type' => 'Country',
				'name'  => 'Anywhere',
			];
		}

		// Employment type.
		if ( ! empty( $data['employment_type'] ) ) {
			$schema['employmentType'] = strtoupper( sanitize_text_field( $data['employment_type'] ) );
		}

		// Salary / Base Salary.
		if ( ! empty( $data['salary_min'] ) || ! empty( $data['salary_max'] ) ) {
			$salary = [
				'@type'    => 'MonetaryAmount',
				'currency' => ! empty( $data['salary_currency'] )
					? strtoupper( sanitize_text_field( $data['salary_currency'] ) )
					: 'USD',
			];

			$value = [ '@type' => 'QuantitativeValue' ];

			if ( ! empty( $data['salary_min'] ) ) {
				$value['minValue'] = (float) $data['salary_min'];
			}
			if ( ! empty( $data['salary_max'] ) ) {
				$value['maxValue'] = (float) $data['salary_max'];
			}
			if ( ! empty( $data['salary_unit'] ) ) {
				$unit_map = [
					'hour'  => 'HOUR',
					'day'   => 'DAY',
					'week'  => 'WEEK',
					'month' => 'MONTH',
					'year'  => 'YEAR',
				];
				$value['unitText'] = $unit_map[ strtolower( $data['salary_unit'] ) ] ?? 'YEAR';
			}

			$salary['value']     = $value;
			$schema['baseSalary'] = $salary;
		}

		// Qualifications.
		if ( ! empty( $data['qualifications'] ) ) {
			$schema['qualifications'] = sanitize_text_field( $data['qualifications'] );
		}

		/**
		 * Filters the JobPosting schema entity.
		 *
		 * @since 0.6.0
		 *
		 * @param array $schema  The JobPosting schema.
		 * @param int   $post_id The post ID.
		 */
		return (array) apply_filters( 'seo_ai/schema/job', $schema, $post_id );
	}
}
