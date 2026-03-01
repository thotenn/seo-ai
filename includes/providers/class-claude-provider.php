<?php
/**
 * Claude Provider.
 *
 * Integration with Anthropic's Messages API (Claude models).
 *
 * @package SeoAi\Providers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Providers;

defined('ABSPATH') || exit;

/**
 * Class Claude_Provider
 *
 * Implements the Provider_Interface for the Anthropic (Claude) API.
 *
 * @since 1.0.0
 */
final class Claude_Provider implements Provider_Interface {

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	private const ID = 'claude';

	/**
	 * Human-readable provider name.
	 *
	 * @var string
	 */
	private const NAME = 'Anthropic (Claude)';

	/**
	 * Default API base URL.
	 *
	 * @var string
	 */
	private const DEFAULT_BASE_URL = 'https://api.anthropic.com';

	/**
	 * Default model.
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'claude-sonnet-4-5-20250929';

	/**
	 * Required API version header value.
	 *
	 * @var string
	 */
	private const API_VERSION = '2023-06-01';

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
			'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5 (balanced, recommended)',
			'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5 (fast, affordable)',
			'claude-opus-4-6'            => 'Claude Opus 4.6 (most capable)',
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
				'message' => __('Anthropic API key is not configured.', 'seo-ai'),
			];
		}

		try {
			$response = $this->chat(
				'You are a helpful assistant.',
				'Say hello in one short sentence.',
				[
					'max_tokens'  => 30,
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
				__('Anthropic API key is not configured.', 'seo-ai')
			);
		}

		$base_url    = $this->get_setting('base_url', self::DEFAULT_BASE_URL);
		$model       = $options['model'] ?? $this->get_setting('model', self::DEFAULT_MODEL);
		$temperature = (float) ($options['temperature'] ?? $this->get_setting('temperature', 0.7));
		$max_tokens  = (int) ($options['max_tokens'] ?? $this->get_setting('max_tokens', 4096));

		$body = [
			'model'       => $model,
			'max_tokens'  => $max_tokens,
			'temperature' => $temperature,
			'system'      => $system_prompt,
			'messages'    => [
				[
					'role'    => 'user',
					'content' => $user_prompt,
				],
			],
		];

		$url = rtrim($base_url, '/') . '/v1/messages';

		$response = wp_remote_post($url, [
			'timeout' => self::TIMEOUT,
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => self::API_VERSION,
			],
			'body'    => wp_json_encode($body),
		]);

		if (is_wp_error($response)) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message from WordPress HTTP API */
					__('Anthropic API request failed: %s', 'seo-ai'),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body_raw    = wp_remote_retrieve_body($response);
		$data        = json_decode($body_raw, true);

		if ($status_code < 200 || $status_code >= 300) {
			$error_message = $data['error']['message']
				?? $data['error']['type']
				?? sprintf('HTTP %d', $status_code);

			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message from the API */
					__('Anthropic API error: %s', 'seo-ai'),
					$error_message
				)
			);
		}

		if (! isset($data['content'][0]['text'])) {
			throw new \RuntimeException(
				__('Anthropic API returned an unexpected response format.', 'seo-ai')
			);
		}

		return trim($data['content'][0]['text']);
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
		$provider_settings = $all_settings[self::ID] ?? [];

		return $provider_settings[$key] ?? $default;
	}
}
