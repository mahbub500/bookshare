/**
 * BookCircle — Rokomari Importer  (assets/js/importer.js)
 *
 * Depends on: jQuery, BSImporter (localized by ImportController::enqueue_assets)
 *
 * BSImporter = {
 *   endpoint : 'https://site.com/wp-json/bookshare/v1/import/rokomari',
 *   nonce    : '<wp_rest nonce>',
 *   edit_url : 'https://site.com/wp-admin/post.php',
 * }
 */
/* global BSImporter */
( function ( $ ) {
    'use strict';

    // ── DOM refs ──────────────────────────────────────────────────────────────
    const $btn      = $( '#bs-import-btn' );
    const $spinner  = $( '#bs-import-spinner' );
    const $progress = $( '#bs-import-progress' );
    const $results  = $( '#bs-import-results' );
    const $tbody    = $( '#bs-import-tbody' );
    const $summary  = $( '#bs-import-summary' );

    // ── Import click ──────────────────────────────────────────────────────────
    $btn.on( 'click', function () {
        const raw  = $( '#bs-import-urls' ).val().trim();
        const urls = parseUrls( raw );

        if ( ! urls.length ) {
            alert( 'Please enter at least one Rokomari URL starting with https://' );
            return;
        }

        setLoading( true, 'Importing ' + urls.length + ' book(s)\u2026' );
        $tbody.empty();
        $results.prop( 'hidden', true );
        $summary.text( '' );

        $.ajax( {
            url         : BSImporter.endpoint,
            method      : 'POST',
            contentType : 'application/json',
            data        : JSON.stringify( { urls: urls } ),
            beforeSend  : function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', BSImporter.nonce );
            },
            success : handleSuccess,
            error   : handleError,
        } );
    } );

    // ── Success handler ───────────────────────────────────────────────────────
    function handleSuccess( response ) {
        setLoading( false, '' );

        if ( ! response || ! Array.isArray( response.results ) ) {
            alert( 'Unexpected response from server.' );
            return;
        }

        var imported = 0, skipped = 0, errors = 0;

        response.results.forEach( function ( r ) {
            var data = r.data || {};

            // ── Build author tags ─────────────────────────────────────────────
            var authorHtml = '—';
            if ( Array.isArray( data.author_names ) && data.author_names.length ) {
                authorHtml = data.author_names
                    .map( function ( n ) {
                        return '<span class="bs-author-tag">' + esc( n ) + '</span>';
                    } )
                    .join( ' ' );
            }

            // ── Publisher ─────────────────────────────────────────────────────
            var publisherHtml = data.publisher_name ? esc( data.publisher_name ) : '—';

            // ── Title with edit link ──────────────────────────────────────────
            var title     = data.title || '—';
            var titleHtml = r.post_id
                ? '<a href="' + BSImporter.edit_url + '?post=' + r.post_id + '&action=edit" target="_blank">'
                    + esc( title ) + ' <span style="opacity:.5">&#8599;</span></a>'
                : esc( title );

            // ── Status badge ──────────────────────────────────────────────────
            var badgeClass = {
                imported : 'bs-badge-imported',
                skipped  : 'bs-badge-skipped',
                error    : 'bs-badge-error',
            }[ r.status ] || 'bs-badge-skipped';

            var badgeIcon = {
                imported : '✅',
                skipped  : '⚠️',
                error    : '❌',
            }[ r.status ] || '•';

            var statusHtml = '<span class="bs-badge ' + badgeClass + '">'
                + badgeIcon + ' ' + cap( r.status ) + '</span>';

            // ── Row ───────────────────────────────────────────────────────────
            $tbody.append(
                '<tr>'
                + '<td class="cell-url"><a href="' + esc( r.url ) + '" target="_blank" rel="noopener">'
                    + truncate( r.url, 50 ) + '</a></td>'
                + '<td class="cell-title">' + titleHtml + '</td>'
                + '<td class="cell-authors">' + authorHtml + '</td>'
                + '<td class="cell-publisher">' + publisherHtml + '</td>'
                + '<td>' + statusHtml + '</td>'
                + '<td style="font-size:12px;color:#6b7280">' + esc( r.message || '' ) + '</td>'
                + '</tr>'
            );

            if ( r.status === 'imported' ) { imported++; }
            else if ( r.status === 'skipped' ) { skipped++; }
            else { errors++; }
        } );

        $summary.text(
            '\u2705 ' + imported + ' imported'
            + '  \u26A0\uFE0F ' + skipped + ' skipped'
            + '  \u274C ' + errors + ' errors'
        );
        $results.prop( 'hidden', false );
    }

    // ── Error handler ─────────────────────────────────────────────────────────
    function handleError( xhr ) {
        setLoading( false, '' );
        var msg = 'Import request failed.';
        if ( xhr.responseJSON && xhr.responseJSON.message ) {
            msg = xhr.responseJSON.message;
        }
        alert( 'Error: ' + msg );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function setLoading( loading, progressText ) {
        $btn.prop( 'disabled', loading );
        if ( loading ) {
            $spinner.addClass( 'is-active' );
        } else {
            $spinner.removeClass( 'is-active' );
        }
        $progress.text( progressText || '' );
    }

    /**
     * Parse textarea content into a clean array of valid Rokomari URLs.
     * Strips blank lines and lines that don't start with http.
     */
    function parseUrls( raw ) {
        return raw
            .split( /\r?\n/ )
            .map( function ( l ) { return l.trim(); } )
            .filter( function ( l ) {
                return l.length > 0 && l.startsWith( 'http' );
            } );
    }

    /** HTML-escape a string to prevent XSS in dynamic table cells. */
    function esc( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

    /** Capitalise first letter. */
    function cap( str ) {
        return str.charAt( 0 ).toUpperCase() + str.slice( 1 );
    }

    /** Truncate a string and add ellipsis. */
    function truncate( str, max ) {
        return str.length > max ? str.slice( 0, max ) + '\u2026' : str;
    }

} )( jQuery );