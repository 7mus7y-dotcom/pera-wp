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
		loading: false
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
		container.style.maxWidth = 'none';
		container.style.marginTop = '16px';
		app.appendChild(container);
		return container;
	}

	function renderMissingUi(payload){
		var container = ensureMissingUiContainer();
		if(!container){ return; }

		var items = (payload && payload.items) ? payload.items : [];
		var totalMissing = payload && payload.total_missing_attachments !== null ? payload.total_missing_attachments : null;
		var totalRegeneratable = payload && payload.total_regeneratable ? payload.total_regeneratable : 0;
		var attachmentsChecked = payload && payload.attachments_checked ? payload.attachments_checked : 0;

		var summary = totalMissing === null
			? 'Attachments with missing thumbnails: calculating…'
			: 'Attachments with missing thumbnails: <strong>' + Number(totalMissing).toLocaleString() + '</strong>';

		var rows = items.length ? items.map(function(item){
			var name = item.title || item.filename || ('Attachment ' + item.id);
			var attachmentText = '<strong>' + escHtml(name) + '</strong><br /><code>' + escHtml(item.filename || '') + '</code>';
			var missingSizes = (item.missing_sizes || []).map(function(size){
				return '<code>' + escHtml(size) + '</code>';
			}).join(', ');
			var actions = '<a href="' + escHtml(item.regenerate_url || ('#/regenerate/' + item.id)) + '">Regenerate this attachment</a>';
			if(item.edit_url){
				actions += ' · <a href="' + escHtml(item.edit_url) + '">Edit Media</a>';
			}

			return '<tr>'
				+ '<td>' + attachmentText + '</td>'
				+ '<td>' + missingSizes + '</td>'
				+ '<td>' + actions + '</td>'
				+ '</tr>';
		}).join('') : '<tr><td colspan="3">No attachments requiring regeneration were found on this page.</td></tr>';

		container.innerHTML =
			'<h2 style="margin-top:0;">Missing thumbnails</h2>'
			+ '<p>' + summary + '</p>'
			+ '<p>Total regeneratable candidate attachments: ' + Number(totalRegeneratable).toLocaleString() + '</p>'
			+ '<p>Attachments checked while building snapshot: ' + Number(attachmentsChecked).toLocaleString() + '</p>'
			+ '<table class="widefat striped">'
			+ '<thead><tr><th>Attachment</th><th>Missing sizes</th><th>Actions</th></tr></thead>'
			+ '<tbody>' + rows + '</tbody>'
			+ '</table>'
			+ '<p style="margin-top:12px;">'
			+ '<button type="button" class="button" id="regenthumbs-missing-prev"' + (missingState.page <= 1 ? ' disabled="disabled"' : '') + '>Previous</button> '
			+ '<button type="button" class="button" id="regenthumbs-missing-next"' + (missingState.page >= missingState.totalPages ? ' disabled="disabled"' : '') + '>Next</button> '
			+ '<span style="margin-left:8px;">Page ' + missingState.page + ' of ' + missingState.totalPages + '</span>'
			+ '</p>';

		var prev = document.getElementById('regenthumbs-missing-prev');
		var next = document.getElementById('regenthumbs-missing-next');
		if(prev){
			prev.addEventListener('click', function(){
				if(missingState.page > 1){
					loadMissingUi(missingState.page - 1);
				}
			});
		}
		if(next){
			next.addEventListener('click', function(){
				if(missingState.page < missingState.totalPages){
					loadMissingUi(missingState.page + 1);
				}
			});
		}
	}

	function loadMissingUi(page){
		if(missingState.loading){ return; }
		missingState.loading = true;
		missingState.page = page || 1;

		wp.apiRequest({
			namespace: 'regenerate-thumbnails/v1',
			endpoint: 'missing',
			data: { page: missingState.page, per_page: missingState.perPage, include_summary: 1 },
			type: 'GET',
			dataType: 'json',
			cache: false
		}).done(function(response, textStatus, xhr){
			var totalPages = parseInt(xhr.getResponseHeader('x-wp-totalpages'), 10);
			missingState.totalPages = totalPages && totalPages > 0 ? totalPages : 1;
			renderMissingUi(response);
		}).fail(function(xhr){
			var container = ensureMissingUiContainer();
			if(container){
				var message = 'Unable to load missing thumbnails.';
				if(xhr && xhr.responseJSON && xhr.responseJSON.message){
					message += ' ' + xhr.responseJSON.message;
				}
				container.innerHTML = '<h2 style="margin-top:0;">Missing thumbnails</h2><p>' + escHtml(message) + '</p>';
			}
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
