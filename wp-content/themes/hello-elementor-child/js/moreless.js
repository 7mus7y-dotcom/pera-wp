(function () {
  var blocks = Array.prototype.slice.call(document.querySelectorAll('[data-moreless]'));

  if (!blocks.length) {
    return;
  }

  function updateButton(block, buttons, isCollapsed) {
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
    var wasCollapsed = block.getAttribute('data-collapsed') !== 'false';

    if (!wasCollapsed) {
      block.setAttribute('data-collapsed', 'true');
    }

    var needsToggle = content.scrollHeight > content.clientHeight + 8;

    if (!wasCollapsed) {
      block.setAttribute('data-collapsed', 'false');
    }

    buttons.forEach(function (button) {
      button.hidden = !needsToggle;
    });
  }

  blocks.forEach(function (block) {
    var content = block.querySelector('[data-moreless-content]');
    var buttons = Array.prototype.slice.call(block.querySelectorAll('[data-moreless-toggle]'));

    if (!content || !buttons.length) {
      return;
    }

    var startCollapsed = block.getAttribute('data-collapsed') !== 'false';

    updateButton(block, buttons, startCollapsed);

    window.requestAnimationFrame(function () {
      updateToggleVisibility(block, content, buttons);
    });
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-moreless-toggle]');

    if (!button) {
      return;
    }

    event.preventDefault();

    var block = button.closest('[data-moreless]');

    if (!block) {
      return;
    }

    var content = block.querySelector('[data-moreless-content]');
    var buttons = Array.prototype.slice.call(block.querySelectorAll('[data-moreless-toggle]'));

    if (!content || !buttons.length) {
      return;
    }

    var isCollapsed = block.getAttribute('data-collapsed') !== 'false';

    updateButton(block, buttons, !isCollapsed);
    updateToggleVisibility(block, content, buttons);
  });

  window.addEventListener('resize', function () {
    blocks.forEach(function (block) {
      var content = block.querySelector('[data-moreless-content]');
      var buttons = Array.prototype.slice.call(block.querySelectorAll('[data-moreless-toggle]'));

      if (!content || !buttons.length) {
        return;
      }

      updateToggleVisibility(block, content, buttons);
    });
  }, { passive: true });
})();
