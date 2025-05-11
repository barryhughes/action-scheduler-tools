<?php

namespace Automattic\Chronos\Action_Scheduler_Tools;

use ActionScheduler_Store;
use Exception;

class Plugin {
	public const string SETTINGS_KEY = 'action_scheduler_tools_settings';
	private Settings $settings;

	public function __construct(
		public readonly string $plugin_url,
		public readonly string $version,
	) {}

	public function setup(): void {
		add_action( 'load-tools_page_action-scheduler', array( $this, 'on_action_scheduler_screen' ) );
		add_action( 'wp_ajax_action_scheduler_tools_save_settings', array( $this, 'on_settings_save' ) );
		add_action( 'wp_ajax_action_scheduler_tools_delete_finalized', array( $this, 'on_delete_finalized' ) );
		add_action( 'action_scheduler_init', array( $this, 'apply_filters' ), 1 );
	}

	public function on_action_scheduler_screen(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts(): void {
		wp_enqueue_style( 'action-scheduler-tools', $this->plugin_url . '/css/action-scheduler-tools.css', array(), $this->version );
		wp_enqueue_script( 'action-scheduler-tools', $this->plugin_url . '/js/action-scheduler-tools.js', array( 'wp-i18n' ), $this->version );
		wp_localize_script( 'action-scheduler-tools', 'actionSchedulerTools', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'action-scheduler-tools' ),
			'settings' => $this->settings()->get_settings_and_constraints(),
		) );
	}

	public function settings(): Settings {
		if ( empty( $this->settings ) ) {
			$this->settings = new Settings;
		}

		return $this->settings;
	}

	public function on_settings_save(): void {
		$this->do_save_settings();
	}

	public function do_save_settings( array|null $post_data = null ): void {
		$post_data = (array) ( $post_data ?? $_POST );

		if ( ! wp_verify_nonce( $post_data['nonce'], 'action-scheduler-tools' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
			return;
		}

		$settings = $this->settings()->get_settings();

		foreach ( $post_data as $key => $value ) {
			if ( array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
			}
		}

		$settings = $this->settings()->sanitize( $settings );
		update_option( self::SETTINGS_KEY, $settings );
		wp_send_json_success();
	}

	public function on_delete_finalized(): void {
		$this->do_delete_finalized();
	}

	public function do_delete_finalized( array|null $post_data = null ): void {
		$post_data = (array) ( $post_data ?? $_POST );

		if ( ! wp_verify_nonce( $post_data['nonce'], 'action-scheduler-tools' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
			return;
		}

		wp_send_json_success( [
			'continue'  => $this->delete_finalized_actions(),
			'remaining' => $this->count_remaining_actions_to_be_deleted(),
		] );
	}

	/**
	 * @return bool True if a further call is recommended, false if not.
	 */
	private function delete_finalized_actions(): bool {
		$store   = ActionScheduler_Store::instance();
		$actions = $store->query_actions( [
			'per_page' => 40,
			'status'   => [
				ActionScheduler_Store::STATUS_COMPLETE,
				ActionScheduler_Store::STATUS_CANCELED,
				ActionScheduler_Store::STATUS_FAILED,
			],
		] );

		foreach ( $actions as $action ) {
			try {
				$store->delete_action( $action );
			} catch ( Exception $e ) {
				// An exception may be thrown if the action was already deleted by another process.
			}
		}

		return count( $actions ) === 40;
	}

	private function count_remaining_actions_to_be_deleted(): int {
		return (int) ActionScheduler_Store::instance()->query_actions(
			[
				'per_page' => 40,
				'status'   => [
					ActionScheduler_Store::STATUS_COMPLETE,
					ActionScheduler_Store::STATUS_CANCELED,
					ActionScheduler_Store::STATUS_FAILED,
				],
			],
			'count'
		);
	}


	public function apply_filters(): void {
		( new Filters( $this->settings()->get_settings() ) )->setup();
	}
}
