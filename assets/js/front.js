/**
 * BookCircle Community — Frontend App v2
 * ES5-compatible. All REST API calls. No globals crash.
 */
(function () {
  'use strict';

  /* ── Config ─────────────────────────────────────────────────────── */
  var cfg      = window.BS || {};
  var API      = cfg.rest      || '/wp-json/bookshare/v1/';
  var NONCE    = cfg.nonce     || '';
  var USER_ID  = parseInt(cfg.user_id || 0, 10);
  var IS_LOGGED = (cfg.is_logged === '1' || cfg.is_logged === true);
  var LOGIN_URL = cfg.login_url || '/wp-login.php';

  /* ── Fetch helper ────────────────────────────────────────────────── */
  function apiFetch(path, options) {
    options = options || {};
    var h = { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE };
    if (options.headers) {
      for (var k in options.headers) h[k] = options.headers[k];
      delete options.headers;
    }
    options.headers = h;
    return fetch(API + path, options).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) throw new Error(data.message || 'Request failed');
        return data;
      });
    });
  }

  /* ── Helpers ─────────────────────────────────────────────────────── */
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function toast(msg, type) {
    var t = document.getElementById('bc-toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'bc-toast';
      document.body.appendChild(t);
    }
    t.className = 'bc-toast bc-toast--' + (type || 'success') + ' bc-toast--show';
    t.textContent = msg;
    clearTimeout(t._timer);
    t._timer = setTimeout(function () { t.classList.remove('bc-toast--show'); }, 3200);
  }

  function showSpinner(el) {
    if (!el || el.querySelector('.bc-spinner')) return;
    var s = document.createElement('div');
    s.className = 'bc-spinner';
    el.appendChild(s);
  }
  function hideSpinner(el) {
    if (!el) return;
    var s = el.querySelector('.bc-spinner');
    if (s) s.parentNode.removeChild(s);
  }

  function coverStyle(book) {
    if (book.cover_url) return 'style="background-image:url(\'' + esc(book.cover_url) + '\');background-size:cover;background-position:center"';
    var p = ['#c2401e,#d4a843','#2e6b4f,#52b788','#2c3e50,#4a90d9','#6c3483,#c0392b','#784212,#f39c12'];
    return 'style="background:linear-gradient(135deg,' + p[((book.title || 'A').charCodeAt(0) || 0) % p.length] + ')"';
  }

  /* ── Book card ────────────────────────────────────────────────────── */
  function bookCard(book, opts) {
    opts = opts || {};
    var addBtn    = !!opts.addBtn;
    var rentBtn   = !!opts.rentBtn;
    var visToggle = !!opts.visToggle;
    var removeBtn = !!opts.removeBtn;
    var isPublic  = (opts.isPublic != null) ? opts.isPublic : null;
    var ownerName = opts.ownerName || '';
    var ownerId   = opts.ownerId   || 0;

    var pubBadge  = isPublic !== null
      ? '<span class="bc-badge ' + (isPublic ? 'bc-badge--green' : 'bc-badge--grey') + '">' + (isPublic ? '🌐 Public' : '🔒 Private') + '</span>' : '';
    var genBadge  = book.genre ? '<span class="bc-badge bc-badge--warm">' + esc(book.genre) + '</span>' : '';

    var acts = '';
    if (addBtn)    acts += '<button class="bc-btn bc-btn--outline bc-btn--sm js-add" data-id="'     + book.id + '">+ My Library</button>';
    if (rentBtn)   acts += '<button class="bc-btn bc-btn--accent bc-btn--sm js-rent" data-book-id="' + book.id + '" data-owner-id="' + ownerId + '" data-title="' + esc(book.title) + '" data-owner="' + esc(ownerName) + '">Request Rent</button>';
    if (visToggle) acts += '<button class="bc-btn bc-btn--outline bc-btn--sm js-toggle" data-id="'  + book.id + '">' + (isPublic ? 'Make Private' : 'Make Public') + '</button>';
    if (removeBtn) acts += '<button class="bc-btn bc-btn--danger bc-btn--sm js-remove" data-id="'   + book.id + '">✕ Remove</button>';

    return '<article class="bc-card">'
      + '<div class="bc-card__cover" ' + coverStyle(book) + '>'
      + (!book.cover_url ? '<span class="bc-card__initial">' + esc((book.title || 'B').charAt(0).toUpperCase()) + '</span>' : '')
      + '</div>'
      + '<div class="bc-card__body">'
      + '<div class="bc-card__badges">' + pubBadge + genBadge + '</div>'
      + '<p class="bc-card__code">#' + esc(book.unique_code || '') + '</p>'
      + '<h3 class="bc-card__title">' + esc(book.title || '') + '</h3>'
      + '<p class="bc-card__author">' + esc(book.author || '') + '</p>'
      + (book.publisher ? '<p class="bc-card__publisher">' + esc(book.publisher) + '</p>' : '')
      + (book.pub_year  ? '<p class="bc-card__meta">' + esc(book.pub_year) + (book.pages ? ' · ' + esc(book.pages) + ' pp' : '') + '</p>' : '')
      + (ownerName ? '<p class="bc-card__owner">📚 ' + esc(ownerName) + '\'s library</p>' : '')
      + (acts ? '<div class="bc-card__actions">' + acts + '</div>' : '')
      + '</div></article>';
  }

  /* ── Bind card buttons ────────────────────────────────────────────── */
  function bindCards(container, refreshFn) {
    var i, btn;

    var adds = container.querySelectorAll('.js-add');
    for (i = 0; i < adds.length; i++) {
      (function (b) {
        b.addEventListener('click', function () {
          if (!IS_LOGGED) { window.location.href = LOGIN_URL; return; }
          apiFetch('library', { method: 'POST', body: JSON.stringify({ book_id: +b.dataset.id }) })
            .then(function () { toast('✅ Added to your library!'); b.textContent = '✓ Added'; b.disabled = true; })
            .catch(function (e) { toast(e.message, 'error'); });
        });
      })(adds[i]);
    }

    var rents = container.querySelectorAll('.js-rent');
    for (i = 0; i < rents.length; i++) {
      (function (b) {
        b.addEventListener('click', function () {
          if (!IS_LOGGED) { window.location.href = LOGIN_URL; return; }
          openRentModal(+b.dataset.bookId, +b.dataset.ownerId, b.dataset.title, b.dataset.owner);
        });
      })(rents[i]);
    }

    var toggles = container.querySelectorAll('.js-toggle');
    for (i = 0; i < toggles.length; i++) {
      (function (b) {
        b.addEventListener('click', function () {
          apiFetch('library/toggle', { method: 'POST', body: JSON.stringify({ book_id: +b.dataset.id }) })
            .then(function () { toast('Visibility updated!'); if (refreshFn) refreshFn(); })
            .catch(function (e) { toast(e.message, 'error'); });
        });
      })(toggles[i]);
    }

    var removes = container.querySelectorAll('.js-remove');
    for (i = 0; i < removes.length; i++) {
      (function (b) {
        b.addEventListener('click', function () {
          if (!confirm('Remove this book from your library?')) return;
          apiFetch('library', { method: 'DELETE', body: JSON.stringify({ book_id: +b.dataset.id }) })
            .then(function () { toast('Book removed'); if (refreshFn) refreshFn(); })
            .catch(function (e) { toast(e.message, 'error'); });
        });
      })(removes[i]);
    }
  }

  /* ── Rental card ──────────────────────────────────────────────────── */
  function rentalCard(r) {
    var c = { pending: '#d97706', approved: '#2e6b4f', rejected: '#e53935', returned: '#9ca3af' }[r.status] || '#9ca3af';
    var mine = (r.owner_id == USER_ID);
    var html = '<div class="bc-rental" style="border-left-color:' + c + '">'
      + '<div class="bc-rental__info">'
      + '<strong>' + esc(r.book_title || '?') + '</strong> '
      + '<span class="bc-badge" style="background:' + c + '22;color:' + c + '">' + esc(r.status) + '</span><br>'
      + '<small>' + esc(mine ? ('From: ' + (r.requester_name || '?')) : ('Owner: ' + (r.owner_name || '?')))
      + ' &middot; ' + esc((r.requested_at || '').slice(0, 10)) + '</small>'
      + (r.message ? '<p class="bc-rental__msg">"' + esc(r.message) + '"</p>' : '')
      + '</div>';
    if (mine && r.status === 'pending') {
      html += '<div class="bc-rental__actions">'
        + '<button class="bc-btn bc-btn--accent bc-btn--sm js-rental-act" data-id="' + r.id + '" data-action="approved">✓ Approve</button>'
        + '<button class="bc-btn bc-btn--danger bc-btn--sm js-rental-act" data-id="' + r.id + '" data-action="rejected">✕ Reject</button>'
        + '</div>';
    } else if (mine && r.status === 'approved') {
      html += '<div class="bc-rental__actions">'
        + '<button class="bc-btn bc-btn--outline bc-btn--sm js-rental-act" data-id="' + r.id + '" data-action="returned">Mark Returned</button>'
        + '</div>';
    }
    return html + '</div>';
  }

  function bindRentals(container, refreshFn) {
    var btns = container.querySelectorAll('.js-rental-act');
    for (var i = 0; i < btns.length; i++) {
      (function (b) {
        b.addEventListener('click', function () {
          apiFetch('rentals/' + b.dataset.id + '/status', { method: 'POST', body: JSON.stringify({ status: b.dataset.action }) })
            .then(function () { toast('Updated!'); if (refreshFn) refreshFn(); })
            .catch(function (e) { toast(e.message, 'error'); });
        });
      })(btns[i]);
    }
  }

  /* ── CATALOG ─────────────────────────────────────────────────────── */
  function initCatalog(root) {
    var grid     = root.querySelector('.js-catalog-grid');
    var searchEl = root.querySelector('.js-catalog-search');
    var reqPanel = root.querySelector('.js-req-panel');
    var openBtn  = root.querySelector('.js-open-req');
    var form     = root.querySelector('.js-req-form');
    var timer;

    function load(q) {
      showSpinner(grid);
      apiFetch('books?search=' + encodeURIComponent(q || '') + '&limit=24')
        .then(function (data) {
          var books = data.books || (Array.isArray(data) ? data : []);
          hideSpinner(grid);
          grid.innerHTML = books.length
            ? books.map(function (b) { return bookCard(b, { addBtn: IS_LOGGED }); }).join('')
            : '<p class="bc-empty">No books in the catalog yet.</p>';
          bindCards(grid);
        })
        .catch(function () {
          hideSpinner(grid);
          grid.innerHTML = '<p class="bc-empty bc-empty--err">Could not load books. Please try again.</p>';
        });
    }

    if (searchEl) {
      searchEl.addEventListener('input', function (e) {
        clearTimeout(timer);
        var v = e.target.value.trim();
        timer = setTimeout(function () { load(v); }, 350);
      });
    }

    if (openBtn && reqPanel) {
      openBtn.addEventListener('click', function () { reqPanel.classList.toggle('bc-open'); });
    }

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var data = {};
        new FormData(form).forEach(function (v, k) { data[k] = v; });
        apiFetch('book-requests', { method: 'POST', body: JSON.stringify(data) })
          .then(function () {
            toast('📬 Request sent to admin!');
            form.reset();
            if (reqPanel) reqPanel.classList.remove('bc-open');
          })
          .catch(function (e) { toast(e.message, 'error'); });
      });
    }

    load();
  }

  /* ── MY LIBRARY ──────────────────────────────────────────────────── */
  function initLibrary(root) {
    var grid     = root.querySelector('.js-my-grid');
    var incomEl  = root.querySelector('.js-incoming');
    var outgoEl  = root.querySelector('.js-outgoing');
    var myReqEl  = root.querySelector('.js-my-requests');

    function loadMine() {
      showSpinner(grid);
      apiFetch('library')
        .then(function (books) {
          hideSpinner(grid);
          grid.innerHTML = books.length
            ? books.map(function (b) { return bookCard(b, { visToggle: true, removeBtn: true, isPublic: !!b.is_public }); }).join('')
            : '<p class="bc-empty">Your library is empty. Browse the catalog and add books.</p>';
          bindCards(grid, loadMine);
        })
        .catch(function (e) { hideSpinner(grid); grid.innerHTML = '<p class="bc-empty bc-empty--err">' + esc(e.message) + '</p>'; });
    }

    function loadIncoming() {
      showSpinner(incomEl);
      apiFetch('rentals/incoming')
        .then(function (reqs) {
          hideSpinner(incomEl);
          incomEl.innerHTML = reqs.length ? reqs.map(rentalCard).join('') : '<p class="bc-empty">No incoming rental requests.</p>';
          bindRentals(incomEl, loadIncoming);
        })
        .catch(function () { hideSpinner(incomEl); });
    }

    function loadOutgoing() {
      showSpinner(outgoEl);
      apiFetch('rentals/outgoing')
        .then(function (reqs) {
          hideSpinner(outgoEl);
          outgoEl.innerHTML = reqs.length ? reqs.map(rentalCard).join('') : '<p class="bc-empty">No outgoing rental requests.</p>';
        })
        .catch(function () { hideSpinner(outgoEl); });
    }

    function loadMyRequests() {
      if (!myReqEl) return;
      apiFetch('book-requests')
        .then(function (reqs) {
          if (!reqs.length) { myReqEl.innerHTML = '<p class="bc-empty">No book requests submitted yet.</p>'; return; }
          myReqEl.innerHTML = reqs.map(function (r) {
            var cls = r.status === 'approved' ? 'bc-badge--green' : r.status === 'rejected' ? 'bc-badge--err' : 'bc-badge--warm';
            return '<div class="bc-req-row">'
              + '<span class="bc-badge ' + cls + '">' + esc(r.status) + '</span>'
              + ' <strong>' + esc(r.title) + '</strong>'
              + ' by ' + esc(r.author || '?')
              + ' <small style="color:var(--bc-muted);margin-left:auto">' + esc((r.created_at || '').slice(0, 10)) + '</small>'
              + '</div>';
          }).join('');
        });
    }

    // Tabs
    var tabs   = root.querySelectorAll('.js-lib-tab');
    var panels = root.querySelectorAll('.js-lib-panel');
    for (var i = 0; i < tabs.length; i++) {
      (function (tab) {
        tab.addEventListener('click', function () {
          for (var t = 0; t < tabs.length; t++)   tabs[t].classList.remove('bc-tab--on');
          for (var p = 0; p < panels.length; p++) panels[p].classList.remove('bc-panel--on');
          tab.classList.add('bc-tab--on');
          var panel = root.querySelector('#' + tab.dataset.panel);
          if (panel) panel.classList.add('bc-panel--on');
        });
      })(tabs[i]);
    }
    if (tabs.length)   tabs[0].classList.add('bc-tab--on');
    if (panels.length) panels[0].classList.add('bc-panel--on');

    loadMine();
    loadIncoming();
    loadOutgoing();
    loadMyRequests();
  }

  /* ── SEARCH BY CODE ───────────────────────────────────────────────── */
  function initSearch(root) {
    var input    = root.querySelector('.js-code-input');
    var results  = root.querySelector('.js-code-results');
    var codeBtn  = root.querySelector('.js-code-btn');
    var reqPanel = root.querySelector('.js-code-req-panel');
    var reqForm  = root.querySelector('.js-code-req-form');

    function doSearch() {
      var code = (input ? input.value : '').trim().toUpperCase();
      if (!code || !results) return;
      results.innerHTML = '<div class="bc-spinner" style="margin:2rem auto"></div>';
      apiFetch('library/search?code=' + encodeURIComponent(code))
        .then(function (holders) {
          if (!holders.length) {
            results.innerHTML = '<p class="bc-empty">No public libraries found with this book code.'
              + (IS_LOGGED ? '</p><button class="bc-btn bc-btn--outline bc-btn--sm" id="bc-show-req" style="margin:.75rem auto;display:block">📬 Request admin to add this book</button>' : '</p>');
            var showBtn = document.getElementById('bc-show-req');
            if (showBtn && reqPanel) showBtn.addEventListener('click', function () { reqPanel.classList.toggle('bc-open'); });
            return;
          }
          var html = '<p class="bc-results-head">Found in <strong>' + holders.length + '</strong> reader\'s librar' + (holders.length > 1 ? 'ies' : 'y') + '</p><div class="bc-grid">';
          for (var h = 0; h < holders.length; h++) {
            html += bookCard(holders[h], { rentBtn: IS_LOGGED && holders[h].user_id != USER_ID, ownerName: holders[h].reader_name, ownerId: holders[h].user_id });
          }
          results.innerHTML = html + '</div>';
          bindCards(results);
        })
        .catch(function () { results.innerHTML = '<p class="bc-empty bc-empty--err">Search failed. Please try again.</p>'; });
    }

    if (codeBtn) codeBtn.addEventListener('click', doSearch);
    if (input)   input.addEventListener('keydown', function (e) { if (e.key === 'Enter') doSearch(); });

    if (reqForm) {
      reqForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var data = {};
        new FormData(reqForm).forEach(function (v, k) { data[k] = v; });
        apiFetch('book-requests', { method: 'POST', body: JSON.stringify(data) })
          .then(function () { toast('📬 Request sent to admin!'); reqForm.reset(); if (reqPanel) reqPanel.classList.remove('bc-open'); })
          .catch(function (e) { toast(e.message, 'error'); });
      });
    }
  }

  /* ── RENT MODAL (created on demand, once) ─────────────────────────── */
  var _modal = null;

  function getModal() {
    if (_modal) return _modal;
    _modal = document.createElement('div');
    _modal.id = 'bc-rent-modal';
    _modal.className = 'bc-modal';
    _modal.innerHTML = ''
      + '<div class="bc-modal__bg"></div>'
      + '<div class="bc-modal__box">'
      + '<button class="bc-modal__close" type="button">&times;</button>'
      + '<h2 class="bc-modal__title">Request to Rent</h2>'
      + '<p class="bc-modal__sub" id="bc-modal-sub">...</p>'
      + '<textarea class="bc-input" id="bc-rent-msg" rows="4" placeholder="Optional message to the owner\u2026"></textarea>'
      + '<button class="bc-btn bc-btn--accent bc-btn--full" id="bc-confirm-rent" type="button">Send Rental Request 📬</button>'
      + '</div>';
    document.body.appendChild(_modal);

    _modal.addEventListener('click', function (e) {
      if (e.target === _modal || e.target.className === 'bc-modal__bg' || e.target.classList.contains('bc-modal__close')) {
        _modal.classList.remove('bc-modal--open');
      }
    });

    document.getElementById('bc-confirm-rent').addEventListener('click', function () {
      var d   = _modal._data || {};
      var msg = document.getElementById('bc-rent-msg').value.trim();
      apiFetch('rentals/request', { method: 'POST', body: JSON.stringify({ book_id: d.bookId, owner_id: d.ownerId, message: msg }) })
        .then(function () { toast('📬 Rental request sent!'); _modal.classList.remove('bc-modal--open'); })
        .catch(function (e) { toast(e.message, 'error'); });
    });

    return _modal;
  }

  function openRentModal(bookId, ownerId, title, owner) {
    var m = getModal();
    document.getElementById('bc-modal-sub').innerHTML = 'Requesting <strong>' + esc(title) + '</strong> from <strong>' + esc(owner) + '</strong>';
    document.getElementById('bc-rent-msg').value = '';
    m._data = { bookId: bookId, ownerId: ownerId };
    m.classList.add('bc-modal--open');
  }

  /* ── Init ─────────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    var i;
    var cats = document.querySelectorAll('[data-bc="catalog"]');
    var libs = document.querySelectorAll('[data-bc="library"]');
    var srch = document.querySelectorAll('[data-bc="search"]');
    for (i = 0; i < cats.length; i++) initCatalog(cats[i]);
    for (i = 0; i < libs.length; i++) initLibrary(libs[i]);
    for (i = 0; i < srch.length; i++) initSearch(srch[i]);
  });

})();