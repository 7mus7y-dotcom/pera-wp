(function () {
	'use strict';

	function request(data) {
		var body = new URLSearchParams(data);
		return fetch(peraMLQueue.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: body.toString()
		}).then(function (response) { return response.json(); });
	}

	function statusText(status) {
		if (status.complete) return '✅ Complete';
		if (!status.existing) return '— Not translated';
		if (status.stale.length) return '⚠ ' + status.current + ' current / ' + status.stale.length + ' stale';
		return '⚠ ' + status.existing + '/' + status.applicable + ' translated';
	}

	function start(container, event) {
		event.preventDefault();
		if (container.dataset.running === '1') return;
		container.dataset.running = '1';
		var button = container.querySelector('.pera-ml-queue-button');
		var output = container.querySelector('.pera-ml-queue-fields');
		var summary = container.querySelector('.pera-ml-queue-status');
		var languageName = container.dataset.languageName;
		summary.style.whiteSpace = 'pre-line';
		button.disabled = true;
		output.textContent = '';
		summary.textContent = 'Preparing translation…';
		var common = {post_id: container.dataset.postId, language: container.dataset.language, nonce: container.dataset.nonce};
		request(Object.assign({action: 'pera_ml_translation_queue', mode: button.dataset.mode}, common)).then(function (inventory) {
			if (!inventory.success) throw inventory;
			var retryFields = container.dataset.retryFields ? JSON.parse(container.dataset.retryFields) : [];
			var fields = retryFields.length ? retryFields.filter(function (field) { return inventory.applicable_fields.indexOf(field) !== -1; }) : inventory.fields;
			var failed = [];
			var completed = 0;
			var chain = Promise.resolve();
			fields.forEach(function (field) {
				chain = chain.then(function () {
					summary.textContent = 'Translating ' + (completed + 1) + ' / ' + fields.length + '\nCurrently: ' + field;
					return request(Object.assign({action: 'pera_ml_translate_field', field: field}, common)).then(function (result) {
						completed++;
						var line = document.createElement('div');
						line.textContent = (result.success ? '✓ ' : '✗ ') + field;
						if (!result.success) failed.push(field);
						output.appendChild(line);
					}).catch(function () {
						completed++; failed.push(field);
						var line = document.createElement('div'); line.textContent = '✗ ' + field; output.appendChild(line);
					});
				});
			});
			return chain.then(function () {
				return request(Object.assign({action: 'pera_ml_translation_queue', mode: 'complete'}, common)).then(function (fresh) {
					if (fresh.success) summary.textContent = statusText(fresh.status);
					else summary.textContent = completed + ' / ' + fields.length + ' complete';
					if (failed.length) summary.textContent += '\nFailed: ' + failed.join(', ');
					container.dataset.retryFields = failed.length ? JSON.stringify(failed) : '';
					button.dataset.mode = fresh.success && fresh.status.complete ? 'regenerate' : 'complete';
					button.textContent = (button.dataset.mode === 'regenerate' ? 'Regenerate ' : 'Retry missing ') + languageName;
				});
			});
		}).catch(function (error) {
			summary.textContent = 'Translation request failed' + (error && error.error_code ? ': ' + error.error_code : '.');
		}).finally(function () { button.disabled = false; container.dataset.running = '0'; });
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-pera-ml-queue]').forEach(function (container) {
			container.querySelector('.pera-ml-queue-button').addEventListener('click', start.bind(null, container));
		});
	});
}());
