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

    // Modal refs
    const $overlay  = $( '#bs-modal-overlay' );
    const $mIcon    = $( '#bs-modal-icon' );
    const $mTitle   = $( '#bs-modal-title' );
    const $mMessage = $( '#bs-modal-message' );
    const $mOk      = $( '#bs-modal-ok' );
    const $mX       = $( '#bs-modal-x' );

    // =========================================================================
    // MODAL
    // =========================================================================

    /**
     * showModal( options )
     *
     * options = {
     *   type    : 'info' | 'warning' | 'error'   (default: 'info')
     *   title   : string
     *   message : string
     * }
     */
    function showModal( options ) {
        const type    = options.type    || 'info';
        const title   = options.title   || '';
        const message = options.message || '';

        const iconMap = {
            info    : '💬',
            warning : '⚠️',
            error   : '❌',
            success : '✅',
        };

        // Set content
        $mIcon.text( iconMap[ type ] || '💬' );
        $mTitle.text( title );
        $mMessage.text( message );

        // Set colour modifier on modal box
        $overlay.find( '.bs-modal' )
            .removeClass( 'bs-modal--info bs-modal--warning bs-modal--error bs-modal--success' )
            .addClass( 'bs-modal--' + type );

        /*
         * Open:
         *   1. Remove [hidden] so the element re-enters the layout.
         *   2. On the next animation frame, add .bs-modal-visible which:
         *        - sets opacity:1  (fade in)
         *        - sets pointer-events:auto  (page is interactive again)
         *        - sets visibility:visible   (back in tab order)
         *      Using rAF ensures the browser has painted the hidden→visible
         *      state change before we trigger the CSS transition.
         */
        $overlay.prop( 'hidden', false );
        requestAnimationFrame( function () {
            $overlay.addClass( 'bs-modal-visible' );
            // Focus OK button for keyboard / screen-reader accessibility
            setTimeout( function () { $mOk.trigger( 'focus' ); }, 50 );
        } );
    }

    function hideModal() {
        /*
         * Close:
         *   1. Remove .bs-modal-visible — CSS transitions opacity to 0,
         *      pointer-events back to none, visibility to hidden.
         *      The overlay is now invisible AND non-interactive immediately.
         *   2. After the transition (220ms), set [hidden] so it is fully
         *      removed from layout / accessibility tree.
         */
        $overlay.removeClass( 'bs-modal-visible' );
        setTimeout( function () {
            $overlay.prop( 'hidden', true );
        }, 230 );
    }

    // Close on OK button
    $mOk.on( 'click', hideModal );

    // Close on × button
    $mX.on( 'click', hideModal );

    // Close on overlay backdrop click (click outside the modal box)
    $overlay.on( 'click', function ( e ) {
        if ( $( e.target ).is( '#bs-modal-overlay' ) ) {
            hideModal();
        }
    } );

    // Close on Escape key
    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' && ! $overlay.prop( 'hidden' ) ) {
            hideModal();
        }
    } );

    // =========================================================================
    // IMPORT CLICK
    // =========================================================================

    $btn.on( 'click', function () {
        const raw  = $( '#bs-import-urls' ).val().trim();
        const urls = parseUrls( raw );

        if ( ! urls.length ) {
            // ── was: alert('Please enter...') ─────────────────────────────────
            showModal( {
                type    : 'warning',
                title   : 'No URLs entered',
                message : 'Please enter at least one Rokomari URL starting with https://',
            } );
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

    // =========================================================================
    // SUCCESS HANDLER
    // =========================================================================

    function handleSuccess( response ) {
        setLoading( false, '' );

        if ( ! response || ! Array.isArray( response.results ) ) {
            // ── was: alert('Unexpected response...') ──────────────────────────
            showModal( {
                type    : 'error',
                title   : 'Unexpected Response',
                message : 'The server returned an unexpected response. Please check your error log.',
            } );
            return;
        }

        var imported = 0, skipped = 0, errors = 0;

        response.results.forEach( function ( r ) {
            var data = r.data || {};

            // ── Author tags ───────────────────────────────────────────────────
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
                ? '<a href="' + BSImporter.edit_url + '?post=' + r.post_id + '&action=edit"'
                    + ' target="_blank" rel="noopener">'
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

            // ── Append row ────────────────────────────────────────────────────
            $tbody.append(
                '<tr>'
                + '<td class="cell-url"><a href="' + esc( r.url ) + '" target="_blank" rel="noopener">'
                    + truncate( r.url, 50 ) + '</a></td>'
                + '<td class="cell-title">' + titleHtml + '</td>'
                + '<td class="cell-authors">' + authorHtml + '</td>'
                + '<td class="cell-publisher">' + publisherHtml + '</td>'
                + '<td>' + statusHtml + '</td>'
                + '<td class="cell-note">' + esc( r.message || '' ) + '</td>'
                + '</tr>'
            );

            if ( r.status === 'imported' )      { imported++; }
            else if ( r.status === 'skipped' )  { skipped++;  }
            else                                { errors++;   }
        } );

        $summary.text(
            '\u2705 ' + imported + ' imported'
            + '  \u26A0\uFE0F ' + skipped + ' skipped'
            + '  \u274C ' + errors + ' errors'
        );
        $results.prop( 'hidden', false );
    }

    // =========================================================================
    // ERROR HANDLER
    // =========================================================================

    function handleError( xhr ) {
        setLoading( false, '' );

        var msg = 'The import request failed. Please check your server error log.';
        if ( xhr.responseJSON && xhr.responseJSON.message ) {
            msg = xhr.responseJSON.message;
        } else if ( xhr.status ) {
            msg = 'HTTP ' + xhr.status + ' — ' + ( xhr.statusText || 'Unknown error' );
        }

        // ── was: alert('Error: ' + msg) ───────────────────────────────────────
        showModal( {
            type    : 'error',
            title   : 'Import Failed',
            message : msg,
        } );
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    function setLoading( loading, progressText ) {
        $btn.prop( 'disabled', loading );
        if ( loading ) {
            $spinner.addClass( 'is-active' );
        } else {
            $spinner.removeClass( 'is-active' );
        }
        $progress.text( progressText || '' );
    }

    /** Parse textarea into clean array of URLs. */
    function parseUrls( raw ) {
        return raw
            .split( /\r?\n/ )
            .map( function ( l ) { return l.trim(); } )
            .filter( function ( l ) {
                return l.length > 0 && l.startsWith( 'http' );
            } );
    }

    /** HTML-escape to prevent XSS in dynamic cells. */
    function esc( str ) {
        return String( str )
            .replace( /&/g,  '&amp;'  )
            .replace( /</g,  '&lt;'   )
            .replace( />/g,  '&gt;'   )
            .replace( /"/g,  '&quot;' );
    }

    /** Capitalise first letter. */
    function cap( str ) {
        return str.charAt( 0 ).toUpperCase() + str.slice( 1 );
    }

    /** Truncate with ellipsis. */
    function truncate( str, max ) {
        return str.length > max ? str.slice( 0, max ) + '\u2026' : str;
    }

} )( jQuery );