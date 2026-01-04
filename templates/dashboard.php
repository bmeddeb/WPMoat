<?php
/**
 * Dashboard Template
 *
 * @package WPMoat
 *
 * @var WPMoat\Core\Plugin       $plugin   Plugin instance.
 * @var WPMoat\Core\Settings     $settings Settings instance.
 * @var WPMoat\Core\ModuleLoader $loader   Module loader instance.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$modules        = $loader->getModuleInfo();
$active_count   = count( array_filter( $modules, fn( $m ) => $m['loaded'] ) );
$total_count    = count( $modules );
?>

<div class="wrap wp-moat-dashboard">
	<div class="wp-moat-header">
		<h1>
			<span class="dashicons dashicons-shield"></span>
			<?php esc_html_e( 'WP Moat Security', 'wp-moat' ); ?>
		</h1>
		<p><?php esc_html_e( 'Protect your WordPress site with comprehensive security features.', 'wp-moat' ); ?></p>
	</div>

	<?php settings_errors(); ?>

	<div class="wp-moat-stats">
		<div class="wp-moat-stat-box">
			<div class="stat-number"><?php echo esc_html( (string) $active_count ); ?></div>
			<div class="stat-label"><?php esc_html_e( 'Active Modules', 'wp-moat' ); ?></div>
		</div>
		<div class="wp-moat-stat-box">
			<div class="stat-number"><?php echo esc_html( (string) $total_count ); ?></div>
			<div class="stat-label"><?php esc_html_e( 'Available Modules', 'wp-moat' ); ?></div>
		</div>
		<div class="wp-moat-stat-box">
			<div class="stat-number"><?php echo esc_html( WP_MOAT_VERSION ); ?></div>
			<div class="stat-label"><?php esc_html_e( 'Version', 'wp-moat' ); ?></div>
		</div>
	</div>

	<?php if ( empty( $modules ) ) : ?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'No modules found. Security modules will appear here once they are added.', 'wp-moat' ); ?>
			</p>
		</div>
	<?php else : ?>
		<h2><?php esc_html_e( 'Security Modules', 'wp-moat' ); ?></h2>
		<div class="wp-moat-modules-grid">
			<?php foreach ( $modules as $module ) : ?>
				<div class="wp-moat-module-card">
					<h3>
						<?php echo esc_html( $module['name'] ); ?>
						<span class="status-indicator <?php echo $module['loaded'] ? 'active' : 'inactive'; ?>"></span>
					</h3>
					<p><?php echo esc_html( $module['description'] ); ?></p>
					<div class="actions">
						<span class="wp-moat-status-badge <?php echo $module['loaded'] ? 'active' : 'inactive'; ?>">
							<?php echo $module['loaded'] ? esc_html__( 'Active', 'wp-moat' ) : esc_html__( 'Inactive', 'wp-moat' ); ?>
						</span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="wp-moat-footer" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #c3c4c7;">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin version */
				esc_html__( 'WP Moat v%s', 'wp-moat' ),
				esc_html( WP_MOAT_VERSION )
			);
			?>
			|
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-moat-modules' ) ); ?>">
				<?php esc_html_e( 'Manage Modules', 'wp-moat' ); ?>
			</a>
		</p>
	</div>
</div>
