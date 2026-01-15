{*
 * 2007-2024 PrestaShop
 * License: Academic Free License (AFL 3.0)
 * Author: PrestaShop SA
 *}
{assign var='select_boxnow_locker_error' value={l s='Please select BoxNow Locker' mod='boxnow'}}
{assign var='locker_label' value={l s='Locker' mod='boxnow'}}

<div class="delivery-option" id="boxnow-map-container">
	{if $boxnow_map_mode == 'popup'}
		<div id="boxnowmap-popup"></div>
		<div id="boxnow-popup-content-wrapper">
			<div class="boxnow-selected-locker">
				{assign var="address_full" value=""}
				{if $boxnow_selected_entry->locker_address}
					{assign var="address_full" value=$boxnow_selected_entry->locker_address}
				{/if}
				{if $boxnow_selected_entry->locker_post_code}
					{if $address_full != ""}
						{assign var="address_full" value=$address_full|cat:", "}
					{/if}
					{assign var="address_full" value=$address_full|cat:$boxnow_selected_entry->locker_post_code}
				{/if}

				{if isset($boxnow_selected_entry->locker_id) && $boxnow_selected_entry->locker_id != ''}
					<div class="boxnow-selected-locker">
						<div class="selected-boxnow">
							<strong>{$locker_label}:</strong><br>
							{$boxnow_selected_entry->locker_name}<br>
							{$address_full}
						</div>
					</div>
				{/if}

			</div>

			<button class="btn btn-primary float-xs-right boxnow-map-widget-button"
				style="background-color: {if empty($boxnow_button_color)}#84C33F{else}{$boxnow_button_color}{/if};"
				id="boxnow-map-button">
				{if empty($boxnow_button_text)}Pick a locker{else}{$boxnow_button_text}{/if}
			</button>
		</div>
	{else}
		<div class="boxnow-pickup-map" style="width:100%">
			<div class="boxnow-selected-locker"></div>
			<div class="gmap_canvas" style="overflow:hidden;background:none !important;height:700px;width:100%;">
				<div id="boxnowmap-inline" style="height:100%; width:100%;"></div>
			</div>
		</div>
	{/if}

	<input type="hidden" class="boxnow-id" value="{$boxnow_selected_entry->locker_id|escape:'htmlall':'UTF-8'}">
	<input type="hidden" class="boxnow-name" value="{$boxnow_selected_entry->locker_name|escape:'htmlall':'UTF-8'}">
	<input type="hidden" class="boxnow-address"
		value="{$boxnow_selected_entry->locker_address|escape:'htmlall':'UTF-8'}">
	<input type="hidden" class="boxnow-zip" value="{$boxnow_selected_entry->locker_post_code|escape:'htmlall':'UTF-8'}">

	<script type="text/javascript">
		// CRITICAL: Define config FIRST before any initialization calls
		window._bn_map_widget_config = {
			type: "{$boxnow_map_mode|escape:'htmlall':'UTF-8'}",
			gps: true,
			partnerId: {$boxnow_partner_id},
			parentElement: "#{if $boxnow_map_mode == 'popup'}boxnowmap-popup{else}boxnowmap-inline{/if}",
			autoclose: {if $boxnow_map_mode == 'popup'}true{else}false{/if},
			autoshow: {if $boxnow_map_mode != 'popup'}true{else}false{/if},
			autoselect: {if $boxnow_map_mode != 'popup'}true{else}false{/if},
			afterSelect: function(selected) {
				const endpoint = '{$boxnow_select_endpoint nofilter}';
				const cartId = '{$boxnow_id_cart}';
				const text = selected.boxnowLockerName + '<br/>' + selected.boxnowLockerAddressLine1 + ', ' + selected
					.boxnowLockerPostalCode;

				$('.boxnow-id').val(selected.boxnowLockerId);
				$('.boxnow-text').val(text);
				$('.boxnow-name').val(selected.boxnowLockerName);
				$('.boxnow-address').val(selected.boxnowLockerAddressLine1);
				$('.boxnow-zip').val(selected.boxnowLockerPostalCode);

				if (selected.boxnowLockerId) {
					$.ajax({
						url: endpoint,
						type: 'POST',
						data: {
							'boxnow_selected': "true",
							'boxnow_id': selected.boxnowLockerId,
							'boxnow_cart_id': cartId,
							'boxnow_locker_id': selected.boxnowLockerId,
							'boxnow_locker_name': selected.boxnowLockerName,
							'boxnow_locker_address': selected.boxnowLockerAddressLine1,
							'boxnow_locker_post_code': selected.boxnowLockerPostalCode,
						},
						dataType: 'json',
						success: function(response) {
							if (response.status === "success") {
								$('.boxnow-selected-locker').html('<div class="selected-boxnow"><strong>{$locker_label}:<br/></strong>' + text + '</div>');
							}
						}
					});
				}
			}
		};

		// Also keep the local variable for backward compatibility
		var _bn_map_widget_config = window._bn_map_widget_config;

		// BOXNOW widget initialization function (defined globally to prevent redefinition)
		if (typeof window.initBoxNowWidget === 'undefined') {
			// Debounce timer to prevent multiple simultaneous initializations
			window._boxnow_init_timer = null;

			window.initBoxNowWidget = function(forceReload) {
				// DEBOUNCE: Clear any pending initialization
				if (window._boxnow_init_timer) {
					clearTimeout(window._boxnow_init_timer);
				}

				// DEBOUNCE: Schedule initialization after 300ms
				window._boxnow_init_timer = setTimeout(function() {
					// Step 1: Cleanup old event listeners
					jQuery('#js-delivery .continue').off('click.boxnow');

					// Step 2: Add validation listener
					jQuery('#js-delivery .continue').on('click.boxnow', function() {
						if ($('.boxnow-zip').val() === '' && $('#boxnow-map-container').is(":visible")) {
							$('.boxnow-selected-locker').html('<div class="select-boxnow-locker error">{$select_boxnow_locker_error}</div>');
							const offset = 100;
							const target = $('.boxnow-selected-locker');
							$('html, body').stop().animate({
								'scrollTop': $(target).offset().top - offset
							}, 700, 'swing');
							return false;
						}
					});

					if (typeof window._bn_map_widget_config === 'undefined') {
						return;
					}

					// Step 3: Clean up old widgets/iframes if force reload is requested
					if (forceReload === true) {
						// Remove old iframes
						$('#boxnowmap-popup iframe, #boxnowmap-inline iframe').remove();

						// Clear the containers completely
						$('#boxnowmap-popup, #boxnowmap-inline').empty();

						// Remove the external widget script and force reload
						const oldScript = document.querySelector('script[src*="widget-cdn.boxnow.gr"]');
						if (oldScript) {
							oldScript.remove();
						}

						// Reset any BoxNow global objects
						if (typeof window.BoxNowWidget !== 'undefined') {
							delete window.BoxNowWidget;
						}
						if (typeof window.BoxNowMapWidget !== 'undefined') {
							delete window.BoxNowMapWidget;
						}
					}

					// Step 4: Load widget script (always reload if forced)
					const scriptExists = document.querySelector('script[src*="widget-cdn.boxnow.gr"]');

					if (!scriptExists || forceReload === true) {
						(function(d) {
							const e = d.createElement("script");
							e.src = "https://widget-cdn.boxnow.gr/map-widget/client/v5.js?v=" + Date.now();
							e.async = true;
							e.defer = false;
							d.getElementsByTagName("head")[0].appendChild(e);

							// Wait for script to load and initialize
							e.onload = function() {
								setTimeout(function() {
									// Try to trigger widget initialization
									if (window.BoxNowMapWidget && typeof window.BoxNowMapWidget.init === 'function') {
										window.BoxNowMapWidget.init(window._bn_map_widget_config);
									}
								}, 200);
							};
						})(document);
					}

					window._boxnow_init_timer = null;
				}, 300);
			};
		}

		// Execute on standard PrestaShop checkout (register only once)
		if (typeof window._boxnow_load_registered === 'undefined') {
			window._boxnow_load_registered = true;
			window.addEventListener("load", function() {
				window.initBoxNowWidget(false);
			});
		}

		// Execute on One Page Checkout modules (register only once)
		if (typeof prestashop !== 'undefined' && typeof prestashop.on === 'function') {
			if (typeof window._boxnow_opc_registered === 'undefined') {
				window._boxnow_opc_registered = true;

				prestashop.on('opc-shipping-getCarrierList-complete', function(data) {
					if ($('#boxnow-map-container').length > 0) {
						window.initBoxNowWidget(true);
					}
				});
			}
		}

		// Only call initialization inline on the FIRST render
		// Subsequent renders are handled by OPC events
		if ($('#boxnow-map-container').length > 0) {
			if (typeof window._boxnow_ever_initialized === 'undefined') {
				window._boxnow_ever_initialized = true;
				window.initBoxNowWidget(false);
			}
		}
	</script>

	{literal}
		<style>
			.selected-boxnow,
			.select-boxnow-locker.error {
				padding: 15px;
				background: #fff;
				margin: 15px;
			}

			.select-boxnow-locker.error {
				color: #cc0000;
				font-weight: 700;
			}

			#boxnow_close_all {
				z-index: 99999 !important;
			}

			.boxnow-map-widget-button {
				height: auto !important;
				max-height: 38px;
			}

			.boxnow-selected-locker {
				width: 80%!important;
			}

			#boxnowmap-popup iframe,
			#boxnowmap-inline iframe {
				z-index: 99999;
			}
			.carrier__extra-content-wrapper.carrier__extra-content-wrapper--active.js-carrier-extra-content {
    max-height: none !important;
}.boxnow-popup-content-wrapper{
	align-items:baseline;
}
		</style>
	{/literal}
</div>