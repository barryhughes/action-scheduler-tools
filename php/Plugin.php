<?php

namespace Automattic\Chronos\Action_Scheduler_Tools;

class Plugin {
	public const SETTINGS_KEY = 'action_scheduler_tools_settings';

	public function __construct(
		public readonly string $plugin_url,
		public readonly string $version,
	) {}

	public function setup(): void {
		add_action( 'load-tools_page_action-scheduler', array( $this, 'on_action_scheduler_screen' ) );
		add_action( 'wp_ajax_action_scheduler_tools_save_settings', array( $this, 'on_settings_save' ) );
		add_action( 'action_scheduler_init', array( $this, 'apply_filters' ) );
	}

	public function on_action_scheduler_screen(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts(): void {
		wp_enqueue_style( 'action-scheduler-tools', $this->plugin_url . '/css/action-scheduler-tools.css', array(), $this->version );
		wp_enqueue_script( 'action-scheduler-tools', $this->plugin_url . '/js/action-scheduler-tools.js', array(), $this->version );
		wp_localize_script( 'action-scheduler-tools', 'actionSchedulerTools', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'action-scheduler-tools' ),
			'settings' => $this->get_settings(),
		) );
	}

	private function get_settings(): array {
		$defaults = array(
			'batch_size'               => 10,
			'batch_size_enabled'       => false,
			'max_runners'              => 10,
			'max_runners_enabled'      => false,
			'retention_period'         => 10,
			'retention_period_enabled' => false,
		);

		$persisted = (array) get_option( self::SETTINGS_KEY, array() );
		return $this->sanitize_settings(
			array_merge( $defaults, array_intersect_key( $persisted, $defaults ) )
		);
	}

	public function on_settings_save(): void {
		$this->do_save_settings();
	}

	public function do_save_settings( array|null $post_data = null ): void {
		$post_data = (array) ( $post_data ?? $_POST );

		if ( ! wp_verify_nonce( $post_data['nonce'], 'action-scheduler-tools' ) ) {
			wp_send_json_error();
			return;
		}

		$settings = $this->get_settings();

		foreach ( $post_data as $key => $value ) {
			if ( array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
			}
		}

		$settings = $this->sanitize_settings( $settings );
		update_option( self::SETTINGS_KEY, $settings );
		wp_send_json_success();
	}

	private function sanitize_settings( array $settings ): array {
		foreach ( $settings as $key => $value ) {
			if ( str_ends_with( $key, '_enabled' ) ) {
				$settings[ $key ] = $this->boolify( $value );
			} else {
				$settings[ $key ] = (int) $value;
			}
		}

		return $settings;
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

	public function apply_filters(): void {
		( new Filters( $this->get_settings() ) )->setup();
	}
}
