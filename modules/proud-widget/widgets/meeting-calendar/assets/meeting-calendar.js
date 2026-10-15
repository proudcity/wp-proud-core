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
				eventSources: [{
					url: config.ajaxUrl,
					data: {
						action: config.action,
						categories: (config.categories || []).join(',')
					}
				}]
			});
		});
	});
})(jQuery);
