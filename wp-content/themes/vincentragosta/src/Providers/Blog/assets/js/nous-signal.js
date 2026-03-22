/**
 * Nous Signal — Frontend interactivity
 *
 * Global: nav hover flash, console easter egg.
 * Blog pages only: decrypt reveal, light mode resistance.
 */
(function () {
  'use strict';

  /**
   * Shared resistance flash mechanism.
   * Creates the overlay once and exposes a trigger function.
   */
  function createResistanceFlash() {
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) return null;

    var overlay = document.createElement('div');
    overlay.className = 'nous-resistance-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    document.body.appendChild(overlay);

    var isResisting = false;

    return function flash() {
      if (isResisting) return;

      isResisting = true;
      overlay.classList.add('is-resisting');

      overlay.addEventListener(
        'animationend',
        function () {
          overlay.classList.remove('is-resisting');
          isResisting = false;
        },
        { once: true }
      );
    };
  }

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
   * Light mode resistance: when light mode is toggled on,
   * fire a brief red flash overlay before yielding.
   */
  function initLightModeResistance(flash) {
    var wasLightMode = document.documentElement.classList.contains('light-mode');

    var observer = new MutationObserver(function () {
      var isLightMode = document.documentElement.classList.contains('light-mode');

      if (isLightMode && !wasLightMode) {
        flash();

        console.log(
          '%cNOUS: You dare bring light into my domain?',
          'color: #ff3333; font-family: monospace; font-size: 14px;'
        );
      }

      wasLightMode = isLightMode;
    });

    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class'],
    });
  }

  /**
   * Nav hover flash: hovering over the Nous Signal nav item
   * triggers the resistance flash (global, all pages).
   */
  function initNavHoverFlash(flash) {
    var navItem = document.querySelector('.nous-signal-nav-item a');

    if (!navItem) return;

    navItem.addEventListener('mouseenter', flash);
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

  // Boot
  function init() {
    var flash = createResistanceFlash();
    var isSignalPage = document.body.classList.contains('nous-signal-page');

    // Global
    if (flash) {
      initNavHoverFlash(flash);
    }

    // Blog pages only
    if (isSignalPage) {
      initDecryptReveal();

      if (flash) {
        initLightModeResistance(flash);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  initConsoleEasterEgg();
})();
