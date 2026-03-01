<?php
namespace SeoAi\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Options helper for centralized plugin settings access.
 *
 * Provides a cached interface to the `seo_ai_settings` option
 * and the `seo_ai_providers` provider configuration option.
 *
 * @since 1.0.0
 */
class Options {

	/**
	 * Option key for main plugin settings.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'seo_ai_settings';

	/**
	 * Option key for provider settings.
	 *
	 * @var string
	 */
	private const PROVIDERS_KEY = 'seo_ai_providers';

	/**
	 * Cached settings array.
	 *
	 * @var array|null
	 */
	private ?array $cache = null;

	/**
	 * Cached provider settings array.
	 *
	 * @var array|null
	 */
	private ?array $provider_cache = null;

	/**
	 * Whether the settings have been modified and need saving.
	 *
	 * @var bool
	 */
	private bool $dirty = false;

	/**
	 * Static singleton instance.
	 *
	 * @var Options|null
	 */
	private static ?Options $instance = null;

	/**
	 * Get the static singleton instance.
	 *
	 * @return Options
	 */
	public static function instance(): Options {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get a setting value by key.
	 *
	 * @param string $key     The setting key (dot notation not supported, use flat keys).
	 * @param mixed  $default Default value if the key does not exist.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$settings = $this->get_all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Set a setting value by key.
	 *
	 * Changes are held in cache until `save()` is called.
	 *
	 * @param string $key   The setting key.
	 * @param mixed  $value The value to set.
	 * @return Options This instance, for chaining.
	 */
	public function set( string $key, mixed $value ): Options {
		$this->load_settings();

		$this->cache[ $key ] = $value;
		$this->dirty         = true;

		return $this;
	}

	/**
	 * Delete a setting by key.
	 *
	 * Changes are held in cache until `save()` is called.
	 *
	 * @param string $key The setting key to remove.
	 * @return Options This instance, for chaining.
	 */
	public function delete( string $key ): Options {
		$this->load_settings();

		if ( array_key_exists( $key, $this->cache ) ) {
			unset( $this->cache[ $key ] );
			$this->dirty = true;
		}

		return $this;
	}

	/**
	 * Get all plugin settings.
	 *
	 * @return array
	 */
	public function get_all(): array {
		$this->load_settings();

		return $this->cache;
	}

	/**
	 * Persist all pending changes to the database.
	 *
	 * @return bool True if the option was updated or no changes were pending, false on failure.
	 */
	public function save(): bool {
		if ( ! $this->dirty ) {
			return true;
		}

		$result      = update_option( self::OPTION_KEY, $this->cache, 'no' );
		$this->dirty = false;

		return $result;
	}

	/**
	 * Bulk-update settings from an associative array.
	 *
	 * Merges the provided data into existing settings and persists immediately.
	 *
	 * @param array $data Key-value pairs of settings to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( array $data ): bool {
		$this->load_settings();

		$this->cache = array_merge( $this->cache, $data );
		$this->dirty = true;

		return $this->save();
	}

	/**
	 * Get AI provider settings.
	 *
	 * @return array
	 */
	public function get_provider_settings(): array {
		if ( null === $this->provider_cache ) {
			$this->provider_cache = get_option( self::PROVIDERS_KEY, [] );

			if ( ! is_array( $this->provider_cache ) ) {
				$this->provider_cache = [];
			}
		}

		return $this->provider_cache;
	}

	/**
	 * Update AI provider settings.
	 *
	 * Merges the provided data into existing provider settings and persists.
	 *
	 * @param array $data Provider settings data.
	 * @return bool True on success, false on failure.
	 */
	public function set_provider_settings( array $data ): bool {
		$current = $this->get_provider_settings();

		// Deep merge provider-specific keys.
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) && isset( $current[ $key ] ) && is_array( $current[ $key ] ) ) {
				$current[ $key ] = array_merge( $current[ $key ], $value );
			} else {
				$current[ $key ] = $value;
			}
		}

		$this->provider_cache = $current;

		return update_option( self::PROVIDERS_KEY, $this->provider_cache, 'no' );
	}

	/**
	 * Get settings for a specific AI provider.
	 *
	 * @param string $provider_slug The provider slug (e.g., 'openai', 'ollama').
	 * @return array
	 */
	public function get_provider( string $provider_slug ): array {
		$providers = $this->get_provider_settings();

		return $providers[ $provider_slug ] ?? [];
	}

	/**
	 * Get the slug of the currently active AI provider.
	 *
	 * @return string
	 */
	public function get_active_provider(): string {
		$providers = $this->get_provider_settings();

		return $providers['active_provider'] ?? 'ollama';
	}

	/**
	 * Invalidate the internal cache, forcing a reload on next access.
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		$this->cache          = null;
		$this->provider_cache = null;
		$this->dirty          = false;
	}

	/**
	 * Load settings from the database into cache if not already loaded.
	 *
	 * @return void
	 */
	private function load_settings(): void {
		if ( null !== $this->cache ) {
			return;
		}

		$this->cache = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $this->cache ) ) {
			$this->cache = [];
		}
	}
}
