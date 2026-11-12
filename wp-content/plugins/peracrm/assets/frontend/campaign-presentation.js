/* The operator must review and approve a replacement footer before saving. */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-campaign-editor]').forEach(function (form) {
    form.addEventListener('click', function (event) {
      var button = event.target.closest('[data-footer-template]');
      if (!button || !form.contains(button)) return;
      var template = form.querySelector('[data-campaign-footer-template]');
      var field = form.querySelector('textarea[name="document[footer]"]');
      var approval = form.querySelector('input[name="document[footer_approved]"]');
      if (!template || !field || !approval) return;
      field.value = template.content.textContent;
      approval.checked = false;
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.focus();
    });
  });
});
