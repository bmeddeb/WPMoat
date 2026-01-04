/**
 * WP Moat Admin JavaScript
 *
 * @package WPMoat
 */

(function($) {
	'use strict';

	/**
	 * WP Moat Admin Module
	 */
	const WPMoatAdmin = {
		/**
		 * Initialize the admin module.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Module toggle buttons
			$(document).on('click', '.wp-moat-toggle-module', this.toggleModule);

			// Confirm dangerous actions
			$(document).on('click', '.wp-moat-confirm-action', this.confirmAction);

			// Tab navigation
			$(document).on('click', '.wp-moat-tab-link', this.switchTab);
		},

		/**
		 * Toggle a module on/off via AJAX.
		 *
		 * @param {Event} e Click event.
		 */
		toggleModule: function(e) {
			e.preventDefault();

			const $button = $(this);
			const moduleId = $button.data('module');
			const currentState = $button.data('enabled');
			const newState = !currentState;

			$button.prop('disabled', true).text(wpMoat.i18n?.saving || 'Saving...');

			$.ajax({
				url: wpMoat.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_moat_toggle_module',
					nonce: wpMoat.nonce,
					module: moduleId,
					enabled: newState ? 1 : 0
				},
				success: function(response) {
					if (response.success) {
						$button
							.data('enabled', newState)
							.text(newState ? (wpMoat.i18n?.disable || 'Disable') : (wpMoat.i18n?.enable || 'Enable'))
							.toggleClass('button-primary', !newState);

						// Update status indicator
						$button.closest('.wp-moat-module-card')
							.find('.status-indicator')
							.toggleClass('active', newState)
							.toggleClass('inactive', !newState);

						WPMoatAdmin.showNotice(response.data.message, 'success');
					} else {
						WPMoatAdmin.showNotice(response.data.message, 'error');
					}
				},
				error: function() {
					WPMoatAdmin.showNotice(wpMoat.i18n?.error || 'An error occurred. Please try again.', 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Confirm before executing dangerous actions.
		 *
		 * @param {Event} e Click event.
		 */
		confirmAction: function(e) {
			const message = $(this).data('confirm') || 'Are you sure you want to proceed?';

			if (!confirm(message)) {
				e.preventDefault();
			}
		},

		/**
		 * Switch between tabs.
		 *
		 * @param {Event} e Click event.
		 */
		switchTab: function(e) {
			e.preventDefault();

			const $link = $(this);
			const targetId = $link.attr('href');

			// Update active tab link
			$link.closest('.wp-moat-tabs').find('.wp-moat-tab-link').removeClass('nav-tab-active');
			$link.addClass('nav-tab-active');

			// Show target panel
			$('.wp-moat-tab-panel').hide();
			$(targetId).show();
		},

		/**
		 * Show a temporary notice.
		 *
		 * @param {string} message The message to display.
		 * @param {string} type    Notice type (success, error, warning, info).
		 */
		showNotice: function(message, type) {
			type = type || 'info';

			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

			$('.wrap h1').first().after($notice);

			// Auto-dismiss after 5 seconds
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);

			// Make dismissible work
			if (typeof wp !== 'undefined' && wp.updates) {
				wp.updates.addAdminNotice = function() {};
			}
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		WPMoatAdmin.init();
	});

})(jQuery);
