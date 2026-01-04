<?php
/**
 * URL Rewriter Service.
 *
 * @package WPMoat\Modules\PathHiding
 */

declare(strict_types=1);

namespace WPMoat\Modules\PathHiding;

/**
 * Rewrites WordPress URLs in HTML output to hide default paths.
 *
 * Uses output buffering to replace wp-content, wp-includes, and
 * plugin/theme paths with custom alternatives.
 */
class URLRewriter {

	/**
	 * Custom content directory path.
	 *
	 * @var string
	 */
	private string $content_path = '';

	/**
	 * Custom plugins directory path.
	 *
	 * @var string
	 */
	private string $plugins_path = '';

	/**
	 * Custom themes directory path.
	 *
	 * @var string
	 */
	private string $themes_path = '';

	/**
	 * Whether output buffering is active.
	 *
	 * @var bool
	 */
	private bool $buffering = false;

	/**
	 * Set custom content path.
	 *
	 * @param string $path Custom path (e.g., 'assets').
	 */
	public function setContentPath( string $path ): void {
		$this->content_path = $this->sanitizePath( $path );
	}

	/**
	 * Set custom plugins path.
	 *
	 * @param string $path Custom path (e.g., 'modules').
	 */
	public function setPluginsPath( string $path ): void {
		$this->plugins_path = $this->sanitizePath( $path );
	}

	/**
	 * Set custom themes path.
	 *
	 * @param string $path Custom path (e.g., 'templates').
	 */
	public function setThemesPath( string $path ): void {
		$this->themes_path = $this->sanitizePath( $path );
	}

	/**
	 * Sanitize a path segment.
	 *
	 * @param string $path The path to sanitize.
	 *
	 * @return string Sanitized path.
	 */
	private function sanitizePath( string $path ): string {
		// Remove leading/trailing slashes and sanitize.
		$path = trim( $path, '/' );
		$path = sanitize_file_name( $path );
		return $path;
	}

	/**
	 * Register hooks for URL rewriting.
	 */
	public function register(): void {
		// Only rewrite on frontend.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// Start output buffering early.
		add_action( 'template_redirect', [ $this, 'startBuffer' ], 1 );

		// Add rewrite rules.
		add_action( 'init', [ $this, 'addRewriteRules' ] );

		// Filter URLs in various contexts.
		add_filter( 'wp_moat_rewrite_url', [ $this, 'rewriteUrl' ], 10, 2 );
		add_filter( 'script_loader_src', [ $this, 'filterAssetUrl' ], 100 );
		add_filter( 'style_loader_src', [ $this, 'filterAssetUrl' ], 100 );
	}

	/**
	 * Start output buffering.
	 */
	public function startBuffer(): void {
		if ( $this->buffering ) {
			return;
		}

		$this->buffering = true;
		ob_start( [ $this, 'processOutput' ] );
	}

	/**
	 * Process the output buffer.
	 *
	 * @param string $content The buffered content.
	 *
	 * @return string Modified content.
	 */
	public function processOutput( string $content ): string {
		if ( empty( $content ) ) {
			return $content;
		}

		// Skip if not HTML.
		if ( ! $this->isHtml( $content ) ) {
			return $content;
		}

		$content = $this->rewriteContent( $content );

		/**
		 * Filter the final output after path rewriting.
		 *
		 * @param string $content The processed content.
		 */
		return apply_filters( 'wp_moat_filter_output', $content );
	}

	/**
	 * Check if content appears to be HTML.
	 *
	 * @param string $content The content to check.
	 *
	 * @return bool True if HTML.
	 */
	private function isHtml( string $content ): bool {
		return (
			stripos( $content, '<!DOCTYPE html' ) !== false ||
			stripos( $content, '<html' ) !== false ||
			stripos( $content, '<head' ) !== false
		);
	}

	/**
	 * Rewrite WordPress paths in content.
	 *
	 * @param string $content The content to process.
	 *
	 * @return string Modified content.
	 */
	public function rewriteContent( string $content ): string {
		$site_url = site_url();
		$replacements = $this->getReplacements( $site_url );

		if ( empty( $replacements ) ) {
			return $content;
		}

		// Perform replacements.
		foreach ( $replacements as $search => $replace ) {
			$content = str_replace( $search, $replace, $content );
		}

		return $content;
	}

	/**
	 * Get the search/replace pairs for URL rewriting.
	 *
	 * @param string $site_url The site URL.
	 *
	 * @return array<string, string> Search => replace pairs.
	 */
	private function getReplacements( string $site_url ): array {
		$replacements = [];

		// Content path replacement.
		if ( ! empty( $this->content_path ) ) {
			$replacements[ $site_url . '/wp-content/' ] = $site_url . '/' . $this->content_path . '/';
			$replacements[ '/wp-content/' ] = '/' . $this->content_path . '/';
		}

		// Plugins path replacement (more specific, do first).
		if ( ! empty( $this->plugins_path ) ) {
			$base = ! empty( $this->content_path ) ? $this->content_path : 'wp-content';
			$replacements[ $site_url . '/' . $base . '/plugins/' ] = $site_url . '/' . $this->plugins_path . '/';
			$replacements[ '/' . $base . '/plugins/' ] = '/' . $this->plugins_path . '/';
		}

		// Themes path replacement.
		if ( ! empty( $this->themes_path ) ) {
			$base = ! empty( $this->content_path ) ? $this->content_path : 'wp-content';
			$replacements[ $site_url . '/' . $base . '/themes/' ] = $site_url . '/' . $this->themes_path . '/';
			$replacements[ '/' . $base . '/themes/' ] = '/' . $this->themes_path . '/';
		}

		/**
		 * Filter the URL replacements.
		 *
		 * @param array<string, string> $replacements Search => replace pairs.
		 * @param string                $site_url     The site URL.
		 */
		return apply_filters( 'wp_moat_url_replacements', $replacements, $site_url );
	}

	/**
	 * Add rewrite rules for custom paths.
	 */
	public function addRewriteRules(): void {
		// Content path rewrite.
		if ( ! empty( $this->content_path ) ) {
			add_rewrite_rule(
				'^' . preg_quote( $this->content_path, '/' ) . '/(.*)$',
				'wp-content/$1',
				'top'
			);
		}

		// Plugins path rewrite.
		if ( ! empty( $this->plugins_path ) ) {
			add_rewrite_rule(
				'^' . preg_quote( $this->plugins_path, '/' ) . '/(.*)$',
				'wp-content/plugins/$1',
				'top'
			);
		}

		// Themes path rewrite.
		if ( ! empty( $this->themes_path ) ) {
			add_rewrite_rule(
				'^' . preg_quote( $this->themes_path, '/' ) . '/(.*)$',
				'wp-content/themes/$1',
				'top'
			);
		}
	}

	/**
	 * Rewrite a single URL.
	 *
	 * @param string $url          The URL to rewrite.
	 * @param string $original_url The original URL (unused, for filter).
	 *
	 * @return string The rewritten URL.
	 */
	public function rewriteUrl( string $url, string $original_url = '' ): string {
		$site_url = site_url();
		$replacements = $this->getReplacements( $site_url );

		foreach ( $replacements as $search => $replace ) {
			$url = str_replace( $search, $replace, $url );
		}

		return $url;
	}

	/**
	 * Filter asset URLs (scripts and styles).
	 *
	 * @param string $url The asset URL.
	 *
	 * @return string The filtered URL.
	 */
	public function filterAssetUrl( string $url ): string {
		return $this->rewriteUrl( $url );
	}

	/**
	 * Check if custom paths are configured.
	 *
	 * @return bool True if any custom paths are set.
	 */
	public function hasCustomPaths(): bool {
		return ! empty( $this->content_path ) ||
			   ! empty( $this->plugins_path ) ||
			   ! empty( $this->themes_path );
	}
}
