(function ($) {
	'use strict';

	var DEBOUNCE_MS = 250;
	var LEGACY_URL_RE = /\/wp-admin\/post\.php\?post=([0-9]+)/;

	function debounce(fn, delay) {
		var timer;
		return function () {
			var ctx  = this;
			var args = arguments;
			clearTimeout(timer);
			timer = setTimeout(function () {
				fn.apply(ctx, args);
			}, delay);
		};
	}

	function bindPicker($picker) {
		var $input    = $picker.find('[data-input]');
		var $results  = $picker.find('[data-results]');
		var $hiddenId = $picker.find('[data-hidden-id]');
		var $preview  = $picker.find('[data-preview]');

		function firePreview(id) {
			if (!id || id <= 0) {
				return;
			}
			$.get(proudDocumentWidget.ajax_url, {
				action:    'proud_document_preview',
				post_id:   id,
				_wpnonce:  proudDocumentWidget.nonce
			}).done(function (resp) {
				if (resp && resp.html) {
					$preview.html(resp.html);
				}
			});
		}

		function showResults(items) {
			$results.empty();
			if (!items || items.length === 0) {
				$results.append(
					$('<li class="proud-doc-no-results"></li>').text(proudDocumentWidget.i18n.no_results)
				);
				return;
			}
			$.each(items, function (i, item) {
				var $li    = $('<li class="proud-doc-result" role="option"></li>').data('id', item.id);
				var $icon  = $('<i aria-hidden="true"></i>').addClass('fa').addClass(item.icon);
				var $title = $('<span class="proud-doc-title"></span>').text(item.title);
				$li.append($icon).append(' ').append($title);
				if (item.filename) {
					$li.append(' ').append(
						$('<small class="proud-doc-filename"></small>').text('(' + item.filename + ')')
					);
				}
				$results.append($li);
			});
		}

		var doSearch = debounce(function () {
			var q = $input.val();
			if (q.length < 2) {
				$results.empty();
				return;
			}
			$.get(proudDocumentWidget.ajax_url, {
				action:   'proud_document_search',
				q:        q,
				_wpnonce: proudDocumentWidget.nonce
			}).done(function (resp) {
				if (resp && resp.items) {
					showResults(resp.items);
				}
			});
		}, DEBOUNCE_MS);

		$input.on('input', doSearch);

		$results.on('click', '.proud-doc-result', function () {
			var id = $(this).data('id');
			$hiddenId.val(id);
			$results.empty();
			$input.val($(this).find('.proud-doc-title').text());
			firePreview(id);
		});

		// On initial bind, if the hidden input already holds a value, show preview.
		var existingValue = $hiddenId.val();
		if (existingValue) {
			var legacyMatch = existingValue.match(LEGACY_URL_RE);
			if (legacyMatch) {
				var extractedId = parseInt(legacyMatch[1], 10);
				$hiddenId.val(extractedId);
				firePreview(extractedId);
			} else {
				var numericId = parseInt(existingValue, 10);
				if (numericId > 0) {
					firePreview(numericId);
				}
			}
		}
	}

	function initAllPickers() {
		$('[data-proud-doc-picker]').each(function () {
			if (!$(this).data('proud-doc-picker-bound')) {
				$(this).data('proud-doc-picker-bound', true);
				bindPicker($(this));
			}
		});
	}

	$(document).ready(function () {
		initAllPickers();
	});

	$(document).on('widget-added widget-updated panelsopen panelsdone', function () {
		initAllPickers();
	});

})(jQuery);
