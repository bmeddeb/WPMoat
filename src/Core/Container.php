<?php
/**
 * Simple Dependency Injection Container.
 *
 * @package WPMoat\Core
 */

declare(strict_types=1);

namespace WPMoat\Core;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * A simple dependency injection container with autowiring support.
 */
class Container {

	/**
	 * Registered bindings (factories).
	 *
	 * @var array<string, Closure>
	 */
	private array $bindings = [];

	/**
	 * Singleton instances.
	 *
	 * @var array<string, object>
	 */
	private array $instances = [];

	/**
	 * Keys that should be treated as singletons.
	 *
	 * @var array<string, bool>
	 */
	private array $singletons = [];

	/**
	 * Register a binding in the container.
	 *
	 * @param string  $abstract The abstract type or alias.
	 * @param Closure $factory  Factory function that creates the instance.
	 */
	public function bind( string $abstract, Closure $factory ): void {
		$this->bindings[ $abstract ] = $factory;
	}

	/**
	 * Register a singleton binding in the container.
	 *
	 * @param string  $abstract The abstract type or alias.
	 * @param Closure $factory  Factory function that creates the instance.
	 */
	public function singleton( string $abstract, Closure $factory ): void {
		$this->bindings[ $abstract ]  = $factory;
		$this->singletons[ $abstract ] = true;
	}

	/**
	 * Register an existing instance as a singleton.
	 *
	 * @param string $abstract The abstract type or alias.
	 * @param object $instance The instance to register.
	 */
	public function instance( string $abstract, object $instance ): void {
		$this->instances[ $abstract ] = $instance;
	}

	/**
	 * Resolve a type from the container.
	 *
	 * @param string $abstract The abstract type or alias to resolve.
	 *
	 * @return object The resolved instance.
	 *
	 * @throws InvalidArgumentException If the type cannot be resolved.
	 */
	public function get( string $abstract ): object {
		// Return existing singleton instance.
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		// Use registered binding if available.
		if ( isset( $this->bindings[ $abstract ] ) ) {
			$instance = $this->bindings[ $abstract ]( $this );

			// Cache if singleton.
			if ( isset( $this->singletons[ $abstract ] ) ) {
				$this->instances[ $abstract ] = $instance;
			}

			return $instance;
		}

		// Try autowiring.
		return $this->autowire( $abstract );
	}

	/**
	 * Check if a type is registered in the container.
	 *
	 * @param string $abstract The abstract type or alias.
	 *
	 * @return bool True if registered, false otherwise.
	 */
	public function has( string $abstract ): bool {
		return isset( $this->bindings[ $abstract ] ) || isset( $this->instances[ $abstract ] );
	}

	/**
	 * Autowire a class by resolving its constructor dependencies.
	 *
	 * @param string $class The fully qualified class name.
	 *
	 * @return object The instantiated object.
	 *
	 * @throws InvalidArgumentException If the class cannot be autowired.
	 */
	private function autowire( string $class ): object {
		if ( ! class_exists( $class ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Class "%s" does not exist and cannot be autowired.', $class )
			);
		}

		try {
			$reflection = new ReflectionClass( $class );
		} catch ( ReflectionException $e ) {
			throw new InvalidArgumentException(
				sprintf( 'Cannot reflect class "%s": %s', $class, $e->getMessage() )
			);
		}

		if ( ! $reflection->isInstantiable() ) {
			throw new InvalidArgumentException(
				sprintf( 'Class "%s" is not instantiable.', $class )
			);
		}

		$constructor = $reflection->getConstructor();

		// No constructor means no dependencies.
		if ( null === $constructor ) {
			return new $class();
		}

		$parameters   = $constructor->getParameters();
		$dependencies = [];

		foreach ( $parameters as $parameter ) {
			$type = $parameter->getType();

			// Handle untyped or built-in type parameters.
			if ( ! $type instanceof ReflectionNamedType || $type->isBuiltin() ) {
				if ( $parameter->isDefaultValueAvailable() ) {
					$dependencies[] = $parameter->getDefaultValue();
					continue;
				}

				throw new InvalidArgumentException(
					sprintf(
						'Cannot autowire parameter "$%s" in class "%s" - no type hint or default value.',
						$parameter->getName(),
						$class
					)
				);
			}

			// Resolve the dependency.
			$dependency_class = $type->getName();
			$dependencies[]   = $this->get( $dependency_class );
		}

		return $reflection->newInstanceArgs( $dependencies );
	}

	/**
	 * Call a method on an object, autowiring its parameters.
	 *
	 * @param object       $instance   The object instance.
	 * @param string       $method     The method name.
	 * @param array<mixed> $parameters Additional parameters to pass.
	 *
	 * @return mixed The method return value.
	 *
	 * @throws InvalidArgumentException If the method cannot be called.
	 */
	public function call( object $instance, string $method, array $parameters = [] ): mixed {
		try {
			$reflection = new ReflectionClass( $instance );
			$method_ref = $reflection->getMethod( $method );
		} catch ( ReflectionException $e ) {
			throw new InvalidArgumentException(
				sprintf( 'Cannot reflect method "%s": %s', $method, $e->getMessage() )
			);
		}

		$method_params = $method_ref->getParameters();
		$dependencies  = [];

		foreach ( $method_params as $index => $parameter ) {
			// Use provided parameter if available.
			if ( array_key_exists( $index, $parameters ) ) {
				$dependencies[] = $parameters[ $index ];
				continue;
			}

			if ( array_key_exists( $parameter->getName(), $parameters ) ) {
				$dependencies[] = $parameters[ $parameter->getName() ];
				continue;
			}

			$type = $parameter->getType();

			// Try to autowire typed parameters.
			if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
				$dependencies[] = $this->get( $type->getName() );
				continue;
			}

			// Use default value if available.
			if ( $parameter->isDefaultValueAvailable() ) {
				$dependencies[] = $parameter->getDefaultValue();
				continue;
			}

			throw new InvalidArgumentException(
				sprintf(
					'Cannot resolve parameter "$%s" for method "%s".',
					$parameter->getName(),
					$method
				)
			);
		}

		return $method_ref->invokeArgs( $instance, $dependencies );
	}
}
