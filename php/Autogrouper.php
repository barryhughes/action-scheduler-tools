<?php

namespace Automattic\Chronos\Action_Scheduler_Tools;

/**
 * @see https://github.com/woocommerce/action-scheduler/pull/1265
 *      Autogrouping will be unaffective for anything except as_enqueue_async_action() calls until this is addressed.
 */
class Autogrouper {
	private const AS_ENQUEUE_FUNCTIONS = [
		'as_enqueue_async_action',
		'as_schedule_cron_action',
		'as_schedule_recurring_action',
		'as_schedule_single_action',
	];

	public function setup(): void {
		add_filter( 'pre_as_enqueue_async_action',      [ $this, 'autogroup_async_action' ], 10, 6 );
		add_filter( 'pre_as_schedule_single_action',    [ $this, 'autogroup_single_action' ], 10, 7 );
		add_filter( 'pre_as_schedule_recurring_action', [ $this, 'autogroup_recurring_action' ], 10, 8 );
		add_filter( 'pre_as_schedule_cron_action',      [ $this, 'autogroup_cron_action' ], 10, 8 );
	}

	public function autogroup_async_action( $short_circuit, $hook, $args, $group, $priority, $unique ) {
		return $this->autogroup_action( $short_circuit, 'as_enqueue_async_action', $hook, $args, $group, $priority, $unique );
	}

	public function autogroup_single_action( $short_circuit, $timestamp, $hook, $args, $group, $priority, $unique ) {
		return $this->autogroup_action( $short_circuit, 'as_schedule_single_action', $hook, $args, $group, $priority, $unique, $timestamp );
	}

	public function autogroup_recurring_action( $short_circuit, $timestamp, $interval_in_seconds, $hook, $args, $group, $priority, $unique ) {
		return $this->autogroup_action( $short_circuit, 'as_schedule_recurring_action', $hook, $args, $group, $priority, $unique, $timestamp, $interval_in_seconds );
	}

	public function autogroup_cron_action( $short_circuit, $timestamp, $schedule, $hook, $args, $group, $priority, $unique ) {
		return $this->autogroup_action( $short_circuit, 'as_schedule_cron_action', $hook, $args, $group, $priority, $unique, $timestamp, $schedule );
	}

	private function autogroup_action( $short_circuit, string $function, $hook, $args, $group, $priority, $unique, $timestamp = null, $scheduling = null ) {
		static $lock = false;

		if ( $lock === true || ! empty( $group ) ) {
			return $short_circuit;
		}

		$new_group = $this->assign_group();

		if ( $new_group === $group ) {
			return $short_circuit;
		}

		$lock   = true;
		$result = match ( $function ) {
			'as_enqueue_async_action'      => as_enqueue_async_action( $hook, $args, $new_group, $priority, $unique ),
			'as_schedule_single_action'    => as_schedule_single_action( $timestamp, $hook, $args, $new_group, $priority, $unique ),
			'as_schedule_recurring_action' => as_schedule_recurring_action( $timestamp, $scheduling, $hook, $args, $new_group, $priority, $unique ),
			'as_schedule_cron_action'      => as_schedule_cron_action( $timestamp, $scheduling, $hook, $args, $new_group, $priority, $unique ),
		};
		$lock   = false;

		return $result;
	}

	private function assign_group(): string {
		$call_frame_found = false;
		$origin           = '';

		foreach ( debug_backtrace() as $frame ) {
			// If the last frame was the enqueue function, this frame is the call-site.
			if ( $call_frame_found ) {
				$origin = $frame;
				break;
			}

			if ( isset( $frame['function'] ) && in_array( $frame['function'], self::AS_ENQUEUE_FUNCTIONS, true ) ) {
				$call_frame_found = true;
			}
		}

		if ( empty( $origin ) ) {
			return 'default';
		}

		// For closures, extract the path from the function key.
		if ( isset( $origin['function'] ) && str_starts_with( $origin['function'], '{closure:' ) ) {
			$source = str_replace( 'closure:', '', trim( $origin['function'], '{}' ) );
		} else {
			$source = $origin['file'];
		}

		// Normalize.
		$source = strtolower( str_replace( '\\', '/', $source ) );

		if ( str_starts_with( $source, WP_PLUGIN_DIR ) ) {
			$parts = array_values( array_filter( explode( '/', str_replace( WP_PLUGIN_DIR, '', $source ) ) ) );

			if ( ! empty( $parts[0] ) ) {
				return sanitize_title( $parts[0] );
			}
		}

		return 'default';
	}
}