/* ============================================================
   RajaOngkir Komerce - Admin JavaScript
   ============================================================ */

(function ($) {
    'use strict';

    // ── Utilities ────────────────────────────────────────────────────────────

    function debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function doAjax(action, data, callback) {
        $.post(KomerceAdmin.ajax_url, $.extend({ action, nonce: KomerceAdmin.nonce }, data), callback);
    }

    // ── Destination Autocomplete (Shipper - Settings Page) ───────────────────

    function initDestAutocomplete({ inputId, listId, hiddenId, selectedId }) {
        const $input = $('#' + inputId);
        const $list = $('#' + listId);
        const $hidden = $('#' + hiddenId);
        const $selected = $('#' + selectedId);

        if (!$input.length) return;

        const doSearch = debounce(function () {
            const keyword = $input.val().trim();
            if (keyword.length < 3) { $list.hide().empty(); return; }

            $list.html('<li style="padding:10px 14px;color:#999">Mencari...</li>').show();
            doAjax('komerce_admin_search_dest', { keyword }, function (res) {
                $list.empty();
                const items = res?.data || [];
                if (!items.length) {
                    $list.html('<li style="padding:10px 14px;color:#999">Tidak ditemukan</li>');
                    return;
                }
                items.slice(0, 10).forEach(function (item) {
                    const label = item.label || [item.subdistrict_name, item.district_name, item.city_name].filter(Boolean).join(', ');
                    const $li = $('<li></li>').html(
                        `<strong>${label}</strong><span>${item.zip_code || ''}</span>`
                    ).data('id', item.id).data('label', label);
                    $list.append($li);
                });
                $list.show();
            });
        }, 400);

        $input.on('input', doSearch);

        $list.on('click', 'li', function () {
            const id = $(this).data('id');
            const label = $(this).data('label');
            $hidden.val(id);
            $input.val(label);
            $selected.html(`✅ Terpilih: <strong>${label}</strong> (ID: ${id})`);
            $list.hide().empty();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#' + inputId + ', #' + listId).length) {
                $list.hide();
            }
        });
    }

    // Settings page: shipper destination
    initDestAutocomplete({
        inputId: 'shipper_dest_search',
        listId: 'shipper-dest-results',
        hiddenId: 'shipper_dest_id',
        selectedId: 'shipper-dest-selected',
    });

    // ── Datepicker for Pickup Date ───────────────────────────────────────────

    if ($.fn.datepicker && $('#pickup_date').length) {
        const today = new Date();
        $('#pickup_date').datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: today,
            onSelect: function (val) { $(this).val(val); },
        }).val(today.toISOString().split('T')[0]);
    }

    // ── Select All Checkboxes (Pickup) ───────────────────────────────────────

    $('#komerce-check-all').on('change', function () {
        $('input[name="order_numbers[]"]').prop('checked', this.checked);
    });

    // ── Manual Order Add (Pickup) ────────────────────────────────────────────

    $('#komerce-add-manual').on('click', function () {
        const $row = $('<div class="manual-order-row">' +
            '<input type="text" class="regular-text manual-order-no" name="order_numbers[]" placeholder="No. Order Komerce">' +
            '<button type="button" class="button komerce-remove-manual">✕</button>' +
            '</div>');
        $('#komerce-manual-orders').append($row);
    });

    $(document).on('click', '.komerce-remove-manual', function () {
        $(this).closest('.manual-order-row').remove();
    });

    // Also register existing manual inputs in pickup form
    $(document).on('blur', '.manual-order-no', function () {
        const val = $(this).val().trim();
        if (val) $(this).attr('name', 'order_numbers[]');
    });

    // ── Tracking ─────────────────────────────────────────────────────────────

    function renderTimeline(history) {
        if (!history || !history.length) return '<p style="color:#999">Tidak ada history tracking.</p>';
        return history.map(function (item, idx) {
            const isLatest = idx === 0 ? 'is-latest' : '';
            return `<div class="timeline-item ${isLatest}">
                <div class="timeline-item-desc">${item.desc || '-'}</div>
                <div class="timeline-item-date">📅 ${item.date || '-'}</div>
                <span class="timeline-item-status">${item.status || ''}</span>
            </div>`;
        }).join('');
    }

    $('#komerce-do-track').on('click', function () {
        const shipping = $('#track_shipping').val();
        const awb = $('#track_awb').val().trim();

        if (!awb) { alert('Masukkan No. Resi terlebih dahulu.'); return; }

        const $result = $('#komerce-tracking-result');
        const $loading = $('#komerce-tracking-loading');
        $loading.show();
        $result.html('');

        doAjax('komerce_admin_track', { shipping, awb }, function (res) {
            $loading.hide();
            const meta = res?.meta || {};
            const data = res?.data || {};

            if (meta.code !== 200 && meta.code !== 201) {
                $result.html(`<div class="komerce-status status-error">❌ ${meta.message || 'Gagal mengambil tracking'}</div>`);
                return;
            }

            const html = `
                <div class="komerce-tracking-header">
                    <div class="tracking-awb-info">
                        <span class="tracking-awb-label">AWB:</span>
                        <strong class="tracking-awb-number">${data.airway_bill || awb}</strong>
                    </div>
                    <span class="komerce-badge badge-status">📍 ${data.last_status || '-'}</span>
                </div>
                <div class="komerce-timeline">${renderTimeline(data.history || [])}</div>
            `;
            $result.html(html);
        });
    });

    // Auto-trigger tracking if AWB is pre-filled (from URL param)
    if ($('#track_awb').val()) {
        setTimeout(function () { $('#komerce-do-track').trigger('click'); }, 300);
    }

    // ── Print Label ──────────────────────────────────────────────────────────

    function generateLabel(orderNo, page) {
        if (!orderNo) { alert('Masukkan No. Order terlebih dahulu.'); return; }

        $('#komerce-label-result').hide();
        $('#komerce-label-error').hide();
        $('#komerce-generate-label, .komerce-quick-label').prop('disabled', true).text('⏳ Generating...');

        doAjax('komerce_admin_print_label', { order_no: orderNo, page: page }, function (res) {
            $('#komerce-generate-label').prop('disabled', false).text('🖨️ Generate Label PDF');
            $('.komerce-quick-label').prop('disabled', false).text('🖨️ Cetak');

            const meta = res?.meta || {};
            const data = res?.data || {};

            if ((meta.code === 200 || meta.code === 201) && data.path) {
                const fullUrl = KomerceAdmin.label_base + data.path;
                $('#komerce-label-msg').text('File: ' + data.path);
                $('#komerce-label-link').attr('href', fullUrl);
                $('#komerce-label-result').show();
            } else {
                $('#komerce-label-error-msg').text('❌ ' + (meta.message || 'Gagal generate label'));
                $('#komerce-label-error').show();
            }
        });
    }

    $('#komerce-generate-label').on('click', function () {
        generateLabel($('#label_order_no').val().trim(), $('#label_page').val());
    });

    $(document).on('click', '.komerce-quick-label', function () {
        const orderNo = $(this).data('order-no');
        $('#label_order_no').val(orderNo);
        generateLabel(orderNo, 'page_2');
    });

    // ── Dismiss notices auto ─────────────────────────────────────────────────

    setTimeout(function () { $('.is-dismissible').fadeOut(500); }, 5000);

})(jQuery);
