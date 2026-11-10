/**
 * Adds Duplicate Post's Clone and New Draft links, and a Draft label, to the
 * Bold Timelines list. The list table has no row-action filter, so the links
 * (built in PHP by Duplicate Post) arrive in proudBoldTimelineClone.links keyed
 * by timeline ID. See wp-proudcity#3014.
 */
(function () {
  'use strict';

  var data = window.proudBoldTimelineClone;
  if (!data || !data.links) {
    return;
  }

  var labels = data.labels || {};

  function aria(template, title) {
    return String(template || '%s').replace('%s', function () { return title; });
  }

  function makeAction(className, url, text, ariaLabel) {
    var span = document.createElement('span');
    span.className = className;

    var link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('aria-label', ariaLabel);
    link.textContent = text;

    span.appendChild(link);
    return span;
  }

  var rows = document.querySelectorAll('#the-list tr');

  Array.prototype.forEach.call(rows, function (row) {
    var checkbox = row.querySelector('input[name="post[]"]');
    if (!checkbox) {
      return;
    }

    var id = parseInt(checkbox.value, 10);
    var entry = id ? data.links[id] : null;
    if (!entry) {
      return;
    }

    var titleLink = row.querySelector('.row-title');
    var title = titleLink ? titleLink.textContent : '';

    var actions = row.querySelector('.row-actions');
    if (actions) {
      var items = [];
      if (entry.clone) {
        items.push(makeAction('clone', entry.clone, labels.clone, aria(labels.cloneAria, title)));
      }
      if (entry.newDraft) {
        items.push(makeAction('edit_as_new_draft', entry.newDraft, labels.newDraft, aria(labels.newDraftAria, title)));
      }

      items.forEach(function (item) {
        // Core separates row actions with " | " inside the previous span.
        var last = actions.lastElementChild;
        if (last) {
          last.appendChild(document.createTextNode(' | '));
        }
        actions.appendChild(item);
      });
    }

    if (entry.isDraft && titleLink && titleLink.parentNode) {
      var state = document.createElement('span');
      state.className = 'post-state';
      state.textContent = ' — ' + labels.draft;
      titleLink.parentNode.insertBefore(state, titleLink.nextSibling);
    }
  });
})();
