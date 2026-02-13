(function () {
  var root = document.querySelector('[data-crm-view-toggle]');
  var storageKey = root && root.getAttribute('data-storage-key') ? root.getAttribute('data-storage-key') : 'peracrm_clients_view';
  if (!root) {
    return;
  }

  var buttons = Array.prototype.slice.call(root.querySelectorAll('button[data-view]'));
  var tableView = document.querySelector('[data-crm-view="table"]');
  var cardsView = document.querySelector('[data-crm-view="cards"]');

  function applyView(view) {
    var isCards = view !== 'table';

    if (tableView) {
      tableView.classList.toggle('is-hidden', isCards);
    }
    if (cardsView) {
      cardsView.classList.toggle('is-hidden', !isCards);
    }

    buttons.forEach(function (button) {
      var active = button.getAttribute('data-view') === (isCards ? 'cards' : 'table');
      button.classList.toggle('btn--solid', active);
      button.classList.toggle('btn--ghost', !active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  var stored = '';
  try {
    stored = window.localStorage.getItem(storageKey) || '';
  } catch (e) {
    stored = '';
  }

  var initial = stored === 'table' ? 'table' : 'cards';
  applyView(initial);

  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      var view = button.getAttribute('data-view') === 'table' ? 'table' : 'cards';
      applyView(view);
      try {
        window.localStorage.setItem(storageKey, view);
      } catch (e) {}
    });
  });

  var table = document.querySelector('[data-crm-sort-table]') || document.querySelector('.crm-leads-table');
  if (!table) {
    return;
  }

  var tbody = table.querySelector('tbody');
  if (!tbody) {
    return;
  }

  var headers = Array.prototype.slice.call(table.querySelectorAll('th [data-sort]'));
  var state = { key: '', dir: 'asc' };

  function toComparable(row, key) {
    var value = String(row.getAttribute('data-' + key) || '').trim();
    if (key === 'created' || key === 'updated') {
      var n = Number(value);
      if (!Number.isNaN(n) && n > 0) {
        return n;
      }
      var d = Date.parse(value);
      return Number.isNaN(d) ? value.toLowerCase() : d;
    }

    var maybeNumber = Number(value);
    if (value !== '' && !Number.isNaN(maybeNumber) && /\d/.test(value)) {
      return maybeNumber;
    }

    return value.toLowerCase();
  }

  function compare(a, b, key, dir) {
    var left = toComparable(a, key);
    var right = toComparable(b, key);
    var direction = dir === 'desc' ? -1 : 1;

    if (typeof left === 'number' && typeof right === 'number') {
      return (left - right) * direction;
    }

    return String(left).localeCompare(String(right), undefined, { sensitivity: 'base' }) * direction;
  }

  function updateHeaderState(activeKey, dir) {
    headers.forEach(function (button) {
      var th = button.closest('th');
      var indicator = button.querySelector('.peracrm-sort-indicator');
      var isActive = button.getAttribute('data-sort') === activeKey;
      if (th) {
        th.setAttribute('aria-sort', isActive ? (dir === 'desc' ? 'descending' : 'ascending') : 'none');
      }
      if (indicator) {
        indicator.textContent = isActive ? (dir === 'desc' ? '▼' : '▲') : '';
      }
    });
  }

  function sortRows(key) {
    var dir = state.key === key && state.dir === 'asc' ? 'desc' : 'asc';
    state = { key: key, dir: dir };

    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-sort-row], tr[data-name]'));
    rows.sort(function (a, b) {
      return compare(a, b, key, dir);
    });
    rows.forEach(function (row) {
      tbody.appendChild(row);
    });

    updateHeaderState(key, dir);
  }

  headers.forEach(function (button) {
    button.addEventListener('click', function () {
      sortRows(button.getAttribute('data-sort'));
    });
  });

  tbody.addEventListener('click', function (event) {
    var target = event.target;
    if (!target) {
      return;
    }

    if (target.closest('a, button, input, select, textarea')) {
      return;
    }

    var row = target.closest('tr[data-row-url]');
    if (!row) {
      return;
    }

    var url = row.getAttribute('data-row-url');
    if (url) {
      window.location.href = url;
    }
  });
})();
