
(function () {
  var header = document.querySelector('#site-header.peracrm-shell-header');
  var crmPage = document.querySelector('.crm-page');
  var rafId = 0;

  function syncScrolledState() {
    if (!header) {
      return;
    }
    header.classList.toggle('is-scrolled', window.scrollY > 8);
  }

  function readHeaderOffset() {
    if (!header) {
      return 0;
    }
    return Math.max(0, Math.round(header.getBoundingClientRect().bottom));
  }

  function syncStickyOffsetVar() {
    if (!crmPage) {
      return;
    }
    crmPage.style.setProperty('--crm-shell-sticky-offset', readHeaderOffset() + 'px');
  }

  function scheduleStickyOffsetSync() {
    if (rafId) {
      return;
    }
    rafId = window.requestAnimationFrame(function () {
      rafId = 0;
      syncStickyOffsetVar();
    });
  }

  syncScrolledState();
  syncStickyOffsetVar();
  window.addEventListener('scroll', syncScrolledState, { passive: true });
  window.addEventListener('scroll', scheduleStickyOffsetSync, { passive: true });
  window.addEventListener('resize', scheduleStickyOffsetSync, { passive: true });
})();

(function () {
  var form = document.querySelector('[data-peracrm-header-search]');
  var ajaxUrl = window.peraCrmData && window.peraCrmData.ajaxUrl ? window.peraCrmData.ajaxUrl : '';
  var nonce = window.peraCrmData && window.peraCrmData.headerSearchNonce ? window.peraCrmData.headerSearchNonce : '';

  if (!form || !ajaxUrl || !nonce) {
    return;
  }

  var input = form.querySelector('[data-peracrm-header-search-input]');
  var resultsEl = form.querySelector('[data-peracrm-header-search-results]');
  var debounceTimer = 0;
  var activeIndex = -1;
  var results = [];
  var requestId = 0;

  if (!input || !resultsEl || !window.fetch || !window.FormData) {
    return;
  }

  // One shared CRM search input, permanently docked on small screens.
  // Keep its original node and AJAX listeners: never clone or reparent it.
  (function setupMobileSearchPosition() {
    if (!document.querySelector('.crm-page') || !window.matchMedia) {
      return;
    }
    var mobile = window.matchMedia('(max-width: 767px)');
    var viewport = window.visualViewport;
    var scheduled = false;

    function positionSearch() {
      scheduled = false;
      var enabled = mobile.matches;
      form.classList.toggle('is-crm-search-docked', enabled);
      // On mobile browsers with a visual viewport, lift the dock above an
      // onscreen keyboard instead of leaving it underneath the keyboard.
      var keyboardSpace = 0;
      if (enabled && viewport && document.activeElement === input) {
        var obstruction = window.innerHeight - viewport.height - viewport.offsetTop;
        keyboardSpace = obstruction > 130 ? Math.max(0, obstruction) : 0;
      }
      form.style.setProperty('--crm-search-keyboard-offset', keyboardSpace + 'px');
    }

    function schedulePosition() {
      if (!scheduled) {
        scheduled = true;
        window.requestAnimationFrame(positionSearch);
      }
    }

    schedulePosition();
    window.addEventListener('resize', schedulePosition, { passive: true });
    input.addEventListener('focus', schedulePosition);
    input.addEventListener('blur', schedulePosition);
    if (viewport) {
      viewport.addEventListener('resize', schedulePosition, { passive: true });
      viewport.addEventListener('scroll', schedulePosition, { passive: true });
    }
    if (mobile.addEventListener) {
      mobile.addEventListener('change', schedulePosition);
    } else if (mobile.addListener) {
      mobile.addListener(schedulePosition);
    }
  })();

  function setExpanded(isExpanded) {
    input.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
  }

  function setBusy(isBusy) {
    if (isBusy) {
      form.setAttribute('aria-busy', 'true');
    } else {
      form.removeAttribute('aria-busy');
    }
  }

  function clearResults() {
    results = [];
    activeIndex = -1;
    resultsEl.textContent = '';
    resultsEl.hidden = true;
    setExpanded(false);
  }

  function setActive(index) {
    var items = Array.prototype.slice.call(resultsEl.querySelectorAll('[data-peracrm-header-search-result]'));
    activeIndex = items.length ? Math.max(0, Math.min(index, items.length - 1)) : -1;

    items.forEach(function (item, itemIndex) {
      var isActive = itemIndex === activeIndex;
      item.classList.toggle('is-active', isActive);
      item.setAttribute('aria-selected', isActive ? 'true' : 'false');
      if (isActive) {
        item.focus({ preventScroll: true });
      }
    });
  }

  function appendText(parent, className, text) {
    if (!text) {
      return null;
    }

    var el = document.createElement('span');
    el.className = className;
    el.textContent = String(text);
    parent.appendChild(el);
    return el;
  }

  function renderStatus(message) {
    results = [];
    activeIndex = -1;
    resultsEl.textContent = '';
    var status = document.createElement('div');
    status.className = 'peracrm-header-search__status';
    status.setAttribute('role', 'status');
    status.textContent = message;
    resultsEl.appendChild(status);
    resultsEl.hidden = false;
    setExpanded(true);
  }

  function renderResults(items) {
    results = Array.isArray(items) ? items : [];
    activeIndex = -1;
    resultsEl.textContent = '';

    if (!results.length) {
      renderStatus('No clients found.');
      return;
    }

    var list = document.createElement('div');
    list.className = 'peracrm-header-search__list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'CRM client search results');

    results.forEach(function (item, index) {
      var url = item && item.url ? String(item.url) : '';
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'peracrm-header-search__result';
      button.setAttribute('role', 'option');
      button.setAttribute('aria-selected', 'false');
      button.setAttribute('data-peracrm-header-search-result', '');
      button.setAttribute('data-result-index', String(index));

      var title = document.createElement('span');
      title.className = 'peracrm-header-search__result-title';
      title.textContent = item && item.title ? String(item.title) : 'Untitled client';
      button.appendChild(title);

      var meta = document.createElement('span');
      meta.className = 'peracrm-header-search__result-meta';
      appendText(meta, 'peracrm-header-search__result-chip', item && item.type_label ? item.type_label : '');
      appendText(meta, 'peracrm-header-search__result-chip', item && item.stage_label ? item.stage_label : '');
      appendText(meta, 'peracrm-header-search__result-contact', item && item.email ? item.email : '');
      appendText(meta, 'peracrm-header-search__result-contact', item && item.phone ? item.phone : '');
      if (meta.childNodes.length) {
        button.appendChild(meta);
      }

      button.addEventListener('click', function () {
        if (url) {
          window.location.assign(url);
        }
      });

      list.appendChild(button);
    });

    resultsEl.appendChild(list);
    resultsEl.hidden = false;
    setExpanded(true);
  }

  function parseSearchResponse(response) {
    return response.text().then(function (body) {
      var json = null;
      try {
        json = body ? JSON.parse(body) : null;
      } catch (e) {
        json = null;
      }

      if (!response.ok || !json || !json.success) {
        throw new Error('Unable to search clients.');
      }

      return json.data && Array.isArray(json.data.results) ? json.data.results : [];
    });
  }

  function requestSearch(term) {
    var currentRequestId = ++requestId;
    var payload = new window.FormData();
    payload.append('action', 'peracrm_header_search');
    payload.append('nonce', String(nonce));
    payload.append('q', term);

    setBusy(true);

    window.fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload
    })
      .then(parseSearchResponse)
      .then(function (items) {
        if (currentRequestId !== requestId) {
          return;
        }
        renderResults(items);
      })
      .catch(function () {
        if (currentRequestId !== requestId) {
          return;
        }
        renderStatus('Unable to search. Press Enter to view matching clients.');
      })
      .finally(function () {
        if (currentRequestId === requestId) {
          setBusy(false);
        }
      });
  }

  input.addEventListener('input', function () {
    var term = input.value.trim();
    window.clearTimeout(debounceTimer);

    if (term.length < 2) {
      requestId += 1;
      setBusy(false);
      clearResults();
      return;
    }

    debounceTimer = window.setTimeout(function () {
      requestSearch(term);
    }, 250);
  });

  input.addEventListener('keydown', function (event) {
    var items = Array.prototype.slice.call(resultsEl.querySelectorAll('[data-peracrm-header-search-result]'));

    if (event.key === 'Escape') {
      clearResults();
      input.focus({ preventScroll: true });
      return;
    }

    if (!items.length) {
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setActive(activeIndex < 0 ? 0 : activeIndex + 1);
      return;
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActive(activeIndex < 0 ? items.length - 1 : activeIndex - 1);
      return;
    }

    if (event.key === 'Enter' && activeIndex >= 0 && results[activeIndex] && results[activeIndex].url) {
      event.preventDefault();
      window.location.assign(String(results[activeIndex].url));
    }
  });

  resultsEl.addEventListener('keydown', function (event) {
    var items = Array.prototype.slice.call(resultsEl.querySelectorAll('[data-peracrm-header-search-result]'));

    if (event.key === 'Escape') {
      clearResults();
      input.focus({ preventScroll: true });
      return;
    }

    if (!items.length) {
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setActive(activeIndex < 0 ? 0 : activeIndex + 1);
      return;
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActive(activeIndex < 0 ? items.length - 1 : activeIndex - 1);
      return;
    }

    if (event.key === 'Enter' && activeIndex >= 0 && results[activeIndex] && results[activeIndex].url) {
      event.preventDefault();
      window.location.assign(String(results[activeIndex].url));
    }
  });

  document.addEventListener('click', function (event) {
    if (event.target && form.contains(event.target)) {
      return;
    }
    clearResults();
  });
})();


(function () {
  var noteWraps = Array.prototype.slice.call(document.querySelectorAll('[data-crm-note-wrap]'));
  if (!noteWraps.length) {
    return;
  }

  function closeWrap(wrap) {
    if (!wrap) {
      return;
    }
    var trigger = wrap.querySelector('[data-crm-note-trigger]');
    var popover = wrap.querySelector('[data-crm-note-popover]');
    wrap.classList.remove('is-open');
    if (trigger) {
      trigger.setAttribute('aria-expanded', 'false');
    }
    if (popover) {
      popover.hidden = true;
    }
  }

  function openWrap(wrap) {
    if (!wrap) {
      return;
    }
    var trigger = wrap.querySelector('[data-crm-note-trigger]');
    var popover = wrap.querySelector('[data-crm-note-popover]');
    wrap.classList.add('is-open');
    if (trigger) {
      trigger.setAttribute('aria-expanded', 'true');
    }
    if (popover) {
      popover.hidden = false;
    }
  }

  function closeOthers(activeWrap) {
    noteWraps.forEach(function (wrap) {
      if (wrap !== activeWrap) {
        closeWrap(wrap);
      }
    });
  }

  noteWraps.forEach(function (wrap) {
    var trigger = wrap.querySelector('[data-crm-note-trigger]');
    if (!trigger) {
      return;
    }

    wrap.addEventListener('mouseenter', function () {
      closeOthers(wrap);
      openWrap(wrap);
    });

    wrap.addEventListener('mouseleave', function () {
      closeWrap(wrap);
    });

    trigger.addEventListener('click', function (event) {
      event.preventDefault();
      var isOpen = wrap.classList.contains('is-open');
      closeOthers(wrap);
      if (isOpen) {
        closeWrap(wrap);
      } else {
        openWrap(wrap);
      }
    });
  });

  document.addEventListener('click', function (event) {
    var insideWrap = event.target && event.target.closest ? event.target.closest('[data-crm-note-wrap]') : null;
    if (!insideWrap) {
      noteWraps.forEach(closeWrap);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      noteWraps.forEach(closeWrap);
    }
  });
})();


(function () {
  var clientPage = document.querySelector('.crm-page--client-view');
  if (!clientPage) {
    return;
  }

  var stickyName = clientPage.querySelector('.crm-client-sticky-name');
  if (!stickyName) {
    return;
  }

  function syncStickyNameVisibility() {
    stickyName.classList.toggle('is-hidden', window.scrollY <= 320);
  }

  syncStickyNameVisibility();
  window.addEventListener('scroll', syncStickyNameVisibility, { passive: true });
})();

(function () {
  var wrappers = document.querySelectorAll('[data-crm-client-name-edit]');
  if (!wrappers.length) {
    return;
  }

  wrappers.forEach(function (wrapper) {
    var display = wrapper.querySelector('[data-crm-client-name-display]');
    var form = wrapper.querySelector('[data-crm-client-name-edit-form]');
    var trigger = wrapper.querySelector('[data-crm-client-name-edit-trigger]');
    var cancel = wrapper.querySelector('[data-crm-client-name-edit-cancel]');
    var input = form ? form.querySelector('input[name="peracrm_client_name"]') : null;
    var originalValue = input ? input.value : '';

    if (!display || !form || !trigger || !cancel || !input) {
      return;
    }

    trigger.addEventListener('click', function () {
      originalValue = input.value;
      display.hidden = true;
      form.hidden = false;
      input.focus();
      input.select();
    });

    cancel.addEventListener('click', function () {
      input.value = originalValue;
      form.hidden = true;
      display.hidden = false;
      trigger.focus();
    });
  });
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
    var timelineBlock = block.closest('.crm-client-timeline');

    buttons.forEach(function (button, index) {
      var isBottomToggle = button.classList.contains('archive-hero-desc__toggle--bottom');
      if (!needsToggle || (timelineBlock && index > 0)) {
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
    var scope = root.closest('[data-crm-view-scope], [data-crm-clients-workspace], .crm-layout__main, main, body') || document;
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
        button.classList.toggle('btn--blue', active);
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
    var headerConfig = {};

    headers.forEach(function (button) {
      var key = String(button.getAttribute('data-sort') || '').trim();
      if (!key) {
        return;
      }
      headerConfig[key] = {
        type: String(button.getAttribute('data-sort-type') || '').trim(),
        emptyLast: button.getAttribute('data-sort-empty-last') === 'true'
      };
    });

    function toComparable(row, key, config) {
      var value = String(row.getAttribute('data-' + key) || '').trim();
      var normalized = value.toLowerCase();
      var type = config && config.type ? config.type : '';

      if (type === 'number') {
        var num = Number(value);
        return { empty: value === '' || Number.isNaN(num), value: Number.isNaN(num) ? 0 : num };
      }

      if (type === 'date') {
        var ts = Number(value);
        if (!Number.isNaN(ts) && ts > 0) {
          return { empty: false, value: ts };
        }
        var parsedTs = Date.parse(value);
        return { empty: value === '' || Number.isNaN(parsedTs), value: Number.isNaN(parsedTs) ? 0 : parsedTs };
      }

      if (key === 'created' || key === 'updated') {
        var maybeTs = Number(value);
        if (!Number.isNaN(maybeTs) && maybeTs > 0) {
          return { empty: false, value: maybeTs };
        }
      }

      return { empty: normalized === '', value: normalized };
    }

    function compare(a, b, key, dir) {
      var config = headerConfig[key] || { type: '', emptyLast: false };
      var left = toComparable(a, key, config);
      var right = toComparable(b, key, config);
      var direction = dir === 'desc' ? -1 : 1;

      if (config.emptyLast && left.empty !== right.empty) {
        return left.empty ? 1 : -1;
      }

      if (typeof left.value === 'number' && typeof right.value === 'number') {
        return (left.value - right.value) * direction;
      }

      return String(left.value).localeCompare(String(right.value), undefined, { sensitivity: 'base' }) * direction;
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
  var data = window.peraCrmData || {};
  var ajaxUrl = data.ajaxUrl ? String(data.ajaxUrl) : '';
  var createNonce = data.createPortfolioNonce ? String(data.createPortfolioNonce) : '';
  var updateNonce = data.updatePortfolioNonce ? String(data.updatePortfolioNonce) : '';

  function resolvePortfolioSection(fromNode) {
    if (!fromNode || !fromNode.closest) {
      return document.querySelector('[data-crm-linked-properties][data-crm-panel="properties"]');
    }

    return fromNode.closest('[data-crm-linked-properties][data-crm-panel="properties"]')
      || fromNode.closest('[data-crm-linked-properties]')
      || fromNode.closest('.crm-client-subsection')
      || document.querySelector('[data-crm-linked-properties][data-crm-panel="properties"]');
  }

  function ensureInlineFeedback(section) {
    if (!section || !section.querySelector) {
      return null;
    }

    var outputRow = section.querySelector('[data-crm-portfolio-output]');
    if (!outputRow) {
      return null;
    }

    var feedback = outputRow.querySelector('[data-crm-portfolio-inline-feedback]');
    if (!feedback) {
      feedback = document.createElement('small');
      feedback.className = 'text-sm';
      feedback.setAttribute('data-crm-portfolio-inline-feedback', '');
      outputRow.appendChild(feedback);
    }

    return feedback;
  }

  function setInlineFeedback(section, message) {
    var feedback = ensureInlineFeedback(section);
    if (feedback) {
      feedback.textContent = message ? String(message) : '';
    }
  }

  function openDialog(dialog) {
    if (!dialog) {
      return;
    }

    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
      return;
    }

    dialog.setAttribute('open', 'open');
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

  function applyPortfolioResponse(section, responseData) {
    if (!section || !responseData) {
      return;
    }

    var outputRow = section.querySelector('[data-crm-portfolio-output]');
    var urlInput = section.querySelector('[data-crm-portfolio-url]');
    var expiresNote = section.querySelector('[data-crm-portfolio-expires]');
    var updateButton = section.querySelector('[data-crm-portfolio-update]');
    var openLink = section.querySelector('[data-crm-portfolio-open-link]');
    var portfolioUrl = String(responseData.url || '');

    if (outputRow) {
      outputRow.hidden = false;
    }

    if (urlInput) {
      urlInput.value = portfolioUrl;
    }

    if (openLink) {
      if (portfolioUrl) {
        openLink.setAttribute('href', portfolioUrl);
        openLink.hidden = false;
        openLink.removeAttribute('aria-hidden');
        openLink.removeAttribute('tabindex');
      } else {
        openLink.setAttribute('href', '#');
        openLink.hidden = true;
        openLink.setAttribute('aria-hidden', 'true');
        openLink.setAttribute('tabindex', '-1');
      }
    }

    if (expiresNote) {
      expiresNote.textContent = responseData.expires_label ? 'Expires: ' + String(responseData.expires_label) : '';
    }

    if (updateButton && responseData.post_id) {
      updateButton.hidden = false;
      updateButton.setAttribute('data-portfolio-post-id', String(responseData.post_id));
    }
  }

  function copyWithFallback(input, value) {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      return navigator.clipboard.writeText(value).catch(function () {
        input.focus();
        input.select();
        if (!document.execCommand('copy')) {
          throw new Error('Clipboard access failed.');
        }
      });
    }

    input.focus();
    input.select();
    if (!document.execCommand('copy')) {
      return Promise.reject(new Error('Clipboard access failed.'));
    }

    return Promise.resolve();
  }

  document.addEventListener('click', function (event) {
    var openButton = event.target.closest('[data-crm-portfolio-open]');
    if (openButton) {
      event.preventDefault();
      var dialogId = openButton.getAttribute('data-crm-portfolio-open');
      var dialog = dialogId ? document.getElementById(dialogId) : null;
      if (!dialog) {
        return;
      }

      var section = resolvePortfolioSection(openButton);
      dialog.__crmPortfolioSection = section || null;

      var feedback = dialog.querySelector('[data-crm-portfolio-feedback]');
      if (feedback) {
        feedback.textContent = '';
      }

      openDialog(dialog);
      return;
    }

    var closeButton = event.target.closest('[data-crm-portfolio-close]');
    if (closeButton) {
      event.preventDefault();
      var closeDialogId = closeButton.getAttribute('data-crm-portfolio-close');
      closeDialog(closeDialogId ? document.getElementById(closeDialogId) : null);
      return;
    }

    var copyButton = event.target.closest('[data-crm-portfolio-copy]');
    if (copyButton) {
      event.preventDefault();
      var copySection = resolvePortfolioSection(copyButton);
      var urlInput = copySection ? copySection.querySelector('[data-crm-portfolio-url]') : null;
      if (!urlInput) {
        setInlineFeedback(copySection, 'Portfolio URL input is unavailable.');
        return;
      }

      var value = String(urlInput.value || '').trim();
      if (!value) {
        setInlineFeedback(copySection, 'No portfolio URL to copy yet.');
        return;
      }

      copyWithFallback(urlInput, value)
        .then(function () {
          setInlineFeedback(copySection, 'Portfolio URL copied.');
        })
        .catch(function () {
          setInlineFeedback(copySection, 'Unable to copy automatically. Select the URL and copy manually.');
        });
      return;
    }

    var updateButton = event.target.closest('[data-crm-portfolio-update]');
    if (!updateButton) {
      return;
    }

    event.preventDefault();
    var section = resolvePortfolioSection(updateButton);
    var clientId = updateButton.getAttribute('data-client-id') || (section ? (section.getAttribute('data-client-id') || '') : '');
    var portfolioPostId = updateButton.getAttribute('data-portfolio-post-id') || '';

    if (!ajaxUrl || !updateNonce) {
      setInlineFeedback(section, 'Portfolio update is unavailable: missing frontend configuration.');
      return;
    }

    if (!clientId || !portfolioPostId) {
      setInlineFeedback(section, 'Portfolio update is unavailable: missing portfolio identifier.');
      return;
    }

    var payload = new window.FormData();
    payload.append('action', 'peracrm_update_portfolio_token');
    payload.append('nonce', updateNonce);
    payload.append('client_id', clientId);
    payload.append('portfolio_post_id', portfolioPostId);

    var originalLabel = updateButton.textContent;
    updateButton.disabled = true;
    updateButton.textContent = 'Updating…';
    setInlineFeedback(section, '');

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

        applyPortfolioResponse(section, json.data);
        setInlineFeedback(section, 'Portfolio link updated.');
      })
      .catch(function (error) {
        setInlineFeedback(section, error && error.message ? error.message : 'Unable to update portfolio link.');
      })
      .finally(function () {
        updateButton.disabled = false;
        updateButton.textContent = originalLabel;
      });
  });

  document.addEventListener('submit', function (event) {
    var form = event.target && event.target.matches ? (event.target.matches('[data-crm-portfolio-form]') ? event.target : null) : null;
    if (!form) {
      return;
    }

    event.preventDefault();
    var dialog = form.closest('dialog');
    var section = dialog && dialog.__crmPortfolioSection ? dialog.__crmPortfolioSection : resolvePortfolioSection(form);
    var submitButton = form.querySelector('[data-crm-portfolio-submit]');
    var feedback = form.querySelector('[data-crm-portfolio-feedback]');
    var clientId = section ? (section.getAttribute('data-client-id') || '') : '';

    if (!ajaxUrl || !createNonce) {
      if (feedback) {
        feedback.textContent = 'Portfolio creation is unavailable: missing frontend configuration.';
      }
      return;
    }

    if (!section || !clientId) {
      if (feedback) {
        feedback.textContent = 'Portfolio creation is unavailable: unable to resolve this client section.';
      }
      return;
    }

    var rows = Array.prototype.slice.call(section.querySelectorAll('[data-crm-portfolio-row]'));
    var propertyIds = rows.map(function (row) {
      return String(row.getAttribute('data-property-id') || '').trim();
    }).filter(Boolean);

    var formData = new window.FormData(form);
    var payload = new window.FormData();
    payload.append('action', 'peracrm_create_portfolio_token');
    payload.append('nonce', createNonce);
    payload.append('client_id', clientId);
    payload.append('expiry', String(formData.get('expiry') || '').trim());
    payload.append('portfolio_property_ids', propertyIds.join(','));

    var originalLabel = submitButton ? submitButton.textContent : '';
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Generating…';
    }
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

        applyPortfolioResponse(section, json.data);
        setInlineFeedback(section, 'Portfolio link generated.');

        if (feedback) {
          feedback.textContent = 'Portfolio link generated.';
        }

        closeDialog(dialog);
      })
      .catch(function (error) {
        if (feedback) {
          feedback.textContent = error && error.message ? error.message : 'Unable to generate portfolio link.';
        }
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalLabel;
        }
      });
  });

  document.addEventListener('click', function (event) {
    var dialog = event.target && event.target.matches && event.target.matches('dialog.crm-danger-dialog') ? event.target : null;
    if (dialog && event.target === dialog) {
      closeDialog(dialog);
    }
  });
})();

(function () {
  var data = window.peraCrmData || {};
  var ajaxUrl = data.ajaxUrl ? String(data.ajaxUrl) : '';
  var addNonce = data.themePortfolioAddNonce ? String(data.themePortfolioAddNonce) : '';
  var removeNonce = data.themePortfolioRemoveNonce ? String(data.themePortfolioRemoveNonce) : '';
  var refreshNonce = data.themePortfolioRefreshNonce ? String(data.themePortfolioRefreshNonce) : '';

  function getSection(node) {
    if (!node || !node.closest) {
      return document.querySelector('[data-crm-theme-portfolio]');
    }

    return node.closest('[data-crm-theme-portfolio]') || document.querySelector('[data-crm-theme-portfolio]');
  }

  function setFeedback(section, message) {
    if (!section) {
      return;
    }

    var feedback = section.querySelector('[data-crm-theme-portfolio-feedback]');
    if (!feedback) {
      return;
    }

    feedback.textContent = message ? String(message) : '';
    feedback.classList.remove('is-success', 'is-error');
  }

  function setFeedbackState(section, message, type) {
    if (!section) {
      return;
    }

    var feedback = section.querySelector('[data-crm-theme-portfolio-feedback]');
    if (!feedback) {
      return;
    }

    feedback.textContent = message ? String(message) : '';
    feedback.classList.remove('is-success', 'is-error');
    if (type === 'success') {
      feedback.classList.add('is-success');
    } else if (type === 'error') {
      feedback.classList.add('is-error');
    }
  }

  function shortenUrlForDisplay(url) {
    var value = String(url || '').trim();
    if (!value) {
      return '';
    }

    var cleaned = value.replace(/^https?:\/\//i, '').replace(/^www\./i, '');
    return cleaned.length > 54 ? cleaned.slice(0, 51) + '…' : cleaned;
  }

  function syncCreateUpdateButtons(section, hasUrl) {
    if (!section) {
      return;
    }

    var createBtn = section.querySelector('[data-crm-theme-portfolio-create]');
    var updateBtn = section.querySelector('[data-crm-theme-portfolio-refresh]');

    if (createBtn) {
      createBtn.hidden = !!hasUrl;
    }

    if (updateBtn) {
      updateBtn.hidden = !hasUrl;
    }
  }

  function copyValue(input, value) {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      return navigator.clipboard.writeText(value);
    }

    input.focus();
    input.select();
    if (!document.execCommand('copy')) {
      return Promise.reject(new Error('Clipboard access failed.'));
    }

    return Promise.resolve();
  }

  function postAction(payload) {
    return fetch(ajaxUrl, {
      method: 'POST',
      body: payload,
      credentials: 'same-origin'
    }).then(function (response) { return response.json(); });
  }

  function applyUrlState(section, json) {
    var data = json && json.data ? json.data : {};
    var url = String(data.url || '');
    var urlInput = section.querySelector('[data-crm-theme-portfolio-url]');
    var openLink = section.querySelector('[data-crm-theme-portfolio-open-link]');
    var updated = section.querySelector('[data-crm-theme-portfolio-updated]');

    if (urlInput) {
      urlInput.setAttribute('data-crm-theme-portfolio-url-full', url);
      urlInput.value = shortenUrlForDisplay(url);
      urlInput.setAttribute('title', url);
    }

    if (openLink) {
      if (url) {
        openLink.hidden = false;
        openLink.removeAttribute('aria-hidden');
        openLink.removeAttribute('tabindex');
        openLink.setAttribute('href', url);
      } else {
        openLink.hidden = true;
        openLink.setAttribute('aria-hidden', 'true');
        openLink.setAttribute('tabindex', '-1');
        openLink.setAttribute('href', '#');
      }
    }

    if (updated) {
      updated.textContent = data.updated_label ? 'Updated: ' + String(data.updated_label) : '';
    }

    syncCreateUpdateButtons(section, !!url);
  }

  document.addEventListener('submit', function (event) {
    var form = event.target && event.target.matches ? (event.target.matches('[data-crm-theme-portfolio-add-form]') ? event.target : null) : null;
    if (!form) {
      return;
    }

    event.preventDefault();
    var section = getSection(form);
    var clientId = section ? String(section.getAttribute('data-client-id') || '') : '';
    var propertyId = form.querySelector('[data-crm-property-id]') ? String(form.querySelector('[data-crm-property-id]').value || '') : '';

    if (!ajaxUrl || !addNonce || !clientId || !propertyId) {
      setFeedback(section, 'Unable to add property right now.');
      return;
    }

    var payload = new window.FormData();
    payload.append('action', 'peracrm_theme_portfolio_add_property');
    payload.append('nonce', addNonce);
    payload.append('client_id', clientId);
    payload.append('property_id', propertyId);

    setFeedback(section, 'Adding property…');
    postAction(payload)
      .then(function (json) {
        if (!json || !json.success) {
          throw new Error(json && json.data && json.data.message ? String(json.data.message) : 'Unable to add property.');
        }

        window.location.reload();
      })
      .catch(function (error) {
        setFeedback(section, error && error.message ? error.message : 'Unable to add property.');
      });
  });

  document.addEventListener('click', function (event) {
    var copyBtn = event.target.closest('[data-crm-theme-portfolio-copy]');
    if (copyBtn) {
      event.preventDefault();
      var copySection = getSection(copyBtn);
      var input = copySection ? copySection.querySelector('[data-crm-theme-portfolio-url]') : null;
      var value = input ? String(input.getAttribute('data-crm-theme-portfolio-url-full') || input.value || '').trim() : '';
      if (!input || !value) {
        setFeedbackState(copySection, 'No URL to copy yet.', 'error');
        return;
      }

      copyValue(input, value)
        .then(function () { setFeedbackState(copySection, 'Portfolio URL copied.', 'success'); })
        .catch(function () { setFeedbackState(copySection, 'Unable to copy automatically.', 'error'); });
      return;
    }

    var removeBtn = event.target.closest('[data-crm-theme-portfolio-remove]');
    if (removeBtn) {
      event.preventDefault();
      var removeSection = getSection(removeBtn);
      var clientId = removeSection ? String(removeSection.getAttribute('data-client-id') || '') : '';
      var propertyId = String(removeBtn.getAttribute('data-property-id') || '');
      if (!ajaxUrl || !removeNonce || !clientId || !propertyId) {
        setFeedback(removeSection, 'Unable to remove property right now.');
        return;
      }

      var removePayload = new window.FormData();
      removePayload.append('action', 'peracrm_theme_portfolio_remove_property');
      removePayload.append('nonce', removeNonce);
      removePayload.append('client_id', clientId);
      removePayload.append('property_id', propertyId);

      setFeedback(removeSection, 'Removing property…');
      postAction(removePayload)
        .then(function (json) {
          if (!json || !json.success) {
            throw new Error(json && json.data && json.data.message ? String(json.data.message) : 'Unable to remove property.');
          }

          window.location.reload();
        })
        .catch(function (error) {
          setFeedback(removeSection, error && error.message ? error.message : 'Unable to remove property.');
        });
      return;
    }

    var refreshBtn = event.target.closest('[data-crm-theme-portfolio-refresh], [data-crm-theme-portfolio-create]');
    if (!refreshBtn) {
      return;
    }

    event.preventDefault();
    var section = getSection(refreshBtn);
    var clientId = section ? String(section.getAttribute('data-client-id') || '') : '';
    if (!ajaxUrl || !refreshNonce || !clientId) {
      setFeedbackState(section, 'Unable to refresh URL right now.', 'error');
      return;
    }

    var payload = new window.FormData();
    payload.append('action', 'peracrm_refresh_theme_portfolio_url');
    payload.append('nonce', refreshNonce);
    payload.append('client_id', clientId);

    var original = refreshBtn.textContent;
    var actionLabel = refreshBtn.matches('[data-crm-theme-portfolio-create]') ? 'Creating…' : 'Updating…';
    var successLabel = refreshBtn.matches('[data-crm-theme-portfolio-create]') ? 'Portfolio URL generated.' : 'Portfolio URL updated.';
    refreshBtn.disabled = true;
    refreshBtn.textContent = actionLabel;
    setFeedback(section, '');

    postAction(payload)
      .then(function (json) {
        if (!json || !json.success) {
          throw new Error(json && json.data && json.data.message ? String(json.data.message) : 'Unable to refresh URL.');
        }

        applyUrlState(section, json);
        setFeedbackState(section, successLabel, 'success');
      })
      .catch(function (error) {
        setFeedbackState(section, error && error.message ? error.message : 'Unable to refresh URL.', 'error');
      })
      .finally(function () {
        refreshBtn.disabled = false;
        refreshBtn.textContent = original;
      });
  });

  document.addEventListener('DOMContentLoaded', function () {
    var section = getSection(document.body);
    var input = section ? section.querySelector('[data-crm-theme-portfolio-url]') : null;
    if (!section || !input) {
      return;
    }

    var fullUrl = String(input.getAttribute('data-crm-theme-portfolio-url-full') || input.value || '').trim();
    input.setAttribute('data-crm-theme-portfolio-url-full', fullUrl);
    input.value = shortenUrlForDisplay(fullUrl);
    input.setAttribute('title', fullUrl);
    syncCreateUpdateButtons(section, !!fullUrl);
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

          if (data.workspace_panels || data.panel_html) {
            document.dispatchEvent(new CustomEvent('peracrm:workspace-panels', { detail: data.workspace_panels || {} }));
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

// Withdrawal remains a deliberate native form POST; this prompt adds staff context.
document.addEventListener('click', function (event) {
  if (event.target.closest('[data-subscriber-withdraw]') && !window.confirm('Withdraw marketing permission and add a global unsubscribe suppression?')) {
    event.preventDefault();
  }
});

// Plugin-owned campaign drafts, bounded selectors and isolated previews.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-campaign-editor]').forEach(function (form) {
    var status = form.querySelector('[data-campaign-status]');
    var dirty = Boolean(document.querySelector('[data-campaign-error]'));
    var submitted = false;
    var blocks = form.querySelector('[data-campaign-blocks]');
    var senders = form.querySelector('[data-campaign-senders]');
    var senderTemplate = senders && senders.firstElementChild ? senders.firstElementChild.cloneNode(true) : null;
    function changed(event) { if (event && event.target && !String(event.target.name || '').startsWith('document[')) return; dirty = true; if (status) status.textContent = 'Unsaved changes'; }
    function reindex(container, selector, key) {
      if (!container) return;
      var items = Array.from(container.querySelectorAll(selector));
      items.forEach(function (item, index) {
        item.querySelectorAll('[name]').forEach(function (field) { field.name = field.name.replace(new RegExp('\\[' + key + '\\]\\[\\d+\\]'), '[' + key + '][' + index + ']'); });
        var up = item.querySelector('[data-block-up]');
        var down = item.querySelector('[data-block-down]');
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === items.length - 1;
      });
      form.querySelectorAll(key === 'blocks' ? '[data-block-add]' : '[data-sender-add]').forEach(function (button) { button.disabled = items.length >= (key === 'blocks' ? 20 : 10); });
    }
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    form.addEventListener('click', function (event) {
      var button = event.target.closest('button');
      if (!button) return;
      var block = button.closest('[data-campaign-block]');
      if (button.hasAttribute('data-block-add') && blocks && blocks.children.length < 20) {
        var template = document.querySelector('[data-block-template="' + button.dataset.blockAdd + '"]');
        if (!template) return;
        blocks.appendChild(template.content.cloneNode(true));
        blocks.lastElementChild.querySelector('input:not([type=hidden]),textarea').focus();
      } else if (block && button.hasAttribute('data-block-remove')) {
        block.remove();
      } else if (block && button.hasAttribute('data-block-up') && block.previousElementSibling) {
        blocks.insertBefore(block, block.previousElementSibling);
      } else if (block && button.hasAttribute('data-block-down') && block.nextElementSibling) {
        blocks.insertBefore(block.nextElementSibling, block);
      } else if (button.hasAttribute('data-sender-add') && senders && senders.children.length < 10 && senderTemplate) {
        var sender = senderTemplate.cloneNode(true);
        sender.querySelectorAll('input').forEach(function (input) { input.value = input.type === 'checkbox' ? '1' : ''; input.checked = false; });
        senders.appendChild(sender);
        sender.querySelector('input').focus();
      } else if (button.hasAttribute('data-sender-remove')) {
        button.closest('[data-campaign-sender]').remove();
      } else return;
      reindex(blocks, '[data-campaign-block]', 'blocks');
      reindex(senders, '[data-campaign-sender]', 'senders');
      changed();
    });
    var tools = form.querySelector('[data-campaign-preview-tools]');
    if (tools) {
      var nextContent = null;
      var searchBinding = '';
      var cursor = '';
      var audienceGeneration = 0;
      var previewGeneration = 0;
      var searchGeneration = 0;
      var audienceNext = tools.querySelector('[data-audience-next]');
      var audienceResult = tools.querySelector('[data-audience-result]');
      function invalidateAudience(event) {
        if (event && event.target && !String(event.target.name || '').startsWith('document[')) return;
        audienceGeneration++; cursor = ''; audienceNext.hidden = true;
        audienceResult.textContent = 'Form changed. Save the current draft and restart audience preview.';
      }
      form.addEventListener('input', invalidateAudience);
      form.addEventListener('change', invalidateAudience);
      function request(operation, fields) {
        var data = new FormData();
        data.set('action', 'peracrm_campaign_preview'); data.set('nonce', tools.dataset.nonce); data.set('operation', operation);
        Object.keys(fields).forEach(function (key) { data.set(key, fields[key]); });
        return fetch(tools.dataset.endpoint, {method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store'})
          .then(function (response) { return response.json(); })
          .then(function (json) { if (!json.success) throw Error(json.data && json.data.message || 'Preview unavailable.'); return json.data; });
      }
      function documentModel() {
        var data = new FormData(form);
        var doc = {schema_version: 1, blocks: [], audience: {version: 1}};
        ['internal_name','subject','preheader','language','sender_id','introduction','main_text'].forEach(function (key) { doc[key] = String(data.get('document[' + key + ']') || ''); });
        ['language','topic'].forEach(function (key) { doc.audience[key] = String(data.get('document[audience][' + key + ']') || ''); });
        blocks.querySelectorAll('[data-campaign-block]').forEach(function (block) {
          var item = {};
          block.querySelectorAll('[name]').forEach(function (field) { var key = field.name.match(/\[([^\]]+)\]$/)[1]; item[key] = key === 'id' ? Number(field.value) : field.value; });
          doc.blocks.push(item);
        });
        return doc;
      }
      function search(after) {
        var generation = ++searchGeneration;
        var fields = {kind: tools.querySelector('[data-content-kind]').value, term: tools.querySelector('[data-content-term]').value, after: after || 0, language: form.elements['document[language]'].value};
        searchBinding = JSON.stringify([fields.kind,fields.term,fields.language]);
        var feedback = tools.querySelector('[data-content-feedback]');
        var results = tools.querySelector('[data-content-results]');
        tools.querySelector('[data-content-next]').hidden = true;
        feedback.textContent = 'Searching published content…'; results.replaceChildren();
        request('search', fields).then(function (data) {
          if (generation !== searchGeneration) return;
          feedback.textContent = data.notice + (data.items.length ? '' : ' No matching public content.');
          data.items.forEach(function (item) {
            var row = document.createElement('p');
            var label = document.createElement('span'); label.textContent = item.title + ' · ' + item.availability + ' ';
            var select = document.createElement('button'); select.type = 'button'; select.className = 'btn btn--ghost'; select.textContent = 'Select ' + item.type + ' #' + item.id;
            select.addEventListener('click', function () {
              if (blocks.children.length >= 20) { feedback.textContent = 'Use at most 20 blocks; remove one first.'; return; }
              var template = document.querySelector('[data-block-template="' + item.type + '"]');
              blocks.appendChild(template.content.cloneNode(true));
              var block = blocks.lastElementChild;
              block.querySelector('input[name$="[id]"]').value = String(item.id);
              block.querySelector('[data-content-id]').textContent = String(item.id);
              reindex(blocks,'[data-campaign-block]','blocks'); changed(); invalidateAudience();
            });
            row.append(label,select); results.appendChild(row);
          });
          nextContent = data.next; tools.querySelector('[data-content-next]').hidden = nextContent === null;
        }).catch(function (error) { if (generation === searchGeneration) feedback.textContent = error.message; });
      }
      tools.querySelector('[data-content-search]').addEventListener('click', function () { search(0); });
      tools.querySelector('[data-content-next]').addEventListener('click', function () {
        var current = JSON.stringify([tools.querySelector('[data-content-kind]').value,tools.querySelector('[data-content-term]').value,form.elements['document[language]'].value]);
        search(current === searchBinding ? nextContent : 0);
      });
      tools.querySelectorAll('[data-email-preview]').forEach(function (button) {
        button.addEventListener('click', function () {
          var generation = ++previewGeneration;
          var feedback = tools.querySelector('[data-preview-feedback]');
          feedback.textContent = 'Rendering current draft…';
          request('render', {document_json: JSON.stringify(documentModel())}).then(function (data) {
            if (generation !== previewGeneration) return;
            var mode = button.dataset.emailPreview;
            var container = tools.querySelector('[data-preview-container]');
            container.hidden = mode === 'text'; container.dataset.size = mode;
            tools.querySelector('[data-email-frame]').srcdoc = data.html;
            var text = tools.querySelector('[data-email-text]'); text.hidden = mode !== 'text'; text.textContent = data.text;
            var links = tools.querySelector('[data-preview-links]'); links.replaceChildren();
            var label = document.createElement('p'); label.textContent = 'Validated destinations — opens externally for staff verification:'; links.appendChild(label);
            data.destinations.forEach(function (destination) { var row = document.createElement('p'); var a = document.createElement('a'); a.href = destination.url; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.textContent = destination.label + ' · ' + destination.url; row.appendChild(a); links.appendChild(row); });
            feedback.textContent = 'Draft preview of form at ' + new Date().toISOString() + '. Preference placeholders only; domain/provider unverified. Refresh after edits.';
          }).catch(function (error) { if (generation === previewGeneration) feedback.textContent = error.message; });
        });
      });
      function audience(start) {
        if (dirty || !tools.dataset.id || tools.dataset.id === '0') { audienceResult.textContent = 'Save the current draft and rules before counting.'; return; }
        if (start) { cursor = ''; audienceGeneration++; }
        var generation = audienceGeneration;
        var revision = Number(form.elements.revision.value);
        audienceNext.hidden = true; audienceResult.textContent = 'Counting a bounded batch…';
        request('audience', {id: tools.dataset.id, revision: revision, rules_json: JSON.stringify(documentModel().audience), cursor: cursor}).then(function (data) {
          if (generation !== audienceGeneration) return;
          cursor = data.cursor;
          var exclusions = Object.keys(data.exclusions).map(function (key) { return key + ': ' + data.exclusions[key]; }).join('; ');
          audienceResult.textContent = (data.complete ? 'Complete' : 'Partial — continue counting') + ' · As of ' + data.as_of + ' UTC; checked through ' + data.checked_at + ' UTC. Total considered: ' + data.considered + '; eligible: ' + data.eligible + '; excluded — ' + exclusions + '. ' + data.notice;
          audienceNext.hidden = data.complete;
        }).catch(function (error) { if (generation === audienceGeneration) { cursor = ''; audienceResult.textContent = error.message; } });
      }
      tools.querySelector('[data-audience-start]').addEventListener('click', function () { audience(true); });
      audienceNext.addEventListener('click', function () { audience(false); });
      form.addEventListener('click', function (event) { if (event.target.closest('[data-block-add],[data-block-up],[data-block-down],[data-block-remove]')) invalidateAudience(); });
    }
    var freeze = document.querySelector('[data-campaign-freeze]');
    if (freeze && blocks) {
      var busy = false, proof = '', captureNonce = '', generation = 0, controller;
      var feedback = freeze.querySelector('[data-freeze-feedback]');
      var panel = freeze.querySelector('[data-freeze-panel]');
      var review = freeze.querySelector('[data-freeze-review]');
      var channel = typeof BroadcastChannel === 'function' ? new BroadcastChannel('peracrm-freeze-' + location.origin + '-' + freeze.dataset.id) : null;
      function focusFeedback() { feedback.focus({preventScroll:true}); feedback.scrollIntoView({block:'center',behavior:'instant'}); }
      function invalidateReview() { proof = ''; captureNonce = ''; review.replaceChildren(); }
      form.addEventListener('input', function (event) { if (String(event.target.name || '').startsWith('document[')) invalidateReview(); });
      form.addEventListener('change', function (event) { if (String(event.target.name || '').startsWith('document[')) invalidateReview(); });
      form.addEventListener('click', function (event) { if (event.target.closest('[data-block-add],[data-block-remove],[data-block-up],[data-block-down]')) invalidateReview(); });
      function controls(disabled) { freeze.querySelectorAll('button').forEach(function (button) { button.disabled = disabled; }); }
      if (channel) channel.onmessage = function () {
        generation++; if (controller) controller.abort(); busy = false; invalidateReview();
        controls(true); var reload = panel.querySelector('[data-freeze-action="state"]'); if (reload) reload.disabled = false;
        feedback.textContent = 'Another tab changed this campaign. Reload current state before acting.'; focusFeedback();
      };
      window.addEventListener('pagehide', function () { generation++; if (controller) controller.abort(); if (channel) channel.close(); });
      freeze.addEventListener('click', function (event) {
        var button = event.target.closest('[data-freeze-action]');
        if (!button || busy) return;
        var operation = button.dataset.freezeAction;
        if (['review','start','new_revision'].includes(operation) && dirty) { invalidateReview(); feedback.textContent = 'Save your changes before review, freeze or starting a new revision.'; focusFeedback(); return; }
        if (operation === 'start' && !proof) { feedback.textContent = 'Review the saved revision again before freezing.'; return; }
        if (operation === 'publish' && !window.confirm('Publish this complete immutable revision? This does not send anything.')) return;
        if (operation === 'cancel' && !window.confirm('Cancel this materialization permanently? A new saved revision will be required.')) return;
        var data = new FormData(form);
        data.set('action','peracrm_campaign_freeze'); data.set('nonce',freeze.dataset.nonce);
        data.set('service_nonce',freeze.dataset.serviceNonce); data.set('id',freeze.dataset.id); data.set('operation',operation);
        data.set('proof',proof); data.set('capture_nonce',captureNonce);
        var attempt = panel.querySelector('[data-freeze-attempt]');
        data.set('attempt',button.dataset.attempt || (attempt ? attempt.dataset.freezeAttempt : '0'));
        data.set('version',attempt ? attempt.dataset.freezeVersion : '0');
        data.set('before',button.dataset.before || '0'); data.set('after',button.dataset.after || '0');
        busy = true; controls(true); feedback.textContent = 'Processing one bounded request…';
        var failed = false; var current = ++generation; controller = new AbortController();
        fetch(freeze.dataset.endpoint,{method:'POST',body:data,credentials:'same-origin',cache:'no-store',signal:controller.signal})
          .then(function (response) { return response.json(); })
          .then(function (json) {
            if (current !== generation) return;
            if (!json.success) throw Error(json.data && json.data.message || 'Request failed. Reload current state.');
            if (json.data.reload) { submitted = true; location.reload(); return; }
            if (json.data.review_html) { review.innerHTML = json.data.review_html; proof = json.data.proof; captureNonce = json.data.capture_nonce; }
            else { panel.innerHTML = json.data.html; invalidateReview(); }
            feedback.textContent = operation === 'review' ? 'Saved revision reviewed. Review proof expires after 15 minutes; source or configuration changes require a fresh review.' : 'Server state loaded. Choose the next action explicitly.';
            if (channel && ['start','continue','resume','cancel','publish','new_revision'].includes(operation)) channel.postMessage('changed');
            focusFeedback();
          }).catch(function (error) {
            if (current !== generation) return;
            failed = true; invalidateReview(); feedback.textContent = error.message; focusFeedback();
            // Do not act on the old version following any error or lost response.
            controls(true); var reload = panel.querySelector('[data-freeze-action="state"]'); if (reload) reload.disabled = false;
          }).finally(function () { if (current === generation) { busy = false; if (!failed) controls(false); } });
      });
    }
    form.addEventListener('submit', function () { submitted = true; if (status) status.textContent = 'Saving…'; });
    window.addEventListener('beforeunload', function (event) { if (dirty && !submitted) { event.preventDefault(); event.returnValue = ''; } });
    reindex(blocks, '[data-campaign-block]', 'blocks');
    reindex(senders, '[data-campaign-sender]', 'senders');
  });
});

// Operational queue: one explicit bounded request, server version fences and stable schedule input.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-campaign-queue]').forEach(function (box) {
    var busy = false, stale = false, dirty = false, generation = 0;
    var panel = box.querySelector('[data-queue-panel]'), feedback = box.querySelector('[data-queue-feedback]');
    var schedule = box.querySelector('[data-queue-schedule]');
    var channel = typeof BroadcastChannel === 'function' ? new BroadcastChannel('peracrm-queue-' + location.origin + '-' + box.dataset.id) : null;
    function announce(message) { feedback.textContent = message; feedback.focus({preventScroll:true}); feedback.scrollIntoView({block:'center',behavior:'instant'}); }
    function controls() {
      box.querySelectorAll('[data-queue-action]').forEach(function (button) { button.disabled = busy || (stale && button.dataset.queueAction !== 'state'); });
    }
    if (schedule) schedule.addEventListener('input', function () { dirty = true; });
    if (channel) channel.onmessage = function () {
      // Do not abort an in-flight mutation: its durable result may already exist. Its version stays fenced.
      stale = true; generation++; controls();
      announce('Another tab changed this campaign queue. Reload current state before acting. Schedule input is retained.');
    };
    box.addEventListener('click', function (event) {
      var button = event.target.closest('[data-queue-action]');
      if (!button || busy || button.disabled) return;
      var operation = button.dataset.queueAction;
      if (operation === 'cancel' && !window.confirm('Cancel this job permanently? Its ledger and revision fence will remain. Accepted and unknown outcomes will never be reset.')) return;
      if (operation === 'schedule' && (!schedule || !schedule.value || !schedule.checkValidity())) {
        announce('Enter a valid future Istanbul date and time beside the schedule control.'); if (schedule) schedule.focus(); return;
      }
      var job = panel.querySelector('[data-queue-job]'), data = new FormData();
      data.set('action','peracrm_campaign_queue'); data.set('nonce',box.dataset.nonce); data.set('service_nonce',box.dataset.serviceNonce);
      data.set('id',box.dataset.id); data.set('operation',operation); data.set('job',job ? job.dataset.queueJob : '0');
      data.set('version',job ? job.dataset.queueVersion : '0'); data.set('revision',button.dataset.revision || '0');
      data.set('after',button.dataset.after || '0'); data.set('schedule',schedule ? schedule.value : '');
      if (operation === 'cancel') data.set('confirmed','cancel');
      var inputValue = schedule ? schedule.value : '', current = generation;
      busy = true; controls(); announce('Processing one bounded queue request…');
      fetch(box.dataset.endpoint,{method:'POST',body:data,credentials:'same-origin',cache:'no-store'})
        .then(function (response) { return response.json(); })
        .then(function (json) {
          if (current !== generation) return;
          if (!json.success) throw Error(json.data && json.data.message || 'Queue request failed. Reload current state.');
          if (json.data.url) { busy = false; location.assign(json.data.url); return; }
          panel.innerHTML = json.data.html; stale = false;
          var health = document.querySelector('[data-queue-health]');
          if (health && json.data.health_html) health.outerHTML = json.data.health_html;
          if (operation === 'schedule' && schedule.value === inputValue) dirty = false;
          var currentJob = panel.querySelector('[data-queue-job]');
          if (schedule && !dirty && currentJob) schedule.value = currentJob.dataset.queueScheduledValue;
          announce('Server state loaded.' + (dirty ? ' Unsaved schedule input retained; review it against the displayed server schedule before recording.' : ' Choose the next action explicitly.'));
          if (channel && ['continue','schedule','pause','resume','cancel'].includes(operation)) channel.postMessage('changed');
        }).catch(function (error) {
          if (current !== generation) return;
          stale = true; announce(error.message + ' Schedule input is retained. Reload current state before retrying.');
        }).finally(function () { busy = false; controls(); });
    });
    window.addEventListener('beforeunload', function (event) { if (dirty || busy) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pagehide', function () { if (channel) channel.close(); });
  });
});

// Native form submission remains usable without JavaScript; focus server feedback.
(function () {
  var page = document.querySelector('[data-readiness-page]');
  if (!page) { return; }
  var feedback = page.querySelector('[data-readiness-feedback]');
  if (feedback) { feedback.focus(); }
  var form = page.querySelector('[data-readiness-form]');
  if (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('button[type="submit"]');
      if (button) { button.disabled = true; button.textContent = 'Recording evidence…'; }
    });
  }
})();

// Client workspace preferences: server owns eligibility; DOM hiding keeps form values intact.
(function () {
  var manager = document.querySelector('[data-crm-manage-panels]');
  var feedback = document.querySelector('[data-crm-panels-feedback]');
  var ajaxUrl = window.peraCrmData && window.peraCrmData.ajaxUrl;
  if (!manager || !feedback || !ajaxUrl || !window.fetch) { return; }
  var busy = false;
  var pendingFragments = null;
  var hidden = [];
  var completePreferences = false;
  document.querySelectorAll('[data-crm-optional-panel]').forEach(function (panel) {
    if (panel.hidden) { hidden.push(panel.dataset.crmOptionalPanel); }
  });

  function applyState(next) {
    hidden = next.slice();
    var hiddenCount = 0;
    document.querySelectorAll('[data-crm-optional-panel]').forEach(function (panel) {
      panel.hidden = hidden.indexOf(panel.dataset.crmOptionalPanel) !== -1;
      if (panel.hidden) { hiddenCount++; }
    });
    manager.querySelectorAll('[data-crm-panel-toggle]').forEach(function (toggle) {
      toggle.checked = hidden.indexOf(toggle.dataset.crmPanelToggle) === -1;
    });
    manager.querySelectorAll('[data-crm-panel-state]').forEach(function (state) {
      state.textContent = hidden.indexOf(state.dataset.crmPanelState) === -1 ? feedback.dataset.visibleLabel : feedback.dataset.hiddenLabel;
    });
    manager.querySelector('[data-crm-panels-empty]').hidden = hiddenCount > 0;
  }

  function enableControls() {
    document.querySelectorAll('[data-crm-panel-hide]').forEach(function (button) {
      button.hidden = false;
      button.disabled = busy;
    });
    manager.querySelectorAll('[data-crm-panel-toggle], [data-crm-panels-restore]').forEach(function (control) {
      control.disabled = busy;
    });
    manager.setAttribute('aria-busy', busy ? 'true' : 'false');
  }

  function change(operation, key, target) {
    if (busy) { return Promise.resolve(false); }
    busy = true;
    var previous = hidden.slice();
    var next = operation === 'restore_all' ? [] : hidden.filter(function (value) { return value !== key; });
    if (operation === 'hide') { next.push(key); }
    applyState(next);
    enableControls();
    feedback.textContent = '';
    if (operation === 'hide' && target && target.matches('[data-crm-panel-hide]')) {
      manager.querySelector('.crm-section__title').focus({ preventScroll: true });
    }
    var payload = new window.FormData();
    payload.append('action', 'peracrm_client_panels');
    payload.append('client_id', manager.dataset.clientId);
    payload.append('nonce', manager.dataset.nonce);
    payload.append('operation', operation);
    payload.append('panel', key || '');
    // A timeout must also roll back optimistic state.
    var controller = window.AbortController ? new window.AbortController() : null;
    var timer = controller ? window.setTimeout(function () { controller.abort(); }, 15000) : null;
    return window.fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload, signal: controller ? controller.signal : undefined })
      .then(function (response) { return response.json(); })
      .then(function (json) {
        if (!json.success || !json.data || !Array.isArray(json.data.hidden)) {
          throw new Error(json.data && json.data.message ? json.data.message : feedback.dataset.error);
        }
        completePreferences = true;
        applyState(json.data.hidden);
        feedback.textContent = feedback.dataset.saved;
        return true;
      })
      .catch(function (error) {
        applyState(previous);
        feedback.textContent = error.name === 'AbortError' ? feedback.dataset.error : (error.message || feedback.dataset.error);
        feedback.focus({ preventScroll: true });
        return false;
      })
      .finally(function () {
        if (timer) { window.clearTimeout(timer); }
        busy = false;
        enableControls();
        if (pendingFragments) { reconcile(pendingFragments); pendingFragments = null; }
      });
  }

  function reconcile(fragments) {
    if (busy) { pendingFragments = fragments; return; }
    ['instructions', 'deals'].forEach(function (slot) {
      if (!fragments[slot]) { return; }
      var current = document.querySelector('[data-crm-panel-slot="' + slot + '"]');
      var parsed = new window.DOMParser().parseFromString(fragments[slot], 'text/html');
      var incoming = parsed.querySelector('[data-crm-panel-slot]');
      if (!current || !incoming) { return; }
      var oldPanel = current.querySelector('[data-crm-optional-panel]');
      var newPanel = incoming.querySelector('[data-crm-optional-panel]');
      var oldKey = oldPanel ? oldPanel.dataset.crmOptionalPanel : '';
      var newKey = newPanel ? newPanel.dataset.crmOptionalPanel : '';
      // Retain unsaved form contents when eligibility has not changed.
      if (oldKey !== newKey) { current.replaceWith(incoming); }
    });
    if (fragments.manage) {
      var parsed = new window.DOMParser().parseFromString(fragments.manage, 'text/html');
      var incoming = parsed.querySelector('[data-crm-manage-panels]');
      if (incoming) {
        manager.replaceWith(incoming);
        manager = incoming;
        // Include hidden preferences for newly eligible panels from the server.
        manager.querySelectorAll('[data-crm-panel-toggle]').forEach(function (toggle) {
          if (!completePreferences && !toggle.checked && hidden.indexOf(toggle.dataset.crmPanelToggle) === -1) { hidden.push(toggle.dataset.crmPanelToggle); }
        });
      }
    }
    applyState(hidden);
    enableControls();
  }

  document.addEventListener('peracrm:workspace-panels', function (event) { reconcile(event.detail); });
  document.addEventListener('click', function (event) {
    var hide = event.target.closest('[data-crm-panel-hide]');
    var restore = event.target.closest('[data-crm-panels-restore]');
    if (!hide && !restore) { return; }
    event.preventDefault();
    event.stopPropagation();
    change(restore ? 'restore_all' : 'hide', hide ? hide.dataset.crmPanelHide : '', hide || restore);
  });
  document.addEventListener('change', function (event) {
    var toggle = event.target.closest('[data-crm-panel-toggle]');
    if (toggle) { change(toggle.checked ? 'show' : 'hide', toggle.dataset.crmPanelToggle, toggle); }
  });

  function restoreTarget(hash) {
    if (!hash || hash === '#') { return; }
    var target;
    try { target = document.getElementById(decodeURIComponent(hash.slice(1))); } catch (error) { return; }
    var panel = target && target.closest('[data-crm-optional-panel]');
    if (!panel || !panel.hidden) { return; }
    return change('show', panel.dataset.crmOptionalPanel).then(function (ok) {
      if (ok) {
        var parent = target.parentElement;
        while (parent && parent !== panel.parentElement) {
          if (parent.tagName === 'DETAILS') { parent.open = true; }
          parent = parent.parentElement;
        }
        target.scrollIntoView({ block: 'start' });
        if (!target.matches('a, button, input, select, textarea, [tabindex]')) { target.setAttribute('tabindex', '-1'); }
        target.focus({ preventScroll: true });
      }
    });
  }
  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[href]');
    if (!link || link.target === '_blank') { return; }
    var url = new URL(link.href, window.location.href);
    if (url.origin === window.location.origin && url.pathname === window.location.pathname && url.search === window.location.search) {
      if (restoreTarget(url.hash)) { event.preventDefault(); }
    }
  });
  window.addEventListener('hashchange', function () { restoreTarget(window.location.hash); });
  applyState(hidden);
  enableControls();
  restoreTarget(window.location.hash);
  // Edit deal links are an explicit navigation to that workspace.
  if (new URL(window.location.href).searchParams.has('deal_id')) {
    var deal = document.querySelector('[data-crm-optional-panel="deals"]');
    if (deal && deal.hidden) { change('show', 'deals'); }
  }
})();


/* Overview new-lead carousel: native swipe/scroll with keyboard and chevrons. */
(function () {
  var root = document.querySelector('[data-crm-new-lead-carousel]');
  if (!root) return;
  var viewport = root.querySelector('[data-crm-carousel-viewport]');
  var track = root.querySelector('[data-crm-carousel-track]');
  var cards = track ? Array.prototype.slice.call(track.querySelectorAll('[data-crm-carousel-card]')) : [];
  var prev = root.querySelector('[data-crm-carousel-prev]');
  var next = root.querySelector('[data-crm-carousel-next]');
  var status = root.querySelector('[data-crm-carousel-status]');
  if (!viewport || !track || !cards.length) return;

  function visibleCount() {
    var first = cards[0].getBoundingClientRect();
    var itemWidth = first.width;
    var gap = parseFloat(window.getComputedStyle(track).gap) || 0;
    return Math.max(1, Math.min(cards.length, Math.floor((viewport.clientWidth + gap + 1) / (itemWidth + gap))));
  }
  function currentIndex() {
    var position = track.scrollLeft;
    var best = 0;
    var distance = Infinity;
    cards.forEach(function (card, i) {
      var d = Math.abs(card.offsetLeft - cards[0].offsetLeft - position);
      if (d < distance) { distance = d; best = i; }
    });
    return best;
  }
  function sync() {
    var first = currentIndex();
    var shown = visibleCount();
    var finalStart = Math.max(0, cards.length - shown);
    first = Math.min(first, finalStart);
    if (prev) prev.disabled = first <= 0;
    if (next) next.disabled = first >= finalStart;
    if (status) status.textContent = (first + 1) + '–' + Math.min(cards.length, first + shown) + ' of ' + cards.length;
  }
  function move(direction) {
    var shown = visibleCount();
    var target = Math.max(0, Math.min(cards.length - shown, currentIndex() + direction * shown));
    var left = cards[target].offsetLeft - cards[0].offsetLeft;
    track.scrollTo({ left: left, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }
  if (prev) prev.addEventListener('click', function () { move(-1); });
  if (next) next.addEventListener('click', function () { move(1); });
  viewport.addEventListener('keydown', function (e) {
    if (e.target.closest('a, button, input, select, textarea')) return;
    if (e.key === 'ArrowLeft') { e.preventDefault(); move(-1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); move(1); }
  });
  var raf = 0;
  track.addEventListener('scroll', function () {
    if (!raf) raf = window.requestAnimationFrame(function () { raf = 0; sync(); });
  }, { passive: true });
  window.addEventListener('resize', sync, { passive: true });
  sync();
})();


/* Confirm destructive property instruction actions before submitting. */
(function () {
  document.querySelectorAll('[data-crm-instruction-delete-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      var message = form.getAttribute('data-confirm-message') || 'Delete this property instruction permanently?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
})();
