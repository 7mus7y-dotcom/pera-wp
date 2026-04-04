(function () {
  function normalize(value) {
    return String(value || '').toLowerCase().trim();
  }

  function optionIndex(option) {
    var iso = option.getAttribute('data-country-iso') || '';
    var extra = option.getAttribute('data-country-search') || '';
    return normalize(option.textContent + ' ' + option.value + ' ' + iso + ' ' + extra);
  }

  function initPhoneCountrySelect(select) {
    if (!select || select.dataset.phoneCountryEnhanced === '1') return;
    select.dataset.phoneCountryEnhanced = '1';

    var wrapper = document.createElement('div');
    wrapper.className = 'phone-country-search-wrap';

    var input = document.createElement('input');
    input.type = 'search';
    input.className = 'phone-country-search';
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('placeholder', 'Search country or code');
    input.setAttribute('aria-label', 'Search country code');

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(input);
    wrapper.appendChild(select);

    var options = Array.prototype.slice.call(select.options).map(function (opt) {
      return { node: opt, index: optionIndex(opt) };
    });

    function applyFilter(query) {
      var q = normalize(query);
      var hasVisible = false;

      options.forEach(function (entry) {
        var visible = q === '' || entry.index.indexOf(q) !== -1;
        entry.node.hidden = !visible;
        entry.node.disabled = !visible;
        if (visible) hasVisible = true;
      });

      if (!hasVisible) {
        options.forEach(function (entry) {
          entry.node.hidden = false;
          entry.node.disabled = false;
        });
      }
    }

    input.addEventListener('input', function () {
      applyFilter(input.value);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('select[data-phone-country-select="1"]').forEach(initPhoneCountrySelect);
  });
})();
