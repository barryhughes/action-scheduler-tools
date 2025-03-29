<?php

namespace Automattic\Chronos\Action_Scheduler_Tools;

use ActionScheduler_Logger;

readonly class Filters {
	public function __construct(
		private array $settings
	) {}

	public function setup(): void {
		if ( $this->settings['batch_size'] ?? false ) {
			add_filter( 'action_scheduler_queue_runner_batch_size', array( $this, 'batch_size' ), 1000 );
		}

		if ( $this->settings['max_runners_enabled'] ?? false ) {
			add_filter( 'action_scheduler_queue_runner_concurrent_batches', array( $this, 'max_runners' ), 1000 );
		}

		if ( $this->settings['retention_period_enabled'] ?? false ) {
			add_filter( 'action_scheduler_retention_period', array( $this, 'retention_period' ), 1000 );
		}

		if ( $this->settings['lock_duration_enabled'] ?? false ) {
			add_filter( 'action_scheduler_lock_duration', array( $this, 'lock_duration' ), 1000, 2 );
		}

		if ( $this->settings['disable_routine_logs_enabled'] ?? false ) {
			$this->unhook_default_loggers();
		}
	}

	public function batch_size( mixed $default ): mixed {
		if ( isset( $this->settings['batch_size'] ) && is_int( $this->settings['batch_size'] ) ) {
			return (int) $this->settings['batch_size'];
		}

		return $default;
	}

	public function max_runners( mixed $default ): mixed {
		if ( isset( $this->settings['max_runners'] ) && is_int( $this->settings['max_runners'] ) ) {
			return (int) $this->settings['max_runners'];
		}

		return $default;
	}

	public function retention_period( mixed $default ): mixed {
		if ( isset( $this->settings['retention_period'] ) && is_int( $this->settings['retention_period'] ) ) {
			return (int) $this->settings['retention_period'] * DAY_IN_SECONDS;
		}

		return $default;
	}

	public function lock_duration( mixed $default, string $type ): mixed {
		if ( 'async-request-runner' === $type && isset( $this->settings['lock_duration'] ) && is_int( $this->settings['lock_duration'] ) ) {
			return (int) $this->settings['lock_duration'];
		}

		return $default;
	}

	public function unhook_default_loggers(): void {
		$remove_default_loggers = function () {
			$logger = ActionScheduler_Logger::instance();
			remove_action( 'action_scheduler_begin_execute', [ $logger, 'log_started_action' ] );
			remove_action( 'action_scheduler_after_execute', [ $logger, 'log_completed_action' ] );
			remove_action( 'action_scheduler_stored_action', [ $logger, 'log_stored_action' ] );
		};

		add_action( 'init', $remove_default_loggers, 10000 );
	}
}
