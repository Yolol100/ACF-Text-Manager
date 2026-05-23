<?php
namespace WA_ACF_PTM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Container {
	/**
	 * @var array<string, callable(self):mixed>
	 */
	private array $bindings = array();

	/**
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * @param callable(self):mixed $factory
	 */
	public function singleton( string $id, callable $factory ): void {
		$this->bindings[ $id ] = $factory;
	}

	/**
	 * @template T
	 * @param class-string<T>|string $id
	 * @return T|mixed
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->bindings[ $id ] ) ) {
			throw new \RuntimeException( sprintf( 'Container binding not found: %s', esc_html( $id ) ) );
		}

		$this->instances[ $id ] = ( $this->bindings[ $id ] )( $this );

		return $this->instances[ $id ];
	}
}
