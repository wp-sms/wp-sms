(function () {
    var MOUNT_SELECTOR = '.wsms-verify-mount';
    var LISTENER_KEY = '_wsmsListeners';

    function resolveEl(container, selector, scope) {
        if (selector.startsWith('#')) return document.querySelector(selector);
        var parent = scope ? container.closest(scope) : container.parentElement;
        return parent && parent.querySelector(selector);
    }

    function initContainer(el) {
        if (el.dataset.wsmsReady) return;
        if (typeof wsmsVerify === 'undefined') return;

        var inputSelector = el.dataset.wsmsInput;
        var tokenSelector = el.dataset.wsmsToken;
        var scope = el.dataset.wsmsScope || null;
        var channel = el.dataset.wsmsChannel;
        var skipValue = el.dataset.wsmsSkip || null;

        var input = resolveEl(el, inputSelector, scope);
        var flag = resolveEl(el, tokenSelector, scope);
        if (!input || !flag) return;
        el.dataset.wsmsReady = '1';

        // Remove stale listeners if this input was previously bound by a different container.
        if (input[LISTENER_KEY]) {
            input.removeEventListener('blur', input[LISTENER_KEY].blur);
            input.removeEventListener('change', input[LISTENER_KEY].change);
            if (input[LISTENER_KEY].form) {
                input[LISTENER_KEY].form.removeEventListener('reset', input[LISTENER_KEY].reset);
            }
        }

        var lastValue = '';

        function onBlur() {
            var value = input.value.trim();
            if (!value || value === lastValue) return;
            lastValue = value;
            flag.value = '';

            if (skipValue && value.toLowerCase() === skipValue.toLowerCase()) {
                wsmsVerify.destroy(el);
                el.style.display = 'none';
                return;
            }
            el.style.display = '';

            wsmsVerify.mount(el, {
                channel: channel,
                identifier: value,
                onVerified: function (token) {
                    flag.value = token;
                },
            });
        }

        function onChange() {
            if (flag.value && input.value.trim() !== lastValue) {
                flag.value = '';
                lastValue = '';
                wsmsVerify.destroy(el);
            }
        }

        // Reset lastValue when form is reset (CF7/WPForms clear fields but the
        // same value on re-entry shouldn't be skipped if the token was cleared).
        function onReset() {
            lastValue = '';
        }

        var form = input.closest('form');

        input.addEventListener('blur', onBlur);
        input.addEventListener('change', onChange);
        if (form) form.addEventListener('reset', onReset);
        input[LISTENER_KEY] = { blur: onBlur, change: onChange, reset: onReset, form: form };
    }

    function scanAndInit(root) {
        (root || document).querySelectorAll(MOUNT_SELECTOR).forEach(initContainer);
    }

    function startObserver() {
        scanAndInit();
        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var node = added[j];
                    if (node.nodeType !== 1) continue;
                    if (node.matches && node.matches(MOUNT_SELECTOR)) initContainer(node);
                    if (node.querySelectorAll) node.querySelectorAll(MOUNT_SELECTOR).forEach(initContainer);
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startObserver);
    } else {
        startObserver();
    }
})();
