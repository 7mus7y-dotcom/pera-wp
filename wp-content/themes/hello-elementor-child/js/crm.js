(function () {
  var storageKey = 'peraCrmLeadsView';
  var root = document.querySelector('[data-crm-view-toggle]');
  if (!root) {
    return;
  }

  var buttons = root.querySelectorAll('button[data-view]');
  var tableView = document.querySelector('[data-crm-view="table"]');
  var cardsView = document.querySelector('[data-crm-view="cards"]');

  function applyView(view) {
    var isCards = view === 'cards';

    if (tableView) {
      tableView.style.display = isCards ? 'none' : '';
    }
    if (cardsView) {
      cardsView.style.display = isCards ? '' : 'none';
    }

    buttons.forEach(function (button) {
      var active = button.getAttribute('data-view') === view;
      button.classList.toggle('btn--solid', active);
      button.classList.toggle('btn--ghost', !active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  var mobile = window.matchMedia('(max-width: 1024px)').matches;
  var defaultView = mobile ? 'cards' : 'table';
  var stored = '';

  try {
    stored = window.localStorage.getItem(storageKey) || '';
  } catch (e) {
    stored = '';
  }

  var initial = stored === 'cards' || stored === 'table' ? stored : defaultView;
  applyView(initial);

  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      var view = button.getAttribute('data-view') === 'cards' ? 'cards' : 'table';
      applyView(view);
      try {
        window.localStorage.setItem(storageKey, view);
      } catch (e) {
      }
    });
  });
})();
