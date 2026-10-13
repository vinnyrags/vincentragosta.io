/**
 * Nous Signal — Frontend interactivity
 *
 * Global: console easter egg.
 * Blog pages only: decrypt reveal.
 */
(function () {
  'use strict';

  /**
   * Decrypt reveal: cards show a brief [DECRYPTING...] flash
   * before content appears on scroll into view.
   */
  function initDecryptReveal() {
    var cards = document.querySelectorAll('[data-nous-decrypt]');

    if (!cards.length) return;

    var STAGGER_DELAY = 150; // ms between each card
    var DECRYPT_DURATION = 600;

    var observer = new IntersectionObserver(
      function (entries) {
        var visible = entries.filter(function (e) { return e.isIntersecting; });

        visible.forEach(function (entry, index) {
          var card = entry.target;
          var delay = index * STAGGER_DELAY;

          setTimeout(function () {
            card.classList.add('is-decrypting');

            setTimeout(function () {
              card.classList.remove('is-decrypting');
              card.classList.add('is-decrypted');
            }, DECRYPT_DURATION);
          }, delay);

          observer.unobserve(card);
        });
      },
      { threshold: 0.15 }
    );

    cards.forEach(function (card) { observer.observe(card); });
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

  /**
   * Desktop-only check — flash effects are disabled on mobile.
   */
  function isDesktop() {
    return window.matchMedia('(min-width: 992px)').matches;
  }

  // Boot
  function init() {
    var isSignalPage = document.body.classList.contains('nous-signal-page');

    if (isDesktop()) {
      // Blog pages only
      if (isSignalPage) {
        initDecryptReveal();
      }
    } else if (isSignalPage) {
      // On mobile, skip decrypt animation — show cards immediately
      document.querySelectorAll('[data-nous-decrypt]').forEach(function (card) {
        card.classList.add('is-decrypted');
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  initConsoleEasterEgg();
})();
