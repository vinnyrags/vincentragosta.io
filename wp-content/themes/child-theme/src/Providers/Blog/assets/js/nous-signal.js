/**
 * Nous Signal — Frontend interactivity
 *
 * Handles the decrypt reveal animation on post cards,
 * light mode resistance, and the console easter egg.
 */
(function () {
  'use strict';

  /**
   * Decrypt reveal: cards show a brief [DECRYPTING...] flash
   * before content appears on scroll into view.
   */
  function initDecryptReveal() {
    const cards = document.querySelectorAll('[data-nous-decrypt]');

    if (!cards.length) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const card = entry.target;
            card.classList.add('is-decrypting');

            setTimeout(() => {
              card.classList.remove('is-decrypting');
              card.classList.add('is-decrypted');
            }, 600);

            observer.unobserve(card);
          }
        });
      },
      { threshold: 0.15 }
    );

    cards.forEach((card) => observer.observe(card));
  }

  /**
   * Light mode resistance: when light mode is toggled on,
   * fire a brief red flash overlay before yielding.
   * Scoped to blog pages (only runs if signal cards exist).
   * Respects prefers-reduced-motion.
   */
  function initLightModeResistance() {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) return;

    const overlay = document.createElement('div');
    overlay.className = 'nous-resistance-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    document.body.appendChild(overlay);

    let isResisting = false;
    let wasLightMode = document.documentElement.classList.contains('light-mode');

    const observer = new MutationObserver(() => {
      const isLightMode = document.documentElement.classList.contains('light-mode');

      // Only fire on dark → light transition
      if (isLightMode && !wasLightMode && !isResisting) {
        isResisting = true;
        overlay.classList.add('is-resisting');

        console.log(
          '%cNOUS: You dare bring light into my domain?',
          'color: #ff3333; font-family: monospace; font-size: 14px;'
        );

        overlay.addEventListener(
          'animationend',
          () => {
            overlay.classList.remove('is-resisting');
            isResisting = false;
          },
          { once: true }
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
    initDecryptReveal();
    initLightModeResistance();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  initConsoleEasterEgg();
})();
