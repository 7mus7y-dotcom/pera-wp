(function($){
	function setStatus(message){
		var container = document.getElementById('regenerate-thumbnails-diagnostics');
		var text = document.getElementById('regenerate-thumbnails-diagnostics-text');
		if(!container || !text){ return; }
		container.style.display = '';
		text.textContent = message;
	}

	function getSingleIdFromHash(){
		var match = String(window.location.hash || '').match(/#\/regenerate\/(\d+)/);
		return match ? match[1] : null;
	}

	setStatus('external diagnostics script executed');

	if (typeof window.regenerateThumbnails === 'undefined') {
		setStatus('info: optional global missing: regenerateThumbnails (continuing)');
	}

	if (typeof window.wp === 'undefined' || typeof window.wp.apiRequest !== 'function') {
		setStatus('required global missing: wp.apiRequest');
		return;
	}

	if (typeof window.wpApiSettings === 'undefined' || !window.wpApiSettings.nonce) {
		setStatus('required global missing: wpApiSettings.nonce');
		return;
	}

	function updateRouteStatus(){
		var id = getSingleIdFromHash();
		if(id){
			setStatus('route entered with attachment ID ' + id);
		}
	}

	updateRouteStatus();
	window.addEventListener('hashchange', updateRouteStatus);

	$(document).on('ajaxSend', function(event, jqXHR, settings){
		var id = getSingleIdFromHash();
		if(!id || !settings || !settings.url){ return; }
		if(settings.url.indexOf('/regenerate-thumbnails/v1/attachmentinfo/' + id) !== -1){
			setStatus('request started');
		}
	});

	$(document).on('ajaxSuccess', function(event, jqXHR, settings){
		var id = getSingleIdFromHash();
		if(!id || !settings || !settings.url){ return; }
		if(settings.url.indexOf('/regenerate-thumbnails/v1/attachmentinfo/' + id) !== -1){
			setStatus('request success');
		}
	});

	$(document).on('ajaxError', function(event, jqXHR, settings, errorThrown){
		var id = getSingleIdFromHash();
		if(!id || !settings || !settings.url){ return; }
		if(settings.url.indexOf('/regenerate-thumbnails/v1/attachmentinfo/' + id) !== -1){
			var message = 'request failed';
			if(jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message){
				message += ': ' + jqXHR.responseJSON.message;
			} else if (jqXHR && jqXHR.responseText) {
				message += ': ' + String(jqXHR.responseText).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 220);
			} else if (errorThrown) {
				message += ': ' + errorThrown;
			}
			setStatus(message);
		}
	});

	var missingState = {
		loading: false,
		cursor: 0,
		hasMore: false,
		itemsById: {},
		foundCount: 0,
		lastChecked: 0,
		totalChecked: 0,
		requestCount: 0,
		lastResultNotice: null
	};
	var MISSING_VISIBLE_LIMIT = 25;
	var MISSING_MAX_AUTO_REQUESTS = 12;

	function escHtml(value){
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function ensureMissingUiContainer(){
		var app = document.getElementById('regenerate-thumbnails-app');
		if(!app){ return null; }

		var existing = document.getElementById('regenerate-thumbnails-missing-ui');
		if(existing){ return existing; }

		var container = document.createElement('div');
		container.id = 'regenerate-thumbnails-missing-ui';
		container.className = 'card';
		app.appendChild(container);
		return container;
	}

	function getSelectedMissingIds(){
		return $('.regenthumbs-missing-select:checked').map(function(){
			return parseInt($(this).val(), 10);
		}).get().filter(function(id){
			return !Number.isNaN(id) && id > 0;
		});
	}

	function updateMissingSelectionUi(){
		var selectedCount = getSelectedMissingIds().length;
		var totalCount = $('.regenthumbs-missing-select').length;
		var summary = document.getElementById('regenthumbs-missing-selection-summary');
		var regenerateButton = document.getElementById('regenthumbs-missing-regenerate-selected');
		if(summary){
			summary.textContent = selectedCount + ' selected';
		}
		if(regenerateButton){
			regenerateButton.disabled = selectedCount === 0 || missingState.loading;
		}
		var selectAll = document.getElementById('regenthumbs-missing-select-all-header');
		if(selectAll){
			selectAll.checked = totalCount > 0 && selectedCount === totalCount;
		}
	}

	function rowMarkup(item){
		var name = item.title || item.filename || ('Attachment ' + item.id);
		var attachmentText = '<strong class="regenthumbs-missing-name">' + escHtml(name) + '</strong>';
		if(item.filename){
			attachmentText += '<br /><code class="regenthumbs-missing-filename">' + escHtml(item.filename) + '</code>';
		}
		var missingSizes = (item.missing_sizes || []).map(function(size){
			return '<code class="regenthumbs-missing-size-tag">' + escHtml(size) + '</code>';
		}).join(' ');
		var actions = '<a class="button button-small" href="' + escHtml(item.regenerate_url || ('#/regenerate/' + item.id)) + '">Regenerate</a>';
		if(item.edit_url){
			actions += ' <a class="button-link regenthumbs-missing-edit-link" href="' + escHtml(item.edit_url) + '">Edit Media</a>';
		}

		return '<tr data-attachment-id="' + Number(item.id) + '">'
			+ '<th scope="row" class="check-column"><input type="checkbox" class="regenthumbs-missing-select" value="' + Number(item.id) + '" /></th>'
			+ '<td>' + attachmentText + '</td>'
			+ '<td class="regenthumbs-missing-sizes-cell">' + missingSizes + '</td>'
			+ '<td class="regenthumbs-missing-actions-cell">' + actions + '</td>'
			+ '</tr>';
	}

	function renderMissingChrome(){
		var container = ensureMissingUiContainer();
		if(!container){ return; }
		container.innerHTML =
			'<h2 class="regenthumbs-missing-heading">Missing thumbnails</h2>'
			+ '<div id="regenthumbs-missing-status-area"></div>'
			+ '<p class="regenthumbs-missing-summary" id="regenthumbs-missing-summary-main"></p>'
			+ '<p class="regenthumbs-missing-summary">This tool scans in small batches and lists up to 25 missing items per pass.</p>'
			+ '<p class="regenthumbs-missing-summary" id="regenthumbs-missing-summary-checked"></p>'
			+ '<div class="regenthumbs-missing-toolbar">'
			+ '<button type="button" class="button" id="regenthumbs-missing-select-all">Select all listed</button>'
			+ '<button type="button" class="button" id="regenthumbs-missing-clear-selection">Clear selection</button>'
			+ '<button type="button" class="button button-primary" id="regenthumbs-missing-regenerate-selected" disabled="disabled">Regenerate selected</button>'
			+ '<span id="regenthumbs-missing-selection-summary" class="regenthumbs-missing-selection-summary">0 selected</span>'
			+ '</div>'
			+ '<div class="regenthumbs-missing-table-wrap">'
			+ '<table class="widefat striped regenthumbs-missing-table">'
			+ '<thead><tr><td class="check-column"><input type="checkbox" id="regenthumbs-missing-select-all-header" /></td><th>Attachment</th><th>Missing sizes</th><th>Actions</th></tr></thead>'
			+ '<tbody id="regenthumbs-missing-table-body"><tr id="regenthumbs-missing-empty"><td colspan="4">Loading missing thumbnails…</td></tr></tbody>'
			+ '</table>'
			+ '</div>'
			+ '<div class="regenthumbs-missing-footer">'
			+ '<button type="button" class="button" id="regenthumbs-missing-find-more" disabled="disabled">Find 25 more</button>'
			+ '</div>';

		if(missingState.lastResultNotice && missingState.lastResultNotice.message){
			renderNotice(missingState.lastResultNotice.type, missingState.lastResultNotice.message);
		}
	}

	function updateMissingSummary(){
		var main = document.getElementById('regenthumbs-missing-summary-main');
		var checked = document.getElementById('regenthumbs-missing-summary-checked');
		if(main){
			main.innerHTML = 'Scanning media library… checked <strong>' + Number(missingState.totalChecked).toLocaleString() + '</strong> attachments, found <strong>' + Number(missingState.foundCount).toLocaleString() + '</strong> missing thumbnails.';
		}
		if(checked){
			checked.textContent = 'Attachments scanned in latest request: ' + Number(missingState.lastChecked).toLocaleString() + '.';
		}
		var findMore = document.getElementById('regenthumbs-missing-find-more');
		if(findMore){
			findMore.disabled = missingState.loading || !missingState.hasMore;
			findMore.textContent = missingState.loading ? 'Searching…' : 'Find 25 more';
		}
	}

	function appendMissingRows(items){
		var body = document.getElementById('regenthumbs-missing-table-body');
		if(!body){ return; }

		var emptyRow = document.getElementById('regenthumbs-missing-empty');
		if(emptyRow){
			emptyRow.parentNode.removeChild(emptyRow);
		}

		var appended = 0;
		(items || []).forEach(function(item){
			if(!item || !item.id || missingState.itemsById[item.id]){
				return;
			}
			missingState.itemsById[item.id] = true;
			body.insertAdjacentHTML('beforeend', rowMarkup(item));
			appended += 1;
		});

		if(body.children.length === 0){
			body.innerHTML = '<tr id="regenthumbs-missing-empty"><td colspan="4">No attachments requiring regeneration were found yet.</td></tr>';
		}

		missingState.foundCount += appended;
		updateMissingSelectionUi();
		updateMissingSummary();
	}

	function renderNotice(type, message){
		var container = document.getElementById('regenthumbs-missing-status-area');
		if(!container){ return; }
		missingState.lastResultNotice = { type: type, message: message };
		container.innerHTML = '<div class="notice notice-' + escHtml(type) + ' inline"><p>' + escHtml(message) + '</p></div>';
	}

	function processRegenerationQueue(ids){
		var queue = ids.slice(0);
		var total = queue.length;
		var done = 0;
		var failed = [];
		missingState.loading = true;
		updateMissingSelectionUi();

		function next(){
			if(!queue.length){
				missingState.loading = false;
				updateMissingSelectionUi();
				if(failed.length){
					renderNotice('warning', 'Regenerated ' + (total - failed.length) + ' image(s). ' + failed.length + ' failed. Failed IDs: ' + failed.join(', ') + '.');
				} else {
					renderNotice('success', 'Regenerated thumbnails for ' + total + ' image(s).');
				}
				var priorNotice = missingState.lastResultNotice;
				resetMissingUiState();
				missingState.lastResultNotice = priorNotice;
				loadMissingUi(true);
				return;
			}

			var currentId = queue.shift();
			done += 1;
			var progressMessage = 'Regenerating ' + done + ' of ' + total + '…';
			setStatus(progressMessage);
			renderNotice('info', progressMessage);

			window.wp.apiRequest({
				path: '/regenerate-thumbnails/v1/regenerate/' + currentId,
				method: 'POST',
				data: { only_regenerate_missing_thumbnails: true }
			}).done(function(){
				$('tr[data-attachment-id="' + currentId + '"]').remove();
			}).fail(function(){
				failed.push(currentId);
			}).always(function(){
				next();
			});
		}

		next();
	}

	$(document).on('change', '.regenthumbs-missing-select, #regenthumbs-missing-select-all-header', function(event){
		if(event.target && event.target.id === 'regenthumbs-missing-select-all-header'){
			$('.regenthumbs-missing-select').prop('checked', event.target.checked);
		}
		updateMissingSelectionUi();
	});

	$(document).on('click', '#regenthumbs-missing-select-all', function(){
		$('.regenthumbs-missing-select').prop('checked', true);
		updateMissingSelectionUi();
	});

	$(document).on('click', '#regenthumbs-missing-clear-selection', function(){
		$('.regenthumbs-missing-select, #regenthumbs-missing-select-all-header').prop('checked', false);
		updateMissingSelectionUi();
	});

	$(document).on('click', '#regenthumbs-missing-regenerate-selected', function(event){
		if(event){
			event.preventDefault();
			event.stopPropagation();
		}
		var ids = getSelectedMissingIds();
		if(!ids.length){
			return false;
		}

		processRegenerationQueue(ids);
		return false;
	});

	$(document).on('click', '#regenthumbs-missing-find-more', function(){
		if(missingState.loading || !missingState.hasMore){
			return;
		}
		loadMissingUi(false);
	});

	function loadMissingUi(isInitial){
		if(missingState.loading){ return; }
		missingState.loading = true;
		setStatus('missing thumbnails request started');
		updateMissingSummary();

		if(isInitial){
			renderMissingChrome();
		}

		var requestPath = '/regenerate-thumbnails/v1/missing';
		var requestData = {
			cursor: missingState.cursor,
			include_summary: 1
		};
		var requestDescription = 'wp.apiRequest path "' + requestPath + '" with query ' + $.param(requestData);

		window.wp.apiRequest({
			path: requestPath,
			method: 'GET',
			data: requestData
		}).done(function(response){
			missingState.cursor = (response && response.next_cursor !== null && typeof response.next_cursor !== 'undefined')
				? parseInt(response.next_cursor, 10)
				: missingState.cursor;
			missingState.hasMore = !!(response && response.has_more);
			missingState.lastChecked = response && response.attachments_checked ? response.attachments_checked : 0;
			missingState.totalChecked += missingState.lastChecked;
			missingState.requestCount += 1;
			appendMissingRows(response && response.items ? response.items : []);
			setStatus('missing thumbnails request succeeded');
			if(
				missingState.hasMore
				&& missingState.foundCount < MISSING_VISIBLE_LIMIT
				&& missingState.requestCount < MISSING_MAX_AUTO_REQUESTS
			){
				loadMissingUi(false);
			}
		}).fail(function(xhr, textStatus, errorThrown){
			var message = 'Unable to load missing thumbnails.';
			if(xhr && xhr.status === 0){
				message += ' The request was aborted, blocked, or timed out before WordPress returned a response. Try reducing scan batch size or check server/PHP timeout.';
			}
			if(xhr && xhr.responseJSON && xhr.responseJSON.message){
				message += ' ' + xhr.responseJSON.message;
			} else {
				message += ' An unexpected error occurred while loading data.';
			}
			message += ' Request debug: ' + requestDescription + '.';
			message += ' textStatus: ' + (textStatus || '(none)') + '.';
			message += ' errorThrown: ' + (errorThrown || '(none)') + '.';
			if(xhr && typeof xhr.status !== 'undefined'){
				message += ' HTTP status: ' + xhr.status + '.';
			}
			if(xhr && xhr.statusText){
				message += ' Status text: ' + xhr.statusText + '.';
			}
			if (errorThrown) {
				message += ' Error: ' + errorThrown + '.';
			}
			var container = ensureMissingUiContainer();
			if(container){
				container.innerHTML = '<h2 class="regenthumbs-missing-heading">Missing thumbnails</h2><p>' + escHtml(message) + '</p>';
			}
			setStatus('missing thumbnails request failed: ' + message);
		}).always(function(){
			missingState.loading = false;
			updateMissingSummary();
		});
	}

	function resetMissingUiState(){
		missingState.loading = false;
		missingState.cursor = 0;
		missingState.hasMore = false;
		missingState.itemsById = {};
		missingState.foundCount = 0;
		missingState.lastChecked = 0;
		missingState.totalChecked = 0;
		missingState.requestCount = 0;
	}

	function maybeLoadMissingUi(){
		if(window.location.hash && window.location.hash.indexOf('#/regenerate/') === 0){
			return;
		}
		if(!document.getElementById('regenerate-thumbnails-app')){
			return;
		}

		resetMissingUiState();
		loadMissingUi(true);
	}

	$(document).ready(function(){
		setTimeout(maybeLoadMissingUi, 100);
	});
	window.addEventListener('hashchange', function(){
		setTimeout(maybeLoadMissingUi, 100);
	});
})(jQuery);
