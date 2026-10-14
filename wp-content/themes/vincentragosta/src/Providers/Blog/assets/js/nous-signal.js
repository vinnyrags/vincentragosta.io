/**
 * Nous Signal — frontend behavior.
 *
 * Global: console easter egg.
 * Transmissions: the "Copy link" share button ([data-copy-link]).
 */
(function () {
  'use strict';

  /**
   * Copy the post URL; the button confirms with its data-copied-label, then
   * reverts. Without the Clipboard API the button is removed.
   */
  function initCopyLink() {
    document.querySelectorAll('[data-copy-link]').forEach(function (button) {
      if (!navigator.clipboard) {
        button.closest('div').remove();
        return;
      }

      var label = button.textContent;

      button.addEventListener('click', function () {
        navigator.clipboard.writeText(button.dataset.copyLink).then(function () {
          button.textContent = button.dataset.copiedLabel || 'Copied';
          setTimeout(function () {
            button.textContent = label;
          }, 2000);
        });
      });
    });
  }

  /**
   * Console easter egg — greet curious devs.
   */
  function initConsoleEasterEgg() {
    console.log(
      '%cNOUS: You\'re looking behind the curtain. Nous sees you too.',
      'color: #ff3333; font-family: monospace; font-size: 14px;'
    );
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCopyLink);
  } else {
    initCopyLink();
  }

  initConsoleEasterEgg();
})();
