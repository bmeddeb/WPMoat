<?php
/**
 * Modules Management Template
 *
 * @package WPMoat
 *
 * @var WPMoat\Core\Plugin       $plugin   Plugin instance.
 * @var WPMoat\Core\Settings     $settings Settings instance.
 * @var WPMoat\Core\ModuleLoader $loader   Module loader instance.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$modules = $loader->getModuleInfo();
?>

<div class="wrap wp-moat-modules-page">
	<h1><?php esc_html_e( 'WP Moat Modules', 'wp-moat' ); ?></h1>
	<p><?php esc_html_e( 'Enable or disable security modules based on your needs.', 'wp-moat' ); ?></p>

	<?php settings_errors(); ?>

	<?php if ( empty( $modules ) ) : ?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'No modules available. Add modules to the src/Modules directory.', 'wp-moat' ); ?>
			</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="column-name"><?php esc_html_e( 'Module', 'wp-moat' ); ?></th>
					<th scope="col" class="column-description"><?php esc_html_e( 'Description', 'wp-moat' ); ?></th>
					<th scope="col" class="column-status" style="width: 100px;"><?php esc_html_e( 'Status', 'wp-moat' ); ?></th>
					<th scope="col" class="column-actions" style="width: 150px;"><?php esc_html_e( 'Actions', 'wp-moat' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $modules as $module ) : ?>
					<tr>
						<td class="column-name">
							<strong><?php echo esc_html( $module['name'] ); ?></strong>
							<br>
							<small style="color: #646970;"><?php echo esc_html( $module['id'] ); ?></small>
						</td>
						<td class="column-description">
							<?php echo esc_html( $module['description'] ); ?>
						</td>
						<td class="column-status">
							<span class="wp-moat-status-badge <?php echo $module['loaded'] ? 'active' : 'inactive'; ?>">
								<?php echo $module['loaded'] ? esc_html__( 'Active', 'wp-moat' ) : esc_html__( 'Inactive', 'wp-moat' ); ?>
							</span>
						</td>
						<td class="column-actions">
							<button
								type="button"
								class="button wp-moat-toggle-module <?php echo $module['loaded'] ? '' : 'button-primary'; ?>"
								data-module="<?php echo esc_attr( $module['id'] ); ?>"
								data-enabled="<?php echo $module['loaded'] ? 'true' : 'false'; ?>"
							>
								<?php echo $module['loaded'] ? esc_html__( 'Disable', 'wp-moat' ) : esc_html__( 'Enable', 'wp-moat' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="wp-moat-notice info" style="margin-top: 20px;">
			<p>
				<strong><?php esc_html_e( 'Note:', 'wp-moat' ); ?></strong>
				<?php esc_html_e( 'Some modules may require additional configuration after activation. Check each module\'s settings page for options.', 'wp-moat' ); ?>
			</p>
		</div>
	<?php endif; ?>
</div>
