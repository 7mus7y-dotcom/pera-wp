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

// Formatting stays in bounded plain-text fields; only fixed syntax is rendered as HTML.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-format-editor]').forEach(function (editor) {
    var field=editor.querySelector('textarea');
    function format(kind) {
      var start=field.selectionStart,end=field.selectionEnd;
      if(start===end) { field.focus(); return; }
      var text=field.value.slice(start,end),open='',close='';
      if(kind==='bold') { open=close='**'; }
      else if(kind==='italic') { open=close='*'; }
      else if(kind==='small' || kind==='large') { open='['+kind+']'; close='[/'+kind+']'; }
      else { text=text.replace(/\*\*([^*]+)\*\*|\*([^*]+)\*|\[(?:small|large)\]([\s\S]*?)\[\/(?:small|large)\]/g,function (_,a,b,c) { return a||b||c||''; }); }
      var replacement=open+text+close;
      if(field.value.length-(end-start)+replacement.length>field.maxLength) return;
      field.setRangeText(replacement,start,end,'select');
      var select=editor.closest('form').querySelector('[data-text-format]');
      select.value='basic_v1'; select.dispatchEvent(new Event('change',{bubbles:true}));
      field.dispatchEvent(new Event('input',{bubbles:true})); field.focus();
    }
    editor.querySelectorAll('[data-format]').forEach(function (button) {
      button.addEventListener('mousedown',function (event) { event.preventDefault(); });
      button.addEventListener('click',function () { format(button.dataset.format); });
    });
    editor.querySelector('[data-format-size]').addEventListener('change',function (event) { if(event.target.value) format(event.target.value); event.target.value=''; });
  });
});
