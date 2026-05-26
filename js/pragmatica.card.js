(function (Drupal) {
  'use strict';

  /**
   * Behaviour: hover a label badge → highlight corresponding text ranges.
   * Click the pin icon → keep the highlight fixed until un-pinned.
   * Multiple active (hovered + pinned) labels are combined with striped colours
   * when their ranges overlap.
   */
  Drupal.behaviors.pragmaticaCard = {
    attach: function (context) {
      var badges = context.querySelectorAll('.pragmatica-label-badge:not([data-card-init])');
      Array.prototype.forEach.call(badges, function (badge) {
        badge.setAttribute('data-card-init', '1');

        var container = findContainer(badge);
        var textEl = container ? container.querySelector('.pragmatica-response-text') : null;
        if (!textEl) return;

        // Cache the original plain text once so we can always restore correctly.
        if (!textEl.dataset.originalText) {
          textEl.dataset.originalText = textEl.textContent;
        }

        badge.addEventListener('mouseenter', function () {
          renderHighlights(textEl, container, badge);
        });

        badge.addEventListener('mouseleave', function () {
          renderHighlights(textEl, container, null);
        });

        var pinBtn = badge.querySelector('.pragmatica-pin-btn');
        if (pinBtn) {
          pinBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var nowPinned = badge.dataset.pinned !== '1';
            badge.dataset.pinned = nowPinned ? '1' : '0';
            pinBtn.classList.toggle('pinned', nowPinned);
            pinBtn.setAttribute('aria-pressed', nowPinned ? 'true' : 'false');
            // Re-render using the current hover state (badge may still be hovered).
            renderHighlights(textEl, container, badge.matches(':hover') ? badge : null);
          });
        }
      });

      // Auto-pin badges whose label ID is listed in a [data-autopins].
      var autopinContainers = context.querySelectorAll('[data-autopins]');
      Array.prototype.forEach.call(autopinContainers, function (wrapper) {
        var ids = (wrapper.getAttribute('data-autopins') || '').split(',').filter(Boolean);
        if (!ids.length) return;
        var autoBadges = wrapper.querySelectorAll('.pragmatica-label-badge[data-label-id]');
        Array.prototype.forEach.call(autoBadges, function (badge) {
          if (ids.indexOf(badge.getAttribute('data-label-id')) === -1) return;
          if (badge.hasAttribute('data-pinned')) return; // already interacted
          badge.dataset.pinned = '1';
          var pinBtn = badge.querySelector('.pragmatica-pin-btn');
          if (pinBtn) {
            pinBtn.classList.add('pinned');
            pinBtn.setAttribute('aria-pressed', 'true');
          }
          var cardContainer = findContainer(badge);
          var textEl = cardContainer ? cardContainer.querySelector('.pragmatica-response-text') : null;
          if (textEl) {
            if (!textEl.dataset.originalText) {
              textEl.dataset.originalText = textEl.textContent;
            }
            renderHighlights(textEl, cardContainer, null);
          }
        });
      });
    }
  };

  // ---------------------------------------------------------------------------
  // DOM helpers
  // ---------------------------------------------------------------------------

  function findContainer(badge) {
    return badge.closest('.prag-card') || badge.closest('.pragmatica-response-container');
  }

  // ---------------------------------------------------------------------------
  // Highlight rendering
  // ---------------------------------------------------------------------------

  /**
   * Collect all colour ranges from pinned badges + the currently hovered badge,
   * then apply them to textEl.
   *
   * @param {Element} textEl       - the .pragmatica-response-text element
   * @param {Element} container    - the enclosing .card / .pragmatica-response-container
   * @param {Element|null} hovered - the badge being hovered (null = none)
   */
  function renderHighlights(textEl, container, hovered) {
    var originalText = textEl.dataset.originalText || textEl.textContent;
    var colorRanges = collectColorRanges(container, hovered, originalText);

    if (!colorRanges.length) {
      textEl.textContent = originalText;
      return;
    }

    textEl.innerHTML = buildHighlightedHtml(originalText, colorRanges);
  }

  /**
   * Returns [{start, end, color}] for all active (pinned or hovered) badges.
   * Selection positions from the server are UTF-8 byte offsets; they are
   * converted to JS string (UTF-16) char indices before being returned.
   */
  function collectColorRanges(container, hovered, originalText) {
    var ranges = [];
    var badges = container.querySelectorAll('.pragmatica-label-badge');
    Array.prototype.forEach.call(badges, function (badge) {
      var active = badge.dataset.pinned === '1' || badge === hovered;
      if (!active) return;

      var selections;
      try {
        selections = JSON.parse(badge.getAttribute('data-selections') || '[]');
      } catch (e) {
        return;
      }

      var color = badge.getAttribute('data-color') || '#ffff00';
      selections.forEach(function (sel) {
        var start = utf8ByteOffsetToCharIndex(originalText, sel.start);
        var end   = utf8ByteOffsetToCharIndex(originalText, sel.end);
        if (end > start) {
          ranges.push({ start: start, end: end, color: color });
        }
      });
    });
    return ranges;
  }

  /**
   * Convert a UTF-8 byte offset to a JavaScript string char index.
   * Needed because positions are stored as byte offsets in the database
   * while JS strings are UTF-16 (each CJK/ideographic char = 3 bytes but
   * 1 JS char; emoji = 4 bytes but 2 JS chars / 1 code point).
   */
  function utf8ByteOffsetToCharIndex(str, byteOffset) {
    if (byteOffset <= 0) return 0;
    var bytes = 0;
    for (var i = 0; i < str.length; ) {
      if (bytes >= byteOffset) return i;
      var code = str.codePointAt(i);
      if      (code < 0x80)    { bytes += 1; }
      else if (code < 0x800)   { bytes += 2; }
      else if (code < 0x10000) { bytes += 3; }
      else                     { bytes += 4; i++; } // surrogate pair: extra JS code unit
      i++;
    }
    return str.length;
  }

  // ---------------------------------------------------------------------------
  // HTML construction with multi-colour overlap support
  // ---------------------------------------------------------------------------

  /**
   * Build HTML for `text` where each character position covered by one or more
   * ranges in `colorRanges` is wrapped in a <mark> with the appropriate colour.
   * Overlapping ranges receive a striped gradient.
   */
  function buildHighlightedHtml(text, colorRanges) {
    // Build boundary events.
    var events = [];
    colorRanges.forEach(function (range, i) {
      events.push({ pos: range.start, type: 'start', color: range.color, id: i });
      events.push({ pos: range.end,   type: 'end',   color: range.color, id: i });
    });

    // Sort: ascending position; at same position, ends before starts so a
    // colour that ends exactly where another begins does not bleed.
    events.sort(function (a, b) {
      if (a.pos !== b.pos) return a.pos - b.pos;
      if (a.type === 'end'   && b.type === 'start') return -1;
      if (a.type === 'start' && b.type === 'end')   return  1;
      return 0;
    });

    // Collect unique boundary positions.
    var positions = [];
    events.forEach(function (e) {
      if (positions.indexOf(e.pos) === -1) positions.push(e.pos);
    });
    positions.sort(function (a, b) { return a - b; });

    var html = '';
    var pos  = 0;
    var activeColors = {}; // id → color

    positions.forEach(function (boundary) {
      // Render the segment [pos, boundary) with the CURRENT active colours.
      if (boundary > pos) {
        var len     = Math.min(boundary, text.length);
        var segment = escapeHtml(text.substring(pos, len));
        var colors  = Object.keys(activeColors).map(function (k) { return activeColors[k]; });
        if (colors.length) {
          html += '<mark style="' + buildBgStyle(colors) + '">' + segment + '</mark>';
        } else {
          html += '<span class="pragmatica-dim">' + segment + '</span>';
        }
      }

      // Update active colours with events AT this boundary.
      events.forEach(function (e) {
        if (e.pos !== boundary) return;
        if (e.type === 'start') {
          activeColors[e.id] = e.color;
        } else {
          delete activeColors[e.id];
        }
      });

      pos = boundary;
    });

    // Append any trailing text after the last boundary (always un-highlighted).
    if (pos < text.length) {
      html += '<span class="pragmatica-dim">' + escapeHtml(text.substring(pos)) + '</span>';
    }

    return html;
  }

  // ---------------------------------------------------------------------------
  // Colour helpers
  // ---------------------------------------------------------------------------

  /**
   * Build a CSS background value for one or more hex colours.
   * Single colour → semi-transparent solid.
   * Multiple      → diagonal stripes.
   */
  function buildBgStyle(colors) {
    if (colors.length === 1) {
      return 'background:' + hexToRgba(colors[0], 0.4) + ';padding:0 1px;border-radius:2px;';
    }
    var sw = 5; // stripe width in px
    var stops = [];
    colors.forEach(function (c, i) {
      var rgba = hexToRgba(c, 0.6);
      stops.push(rgba + ' ' + (i * sw) + 'px');
      stops.push(rgba + ' ' + ((i + 1) * sw) + 'px');
    });
    return 'background:repeating-linear-gradient(45deg,' + stops.join(',') + ');padding:0 1px;border-radius:2px;';
  }

  function hexToRgba(hex, alpha) {
    if (!hex || hex[0] !== '#') return 'rgba(255,255,0,' + alpha + ')';
    var r, g, b;
    if (hex.length === 7) {
      r = parseInt(hex.slice(1, 3), 16);
      g = parseInt(hex.slice(3, 5), 16);
      b = parseInt(hex.slice(5, 7), 16);
    } else if (hex.length === 4) {
      r = parseInt(hex[1] + hex[1], 16);
      g = parseInt(hex[2] + hex[2], 16);
      b = parseInt(hex[3] + hex[3], 16);
    } else {
      return 'rgba(255,255,0,' + alpha + ')';
    }
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  function escapeHtml(str) {
    return str
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;')
      .replace(/'/g,  '&#039;');
  }

})(Drupal);
