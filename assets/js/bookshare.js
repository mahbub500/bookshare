/**
 * BookCircle Frontend App — bookshare.js
 * Pure vanilla JS, no dependencies
 */
(function () {
    'use strict';

    const cfg = window.BSConfig || {};
    const API = cfg.root || '/wp-json/bookshare/v1/';
    const NONCE = cfg.nonce || '';
    const IS_LOGGED_IN = !!cfg.logged_in;

    // ─── State ──────────────────────────────────────────────────────────────
    const state = {
        catalogPage: 0,
        catalogSearch: '',
        catalogGenre: '',
        catalogTotal: 0,
        PER_PAGE: 12,
        authors: [],
        publishers: [],
        library: [],
        incoming: [],
        outgoing: [],
        activeTab: null,
    };

    // ─── Utility ────────────────────────────────────────────────────────────
    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

    function el(tag, cls, html = '') {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (html) e.innerHTML = html;
        return e;
    }

    function request(method, path, body = null) {
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        };
        if (body) opts.body = JSON.stringify(body);
        return fetch(API + path, opts).then(r => r.json());
    }

    function toast(msg, type = '') {
        const t = $('#bs-toast');
        if (!t) return;
        t.textContent = msg;
        t.className = 'bs-toast show ' + type;
        setTimeout(() => (t.className = 'bs-toast'), 2800);
    }

    function loading(show) {
        const l = $('#bs-global-loading');
        if (l) l.style.display = show ? 'flex' : 'none';
    }

    function coverHtml(url, title = '') {
        if (url) return `<img src="${esc(url)}" alt="${esc(title)}" loading="lazy">`;
        return `<div class="bs-book-cover-placeholder">📚</div>`;
    }

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function statusBadge(status) {
        return `<span class="bs-status bs-status-${esc(status)}">${esc(status)}</span>`;
    }

    // ─── Modal helpers ───────────────────────────────────────────────────────
    function openModal(id) { const m = $('#' + id); if (m) m.style.display = 'flex'; }
    function closeModal(id) { const m = $('#' + id); if (m) m.style.display = 'none'; }

    // ─── Tabs ────────────────────────────────────────────────────────────────
    function initTabs() {
        const app = $('#bs-app');
        const initTab = app ? app.dataset.initTab : 'catalog';

        $$('.bs-tab').forEach(tab => {
            tab.addEventListener('click', () => switchTab(tab.dataset.tab));
        });

        switchTab(initTab || 'catalog');
    }

    function switchTab(name) {
        if (state.activeTab === name) return;
        state.activeTab = name;

        $$('.bs-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
        $$('.bs-panel').forEach(p => p.classList.toggle('active', p.id === 'bs-panel-' + name));

        if (name === 'catalog') loadCatalog();
        if (name === 'library') loadLibrary();
        if (name === 'rentals') loadRentals();
    }

    // ─── Catalog ─────────────────────────────────────────────────────────────
    async function loadCatalog() {
        loading(true);
        const params = new URLSearchParams({
            search: state.catalogSearch,
            genre: state.catalogGenre,
            per_page: state.PER_PAGE,
            offset: state.catalogPage * state.PER_PAGE,
        });
        const data = await request('GET', 'books?' + params);


        loading(false);
        state.catalogTotal = data.total || 0;
        renderCatalog(data.books || []);
        populateGenreFilter(data.genres || []);
        renderPagination();
    }

    function renderCatalog(books) {
        const grid = $('#bs-catalog-grid');
        if (!grid) return;
        if (!books.length) {
            grid.innerHTML = '<div class="bs-empty-state"><div class="bs-empty-icon">🔍</div><h3>No books found</h3><p>Try a different search.</p></div>';
            return;
        }
        grid.innerHTML = books.map(bookCardHtml).join('');

        // Add to library buttons
        grid.querySelectorAll('[data-action="add-library"]').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); addToLibrary(btn.dataset.id); });
        });

        // Open detail on card click
        grid.querySelectorAll('.bs-book-card').forEach(card => {
            card.addEventListener('click', () => openBookDetail(card.dataset.code));
        });
    }

    function bookCardHtml(book) {
        const addBtn = IS_LOGGED_IN
            ? `<button class="bs-btn bs-btn-primary bs-btn-sm" data-action="add-library" data-id="${esc(book.ID)}">+ My Library</button>`
            : '';
        return `<div class="bs-book-card" data-code="${esc(book.unique_code)}">
            <div class="bs-book-cover">
                ${coverHtml(book.cover_url, book.title)}
                <span class="bs-book-code-badge">${esc(book.unique_code)}</span>
            </div>
            <div class="bs-book-info">
                <div class="bs-book-title">${esc(book.title)}</div>
                <div class="bs-book-author">${esc(book.author_name || 'Unknown Author')}</div>
                ${book.genre ? `<span class="bs-book-genre">${esc(book.genre)}</span>` : ''}
            </div>
            <div class="bs-book-actions">${addBtn}</div>
        </div>`;
    }

    function populateGenreFilter(genres) {
        const sel = $('#bs-catalog-genre');
        if (!sel || sel.dataset.populated) return;
        genres.forEach(g => {
            const opt = document.createElement('option');
            opt.value = g; opt.textContent = g;
            sel.appendChild(opt);
        });
        sel.dataset.populated = '1';
    }

    function renderPagination() {
        const cont = $('#bs-catalog-pagination');
        if (!cont) return;
        const pages = Math.ceil(state.catalogTotal / state.PER_PAGE);
        if (pages <= 1) { cont.innerHTML = ''; return; }
        let html = '';
        for (let i = 0; i < pages; i++) {
            html += `<button class="bs-page-btn ${i === state.catalogPage ? 'active' : ''}" data-page="${i}">${i + 1}</button>`;
        }
        cont.innerHTML = html;
        cont.querySelectorAll('.bs-page-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                state.catalogPage = parseInt(btn.dataset.page);
                loadCatalog();
                cont.closest('.bs-panel')?.scrollTo({ top: 0 });
            });
        });
    }

    // ─── Book Detail ──────────────────────────────────────────────────────────
    async function openBookDetail(code) {
        const data = await request('GET', 'books/' + code);
        if (data.code) { toast('Book not found', 'error'); return; }
        const holders = (data.holders || []);
        const holdersHtml = holders.length
            ? holders.map(h => `<div class="bs-holder-card">
                <img src="${esc(h.avatar)}" class="bs-holder-avatar" alt="${esc(h.display_name)}">
                <div>
                    <div class="bs-holder-name">${esc(h.display_name)}</div>
                    ${h.condition_note ? `<div class="bs-holder-note">Condition: ${esc(h.condition_note)}</div>` : ''}
                </div>
                ${IS_LOGGED_IN
                    ? `<div class="bs-holder-actions"><button class="bs-btn bs-btn-primary bs-btn-sm" data-action="request-rental" data-book="${esc(data.id)}" data-owner="${esc(h.user_id)}" data-name="${esc(data.title)}">Request</button></div>`
                    : ''}
              </div>`).join('')
            : '<p style="color:var(--bs-text-muted)">No community member has listed this book publicly yet.</p>';

        const html = `<div class="bs-detail">
            <div>
                <div class="bs-detail-cover">${coverHtml(data.cover_url, data.title)}</div>
            </div>
            <div>
                <div class="bs-detail-code">${esc(data.unique_code)}</div>
                <h2 class="bs-detail-title">${esc(data.title)}</h2>
                <p class="bs-detail-author">by ${esc(data.author_name || 'Unknown')}</p>
                <div class="bs-detail-meta">
                    ${data.genre        ? `<span class="bs-meta-item">🏷 ${esc(data.genre)}</span>` : ''}
                    ${data.published_year ? `<span class="bs-meta-item">📅 ${esc(data.published_year)}</span>` : ''}
                    ${data.pages        ? `<span class="bs-meta-item">📄 ${esc(data.pages)} pages</span>` : ''}
                    ${data.isbn         ? `<span class="bs-meta-item">ISBN ${esc(data.isbn)}</span>` : ''}
                    ${data.language     ? `<span class="bs-meta-item">🌐 ${esc(data.language)}</span>` : ''}
                </div>
                ${data.description ? `<p class="bs-detail-desc">${esc(data.description)}</p>` : ''}
                <div class="bs-detail-holders">
                    <h4>📍 Community Holders (${holders.length})</h4>
                    ${holdersHtml}
                </div>
            </div>
        </div>`;

        const cont = $('#bs-modal-detail-content');
        if (cont) {
            cont.innerHTML = html;
            cont.querySelectorAll('[data-action="request-rental"]').forEach(btn => {
                btn.addEventListener('click', () => openRentalModal(btn.dataset.book, btn.dataset.owner, btn.dataset.name));
            });
        }
        openModal('bs-modal-detail');
    }

    // ─── Add Book ────────────────────────────────────────────────────────────
    async function openAddBookModal() {
        await ensureAuthors();
        $('#bs-book-edit-id').value = '';
        $('#bs-modal-book-title').textContent = 'Add New Book';
        ['title','author','publisher','genre','isbn','year','pages','language','cover','desc'].forEach(f => {
            const el = $('#bs-book-' + f);
            if (el) el.value = f === 'language' ? 'English' : '';
        });
        openModal('bs-modal-book');
    }

    async function ensureAuthors() {
        if (state.authors.length) return;
        const [aData, pData] = await Promise.all([
            request('GET', 'authors'),
            request('GET', 'publishers'),
        ]);
        state.authors    = aData.authors || [];
        state.publishers = pData.publishers || [];

        const asel = $('#bs-book-author');
        const psel = $('#bs-book-publisher');
        if (asel) state.authors.forEach(a => {
            const o = document.createElement('option'); o.value = a.id; o.textContent = a.name; asel.appendChild(o);
        });
        if (psel) state.publishers.forEach(p => {
            const o = document.createElement('option'); o.value = p.id; o.textContent = p.name; psel.appendChild(o);
        });
    }

    async function saveBook() {
        const editId = $('#bs-book-edit-id')?.value;
        const payload = {
            title:          $('#bs-book-title')?.value?.trim(),
            author_id:      $('#bs-book-author')?.value || null,
            publisher_id:   $('#bs-book-publisher')?.value || null,
            genre:          $('#bs-book-genre')?.value?.trim(),
            isbn:           $('#bs-book-isbn')?.value?.trim(),
            published_year: parseInt($('#bs-book-year')?.value) || null,
            pages:          parseInt($('#bs-book-pages')?.value) || null,
            language:       $('#bs-book-language')?.value?.trim() || 'English',
            cover_url:      $('#bs-book-cover')?.value?.trim(),
            description:    $('#bs-book-desc')?.value?.trim(),
        };

        if (!payload.title) { toast('Title is required', 'error'); return; }

        const btn = $('#bs-btn-save-book');
        btn.disabled = true; btn.textContent = 'Saving…';

        const method = editId ? 'PUT' : 'POST';
        const path   = editId ? `books/${editId}` : 'books';
        const data   = await request(method, path, payload);
        btn.disabled = false; btn.textContent = 'Save Book';

        if (data.id) {
            toast('Book saved!', 'success');
            closeModal('bs-modal-book');
            state.catalogPage = 0;
            loadCatalog();
        } else {
            toast(data.message || 'Error saving book', 'error');
        }
    }

    async function addToLibrary(bookId) {
        if (!IS_LOGGED_IN) { toast('Please sign in first', 'error'); return; }
        const data = await request('POST', 'library', { book_id: parseInt(bookId) });
        if (data.added) toast('Added to your library! 📚', 'success');
        else toast(data.message || 'Already in library', '');
    }

    // ─── Library ──────────────────────────────────────────────────────────────
    async function loadLibrary() {
        if (!IS_LOGGED_IN) return;
        loading(true);
        const data = await request('GET', 'library');
        loading(false);
        state.library = Array.isArray(data) ? data : [];
        renderLibrary();
    }

    function renderLibrary() {
        const grid = $('#bs-library-grid');
        if (!grid) return;
        if (!state.library.length) {
            grid.innerHTML = '<div class="bs-empty-state"><div class="bs-empty-icon">📚</div><h3>Your library is empty</h3><p>Browse the catalog and add books you own.</p></div>';
            return;
        }
        grid.innerHTML = state.library.map(book => {
            const isPub = book.is_public == 1;
            return `<div class="bs-book-card" data-code="${esc(book.unique_code)}">
                <div class="bs-book-cover">
                    ${coverHtml(book.cover_url, book.title)}
                    <span class="bs-book-code-badge">${esc(book.unique_code)}</span>
                </div>
                <div class="bs-book-info">
                    <div class="bs-book-title">${esc(book.title)}</div>
                    <div class="bs-book-author">${esc(book.author_name || 'Unknown Author')}</div>
                    ${book.genre ? `<span class="bs-book-genre">${esc(book.genre)}</span>` : ''}
                </div>
                <div class="bs-book-actions" style="flex-wrap:wrap;gap:5px">
                    <button class="bs-privacy-toggle ${isPub?'public':'private'}" data-action="toggle" data-id="${esc(book.book_id)}">
                        ${isPub ? '🌐 Public' : '🔒 Private'}
                    </button>
                    <button class="bs-btn bs-btn-danger bs-btn-sm" data-action="remove" data-id="${esc(book.book_id)}">Remove</button>
                </div>
            </div>`;
        }).join('');

        grid.querySelectorAll('[data-action="toggle"]').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); toggleLibraryVisibility(btn.dataset.id, btn); });
        });
        grid.querySelectorAll('[data-action="remove"]').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); removeFromLibrary(btn.dataset.id); });
        });
        grid.querySelectorAll('.bs-book-card').forEach(card => {
            card.addEventListener('click', () => openBookDetail(card.dataset.code));
        });
    }

    async function toggleLibraryVisibility(bookId, btn) {
        const data = await request('POST', 'library/toggle', { book_id: parseInt(bookId) });
        if (data.toggled) loadLibrary();
    }

    async function removeFromLibrary(bookId) {
        if (!confirm('Remove this book from your library?')) return;
        await request('DELETE', 'library', { book_id: parseInt(bookId) });
        state.library = state.library.filter(b => b.book_id != bookId);
        renderLibrary();
        toast('Book removed from library', '');
    }

    // ─── Search by Code ───────────────────────────────────────────────────────
    async function searchByCode() {
        const input = $('#bs-code-input');
        const code  = input?.value?.trim().toUpperCase();
        if (!code) { toast('Enter a book code', 'error'); return; }

        const results = $('#bs-search-results');
        if (results) results.innerHTML = '<div class="bs-empty-state"><div class="bs-spinner"></div></div>';

        const data = await request('GET', `library/search?code=${code}`);
        if (!results) return;

        if (data.code === 'rest_error' || !data.holders) {
            results.innerHTML = '<div class="bs-empty-state"><div class="bs-empty-icon">❌</div><h3>Code not found</h3><p>No book matches this code.</p></div>';
            return;
        }

        if (!data.holders.length) {
            results.innerHTML = '<div class="bs-empty-state"><div class="bs-empty-icon">😕</div><h3>No holders found</h3><p>No community members have listed this book publicly.</p></div>';
            return;
        }

        results.innerHTML = `<h3 style="text-align:center;margin-bottom:16px;color:var(--bs-primary)">📚 ${esc(data.holders[0]?.title || data.code)} — ${data.holders.length} holder(s)</h3>` +
            data.holders.map(h => `<div class="bs-holder-card">
                <img src="${esc(h.avatar)}" class="bs-holder-avatar" alt="${esc(h.display_name)}">
                <div>
                    <div class="bs-holder-name">${esc(h.display_name)}</div>
                    ${h.condition_note ? `<div class="bs-holder-note">Condition: ${esc(h.condition_note)}</div>` : ''}
                </div>
                ${IS_LOGGED_IN
                    ? `<div class="bs-holder-actions"><button class="bs-btn bs-btn-primary bs-btn-sm" data-action="request-rental" data-book="${esc(h.book_id ?? '')}" data-owner="${esc(h.user_id)}" data-name="${esc(h.title)}">Request Borrow</button></div>`
                    : ''}
            </div>`).join('');

        results.querySelectorAll('[data-action="request-rental"]').forEach(btn => {
            btn.addEventListener('click', () => openRentalModal(btn.dataset.book, btn.dataset.owner, btn.dataset.name));
        });
    }

    // ─── Rentals ─────────────────────────────────────────────────────────────
    function openRentalModal(bookId, ownerId, bookName) {
        if (!IS_LOGGED_IN) { toast('Please sign in to request books', 'error'); return; }
        $('#bs-rental-book-id').value  = bookId;
        $('#bs-rental-owner-id').value = ownerId;
        $('#bs-rental-book-name').textContent = bookName;
        openModal('bs-modal-rental');
    }

    async function sendRentalRequest() {
        const bookId  = parseInt($('#bs-rental-book-id')?.value);
        const ownerId = parseInt($('#bs-rental-owner-id')?.value);
        const message = $('#bs-rental-message')?.value?.trim();
        const start   = $('#bs-rental-start')?.value;
        const end     = $('#bs-rental-end')?.value;

        const btn = $('#bs-btn-send-rental');
        btn.disabled = true; btn.textContent = 'Sending…';

        const data = await request('POST', 'rentals/request', { book_id: bookId, owner_id: ownerId, message, start_date: start, end_date: end });
        btn.disabled = false; btn.textContent = 'Send Request';

        if (data.id) {
            toast('Request sent! 📬', 'success');
            closeModal('bs-modal-rental');
            closeModal('bs-modal-detail');
        } else {
            toast(data.message || 'Could not send request', 'error');
        }
    }

    async function loadRentals() {
        if (!IS_LOGGED_IN) return;
        loading(true);
        const [inc, out] = await Promise.all([
            request('GET', 'rentals/incoming'),
            request('GET', 'rentals/outgoing'),
        ]);
        loading(false);
        state.incoming = Array.isArray(inc) ? inc : [];
        state.outgoing = Array.isArray(out) ? out : [];

        // Update badge
        const pending = state.incoming.filter(r => r.status === 'pending').length;
        const badge   = $('#bs-badge-rentals');
        if (badge) { badge.textContent = pending; badge.style.display = pending ? 'inline-flex' : 'none'; }

        renderRentals();
    }

    function rentalCardHtml(r, isOwner) {
        const cover = r.cover_url
            ? `<img src="${esc(r.cover_url)}" class="bs-rental-cover" style="object-fit:cover" alt="">`
            : `<div class="bs-rental-cover">📚</div>`;

        let actions = '';
        if (isOwner && r.status === 'pending') {
            actions = `<button class="bs-btn bs-btn-success bs-btn-sm" data-action="approve" data-id="${esc(r.id)}">Approve</button>
                       <button class="bs-btn bs-btn-danger bs-btn-sm" data-action="reject" data-id="${esc(r.id)}">Reject</button>`;
        } else if (isOwner && r.status === 'approved') {
            actions = `<button class="bs-btn bs-btn-primary bs-btn-sm" data-action="returned" data-id="${esc(r.id)}">Mark Returned</button>`;
        } else if (!isOwner && r.status === 'pending') {
            actions = `<button class="bs-btn bs-btn-ghost bs-btn-sm" data-action="cancel" data-id="${esc(r.id)}">Cancel</button>`;
        }

        const who = isOwner
            ? `<div class="bs-rental-who">From: <strong>${esc(r.requester_name)}</strong></div>`
            : `<div class="bs-rental-who">Owner: <strong>${esc(r.owner_name)}</strong></div>`;

        const dates = (r.start_date || r.end_date)
            ? `<span class="bs-rental-dates">${r.start_date || '?'} → ${r.end_date || '?'}</span>`
            : '';

        return `<div class="bs-rental-card">
            <div class="bs-rental-top">
                ${cover}
                <div style="flex:1">
                    <div class="bs-rental-title">${esc(r.title)}</div>
                    ${who}
                </div>
            </div>
            ${r.message ? `<div class="bs-rental-msg">"${esc(r.message)}"</div>` : ''}
            <div class="bs-rental-foot">
                <div style="display:flex;align-items:center;gap:10px">
                    ${statusBadge(r.status)}
                    ${dates}
                </div>
                ${actions ? `<div class="bs-rental-actions">${actions}</div>` : ''}
            </div>
        </div>`;
    }

    function renderRentals() {
        const inc = $('#bs-rentals-incoming');
        const out = $('#bs-rentals-outgoing');

        if (inc) {
            inc.innerHTML = state.incoming.length
                ? state.incoming.map(r => rentalCardHtml(r, true)).join('')
                : '<div class="bs-empty-state"><div class="bs-empty-icon">📭</div><p>No incoming requests</p></div>';
            bindRentalActions(inc);
        }

        if (out) {
            out.innerHTML = state.outgoing.length
                ? state.outgoing.map(r => rentalCardHtml(r, false)).join('')
                : '<div class="bs-empty-state"><div class="bs-empty-icon">📬</div><p>No outgoing requests</p></div>';
            bindRentalActions(out);
        }
    }

    function bindRentalActions(container) {
        container.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id     = btn.dataset.id;
                const action = btn.dataset.action;
                const statusMap = { approve: 'approved', reject: 'rejected', returned: 'returned', cancel: 'cancelled' };
                const status = statusMap[action];
                if (!status) return;
                btn.disabled = true;
                await request('POST', `rentals/${id}/status`, { status });
                toast('Status updated', 'success');
                loadRentals();
            });
        });
    }

    // ─── Event bindings ───────────────────────────────────────────────────────
    function bindEvents() {
        // Close modals
        $$('[data-close]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.close)));
        $$('.bs-modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', e => {
                if (e.target === overlay) overlay.style.display = 'none';
            });
        });

        // Catalog search
        const searchInput = $('#bs-catalog-search');
        if (searchInput) {
            let timer;
            searchInput.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    state.catalogSearch = searchInput.value;
                    state.catalogPage   = 0;
                    loadCatalog();
                }, 350);
            });
        }

        // Genre filter
        const genreSel = $('#bs-catalog-genre');
        if (genreSel) {
            genreSel.addEventListener('change', () => {
                state.catalogGenre = genreSel.value;
                state.catalogPage  = 0;
                loadCatalog();
            });
        }

        // Add book button
        const addBookBtn = $('#bs-btn-add-book');
        if (addBookBtn) addBookBtn.addEventListener('click', openAddBookModal);

        // Save book
        const saveBookBtn = $('#bs-btn-save-book');
        if (saveBookBtn) saveBookBtn.addEventListener('click', saveBook);

        // Add to library from library tab
        const addLibBtn = $('#bs-btn-add-to-library');
        if (addLibBtn) addLibBtn.addEventListener('click', () => switchTab('catalog'));

        // Code search
        const codeBtn = $('#bs-btn-code-search');
        if (codeBtn) codeBtn.addEventListener('click', searchByCode);
        const codeInput = $('#bs-code-input');
        if (codeInput) codeInput.addEventListener('keyup', e => { if (e.key === 'Enter') searchByCode(); });

        // Rental modal send
        const sendBtn = $('#bs-btn-send-rental');
        if (sendBtn) sendBtn.addEventListener('click', sendRentalRequest);

        // Escape key closes modals
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') $$('.bs-modal-overlay').forEach(m => { m.style.display = 'none'; });
        });
    }

    // ─── Init ────────────────────────────────────────────────────────────────
    function init() {
        const app = document.getElementById('bs-app');
        if (!app) return;

        loading(false); // hide initial loader
        bindEvents();
        initTabs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

(function ($) {

    $(function () {
        
        const cookieName = 'bs_active_tab';

        // Cookie helpers
        function setCookie(name, value, days) {
            const d = new Date();
            d.setTime(d.getTime() + days * 86400000);
            document.cookie = `${name}=${value};expires=${d.toUTCString()};path=/`;
        }

        function getCookie(name) {
            return document.cookie
                .split('; ')
                .find(row => row.startsWith(name + '='))
                ?.split('=')[1] || null;
        }

        // When a tab is clicked → save cookie
        $('.bs-tab').on('click', function () {
            const tab = $(this).data('tab');
            setCookie(cookieName, tab, 7);
        });

        // After reload → auto-click saved tab
        const savedTab = getCookie(cookieName);
        if (savedTab && $(`.bs-tab[data-tab="${savedTab}"]`).length) {

            // Auto-click saved tab
            $(`.bs-tab[data-tab="${savedTab}"]`).trigger('click');

        } else {

            // If no cookie → click first tab
            $('.bs-tab').first().trigger('click');
        }

    });

})(jQuery);
