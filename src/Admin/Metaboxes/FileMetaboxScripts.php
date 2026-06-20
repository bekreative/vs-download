<?php
/**
 * Inline admin scripts for the file metabox.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin\Metaboxes;

/**
 * Media uploader and version-row UI for FileMetabox.
 */
final class FileMetaboxScripts {

	/**
	 * @return string JavaScript for wp_add_inline_script.
	 */
	public static function inline_js(): string {
		return "
		jQuery(document).ready(function($){
			var frame;
			var targetInput;

			$(document).on('click', '.lwd-upload-button', function(e) {
				e.preventDefault();
				targetInput = $(this).siblings('.lwd-file-url');

				if ( frame ) {
					frame.open();
					return;
				}
				frame = wp.media({
					title: 'Select or Upload File',
					button: { text: 'Use this file' },
					multiple: false
				});
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					targetInput.val(attachment.url);
				});
				frame.open();
			});

			$('#lwd-add-version').on('click', function(e){
				e.preventDefault();
				var html = '<tr>';
				html += '<td><input type=\"text\" name=\"_lwd_versions[]\" placeholder=\"Version (e.g. 1.0)\" /></td>';
				html += '<td><input type=\"text\" name=\"_lwd_urls[]\" class=\"lwd-file-url\" style=\"width:70%;\" /><button class=\"button lwd-upload-button\">Upload</button></td>';
				html += '<td><button class=\"button lwd-remove-version\">Remove</button></td>';
				html += '</tr>';
				$('#lwd-versions-table tbody').append(html);
			});

			$(document).on('click', '.lwd-remove-version', function(e){
				e.preventDefault();
				$(this).closest('tr').remove();
			});
		});
		";
	}
}
