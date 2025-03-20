<?php

namespace Automattic\Chronos\Action_Scheduler_Tools;

class Settings {
	public const string SETTINGS_KEY = 'action_scheduler_tools_settings';

	private const array FIELDS = [
		'batch_size' => [
			'default'    => 10,
			'validation' => 'absint',
			'min'        => 0,
			'max'        => 40,
			'type'       => 'range',
		],
		'lock_duration' => [
			'default'    => 20,
			'validation' => 'absint',
			'min'        => 0,
			'max'        => 120,
			'type'       => 'range',
		],
		'max_runners' => [
			'default'    => 10,
			'validation' => 'absint',
			'min'        => 0,
			'max'        => 40,
			'type'       => 'range',
		],
		'retention_period' => [
			'default'    => 10,
			'validation' => 'absint',
			'min'        => 0,
			'max'        => 40,
			'type'       => 'range',
		],
	];

	private array $defaults;

	public function __construct() {
		foreach ( self::FIELDS as $key => $constraint ) {
			$this->defaults[ $key ]              = $constraint['default'];
			$this->defaults[ $key . '_enabled' ] = false;
		}
	}

	public function get_settings_and_constraints(): array {
		$description = self::FIELDS;
		$settings    = $this->get_settings();

		foreach ( $description as $key => $constraint ) {
			$description[ $key ]['enabled'] = $settings[ $key . '_enabled' ] ?? false;
			$description[ $key ]['value']   = $settings[ $key ] ?? $constraint['default'];
		}

		return $description;
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

			if ( isset( self::FIELDS[ $key ]['validation'] ) ) {
				$sanitized[ $key ] = call_user_func( self::FIELDS[ $key ]['validation'], $value );
			}

			if ( isset( self::FIELDS[ $key ]['min'] ) && $value < self::FIELDS[ $key ]['min'] ) {
				$sanitized[ $key ] = self::FIELDS[ $key ]['min'];
			}

			if ( isset( self::FIELDS[ $key ]['max'] ) && $value > self::FIELDS[ $key ]['max'] ) {
				$sanitized[ $key ] = self::FIELDS[ $key ]['max'];
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
