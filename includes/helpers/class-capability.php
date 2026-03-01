<?php
namespace SeoAi\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Capability helper for managing SEO AI custom capabilities.
 *
 * Registers plugin-specific capabilities and provides convenience
 * methods for checking permissions against the current user.
 *
 * @since 1.0.0
 */
class Capability {

	/**
	 * Capability for managing plugin settings.
	 *
	 * @var string
	 */
	public const MANAGE_SETTINGS = 'seo_ai_manage_settings';

	/**
	 * Capability for managing redirects and 404 monitoring.
	 *
	 * @var string
	 */
	public const MANAGE_REDIRECTS = 'seo_ai_manage_redirects';

	/**
	 * Capability for viewing SEO reports and analytics.
	 *
	 * @var string
	 */
	public const VIEW_REPORTS = 'seo_ai_view_reports';

	/**
	 * All custom capabilities defined by the plugin.
	 *
	 * @var string[]
	 */
	private const ALL_CAPABILITIES = [
		self::MANAGE_SETTINGS,
		self::MANAGE_REDIRECTS,
		self::VIEW_REPORTS,
	];

	/**
	 * Grant all custom capabilities to the administrator role.
	 *
	 * Called during plugin activation.
	 *
	 * @return void
	 */
	public static function grant_defaults(): void {
		$admin_role = get_role( 'administrator' );

		if ( null === $admin_role ) {
			return;
		}

		foreach ( self::ALL_CAPABILITIES as $cap ) {
			if ( ! $admin_role->has_cap( $cap ) ) {
				$admin_role->add_cap( $cap, true );
			}
		}
	}

	/**
	 * Remove all custom capabilities from all roles.
	 *
	 * Called during plugin uninstallation.
	 *
	 * @return void
	 */
	public static function remove_all(): void {
		global $wp_roles;

		if ( ! $wp_roles instanceof \WP_Roles ) {
			$wp_roles = wp_roles();
		}

		foreach ( $wp_roles->role_objects as $role ) {
			foreach ( self::ALL_CAPABILITIES as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Check if the current user can manage plugin settings.
	 *
	 * @return bool
	 */
	public function can_manage_settings(): bool {
		return current_user_can( self::MANAGE_SETTINGS ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check if the current user can manage redirects.
	 *
	 * @return bool
	 */
	public function can_manage_redirects(): bool {
		return current_user_can( self::MANAGE_REDIRECTS ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check if the current user can view SEO reports.
	 *
	 * @return bool
	 */
	public function can_view_reports(): bool {
		return current_user_can( self::VIEW_REPORTS ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check if a specific user has a given custom capability.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $cap     The capability to check.
	 * @return bool
	 */
	public static function user_can( int $user_id, string $cap ): bool {
		return user_can( $user_id, $cap );
	}

	/**
	 * Grant a custom capability to a specific role.
	 *
	 * @param string $role_slug The role slug (e.g., 'editor').
	 * @param string $cap       The capability to grant.
	 * @return bool True if the role was found and capability added, false otherwise.
	 */
	public static function grant_to_role( string $role_slug, string $cap ): bool {
		if ( ! in_array( $cap, self::ALL_CAPABILITIES, true ) ) {
			return false;
		}

		$role = get_role( $role_slug );

		if ( null === $role ) {
			return false;
		}

		$role->add_cap( $cap, true );
		return true;
	}

	/**
	 * Revoke a custom capability from a specific role.
	 *
	 * @param string $role_slug The role slug (e.g., 'editor').
	 * @param string $cap       The capability to revoke.
	 * @return bool True if the role was found and capability removed, false otherwise.
	 */
	public static function revoke_from_role( string $role_slug, string $cap ): bool {
		if ( ! in_array( $cap, self::ALL_CAPABILITIES, true ) ) {
			return false;
		}

		$role = get_role( $role_slug );

		if ( null === $role ) {
			return false;
		}

		$role->remove_cap( $cap );
		return true;
	}

	/**
	 * Get all custom capabilities registered by the plugin.
	 *
	 * @return string[]
	 */
	public static function get_all_capabilities(): array {
		return self::ALL_CAPABILITIES;
	}
}
