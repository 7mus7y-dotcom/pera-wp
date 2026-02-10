(function ($) {
  var originalEdit = inlineEditPost.edit;

  function collectDistrictIds(selectedId) {
    var ancestors = [];
    if (window.PERA_DISTRICT_ANCESTORS && window.PERA_DISTRICT_ANCESTORS[selectedId]) {
      ancestors = window.PERA_DISTRICT_ANCESTORS[selectedId];
    }

    var combined = [selectedId].concat(ancestors);
    var unique = [];

    $.each(combined, function (index, termId) {
      var numericId = parseInt(termId, 10);
      if (numericId && $.inArray(numericId, unique) === -1) {
        unique.push(numericId);
      }
    });

    return unique;
  }

  function updateBulkDistrictInputs($row) {
    var selected = parseInt($row.find('select[name="pera_bulk_district_term"]').val(), 10);

    $row.find('input[name="tax_input[district][]"]').remove();

    if (selected === -1 || isNaN(selected)) {
      return;
    }

    var $target = $row.find('.inline-edit-col-right');

    if (selected === 0) {
      $('<input />', {
        type: 'hidden',
        name: 'tax_input[district][]',
        value: ''
      }).appendTo($target);
      return;
    }

    var termIds = collectDistrictIds(selected);
    $.each(termIds, function (index, termId) {
      $('<input />', {
        type: 'hidden',
        name: 'tax_input[district][]',
        value: termId
      }).appendTo($target);
    });
  }

  function bindBulkEdit() {
    $(document).on('click', '.bulk-edit-row .button-primary', function () {
      var $row = $(this).closest('.bulk-edit-row');
      if ($row.length) {
        updateBulkDistrictInputs($row);
      }
    });
  }

  inlineEditPost.edit = function (id) {
    originalEdit.apply(this, arguments);

    var postId = typeof id === 'object' ? this.getId(id) : id;
    if (!postId) {
      return;
    }

    var $row = $('#post-' + postId);
    var districtId = parseInt($row.find('.pera-district-term').data('district-id'), 10) || 0;
    $('#edit-' + postId)
      .find('select[name="pera_district_term"]')
      .val(districtId);
  };

  $(bindBulkEdit);
})(jQuery);
