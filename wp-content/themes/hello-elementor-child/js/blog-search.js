(function () {
  'use strict';

  var settings = window.peraBlogSearch || {};
  var form = document.querySelector('[data-blog-search-form]');
  var results = document.querySelector('[data-blog-results]');

  if (!form || !results || !settings.ajax_url || !settings.action || !settings.nonce || !window.fetch) {
    return;
  }

  var input = form.querySelector('input[type="search"][name="s"]');
  var sortInput = form.querySelector('[data-blog-sort-input]');
  var count = form.querySelector('[data-blog-search-count]');
  var debounceTimer = null;
  var controller = null;

  function getActiveSort() {
    var activeSort = sortInput ? sortInput.value : '';
    var activeSortLink = document.querySelector('.blog-sort__link.is-active[href]');

    if (activeSortLink) {
      try {
        activeSort = new URL(activeSortLink.href, window.location.href).searchParams.get('sort') || activeSort;
      } catch (error) {
        activeSort = activeSort || '';
      }
    }

    return activeSort;
  }

  function getPageFromUrl(url) {
    try {
      var parsed = new URL(url, window.location.href);
      var paged = parsed.searchParams.get('paged') || parsed.searchParams.get('page');
      var match = parsed.pathname.match(/\/page\/(\d+)\/?$/);

      if (!paged && match) {
        paged = match[1];
      }

      return Math.max(1, parseInt(paged || '1', 10));
    } catch (error) {
      return 1;
    }
  }

  function updateUrl(searchTerm, sort, paged) {
    if (!window.history || !window.history.replaceState) {
      return;
    }

    var url = new URL(window.location.href);

    if (searchTerm) {
      url.searchParams.set('s', searchTerm);
    } else {
      url.searchParams.delete('s');
    }

    if (sort && sort !== 'published') {
      url.searchParams.set('sort', sort);
    } else {
      url.searchParams.delete('sort');
    }

    url.searchParams.delete('paged');
    url.searchParams.delete('page');

    if (paged && paged > 1) {
      url.searchParams.set('paged', String(paged));
    }

    window.history.replaceState({}, '', url.toString());
  }

  function setLoading(isLoading) {
    form.classList.toggle('is-loading', isLoading);
    results.setAttribute('aria-busy', isLoading ? 'true' : 'false');
  }

  function runSearch(paged) {
    var searchTerm = input ? input.value.trim() : '';
    var sort = getActiveSort();
    var body = new FormData(form);

    body.set('action', settings.action);
    body.set('nonce', settings.nonce);
    body.set('s', searchTerm);
    body.set('sort', sort);
    body.set('paged', String(paged || 1));
    body.set('archive_type', form.dataset.archiveType || 'home');
    body.set('archive_id', form.dataset.archiveId || '0');
    body.set('archive_year', form.dataset.archiveYear || '0');
    body.set('archive_month', form.dataset.archiveMonth || '0');
    body.set('archive_day', form.dataset.archiveDay || '0');
    body.set('base_url', form.getAttribute('action') || window.location.href);

    if (controller) {
      controller.abort();
    }

    controller = new AbortController();
    setLoading(true);

    return fetch(settings.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
      signal: controller.signal
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Blog search request failed.');
        }

        return response.json();
      })
      .then(function (json) {
        if (!json || !json.success || !json.data) {
          throw new Error('Blog search returned an invalid response.');
        }

        results.innerHTML = (json.data.grid_html || '') + (json.data.pagination_html || '');

        if (count) {
          count.textContent = json.data.count_text || '';
        }

        updateUrl(searchTerm, sort, paged || 1);
      })
      .catch(function (error) {
        if (error.name === 'AbortError') {
          return;
        }

        form.submit();
      })
      .finally(function () {
        setLoading(false);
      });
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    window.clearTimeout(debounceTimer);
    runSearch(1);
  });

  if (input) {
    input.addEventListener('input', function () {
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(function () {
        runSearch(1);
      }, 250);
    });
  }

  results.addEventListener('click', function (event) {
    var link = event.target.closest('.posts-pagination a[href]');

    if (!link || !results.contains(link)) {
      return;
    }

    event.preventDefault();
    window.clearTimeout(debounceTimer);
    runSearch(getPageFromUrl(link.href));
  });
})();
