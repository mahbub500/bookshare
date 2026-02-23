/**
 * BookShare Community — Frontend App
 * All UI interactions handled here via REST API calls.
 */
(function () {
  'use strict';

  const API = window.BookShare?.rest_url || '/wp-json/bookshare/v1/';
  const NONCE = window.BookShare?.nonce || '';
  const USER_ID = parseInt(window.BookShare?.user_id || 0);
  const IS_LOGGED = window.BookShare?.is_logged === true || window.BookShare?.is_logged === '1';

  /* ── Utility ──────────────────────────────────────────────────────── */

  async function apiFetch(path, options = {}) {
    const res = await fetch(API + path, {
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': NONCE,
        ...options.headers,
      },
      ...options,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || 'API error');
    return data;
  }

  function toast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `bs-toast bs-toast--${type}`;
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add('bs-toast--show'));
    setTimeout(() => {
      el.classList.remove('bs-toast--show');
      setTimeout(() => el.remove(), 400);
    }, 3000);
  }

  function spinner(container, show) {
    const existing = container.querySelector('.bs-spinner');
    if (show) {
      if (existing) return;
      const s = document.createElement('div');
      s.className = 'bs-spinner';
      container.appendChild(s);
    } else {
      existing?.remove();
    }
  }

  function bookCard(book, { showAddBtn = false, showRentBtn = false, ownerName = '', ownerId = 0 } = {}) {
    const cover = book.cover_url
      ? `<img src="${escHtml(book.cover_url)}" class="bs-card__cover" alt="cover">`
      : `<div class="bs-card__cover bs-card__cover--placeholder"><span>${escHtml(book.title[0] || 'B')}</span></div>`;

    const addBtn = showAddBtn
      ? `<button class="bs-btn bs-btn--sm bs-btn--outline js-add-book" data-id="${book.id}">+ My Library</button>`
      : '';

    const rentBtn = showRentBtn
      ? `<button class="bs-btn bs-btn--sm bs-btn--accent js-request-rent"
            data-book-id="${book.id}" data-owner-id="${ownerId}" data-book-title="${escHtml(book.title)}" data-owner="${escHtml(ownerName)}">
            Request to Rent
          </button>`
      : '';

    return `
      <article class="bs-card" data-book-id="${book.id}">
        ${cover}
        <div class="bs-card__body">
          <span class="bs-badge">${escHtml(book.genre || 'Book')}</span>
          <p class="bs-card__code"># ${escHtml(book.unique_code)}</p>
          <h3 class="bs-card__title">${escHtml(book.title)}</h3>
          <p class="bs-card__author">${escHtml(book.author)}</p>
          ${book.publisher ? `<p class="bs-card__publisher">${escHtml(book.publisher)}</p>` : ''}
          ${ownerName ? `<p class="bs-card__owner">📚 ${escHtml(ownerName)}'s library</p>` : ''}
          <div class="bs-card__actions">${addBtn}${rentBtn}</div>
        </div>
      </article>`;
  }

  function escHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])
    );
  }

  /* ── Catalog ─────────────────────────────────────────────────────── */

  function initCatalog(root) {
    const grid   = root.querySelector('.js-catalog-grid');
    const search = root.querySelector('.js-catalog-search');
    const addForm = root.querySelector('.js-add-book-form');

    let debounceTimer;

    async function loadBooks(query = '') {
      spinner(grid, true);
      try {
        const books = await apiFetch(`books?search=${encodeURIComponent(query)}&limit=24`);
        grid.innerHTML = books.length
          ? books.map(b => bookCard(b, { showAddBtn: IS_LOGGED })).join('')
          : '<p class="bs-empty">No books found in the catalog yet.</p>';
        bindAddButtons(grid);
      } catch (e) {
        grid.innerHTML = '<p class="bs-empty bs-empty--error">Could not load books.</p>';
      } finally {
        spinner(grid, false);
      }
    }

    search?.addEventListener('input', e => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => loadBooks(e.target.value.trim()), 350);
    });

    // Add new book form
    addForm?.addEventListener('submit', async e => {
      e.preventDefault();
      const fd = new FormData(addForm);
      const data = Object.fromEntries(fd.entries());
      try {
        await apiFetch('books', { method: 'POST', body: JSON.stringify(data) });
        toast('Book added to catalog!');
        addForm.reset();
        addForm.classList.remove('bs-form--open');
        loadBooks();
      } catch (err) {
        toast(err.message, 'error');
      }
    });

    root.querySelector('.js-toggle-add-form')?.addEventListener('click', () => {
      addForm?.classList.toggle('bs-form--open');
    });

    loadBooks();
  }

  /* ── My Library ─────────────────────────────────────────────────────*/

  function initLibrary(root) {
    const grid = root.querySelector('.js-library-grid');

    async function loadLibrary() {
      spinner(grid, true);
      try {
        const books = await apiFetch('library');
        if (!books.length) {
          grid.innerHTML = '<p class="bs-empty">Your library is empty. Browse the catalog and add books!</p>';
          return;
        }
        grid.innerHTML = books.map(b => `
          <article class="bs-card bs-card--mine" data-book-id="${b.id}">
            <div class="bs-card__cover ${!b.cover_url ? 'bs-card__cover--placeholder' : ''}">
              ${b.cover_url ? `<img src="${escHtml(b.cover_url)}" alt="">` : `<span>${escHtml(b.title[0])}</span>`}
            </div>
            <div class="bs-card__body">
              <span class="bs-badge ${b.is_public == 1 ? 'bs-badge--public' : 'bs-badge--private'}">
                ${b.is_public == 1 ? '🌐 Public' : '🔒 Private'}
              </span>
              <p class="bs-card__code"># ${escHtml(b.unique_code)}</p>
              <h3 class="bs-card__title">${escHtml(b.title)}</h3>
              <p class="bs-card__author">${escHtml(b.author)}</p>
              <div class="bs-card__actions">
                <button class="bs-btn bs-btn--sm bs-btn--outline js-toggle-vis" data-id="${b.id}">
                  ${b.is_public == 1 ? 'Make Private' : 'Make Public'}
                </button>
                <button class="bs-btn bs-btn--sm bs-btn--danger js-remove-book" data-id="${b.id}">Remove</button>
              </div>
            </div>
          </article>`).join('');

        // Toggle visibility
        grid.querySelectorAll('.js-toggle-vis').forEach(btn => {
          btn.addEventListener('click', async () => {
            await apiFetch('library/toggle', { method: 'POST', body: JSON.stringify({ book_id: +btn.dataset.id }) });
            toast('Visibility updated');
            loadLibrary();
          });
        });

        // Remove book
        grid.querySelectorAll('.js-remove-book').forEach(btn => {
          btn.addEventListener('click', async () => {
            if (!confirm('Remove this book from your library?')) return;
            await apiFetch('library', { method: 'DELETE', body: JSON.stringify({ book_id: +btn.dataset.id }) });
            toast('Book removed');
            loadLibrary();
          });
        });
      } catch (e) {
        grid.innerHTML = '<p class="bs-empty bs-empty--error">Could not load your library.</p>';
      } finally {
        spinner(grid, false);
      }
    }

    loadLibrary();

    // Rental requests tab
    const incomingGrid = root.querySelector('.js-incoming-grid');
    if (incomingGrid) loadIncoming(incomingGrid);
  }

  async function loadIncoming(grid) {
    spinner(grid, true);
    try {
      const requests = await apiFetch('rentals/incoming');
      if (!requests.length) {
        grid.innerHTML = '<p class="bs-empty">No incoming rental requests.</p>';
        return;
      }
      grid.innerHTML = requests.map(r => `
        <div class="bs-rental-card bs-rental-card--${escHtml(r.status)}">
          <div class="bs-rental-card__info">
            <strong>${escHtml(r.title)}</strong>
            <span class="bs-badge">${escHtml(r.status)}</span><br>
            <small>From: ${escHtml(r.requester_name)} · ${escHtml(r.requested_at?.slice(0, 10) || '')}</small>
            ${r.message ? `<p class="bs-rental-card__msg">"${escHtml(r.message)}"</p>` : ''}
          </div>
          ${r.status === 'pending' ? `
          <div class="bs-rental-card__actions">
            <button class="bs-btn bs-btn--sm bs-btn--accent js-rental-action" data-id="${r.id}" data-action="approved">Approve</button>
            <button class="bs-btn bs-btn--sm bs-btn--danger  js-rental-action" data-id="${r.id}" data-action="rejected">Reject</button>
          </div>` : ''}
          ${r.status === 'approved' ? `
          <div class="bs-rental-card__actions">
            <button class="bs-btn bs-btn--sm bs-btn--outline js-rental-action" data-id="${r.id}" data-action="returned">Mark Returned</button>
          </div>` : ''}
        </div>`).join('');

      grid.querySelectorAll('.js-rental-action').forEach(btn => {
        btn.addEventListener('click', async () => {
          await apiFetch(`rentals/${btn.dataset.id}/status`, {
            method: 'POST',
            body: JSON.stringify({ status: btn.dataset.action }),
          });
          toast('Request updated');
          loadIncoming(grid);
        });
      });
    } finally {
      spinner(grid, false);
    }
  }

  /* ── Search by Unique Code ───────────────────────────────────────── */

  function initSearch(root) {
    const input   = root.querySelector('.js-code-input');
    const results = root.querySelector('.js-code-results');

    root.querySelector('.js-code-search-btn')?.addEventListener('click', () => doSearch());
    input?.addEventListener('keydown', e => e.key === 'Enter' && doSearch());

    async function doSearch() {
      const code = (input?.value || '').trim().toUpperCase();
      if (!code) return;
      spinner(results, true);
      results.innerHTML = '';
      try {
        const holders = await apiFetch(`library/search?code=${encodeURIComponent(code)}`);
        if (!holders.length) {
          results.innerHTML = '<p class="bs-empty">No public libraries found with this book code.</p>';
          return;
        }
        results.innerHTML = `<h3 class="bs-results-title">Found in ${holders.length} reader's librar${holders.length > 1 ? 'ies' : 'y'}</h3>` +
          holders.map(h => bookCard(h, {
            showRentBtn: IS_LOGGED && h.user_id != USER_ID,
            ownerName: h.reader_name,
            ownerId: h.user_id,
          })).join('');
        bindRentButtons(results);
      } catch (e) {
        results.innerHTML = '<p class="bs-empty bs-empty--error">Search failed. Try again.</p>';
      } finally {
        spinner(results, false);
      }
    }
  }

  /* ── Shared helpers ─────────────────────────────────────────────────*/

  function bindAddButtons(container) {
    container.querySelectorAll('.js-add-book').forEach(btn => {
      btn.addEventListener('click', async () => {
        const bookId = +btn.dataset.id;
        try {
          await apiFetch('library', { method: 'POST', body: JSON.stringify({ book_id: bookId }) });
          toast('Added to your library!');
          btn.textContent = '✓ Added';
          btn.disabled = true;
        } catch (e) {
          toast(e.message || 'Already in your library', 'error');
        }
      });
    });
  }

  function bindRentButtons(container) {
    container.querySelectorAll('.js-request-rent').forEach(btn => {
      btn.addEventListener('click', () => openRentModal(btn));
    });
  }

  /* ── Rent Modal ─────────────────────────────────────────────────────*/

  function openRentModal(btn) {
    const bookId   = +btn.dataset.bookId;
    const ownerId  = +btn.dataset.ownerId;
    const title    = btn.dataset.bookTitle;
    const owner    = btn.dataset.owner;

    const modal = document.createElement('div');
    modal.className = 'bs-modal';
    modal.innerHTML = `
      <div class="bs-modal__backdrop"></div>
      <div class="bs-modal__box">
        <button class="bs-modal__close">×</button>
        <h2 class="bs-modal__title">Request to Rent</h2>
        <p class="bs-modal__sub">Requesting <strong>${escHtml(title)}</strong> from <strong>${escHtml(owner)}</strong></p>
        <textarea class="bs-input bs-input--textarea" placeholder="Optional message to the owner..." rows="4"></textarea>
        <button class="bs-btn bs-btn--accent bs-btn--full js-confirm-rent">Send Request</button>
      </div>`;

    document.body.appendChild(modal);
    requestAnimationFrame(() => modal.classList.add('bs-modal--open'));

    const close = () => {
      modal.classList.remove('bs-modal--open');
      setTimeout(() => modal.remove(), 300);
    };

    modal.querySelector('.bs-modal__close').addEventListener('click', close);
    modal.querySelector('.bs-modal__backdrop').addEventListener('click', close);

    modal.querySelector('.js-confirm-rent').addEventListener('click', async () => {
      const message = modal.querySelector('textarea').value;
      try {
        await apiFetch('rentals/request', {
          method: 'POST',
          body: JSON.stringify({ book_id: bookId, owner_id: ownerId, message }),
        });
        toast('Rental request sent!');
        close();
      } catch (e) {
        toast(e.message || 'Could not send request', 'error');
      }
    });
  }

  /* ── Tab navigation ─────────────────────────────────────────────────*/

  function initTabs(root) {
    root.querySelectorAll('.js-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        root.querySelectorAll('.js-tab-btn').forEach(b => b.classList.remove('bs-tab--active'));
        root.querySelectorAll('.js-tab-panel').forEach(p => p.classList.remove('bs-panel--active'));
        btn.classList.add('bs-tab--active');
        root.querySelector(`#${btn.dataset.tab}`)?.classList.add('bs-panel--active');
      });
    });
    root.querySelector('.js-tab-btn')?.classList.add('bs-tab--active');
    root.querySelector('.js-tab-panel')?.classList.add('bs-panel--active');
  }

  /* ── Bootstrap ──────────────────────────────────────────────────────*/

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-catalog]').forEach(el => {
      initTabs(el);
      initCatalog(el);
    });
    document.querySelectorAll('[data-bs-library]').forEach(el => {
      initTabs(el);
      initLibrary(el);
    });
    document.querySelectorAll('[data-bs-search]').forEach(el => {
      initSearch(el);
    });
  });
})();