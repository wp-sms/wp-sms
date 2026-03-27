const { PhoneInput } = window.WsmsVendor.LitePhoneInput;
import './cf7-phone.css';

function destroyPhoneInputs(root) {
  root.querySelectorAll('.wsms-phone-container').forEach((container) => {
    if (container._wsmsPhone) {
      container._wsmsPhone.destroy();
      delete container._wsmsPhone;
    }
  });
}

function initPhoneInputs(root = document) {
  root.querySelectorAll('.wsms-phone-container').forEach((container) => {
    if (container._wsmsPhone) return;

    const wrap = container.closest('.wsms-phone-wrap');
    const fieldName = container.dataset.wsmsField;
    if (!wrap || !fieldName) return;

    const config = window.wsmsCf7PhoneConfig || {};

    const inputAttrs = {};
    if (container.dataset.wsmsRequired === '1') inputAttrs['aria-required'] = 'true';
    if (container.dataset.wsmsId) inputAttrs.id = container.dataset.wsmsId;

    const instance = PhoneInput.mount(container, {
      defaultCountry: config.defaultCountry || 'US',
      preferredCountries: config.preferredCountries,
      allowedCountries: config.allowedCountries,
      excludedCountries: config.excludedCountries,
      hiddenInput: { phone: fieldName },
      formatAsYouType: true,
      separateDialCode: config.separateDialCode ?? false,
      nationalMode: config.nationalMode ?? false,
      allowDropdown: config.allowDropdown ?? true,
      placeholder: container.dataset.wsmsPlaceholder || 'auto',
      initialValue: container.dataset.wsmsInitial || '',
      inputAttributes: inputAttrs,
    });

    container._wsmsPhone = instance;

    // Verify widget bridge (only if verify attribute set)
    if (container.dataset.wsmsVerify === '1') {
      const verifyContainer = wrap.querySelector('.wsms-verify-widget-container');
      const flag = wrap.querySelector('.wsms-verified-flag');
      if (!verifyContainer || !flag) return;

      let lastVerifiedNumber = '';

      const input = container.querySelector('.lpi__input');
      if (input) {
        input.addEventListener('blur', () => {
          const e164 = instance.getValue();
          if (!e164 || e164 === lastVerifiedNumber) return;

          flag.value = '';
          lastVerifiedNumber = '';

          if (typeof wsmsVerify !== 'undefined') {
            wsmsVerify.mount(verifyContainer, {
              channel: 'phone',
              identifier: e164,
              onVerified: (sessionToken) => {
                flag.value = sessionToken;
                lastVerifiedNumber = e164;
              },
            });
          }
        });
      }
    }
  });
}

// Initial setup — run immediately if DOM is already parsed (script loaded in footer)
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initPhoneInputs());
} else {
  initPhoneInputs();
}

// Re-init after CF7 AJAX form reset (CF7 replaces form DOM)
document.addEventListener('wpcf7submit', (e) => {
  const form = e.target;
  if (!form) return;
  destroyPhoneInputs(form);
  setTimeout(() => initPhoneInputs(form), 100);
});

// Re-init for forms loaded after DOMContentLoaded (AJAX-loaded pages, page builders)
document.addEventListener('wpcf7-loaded', (e) => {
  const form = e.target;
  if (form) initPhoneInputs(form);
});
