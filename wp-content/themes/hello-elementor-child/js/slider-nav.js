document.addEventListener('DOMContentLoaded', function () {
  var navButtons = document.querySelectorAll('.cards-slider-nav[data-slider-target]');

  navButtons.forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();

      var targetId = button.getAttribute('data-slider-target');
      if (!targetId) return;

      var slider = document.getElementById(targetId);
      if (!slider) return;

      var direction = button.classList.contains('cards-slider-nav--prev') ? -1 : 1;
      var step = Math.max(220, Math.round(slider.clientWidth * 0.85));

      slider.scrollBy({
        left: direction * step,
        behavior: 'smooth'
      });
    });
  });
});
