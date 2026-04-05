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
		page: 1,
		perPage: 20,
		totalPages: 1,
		loading: false,
		candidateWindow: 100
	};

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
			regenerateButton.disabled = selectedCount === 0;
		}
		var selectAll = document.getElementById('regenthumbs-missing-select-all-header');
		if(selectAll){
			selectAll.checked = totalCount > 0 && selectedCount === totalCount;
		}
	}

	function renderMissingUi(payload){
		var container = ensureMissingUiContainer();
		if(!container){ return; }

		var items = (payload && payload.items) ? payload.items : [];
		var totalMissing = payload && payload.total_missing_attachments !== null ? payload.total_missing_attachments : null;
		var attachmentsChecked = payload && payload.attachments_checked ? payload.attachments_checked : 0;

		var summary = totalMissing === null
			? 'Showing attachments requiring regeneration from the ' + Number(missingState.candidateWindow).toLocaleString() + ' most recent uploads: calculating…'
			: 'Showing attachments requiring regeneration from the ' + Number(missingState.candidateWindow).toLocaleString() + ' most recent uploads: <strong>' + Number(totalMissing).toLocaleString() + '</strong> found';

		var rows = items.length ? items.map(function(item){
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

			return '<tr>'
				+ '<th scope="row" class="check-column"><input type="checkbox" class="regenthumbs-missing-select" value="' + Number(item.id) + '" /></th>'
				+ '<td>' + attachmentText + '</td>'
				+ '<td class="regenthumbs-missing-sizes-cell">' + missingSizes + '</td>'
				+ '<td class="regenthumbs-missing-actions-cell">' + actions + '</td>'
				+ '</tr>';
		}).join('') : '<tr><td colspan="4">No attachments requiring regeneration were found on this page.</td></tr>';

		container.innerHTML =
			'<h2 class="regenthumbs-missing-heading">Missing thumbnails</h2>'
			+ '<p class="regenthumbs-missing-summary">' + summary + '</p>'
			+ '<p class="regenthumbs-missing-summary">This queue checks only the most recent ' + Number(missingState.candidateWindow).toLocaleString() + ' regeneratable uploads, ordered newest first.</p>'
			+ '<p class="regenthumbs-missing-summary">Attachments scanned while building this recent-items queue: ' + Number(attachmentsChecked).toLocaleString() + '</p>'
			+ '<div class="regenthumbs-missing-toolbar">'
			+ '<button type="button" class="button" id="regenthumbs-missing-select-all">Select all on page</button>'
			+ '<button type="button" class="button" id="regenthumbs-missing-clear-selection">Clear selection</button>'
			+ '<button type="button" class="button button-primary" id="regenthumbs-missing-regenerate-selected" disabled="disabled">Regenerate selected</button>'
			+ '<span id="regenthumbs-missing-selection-summary" class="regenthumbs-missing-selection-summary">0 selected</span>'
			+ '</div>'
			+ '<div class="regenthumbs-missing-table-wrap">'
			+ '<table class="widefat striped regenthumbs-missing-table">'
			+ '<thead><tr><td class="check-column"><input type="checkbox" id="regenthumbs-missing-select-all-header" /></td><th>Attachment</th><th>Missing sizes</th><th>Actions</th></tr></thead>'
			+ '<tbody>' + rows + '</tbody>'
			+ '</table>'
			+ '</div>'
			+ '<div class="regenthumbs-missing-footer">'
			+ '<div class="regenthumbs-missing-pagination">'
			+ '<button type="button" class="button" id="regenthumbs-missing-prev"' + (missingState.page <= 1 ? ' disabled="disabled"' : '') + '>Previous</button> '
			+ '<button type="button" class="button" id="regenthumbs-missing-next"' + (missingState.page >= missingState.totalPages ? ' disabled="disabled"' : '') + '>Next</button> '
			+ '<span class="regenthumbs-missing-page-label">Page ' + missingState.page + ' of ' + missingState.totalPages + '</span>'
			+ '</div>'
			+ '</div>';

		updateMissingSelectionUi();
	}

	function goToBulkRegenerate(ids){
		if(!ids.length){ return; }
		var url = new URL(window.location.href);
		url.searchParams.set('page', 'regenerate-thumbnails');
		url.searchParams.set('ids', ids.join(','));
		url.hash = '';
		window.location.assign(url.toString());
	}

	$(document).on('click', '#regenthumbs-missing-prev', function(){
		if(missingState.page > 1){
			loadMissingUi(missingState.page - 1);
		}
	});

	$(document).on('click', '#regenthumbs-missing-next', function(){
		if(missingState.page < missingState.totalPages){
			loadMissingUi(missingState.page + 1);
		}
	});

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

	$(document).on('click', '#regenthumbs-missing-regenerate-selected', function(){
		var ids = getSelectedMissingIds();
		if(!ids.length){
			return;
		}

		setStatus('starting batch regeneration for ' + ids.length + ' selected attachments');
		goToBulkRegenerate(ids);
	});

	function loadMissingUi(page){
		if(missingState.loading){ return; }
		missingState.loading = true;
		missingState.page = page || 1;
		setStatus('missing thumbnails request started');

		var container = ensureMissingUiContainer();
		if(container){
			container.innerHTML = '<h2 class="regenthumbs-missing-heading">Missing thumbnails</h2><p>Loading missing thumbnails…</p>';
		}

		wp.apiRequest({
			path: '/regenerate-thumbnails/v1/missing',
			data: { page: missingState.page, per_page: missingState.perPage, include_summary: 1 },
			type: 'GET',
			dataType: 'json',
			cache: false,
			timeout: 45000
		}).done(function(response, textStatus, xhr){
			var totalPages = parseInt(xhr.getResponseHeader('x-wp-totalpages'), 10);
			missingState.totalPages = totalPages && totalPages > 0 ? totalPages : 1;
			renderMissingUi(response);
			setStatus('missing thumbnails request succeeded');
		}).fail(function(xhr, textStatus, errorThrown){
			var message = 'Unable to load missing thumbnails.';
			if(xhr && xhr.responseJSON && xhr.responseJSON.message){
				message += ' ' + xhr.responseJSON.message;
			} else {
				message += ' An unexpected error occurred while loading data.';
			}
			if(xhr && typeof xhr.status !== 'undefined'){
				message += ' HTTP status: ' + xhr.status + '.';
			}
			if(xhr && xhr.statusText){
				message += ' Status text: ' + xhr.statusText + '.';
			}
			if(textStatus === 'timeout'){
				message += ' The request timed out after 45 seconds. Please try again.';
			} else if (errorThrown) {
				message += ' Error: ' + errorThrown + '.';
			}
			if(container){
				container.innerHTML = '<h2 class="regenthumbs-missing-heading">Missing thumbnails</h2><p>' + escHtml(message) + '</p>';
			}
			setStatus('missing thumbnails request failed: ' + message);
		}).always(function(){
			missingState.loading = false;
		});
	}

	function maybeLoadMissingUi(){
		if(window.location.hash && window.location.hash.indexOf('#/regenerate/') === 0){
			return;
		}
		if(!document.getElementById('regenerate-thumbnails-app')){
			return;
		}
		loadMissingUi(1);
	}

	$(document).ready(function(){
		setTimeout(maybeLoadMissingUi, 100);
	});
	window.addEventListener('hashchange', function(){
		setTimeout(maybeLoadMissingUi, 100);
	});
})(jQuery);
