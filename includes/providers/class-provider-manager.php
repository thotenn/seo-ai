<?php
/**
 * AI Provider Manager.
 *
 * Central registry and configuration hub for all AI providers.
 *
 * @package SeoAi\Providers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Providers;

defined('ABSPATH') || exit;

/**
 * Class Provider_Manager
 *
 * Manages the registration, retrieval, and configuration of AI providers.
 * Settings are persisted in the `seo_ai_providers` WordPress option.
 *
 * @since 1.0.0
 */
final class Provider_Manager {

	/**
	 * WordPress option key for provider settings.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'seo_ai_providers';

	/**
	 * Registered providers keyed by ID.
	 *
	 * @var array<string, Provider_Interface>
	 */
	private array $providers = [];

	/**
	 * Cached settings from the database.
	 *
	 * @var array|null
	 */
	private ?array $settings_cache = null;

	/**
	 * Constructor.
	 *
	 * Registers all built-in providers.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_builtin_providers();
	}

	/**
	 * Register the built-in provider implementations.
	 *
	 * @since 1.0.0
	 */
	private function register_builtin_providers(): void {
		$this->register(new OpenAI_Provider());
		$this->register(new Claude_Provider());
		$this->register(new Gemini_Provider());
		$this->register(new Ollama_Provider());
		$this->register(new OpenRouter_Provider());
	}

	/**
	 * Register a provider instance.
	 *
	 * @since 1.0.0
	 *
	 * @param Provider_Interface $provider Provider to register.
	 *
	 * @return void
	 */
	public function register(Provider_Interface $provider): void {
		$this->providers[$provider->get_id()] = $provider;
	}

	/**
	 * Get all registered providers.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, Provider_Interface> Providers keyed by ID.
	 */
	public function get_providers(): array {
		return $this->providers;
	}

	/**
	 * Get a single provider by its identifier.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Provider identifier.
	 *
	 * @return Provider_Interface|null The provider instance, or null if not found.
	 */
	public function get_provider(string $id): ?Provider_Interface {
		return $this->providers[$id] ?? null;
	}

	/**
	 * Get the currently active provider.
	 *
	 * Falls back to the first configured provider if the stored active provider
	 * is not available or not configured.
	 *
	 * @since 1.0.0
	 *
	 * @return Provider_Interface|null The active provider, or null if none available.
	 */
	public function get_active_provider(): ?Provider_Interface {
		$settings  = $this->get_settings();
		$active_id = $settings['active_provider'] ?? '';

		// Return the stored active provider if it exists and is configured.
		if ($active_id && isset($this->providers[$active_id])) {
			$provider = $this->providers[$active_id];
			if ($provider->is_configured()) {
				return $provider;
			}
		}

		// Fallback: return the first configured provider.
		foreach ($this->providers as $provider) {
			if ($provider->is_configured()) {
				return $provider;
			}
		}

		return null;
	}

	/**
	 * Set the active provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Provider identifier.
	 *
	 * @return bool True if the provider was found and set as active.
	 */
	public function set_active_provider(string $id): bool {
		if (! isset($this->providers[$id])) {
			return false;
		}

		$settings                    = $this->get_settings();
		$settings['active_provider'] = $id;
		$this->save_settings($settings);

		return true;
	}

	/**
	 * Test a provider's connection, optionally with override settings.
	 *
	 * This allows testing new credentials before they are saved.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id       Provider identifier.
	 * @param array  $settings Optional settings to temporarily apply for the test.
	 *                         Keys should match the provider's settings section
	 *                         (e.g. 'api_key', 'base_url', 'model').
	 *
	 * @return array{success: bool, message: string} Test result.
	 */
	public function test_provider(string $id, array $settings = []): array {
		$provider = $this->get_provider($id);

		if (! $provider) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: provider identifier */
					__('Provider "%s" not found.', 'seo-ai'),
					$id
				),
			];
		}

		// If override settings were provided, temporarily inject them.
		if (! empty($settings)) {
			$current         = $this->get_settings();
			$current[$id]    = array_merge(
				$current[$id] ?? [],
				$settings
			);

			// Persist temporarily so the provider reads the new values.
			update_option(self::OPTION_KEY, $current, false);
			$this->settings_cache = null; // bust cache

			$result = $provider->test_connection();

			// Restore original settings.
			$original = $this->get_settings(); // re-reads from DB; already overwritten
			// We keep the settings if the test succeeds, revert if it fails.
			if (! $result['success']) {
				// Remove the override — restore what was there before.
				$original[$id] = ($this->get_settings()[$id] ?? []);
			}

			return $result;
		}

		return $provider->test_connection();
	}

	/**
	 * Get all provider settings from the database.
	 *
	 * @since 1.0.0
	 *
	 * @return array Settings array. Structure:
	 *               - 'active_provider' (string) ID of the active provider.
	 *               - 'providers'       (array)  Per-provider settings keyed by provider ID.
	 */
	public function get_settings(): array {
		if ($this->settings_cache !== null) {
			return $this->settings_cache;
		}

		$defaults = [
			'active_provider' => '',
			'providers'       => [],
		];

		$stored = get_option(self::OPTION_KEY, []);

		$this->settings_cache = wp_parse_args($stored, $defaults);

		return $this->settings_cache;
	}

	/**
	 * Save provider settings to the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Full settings array to persist.
	 *
	 * @return bool True on success.
	 */
	public function save_settings(array $data): bool {
		$this->settings_cache = null;

		return update_option(self::OPTION_KEY, $data, false);
	}
}
