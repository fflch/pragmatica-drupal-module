(function (Drupal) {
  'use strict';

  Drupal.behaviors.pragmaticaPanel = {
    attach: function (context, settings) {
      document.addEventListener('DOMContentLoaded', function () {
        highlightSnippetOnHover(document, settings);
      });
    }
  };

  function highlightSnippetOnHover(context, settings) {
    const codingItems = context.querySelectorAll('.pragmatica-coding-item');
    const sourceTextParagraph = context.querySelector('.pragmatica-plain-text');
    const fullText = settings.pragmatica.sourcePlainText || '';

    if (!codingItems.length || !sourceTextParagraph || !fullText) {
      return;
    }

    codingItems.forEach(item => {
      item.addEventListener('mouseenter', function() {
        const start = this.getAttribute('data-start');
        const end = this.getAttribute('data-end');
        const color = setHighlightColor(this.getAttribute('data-color'));

        if (start !== null && end !== null) {
          const highlightedText = fullText.substring(start, end);

          sourceTextParagraph.innerHTML = fullText.substring(0, start) +
            '<span id="current-highlighted-text" style="background-color: ' + color + ';">' + highlightedText + '</span>' +
            fullText.substring(end);

          setTimeout(() => {
            const highlightedElement = document.getElementById('current-highlighted-text');
            if (highlightedElement) {
              highlightedElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          }, 500);
        }
      });

      item.addEventListener('mouseleave', function() {
        sourceTextParagraph.innerText = fullText;
      });
    });
  }

  function setHighlightColor(hexColor= '') {
    const defaultColor = `rgba(255, 255, 0, 0.5)`;

    if (!hexColor || hexColor.toLowerCase().startsWith('#000')) {
      return defaultColor;
    }

    if (hexColor.startsWith('#') && (hexColor.length === 7 || hexColor.length === 4)) {
      let r, g, b;
      if (hexColor.length === 7) {
        r = parseInt(hexColor.slice(1, 3), 16);
        g = parseInt(hexColor.slice(3, 5), 16);
        b = parseInt(hexColor.slice(5, 7), 16);
      } else {
        r = parseInt(hexColor[1] + hexColor[1], 16);
        g = parseInt(hexColor[2] + hexColor[2], 16);
        b = parseInt(hexColor[3] + hexColor[3], 16);
      }
      return `rgba(${r}, ${g}, ${b}, 0.3)`;
    }

    return hexColor;
  }


})(Drupal);
