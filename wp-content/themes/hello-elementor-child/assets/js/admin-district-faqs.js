(function () {
  function initDistrictFaqRepeater() {
    var wrapper = document.getElementById('district-page-faqs-wrapper');
    var addButton = document.getElementById('pera-add-faq-row');
    var form = wrapper ? wrapper.closest('form') : null;
    var payloadInput = document.getElementById('district-page-faqs-payload');

    if (!wrapper || !addButton || !form || !payloadInput) {
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

    function collectRowsForSubmit() {
      var rows = [];
      var faqRows = wrapper.querySelectorAll('.pera-district-faq-row');

      faqRows.forEach(function (row) {
        var questionInput = row.querySelector('input[type="text"]');
        var answerInput = row.querySelector('textarea');
        var question = questionInput ? questionInput.value.trim() : '';
        var answer = answerInput ? answerInput.value.trim() : '';

        if (!question || !answer) {
          return;
        }

        rows.push({
          question: question,
          answer: answer
        });
      });

      return rows;
    }

    function setPayloadFromRows() {
      var rows = collectRowsForSubmit();

      try {
        payloadInput.value = JSON.stringify(rows);
      } catch (error) {
        payloadInput.value = '';
      }

      return payloadInput.value;
    }

    function payloadIsReadyForSubmit() {
      var payloadValue = payloadInput.value;

      if (typeof payloadValue !== 'string' || payloadValue.length === 0) {
        return false;
      }

      if (payloadInput.form !== form) {
        return false;
      }

      try {
        return Array.isArray(JSON.parse(payloadValue));
      } catch (error) {
        return false;
      }
    }

    function dropFaqInputNames() {
      var namedInputs = wrapper.querySelectorAll('input[name^="district_page_faqs["], textarea[name^="district_page_faqs["]');
      namedInputs.forEach(function (el) {
        el.setAttribute('data-pera-original-name', el.getAttribute('name') || '');
        el.removeAttribute('name');
      });
    }

    addButton.addEventListener('click', function () {
      wrapper.appendChild(buildRow(getNextIndex()));
      setPayloadFromRows();
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
      setPayloadFromRows();
    });

    wrapper.addEventListener('input', setPayloadFromRows);
    wrapper.addEventListener('change', setPayloadFromRows);

    form.addEventListener('submit', function () {
      setPayloadFromRows();

      // Prevent max_input_vars truncation from large FAQ repeaters only when payload is safely set.
      if (payloadIsReadyForSubmit()) {
        dropFaqInputNames();
      }
    });

    setPayloadFromRows();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDistrictFaqRepeater);
  } else {
    initDistrictFaqRepeater();
  }
})();
