/**
 * BookCircle Admin JS — bookshare-admin.js
 */
(function ($) {
    'use strict';

    const API   = BSAdmin.root;
    const NONCE = BSAdmin.nonce;

    function api(method, path, data = null) {
        return $.ajax({
            url: API + path,
            method,
            contentType: 'application/json',
            data: data ? JSON.stringify(data) : undefined,
            beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', NONCE),
        });
    }

    function openModal(id)  { $('#' + id).addClass('open'); }
    function closeModal(id) { $('#' + id).removeClass('open'); }

    function notice(msg, type = 'success') {
        const cls  = type === 'success' ? 'notice-success' : 'notice-error';
        const html = `<div class="notice ${cls} is-dismissible"><p>${msg}</p></div>`;
        $('.bs-admin-notices').html(html);
        setTimeout(() => $('.bs-admin-notices').html(''), 4000);
    }

    // ── Generic CRUD table ────────────────────────────────────────────────────
    $(document).on('click', '[data-bs-delete]', function () {
        const endpoint = $(this).data('bs-delete');
        const row      = $(this).closest('tr');
        if (!confirm('Delete this item? This cannot be undone.')) return;
        api('DELETE', endpoint)
            .done(() => { row.fadeOut(300, () => row.remove()); notice('Deleted.'); })
            .fail(xhr => notice(xhr.responseJSON?.message || 'Error deleting.', 'error'));
    });

    // ── Book form ─────────────────────────────────────────────────────────────
    $(document).on('click', '#bs-btn-add-book-admin', function () {
        $('#bs-admin-book-form')[0].reset();
        $('#bs-book-edit-id').val('');
        $('#bs-admin-book-modal-title').text('Add New Book');
        openModal('bs-admin-book-modal');
    });

    $(document).on('click', '[data-edit-book]', function () {
        const id = $(this).data('edit-book');
        api('GET', `books/id/${id}`) // note: if needed, fallback to store data in data-* attributes
            .done(book => {
                $('#bs-book-edit-id').val(book.id);
                $('#bs-admin-book-title').val(book.title);
                $('#bs-admin-book-genre').val(book.genre);
                $('#bs-admin-book-isbn').val(book.isbn);
                $('#bs-admin-book-year').val(book.published_year);
                $('#bs-admin-book-pages').val(book.pages);
                $('#bs-admin-book-language').val(book.language);
                $('#bs-admin-book-cover').val(book.cover_url);
                $('#bs-admin-book-desc').val(book.description);
                $('#bs-admin-book-author').val(book.author_id);
                $('#bs-admin-book-publisher').val(book.publisher_id);
                $('#bs-admin-book-modal-title').text('Edit Book');
                openModal('bs-admin-book-modal');
            });
    });

    $(document).on('click', '#bs-btn-save-book-admin', function () {
        const editId = $('#bs-book-edit-id').val();
        const payload = {
            title:          $('#bs-admin-book-title').val().trim(),
            author_id:      $('#bs-admin-book-author').val() || null,
            publisher_id:   $('#bs-admin-book-publisher').val() || null,
            genre:          $('#bs-admin-book-genre').val().trim(),
            isbn:           $('#bs-admin-book-isbn').val().trim(),
            published_year: parseInt($('#bs-admin-book-year').val()) || null,
            pages:          parseInt($('#bs-admin-book-pages').val()) || null,
            language:       $('#bs-admin-book-language').val().trim() || 'English',
            cover_url:      $('#bs-admin-book-cover').val().trim(),
            description:    $('#bs-admin-book-desc').val().trim(),
        };
        if (!payload.title) { notice('Title is required.', 'error'); return; }

        const method = editId ? 'PUT' : 'POST';
        const path   = editId ? `books/${editId}` : 'books';

        api(method, path, payload)
            .done(() => { notice('Book saved!'); closeModal('bs-admin-book-modal'); location.reload(); })
            .fail(xhr => notice(xhr.responseJSON?.message || 'Error.', 'error'));
    });

    // ── Author form ───────────────────────────────────────────────────────────
    $(document).on('click', '#bs-btn-add-author', function () {
        $('#bs-admin-author-form')[0].reset();
        $('#bs-author-edit-id').val('');
        openModal('bs-admin-author-modal');
    });

    $(document).on('click', '#bs-btn-save-author', function () {
        const editId = $('#bs-author-edit-id').val();
        const payload = {
            name:             $('#bs-author-name').val().trim(),
            bio:              $('#bs-author-bio').val().trim(),
            email:            $('#bs-author-email').val().trim(),
            website:          $('#bs-author-website').val().trim(),
            birth_date:       $('#bs-author-birth').val() || null,
            nationality:      $('#bs-author-nationality').val().trim(),
            photo_url:        $('#bs-author-photo').val().trim(),
            social_twitter:   $('#bs-author-twitter').val().trim(),
            social_instagram: $('#bs-author-instagram').val().trim(),
            social_facebook:  $('#bs-author-facebook').val().trim(),
        };
        if (!payload.name) { notice('Name is required.', 'error'); return; }
        const method = editId ? 'PUT' : 'POST';
        const path   = editId ? `authors/${editId}` : 'authors';
        api(method, path, payload)
            .done(() => { notice('Author saved!'); closeModal('bs-admin-author-modal'); location.reload(); })
            .fail(xhr => notice(xhr.responseJSON?.message || 'Error.', 'error'));
    });

    // ── Publisher form ────────────────────────────────────────────────────────
    $(document).on('click', '#bs-btn-add-publisher', function () {
        $('#bs-admin-publisher-form')[0].reset();
        $('#bs-publisher-edit-id').val('');
        openModal('bs-admin-publisher-modal');
    });

    $(document).on('click', '#bs-btn-save-publisher', function () {
        const editId = $('#bs-publisher-edit-id').val();
        const payload = {
            name:         $('#bs-pub-name').val().trim(),
            description:  $('#bs-pub-desc').val().trim(),
            email:        $('#bs-pub-email').val().trim(),
            phone:        $('#bs-pub-phone').val().trim(),
            website:      $('#bs-pub-website').val().trim(),
            address:      $('#bs-pub-address').val().trim(),
            city:         $('#bs-pub-city').val().trim(),
            country:      $('#bs-pub-country').val().trim(),
            founded_year: parseInt($('#bs-pub-year').val()) || null,
            logo_url:     $('#bs-pub-logo').val().trim(),
        };
        if (!payload.name) { notice('Name is required.', 'error'); return; }
        const method = editId ? 'PUT' : 'POST';
        const path   = editId ? `publishers/${editId}` : 'publishers';
        api(method, path, payload)
            .done(() => { notice('Publisher saved!'); closeModal('bs-admin-publisher-modal'); location.reload(); })
            .fail(xhr => notice(xhr.responseJSON?.message || 'Error.', 'error'));
    });

    // ── Rental status ─────────────────────────────────────────────────────────
    $(document).on('change', '[data-rental-status]', function () {
        const id     = $(this).data('rental-status');
        const status = $(this).val();
        api('POST', `rentals/${id}/status`, { status })
            .done(() => notice('Status updated.'))
            .fail(() => notice('Error updating status.', 'error'));
    });

    // ── Close modals ──────────────────────────────────────────────────────────
    $(document).on('click', '[data-bs-close-modal]', function () {
        closeModal($(this).data('bs-close-modal'));
    });
    $(document).on('click', '.bs-admin-modal-overlay', function (e) {
        if ($(e.target).hasClass('bs-admin-modal-overlay')) $(this).removeClass('open');
    });

    $(document).keydown(function (e) {
        if (e.key === 'Escape') $('.bs-admin-modal-overlay').removeClass('open');
    });

})(jQuery);
