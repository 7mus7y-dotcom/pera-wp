(function () {
  var header = document.querySelector('#site-header.peracrm-shell-header');
  if (!header) {
    return;
  }

  function syncScrolledState() {
    header.classList.toggle('is-scrolled', window.scrollY > 8);
  }

  syncScrolledState();
  window.addEventListener('scroll', syncScrolledState, { passive: true });
})();

(function () {
  var ajaxUrl = window.peraCrmData && window.peraCrmData.ajaxUrl ? window.peraCrmData.ajaxUrl : '';
  if (!ajaxUrl) {
    return;
  }

  var toastEl = null;
  var toastTimer = null;
  var activeUndo = null;

  function nativeSubmit(form) {
    if (!form) {
      return;
    }

    form.setAttribute('data-crm-bypass-ajax', '1');
    if (window.HTMLFormElement && window.HTMLFormElement.prototype && window.HTMLFormElement.prototype.submit) {
      window.HTMLFormElement.prototype.submit.call(form);
      return;
    }
    form.submit();
  }

  function parseJsonResponse(response) {
    return response.text().then(function (body) {
      var json = null;
      try {
        json = body ? JSON.parse(body) : null;
      } catch (e) {
        json = null;
      }

      var data = json && json.data ? json.data : {};
      var message = data && data.message ? String(data.message) : 'Unable to update task.';
      if (!response.ok || !json || !json.success) {
        throw new Error(message);
      }

      return data;
    });
  }

  function requestReminderStatus(reminderId, status, nonce) {
    var payload = new window.FormData();
    payload.append('action', 'peracrm_reminder_status_ajax');
    payload.append('peracrm_reminder_id', String(reminderId || ''));
    payload.append('peracrm_status', String(status || ''));
    payload.append('peracrm_update_reminder_status_nonce', String(nonce || ''));

    return window.fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload
    }).then(parseJsonResponse);
  }

  function ensureToast() {
    if (toastEl) {
      return toastEl;
    }

    var container = document.createElement('div');
    container.className = 'crm-reminder-toast';
    container.setAttribute('role', 'status');
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-hidden', 'true');
    container.innerHTML = '' +
      '<button type="button" class="crm-reminder-toast__close" aria-label="Close notification">×</button>' +
      '<p class="crm-reminder-toast__message">Task completed!</p>' +
      '<button type="button" class="btn btn--ghost btn--blue crm-reminder-toast__undo">Undo</button>';
    document.body.appendChild(container);

    container.querySelector('.crm-reminder-toast__close').addEventListener('click', function () {
      hideToast();
    });

    container.querySelector('.crm-reminder-toast__undo').addEventListener('click', function () {
      if (!activeUndo || !activeUndo.reminderId || !activeUndo.nonce) {
        hideToast();
        return;
      }

      var undoButton = container.querySelector('.crm-reminder-toast__undo');
      undoButton.disabled = true;
      undoButton.setAttribute('aria-busy', 'true');
      requestReminderStatus(activeUndo.reminderId, activeUndo.previousStatus || 'pending', activeUndo.nonce)
        .then(function () {
          if (activeUndo && activeUndo.rowState && activeUndo.rowState.parent && activeUndo.rowState.row) {
            activeUndo.rowState.parent.insertBefore(activeUndo.rowState.row, activeUndo.rowState.nextSibling || null);
          }
          hideToast();
        })
        .catch(function (error) {
          var messageNode = container.querySelector('.crm-reminder-toast__message');
          messageNode.textContent = error && error.message ? error.message : 'Unable to undo.';
          scheduleToastDismiss(4500);
        })
        .finally(function () {
          undoButton.disabled = false;
          undoButton.removeAttribute('aria-busy');
        });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && container.classList.contains('is-visible')) {
        hideToast();
      }
    });

    toastEl = container;
    return toastEl;
  }

  function scheduleToastDismiss(delay) {
    if (toastTimer) {
      window.clearTimeout(toastTimer);
    }
    toastTimer = window.setTimeout(function () {
      hideToast();
    }, delay || 4500);
  }

  function hideToast() {
    if (!toastEl) {
      return;
    }
    toastEl.classList.remove('is-visible');
    toastEl.setAttribute('aria-hidden', 'true');
    activeUndo = null;
    if (toastTimer) {
      window.clearTimeout(toastTimer);
      toastTimer = null;
    }
  }

  function showToast(undoState) {
    var toast = ensureToast();
    var messageNode = toast.querySelector('.crm-reminder-toast__message');
    var undoButton = toast.querySelector('.crm-reminder-toast__undo');
    messageNode.textContent = 'Task completed!';
    undoButton.disabled = false;
    undoButton.removeAttribute('aria-busy');
    // Intentionally single-instance toast UX: latest completion owns Undo context.
    activeUndo = undoState || null;

    toast.classList.add('is-visible');
    toast.setAttribute('aria-hidden', 'false');
    scheduleToastDismiss(4500);
  }

  function setButtonLoading(button, isLoading) {
    if (!button) {
      return;
    }

    if (isLoading) {
      var original = button.tagName.toLowerCase() === 'input' ? (button.value || '') : (button.textContent || '');
      button.setAttribute('data-crm-original-label', original);
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      if (button.tagName.toLowerCase() === 'input') {
        button.value = 'Working…';
      } else {
        button.textContent = 'Working…';
      }
      return;
    }

    var label = button.getAttribute('data-crm-original-label');
    button.disabled = false;
    button.removeAttribute('aria-busy');
    if (!label) {
      return;
    }
    if (button.tagName.toLowerCase() === 'input') {
      button.value = label;
    } else {
      button.textContent = label;
    }
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-crm-reminder-action-form="1"]');
    if (!form) {
      return;
    }

    if (form.getAttribute('data-crm-bypass-ajax') === '1') {
      form.removeAttribute('data-crm-bypass-ajax');
      return;
    }

    if (form.getAttribute('data-crm-busy') === '1') {
      event.preventDefault();
      return;
    }

    event.preventDefault();

    var reminderId = parseInt(form.querySelector('input[name="peracrm_reminder_id"]') ? form.querySelector('input[name="peracrm_reminder_id"]').value : '0', 10) || 0;
    var status = form.querySelector('input[name="peracrm_status"]') ? form.querySelector('input[name="peracrm_status"]').value : '';
    var nonce = form.querySelector('input[name="peracrm_update_reminder_status_nonce"]') ? form.querySelector('input[name="peracrm_update_reminder_status_nonce"]').value : '';
    if (!reminderId || !status || !nonce) {
      nativeSubmit(form);
      return;
    }

    var submitButton = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
    var row = form.closest('[data-crm-reminder-row]');
    var rowState = row && row.parentNode ? {
      row: row,
      parent: row.parentNode,
      nextSibling: row.nextSibling
    } : null;

    form.setAttribute('data-crm-busy', '1');
    setButtonLoading(submitButton, true);

    requestReminderStatus(reminderId, status, nonce)
      .then(function (data) {
        if (row && row.parentNode) {
          row.parentNode.removeChild(row);
        }

        showToast({
          reminderId: reminderId,
          previousStatus: data && data.previous_status ? data.previous_status : 'pending',
          nonce: nonce,
          rowState: rowState
        });
      })
      .catch(function () {
        nativeSubmit(form);
      })
      .finally(function () {
        form.removeAttribute('data-crm-busy');
        setButtonLoading(submitButton, false);
      });
  });
})();

(function () {
  var navRoot = document.querySelector('[data-crm-nav]');
  if (!navRoot) {
    return;
  }

  var toggles = Array.prototype.slice.call(document.querySelectorAll('[data-crm-nav-toggle]'));
  var drawer = navRoot.querySelector('[data-crm-nav-drawer]');
  var overlay = navRoot.querySelector('[data-crm-nav-overlay]');
  var close = navRoot.querySelector('[data-crm-nav-close]');
  var lastTrigger = null;

  if (!toggles.length || !drawer || !overlay) {
    return;
  }

  function syncExpanded(open) {
    toggles.forEach(function (toggle) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  function setOpen(open) {
    navRoot.classList.toggle('is-open', open);
    drawer.hidden = !open;
    overlay.hidden = !open;
    syncExpanded(open);
    document.body.classList.toggle('crm-nav-open', open);

    if (open) {
      window.requestAnimationFrame(function () {
        drawer.focus();
      });
      return;
    }

    if (lastTrigger && typeof lastTrigger.focus === 'function') {
      window.requestAnimationFrame(function () {
        lastTrigger.focus();
      });
    }
  }

  toggles.forEach(function (toggle) {
    toggle.addEventListener('click', function () {
      lastTrigger = toggle;
      setOpen(!navRoot.classList.contains('is-open'));
    });
  });

  if (close) {
    close.addEventListener('click', function () {
      setOpen(false);
    });
  }

  overlay.addEventListener('click', function () {
    setOpen(false);
  });

  drawer.addEventListener('click', function (event) {
    var target = event.target.closest('a[href]');
    if (target) {
      setOpen(false);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && navRoot.classList.contains('is-open')) {
      setOpen(false);
    }
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 1024 && navRoot.classList.contains('is-open')) {
      setOpen(false);
    }
  });
})();

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
  function initViewToggle(root) {
    if (!root || root.getAttribute('data-crm-view-toggle-initialized') === 'true') {
      return;
    }

    var storageKey = root.getAttribute('data-storage-key') ? root.getAttribute('data-storage-key') : 'peracrm_clients_view';
    var scope = root.closest('[data-crm-clients-workspace], .crm-layout__main, main, body') || document;
    var buttons = Array.prototype.slice.call(root.querySelectorAll('button[data-view]'));
    var tableView = scope.querySelector('[data-crm-view="table"]');
    var cardsView = scope.querySelector('[data-crm-view="cards"]');

    if (!buttons.length || (!tableView && !cardsView)) {
      return;
    }

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

    var defaultDesktop = root.getAttribute('data-default-desktop') === 'cards' ? 'cards' : 'table';
    var defaultMobile = root.getAttribute('data-default-mobile') === 'table' ? 'table' : 'cards';
    var prefersMobileDefault = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
    var fallbackView = prefersMobileDefault ? defaultMobile : defaultDesktop;
    var initial = stored === 'table' || stored === 'cards' ? stored : fallbackView;
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

    root.setAttribute('data-crm-view-toggle-initialized', 'true');
  }

  function initSortableTable(table) {
    if (!table || table.getAttribute('data-crm-sort-table-initialized') === 'true') {
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

    table.setAttribute('data-crm-sort-table-initialized', 'true');
  }

  function initCrmWorkspaceBehaviors(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;

    Array.prototype.slice.call(root.querySelectorAll('[data-crm-view-toggle]')).forEach(initViewToggle);
    Array.prototype.slice.call(root.querySelectorAll('[data-crm-sort-table], .crm-leads-table')).forEach(initSortableTable);
  }

  window.peraCrmInitWorkspaceBehaviors = initCrmWorkspaceBehaviors;
  initCrmWorkspaceBehaviors(document);
})();

(function () {
  var clientsPathPattern = /\/crm\/clients\/?$/;
  var workspaceSelector = '[data-crm-clients-workspace]';
  var toggleSelector = '[data-crm-clients-type-toggle]';
  var inFlightController = null;

  function isClientsRoute(url) {
    var pathname = url && url.pathname ? url.pathname : window.location.pathname;
    return clientsPathPattern.test(pathname);
  }

  function getWorkspace() {
    return document.querySelector(workspaceSelector);
  }

  function getToggle() {
    return document.querySelector(toggleSelector);
  }

  function setToggleDisabled(toggle, disabled) {
    if (!toggle) {
      return;
    }

    Array.prototype.slice.call(toggle.querySelectorAll('a[href]')).forEach(function (link) {
      if (disabled) {
        link.setAttribute('aria-disabled', 'true');
        link.setAttribute('tabindex', '-1');
      } else {
        link.removeAttribute('aria-disabled');
        link.removeAttribute('tabindex');
      }
    });
  }

  function setLoadingState(workspace, toggle, isLoading) {
    if (!workspace) {
      return;
    }

    workspace.classList.toggle('is-loading', isLoading);
    workspace.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    setToggleDisabled(toggle, isLoading);
  }

  function swapWorkspaceFromHtml(html) {
    var parser = new window.DOMParser();
    var nextDocument = parser.parseFromString(html, 'text/html');
    var nextWorkspace = nextDocument.querySelector(workspaceSelector);
    var currentWorkspace = getWorkspace();

    if (!nextWorkspace || !currentWorkspace || !currentWorkspace.parentNode) {
      throw new Error('CRM clients workspace was not found in the response.');
    }

    currentWorkspace.parentNode.replaceChild(nextWorkspace, currentWorkspace);
    if (typeof window.peraCrmInitWorkspaceBehaviors === 'function') {
      window.peraCrmInitWorkspaceBehaviors(nextWorkspace);
    }
  }

  function fetchAndSwap(url, options) {
    var targetUrl = url instanceof window.URL ? url : new window.URL(url, window.location.origin);
    var workspace = getWorkspace();
    var toggle = getToggle();

    if (!workspace || !toggle || !isClientsRoute(targetUrl)) {
      window.location.href = targetUrl.toString();
      return Promise.resolve(false);
    }

    if (inFlightController) {
      inFlightController.abort();
    }

    inFlightController = new window.AbortController();
    setLoadingState(workspace, toggle, true);

    return window.fetch(targetUrl.toString(), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'fetch',
        'Accept': 'text/html'
      },
      signal: inFlightController.signal
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('CRM clients workspace request failed.');
      }
      return response.text();
    }).then(function (html) {
      swapWorkspaceFromHtml(html);
      if (!options || options.pushState !== false) {
        window.history.pushState({ crmClientsWorkspace: true, url: targetUrl.toString() }, '', targetUrl.toString());
      }
      return true;
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        return false;
      }

      if (!options || options.fallback !== false) {
        window.location.href = targetUrl.toString();
      }
      return false;
    }).finally(function () {
      inFlightController = null;
      setLoadingState(getWorkspace(), getToggle(), false);
    });
  }

  if (!isClientsRoute(new window.URL(window.location.href)) || !getWorkspace() || !getToggle()) {
    return;
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest(toggleSelector + ' a[href]');
    if (!link) {
      return;
    }

    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    if (link.getAttribute('aria-disabled') === 'true') {
      event.preventDefault();
      return;
    }

    var targetUrl = new window.URL(link.href, window.location.origin);
    if (!isClientsRoute(targetUrl)) {
      return;
    }

    event.preventDefault();
    fetchAndSwap(targetUrl);
  });

  window.addEventListener('popstate', function () {
    var targetUrl = new window.URL(window.location.href);
    if (!isClientsRoute(targetUrl)) {
      return;
    }

    fetchAndSwap(targetUrl, { pushState: false, fallback: true });
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
  var ajaxUrl = window.peraCrmData && window.peraCrmData.ajaxUrl ? window.peraCrmData.ajaxUrl : '';
  var nonce = window.peraCrmData && window.peraCrmData.createPortfolioNonce ? window.peraCrmData.createPortfolioNonce : '';
  var updateNonce = window.peraCrmData && window.peraCrmData.updatePortfolioNonce ? window.peraCrmData.updatePortfolioNonce : '';
  if (!ajaxUrl || !nonce) {
    return;
  }

  function initLinkedPropertiesSection(section) {
    if (!section || section.getAttribute('data-crm-portfolio-initialized') === 'true') {
      return;
    }

    var clientId = section.getAttribute('data-client-id') || '';
    var openButton = section.querySelector('[data-crm-portfolio-open]');
    var outputRow = section.querySelector('[data-crm-portfolio-output]');
    var urlInput = section.querySelector('[data-crm-portfolio-url]');
    var copyButton = section.querySelector('[data-crm-portfolio-copy]');
    var updateButton = section.querySelector('[data-crm-portfolio-update]');
    var expiresNote = section.querySelector('[data-crm-portfolio-expires]');
    var faqToggle = section.querySelector('[data-crm-portfolio-citizenship-faq]');
    var updateFeedback = null;

    if (!clientId || !openButton) {
      return;
    }

    if (outputRow) {
      updateFeedback = outputRow.querySelector('[data-crm-portfolio-update-feedback]');
      if (!updateFeedback) {
        updateFeedback = document.createElement('small');
        updateFeedback.className = 'text-sm';
        updateFeedback.setAttribute('data-crm-portfolio-update-feedback', '');
        outputRow.appendChild(updateFeedback);
      }
    }

    var dialogId = openButton.getAttribute('data-crm-portfolio-open');
    var dialog = dialogId ? document.getElementById(dialogId) : null;
    if (!dialog) {
      return;
    }

    var form = dialog.querySelector('[data-crm-portfolio-form]');
    var submitButton = dialog.querySelector('[data-crm-portfolio-submit]');
    var feedback = dialog.querySelector('[data-crm-portfolio-feedback]');
    var closeButtons = Array.prototype.slice.call(dialog.querySelectorAll('[data-crm-portfolio-close]'));

    function closeDialog() {
      if (typeof dialog.close === 'function') {
        dialog.close();
      } else {
        dialog.removeAttribute('open');
      }
    }

    function openDialog() {
      if (feedback) {
        feedback.textContent = '';
      }

      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else {
        dialog.setAttribute('open', 'open');
      }
    }

    openButton.addEventListener('click', function () {
      openDialog();
    });

    closeButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        closeDialog();
      });
    });

    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) {
        closeDialog();
      }
    });

    if (form && submitButton) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();

        var formData = new window.FormData(form);
        var expiry = String(formData.get('expiry') || '').trim();

        var payload = new window.FormData();
        payload.append('action', 'peracrm_create_portfolio_token');
        payload.append('nonce', nonce);
        payload.append('client_id', clientId);
        payload.append('expiry', expiry);
        payload.append('include_citizenship_faq', faqToggle && faqToggle.checked ? '1' : '0');

        submitButton.disabled = true;
        var originalLabel = submitButton.textContent;
        submitButton.textContent = 'Generating…';
        if (feedback) {
          feedback.textContent = '';
        }

        fetch(ajaxUrl, {
          method: 'POST',
          body: payload,
          credentials: 'same-origin'
        })
          .then(function (response) { return response.json(); })
          .then(function (json) {
            if (!json || !json.success || !json.data || !json.data.url) {
              var errorMessage = json && json.data && json.data.message ? String(json.data.message) : 'Unable to generate portfolio link.';
              throw new Error(errorMessage);
            }

            if (urlInput) {
              urlInput.value = String(json.data.url);
            }
            if (outputRow) {
              outputRow.hidden = false;
            }
            if (expiresNote) {
              expiresNote.textContent = json.data.expires_label ? 'Expires: ' + String(json.data.expires_label) : '';
            }
            if (updateButton && json.data.post_id) {
              updateButton.hidden = false;
              updateButton.setAttribute('data-portfolio-post-id', String(json.data.post_id));
            }

            if (feedback) {
              feedback.textContent = 'Portfolio link generated.';
            }
            window.setTimeout(function () {
              closeDialog();
            }, 350);
          })
          .catch(function (error) {
            if (feedback) {
              feedback.textContent = error && error.message ? error.message : 'Unable to generate portfolio link.';
            }
          })
          .finally(function () {
            submitButton.disabled = false;
            submitButton.textContent = originalLabel;
          });
      });
    }

    if (copyButton && urlInput) {
      copyButton.addEventListener('click', function () {
        var value = String(urlInput.value || '').trim();
        if (!value) {
          return;
        }

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          navigator.clipboard.writeText(value)
            .then(function () {})
            .catch(function () {
              urlInput.focus();
              urlInput.select();
              document.execCommand('copy');
            });
          return;
        }

        urlInput.focus();
        urlInput.select();
        document.execCommand('copy');
      });
    }

    if (updateButton && updateNonce) {
      updateButton.addEventListener('click', function () {
        var portfolioPostId = updateButton.getAttribute('data-portfolio-post-id') || '';
        var updateClientId = updateButton.getAttribute('data-client-id') || clientId;
        if (!portfolioPostId || !updateClientId) {
          return;
        }

        var payload = new window.FormData();
        payload.append('action', 'peracrm_update_portfolio_token');
        payload.append('nonce', updateNonce);
        payload.append('client_id', updateClientId);
        payload.append('portfolio_post_id', portfolioPostId);
        payload.append('include_citizenship_faq', faqToggle && faqToggle.checked ? '1' : '0');

        var originalLabel = updateButton.textContent;
        updateButton.disabled = true;
        updateButton.textContent = 'Updating…';
        if (updateFeedback) {
          updateFeedback.textContent = '';
        }

        fetch(ajaxUrl, {
          method: 'POST',
          body: payload,
          credentials: 'same-origin'
        })
          .then(function (response) { return response.json(); })
          .then(function (json) {
            if (!json || !json.success || !json.data || !json.data.url) {
              var message = json && json.data && json.data.message ? String(json.data.message) : 'Unable to update portfolio link.';
              throw new Error(message);
            }

            if (urlInput) {
              urlInput.value = String(json.data.url);
            }

            if (expiresNote) {
              expiresNote.textContent = json.data.expires_label ? 'Expires: ' + String(json.data.expires_label) : '';
            }

            if (json.data.post_id) {
              updateButton.setAttribute('data-portfolio-post-id', String(json.data.post_id));
            }

            if (updateFeedback) {
              updateFeedback.textContent = 'Portfolio link updated.';
            }
          })
          .catch(function (error) {
            var errorMessage = error && error.message ? error.message : 'Unable to update portfolio link.';
            if (updateFeedback) {
              updateFeedback.textContent = errorMessage;
            } else {
              window.alert(errorMessage);
            }
          })
          .finally(function () {
            updateButton.disabled = false;
            updateButton.textContent = originalLabel;
          });
      });
    }

    section.setAttribute('data-crm-portfolio-initialized', 'true');
  }

  function initAllLinkedPropertiesSections(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    Array.prototype.slice.call(root.querySelectorAll('[data-crm-linked-properties]')).forEach(initLinkedPropertiesSection);
  }

  initAllLinkedPropertiesSections(document);
  document.addEventListener('peracrm:panel-replaced', function (event) {
    initAllLinkedPropertiesSections(event && event.detail ? event.detail.element : document);
  });
})();

(function () {
  var ajaxUrl = window.peraCrmData && window.peraCrmData.ajaxUrl ? window.peraCrmData.ajaxUrl : '';
  var nonce = window.peraCrmData && window.peraCrmData.propertySearchNonce ? window.peraCrmData.propertySearchNonce : '';
  if (!ajaxUrl || !nonce) {
    return;
  }

  function initSearchWidget(widget) {
    if (!widget || widget.getAttribute('data-crm-property-search-initialized') === 'true') {
      return;
    }

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
    widget.setAttribute('data-crm-property-search-initialized', 'true');
  }

  function initSearchWidgets(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    var widgets = Array.prototype.slice.call(root.querySelectorAll('[data-crm-property-search]'));
    widgets.forEach(initSearchWidget);
  }

  initSearchWidgets(document);
  document.addEventListener('peracrm:panel-replaced', function (event) {
    initSearchWidgets(event && event.detail ? event.detail.element : document);
  });
})();

(function () {
  var ajaxUrl = window.peraCrmData && window.peraCrmData.ajaxUrl ? window.peraCrmData.ajaxUrl : '';
  var fieldsNonce = window.peraCrmData && window.peraCrmData.portfolioFieldsNonce ? window.peraCrmData.portfolioFieldsNonce : '';
  var uploadNonce = window.peraCrmData && window.peraCrmData.portfolioFloorPlanNonce ? window.peraCrmData.portfolioFloorPlanNonce : '';
  if (!ajaxUrl || !fieldsNonce || !uploadNonce) {
    return;
  }

  function initPortfolioRow(row) {
    if (!row || row.getAttribute('data-crm-portfolio-row-initialized') === 'true') {
      return;
    }

    var saveButton = row.querySelector('[data-action="save-portfolio-fields"]');
    var pickButton = row.querySelector('[data-action="pick-floorplan"]');
    var floorPlanInput = row.querySelector('[data-floorplan-input]');
    var floorPlanAttachmentInput = row.querySelector('[data-field="floor_plan_attachment_id"]');
    var floorPlanLink = row.querySelector('[data-crm-floor-plan-link]');
    var statusEl = row.querySelector('[data-crm-portfolio-status]');
    var fieldsWrap = row.querySelector('[data-crm-portfolio-fields]');

    function ensureFloorPlanLink(url) {
      if (!url) {
        return null;
      }

      if (!floorPlanLink) {
        floorPlanLink = document.createElement('a');
        floorPlanLink.className = 'peracrm-floor-plan-link';
        floorPlanLink.setAttribute('target', '_blank');
        floorPlanLink.setAttribute('rel', 'noopener noreferrer');
        floorPlanLink.setAttribute('data-crm-floor-plan-link', '');
        floorPlanLink.textContent = 'View floor plan';
        if (fieldsWrap && fieldsWrap.parentNode) {
          fieldsWrap.parentNode.insertBefore(floorPlanLink, fieldsWrap.nextSibling);
        } else {
          row.appendChild(floorPlanLink);
        }
      }

      floorPlanLink.href = String(url);
      floorPlanLink.hidden = false;
      return floorPlanLink;
    }
    if (!saveButton) {
      return;
    }

    var clientId = row.getAttribute('data-client-id') || '';
    var propertyId = row.getAttribute('data-property-id') || '';

    function setStatus(message) {
      if (statusEl) {
        statusEl.textContent = message;
      }
    }

    function uploadFloorPlan() {
      return new Promise(function (resolve, reject) {
        if (!floorPlanInput || !floorPlanInput.files || !floorPlanInput.files[0]) {
          resolve(null);
          return;
        }

        if (!clientId || !propertyId) {
          reject(new Error('Missing client or property.'));
          return;
        }

        var payload = new window.FormData();
        payload.append('action', 'pera_crm_upload_portfolio_floor_plan');
        payload.append('nonce', uploadNonce);
        payload.append('client_id', clientId);
        payload.append('property_id', propertyId);
        payload.append('floor_plan', floorPlanInput.files[0]);

        setStatus('Uploading…');

        fetch(ajaxUrl, {
          method: 'POST',
          body: payload,
          credentials: 'same-origin'
        })
          .then(function (response) { return response.json(); })
          .then(function (json) {
            if (!json || !json.success || !json.data) {
              var message = json && json.data && json.data.message ? String(json.data.message) : 'Upload failed.';
              throw new Error(message);
            }

            var attachmentId = json.data.attachment_id ? String(json.data.attachment_id) : '';
            if (floorPlanAttachmentInput) {
              floorPlanAttachmentInput.value = attachmentId;
            }

            if (json.data.url) {
              ensureFloorPlanLink(json.data.url);
            }

            floorPlanInput.value = '';
            setStatus('Uploaded');
            resolve(json.data);
          })
          .catch(function (error) {
            reject(error);
          });
      });
    }

    if (pickButton && floorPlanInput) {
      pickButton.addEventListener('click', function (event) {
        event.preventDefault();
        floorPlanInput.click();
      });
    }

    if (floorPlanInput) {
      floorPlanInput.addEventListener('change', function () {
        if (floorPlanInput.files && floorPlanInput.files[0]) {
          setStatus('Ready to upload');
        }
        uploadFloorPlan().catch(function (error) {
          setStatus(error && error.message ? error.message : 'Upload failed.');
        });
      });
    }

    saveButton.addEventListener('click', function (event) {
      event.preventDefault();

      if (!clientId || !propertyId) {
        setStatus('Missing client or property.');
        return;
      }

      var payload = new window.FormData();
      payload.append('action', 'pera_crm_save_portfolio_property_fields');
      payload.append('nonce', fieldsNonce);
      payload.append('client_id', clientId);
      payload.append('property_id', propertyId);

      ['unit_type', 'floor_number', 'net_size', 'gross_size', 'list_price', 'cash_price', 'notes', 'floor_plan_attachment_id'].forEach(function (fieldName) {
        var input = row.querySelector('[data-field="' + fieldName + '"]');
        payload.append('fields[' + fieldName + ']', input ? String(input.value || '') : '');
      });

      saveButton.disabled = true;
      setStatus('Saving…');

      fetch(ajaxUrl, {
        method: 'POST',
        body: payload,
        credentials: 'same-origin'
      })
        .then(function (response) { return response.json(); })
        .then(function (json) {
          if (!json || !json.success || !json.data || !json.data.fields) {
            var message = json && json.data && json.data.message ? String(json.data.message) : 'Unable to save.';
            throw new Error(message);
          }

          Object.keys(json.data.fields).forEach(function (key) {
            var input = row.querySelector('[data-field="' + key + '"]');
            if (!input) {
              return;
            }
            input.value = json.data.fields[key] === null ? '' : String(json.data.fields[key]);
          });

          if (json.data.floor_plan_url) {
            ensureFloorPlanLink(json.data.floor_plan_url);
          }

          setStatus('Saved');
        })
        .catch(function (error) {
          setStatus(error && error.message ? error.message : 'Unable to save.');
        })
        .finally(function () {
          saveButton.disabled = false;
        });
    });
    row.setAttribute('data-crm-portfolio-row-initialized', 'true');
  }

  function initPortfolioRows(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    var rows = Array.prototype.slice.call(root.querySelectorAll('[data-crm-portfolio-row]'));
    rows.forEach(initPortfolioRow);
  }

  initPortfolioRows(document);
  document.addEventListener('peracrm:panel-replaced', function (event) {
    initPortfolioRows(event && event.detail ? event.detail.element : document);
  });
})();

(function () {
  var ajaxUrl = window.peraCrmData && window.peraCrmData.ajaxUrl ? window.peraCrmData.ajaxUrl : '';
  if (!ajaxUrl) {
    return;
  }

  function ensureStatusNode(form) {
    var box = form.querySelector('[data-crm-form-status]');
    if (!box) {
      box = document.createElement('p');
      box.className = 'crm-inline-status';
      box.setAttribute('aria-live', 'polite');
      box.setAttribute('data-crm-form-status', '');
      form.appendChild(box);
    }
    return box;
  }

  function setFeedback(form, message, ok) {
    if (!form) {
      return;
    }
    var box = ensureStatusNode(form);
    box.textContent = message || '';
    box.classList.toggle('is-success', !!ok);
    box.classList.toggle('is-error', !ok);
  }

  function setLoading(form, loading) {
    var buttons = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
    buttons.forEach(function (button) {
      if (loading) {
        var currentLabel = button.tagName.toLowerCase() === 'input' ? (button.value || '') : (button.textContent || '');
        button.setAttribute('data-crm-original-label', currentLabel);
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        if (button.tagName.toLowerCase() === 'input') {
          button.value = 'Saving…';
        } else {
          button.textContent = 'Saving…';
        }
      } else {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        var label = button.getAttribute('data-crm-original-label') || '';
        if (!label) {
          return;
        }
        if (button.tagName.toLowerCase() === 'input') {
          button.value = label;
        } else {
          button.textContent = label;
        }
      }
    });
  }

  function replacePanel(panelName, panelHtml) {
    if (!panelName || !panelHtml) {
      return false;
    }

    var parser = new window.DOMParser();
    var parsed = parser.parseFromString(panelHtml, 'text/html');
    var incoming = parsed.querySelector('[data-crm-panel="' + panelName + '"]');
    var current = document.querySelector('[data-crm-panel="' + panelName + '"]');
    if (incoming && current) {
      current.replaceWith(incoming);
      document.dispatchEvent(new window.CustomEvent('peracrm:panel-replaced', {
        detail: {
          panel: panelName,
          element: incoming
        }
      }));
      return true;
    }

    return false;
  }

  function replaceProfileFormOnly(panelHtml) {
    if (!panelHtml) {
      return false;
    }

    var parser = new window.DOMParser();
    var parsed = parser.parseFromString(panelHtml, 'text/html');
    var incomingForm = parsed.querySelector('form[data-crm-ajax-form="profile"]');
    var currentForm = document.querySelector('[data-crm-panel="profile"] form[data-crm-ajax-form="profile"]');
    if (!incomingForm || !currentForm) {
      return false;
    }

    currentForm.replaceWith(incomingForm);
    return true;
  }

  function resolveLiveForm(panelName, formType, fallbackForm) {
    if (panelName) {
      var livePanel = document.querySelector('[data-crm-panel="' + panelName + '"]');
      if (livePanel) {
        var panelForm = livePanel.querySelector('form[data-crm-ajax-form="' + formType + '"]');
        if (panelForm) {
          return panelForm;
        }
      }
    }

    var firstMatch = document.querySelector('form[data-crm-ajax-form="' + formType + '"]');
    if (firstMatch) {
      return firstMatch;
    }

    return fallbackForm;
  }

  function ensureAdvisorDialog() {
    var dialog = document.getElementById('crm-advisor-confirm-dialog');
    if (dialog) {
      return dialog;
    }

    dialog = document.createElement('dialog');
    dialog.id = 'crm-advisor-confirm-dialog';
    dialog.className = 'crm-danger-dialog crm-confirm-dialog';
    dialog.innerHTML = '' +
      '<h4 id="crm-advisor-confirm-title">Confirm advisor reassignment</h4>' +
      '<p id="crm-advisor-confirm-desc">You are about to change the assigned advisor for this client.</p>' +
      '<div class="crm-danger-dialog__actions">' +
      '<button type="button" class="btn btn--solid btn--blue" data-crm-confirm-yes>Yes, I’m sure</button>' +
      '<button type="button" class="btn btn--ghost btn--blue" data-crm-confirm-no>No, I made a mistake</button>' +
      '</div>';
    dialog.setAttribute('aria-labelledby', 'crm-advisor-confirm-title');
    dialog.setAttribute('aria-describedby', 'crm-advisor-confirm-desc');
    document.body.appendChild(dialog);
    return dialog;
  }

  function confirmAdvisorSubmit() {
    return new Promise(function (resolve) {
      var dialog = ensureAdvisorDialog();
      var yes = dialog.querySelector('[data-crm-confirm-yes]');
      var no = dialog.querySelector('[data-crm-confirm-no]');

      function cleanup(value) {
        yes.removeEventListener('click', onYes);
        no.removeEventListener('click', onNo);
        if (typeof dialog.close === 'function') {
          dialog.close();
        } else {
          dialog.removeAttribute('open');
        }
        resolve(value);
      }

      function onYes() { cleanup(true); }
      function onNo() { cleanup(false); }
      yes.addEventListener('click', onYes);
      no.addEventListener('click', onNo);

      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else {
        dialog.setAttribute('open', 'open');
      }
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-crm-ajax-form]');
    if (!form) {
      return;
    }

    var type = form.getAttribute('data-crm-ajax-form') || '';
    if (!type) {
      return;
    }

    var confirmText = '';
    var submitter = event.submitter;
    if (submitter && submitter.getAttribute) {
      confirmText = submitter.getAttribute('data-crm-confirm-text') || '';
    }
    if (confirmText && !window.confirm(confirmText)) {
      event.preventDefault();
      return;
    }

    event.preventDefault();

    var proceed = Promise.resolve(true);
    if (type === 'advisor') {
      proceed = confirmAdvisorSubmit();
    }

    proceed.then(function (okToContinue) {
      if (!okToContinue) {
        return;
      }

      var payload = new window.FormData(form);
      payload.append('action', 'pera_crm_client_action');
      payload.append('form_type', type);

      var activeForm = form;
      var activePanel = '';

      setLoading(form, true);

      fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: payload
      })
        .then(function (response) {
          return response.text().then(function (body) {
            var json = null;
            try {
              json = body ? JSON.parse(body) : null;
            } catch (e) {
              throw new Error('Unable to save.');
            }
            return { json: json, status: response.status };
          });
        })
        .then(function (result) {
          var json = result && result.json ? result.json : null;
          var success = !!(json && json.success);
          var data = json && json.data ? json.data : {};
          var message = data && data.message ? data.message : (success ? 'Saved.' : 'Unable to save.');

          if (data.panel) {
            activePanel = data.panel;
          }
          if (data.panel && data.panel_html) {
            var replaced = false;
            if (data.panel === 'profile') {
              replaced = replaceProfileFormOnly(data.panel_html);
            }
            if (!replaced) {
              replacePanel(data.panel, data.panel_html);
            }
          }

          activeForm = resolveLiveForm(activePanel, type, form);
          setFeedback(activeForm, message, success);
        })
        .catch(function () {
          activeForm = resolveLiveForm(activePanel, type, form);
          setFeedback(activeForm, 'Unable to save. Please refresh and try again.', false);
        })
        .finally(function () {
          activeForm = resolveLiveForm(activePanel, type, form);
          setLoading(activeForm, false);
        });
    });
  });
})();
