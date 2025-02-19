import DataTable from 'datatables.net';

jQuery(document).ready(function ($) {
    'use strict';
    // Initialize Select2 with custom placeholder
    $('.wpsms-onboarding select').select2().on('select2:open', function () {
        $('.select2-search__field').attr('placeholder', 'Type to search...');
        $('.wpsms-onboarding select, .wpsms-onboarding .select2-container').css('display', 'inline-block');
    });

    if ($('.select2-container').length > 0) {
        $('.wpsms-skeleton__select').hide();
        $('.wpsms-onboarding select, .wpsms-onboarding .select2-container').css('display', 'inline-block');
    }
    // Initialize DataTable
    let table = new DataTable('.js-table', {
        searching: true,
        info: false,
        order: [],
        responsive: true,
        language: {
            paginate: {
                previous:
                    '<span class="prev-icon paginate_button"><svg xmlns="http://www.w3.org/2000/svg" width="4" height="7" fill="none"><path fill="#5B5B5B" d="M3.948 6.328a.175.175 0 0 1 0 .248l-.37.371a.168.168 0 0 1-.246 0L.116 3.731a.262.262 0 0 1-.077-.186v-.09c0-.07.028-.137.077-.186L3.332.053a.168.168 0 0 1 .245 0l.371.371a.175.175 0 0 1 0 .248L1.121 3.5l2.827 2.828Z"/></svg></span>',
                next:
                    '<span class="next-icon paginate_button"><svg xmlns="http://www.w3.org/2000/svg" width="4" height="7" fill="none"><path fill="#5B5B5B" d="M.052.672a.175.175 0 0 1 0-.248l.37-.371a.168.168 0 0 1 .246 0l3.216 3.216c.049.05.077.116.077.186v.09c0 .07-.028.137-.077.186L.668 6.947a.168.168 0 0 1-.245 0l-.371-.371a.175.175 0 0 1 0-.248L2.879 3.5.052.672Z"/></svg></span>',
                first: '',
                last: ''
            },
        },
        initComplete: function () {
            $('.wpsms-skeleton__table').hide();
            $('.js-table-gateway').css('display', 'table');
        }
    });

    // Handle row selection
    $('.js-table-gateway tbody tr:not(.disabled)').on('click', function (event) {
        event.stopPropagation();
        let radio = $(this).find('input[type="radio"]');
        if (radio.length) {
            radio.prop('checked', true).trigger('change');
        }
    });

    // Search functionality
    $('#searchGateway').on('keyup', function () {
        table.search(this.value).draw();
    });

    // Navigation step click event
    $('.s-nav--steps li').on('click', function () {
        let href = $(this).find('a').attr('href');
        if (href) window.location.href = href;
    });

    // Handle step 2 - row selection and button update
    $('.js-table-gateway td input[type="radio"]').on('change', function () {
        $('.c-table.js-table tbody tr').removeClass('selected-row');
        let selectedRow = $(this).closest('tr').addClass('selected-row');
        $('.c-form__footer input[type="submit"]').val('Continue').prop('disabled', false);
    });

    // Handle Test Connection
    $('#wp_sms_test_connection').on('click', function (e) {
        e.preventDefault(); // Prevent default action if it's a form button

        // Collect data if needed
        let data = {
            action: 'test_connection',
            nonce: wpSmsWizard.nonce,
        };

        // AJAX request
        $.ajax({
            url: wpSmsWizard.ajax_url, // WP AJAX URL passed from localize_script
            type: 'POST',
            data: data,
            success: function (response) {
                $('.gateway-status').html(response.data.status);
                $('.gateway-balance').html(response.data.balance);
                $('.gateway-incoming').html(response.data.incoming);
                $('.gateway-bulk').html(response.data.bulk);
                $('.gateway-mms').html(response.data.mms);

                $('.c-form__result').css('display', 'block');

                $('#wp_sms_test_connection').replaceWith(
                    '<input class="c-btn c-btn--primary" type="submit" value="Continue"/>'
                );
            }
        });
    });
});
