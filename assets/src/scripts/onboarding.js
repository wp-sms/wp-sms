import DataTable from 'datatables.net';

jQuery(document).ready(function ($) {
    'use strict';

    const notices = document.querySelectorAll('.wpsms-admin-notice');
    const mainContent = document.querySelector('.o-section.c-section--maincontent');
    if (notices.length > 0 && mainContent) {
        notices.forEach(notice => {
            notice.classList.add('active')
            mainContent.insertBefore(notice, mainContent.firstChild);
        });
    }
    const wpSmsItiTel = document.querySelector(".wp-sms-input-iti-tel");
    const countryCodeField = document.querySelector("#wp-sms-country-code-field");
    if(wpSmsItiTel){
        const body = document.body;
        const direction = body.classList.contains('rtl') ? 'rtl' : 'ltr';
        wpSmsItiTel.setAttribute('dir', direction)
        let iti_tel = window.intlTelInput(wpSmsItiTel, {
            autoInsertDialCode: true,
            allowDropdown: true,
            strictMode: true,
            useFullscreenPopup: false,
            dropdownContainer: body.classList.contains('rtl') ? null : body,
            nationalMode: true,
            formatOnDisplay: false,
        });
        if (countryCodeField) {
            countryCodeField.value = iti_tel.getSelectedCountryData().dialCode;
        }
        wpSmsItiTel.addEventListener('countrychange', function() {
            if (countryCodeField) {
                countryCodeField.value = iti_tel.getSelectedCountryData().dialCode;
            }
        });
        wpSmsItiTel.addEventListener('blur', function () {
            setDefaultCode(this, iti_tel);
        });
    }

    function setDefaultCode(item, intlTelInputElement) {
        if (item.value == '') {
            let country = intlTelInputElement.getSelectedCountryData();
            item.value = '+' + country.dialCode;
        } else {
            if (intlTelInputElement.getNumber()) {
                item.value = intlTelInputElement.getNumber().replace(/[-\s]/g, '')
            } else {
                item.value = item.value.replace(/[-\s]/g, '')
            }
        }
    }

    // Initialize Select2 with custom placeholder
    $('.wpsms-onboarding select').select2({
        dropdownCssClass: 'c-select2-dropdown'
    }).on('select2:open', function () {
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
        columnDefs: [
            {
                targets: [4],
                visible: false
            }
        ],
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
    if(table){
        // Handle row selection
        table.on('click', 'tbody tr:not(.disabled)', function (event) {
            event.stopPropagation();
            let radio = $(this).find('input[type="radio"]');
            if (radio.length) {
                radio.prop('checked', true).trigger('change');
            }
        });
        $(document).on('change', '.js-table td input[type="radio"]', function (e) {
            let $this = $(this);
            table.$('input[type="radio"]').not(this).prop('checked', false);
            table.$('tr').removeClass('selected-row');
            $this.closest('tr').addClass('selected-row');
            $('.c-form__footer input[type="submit"]').val('Continue').prop('disabled', false);
        });

    }


    // Search functionality
    $('#searchGateway').on('keyup', function () {
        table.column(0).search(this.value).draw();
    });


    // let chosen_country = $('.chosen-country').val();
    //
    // if ($('#filterCountries option[value="' + chosen_country + '"]').length > 0) {
    //     $('#filterCountries').val(chosen_country).trigger('change');
    //     table.column(4).search(chosen_country).draw();
    // }

    $('#filterCountries').on('select2:select', function (e) {
        let selectedCountry = e.params.data.id;
        if (selectedCountry === 'All') {
            table.column(4).search('').draw();
        } else {
            table.column(4).search(selectedCountry).draw();
        }
    });

    // Navigation step click event
    $('.s-nav--steps li').on('click', function () {
        let href = $(this).find('a').attr('href');
        if (href) window.location.href = href;
    });


    // Handle Test Connection
    $('#wp_sms_test_connection').on('click', function (e) {
        e.preventDefault();
        const testBtn = document.getElementById('wp_sms_test_connection');
        testBtn.classList.add('loading')
        // Collect data if needed
        let formData = {};
        $('.wp-sms-onboarding-step-configuration input, .wp-sms-onboarding-step-configuration select').each(function () {
            const fieldName = $(this).attr('name');
            const fieldValue = $(this).val();
            if (fieldName) {
                formData[fieldName] = fieldValue;
            }
        });

        // Include the collected form data in the AJAX request
        let data = {
            action: 'wp_sms_test_gateway',
            sub_action: 'test_status',
            _nonce: WP_Sms_Onboarding_Script_Object.nonce,
            ...formData // Spread the form data into the data object
        };

        // AJAX request
        $.ajax({
            url: WP_Sms_Onboarding_Script_Object.ajax_url, // WP AJAX URL passed from localize_script
            type: 'POST',
            data: data,
            success: function (response) {
                testBtn.classList.remove('loading')
                if (response.success) {
                    const data = response.data;

                    // Inject status values
                    $('.gateway-status-label').text(data.status.label).attr('class', data.status.class);
                    $('.gateway-status-description').text(data.status.description);

                    // Inject balance values
                    $('.gateway-balance-label').text(data.balance.label).attr('class', data.balance.class);
                    $('.gateway-balance-description').text(data.balance.description);

                    // Inject incoming message values
                    $('.gateway-incoming-label').text(data.incoming.label).attr('class', data.incoming.class);
                    $('.gateway-incoming-description').text(data.incoming.description);

                    // Inject bulk SMS values
                    $('.gateway-bulk-label').text(data.bulk.label).attr('class', data.bulk.class);
                    $('.gateway-bulk-description').text(data.bulk.description);

                    // Inject MMS values
                    $('.gateway-mms-label').text(data.mms.label).attr('class', data.mms.class);
                    $('.gateway-mms-description').text(data.mms.description);

                    // Display the container
                    $('.c-form__result').addClass(data.container.class);
                    $('.gateway-status-container').css('display', 'block');

                    // Replace button
                    $('#wp_sms_test_connection').replaceWith(
                        '<input class="c-btn c-btn--primary" type="submit" value="Continue"/>'
                    );
                }
            },
            error: function () {
                testBtn.classList.remove('loading')
                alert('There was an error. Please try again.');
            }
        });

    });

    $('#wp_sms_send_test_sms').on('click', function (e) {
        e.preventDefault();
        const testSmsBtn = document.getElementById('wp_sms_send_test_sms');
        testSmsBtn.classList.add('loading');

        let data = {
            action: 'wp_sms_test_gateway',
            sub_action: 'send_sms',
            _nonce: WP_Sms_Onboarding_Script_Object.nonce,
        };

        $.ajax({
            url: WP_Sms_Onboarding_Script_Object.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                testSmsBtn.classList.remove('loading');
                if (response.success) {
                    const messages = response.data.messages;
                    const classes = response.data.classes;
                    $('.wpsms-admin-alert--content')
                        .html('<h2>' + messages.confirmation_title + '</h2>' + '<p>' + messages.confirmation_text + '</p>')
                        .parent()
                        .addClass(classes.info_class);

                    $(testSmsBtn).remove();

                    $('a.c-btn--primary-light').css('display', 'inline-block');
                    $('input.c-btn--primary').css('display', 'inline-block');
                }
            },
            error: function () {
                testSmsBtn.classList.remove('loading');
                alert('There was an error sending the test SMS. Please try again.');
            }
        });
    });});
