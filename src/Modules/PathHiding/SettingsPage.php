<?php
/**
 * Path Hiding Settings Page.
 *
 * @package WPMoat\Modules\PathHiding
 */

declare(strict_types=1);

namespace WPMoat\Modules\PathHiding;

use WPMoat\Core\AdminPage;
use WPMoat\Core\Settings;

/**
 * Admin settings page for the Path Hiding module.
 */
class SettingsPage extends AdminPage {

	/**
	 * Module ID for settings.
	 */
	private const MODULE_ID = 'path-hiding';

	/**
	 * {@inheritdoc}
	 */
	public function getSlug(): string {
		return 'wp-moat-path-hiding';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTitle(): string {
		return __( 'Path Hiding', 'wp-moat' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMenuTitle(): string {
		return __( 'Path Hiding', 'wp-moat' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		parent::register();
		add_action( 'admin_init', [ $this, 'handleFormSubmission' ] );
	}

	/**
	 * Handle form submissions.
	 */
	public function handleFormSubmission(): void {
		if ( ! isset( $_POST['wp_moat_path_hiding_submit'] ) ) {
			return;
		}

		if ( ! $this->verifyNonce( 'wp_moat_path_hiding_settings' ) ) {
			$this->addNotice( __( 'Security check failed. Please try again.', 'wp-moat' ), 'error' );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->addNotice( __( 'You do not have permission to change these settings.', 'wp-moat' ), 'error' );
			return;
		}

		$settings = $this->sanitizeSettings( $_POST );
		$this->settings->saveAll( self::MODULE_ID, $settings );

		// Flush rewrite rules when login slug changes.
		flush_rewrite_rules();

		$this->addNotice( __( 'Settings saved successfully.', 'wp-moat' ), 'success' );
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 *
	 * @return array<string, mixed> Sanitized settings.
	 */
	private function sanitizeSettings( array $input ): array {
		return [
			'enabled'               => isset( $input['enabled'] ),
			'custom_login_slug'     => isset( $input['custom_login_slug'] ) ? sanitize_title( $input['custom_login_slug'] ) : '',
			'hide_wp_paths'         => isset( $input['hide_wp_paths'] ),
			'custom_content_path'   => isset( $input['custom_content_path'] ) ? sanitize_file_name( $input['custom_content_path'] ) : '',
			'custom_plugins_path'   => isset( $input['custom_plugins_path'] ) ? sanitize_file_name( $input['custom_plugins_path'] ) : '',
			'custom_themes_path'    => isset( $input['custom_themes_path'] ) ? sanitize_file_name( $input['custom_themes_path'] ) : '',
			'remove_version'        => isset( $input['remove_version'] ),
			'remove_meta_generator' => isset( $input['remove_meta_generator'] ),
			'remove_rsd_link'       => isset( $input['remove_rsd_link'] ),
			'remove_wlw_manifest'   => isset( $input['remove_wlw_manifest'] ),
			'remove_shortlink'      => isset( $input['remove_shortlink'] ),
			'clean_headers'         => isset( $input['clean_headers'] ),
			'disable_xmlrpc'        => isset( $input['disable_xmlrpc'] ),
			'disable_rest_users'    => isset( $input['disable_rest_users'] ),
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->getCapability() ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-moat' ) );
		}

		$defaults = [
			'enabled'               => true,
			'custom_login_slug'     => '',
			'hide_wp_paths'         => true,
			'custom_content_path'   => '',
			'custom_plugins_path'   => '',
			'custom_themes_path'    => '',
			'remove_version'        => true,
			'remove_meta_generator' => true,
			'remove_rsd_link'       => true,
			'remove_wlw_manifest'   => true,
			'remove_shortlink'      => true,
			'clean_headers'         => true,
			'disable_xmlrpc'        => true,
			'disable_rest_users'    => true,
		];

		$settings = $this->settings->getAll( self::MODULE_ID, $defaults );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->getTitle() ); ?></h1>
			<p><?php esc_html_e( 'Configure path hiding options to obscure WordPress fingerprints.', 'wp-moat' ); ?></p>

			<?php $this->displayNotices(); ?>

			<form method="post" action="">
				<?php $this->nonceField( 'wp_moat_path_hiding_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Path Hiding', 'wp-moat' ); ?></th>
						<td>
							<?php
							$this->renderCheckbox(
								'enabled',
								(bool) $settings['enabled'],
								__( 'Enable the Path Hiding module', 'wp-moat' )
							);
							?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Custom Login URL', 'wp-moat' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Hide wp-login.php by using a custom login URL. Leave empty to use the default.', 'wp-moat' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="custom_login_slug"><?php esc_html_e( 'Custom Login Slug', 'wp-moat' ); ?></label>
						</th>
						<td>
							<?php echo esc_html( home_url( '/' ) ); ?>
							<input
								type="text"
								name="custom_login_slug"
								id="custom_login_slug"
								value="<?php echo esc_attr( $settings['custom_login_slug'] ); ?>"
								class="regular-text"
								placeholder="secure-login"
							/>
							<p class="description">
								<?php esc_html_e( 'Example: secure-login, my-login, access', 'wp-moat' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Path Obfuscation', 'wp-moat' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Replace default WordPress paths in HTML output. Requires server rewrite rules.', 'wp-moat' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Path Hiding', 'wp-moat' ); ?></th>
						<td>
							<?php
							$this->renderCheckbox(
								'hide_wp_paths',
								(bool) $settings['hide_wp_paths'],
								__( 'Replace WordPress paths in HTML output', 'wp-moat' )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_content_path"><?php esc_html_e( 'Custom Content Path', 'wp-moat' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								name="custom_content_path"
								id="custom_content_path"
								value="<?php echo esc_attr( $settings['custom_content_path'] ); ?>"
								class="regular-text"
								placeholder="assets"
							/>
							<p class="description">
								<?php esc_html_e( 'Replaces wp-content. Example: assets, content, static', 'wp-moat' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_plugins_path"><?php esc_html_e( 'Custom Plugins Path', 'wp-moat' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								name="custom_plugins_path"
								id="custom_plugins_path"
								value="<?php echo esc_attr( $settings['custom_plugins_path'] ); ?>"
								class="regular-text"
								placeholder="modules"
							/>
							<p class="description">
								<?php esc_html_e( 'Replaces wp-content/plugins. Example: modules, extensions, addons', 'wp-moat' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_themes_path"><?php esc_html_e( 'Custom Themes Path', 'wp-moat' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								name="custom_themes_path"
								id="custom_themes_path"
								value="<?php echo esc_attr( $settings['custom_themes_path'] ); ?>"
								class="regular-text"
								placeholder="templates"
							/>
							<p class="description">
								<?php esc_html_e( 'Replaces wp-content/themes. Example: templates, layouts, skins', 'wp-moat' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Version Hiding', 'wp-moat' ); ?></h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Remove Version Strings', 'wp-moat' ); ?></th>
						<td>
							<?php
							$this->renderCheckbox(
								'remove_version',
								(bool) $settings['remove_version'],
								__( 'Remove version query strings from scripts and styles', 'wp-moat' )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Remove Generator Meta', 'wp-moat' ); ?></th>
						<td>
							<?php
							$this->renderCheckbox(
								'remove_meta_generator',
								(bool) $settings['remove_meta_generator'],
								__( 'Remove WordPress generator meta tag', 'wp-moat' )
							);
							?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Header Cleanup', 'wp-moat' ); ?></h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Remove RSD Link', 'wp-moat' ); ?></th>
						<td>
							<?php
							$this->renderCheckbox(
								'remove_rsd_link',
								(bool) $settings['remove_rsd_link'],
								__( 'Remove Really Simple Discovery link', 'wp-moat' )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Remove WLW Manifest', 'wp-moat' ); ?></th>
						<td>
							<?php
							$this->renderCheckbox(
								'remove_wlw_manifest',
								(bool) $settings['remove_wlw_manifest'],
								__( 'Remove Windows Live Writer manifest link', 'wp-moat' )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Remove Shortlink', 'wp-moat' ); ?></th>
						<td>
							<?php
							$this->renderCheckbox(
								'remove_shortlink',
								(bool) $settings['remove_shortlink'],
								__( 'Remove shortlink from head and headers', 'wp-moat' )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Clean HTTP Headers', 'wp-moat' ); ?></th>
						<td>
							<?php
							$this->renderCheckbox(
								'clean_headers',
								(bool) $settings['clean_headers'],
								__( 'Remove X-Powered-By, X-Pingback, and other revealing headers', 'wp-moat' )
							);
							?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'API Protection', 'wp-moat' ); ?></h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Disable XML-RPC', 'wp-moat' ); ?></th>
						<td>
							<?php
							$this->renderCheckbox(
								'disable_xmlrpc',
								(bool) $settings['disable_xmlrpc'],
								__( 'Disable XML-RPC functionality completely', 'wp-moat' ),
								__( 'Recommended unless you use XML-RPC for remote publishing or Jetpack.', 'wp-moat' )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Disable REST User Endpoints', 'wp-moat' ); ?></th>
						<td>
							<?php
							$this->renderCheckbox(
								'disable_rest_users',
								(bool) $settings['disable_rest_users'],
								__( 'Block user enumeration via REST API for non-logged users', 'wp-moat' ),
								__( 'Prevents attackers from discovering usernames via /wp-json/wp/v2/users.', 'wp-moat' )
							);
							?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'wp-moat' ), 'primary', 'wp_moat_path_hiding_submit' ); ?>
			</form>
		</div>
		<?php
	}
}
