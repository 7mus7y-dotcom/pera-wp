document.addEventListener('DOMContentLoaded', function () {
  var navButtons = document.querySelectorAll('.cards-slider-nav[data-slider-target]');
  var sliderMap = new Map();

  navButtons.forEach(function (button) {
    var targetId = button.getAttribute('data-slider-target');
    if (!targetId) return;

    var slider = document.getElementById(targetId);
    if (!slider) return;

    if (!sliderMap.has(slider)) {
      sliderMap.set(slider, {
        prev: null,
        next: null
      });
    }

    var controls = sliderMap.get(slider);
    if (button.classList.contains('cards-slider-nav--prev')) {
      controls.prev = button;
    } else {
      controls.next = button;
    }

    button.addEventListener('click', function (event) {
      event.preventDefault();

      var direction = button.classList.contains('cards-slider-nav--prev') ? -1 : 1;
      var firstCard = slider.querySelector('.slider-card');
      var gap = parseFloat(window.getComputedStyle(slider).columnGap || window.getComputedStyle(slider).gap || '0') || 0;
      var cardStep = firstCard ? (firstCard.getBoundingClientRect().width + gap) : 0;
      var viewportStep = Math.round(slider.clientWidth * 0.85);
      var step = Math.max(220, Math.round(cardStep || viewportStep));

      slider.scrollBy({
        left: direction * step,
        behavior: 'smooth'
      });
    });
  });

  function syncButtons(slider, controls) {
    var maxScrollLeft = Math.max(0, slider.scrollWidth - slider.clientWidth);
    var atStart = slider.scrollLeft <= 4;
    var atEnd = slider.scrollLeft >= (maxScrollLeft - 4);

    if (controls.prev) {
      controls.prev.disabled = atStart;
      controls.prev.setAttribute('aria-disabled', String(atStart));
    }

    if (controls.next) {
      controls.next.disabled = atEnd;
      controls.next.setAttribute('aria-disabled', String(atEnd));
    }
  }

  sliderMap.forEach(function (controls, slider) {
    var update = function () {
      syncButtons(slider, controls);
    };

    slider.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  });
});
