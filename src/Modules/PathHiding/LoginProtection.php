<?php
/**
 * Login Protection Service.
 *
 * @package WPMoat\Modules\PathHiding
 */

declare(strict_types=1);

namespace WPMoat\Modules\PathHiding;

/**
 * Protects the WordPress login page by hiding the default URL.
 *
 * - Replaces wp-login.php with a custom slug
 * - Blocks direct access to wp-login.php
 * - Redirects wp-admin for non-logged users to custom login
 */
class LoginProtection {

	/**
	 * Custom login slug.
	 *
	 * @var string
	 */
	private string $custom_slug = '';

	/**
	 * Set the custom login slug.
	 *
	 * @param string $slug The custom slug (e.g., 'secure-login').
	 */
	public function setCustomSlug( string $slug ): void {
		$this->custom_slug = $this->sanitizeSlug( $slug );
	}

	/**
	 * Sanitize a slug.
	 *
	 * @param string $slug The slug to sanitize.
	 *
	 * @return string Sanitized slug.
	 */
	private function sanitizeSlug( string $slug ): string {
		$slug = sanitize_title( $slug );
		$slug = trim( $slug, '/' );
		return $slug;
	}

	/**
	 * Register hooks for login protection.
	 */
	public function register(): void {
		if ( empty( $this->custom_slug ) ) {
			return;
		}

		// Block direct wp-login.php access.
		add_action( 'login_init', [ $this, 'checkLoginAccess' ], 1 );

		// Redirect wp-admin for non-logged users.
		add_action( 'admin_init', [ $this, 'checkAdminAccess' ], 1 );

		// Add rewrite rule for custom login.
		add_action( 'init', [ $this, 'addRewriteRules' ] );

		// Filter login URLs.
		add_filter( 'login_url', [ $this, 'filterLoginUrl' ], 100, 3 );
		add_filter( 'logout_url', [ $this, 'filterLogoutUrl' ], 100, 2 );
		add_filter( 'lostpassword_url', [ $this, 'filterLostPasswordUrl' ], 100, 2 );
		add_filter( 'register_url', [ $this, 'filterRegisterUrl' ], 100 );

		// Handle custom login page.
		add_action( 'template_redirect', [ $this, 'handleCustomLogin' ] );

		// Ensure site URL doesn't expose login.
		add_filter( 'site_url', [ $this, 'filterSiteUrl' ], 100, 4 );

		// Filter redirects to login.
		add_filter( 'wp_redirect', [ $this, 'filterRedirect' ], 100 );
	}

	/**
	 * Check if current request is accessing wp-login.php directly.
	 */
	public function checkLoginAccess(): void {
		// Allow if user is already logged in.
		if ( is_user_logged_in() ) {
			return;
		}

		// Check if this is a valid custom login request.
		if ( $this->isCustomLoginRequest() ) {
			return;
		}

		// Check for allowed actions (like logout, postpass).
		$allowed_actions = [ 'logout', 'postpass', 'rp', 'resetpass', 'confirmaction' ];
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( in_array( $action, $allowed_actions, true ) ) {
			return;
		}

		// Block direct access - show 404.
		$this->show404();
	}

	/**
	 * Check admin access for non-logged users.
	 */
	public function checkAdminAccess(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		// Only check for admin pages, not admin-ajax or admin-post.
		if ( wp_doing_ajax() ) {
			return;
		}

		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';

		// Allow admin-ajax.php and admin-post.php.
		if ( strpos( $script, 'admin-ajax.php' ) !== false || strpos( $script, 'admin-post.php' ) !== false ) {
			return;
		}

		// Redirect to custom login.
		$redirect_to = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		wp_safe_redirect( $this->getLoginUrl( $redirect_to ) );
		exit;
	}

	/**
	 * Add rewrite rules for custom login.
	 */
	public function addRewriteRules(): void {
		add_rewrite_rule(
			'^' . preg_quote( $this->custom_slug, '/' ) . '/?$',
			'index.php?wp_moat_login=1',
			'top'
		);

		add_rewrite_tag( '%wp_moat_login%', '1' );
	}

	/**
	 * Handle requests to the custom login URL.
	 */
	public function handleCustomLogin(): void {
		$is_login = get_query_var( 'wp_moat_login' );

		if ( ! $is_login && ! $this->isCustomLoginRequest() ) {
			return;
		}

		// If already logged in, redirect to admin.
		if ( is_user_logged_in() ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		// Load wp-login.php.
		$this->loadLoginPage();
	}

	/**
	 * Check if this is a custom login request.
	 *
	 * @return bool True if custom login request.
	 */
	private function isCustomLoginRequest(): bool {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$request_path = trim( (string) $request_path, '/' );

		return $request_path === $this->custom_slug;
	}

	/**
	 * Load the WordPress login page.
	 */
	private function loadLoginPage(): void {
		// Set flag to allow login page access.
		if ( ! defined( 'WP_MOAT_CUSTOM_LOGIN' ) ) {
			define( 'WP_MOAT_CUSTOM_LOGIN', true );
		}

		// Include wp-login.php.
		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Show a 404 page.
	 */
	private function show404(): void {
		global $wp_query;

		status_header( 404 );
		nocache_headers();

		if ( $wp_query ) {
			$wp_query->set_404();
		}

		// Try to load theme's 404 template.
		$template = get_404_template();
		if ( $template ) {
			include $template;
		} else {
			wp_die(
				esc_html__( 'Page not found.', 'wp-moat' ),
				esc_html__( '404 Not Found', 'wp-moat' ),
				[ 'response' => 404 ]
			);
		}

		exit;
	}

	/**
	 * Get the custom login URL.
	 *
	 * @param string $redirect_to URL to redirect to after login.
	 *
	 * @return string The login URL.
	 */
	public function getLoginUrl( string $redirect_to = '' ): string {
		$login_url = home_url( $this->custom_slug );

		if ( ! empty( $redirect_to ) ) {
			$login_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_url );
		}

		return $login_url;
	}

	/**
	 * Filter the login URL.
	 *
	 * @param string $login_url    The login URL.
	 * @param string $redirect_to  Redirect destination.
	 * @param bool   $force_reauth Whether to force re-authentication.
	 *
	 * @return string Filtered URL.
	 */
	public function filterLoginUrl( string $login_url, string $redirect_to = '', bool $force_reauth = false ): string {
		$url = $this->getLoginUrl();

		if ( ! empty( $redirect_to ) ) {
			$url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $url );
		}

		if ( $force_reauth ) {
			$url = add_query_arg( 'reauth', '1', $url );
		}

		return $url;
	}

	/**
	 * Filter the logout URL.
	 *
	 * @param string $logout_url The logout URL.
	 * @param string $redirect   Redirect destination.
	 *
	 * @return string Filtered URL.
	 */
	public function filterLogoutUrl( string $logout_url, string $redirect = '' ): string {
		$url = $this->getLoginUrl();
		$url = add_query_arg( 'action', 'logout', $url );

		// Preserve the nonce.
		$parsed = wp_parse_url( $logout_url );
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
			if ( ! empty( $query['_wpnonce'] ) ) {
				$url = add_query_arg( '_wpnonce', $query['_wpnonce'], $url );
			}
		}

		if ( ! empty( $redirect ) ) {
			$url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
		}

		return $url;
	}

	/**
	 * Filter the lost password URL.
	 *
	 * @param string $url      The lost password URL.
	 * @param string $redirect Redirect destination.
	 *
	 * @return string Filtered URL.
	 */
	public function filterLostPasswordUrl( string $url, string $redirect = '' ): string {
		$url = add_query_arg( 'action', 'lostpassword', $this->getLoginUrl() );

		if ( ! empty( $redirect ) ) {
			$url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
		}

		return $url;
	}

	/**
	 * Filter the registration URL.
	 *
	 * @param string $url The registration URL.
	 *
	 * @return string Filtered URL.
	 */
	public function filterRegisterUrl( string $url ): string {
		return add_query_arg( 'action', 'register', $this->getLoginUrl() );
	}

	/**
	 * Filter site_url to replace wp-login.php references.
	 *
	 * @param string      $url     The complete URL.
	 * @param string      $path    Path relative to site URL.
	 * @param string|null $scheme  URL scheme.
	 * @param int|null    $blog_id Blog ID.
	 *
	 * @return string Filtered URL.
	 */
	public function filterSiteUrl( string $url, string $path = '', ?string $scheme = null, ?int $blog_id = null ): string {
		if ( strpos( $path, 'wp-login.php' ) !== false ) {
			$url = str_replace( 'wp-login.php', $this->custom_slug, $url );
		}

		return $url;
	}

	/**
	 * Filter redirects to ensure login redirects use custom URL.
	 *
	 * @param string $location Redirect location.
	 *
	 * @return string Filtered location.
	 */
	public function filterRedirect( string $location ): string {
		if ( strpos( $location, 'wp-login.php' ) !== false ) {
			$location = str_replace( 'wp-login.php', $this->custom_slug, $location );
		}

		return $location;
	}

	/**
	 * Get the custom slug.
	 *
	 * @return string The custom login slug.
	 */
	public function getCustomSlug(): string {
		return $this->custom_slug;
	}
}
