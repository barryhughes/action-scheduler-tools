<?php
/**
 * Plugin name:       Action Scheduler Tools
 * Description:       Adds visual controls making it easier to fine-tune Action Scheduler's performance characteristics. Experimental.
 * Version:           0.1.0
 * Author:            Automattic
 * Author URI:        https://woocommerce.com
 * License:           GPL-3.0
 * Requires PHP:      8.4
 */

namespace Automattic\Chronos\Action_Scheduler_Tools;

function setup(): void {
	require __DIR__ . '/php/Filters.php';
	require __DIR__ . '/php/Plugin.php';
	require __DIR__ . '/php/Settings.php';
}

function plugin(): Plugin {
	static $plugin;

	if ( empty( $plugin ) ) {
		$plugin = new Plugin( plugin_dir_url( __FILE__ ), '0.1.0' );
		$plugin->setup();
	}

	return $plugin;
}

add_action( 'plugins_loaded', function () {
	setup();
	plugin();
} );
