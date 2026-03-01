<?php
/**
 * Ollama Provider.
 *
 * Integration with locally-hosted Ollama instances for free, private AI inference.
 *
 * @package SeoAi\Providers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Providers;

defined('ABSPATH') || exit;

/**
 * Class Ollama_Provider
 *
 * Implements the Provider_Interface for the Ollama local API.
 * Unlike cloud providers, Ollama requires no API key and runs entirely on the
 * user's own hardware. Models are fetched dynamically from the running instance.
 *
 * @since 1.0.0
 */
final class Ollama_Provider implements Provider_Interface {

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	private const ID = 'ollama';

	/**
	 * Human-readable provider name.
	 *
	 * @var string
	 */
	private const NAME = 'Ollama (Local)';

	/**
	 * Default API base URL.
	 *
	 * @var string
	 */
	private const DEFAULT_BASE_URL = 'http://localhost:11434';

	/**
	 * Default model when none is configured and dynamic fetch fails.
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'llama3.2';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 120;

	/**
	 * Transient key for caching the model list.
	 *
	 * @var string
	 */
	private const MODELS_CACHE_KEY = 'seo_ai_ollama_models';

	/**
	 * Cache duration for the model list (in seconds).
	 *
	 * @var int
	 */
	private const MODELS_CACHE_TTL = 300; // 5 minutes

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Ollama models are fetched dynamically from the running instance.
	 * Falls back to a static list if the instance is unreachable.
	 */
	public function get_models(): array {
		$dynamic = $this->fetch_models();

		if (! empty($dynamic)) {
			return $dynamic;
		}

		// Fallback when Ollama is not reachable.
		return [
			'llama3.2' => 'Llama 3.2 (default)',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_default_model(): string {
		return self::DEFAULT_MODEL;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_default_base_url(): string {
		return self::DEFAULT_BASE_URL;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Ollama does not require an API key. It is considered configured as long as
	 * a base URL is set (which defaults to localhost).
	 */
	public function is_configured(): bool {
		$base_url = $this->get_setting('base_url', self::DEFAULT_BASE_URL);

		return ! empty($base_url);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Tests connectivity by calling the /api/tags endpoint which lists models.
	 */
	public function test_connection(): array {
		$base_url = $this->get_setting('base_url', self::DEFAULT_BASE_URL);
		$url      = rtrim($base_url, '/') . '/api/tags';

		$response = wp_remote_get($url, [
			'timeout' => 15,
			'headers' => [
				'Accept' => 'application/json',
			],
		]);

		if (is_wp_error($response)) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message from WordPress HTTP API */
					__('Could not connect to Ollama: %s', 'seo-ai'),
					$response->get_error_message()
				),
			];
		}

		$status_code = wp_remote_retrieve_response_code($response);

		if ($status_code < 200 || $status_code >= 300) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__('Ollama returned HTTP status %d. Make sure Ollama is running.', 'seo-ai'),
					$status_code
				),
			];
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		$count = is_array($body['models'] ?? null) ? count($body['models']) : 0;

		return [
			'success' => true,
			'message' => sprintf(
				/* translators: %d: number of models available */
				_n(
					'Ollama is running. %d model available.',
					'Ollama is running. %d models available.',
					$count,
					'seo-ai'
				),
				$count
			),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function chat(string $system_prompt, string $user_prompt, array $options = []): string {
		$base_url    = $this->get_setting('base_url', self::DEFAULT_BASE_URL);
		$model       = $options['model'] ?? $this->get_setting('model', self::DEFAULT_MODEL);
		$temperature = (float) ($options['temperature'] ?? $this->get_setting('temperature', 0.7));

		$body = [
			'model'    => $model,
			'messages' => [
				[
					'role'    => 'system',
					'content' => $system_prompt,
				],
				[
					'role'    => 'user',
					'content' => $user_prompt,
				],
			],
			'stream'   => false,
			'options'  => [
				'temperature' => $temperature,
			],
		];

		$url = rtrim($base_url, '/') . '/api/chat';

		$response = wp_remote_post($url, [
			'timeout' => self::TIMEOUT,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode($body),
		]);

		if (is_wp_error($response)) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message from WordPress HTTP API */
					__('Ollama API request failed: %s', 'seo-ai'),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body_raw    = wp_remote_retrieve_body($response);
		$data        = json_decode($body_raw, true);

		if ($status_code < 200 || $status_code >= 300) {
			$error_message = $data['error'] ?? sprintf('HTTP %d', $status_code);

			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message from the API */
					__('Ollama API error: %s', 'seo-ai'),
					is_string($error_message) ? $error_message : wp_json_encode($error_message)
				)
			);
		}

		$content = $data['message']['content'] ?? null;

		if ($content === null) {
			throw new \RuntimeException(
				__('Ollama API returned an unexpected response format.', 'seo-ai')
			);
		}

		return trim($content);
	}

	/**
	 * Fetch available models from the Ollama instance.
	 *
	 * Results are cached as a WordPress transient to avoid hammering the
	 * local API on every page load.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Model IDs mapped to display names. Empty array on failure.
	 */
	public function fetch_models(): array {
		// Check transient cache first.
		$cached = get_transient(self::MODELS_CACHE_KEY);
		if (is_array($cached) && ! empty($cached)) {
			return $cached;
		}

		$base_url = $this->get_setting('base_url', self::DEFAULT_BASE_URL);
		$url      = rtrim($base_url, '/') . '/api/tags';

		$response = wp_remote_get($url, [
			'timeout' => 10,
			'headers' => [
				'Accept' => 'application/json',
			],
		]);

		if (is_wp_error($response)) {
			return [];
		}

		$status_code = wp_remote_retrieve_response_code($response);
		if ($status_code < 200 || $status_code >= 300) {
			return [];
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		if (! is_array($data['models'] ?? null)) {
			return [];
		}

		$models = [];

		foreach ($data['models'] as $model) {
			$name = $model['name'] ?? '';
			if (empty($name)) {
				continue;
			}

			// Build a human-readable label from available metadata.
			$size_bytes = $model['size'] ?? 0;
			$size_label = $size_bytes > 0
				? sprintf('%.1f GB', $size_bytes / 1073741824)
				: '';

			$label = $name;
			if ($size_label) {
				$label .= ' (' . $size_label . ')';
			}

			$models[$name] = $label;
		}

		if (! empty($models)) {
			set_transient(self::MODELS_CACHE_KEY, $models, self::MODELS_CACHE_TTL);
		}

		return $models;
	}

	/**
	 * Get a setting value for this provider.
	 *
	 * Reads from the provider-specific section of the `seo_ai_providers` option.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if the key does not exist.
	 *
	 * @return mixed Setting value.
	 */
	private function get_setting(string $key, mixed $default = null): mixed {
		$all_settings      = get_option(Provider_Manager::OPTION_KEY, []);
		$provider_settings = $all_settings['providers'][self::ID] ?? [];

		return $provider_settings[$key] ?? $default;
	}
}
