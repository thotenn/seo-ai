<?php
/**
 * Local SEO Module.
 *
 * Enhances Organization schema with LocalBusiness variants,
 * opening hours, and geo coordinates for local businesses.
 *
 * @package SeoAi\Modules\Local_Seo
 * @since   0.7.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Local_Seo;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Local_Seo
 *
 * Hooks into the Organization schema filter to upgrade it to a
 * LocalBusiness variant when configured, adding opening hours,
 * geo coordinates, and business-specific properties.
 *
 * @since 0.7.0
 */
final class Local_Seo {

	/**
	 * Supported LocalBusiness sub-types.
	 *
	 * @var string[]
	 */
	public const BUSINESS_TYPES = [
		'LocalBusiness',
		'Restaurant',
		'Store',
		'MedicalBusiness',
		'LegalService',
		'FinancialService',
		'EducationalOrganization',
		'EntertainmentBusiness',
		'FoodEstablishment',
		'HealthAndBeautyBusiness',
		'HomeAndConstructionBusiness',
		'LodgingBusiness',
		'ProfessionalService',
		'SportsActivityLocation',
		'AutoRepair',
		'BarOrPub',
		'CafeOrCoffeeShop',
		'Dentist',
		'RealEstateAgent',
	];

	/**
	 * Days of the week for opening hours.
	 *
	 * @var string[]
	 */
	private const DAYS = [
		'monday'    => 'Monday',
		'tuesday'   => 'Tuesday',
		'wednesday' => 'Wednesday',
		'thursday'  => 'Thursday',
		'friday'    => 'Friday',
		'saturday'  => 'Saturday',
		'sunday'    => 'Sunday',
	];

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'seo_ai/schema/organization', [ $this, 'enhance_local_schema' ], 10, 2 );
	}

	/**
	 * Enhance Organization schema with LocalBusiness data.
	 *
	 * If the local_business_type setting is set, upgrades the Organization
	 * schema to a LocalBusiness variant with opening hours, geo, price range, etc.
	 *
	 * @param array  $schema The Organization/Person schema entity.
	 * @param string $type   The entity type ('Organization' or 'Person').
	 * @return array Enhanced schema.
	 */
	public function enhance_local_schema( array $schema, string $type ): array {
		if ( 'Person' === $type ) {
			return $schema;
		}

		$options       = Options::instance();
		$business_type = (string) $options->get( 'local_business_type', '' );

		if ( '' === $business_type || ! in_array( $business_type, self::BUSINESS_TYPES, true ) ) {
			return $schema;
		}

		// Upgrade @type to LocalBusiness variant.
		$schema['@type'] = $business_type;

		// Price range.
		$price_range = (string) $options->get( 'local_price_range', '' );
		if ( '' !== $price_range ) {
			$schema['priceRange'] = $price_range;
		}

		// Geo coordinates.
		$latitude  = (string) $options->get( 'local_latitude', '' );
		$longitude = (string) $options->get( 'local_longitude', '' );
		if ( '' !== $latitude && '' !== $longitude ) {
			$schema['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $latitude,
				'longitude' => (float) $longitude,
			];
		}

		// Enhanced address.
		$street   = (string) $options->get( 'local_street', '' );
		$city     = (string) $options->get( 'local_city', '' );
		$state    = (string) $options->get( 'local_state', '' );
		$zip      = (string) $options->get( 'local_zip', '' );
		$country  = (string) $options->get( 'local_country', '' );

		if ( '' !== $street || '' !== $city ) {
			$address = [ '@type' => 'PostalAddress' ];
			if ( '' !== $street ) {
				$address['streetAddress'] = $street;
			}
			if ( '' !== $city ) {
				$address['addressLocality'] = $city;
			}
			if ( '' !== $state ) {
				$address['addressRegion'] = $state;
			}
			if ( '' !== $zip ) {
				$address['postalCode'] = $zip;
			}
			if ( '' !== $country ) {
				$address['addressCountry'] = $country;
			}
			$schema['address'] = $address;
		}

		// Opening hours.
		$hours = $this->build_opening_hours( $options );
		if ( ! empty( $hours ) ) {
			$schema['openingHoursSpecification'] = $hours;
		}

		// Area served.
		$area_served = (string) $options->get( 'local_area_served', '' );
		if ( '' !== $area_served ) {
			$schema['areaServed'] = [
				'@type' => 'GeoCircle',
				'geoMidpoint' => [
					'@type'     => 'GeoCoordinates',
					'latitude'  => (float) $latitude,
					'longitude' => (float) $longitude,
				],
			];
		}

		// Payment accepted.
		$payment = (string) $options->get( 'local_payment_accepted', '' );
		if ( '' !== $payment ) {
			$schema['paymentAccepted'] = $payment;
		}

		// Currencies accepted.
		$currencies = (string) $options->get( 'local_currencies_accepted', '' );
		if ( '' !== $currencies ) {
			$schema['currenciesAccepted'] = $currencies;
		}

		/**
		 * Filters the LocalBusiness schema entity.
		 *
		 * @since 0.7.0
		 *
		 * @param array  $schema        The LocalBusiness schema.
		 * @param string $business_type The business type.
		 */
		return (array) apply_filters( 'seo_ai/schema/local_business', $schema, $business_type );
	}

	/**
	 * Build OpeningHoursSpecification array from settings.
	 *
	 * Settings format: `local_hours_{day}_open` and `local_hours_{day}_close`
	 * (e.g., `local_hours_monday_open` = "09:00", `local_hours_monday_close` = "17:00")
	 *
	 * @param Options $options Options helper.
	 * @return array Array of OpeningHoursSpecification objects.
	 */
	private function build_opening_hours( Options $options ): array {
		$specs = [];

		foreach ( self::DAYS as $key => $day_name ) {
			$open  = (string) $options->get( "local_hours_{$key}_open", '' );
			$close = (string) $options->get( "local_hours_{$key}_close", '' );

			if ( '' !== $open && '' !== $close ) {
				$specs[] = [
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $day_name,
					'opens'     => $open,
					'closes'    => $close,
				];
			}
		}

		return $specs;
	}

	/**
	 * Get available business types for settings dropdowns.
	 *
	 * @return string[]
	 */
	public static function get_business_types(): array {
		return self::BUSINESS_TYPES;
	}

	/**
	 * Get days of the week.
	 *
	 * @return array<string, string>
	 */
	public static function get_days(): array {
		return self::DAYS;
	}
}
