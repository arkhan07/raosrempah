/* ============================================================
   RajaOngkir Komerce - Public JavaScript (Frontend)
   ============================================================ */

(function ($) {
    'use strict';

    if (typeof KomercePublic === 'undefined') return;

    var ajax_url = KomercePublic.ajax_url;
    var nonce = KomercePublic.nonce;

    function debounce(fn, delay) {
        var timer;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    // ── Checkout Destination Search ───────────────────────────────────────────

    var $destSearch = $('#komerce-dest-search');
    var $destResults = $('#komerce-dest-results');
    var $destHidden = $('#komerce_receiver_destination_id');
    var $destSelected = $('#komerce-dest-selected');

    if ($destSearch.length) {

        var searchDest = debounce(function () {
            var kw = $destSearch.val().trim();
            if (kw.length < 3) { $destResults.hide().empty(); return; }

            $destResults.html('<li style="padding:10px 14px;color:#999">Mencari...</li>').show();

            $.post(ajax_url, {
                action: 'komerce_search_destination',
                nonce: nonce,
                keyword: kw
            }, function (res) {
                $destResults.empty();
                var items = (res && res.data) ? res.data : [];
                if (!items.length) {
                    $destResults.html('<li style="padding:10px 14px;color:#999">Tidak ditemukan</li>');
                    return;
                }
                $.each(items.slice(0, 12), function (i, item) {
                    var label = item.label || [item.subdistrict_name, item.district_name, item.city_name].filter(Boolean).join(', ');
                    var zip = item.zip_code ? ' (' + item.zip_code + ')' : '';
                    $('<li>')
                        .html('<strong>' + label + '</strong><span>' + zip + '</span>')
                        .data('id', item.id)
                        .data('label', label)
                        .appendTo($destResults);
                });
                $destResults.show();
            });
        }, 400);

        $destSearch.on('input', searchDest);

        $destResults.on('click', 'li', function () {
            var id = $(this).data('id');
            var label = $(this).data('label');
            if (!id) return;
            $destHidden.val(id).trigger('change');
            $destSearch.val(label);
            $destSelected.html('✅ Terpilih: <strong>' + label + '</strong>');
            $destResults.hide().empty();
            // Trigger WooCommerce shipping update
            $(document.body).trigger('update_checkout');
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#komerce-dest-search, #komerce-dest-results').length) {
                $destResults.hide();
            }
        });
    }

    // ── Public Tracking Shortcode ─────────────────────────────────────────────

    $(document).on('click', '#komerce-public-track-btn', function () {
        var shipping = $('#komerce-public-shipping').val();
        var awb = $('#komerce-public-awb').val().trim();
        var $output = $('#komerce-public-result');

        if (!awb) { alert('Masukkan nomor resi.'); return; }

        $output.html('<p style="text-align:center;padding:20px">🔍 Mencari...</p>');

        $.post(ajax_url, {
            action: 'komerce_track_awb',
            nonce: nonce,
            shipping: shipping,
            awb: awb
        }, function (res) {
            var meta = (res && res.meta) ? res.meta : {};
            var data = (res && res.data) ? res.data : {};

            if (meta.code !== 200 && meta.code !== 201) {
                $output.html('<div class="komerce-error-msg">❌ ' + (meta.message || 'Gagal mengambil data tracking') + '</div>');
                return;
            }

            var html = '<div class="komerce-tracking-header" style="margin-bottom:16px">'
                + '<strong>AWB:</strong> ' + (data.airway_bill || awb)
                + ' &nbsp;|&nbsp; <strong>Status:</strong> ' + (data.last_status || '-')
                + '</div>';

            var history = data.history || [];
            if (history.length) {
                html += '<div class="komerce-public-timeline">';
                $.each(history, function (i, item) {
                    var cls = (i === 0) ? 'is-latest' : '';
                    html += '<div class="timeline-item ' + cls + '">'
                        + '<div class="timeline-item-desc">' + (item.desc || '-') + '</div>'
                        + '<div class="timeline-item-date">📅 ' + (item.date || '-') + '</div>'
                        + '<span class="timeline-item-status">' + (item.status || '') + '</span>'
                        + '</div>';
                });
                html += '</div>';
            } else {
                html += '<p style="color:#888">Belum ada history tracking.</p>';
            }

            $output.html(html);
        });
    });

    // Allow pressing Enter in AWB field
    $(document).on('keypress', '#komerce-public-awb', function (e) {
        if (e.which === 13) { e.preventDefault(); $('#komerce-public-track-btn').trigger('click'); }
    });

})(jQuery);
