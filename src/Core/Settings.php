<?php
/**
 * Settings Manager.
 *
 * @package WPMoat\Core
 */

declare(strict_types=1);

namespace WPMoat\Core;

/**
 * Wrapper around WordPress Options API for module settings.
 *
 * Each module gets its own option key (e.g., 'wp_moat_firewall')
 * to keep settings organized and avoid conflicts.
 */
class Settings {

	/**
	 * Option prefix for all WP Moat settings.
	 */
	private const OPTION_PREFIX = 'wp_moat_';

	/**
	 * Cache of loaded settings.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $cache = [];

	/**
	 * Get the full option name for a module.
	 *
	 * @param string $module_id The module identifier.
	 *
	 * @return string The full option name.
	 */
	private function getOptionName( string $module_id ): string {
		return self::OPTION_PREFIX . str_replace( '-', '_', $module_id );
	}

	/**
	 * Get all settings for a module.
	 *
	 * @param string              $module_id The module identifier.
	 * @param array<string,mixed> $defaults  Default values to merge with stored settings.
	 *
	 * @return array<string, mixed> The module settings.
	 */
	public function getAll( string $module_id, array $defaults = [] ): array {
		if ( ! isset( $this->cache[ $module_id ] ) ) {
			$option_name = $this->getOptionName( $module_id );
			$stored      = get_option( $option_name, [] );

			$this->cache[ $module_id ] = is_array( $stored ) ? $stored : [];
		}

		return array_merge( $defaults, $this->cache[ $module_id ] );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $module_id The module identifier.
	 * @param string $key       The setting key.
	 * @param mixed  $default   Default value if setting doesn't exist.
	 *
	 * @return mixed The setting value.
	 */
	public function get( string $module_id, string $key, mixed $default = null ): mixed {
		$settings = $this->getAll( $module_id );

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Set a single setting value.
	 *
	 * @param string $module_id The module identifier.
	 * @param string $key       The setting key.
	 * @param mixed  $value     The value to store.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function set( string $module_id, string $key, mixed $value ): bool {
		$settings         = $this->getAll( $module_id );
		$settings[ $key ] = $value;

		return $this->saveAll( $module_id, $settings );
	}

	/**
	 * Save all settings for a module.
	 *
	 * @param string              $module_id The module identifier.
	 * @param array<string,mixed> $settings  The settings to save.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function saveAll( string $module_id, array $settings ): bool {
		$option_name = $this->getOptionName( $module_id );
		$result      = update_option( $option_name, $settings );

		if ( $result ) {
			$this->cache[ $module_id ] = $settings;
		}

		return $result;
	}

	/**
	 * Delete a single setting.
	 *
	 * @param string $module_id The module identifier.
	 * @param string $key       The setting key to delete.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $module_id, string $key ): bool {
		$settings = $this->getAll( $module_id );

		if ( ! array_key_exists( $key, $settings ) ) {
			return true;
		}

		unset( $settings[ $key ] );

		return $this->saveAll( $module_id, $settings );
	}

	/**
	 * Delete all settings for a module.
	 *
	 * @param string $module_id The module identifier.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function deleteAll( string $module_id ): bool {
		$option_name = $this->getOptionName( $module_id );
		$result      = delete_option( $option_name );

		if ( $result ) {
			unset( $this->cache[ $module_id ] );
		}

		return $result;
	}

	/**
	 * Check if a module is enabled.
	 *
	 * Convenience method for the common 'enabled' setting.
	 *
	 * @param string $module_id The module identifier.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function isModuleEnabled( string $module_id ): bool {
		return (bool) $this->get( $module_id, 'enabled', false );
	}

	/**
	 * Enable or disable a module.
	 *
	 * @param string $module_id The module identifier.
	 * @param bool   $enabled   Whether the module should be enabled.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function setModuleEnabled( string $module_id, bool $enabled ): bool {
		return $this->set( $module_id, 'enabled', $enabled );
	}

	/**
	 * Clear the settings cache.
	 *
	 * Useful when settings have been modified externally.
	 *
	 * @param string|null $module_id Optional module ID to clear. Clears all if null.
	 */
	public function clearCache( ?string $module_id = null ): void {
		if ( null === $module_id ) {
			$this->cache = [];
		} else {
			unset( $this->cache[ $module_id ] );
		}
	}
}
