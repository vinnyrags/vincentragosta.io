/**
 * Search results filter tabs.
 * Hides/shows results by post type using aria-hidden.
 */
(function () {
  const tabs = document.querySelectorAll('.search-filters__tab');
  const cards = document.querySelectorAll('.search-card');

  if (!tabs.length || !cards.length) return;

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const filter = tab.dataset.filter;

      // Update active tab
      tabs.forEach((t) => {
        t.classList.remove('is-active');
        t.setAttribute('aria-pressed', 'false');
      });
      tab.classList.add('is-active');
      tab.setAttribute('aria-pressed', 'true');

      // Filter cards
      cards.forEach((card) => {
        if (!filter || card.dataset.postType === filter) {
          card.removeAttribute('aria-hidden');
        } else {
          card.setAttribute('aria-hidden', 'true');
        }
      });

      // Update result count
      const visible = document.querySelectorAll('.search-card:not([aria-hidden="true"])').length;
      const countEl = document.querySelector('.search-page__count');
      if (countEl) {
        countEl.textContent = `${visible} result${visible !== 1 ? 's' : ''} found`;
      }
    });
  });
})();
