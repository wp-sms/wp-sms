import DataTable from 'datatables.net';

jQuery(document).ready(function ($) {
    'use strict';
    const wpsms_js = {};
    wpsms_js.global = wpsms_global;

    wpsms_js._ = function (key) {
        return (key in this.global.i18n ? this.global.i18n[key] : '');
    };

    const notices = document.querySelectorAll('.wpsms-admin-notice');
    const mainContent = document.querySelector('.o-section.c-section--maincontent');
    const sectionHeader = document.querySelector('.c-section--header');
    let existingNotice = mainContent.querySelector('.wpsms-admin-notice.notice-warning');

    if (notices.length > 0 && mainContent && sectionHeader) {
        let existingHeaderNotice = document.querySelector('.wpsms-admin-notice.notice-warning');
        if (!existingHeaderNotice) {
            notices.forEach(notice => {
                notice.classList.add('active');
                sectionHeader.insertAdjacentElement('afterend', notice);
            });
        }
    }
    const wpSmsItiTel = document.querySelector(".wp-sms-input-iti-tel");
    const countryCodeField = document.querySelector("#wp-sms-country-code-field");
    const formDescription = document.querySelector(".c-form__description.valid");
    const formDescriptionInvalid = document.querySelector(".c-form__description.invalid");
    const submitButton = document.querySelector(".c-form .c-btn--primary");
    const errorNotice = document.querySelector(".c-section--maincontent .notice-warning.wpsms-admin-notice.active");

    if (errorNotice) {
        if (formDescription) formDescription.classList.add('hidden');
        if (formDescriptionInvalid) formDescriptionInvalid.classList.remove('hidden');
    }
    if (wpSmsItiTel) {
        const body = document.body;
        const direction = 'ltr';
        wpSmsItiTel.setAttribute('dir', direction);
        wpSmsItiTel.setAttribute('autocomplete', 'off');
        wpSmsItiTel.value = '';


        let iti_tel = window.intlTelInput(wpSmsItiTel, {
            initialCountry: "us",
            autoInsertDialCode: true,
            allowDropdown: true,
            strictMode: true,
            useFullscreenPopup: false,
            // dropdownContainer: body.classList.contains('rtl') ? null : body,
            dropdownContainer: body,
            nationalMode: false,
            autoPlaceholder: "polite",
            utilsScript: wp_sms_intel_tel_util.util_js,
            customPlaceholder: (selectedCountryPlaceholder, selectedCountryData) => {
                return `+${selectedCountryData.dialCode} 555 123 4567`;
            },
        })

        const updatePlaceholder = () => {
            const dialCode = iti_tel.getSelectedCountryData().dialCode || '1';
            const newPlaceholder = `+${dialCode} 555 123 4567`;
            wpSmsItiTel.setAttribute('placeholder', newPlaceholder);
        };
        updatePlaceholder();

        if (!wpSmsItiTel.value.trim()) {
            submitButton.disabled = true;
            if (formDescription) formDescription.classList.remove('hidden');
            if (formDescriptionInvalid) formDescriptionInvalid.classList.add('hidden');
            const existingNotices = document.querySelectorAll('.wpsms-admin-notice.notice-warning');
            existingNotices.forEach(notice => notice.remove());
        }

        wpSmsItiTel.addEventListener('countrychange', function () {
            const selectedCountryData = iti_tel.getSelectedCountryData();
            const dialCode = selectedCountryData.dialCode || '1';
            if (!wpSmsItiTel.value.trim()) {
                wpSmsItiTel.value = `+${dialCode}`;
            }
            if (countryCodeField) {
                countryCodeField.value = selectedCountryData.name || 'United States';
            }
            updatePlaceholder();
            validateAndSet(wpSmsItiTel, iti_tel);
        });
        wpSmsItiTel.addEventListener('open:countrydropdown', function () {
            const selectedCountryData = iti_tel.getSelectedCountryData();
            if (selectedCountryData.iso2) {
                iti_tel.setCountry(selectedCountryData.iso2); // Explicitly set the country in the dropdown
            }
        });

        function debounce(func, wait) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        const debouncedValidateAndSet = debounce(function () {
            validateAndSet(wpSmsItiTel, iti_tel);
        }, 300);

        wpSmsItiTel.addEventListener('input', function () {
            updatePlaceholder();
            debouncedValidateAndSet();
        });
    }

    function validateAndSet(input, intlTelInputInstance) {
        const isValid = intlTelInputInstance.isValidNumber();
        const isEmpty = input.value.trim() === '';

        const existingNotices = document.querySelectorAll('.wpsms-admin-notice.notice-warning');
        existingNotices.forEach(notice => notice.remove());

        if (isEmpty) {
            if (formDescription) formDescription.classList.remove('hidden');
            if (formDescriptionInvalid) formDescriptionInvalid.classList.add('hidden');
            if (submitButton) submitButton.disabled = true;
        } else if (isValid) {
            if (formDescription) formDescription.classList.remove('hidden');
            if (formDescriptionInvalid) formDescriptionInvalid.classList.add('hidden');
            if (submitButton) submitButton.disabled = false;
            input.value = intlTelInputInstance.getNumber().replace(/[-\s]/g, '');
        } else {
            if (formDescription) formDescription.classList.add('hidden');
            if (formDescriptionInvalid) formDescriptionInvalid.classList.remove('hidden');
            submitButton.disabled = true;

            if (sectionHeader) {
                const notice = document.createElement('div');
                notice.className = 'notice notice-warning wpsms-admin-notice active';
                notice.innerHTML = `<p>${wpsms_js._('fix_highlight')}</p>`;
                sectionHeader.insertAdjacentElement('afterend', notice);
            }
        }
    }


    // Initialize Select2 with custom placeholder
    function initSelect2(selector, options = {}) {
        const defaultOptions = {
            dropdownCssClass: 'c-select2-dropdown'
        };

        const finalOptions = $.extend({}, defaultOptions, options);

        $(selector).select2(finalOptions).on('select2:open', function () {
            $('.wpsms-onboarding select, .wpsms-onboarding .select2-container').css('display', 'inline-block');

            // Add placeholder only for searchable dropdowns
            if (!finalOptions.minimumResultsForSearch || finalOptions.minimumResultsForSearch === 0) {
                $('.select2-search__field').attr('placeholder', 'Type to search...');
            }
        });
    }

     initSelect2('.wpsms-onboarding select');

     initSelect2('.wpsms-onboarding .wp-sms-onboarding-step-configuration select', {
        minimumResultsForSearch: Infinity
    });

    if ($('.select2-container').length > 0) {
        $('.wpsms-skeleton__select').hide();
        $('.wpsms-onboarding select, .wpsms-onboarding .select2-container').css('display', 'inline-block');
    }
    // Initialize DataTable
    DataTable.ext.order['dom-data-sort'] = function (settings, col) {
        return this.api().column(col, {order: 'index'}).nodes().map(function (td) {
            return $(td).find('span').data('sort') || 0;
        });
    };

    function parseListAttr(val) {
        return (val || '')
            .toString()
            .toLowerCase()
            .split(',')
            .map(s => s.trim())
            .filter(Boolean);
    }

    DataTable.ext.search.push(function (settings, data, dataIndex) {
        const sel = document.getElementById('filterCountries');
        if (!sel) return true;

        const selected = (sel.value || '').toString().trim();
        if (!selected || selected.toLowerCase() === 'all') return true;

        const needle = selected.toLowerCase();
        const rowNode = settings.aoData[dataIndex].nTr;

        const countries = parseListAttr(rowNode.getAttribute('data-countries'));
        const regions   = parseListAttr(rowNode.getAttribute('data-regions'));

        if (needle === 'global') {
            return countries.includes('global') || regions.includes('global');
        }
        // Match if either countries OR regions contain the selected label
        return countries.includes(needle) || regions.includes(needle);
    });

    let table = new DataTable('.js-table', {
        searching: true,
        info: false,
        order: [],
        responsive: true,
        columnDefs: [
            {
                targets: [1, 2],
                orderDataType: 'dom-data-sort'
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
    if (table) {
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

    let chosen_origin = $('.chosen-origin').val() || $('.chosen-country').val() || '';
    if (chosen_origin && $('#filterCountries option[value="' + chosen_origin + '"]').length > 0) {
        $('#filterCountries').val(chosen_origin).trigger('change.select2');
        table.draw();
    }

    $('#filterCountries').on('select2:select change', function () {
        table.draw();
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
    });
});
