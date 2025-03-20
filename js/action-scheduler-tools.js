actionSchedulerTools = actionSchedulerTools || {};

( function() {
	const proposedSettings = actionSchedulerTools.settings;

	let saveButton;
	let saveFeedback;
	let saveFeedbackTimeout;

	/**
	 * Let's kick it.
	 */
	function begin() {
		buildDrawerUI();
		initDrawerUI();
	}

	/**
	 * Add our pull-down drawer and drawer handle, alongside the default "Screen Options" and
	 * "Help" drawers.
	 */
	function buildDrawerUI() {
		const screenMeta      = document.getElementById( 'screen-meta' );
		const screenMetaLinks = document.getElementById( 'screen-meta-links' );

		const batchSizeEnabled       = Boolean( actionSchedulerTools.settings.batch_size_enabled );
		const batchSizeSetting       = parseInt( actionSchedulerTools.settings.batch_size, 10 );
		const lockDurationEnabled    = Boolean( actionSchedulerTools.settings.lock_duration_enabled );
		const lockDuration           = parseInt( actionSchedulerTools.settings.lock_duration, 10 );
		const maxRunnersEnabled      = Boolean( actionSchedulerTools.settings.max_runners_enabled );
		const maxRunnersSetting      = parseInt( actionSchedulerTools.settings.max_runners, 10 );
		const retentionPeriodEnabled = Boolean( actionSchedulerTools.settings.retention_period_enabled );
		const retentionPeriodSetting = parseInt( actionSchedulerTools.settings.retention_period, 10 );

		const drawer = makeElement(`
			<div id="as-tools-wrap" class="no-sidebar hidden">
				<h3>${wp.i18n.__( 'Advanced Configuration Tools', 'action-scheduler-tools' )}</h3>
				<p>${wp.i18n.__( 'You can enable and then override various settings via this panel.', 'action-scheduler-tools' )}</p>
				
				<section id="as-tools-batch-size-wrapper">
					<div class="as-tools-enable-disable">
						<label>
							<input type="checkbox" data-settings-key="batch_size_enabled" ${batchSizeEnabled ? 'checked' : ''} />
							<span>
								<strong>${wp.i18n.__( 'Batch Size', 'action-scheduler-tools' )} </strong> &rarr;
								${wp.i18n.__( 'This controls the number of actions that an individual queue runner will attempt to claim per batch.', 'action-scheduler-tools' )}</span>
						</label>
					</div>
					
					<div class="as-tools-enabled-disabled">
						<label>
							<input name="as-tools-batch-size" data-settings-key="batch_size" type="range" min="0" max="40" value="${batchSizeSetting}" />
							<span class="echo-input-value disabled">0</span>
						</label>					
					</div>
				</section>
				
				<section id="as-tools-concurrent-runners-wrapper">
					<div class="as-tools-enable-disable">
						<label>
							<input type="checkbox" data-settings-key="max_runners_enabled" ${maxRunnersEnabled ? 'checked' : ''} />
							<span>
								<strong>${wp.i18n.__( 'Max Queue Runners', 'action-scheduler-tools' )} </strong> &rarr;
								${wp.i18n.__( 'The maximum number of queue runners that should exist and process actions at the same time.', 'action-scheduler-tools' )}
							</span>
						</label>
					</div>
					
					<div class="as-tools-enabled-disabled">
						<label>
							<input name="as-tools-max-runners" data-settings-key="max_runners" type="range" min="0" max="40" value="${maxRunnersSetting}" />
							<span class="echo-input-value disabled">0</span>
						</label>					
					</div>
				</section>
				
				<section id="as-tools-retention-period-wrapper">
					<div class="as-tools-enable-disable">
						<label>
							<input type="checkbox" data-settings-key="retention_period_enabled" ${retentionPeriodEnabled ? 'checked' : ''} />
							<span>
								<strong>${wp.i18n.__( 'Retention Period', 'action-scheduler-tools' )} </strong> &rarr;
								${wp.i18n.__( 'The number of days for which records of completed actions should be retained.', 'action-scheduler-tools' )}
							</span>
						</label>
					</div>
					
					<div class="as-tools-enabled-disabled">
						<label>
							<input name="as-tools-retention-period" data-settings-key="retention_period" type="range" min="0" max="40" value="${retentionPeriodSetting}" />
							<span class="echo-input-value disabled">0</span>
						</label>					
					</div>
				</section>
				
				<section id="as-tools-async-lock-duration-wrapper">
					<div class="as-tools-enable-disable">
						<label>
							<input type="checkbox" data-settings-key="lock_duration_enabled" ${lockDurationEnabled ? 'checked' : ''} />
							<span>
								<strong>${wp.i18n.__( 'Async Lock Duration', 'action-scheduler-tools' )} </strong> &rarr;
								${wp.i18n.__( 'Delay in seconds between the creation of new async queue runners.', 'action-scheduler-tools' )}
							</span>
						</label>
					</div>
					
					<div class="as-tools-enabled-disabled">
						<label>
							<input name="as-tools-lock-duration" data-settings-key="lock_duration" type="range" min="0" max="120" value="${lockDuration}" />
							<span class="echo-input-value disabled">0</span>
						</label>
					</div>
				</section>
				
				<section id="as-tools-save-wrap">
					<button id="as-tools-save" class="button-secondary">${wp.i18n.__( 'Save', 'action-scheduler-tools' )}</button>
					<span id="as-tools-save-feedback"></span>
				</section>
			</div>
		`);

		const drawerHandle = makeElement(`
			<div id="as-tools-link-wrap" class="hide-if-no-js screen-meta-toggle">
				<button id="as-show-tools-link" class="button show-settings" aria-controls="as-tools-wrap" aria-expanded="false">
					${wp.i18n.__( 'Advanced', 'action-scheduler-tools' )}
				</button>
			</div>
		`);

		screenMetaLinks.prepend( drawerHandle );
		screenMeta.prepend( drawer );
	}

	function initDrawerUI() {
		const toolsWrap = document.getElementById( 'as-tools-wrap' );

		toolsWrap.querySelectorAll( 'section .as-tools-enable-disable input' ).forEach( toggleInput => {
			addHandlerAndTrigger( toggleInput, 'change', sectionToggledOnOff );
		} );

		toolsWrap.querySelectorAll( 'section .as-tools-enabled-disabled input' ).forEach( echoedInput => {
			addHandlerAndTrigger( echoedInput, 'input', echoInputValue );
		})

		saveButton   = document.getElementById( 'as-tools-save' );
		saveFeedback = document.getElementById( 'as-tools-save-feedback' );
		saveButton.addEventListener( 'click', onSave );
	}

	function sectionToggledOnOff( event ) {
		const enabled = event.target.checked;
		const setting = event.target.parentElement.parentElement.parentElement.querySelector( '.as-tools-enabled-disabled label' );

		if ( setting.children.length < 1 ) {
			return;
		}

		for (let i = 0; i < setting.children.length; i++ ) {
			setting.children[i].disabled = ! enabled;

			enabled
				? setting.children[i].classList.remove( 'disabled' )
				: setting.children[i].classList.add( 'disabled' );
		}
	}

	function inputChange( event ) {
		echoInputValue( event );
	}

	function echoInputValue( event ) {
		const value     = event.target.value;
		const parent    = event.target.parentElement;
		const echoField = parent.querySelector( '.echo-input-value' );

		if ( echoField ) {
			echoField.innerHTML = value;
		}
	}

	async function onSave() {
		saveButton.disabled = true;
		saveButton.classList.add( 'disabled' );
		saveFeedback.innerHTML = wp.i18n.__( 'Saving&hellip;', 'action-scheduler-tools' );
		clearTimeout( saveFeedbackTimeout );

		const response = await fetch( actionSchedulerTools.ajaxUrl, {
			method:  'POST',
			body:    buildPayload()
		} );

		const responseJson = await response.json();

		saveButton.disabled = false;
		saveButton.classList.remove( 'disabled' );

		const resultText = response.status === 200 && responseJson.success
			? wp.i18n.__( 'saved!', 'action-scheduler-tools' )
			: wp.i18n.__( 'unable to save settings&mdash;consider reloading the page and trying again.', 'action-scheduler-tools' );

		saveFeedback.innerHTML += ` <strong>${resultText}</strong>`;

		saveFeedbackTimeout = setTimeout( () => {
			saveFeedback.innerHTML = '';
		}, 7200 );
	}

	/**
	 * @return {FormData}
	 */
	function buildPayload() {
		const payload = new FormData();

		payload.append( 'action', 'action_scheduler_tools_save_settings' );
		payload.append( 'nonce', actionSchedulerTools.nonce );

		document.querySelectorAll( '#as-tools-wrap input[data-settings-key]' ).forEach( input => {
			value = input.type === 'checkbox' ? input.checked : parseInt( input.value, 10 );
			payload.append( input.dataset.settingsKey, value );
		});

		return payload;
	}

	/**
	 * Converts the HTML string into an actual insertable Element. Assumes that the
	 * HTML describes a structure with a single root/a solitary parent element.
	 *
	 * @param {string} html
	 * @returns {Element}
	 */
	function makeElement( html ) {
		const container     = document.createElement( 'div' );
		container.innerHTML = html.trim();
		return container.firstChild;
	}

	/**
	 *
	 * @param {Element}  element
	 * @param {Event}    event
	 * @param {Function} callable
	 */
	function addHandlerAndTrigger( element, event, callable ) {
		element.addEventListener( event, callable );
		callable( { target: element } );
	}

	// Begin as soon as we are ready...
	( document.readyState === 'complete' || document.readyState === 'interactive' )
		? setTimeout( begin, 1 )
		: document.addEventListener( 'DOMContentLoaded', begin );
} )();