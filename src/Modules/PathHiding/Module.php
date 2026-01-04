<?php
/**
 * Path Hiding Module.
 *
 * @package WPMoat\Modules\PathHiding
 */

declare(strict_types=1);

namespace WPMoat\Modules\PathHiding;

use WPMoat\Core\ModuleInterface;
use WPMoat\Core\Settings;

/**
 * Core module for hiding WordPress fingerprints.
 *
 * Features:
 * - Custom login URL (hide wp-login.php)
 * - Custom admin URL (optional)
 * - Hide wp-content/wp-includes paths in HTML output
 * - Remove WordPress version strings
 * - Clean revealing HTTP headers
 */
class Module implements ModuleInterface {

	/**
	 * Module identifier.
	 */
	private const ID = 'path-hiding';

	/**
	 * Default settings.
	 */
	private const DEFAULTS = [
		'enabled'              => true,
		'custom_login_slug'    => '',
		'hide_wp_paths'        => true,
		'custom_content_path'  => '',
		'custom_plugins_path'  => '',
		'custom_themes_path'   => '',
		'remove_version'       => true,
		'remove_meta_generator'=> true,
		'remove_rsd_link'      => true,
		'remove_wlw_manifest'  => true,
		'remove_shortlink'     => true,
		'clean_headers'        => true,
		'disable_xmlrpc'       => true,
		'disable_rest_users'   => true,
	];

	/**
	 * Settings manager.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * URL Rewriter service.
	 *
	 * @var URLRewriter
	 */
	private URLRewriter $url_rewriter;

	/**
	 * Login Protection service.
	 *
	 * @var LoginProtection
	 */
	private LoginProtection $login_protection;

	/**
	 * Version Hider service.
	 *
	 * @var VersionHider
	 */
	private VersionHider $version_hider;

	/**
	 * Header Cleaner service.
	 *
	 * @var HeaderCleaner
	 */
	private HeaderCleaner $header_cleaner;

	/**
	 * Constructor.
	 *
	 * @param Settings        $settings         Settings manager.
	 * @param URLRewriter     $url_rewriter     URL Rewriter service.
	 * @param LoginProtection $login_protection Login Protection service.
	 * @param VersionHider    $version_hider    Version Hider service.
	 * @param HeaderCleaner   $header_cleaner   Header Cleaner service.
	 */
	public function __construct(
		Settings $settings,
		URLRewriter $url_rewriter,
		LoginProtection $login_protection,
		VersionHider $version_hider,
		HeaderCleaner $header_cleaner
	) {
		$this->settings         = $settings;
		$this->url_rewriter     = $url_rewriter;
		$this->login_protection = $login_protection;
		$this->version_hider    = $version_hider;
		$this->header_cleaner   = $header_cleaner;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getId(): string {
		return self::ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return __( 'Path Hiding', 'wp-moat' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string {
		return __( 'Hide WordPress fingerprints by obfuscating paths, login URLs, and version information.', 'wp-moat' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isEnabled(): bool {
		return (bool) $this->getSetting( 'enabled', true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		// Initialize services with settings.
		$this->initializeServices();

		// Register hooks.
		$this->registerHooks();

		/**
		 * Fires after Path Hiding module has booted.
		 *
		 * @param Module $module The module instance.
		 */
		do_action( 'wp_moat_path_hiding_loaded', $this );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSettingsPage(): ?string {
		return 'wp-moat-path-hiding';
	}

	/**
	 * Initialize services with current settings.
	 */
	private function initializeServices(): void {
		// Configure URL Rewriter.
		if ( $this->getSetting( 'hide_wp_paths' ) ) {
			$this->url_rewriter->setContentPath( $this->getSetting( 'custom_content_path', '' ) );
			$this->url_rewriter->setPluginsPath( $this->getSetting( 'custom_plugins_path', '' ) );
			$this->url_rewriter->setThemesPath( $this->getSetting( 'custom_themes_path', '' ) );
		}

		// Configure Login Protection.
		$custom_login = $this->getSetting( 'custom_login_slug', '' );
		if ( ! empty( $custom_login ) ) {
			$this->login_protection->setCustomSlug( $custom_login );
		}
	}

	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// URL rewriting (output buffer).
		if ( $this->getSetting( 'hide_wp_paths' ) ) {
			$this->url_rewriter->register();
		}

		// Custom login URL.
		$custom_login = $this->getSetting( 'custom_login_slug', '' );
		if ( ! empty( $custom_login ) ) {
			$this->login_protection->register();
		}

		// Version hiding.
		if ( $this->getSetting( 'remove_version' ) ) {
			$this->version_hider->register();
		}

		// Meta tag removal.
		if ( $this->getSetting( 'remove_meta_generator' ) ) {
			$this->version_hider->removeGenerator();
		}

		if ( $this->getSetting( 'remove_rsd_link' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( $this->getSetting( 'remove_wlw_manifest' ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		if ( $this->getSetting( 'remove_shortlink' ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}

		// Header cleaning.
		if ( $this->getSetting( 'clean_headers' ) ) {
			$this->header_cleaner->register();
		}

		// Disable XML-RPC.
		if ( $this->getSetting( 'disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array' );
		}

		// Disable REST API user enumeration.
		if ( $this->getSetting( 'disable_rest_users' ) ) {
			add_filter( 'rest_endpoints', [ $this, 'disableUserEndpoints' ] );
		}

		// Admin settings page.
		if ( is_admin() ) {
			add_action( 'wp_moat_admin_menu', [ $this, 'registerAdminPage' ] );
		}
	}

	/**
	 * Disable REST API user endpoints to prevent enumeration.
	 *
	 * @param array<string, mixed> $endpoints REST API endpoints.
	 *
	 * @return array<string, mixed> Filtered endpoints.
	 */
	public function disableUserEndpoints( array $endpoints ): array {
		// Only restrict for non-logged-in users.
		if ( is_user_logged_in() ) {
			return $endpoints;
		}

		// Remove user-related endpoints.
		$user_endpoints = [
			'/wp/v2/users',
			'/wp/v2/users/(?P<id>[\d]+)',
			'/wp/v2/users/me',
		];

		foreach ( $user_endpoints as $endpoint ) {
			if ( isset( $endpoints[ $endpoint ] ) ) {
				unset( $endpoints[ $endpoint ] );
			}
		}

		return $endpoints;
	}

	/**
	 * Register the admin settings page.
	 */
	public function registerAdminPage(): void {
		$page = new SettingsPage( $this->settings );
		$page->register();
	}

	/**
	 * Get a module setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed Setting value.
	 */
	public function getSetting( string $key, mixed $default = null ): mixed {
		$default = $default ?? ( self::DEFAULTS[ $key ] ?? null );
		return $this->settings->get( self::ID, $key, $default );
	}

	/**
	 * Get all settings with defaults.
	 *
	 * @return array<string, mixed> All settings.
	 */
	public function getSettings(): array {
		return $this->settings->getAll( self::ID, self::DEFAULTS );
	}

	/**
	 * Update a setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 *
	 * @return bool Success status.
	 */
	public function updateSetting( string $key, mixed $value ): bool {
		return $this->settings->set( self::ID, $key, $value );
	}
}
