<?php
/**
 * Version Hider Service.
 *
 * @package WPMoat\Modules\PathHiding
 */

declare(strict_types=1);

namespace WPMoat\Modules\PathHiding;

/**
 * Removes WordPress version information from various locations.
 *
 * - Removes version query strings from scripts/styles
 * - Removes generator meta tag
 * - Removes version from RSS feeds
 * - Removes readme.html and license.txt references
 */
class VersionHider {

	/**
	 * Register hooks for version hiding.
	 */
	public function register(): void {
		// Remove version from scripts.
		add_filter( 'script_loader_src', [ $this, 'removeVersionFromUrl' ], 100 );

		// Remove version from styles.
		add_filter( 'style_loader_src', [ $this, 'removeVersionFromUrl' ], 100 );

		// Remove version from RSS feeds.
		add_filter( 'the_generator', '__return_empty_string' );

		// Remove WordPress version from various sources.
		remove_action( 'wp_head', 'wp_generator' );

		// Remove version from atom and rdf feeds.
		add_filter( 'get_the_generator_atom', '__return_empty_string' );
		add_filter( 'get_the_generator_rss2', '__return_empty_string' );
		add_filter( 'get_the_generator_rdf', '__return_empty_string' );
		add_filter( 'get_the_generator_comment', '__return_empty_string' );
		add_filter( 'get_the_generator_xhtml', '__return_empty_string' );
		add_filter( 'get_the_generator_html', '__return_empty_string' );
	}

	/**
	 * Remove generator meta tag specifically.
	 */
	public function removeGenerator(): void {
		remove_action( 'wp_head', 'wp_generator' );

		// Also remove from login page.
		add_filter( 'login_head', function (): void {
			remove_action( 'login_head', 'wp_generator' );
		}, 1 );

		// Remove from admin.
		add_action( 'admin_head', function (): void {
			remove_action( 'admin_head', 'wp_generator' );
		}, 1 );
	}

	/**
	 * Remove version query string from asset URLs.
	 *
	 * @param string $src The asset URL.
	 *
	 * @return string URL without version.
	 */
	public function removeVersionFromUrl( string $src ): string {
		if ( empty( $src ) ) {
			return $src;
		}

		// Parse the URL.
		$parsed = wp_parse_url( $src );

		if ( empty( $parsed['query'] ) ) {
			return $src;
		}

		// Parse query string.
		parse_str( $parsed['query'], $query );

		// Remove version-related parameters.
		$version_params = [ 'ver', 'v', 'version' ];
		$modified = false;

		foreach ( $version_params as $param ) {
			if ( isset( $query[ $param ] ) ) {
				unset( $query[ $param ] );
				$modified = true;
			}
		}

		if ( ! $modified ) {
			return $src;
		}

		// Rebuild URL.
		$base = $parsed['scheme'] . '://' . $parsed['host'];

		if ( ! empty( $parsed['port'] ) ) {
			$base .= ':' . $parsed['port'];
		}

		$base .= $parsed['path'];

		if ( ! empty( $query ) ) {
			$base .= '?' . http_build_query( $query );
		}

		return $base;
	}

	/**
	 * Hide WordPress version in HTML comments.
	 *
	 * @param string $content The HTML content.
	 *
	 * @return string Content with version comments removed.
	 */
	public function hideVersionInComments( string $content ): string {
		// Remove generator comments.
		$patterns = [
			'/<!-- generator="WordPress[^"]*" -->/i',
			'/<!-- WordPress \d+\.\d+[^>]* -->/i',
			'/<!-- This site is optimized with the [^>]+ -->/i',
		];

		foreach ( $patterns as $pattern ) {
			$content = preg_replace( $pattern, '', $content ) ?? $content;
		}

		return $content;
	}

	/**
	 * Get the current WordPress version (for internal use).
	 *
	 * @return string WordPress version.
	 */
	public function getWordPressVersion(): string {
		global $wp_version;
		return $wp_version ?? '';
	}

	/**
	 * Check if a file reveals WordPress version.
	 *
	 * @param string $file The file path.
	 *
	 * @return bool True if file reveals version.
	 */
	public function isVersionRevealingFile( string $file ): bool {
		$revealing_files = [
			'readme.html',
			'license.txt',
			'wp-includes/version.php',
		];

		$file = ltrim( $file, '/' );

		return in_array( $file, $revealing_files, true );
	}
}
