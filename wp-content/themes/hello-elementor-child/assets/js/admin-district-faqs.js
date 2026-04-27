(function () {
  function initDistrictFaqRepeater() {
    var wrapper = document.getElementById('district-page-faqs-wrapper');
    var addButton = document.getElementById('pera-add-faq-row');

    if (!wrapper || !addButton) {
      return;
    }

    function getNextIndex() {
      var next = parseInt(wrapper.getAttribute('data-next-index') || '0', 10);
      if (Number.isNaN(next) || next < 0) {
        next = 0;
      }
      wrapper.setAttribute('data-next-index', String(next + 1));
      return next;
    }

    function buildRow(index) {
      var row = document.createElement('div');
      row.className = 'pera-district-faq-row';
      row.style.marginBottom = '12px';
      row.style.padding = '12px';
      row.style.border = '1px solid #dcdcde';
      row.style.background = '#fff';

      row.innerHTML = '' +
        '<p><label><strong>Question</strong></label>' +
        '<input type="text" class="widefat" name="district_page_faqs[' + index + '][question]" value="" /></p>' +
        '<p><label><strong>Answer</strong></label>' +
        '<textarea class="widefat" rows="4" name="district_page_faqs[' + index + '][answer]"></textarea></p>' +
        '<p><button type="button" class="button-link-delete pera-remove-faq-row">Remove FAQ</button></p>';

      return row;
    }

    addButton.addEventListener('click', function () {
      wrapper.appendChild(buildRow(getNextIndex()));
    });

    wrapper.addEventListener('click', function (event) {
      var target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      if (!target.classList.contains('pera-remove-faq-row')) {
        return;
      }

      event.preventDefault();
      var row = target.closest('.pera-district-faq-row');
      if (!row) {
        return;
      }

      row.remove();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDistrictFaqRepeater);
  } else {
    initDistrictFaqRepeater();
  }
})();
