(function () {
  'use strict';
  function translate(row) {
    var data = new URLSearchParams({ action: 'pera_ml_health_translate', nonce: peraMLHealth.nonce, row: row.dataset.row });
    row.querySelector('button').disabled = true;
    return fetch(peraMLHealth.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: data.toString() })
      .then(function (response) { return response.json(); }).then(function (result) { if (!result.success) throw new Error(result.data && result.data.code || 'translation_failed'); row.querySelector('.status').textContent = 'Current'; row.classList.add('completed'); });
  }
  document.addEventListener('click', function (event) {
    if (event.target.matches('.pera-ml-health-one')) translate(event.target.closest('tr')).catch(function (error) { window.alert(error.message); event.target.disabled = false; });
    if (event.target.id === 'pera-ml-health-bulk') {
      var rows = Array.prototype.slice.call(document.querySelectorAll('.pera-ml-health-row:not(.completed)')), progress = document.getElementById('pera-ml-health-progress'), done = 0;
      event.target.disabled = true;
      rows.reduce(function (chain, row) { return chain.then(function () { return translate(row).then(function () { done++; progress.textContent = done + ' / ' + rows.length; }); }); }, Promise.resolve()).catch(function (error) { progress.textContent = error.message; }).then(function () { event.target.disabled = false; });
    }
  });
}());
