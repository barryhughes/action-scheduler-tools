<?php

namespace Automattic\Chronos\Action_Scheduler_Tools;

class Reprioritizer extends Modifier {
	protected function modify_action( $short_circuit, string $function, $hook, $args, $group, $priority, $unique = null, $timestamp = null, $scheduling = null ) {
		return;
	}
}
