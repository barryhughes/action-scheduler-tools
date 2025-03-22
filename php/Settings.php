<?php

namespace Automattic\Chronos\Action_Scheduler_Tools;

class Settings {
	public const string SETTINGS_KEY = 'action_scheduler_tools_settings';
	private array $fields;
	private array $defaults;

	public function __construct() {
		$this->fields = [
			'batch_size' => [
				'default'     => 10,
				'validation'  => 'absint',
				'min'         => 0,
				'max'         => 40,
				'type'        => 'range',
				'name'        => __( 'Batch Size', 'action-scheduler-tools' ),
				'description' => __( 'This controls the number of actions that an individual queue runner will attempt to claim per batch.', 'action-scheduler-tools' ),
			],
			'lock_duration' => [
				'default'     => 20,
				'validation'  => 'absint',
				'min'         => 0,
				'max'         => 120,
				'type'        => 'range',
				'name'        => __( 'Max Queue Runners', 'action-scheduler-tools' ),
				'description' => __( 'The maximum number of queue runners that should exist and process actions at the same time.', 'action-scheduler-tools' ),
			],
			'max_runners' => [
				'default'     => 10,
				'validation'  => 'absint',
				'min'         => 0,
				'max'         => 40,
				'type'        => 'range',
				'name'        => __( 'Retention Period', 'action-scheduler-tools' ),
				'description' => __( 'The number of days for which records of completed actions should be retained.', 'action-scheduler-tools' ),
			],
			'retention_period' => [
				'default'     => 10,
				'validation'  => 'absint',
				'min'         => 0,
				'max'         => 40,
				'type'        => 'range',
				'name'        => __( 'Async Lock Duration', 'action-scheduler-tools' ),
				'description' => __( 'Delay in seconds between the creation of new async queue runners.', 'action-scheduler-tools'),
			],
		];

		foreach ( $this->fields as $key => $constraint ) {
			$this->defaults[ $key ]              = $constraint['default'];
			$this->defaults[ $key . '_enabled' ] = false;
		}
	}

	public function get_settings_and_constraints(): array {
		$description = $this->fields;
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

			if ( isset( $this->fields[ $key ]['validation'] ) ) {
				$sanitized[ $key ] = call_user_func( $this->fields[ $key ]['validation'], $value );
			}

			if ( isset( $this->fields[ $key ]['min'] ) && $value < $this->fields[ $key ]['min'] ) {
				$sanitized[ $key ] = $this->fields[ $key ]['min'];
			}

			if ( isset( $this->fields[ $key ]['max'] ) && $value > $this->fields[ $key ]['max'] ) {
				$sanitized[ $key ] = $this->fields[ $key ]['max'];
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
