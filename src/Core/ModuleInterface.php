<?php
/**
 * Module Interface.
 *
 * @package WPMoat\Core
 */

declare(strict_types=1);

namespace WPMoat\Core;

/**
 * Interface that all WP Moat modules must implement.
 *
 * Modules are the building blocks of WP Moat functionality.
 * Each module encapsulates a specific security feature and can be
 * enabled or disabled independently.
 */
interface ModuleInterface {

	/**
	 * Get the unique identifier for this module.
	 *
	 * Used for settings storage, hooks, and internal references.
	 * Should be lowercase with hyphens (e.g., 'path-hiding', 'firewall').
	 *
	 * @return string The module identifier.
	 */
	public function getId(): string;

	/**
	 * Get the human-readable name for this module.
	 *
	 * Displayed in the admin UI.
	 *
	 * @return string The module name.
	 */
	public function getName(): string;

	/**
	 * Get a description of what this module does.
	 *
	 * Displayed in the admin UI to help users understand the module's purpose.
	 *
	 * @return string The module description.
	 */
	public function getDescription(): string;

	/**
	 * Check if this module is currently enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function isEnabled(): bool;

	/**
	 * Boot the module and register its hooks.
	 *
	 * Called when the module is loaded. Should register all WordPress
	 * hooks, filters, and any initialization logic.
	 */
	public function boot(): void;

	/**
	 * Get the settings page slug for this module.
	 *
	 * Return null if the module has no settings page.
	 *
	 * @return string|null The settings page slug, or null.
	 */
	public function getSettingsPage(): ?string;
}
