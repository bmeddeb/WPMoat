<?php
/**
 * Header Cleaner Service.
 *
 * @package WPMoat\Modules\PathHiding
 */

declare(strict_types=1);

namespace WPMoat\Modules\PathHiding;

/**
 * Removes revealing HTTP headers from WordPress responses.
 *
 * - Removes X-Powered-By header
 * - Removes X-Pingback header
 * - Optionally removes Link headers
 */
class HeaderCleaner {

	/**
	 * Headers to remove.
	 *
	 * @var array<string>
	 */
	private array $headers_to_remove = [
		'X-Powered-By',
		'X-Pingback',
		'X-Redirect-By',
	];

	/**
	 * Register hooks for header cleaning.
	 */
	public function register(): void {
		// Clean headers on send.
		add_action( 'send_headers', [ $this, 'cleanHeaders' ], 100 );

		// Remove X-Pingback header.
		add_filter( 'wp_headers', [ $this, 'removeXPingback' ] );

		// Disable pingback functionality.
		add_filter( 'xmlrpc_methods', [ $this, 'disablePingback' ] );

		// Remove pingback link from head.
		remove_action( 'wp_head', 'rsd_link' );

		// Filter REST API headers.
		add_filter( 'rest_post_dispatch', [ $this, 'cleanRestHeaders' ], 100, 3 );
	}

	/**
	 * Clean revealing headers.
	 */
	public function cleanHeaders(): void {
		if ( headers_sent() ) {
			return;
		}

		foreach ( $this->headers_to_remove as $header ) {
			header_remove( $header );
		}

		// Also try to remove PHP version exposure.
		if ( function_exists( 'header_remove' ) ) {
			header_remove( 'X-Powered-By' );
		}
	}

	/**
	 * Remove X-Pingback header.
	 *
	 * @param array<string, string> $headers Response headers.
	 *
	 * @return array<string, string> Filtered headers.
	 */
	public function removeXPingback( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Disable pingback XML-RPC methods.
	 *
	 * @param array<string, callable> $methods XML-RPC methods.
	 *
	 * @return array<string, callable> Filtered methods.
	 */
	public function disablePingback( array $methods ): array {
		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * Clean REST API response headers.
	 *
	 * @param \WP_REST_Response $response Response object.
	 * @param \WP_REST_Server   $server   Server instance.
	 * @param \WP_REST_Request  $request  Request object.
	 *
	 * @return \WP_REST_Response Modified response.
	 */
	public function cleanRestHeaders( \WP_REST_Response $response, \WP_REST_Server $server, \WP_REST_Request $request ): \WP_REST_Response {
		// Remove headers that reveal WordPress.
		$response->remove_header( 'X-WP-Total' );
		$response->remove_header( 'X-WP-TotalPages' );

		return $response;
	}

	/**
	 * Add a header to the removal list.
	 *
	 * @param string $header Header name.
	 */
	public function addHeaderToRemove( string $header ): void {
		if ( ! in_array( $header, $this->headers_to_remove, true ) ) {
			$this->headers_to_remove[] = $header;
		}
	}

	/**
	 * Get list of headers being removed.
	 *
	 * @return array<string> Header names.
	 */
	public function getHeadersToRemove(): array {
		return $this->headers_to_remove;
	}
}
