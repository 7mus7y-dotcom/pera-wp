(() => {
  const data = window.peraFavourites || {};
  const ajaxUrl = data.ajax_url || '';
  const nonce = data.nonce || '';
  const isLoggedIn = Boolean(data.is_logged_in);
  const storageKey = 'pera_favourites';
  const favouritesGrid = document.getElementById('favourites-grid');
  const favouritesHeroSubtext = document.getElementById('favourites-hero-subtext');
  const favouritesCountLabel = document.querySelector('[data-favourites-count]');
  const favouritesEmptyState = document.getElementById('favourites-empty');
  const undoToast = document.getElementById('fav-undo-toast');
  const undoButton = undoToast ? undoToast.querySelector('[data-fav-undo]') : null;
  const favouritesIdsInput = document.getElementById('fav_post_ids');
  const guestFavLink = document.querySelector('[data-guest-fav-link]');
  const guestLatestFavs = document.querySelector('[data-guest-latest-favs]');
  const guestLatestFavsList = document.querySelector('[data-guest-latest-favs-list]');

  const parseIds = (value) => {
    if (!Array.isArray(value)) {
      return [];
    }
    return value
      .map((id) => parseInt(id, 10))
      .filter((id) => Number.isFinite(id) && id > 0);
  };

  const readLocal = () => {
    try {
      const raw = window.localStorage.getItem(storageKey);
      if (!raw) {
        return [];
      }
      const parsed = JSON.parse(raw);
      return parseIds(parsed);
    } catch (err) {
      return [];
    }
  };

  const writeLocal = (ids) => {
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(Array.from(ids)));
    } catch (err) {
      // ignore storage errors
    }
  };

  const updateFavouritesIdsInput = () => {
    if (!favouritesIdsInput) {
      return;
    }
    favouritesIdsInput.value = Array.from(favourites).join(',');
  };

  let favourites = new Set(readLocal());
  let undoTimer = null;
  let lastRemoval = null;

  const hideUndoToast = () => {
    if (!undoToast) {
      return;
    }
    undoToast.hidden = true;
    if (undoTimer) {
      window.clearTimeout(undoTimer);
      undoTimer = null;
    }
  };

  const showUndoToast = (payload) => {
    if (!undoToast) {
      return;
    }
    lastRemoval = payload;
    undoToast.hidden = false;
    if (undoTimer) {
      window.clearTimeout(undoTimer);
    }
    undoTimer = window.setTimeout(() => {
      hideUndoToast();
      lastRemoval = null;
    }, 6000);
  };

  const undoLastRemoval = () => {
    if (!lastRemoval) {
      return;
    }

    const {
      postId,
      removedCard,
      removedNextSibling,
      removedParent,
      isFavouritesPage,
    } = lastRemoval;

    lastRemoval = null;
    hideUndoToast();

    if (favourites.has(postId)) {
      return;
    }

    favourites.add(postId);
    writeLocal(favourites);
    updateFavouritesIdsInput();
    updateButtonsForId(postId);
    updateGuestOffcanvas();

    if (isFavouritesPage) {
      if (removedCard && removedParent) {
        if (removedNextSibling && removedNextSibling.parentElement === removedParent) {
          removedParent.insertBefore(removedCard, removedNextSibling);
        } else {
          removedParent.appendChild(removedCard);
        }
      }
      updateFavouritesHero(favourites.size);
    }

    persistServerToggle(postId, true).then((result) => {
      if (result.ok) {
        if (isFavouritesPage) {
          updateFavouritesHero(favourites.size);
        }
        return;
      }

      favourites.delete(postId);
      writeLocal(favourites);
      updateFavouritesIdsInput();
      updateButtonsForId(postId);
      updateGuestOffcanvas();

      if (isFavouritesPage) {
        if (removedCard && removedParent && removedCard.parentElement === removedParent) {
          removedParent.removeChild(removedCard);
        }
        updateFavouritesHero(favourites.size);
      }
    });
  };

  if (undoButton) {
    undoButton.addEventListener('click', (event) => {
      event.preventDefault();
      undoLastRemoval();
    });
  }

  const updateButton = (button, isFav) => {
    button.classList.toggle('is-fav', isFav);
    button.setAttribute('aria-pressed', isFav ? 'true' : 'false');
    button.setAttribute(
      'aria-label',
      isFav ? 'Remove from favourites' : 'Add to favourites'
    );
  };

  const updateButtonsForId = (postId) => {
    const isFav = favourites.has(postId);
    document
      .querySelectorAll(`.fav-toggle[data-post-id="${postId}"]`)
      .forEach((button) => updateButton(button, isFav));
  };

  const updateAllButtons = () => {
    document.querySelectorAll('.fav-toggle').forEach((button) => {
      const postId = parseInt(button.dataset.postId, 10);
      if (!Number.isFinite(postId) || postId <= 0) {
        return;
      }
      updateButton(button, favourites.has(postId));
    });
  };

  const updateFavouritesHero = (count) => {
    if (favouritesCountLabel) {
      favouritesCountLabel.textContent = String(count);
    }

    if (favouritesHeroSubtext) {
      const text = isLoggedIn
        ? count > 0
          ? favouritesHeroSubtext.dataset.loggedHas
          : favouritesHeroSubtext.dataset.loggedEmpty
        : count > 0
          ? favouritesHeroSubtext.dataset.guestHas
          : favouritesHeroSubtext.dataset.guestEmpty;

      if (text) {
        favouritesHeroSubtext.textContent = text;
      }
    }

    if (favouritesEmptyState) {
      favouritesEmptyState.hidden = count > 0;
    }
  };

  const updateGuestOffcanvas = async () => {
    if (isLoggedIn) {
      return;
    }

    if (!guestFavLink && !guestLatestFavs) {
      return;
    }

    const ids = Array.from(favourites);
    const hasFavs = ids.length > 0;

    if (guestFavLink) {
      guestFavLink.hidden = !hasFavs;
    }

    if (!guestLatestFavs || !guestLatestFavsList) {
      return;
    }

    if (!hasFavs) {
      guestLatestFavs.hidden = true;
      guestLatestFavsList.innerHTML = '';
      return;
    }

    if (!ajaxUrl) {
      guestLatestFavs.hidden = true;
      guestLatestFavsList.innerHTML = '';
      return;
    }

    const latestIds = ids.slice(-3).reverse();
    const body = new URLSearchParams();
    body.set('action', 'pera_favourites_titles');
    body.set('nonce', nonce);
    latestIds.forEach((id) => body.append('ids[]', String(id)));

    try {
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body,
        credentials: 'same-origin',
      });

      const payload = await response.json();
      if (!payload || !payload.success) {
        throw new Error('Bad response');
      }

      const items = Array.isArray(payload.data && payload.data.items)
        ? payload.data.items
        : [];

      if (!items.length) {
        guestLatestFavs.hidden = true;
        guestLatestFavsList.innerHTML = '';
        return;
      }

      guestLatestFavsList.innerHTML = '';
      items.forEach((item) => {
        if (!item || !item.title || !item.url) {
          return;
        }
        const listItem = document.createElement('li');
        const link = document.createElement('a');
        link.href = item.url;
        link.textContent = item.title;
        link.className = 'offcanvas-favourites-link text-sm';
        listItem.appendChild(link);
        guestLatestFavsList.appendChild(listItem);
      });

      guestLatestFavs.hidden = guestLatestFavsList.children.length === 0;
    } catch (err) {
      guestLatestFavs.hidden = true;
      guestLatestFavsList.innerHTML = '';
    }
  };

  const renderFavouritesGrid = async (ids) => {
    if (!ajaxUrl || !favouritesGrid) {
      return;
    }

    if (!ids.length) {
      favouritesGrid.innerHTML = '';
      updateFavouritesHero(0);
      return;
    }

    const body = new URLSearchParams();
    body.set('action', 'pera_render_favourites');
    body.set('nonce', nonce);
    ids.forEach((id) => body.append('ids[]', String(id)));

    try {
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body,
        credentials: 'same-origin',
      });

      const payload = await response.json();
      if (!payload || !payload.success) {
        throw new Error('Bad response');
      }

      const html = payload.data && payload.data.html ? payload.data.html : '';
      const count = parseInt(payload.data && payload.data.count, 10) || 0;

      favouritesGrid.innerHTML = html;
      updateAllButtons();
      updateFavouritesHero(count);
      updateFavouritesIdsInput();
    } catch (err) {
      favouritesGrid.innerHTML = '<p class="text-soft">Unable to load favourites.</p>';
      updateFavouritesHero(0);
      updateFavouritesIdsInput();
    }
  };

  const fetchServerFavourites = async () => {
    if (!isLoggedIn || !ajaxUrl) {
      return;
    }

    const body = new URLSearchParams();
    body.set('action', 'pera_get_favourites');
    body.set('nonce', nonce);

    try {
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body,
        credentials: 'same-origin',
      });

      const payload = await response.json();
      if (!payload || !payload.success) {
        return;
      }

      const serverFavs = parseIds(payload.data && payload.data.favourites);
      serverFavs.forEach((id) => favourites.add(id));
      writeLocal(favourites);
      updateFavouritesIdsInput();
      updateAllButtons();
    } catch (err) {
      // ignore fetch errors
    }
  };

  const persistServerToggle = async (postId, nextIsFav) => {
    if (!isLoggedIn || !ajaxUrl) {
      return { ok: true };
    }

    const body = new URLSearchParams();
    body.set('action', 'pera_toggle_favourite');
    body.set('nonce', nonce);
    body.set('post_id', String(postId));
    body.set('fav_action', nextIsFav ? 'add' : 'remove');

    try {
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body,
        credentials: 'same-origin',
      });

      const payload = await response.json();
      if (!payload || !payload.success) {
        return { ok: false };
      }

      const serverFavs = parseIds(payload.data && payload.data.favourites);
      favourites = new Set(serverFavs);
      writeLocal(favourites);
      updateFavouritesIdsInput();
      updateAllButtons();

      return { ok: true };
    } catch (err) {
      return { ok: false };
    }
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.fav-toggle');
    if (!button) {
      return;
    }

    event.preventDefault();

    const postId = parseInt(button.dataset.postId, 10);
    if (!Number.isFinite(postId) || postId <= 0) {
      return;
    }

    const wasFav = favourites.has(postId);
    const nextIsFav = !wasFav;
    const isFavouritesPage = Boolean(favouritesGrid);
    let removedCard = null;
    let removedNextSibling = null;
    let removedParent = null;

    if (nextIsFav) {
      favourites.add(postId);
      if (lastRemoval && lastRemoval.postId === postId) {
        lastRemoval = null;
        hideUndoToast();
      }
    } else {
      favourites.delete(postId);

      if (isFavouritesPage) {
        const card = button.closest('article');
        if (card && card.parentElement) {
          removedCard = card;
          removedNextSibling = card.nextSibling;
          removedParent = card.parentElement;
          removedParent.removeChild(card);
        }
      }

      showUndoToast({
        postId,
        removedCard,
        removedNextSibling,
        removedParent,
        isFavouritesPage,
      });
    }

    writeLocal(favourites);
    updateFavouritesIdsInput();
    updateButtonsForId(postId);
    if (isFavouritesPage) {
      updateFavouritesHero(favourites.size);
    }
    updateGuestOffcanvas();

    persistServerToggle(postId, nextIsFav).then((result) => {
      if (result.ok) {
        if (isFavouritesPage) {
          updateFavouritesHero(favourites.size);
        }
        updateGuestOffcanvas();
        return;
      }

      if (nextIsFav) {
        favourites.delete(postId);
      } else {
        favourites.add(postId);

        if (removedCard && removedParent) {
          if (removedNextSibling) {
            removedParent.insertBefore(removedCard, removedNextSibling);
          } else {
            removedParent.appendChild(removedCard);
          }
        }
        hideUndoToast();
        lastRemoval = null;
      }

      writeLocal(favourites);
      updateFavouritesIdsInput();
      updateButtonsForId(postId);
      if (isFavouritesPage) {
        updateFavouritesHero(favourites.size);
      }
      updateGuestOffcanvas();
    });
  });

  updateAllButtons();
  fetchServerFavourites();
  updateFavouritesIdsInput();
  updateGuestOffcanvas();

  if (favouritesGrid && !isLoggedIn) {
    const localIds = readLocal();
    renderFavouritesGrid(localIds);
  }
})();
