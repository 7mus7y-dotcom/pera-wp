(function () {
  function initPhoneCountrySelect(select) {
    if (!select || select.dataset.phoneCountryEnhanced === '1') return;
    select.dataset.phoneCountryEnhanced = '1';
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('select[data-phone-country-select="1"]').forEach(initPhoneCountrySelect);
  });
})();
