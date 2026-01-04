<?php
/**
 * Plugin Name: WP Moat
 * Plugin URI: https://github.com/your-repo/wp-moat
 * Description: A modern, modular WordPress security plugin with path hiding, firewall, brute force protection, and more.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Your Name
 * Author URI: https://your-site.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-moat
 * Domain Path: /languages
 *
 * @package WPMoat
 */

declare(strict_types=1);

namespace WPMoat;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WP_MOAT_VERSION', '1.0.0' );
define( 'WP_MOAT_FILE', __FILE__ );
define( 'WP_MOAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_MOAT_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_MOAT_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements check.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action( 'admin_notices', function (): void {
		echo '<div class="error"><p>';
		echo esc_html__( 'WP Moat requires PHP 8.0 or higher. Please upgrade your PHP version.', 'wp-moat' );
		echo '</p></div>';
	} );
	return;
}

/**
 * PSR-4 Autoloader for WPMoat namespace.
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register( function ( string $class ): void {
	$prefix   = 'WPMoat\\';
	$base_dir = WP_MOAT_PATH . 'src/';

	// Check if the class uses the WPMoat namespace.
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	// Get the relative class name.
	$relative_class = substr( $class, $len );

	// Convert namespace separators to directory separators.
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	// Load the file if it exists.
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Initialize the plugin.
 *
 * @return Core\Plugin The plugin instance.
 */
function wp_moat(): Core\Plugin {
	static $plugin = null;

	if ( null === $plugin ) {
		$container = new Core\Container();
		$plugin    = new Core\Plugin( $container );
	}

	return $plugin;
}

// Boot the plugin after WordPress is fully loaded.
add_action( 'plugins_loaded', function (): void {
	wp_moat()->boot();
}, 10 );

// Register activation hook.
register_activation_hook( __FILE__, function (): void {
	// Set default options on activation.
	if ( false === get_option( 'wp_moat_core' ) ) {
		update_option( 'wp_moat_core', [
			'version' => WP_MOAT_VERSION,
		] );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
} );

// Register deactivation hook.
register_deactivation_hook( __FILE__, function (): void {
	// Flush rewrite rules to remove any custom rules.
	flush_rewrite_rules();
} );
