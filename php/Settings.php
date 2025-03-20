<?php

namespace Automattic\Chronos\Action_Scheduler_Tools;

class Settings {
	public const string SETTINGS_KEY = 'action_scheduler_tools_settings';

	private const CONSTRAINTS = [
		'batch_size' => [
			'default'    => 10,
			'validation' => 'absint',
			'min'        => 0,
			'max'        => 40,
		],
		'lock_duration' => [
			'default'    => 20,
			'validation' => 'absint',
			'min'        => 0,
			'max'        => 120,
		],
		'max_runners' => [
			'default'    => 10,
			'validation' => 'absint',
			'min'        => 0,
			'max'        => 40,
		],
		'retention_period' => [
			'default'    => 10,
			'validation' => 'absint',
			'min'        => 0,
			'max'        => 40,
		],
	];

	private array $defaults;

	public function __construct() {
		foreach ( self::CONSTRAINTS as $key => $constraint ) {
			$this->defaults[ $key ]              = $constraint['default'];
			$this->defaults[ $key . '_enabled' ] = false;
		}
	}
	public function get_settings(): array {
		$persisted = (array) get_option( self::SETTINGS_KEY, array() );
		return $this->sanitize(
			array_merge( $this->defaults, array_intersect_key( $persisted, $this->defaults ) )
		);
	}

	public function sanitize( array $settings ): array {
		$sanitized = [];

		foreach ( $settings as $key => $value ) {
			if ( str_ends_with( $key, '_enabled' ) ) {
				$sanitized[ $key ] = $this->boolify( $value );
				continue;
			}

			if ( isset( self::CONSTRAINTS[ $key ]['validation'] ) ) {
				$sanitized[ $key ] = call_user_func( self::CONSTRAINTS[ $key ]['validation'], $value );
			}

			if ( isset( self::CONSTRAINTS[ $key ]['min'] ) && $value < self::CONSTRAINTS[ $key ]['min'] ) {
				$sanitized[ $key ] = self::CONSTRAINTS[ $key ]['min'];
			}

			if ( isset( self::CONSTRAINTS[ $key ]['max'] ) && $value > self::CONSTRAINTS[ $key ]['max'] ) {
				$sanitized[ $key ] = self::CONSTRAINTS[ $key ]['max'];
			}
		}

		return $sanitized;
	}

	private function boolify( $value ): bool {
		// Special handling for JSON 'true' or 'false' strings.
		if ( is_string( $value ) && 'true' === strtolower( $value ) ) {
			return true;
		} elseif ( is_string( $value ) && 'false' === strtolower( $value ) ) {
			return false;
		}

		// Otherwise, follow normal PHP truthiness rules.
		return (bool) $value;
	}
}
