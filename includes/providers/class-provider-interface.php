<?php
/**
 * AI Provider Interface.
 *
 * Defines the contract that all AI provider implementations must follow.
 *
 * @package SeoAi\Providers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Providers;

defined('ABSPATH') || exit;

/**
 * Interface Provider_Interface
 *
 * Every AI provider (OpenAI, Claude, Gemini, Ollama, OpenRouter, etc.) must
 * implement this interface so the plugin can interact with it in a uniform way.
 *
 * @since 1.0.0
 */
interface Provider_Interface {

	/**
	 * Get the unique identifier for this provider.
	 *
	 * Must be a lowercase alphanumeric slug (e.g. 'openai', 'claude').
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider identifier.
	 */
	public function get_id(): string;

	/**
	 * Get the human-readable display name.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider name shown in the UI.
	 */
	public function get_name(): string;

	/**
	 * Get the list of available models for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Associative array of model-id => 'Model Name (description)'.
	 */
	public function get_models(): array;

	/**
	 * Get the default model identifier for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string Default model ID.
	 */
	public function get_default_model(): string;

	/**
	 * Get the default base URL for this provider's API.
	 *
	 * @since 1.0.0
	 *
	 * @return string Default API base URL (no trailing slash).
	 */
	public function get_default_base_url(): string;

	/**
	 * Check whether this provider has all required settings configured.
	 *
	 * Typically verifies that an API key (and optionally a base URL) is present.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the provider is ready to use.
	 */
	public function is_configured(): bool;

	/**
	 * Test the connection to the provider's API.
	 *
	 * Sends a lightweight request to verify credentials and reachability.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string} Result of the connection test.
	 */
	public function test_connection(): array;

	/**
	 * Send a chat completion request to the provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $system_prompt The system-level instruction.
	 * @param string $user_prompt   The user message / query.
	 * @param array  $options       Optional parameters. Recognised keys:
	 *                              - 'model'       (string) Override the configured model.
	 *                              - 'temperature' (float)  Sampling temperature (0.0 - 2.0).
	 *                              - 'max_tokens'  (int)    Maximum tokens in the response.
	 *
	 * @return string The assistant's response text, trimmed.
	 *
	 * @throws \RuntimeException When the API request fails.
	 */
	public function chat(string $system_prompt, string $user_prompt, array $options = []): string;
}
