<?php
/**
 * Gemini Provider.
 *
 * Integration with Google's Generative Language API (Gemini models).
 *
 * @package SeoAi\Providers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Providers;

defined('ABSPATH') || exit;

/**
 * Class Gemini_Provider
 *
 * Implements the Provider_Interface for the Google Gemini API.
 *
 * @since 1.0.0
 */
final class Gemini_Provider implements Provider_Interface {

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	private const ID = 'gemini';

	/**
	 * Human-readable provider name.
	 *
	 * @var string
	 */
	private const NAME = 'Google (Gemini)';

	/**
	 * Default API base URL.
	 *
	 * @var string
	 */
	private const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com';

	/**
	 * Default model.
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'gemini-2.0-flash';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 120;

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
	 */
	public function get_models(): array {
		return [
			'gemini-2.0-flash' => 'Gemini 2.0 Flash (fast, efficient)',
			'gemini-2.5-pro'   => 'Gemini 2.5 Pro (advanced reasoning)',
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
	 */
	public function is_configured(): bool {
		$api_key = $this->get_setting('api_key', '');

		return ! empty($api_key);
	}

	/**
	 * {@inheritDoc}
	 */
	public function test_connection(): array {
		if (! $this->is_configured()) {
			return [
				'success' => false,
				'message' => __('Google API key is not configured.', 'seo-ai'),
			];
		}

		try {
			$response = $this->chat(
				'You are a helpful assistant.',
				'Say hello in one short sentence.',
				[
					'temperature' => 0.0,
				]
			);

			return [
				'success' => true,
				'message' => sprintf(
					/* translators: %s: response text from the API */
					__('Connection successful. Response: %s', 'seo-ai'),
					$response
				),
			];
		} catch (\RuntimeException $e) {
			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function chat(string $system_prompt, string $user_prompt, array $options = []): string {
		$api_key = $this->get_setting('api_key', '');

		if (empty($api_key)) {
			throw new \RuntimeException(
				__('Google API key is not configured.', 'seo-ai')
			);
		}

		$base_url    = $this->get_setting('base_url', self::DEFAULT_BASE_URL);
		$model       = $options['model'] ?? $this->get_setting('model', self::DEFAULT_MODEL);
		$temperature = (float) ($options['temperature'] ?? $this->get_setting('temperature', 0.7));

		// Gemini uses a combined prompt: system instructions prepended to user content.
		$combined_prompt = $system_prompt . "\n\n" . $user_prompt;

		$body = [
			'contents'         => [
				[
					'parts' => [
						[
							'text' => $combined_prompt,
						],
					],
				],
			],
			'generationConfig' => [
				'temperature' => $temperature,
			],
		];

		// Add max output tokens if specified.
		$max_tokens = $options['max_tokens'] ?? $this->get_setting('max_tokens', null);
		if ($max_tokens !== null) {
			$body['generationConfig']['maxOutputTokens'] = (int) $max_tokens;
		}

		$url = sprintf(
			'%s/v1beta/models/%s:generateContent?key=%s',
			rtrim($base_url, '/'),
			rawurlencode($model),
			rawurlencode($api_key)
		);

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
					__('Gemini API request failed: %s', 'seo-ai'),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body_raw    = wp_remote_retrieve_body($response);
		$data        = json_decode($body_raw, true);

		if ($status_code < 200 || $status_code >= 300) {
			$error_message = $data['error']['message']
				?? $data['error']['status']
				?? sprintf('HTTP %d', $status_code);

			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message from the API */
					__('Gemini API error: %s', 'seo-ai'),
					$error_message
				)
			);
		}

		$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

		if ($text === null) {
			// Check for safety filtering or blocked content.
			$finish_reason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
			if ($finish_reason === 'SAFETY') {
				throw new \RuntimeException(
					__('Gemini API: Response was blocked by safety filters.', 'seo-ai')
				);
			}

			throw new \RuntimeException(
				__('Gemini API returned an unexpected response format.', 'seo-ai')
			);
		}

		return trim($text);
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
