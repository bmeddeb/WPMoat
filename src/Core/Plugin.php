<?php
/**
 * Main Plugin Class.
 *
 * @package WPMoat\Core
 */

declare(strict_types=1);

namespace WPMoat\Core;

/**
 * Main plugin bootstrap class.
 *
 * Initializes the plugin, registers core services in the container,
 * and coordinates module loading.
 */
class Plugin {

	/**
	 * DI Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether the plugin has been booted.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 *
	 * @param Container $container DI Container instance.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Boot the plugin.
	 *
	 * Initializes core services, loads modules, and sets up admin pages.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$this->registerCoreServices();
		$this->loadTextdomain();
		$this->loadModules();
		$this->registerAdminPages();
		$this->registerCompatibilityAdapters();

		/**
		 * Fires after WP Moat has fully initialized.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'wp_moat_loaded', $this );
	}

	/**
	 * Register core services in the container.
	 */
	private function registerCoreServices(): void {
		// Register the container itself.
		$this->container->instance( Container::class, $this->container );

		// Register Settings as a singleton.
		$this->container->singleton( Settings::class, function (): Settings {
			return new Settings();
		} );

		// Register ModuleLoader as a singleton.
		$this->container->singleton( ModuleLoader::class, function ( Container $c ): ModuleLoader {
			return new ModuleLoader(
				$c->get( Container::class ),
				$c->get( Settings::class )
			);
		} );

		/**
		 * Fires after core services are registered.
		 *
		 * Use this hook to register additional services in the container.
		 *
		 * @param Container $container The DI container.
		 */
		do_action( 'wp_moat_register_services', $this->container );
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	private function loadTextdomain(): void {
		load_plugin_textdomain(
			'wp-moat',
			false,
			dirname( WP_MOAT_BASENAME ) . '/languages'
		);
	}

	/**
	 * Discover and load enabled modules.
	 */
	private function loadModules(): void {
		/** @var ModuleLoader $loader */
		$loader = $this->container->get( ModuleLoader::class );

		$loader->discover()->loadEnabled();
	}

	/**
	 * Register admin pages.
	 */
	private function registerAdminPages(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'addAdminMenu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
	}

	/**
	 * Add the main admin menu.
	 */
	public function addAdminMenu(): void {
		// Main menu page.
		add_menu_page(
			__( 'WP Moat', 'wp-moat' ),
			__( 'WP Moat', 'wp-moat' ),
			'manage_options',
			'wp-moat',
			[ $this, 'renderDashboard' ],
			'dashicons-shield',
			80
		);

		// Dashboard submenu (same as main).
		add_submenu_page(
			'wp-moat',
			__( 'Dashboard', 'wp-moat' ),
			__( 'Dashboard', 'wp-moat' ),
			'manage_options',
			'wp-moat',
			[ $this, 'renderDashboard' ]
		);

		// Modules submenu.
		add_submenu_page(
			'wp-moat',
			__( 'Modules', 'wp-moat' ),
			__( 'Modules', 'wp-moat' ),
			'manage_options',
			'wp-moat-modules',
			[ $this, 'renderModulesPage' ]
		);

		/**
		 * Fires after WP Moat admin menus are registered.
		 *
		 * Use this hook to add additional submenu pages.
		 */
		do_action( 'wp_moat_admin_menu' );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueueAdminAssets( string $hook ): void {
		// Only load on WP Moat pages.
		if ( strpos( $hook, 'wp-moat' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'wp-moat-admin',
			WP_MOAT_URL . 'assets/css/admin.css',
			[],
			WP_MOAT_VERSION
		);

		wp_enqueue_script(
			'wp-moat-admin',
			WP_MOAT_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WP_MOAT_VERSION,
			true
		);

		wp_localize_script( 'wp-moat-admin', 'wpMoat', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wp_moat_admin' ),
		] );
	}

	/**
	 * Render the main dashboard page.
	 */
	public function renderDashboard(): void {
		$template = WP_MOAT_PATH . 'templates/dashboard.php';

		if ( file_exists( $template ) ) {
			$plugin   = $this;
			$settings = $this->container->get( Settings::class );
			$loader   = $this->container->get( ModuleLoader::class );

			include $template;
		} else {
			$this->renderDefaultDashboard();
		}
	}

	/**
	 * Render default dashboard if template is missing.
	 */
	private function renderDefaultDashboard(): void {
		/** @var ModuleLoader $loader */
		$loader  = $this->container->get( ModuleLoader::class );
		$modules = $loader->getModuleInfo();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WP Moat Security', 'wp-moat' ) . '</h1>';
		echo '<p>' . esc_html__( 'Welcome to WP Moat, your WordPress security solution.', 'wp-moat' ) . '</p>';

		if ( empty( $modules ) ) {
			echo '<div class="notice notice-info"><p>';
			echo esc_html__( 'No modules found. Add modules to the src/Modules directory to get started.', 'wp-moat' );
			echo '</p></div>';
		} else {
			echo '<h2>' . esc_html__( 'Active Modules', 'wp-moat' ) . '</h2>';
			echo '<ul>';
			foreach ( $modules as $module ) {
				if ( $module['loaded'] ) {
					printf(
						'<li><strong>%s</strong> - %s</li>',
						esc_html( $module['name'] ),
						esc_html( $module['description'] )
					);
				}
			}
			echo '</ul>';
		}

		echo '</div>';
	}

	/**
	 * Render the modules management page.
	 */
	public function renderModulesPage(): void {
		$template = WP_MOAT_PATH . 'templates/modules.php';

		if ( file_exists( $template ) ) {
			$plugin   = $this;
			$settings = $this->container->get( Settings::class );
			$loader   = $this->container->get( ModuleLoader::class );

			include $template;
		} else {
			$this->renderDefaultModulesPage();
		}
	}

	/**
	 * Render default modules page if template is missing.
	 */
	private function renderDefaultModulesPage(): void {
		/** @var ModuleLoader $loader */
		$loader  = $this->container->get( ModuleLoader::class );
		$modules = $loader->getModuleInfo();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WP Moat Modules', 'wp-moat' ) . '</h1>';

		if ( empty( $modules ) ) {
			echo '<p>' . esc_html__( 'No modules available.', 'wp-moat' ) . '</p>';
		} else {
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Module', 'wp-moat' ) . '</th>';
			echo '<th>' . esc_html__( 'Description', 'wp-moat' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'wp-moat' ) . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ( $modules as $module ) {
				$status = $module['loaded']
					? '<span style="color:green;">' . esc_html__( 'Active', 'wp-moat' ) . '</span>'
					: '<span style="color:gray;">' . esc_html__( 'Inactive', 'wp-moat' ) . '</span>';

				printf(
					'<tr><td><strong>%s</strong></td><td>%s</td><td>%s</td></tr>',
					esc_html( $module['name'] ),
					esc_html( $module['description'] ),
					$status // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				);
			}

			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * Register compatibility adapters.
	 */
	private function registerCompatibilityAdapters(): void {
		/**
		 * Fires when compatibility adapters should be registered.
		 *
		 * Third-party plugins can hook here to register their adapters.
		 *
		 * @param Container $container The DI container.
		 */
		do_action( 'wp_moat_register_compatibility_adapter', $this->container );
	}

	/**
	 * Get the DI container.
	 *
	 * @return Container The container instance.
	 */
	public function getContainer(): Container {
		return $this->container;
	}

	/**
	 * Get a service from the container.
	 *
	 * @param string $abstract The service class or alias.
	 *
	 * @return object The service instance.
	 */
	public function get( string $abstract ): object {
		return $this->container->get( $abstract );
	}
}
