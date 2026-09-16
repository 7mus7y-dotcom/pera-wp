(function () {
  'use strict';

  function initializeVideoModal() {
    var modal = document.querySelector('[data-pera-offer-video-modal]');
    if (!modal || modal.getAttribute('data-pera-offer-video-ready') === 'true') return;
    modal.setAttribute('data-pera-offer-video-ready', 'true');

    var dialog = modal.querySelector('[role="dialog"]');
    var player = modal.querySelector('[data-pera-offer-video-player]');
    var caption = modal.querySelector('[data-pera-offer-video-caption]');
    var returnFocus = null;

    function openModal(button) {
      var url = button.getAttribute('data-video-url') || '';
      var text = button.getAttribute('data-video-text') || '';
      if (!url) return;

      player.pause();
      player.removeAttribute('src');
      player.load();
      player.src = url;
      caption.textContent = text;
      caption.hidden = text === '';
      returnFocus = button;
      modal.hidden = false;
      document.body.classList.add('pera-offer-video-modal-open');
      dialog.focus();
    }

    function closeModal() {
      if (modal.hidden) return;

      player.pause();
      player.currentTime = 0;
      player.removeAttribute('src');
      player.load();
      caption.textContent = '';
      caption.hidden = true;
      modal.hidden = true;
      document.body.classList.remove('pera-offer-video-modal-open');
      if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
      returnFocus = null;
    }

    document.addEventListener('click', function (event) {
      var openButton = event.target.closest('[data-pera-offer-video-open]');
      if (openButton) {
        event.preventDefault();
        openModal(openButton);
        return;
      }

      if (event.target.closest('[data-pera-offer-video-close]')) closeModal();
    });

    document.addEventListener('keydown', function (event) {
      if (modal.hidden) return;

      if (event.key === 'Escape') {
        closeModal();
        return;
      }

      if (event.key !== 'Tab') return;

      var focusable = Array.prototype.slice.call(
        dialog.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), video[controls], [tabindex]:not([tabindex="-1"])')
      );
      if (!focusable.length) {
        event.preventDefault();
        dialog.focus();
        return;
      }

      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
        event.preventDefault();
        first.focus();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeVideoModal, { once: true });
  } else {
    initializeVideoModal();
  }
})();
