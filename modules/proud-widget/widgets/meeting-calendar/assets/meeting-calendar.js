/**
 * Meeting Calendar widget (issue #2768).
 *
 * Initializes every .proud-meeting-calendar on the page independently --
 * unlike wp-fullcalendar's own inline.js, which only ever initializes the
 * first .wpfc-calendar on a page via a static global (WPFC.data,
 * self::$args). Reading config from each element's own data-calendar
 * attribute, rather than a global, is what lets more than one Meeting
 * Calendar -- or a Meeting Calendar alongside the Events "Calendar" widget --
 * coexist on one page.
 *
 * Never uses the .wpfc-calendar class: that class is what inline.js's
 * $('.wpfc-calendar').first() selector matches, and sharing it here would
 * make this widget invisible to that selector or, worse, collide with it.
 */
(function ($) {
	'use strict';

	$(function () {
		// Shows each category's color as a swatch beside its name, like the
		// Events calendar's filter. Unlike wp-fullcalendar's version (which
		// smuggles "#hex - " into the label and feeds it to .html()), this
		// builds DOM nodes: the name only ever goes in as text and the color
		// only as a validated #RRGGBB background.
		if ($.fn.selectmenu && !$.fn.proudMeetingSelectmenu) {
			var decorate = function ($target, item) {
				var label = String(item.label || '');
				var indent = label.match(/^ */)[0];
				var color = String(item.element.attr('data-color') || '');

				$target.empty().append(document.createTextNode(indent));
				if (item.value !== '') {
					var $swatch = $('<span class="proud-meeting-calendar-swatch" aria-hidden="true"></span>');
					if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
						$swatch.css('background-color', color);
					}
					$target.append($swatch);
				}
				$target.append(document.createTextNode(label.slice(indent.length)));
				return $target;
			};

			$.widget('proud.proudMeetingSelectmenu', $.ui.selectmenu, {
				_renderItem: function (ul, item) {
					var $li = $('<li>');
					if (item.disabled) {
						$li.addClass('ui-state-disabled');
					}
					return $li.append(decorate($('<div>'), item)).appendTo(ul);
				},
				_renderButtonItem: function (item) {
					return decorate($('<span class="ui-selectmenu-text"></span>'), item);
				}
			});
		}

		// Built per call so each calendar owns its source object.
		function eventSource(config, categories) {
			return {
				url: config.ajaxUrl,
				data: {
					action: config.action,
					categories: categories.join(',')
				}
			};
		}

		$('.proud-meeting-calendar').each(function () {
			var $calendar = $(this);
			var config;

			try {
				config = JSON.parse($calendar.attr('data-calendar') || '{}');
			} catch (e) {
				return;
			}

			if (!config.ajaxUrl || !config.action) {
				return;
			}

			// FullCalendar escapes event.title/event.url itself when it
			// renders them (see fullcalendar.js util_1.htmlEscape()), so the
			// title/start/url strings the feed returns are never passed
			// through .html() here.
			$calendar.fullCalendar({
				timeFormat: config.timeFormat,
				defaultView: config.defaultView,
				weekends: config.weekends,
				firstDay: config.firstDay,
				header: config.header,
				// ignoreTimezone on the event source only matters when this is
				// anything other than false, so it's left off below rather than
				// carried as dead config.
				timezone: false,
				editable: false,
				eventSources: [eventSource(config, config.categories || [])]
			});

			// Per-instance category filter: swaps this calendar's event source only,
			// so other calendars on the page are untouched. The value is read with
			// .val() and only ever passed on as a digits-only id.
			var $filter = $calendar.closest('.proud-meeting-calendar-wrapper').find('.proud-meeting-calendar-filter');
			if (!$filter.length) {
				return;
			}

			function applyFilter() {
				var selected = String($filter.val() || '');
				var categories = /^\d+$/.test(selected) ? [selected] : (config.categories || []);

				$calendar.fullCalendar('removeEventSources');
				$calendar.fullCalendar('addEventSource', eventSource(config, categories));
			}

			// Match the Events calendar's category filter: same spot (right
			// under the toolbar) and the same jQuery UI selectmenu that
			// wp-fullcalendar's inline.js uses. Its own select.wpfc-taxonomy
			// class is not reused, because inline.js binds to that selector.
			var $toolbar = $calendar.find('.fc-toolbar').first();
			if ($toolbar.length) {
				$('<div class="proud-meeting-calendar-search"></div>')
					.append($filter.closest('label'))
					.insertAfter($toolbar);
			}

			if ($.fn.proudMeetingSelectmenu) {
				$filter.proudMeetingSelectmenu({ change: applyFilter });
			} else {
				$filter.on('change', applyFilter);
			}
		});
	});
})(jQuery);
