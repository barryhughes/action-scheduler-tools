<?php

namespace Automattic\Chronos\Action_Scheduler_Tools;

/**
 * @todo examine if the current lock works (and works efficiently) now that both Autogrouper and Reprioritizer inherit/extend this class.
 */
class Modifier {
	protected const AS_ENQUEUE_FUNCTIONS = [
		'as_enqueue_async_action',
		'as_schedule_cron_action',
		'as_schedule_recurring_action',
		'as_schedule_single_action',
	];

	public function setup(): void {
		add_filter( 'pre_as_enqueue_async_action',      [ $this, 'listen_for_async_action' ], 10, 6 );
		add_filter( 'pre_as_schedule_single_action',    [ $this, 'listen_for_single_action' ], 10, 7 );
		add_filter( 'pre_as_schedule_recurring_action', [ $this, 'listen_for_recurring_action' ], 10, 8 );
		add_filter( 'pre_as_schedule_cron_action',      [ $this, 'listen_for_cron_action' ], 10, 8 );
	}

	public function listen_for_async_action( $short_circuit, $hook, $args, $group, $priority, $unique = null ) {
		$unique = $unique === null ? $this->discover_if_unique() : $unique;
		return $this->modify_action( $short_circuit, 'as_enqueue_async_action', $hook, $args, $group, $priority, $unique );
	}

	public function listen_for_single_action( $short_circuit, $timestamp, $hook, $args, $group, $priority, $unique = null ) {
		$unique = $unique === null ? $this->discover_if_unique() : $unique;
		return $this->modify_action( $short_circuit, 'as_schedule_single_action', $hook, $args, $group, $priority, $unique, $timestamp );
	}

	public function listen_for_recurring_action( $short_circuit, $timestamp, $interval_in_seconds, $hook, $args, $group, $priority, $unique = null ) {
		$unique = $unique === null ? $this->discover_if_unique() : $unique;
		return $this->modify_action( $short_circuit, 'as_schedule_recurring_action', $hook, $args, $group, $priority, $unique, $timestamp, $interval_in_seconds );
	}

	public function listen_for_cron_action( $short_circuit, $timestamp, $schedule, $hook, $args, $group, $priority, $unique = null ) {
		$unique = $unique === null ? $this->discover_if_unique() : $unique;
		return $this->modify_action( $short_circuit, 'as_schedule_cron_action', $hook, $args, $group, $priority, $unique, $timestamp, $schedule );
	}

	/**
	 * The hooks we are using have not always supplied information about uniqueness (and,
	 * we don't need to go back to many versions to find a point where uniqueness wasn't
	 * supported). This method helps us support the concept in a cross-version way.
	 *
	 * @see https://github.com/woocommerce/action-scheduler/pull/1265
	 */
	private function discover_if_unique(): bool {
		$level = 0;

		foreach ( debug_backtrace() as $frame ) {
			if ( ++$level > 10 ) {
				return false;
			}

			if ( ! isset( $frame['function'] ) || ! in_array( $frame['function'], self::AS_ENQUEUE_FUNCTIONS, true ) ) {
				continue;
			}

			switch ( $frame['function'] ) {
				case 'as_enqueue_async_action':
					return $frame['args'][3] ?? false;

				case 'as_schedule_cron_action':
				case 'as_schedule_recurring_action':
					return $frame['args'][5] ?? false;

				case 'as_schedule_single_action':
					return $frame['args'][4] ?? false;
			};
		}

		return false;
	}

	protected function modify_action( $short_circuit, string $function, $hook, $args, $group, $priority, $unique = null, $timestamp = null, $scheduling = null ) {
		return $short_circuit;
	}
}