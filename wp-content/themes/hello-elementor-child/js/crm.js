(function () {
  var blocks = Array.prototype.slice.call(document.querySelectorAll('.archive-hero-desc'));
  if (!blocks.length) {
    return;
  }

  function updateButtonState(block, buttons, isCollapsed) {
    var sampleButton = buttons.length ? buttons[0] : null;
    if (!sampleButton) {
      return;
    }

    var moreLabel = sampleButton.getAttribute('data-label-more') || 'Read more';
    var lessLabel = sampleButton.getAttribute('data-label-less') || 'Read less';
    block.setAttribute('data-collapsed', isCollapsed ? 'true' : 'false');

    buttons.forEach(function (button) {
      button.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
      button.textContent = isCollapsed ? moreLabel : lessLabel;
    });
  }

  function updateToggleVisibility(block, content, buttons) {
    var needsToggle = content.scrollHeight > content.clientHeight + 8;
    var isCollapsed = block.getAttribute('data-collapsed') !== 'false';

    buttons.forEach(function (button) {
      var isBottomToggle = button.classList.contains('archive-hero-desc__toggle--bottom');
      if (!needsToggle) {
        button.hidden = true;
        return;
      }

      if (isBottomToggle) {
        button.hidden = isCollapsed;
        return;
      }

      button.hidden = false;
    });
  }

  blocks.forEach(function (block) {
    var content = block.querySelector('.archive-hero-desc__content');
    var buttons = Array.prototype.slice.call(block.querySelectorAll('.archive-hero-desc__toggle'));
    if (!content || !buttons.length) {
      return;
    }

    var startCollapsed = block.getAttribute('data-collapsed') !== 'false';
    updateButtonState(block, buttons, startCollapsed);

    window.requestAnimationFrame(function () {
      updateToggleVisibility(block, content, buttons);
    });
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.archive-hero-desc__toggle');
    if (!button) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    var block = button.closest('.archive-hero-desc');
    if (!block) {
      return;
    }

    var content = block.querySelector('.archive-hero-desc__content');
    var buttons = Array.prototype.slice.call(block.querySelectorAll('.archive-hero-desc__toggle'));
    if (!content || !buttons.length) {
      return;
    }

    var isCollapsed = block.getAttribute('data-collapsed') !== 'false';
    updateButtonState(block, buttons, !isCollapsed);
    updateToggleVisibility(block, content, buttons);
  });
})();

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

(function () {
  var openButtons = Array.prototype.slice.call(document.querySelectorAll('[data-crm-danger-open]'));
  if (!openButtons.length) {
    return;
  }

  function closeDialog(dialog) {
    if (!dialog) {
      return;
    }

    if (typeof dialog.close === 'function') {
      dialog.close();
      return;
    }

    dialog.removeAttribute('open');
  }

  openButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      var targetId = button.getAttribute('data-crm-danger-open');
      if (!targetId) {
        return;
      }

      var dialog = document.getElementById(targetId);
      if (!dialog) {
        return;
      }

      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else {
        dialog.setAttribute('open', 'open');
      }
    });
  });

  var closeButtons = Array.prototype.slice.call(document.querySelectorAll('[data-crm-danger-close]'));
  closeButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      var targetId = button.getAttribute('data-crm-danger-close');
      if (!targetId) {
        return;
      }

      closeDialog(document.getElementById(targetId));
    });
  });

  Array.prototype.slice.call(document.querySelectorAll('.crm-danger-dialog')).forEach(function (dialog) {
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) {
        closeDialog(dialog);
      }
    });
  });
})();

(function () {
  var widgets = Array.prototype.slice.call(document.querySelectorAll('[data-crm-property-search]'));
  if (!widgets.length) {
    return;
  }

  var ajaxUrl = window.peraCrmData && window.peraCrmData.ajaxUrl ? window.peraCrmData.ajaxUrl : '';
  var nonce = window.peraCrmData && window.peraCrmData.propertySearchNonce ? window.peraCrmData.propertySearchNonce : '';
  if (!ajaxUrl || !nonce) {
    return;
  }

  widgets.forEach(function (widget) {
    var queryInput = widget.querySelector('[data-crm-property-query]');
    var idInput = widget.querySelector('[data-crm-property-id]');
    var results = widget.querySelector('[data-crm-property-results]');
    var feedback = widget.querySelector('[data-crm-property-feedback]');

    if (!queryInput || !idInput || !results) {
      return;
    }

    var timer = null;

    function renderItems(items) {
      results.innerHTML = '';
      if (!items.length) {
        results.hidden = true;
        return;
      }

      items.forEach(function (item) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'crm-property-search-item';
        button.textContent = item.project_name + (item.district ? ' · ' + item.district : '') + ' (#' + item.property_id + ')';
        button.addEventListener('click', function () {
          queryInput.value = item.project_name;
          idInput.value = String(item.property_id || '');
          results.hidden = true;
          if (feedback) {
            feedback.textContent = 'Selected property #' + item.property_id;
          }
        });
        results.appendChild(button);
      });

      results.hidden = false;
    }

    function runSearch() {
      var term = (queryInput.value || '').trim();
      idInput.value = '';

      if (term.length < 2) {
        results.hidden = true;
        if (feedback) {
          feedback.textContent = 'Type at least 2 letters and choose a project.';
        }
        return;
      }

      if (feedback) {
        feedback.textContent = 'Searching…';
      }

      fetch(ajaxUrl + '?action=pera_crm_property_search&nonce=' + encodeURIComponent(nonce) + '&q=' + encodeURIComponent(term), {
        credentials: 'same-origin'
      })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          var items = payload && payload.success && payload.data && Array.isArray(payload.data.items) ? payload.data.items : [];
          renderItems(items);
          if (feedback) {
            feedback.textContent = items.length ? 'Select a project from the list.' : 'No matching projects found.';
          }
        })
        .catch(function () {
          results.hidden = true;
          if (feedback) {
            feedback.textContent = 'Search unavailable right now.';
          }
        });
    }

    queryInput.addEventListener('input', function () {
      if (timer) {
        clearTimeout(timer);
      }
      timer = setTimeout(runSearch, 250);
    });

    queryInput.addEventListener('blur', function () {
      setTimeout(function () {
        results.hidden = true;
      }, 120);
    });

    queryInput.addEventListener('focus', function () {
      if (results.children.length > 0) {
        results.hidden = false;
      }
    });
  });
})();
