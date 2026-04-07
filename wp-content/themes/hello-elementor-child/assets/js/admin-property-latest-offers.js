(function ($) {
  'use strict';

  function getInitialNextIndex($tbody) {
    var maxIndex = -1;

    $tbody.find('tr.pera-latest-offers-row').each(function () {
      var inputName = $(this).find('input[name^="pera_latest_offers["], textarea[name^="pera_latest_offers["]').first().attr('name') || '';
      var match = inputName.match(/^pera_latest_offers\[(\d+)\]/);

      if (match) {
        maxIndex = Math.max(maxIndex, parseInt(match[1], 10));
      }
    });

    return maxIndex + 1;
  }

  function getNextIndex($wrap, $tbody) {
    var nextIndex = $wrap.data('peraLatestOffersNextIndex');

    if (typeof nextIndex === 'undefined') {
      nextIndex = getInitialNextIndex($tbody);
    }

    $wrap.data('peraLatestOffersNextIndex', nextIndex + 1);

    return nextIndex;
  }

  function addRow($wrap) {
    var $tbody = $wrap.find('[data-pera-latest-offers-rows]');
    var template = $('#tmpl-pera-latest-offers-row').html() || '';
    var index = getNextIndex($wrap, $tbody);
    var rowHtml = template.replace(/__index__/g, String(index));

    $tbody.append(rowHtml);
  }

  function openMediaFrame($row) {
    var frame = wp.media({
      title: 'Select floor plan (JPG)',
      library: { type: ['image/jpeg'] },
      button: { text: 'Use this image' },
      multiple: false
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      var label = attachment.title || attachment.filename || ('Attachment #' + attachment.id);

      $row.find('.pera-floor-plan-id').val(attachment.id);
      $row.find('.pera-floor-plan-label').text(label);
      $row.find('.pera-floor-plan-preview').html(
        attachment.url
          ? '<a href="' + attachment.url + '" target="_blank" rel="noopener noreferrer">View</a>'
          : ''
      );
      $row.find('.pera-floor-plan-remove').prop('disabled', false);
    });

    frame.open();
  }

  $(document).on('click', '[data-pera-latest-offers-add-row]', function (e) {
    e.preventDefault();
    addRow($(this).closest('.pera-latest-offers-wrap'));
  });

  $(document).on('click', '.pera-latest-offers-delete-row', function (e) {
    e.preventDefault();

    var $tbody = $(this).closest('tbody');
    $(this).closest('tr.pera-latest-offers-row').remove();

    if (!$tbody.find('tr.pera-latest-offers-row').length) {
      addRow($tbody.closest('.pera-latest-offers-wrap'));
    }
  });

  $(document).on('click', '.pera-floor-plan-select', function (e) {
    e.preventDefault();
    openMediaFrame($(this).closest('tr.pera-latest-offers-row'));
  });

  $(document).on('click', '.pera-floor-plan-remove', function (e) {
    e.preventDefault();

    var $row = $(this).closest('tr.pera-latest-offers-row');
    $row.find('.pera-floor-plan-id').val('');
    $row.find('.pera-floor-plan-label').text('No file selected');
    $row.find('.pera-floor-plan-preview').empty();
    $(this).prop('disabled', true);
  });
})(jQuery);
