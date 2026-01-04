<?php
/**
 * Module Loader.
 *
 * @package WPMoat\Core
 */

declare(strict_types=1);

namespace WPMoat\Core;

/**
 * Discovers and loads WP Moat modules.
 *
 * Modules are loaded conditionally based on their enabled status in settings.
 * This keeps the plugin lightweight by only loading what's needed.
 */
class ModuleLoader {

	/**
	 * DI Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Settings manager instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Registered module classes.
	 *
	 * @var array<string, class-string<ModuleInterface>>
	 */
	private array $registered = [];

	/**
	 * Loaded module instances.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $loaded = [];

	/**
	 * Constructor.
	 *
	 * @param Container $container DI Container instance.
	 * @param Settings  $settings  Settings manager instance.
	 */
	public function __construct( Container $container, Settings $settings ) {
		$this->container = $container;
		$this->settings  = $settings;
	}

	/**
	 * Register a module class.
	 *
	 * @param class-string<ModuleInterface> $module_class The fully qualified module class name.
	 *
	 * @return self For method chaining.
	 */
	public function register( string $module_class ): self {
		// Create a temporary instance to get the module ID.
		$instance                            = $this->container->get( $module_class );
		$this->registered[ $instance->getId() ] = $module_class;

		return $this;
	}

	/**
	 * Discover and register modules from the Modules directory.
	 *
	 * Looks for Module.php files in subdirectories of src/Modules/.
	 *
	 * @return self For method chaining.
	 */
	public function discover(): self {
		$modules_dir = WP_MOAT_PATH . 'src/Modules';

		if ( ! is_dir( $modules_dir ) ) {
			return $this;
		}

		$directories = glob( $modules_dir . '/*', GLOB_ONLYDIR );

		if ( false === $directories ) {
			return $this;
		}

		foreach ( $directories as $dir ) {
			$module_file = $dir . '/Module.php';

			if ( ! file_exists( $module_file ) ) {
				continue;
			}

			$module_name  = basename( $dir );
			$module_class = "WPMoat\\Modules\\{$module_name}\\Module";

			if ( class_exists( $module_class ) && is_subclass_of( $module_class, ModuleInterface::class ) ) {
				$this->register( $module_class );
			}
		}

		return $this;
	}

	/**
	 * Load all enabled modules.
	 *
	 * Creates instances and calls boot() on each enabled module.
	 *
	 * @return self For method chaining.
	 */
	public function loadEnabled(): self {
		foreach ( $this->registered as $module_id => $module_class ) {
			if ( $this->shouldLoad( $module_id ) ) {
				$this->load( $module_id );
			}
		}

		/**
		 * Fires after all enabled modules have been loaded.
		 *
		 * @param array<string, ModuleInterface> $loaded_modules Array of loaded module instances.
		 */
		do_action( 'wp_moat_modules_loaded', $this->loaded );

		return $this;
	}

	/**
	 * Check if a module should be loaded.
	 *
	 * @param string $module_id The module identifier.
	 *
	 * @return bool True if the module should be loaded.
	 */
	private function shouldLoad( string $module_id ): bool {
		// Core module is always loaded.
		if ( 'core' === $module_id ) {
			return true;
		}

		// Path-hiding is enabled by default.
		if ( 'path-hiding' === $module_id ) {
			$enabled = $this->settings->get( $module_id, 'enabled', true );
			return (bool) $enabled;
		}

		return $this->settings->isModuleEnabled( $module_id );
	}

	/**
	 * Load a specific module.
	 *
	 * @param string $module_id The module identifier.
	 *
	 * @return ModuleInterface|null The loaded module, or null if not found.
	 */
	public function load( string $module_id ): ?ModuleInterface {
		if ( isset( $this->loaded[ $module_id ] ) ) {
			return $this->loaded[ $module_id ];
		}

		if ( ! isset( $this->registered[ $module_id ] ) ) {
			return null;
		}

		$module_class = $this->registered[ $module_id ];
		$module       = $this->container->get( $module_class );

		$module->boot();
		$this->loaded[ $module_id ] = $module;

		/**
		 * Fires after a module has been loaded.
		 *
		 * @param ModuleInterface $module The loaded module instance.
		 */
		do_action( 'wp_moat_module_loaded', $module );
		do_action( "wp_moat_module_{$module_id}_loaded", $module );

		return $module;
	}

	/**
	 * Get a loaded module by ID.
	 *
	 * @param string $module_id The module identifier.
	 *
	 * @return ModuleInterface|null The module instance, or null if not loaded.
	 */
	public function get( string $module_id ): ?ModuleInterface {
		return $this->loaded[ $module_id ] ?? null;
	}

	/**
	 * Get all loaded modules.
	 *
	 * @return array<string, ModuleInterface> Array of loaded module instances.
	 */
	public function getLoaded(): array {
		return $this->loaded;
	}

	/**
	 * Get all registered modules (loaded or not).
	 *
	 * @return array<string, class-string<ModuleInterface>> Array of registered module classes.
	 */
	public function getRegistered(): array {
		return $this->registered;
	}

	/**
	 * Get information about all registered modules.
	 *
	 * @return array<string, array{id: string, name: string, description: string, enabled: bool, loaded: bool}> Module info.
	 */
	public function getModuleInfo(): array {
		$info = [];

		foreach ( $this->registered as $module_id => $module_class ) {
			$instance = $this->container->get( $module_class );

			$info[ $module_id ] = [
				'id'          => $instance->getId(),
				'name'        => $instance->getName(),
				'description' => $instance->getDescription(),
				'enabled'     => $instance->isEnabled(),
				'loaded'      => isset( $this->loaded[ $module_id ] ),
			];
		}

		return $info;
	}
}
