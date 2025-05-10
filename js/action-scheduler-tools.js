actionSchedulerTools = actionSchedulerTools || {};

( function() {
	const __              = ( text ) => text; //wp.i18n.__( text );
	const escAttr         = ( text ) => text; //wp.escapeHtml.escapeAttribute( '' + text );
	const escHtml         = ( text ) => text; //wp.escapeHtml.escapeHTML( '' + text );

	let saveButton;
	let saveFeedback;
	let saveFeedbackTimeout;

	/**
	 * Let's kick it.
	 */
	function begin() {
		buildDialogsUI();
		buildDrawerUI();

		initDialogsUI();
		initDrawerUI();
	}

	/**
	 * Add our pull-down drawer and drawer handle, alongside the default "Screen Options" and
	 * "Help" drawers.
	 */
	function buildDrawerUI() {
		const screenMeta      = document.getElementById( 'screen-meta' );
		const screenMetaLinks = document.getElementById( 'screen-meta-links' );
		let   controls        = '';

		for ( const key in actionSchedulerTools.settings ) {
			const properties = actionSchedulerTools.settings[key];
			const configKey  = escAttr( key );
			const kebabKey   = escAttr( key.replaceAll('_', '-') );
			const labelClass = escAttr( actionSchedulerTools.settings[key].type );

			controls += `
				<section id="as-tools-${kebabKey}-wrapper">
					<div class="as-tools-enable-disable">
						<label class="${labelClass}">
							<input type="checkbox" data-settings-key="${configKey}_enabled" ${properties.enabled ? 'checked' : ''} />
							<span>
								<strong>${escHtml( properties.name )} </strong> &rarr;
								${escHtml( properties.description )}</span>
						</label>
					</div>
			`;

			if ( properties.type === 'range' ) {
				controls += `
					<div class="as-tools-enabled-disabled">
						<label>
							<input name="as-tools-${kebabKey}" data-settings-key="${configKey}" type="range" min="${escAttr( properties.min )}" max="${escAttr( properties.max )}" value="${escAttr( properties.value )}" />
							<span class="echo-input-value disabled">0</span>
						</label>					
					</div>
				`;
			}

			controls += '</section>';
		}

		const drawer = makeElement(`
			<div id="as-tools-wrap" class="no-sidebar hidden">
				<h3>${escHtml( __( 'Advanced Configuration Tools', 'action-scheduler-tools' ) )}</h3>
				<p>${escHtml( __( 'You can enable and then override various settings via this panel.', 'action-scheduler-tools' ) )}</p>
				
				<div class="as-tools-config-controls">
					${controls}
				</div>
				
				<div class="as-tools-reprioritize-rules">
					<p>${escHtml( __( 'You can add rules here to identify actions by hook, group or both those things and set them to a specific priority. As with WordPress actions, lower numbers like 1 are regarded as a high priority than numbers such as 100. The default is 10.', 'action-scheduler-tools' ) )}</p>
					
					<table id="as-tools-reprioritization-grid">
						<thead>
							<tr>
								<th>${escHtml( __( 'Hook to match', 'action-scheduler-tools' ) )}</th>
								<th>${escHtml( __( 'Group to match', 'action-scheduler-tools' ) )}</th>
								<th>${escHtml( __( 'Priority to match', 'action-scheduler-tools' ) )}</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
				
				<section id="as-tools-save-tool-buttons-wrap">
					<div class="as-control-button left">
						<button id="as-tools-save" class="button-secondary">
							${escHtml(__( 'Save', 'action-scheduler-tools' ))}
						</button>
						<span id="as-tools-save-feedback"></span>
					</div>
					
					<div class="as-control-button right">
						<button id="as-tools-delete-finalized" class="button-secondary">
							${escHtml(__( 'Delete all finalized actions', 'action-scheduler-tools' ))}
							<span id="as-tools-delete-feedback"></span>
						</button>
						
					</div>
				</section>
			</div>
		`);

		const drawerHandle = makeElement(`
			<div id="as-tools-link-wrap" class="hide-if-no-js screen-meta-toggle">
				<button id="as-show-tools-link" class="button show-settings" aria-controls="as-tools-wrap" aria-expanded="false">
					${escHtml( __( 'Advanced', 'action-scheduler-tools' ) )}
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

		if ( setting === null || setting.children.length < 1 ) {
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
		saveFeedback.innerHTML = escHtml( __( 'Saving&hellip;', 'action-scheduler-tools' ) );
		clearTimeout( saveFeedbackTimeout );

		const response = await fetch( actionSchedulerTools.ajaxUrl, {
			method:  'POST',
			body:    buildPayload()
		} );

		const responseJson = await response.json();

		saveButton.disabled = false;
		saveButton.classList.remove( 'disabled' );

		const resultText = response.status === 200 && responseJson.success
			? escHtml( __( 'saved!', 'action-scheduler-tools' ) )
			: escHtml( __( 'unable to save settings&mdash;consider reloading the page and trying again.', 'action-scheduler-tools' ) );

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

	function buildDialogsUI() {
		let dialogs = `
			<dialog id="as-tools-dialog-delete-finalized" class="as-tools-modal">
				<section class="description">
					<p>${escHtml( __( 'This tool will delete all finalized actions (it removes all completed and failed actions).', 'action-scheduler-tools' ) ) }</p>
				</section>
				<section class="choice">
					<p>${escHtml( __( 'Do you want to proceed?', 'action-scheduler-tools' ) ) }</p>
					<div class="choices">
						<button class="button-primary proceed">${escHtml( __( 'Proceed', 'action-scheduler-tools' ) )}</button>
						<button class="button-secondary cancel" autofocus>${escHtml( __( 'Cancel', 'action-scheduler-tools' ) )}</button>
					</div>
				</section>
				<section class="working hidden">
					<p>${escHtml( __( 'Working&hellip;', 'action-scheduler-tools' ) ) }</p>
				</section>
			</dialog>
		`

		document.body.insertAdjacentHTML( 'beforeend', dialogs );
	}

	function initDialogsUI() {
		const deleteFinalized = document.getElementById( 'as-tools-dialog-delete-finalized' );
		const choice          = deleteFinalized.querySelector( 'section.choice' )
		const working         = deleteFinalized.querySelector( 'section.working' )
		const show            = document.getElementById( 'as-tools-delete-finalized' );
		const close           = deleteFinalized.querySelector( 'button.cancel' );
		const proceed         = deleteFinalized.querySelector( 'button.proceed' );

		show.addEventListener( 'click', () => deleteFinalized.showModal() );
		close.addEventListener( 'click', () => { deleteFinalized.close() } );
		proceed.addEventListener( 'click', async () => {
			choice.classList.add( 'hidden' );
			working.classList.remove( 'hidden' );

			while ( true ) {
				const request = new FormData();
				request.append('action', 'action_scheduler_tools_delete_finalized');
				request.append('nonce', actionSchedulerTools.nonce);

				const response = await fetch(actionSchedulerTools.ajaxUrl, {
					method: 'POST',
					body: request
				});

				const responseJson = await response.json();

				// The server may ask us to launch another request if there is more work to be done.
				if ( response.status !== 200 || ! responseJson.data || ! responseJson.data.continue ) {
					break;
				}
			}

			location.reload();
		} );
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
	 * @param {string}   event
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
