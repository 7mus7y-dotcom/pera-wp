(function () {
  function initDistrictFaqRepeater() {
    var wrapper = document.getElementById('district-page-faqs-wrapper');
    var addButton = document.getElementById('pera-add-faq-row');
    var form = wrapper ? wrapper.closest('form') : null;

    if (!wrapper || !addButton || !form) {
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

    function collectRowsForSubmit() {
      var rows = [];
      var inputs = wrapper.querySelectorAll('.pera-district-faq-row');

      inputs.forEach(function (row) {
        var questionInput = row.querySelector('input[name^="district_page_faqs["][name$="[question]"]');
        var answerInput = row.querySelector('textarea[name^="district_page_faqs["][name$="[answer]"]');
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

    function ensurePayloadInput() {
      var payload = form.querySelector('input[name="district_page_faqs_payload"]');
      if (!payload) {
        payload = document.createElement('input');
        payload.type = 'hidden';
        payload.name = 'district_page_faqs_payload';
        form.appendChild(payload);
      }
      return payload;
    }

    function dropFaqInputNames() {
      var namedInputs = wrapper.querySelectorAll('input[name^="district_page_faqs["], textarea[name^="district_page_faqs["]');
      namedInputs.forEach(function (el) {
        el.setAttribute('data-pera-original-name', el.getAttribute('name') || '');
        el.removeAttribute('name');
      });
    }

    function markPayloadReady(isReady) {
      form.setAttribute('data-pera-faq-payload-ready', isReady ? '1' : '0');
    }

    form.addEventListener('submit', function () {
      var rows = collectRowsForSubmit();
      var payloadInput = ensurePayloadInput();
      var payloadJson = '';

      markPayloadReady(false);

      try {
        payloadJson = JSON.stringify(rows);
      } catch (error) {
        return;
      }

      if (typeof payloadJson !== 'string') {
        return;
      }

      payloadInput.value = payloadJson;
      markPayloadReady(true);

      // Prevent max_input_vars truncation from large FAQ repeaters only when payload is safely set.
      dropFaqInputNames();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDistrictFaqRepeater);
  } else {
    initDistrictFaqRepeater();
  }
})();
