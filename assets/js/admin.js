/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ 859:
/***/ (() => {

function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
jQuery(document).ready(function () {
  var license_input = document.querySelector('.wpsms-addon__step__active-license input');
  var active_license_btn = document.querySelector('.js-addon-active-license');
  var license_buttons = document.querySelectorAll('.js-wpsms-addon-license-button');
  var toggleAlertBox = function toggleAlertBox(btn) {
    var existingAlertDiv = btn.parentElement.parentElement.querySelector('.wpsms-alert');
    if (existingAlertDiv) {
      existingAlertDiv.remove();
    }
  };
  var errorHandel = function errorHandel(params, button, data) {
    if (params.action === "wp_sms_download_plugin") {
      var current_plugin_checkbox = document.querySelector("[data-slug=\"".concat(params.plugin_slug, "\"]"));
      if (current_plugin_checkbox) {
        var downloadingStatus = current_plugin_checkbox.parentElement.parentElement.querySelector('.wpsms-postbox-addon__status');
        if (downloadingStatus) {
          downloadingStatus.remove();
        }
        var statusSpan = document.createElement('span');
        statusSpan.classList.add('wps-postbox-addon__status', 'wps-postbox-addon__status--danger');
        statusSpan.textContent = wps_js._('failed');
        current_plugin_checkbox.parentElement.parentElement.insertBefore(statusSpan, current_plugin_checkbox.parentElement.parentElement.firstChild);
        if (params.tab === 'get-started') {
          var retryBtn = document.createElement('a');
          retryBtn.classList.add('wps-postbox-addon__button', 'button-retry-addon-download', 'js-addon-retry-btn');
          retryBtn.textContent = wps_js._('retry');
          retryBtn.setAttribute('data-slug', params.plugin_slug);
          current_plugin_checkbox.parentElement.parentElement.insertBefore(retryBtn, statusSpan.nextSibling);
        }
      }
    }
    if (params.action === "wp_sms_activate_plugin") {
      var current_plugin = document.querySelector("[data-slug=\"".concat(params.plugin_slug, "\"]"));
      if (current_plugin) {
        var loadingStatus = current_plugin.parentElement.parentElement.querySelector('.wpsms-postbox-addon__status');
        if (loadingStatus) {
          loadingStatus.remove();
        }
        var _statusSpan = document.createElement('span');
        _statusSpan.classList.add('wps-postbox-addon__status', 'wps-postbox-addon__status--danger');
        _statusSpan.textContent = wps_js._('failed');
        current_plugin.parentElement.parentElement.insertBefore(_statusSpan, current_plugin.parentElement.parentElement.firstChild);
        current_plugin.style.display = 'flex';
      }
    }
  };
  // Define the AJAX request function
  var sendAjaxRequest = function sendAjaxRequest(params, button, callback) {
    if (button) wps_js.loading_button(button);
    if (params.action === "wp_sms_download_plugin") {
      var current_plugin = document.querySelector("[data-slug=\"".concat(params.plugin_slug, "\"]"));
      if (current_plugin) {
        var statusLable = current_plugin.parentElement.parentElement.querySelector('.wpsms-postbox-addon__status');
        if (statusLable) {
          statusLable.remove();
        }
        var statusSpan = document.createElement('span');
        statusSpan.classList.add('wps-postbox-addon__status', 'wps-postbox-addon__status--purple');
        statusSpan.textContent = wps_js._('downloading');
        if (current_plugin && current_plugin.parentElement.parentElement) {
          current_plugin.parentElement.parentElement.insertBefore(statusSpan, current_plugin.parentElement.parentElement.firstChild);
          current_plugin.style.display = 'none';
        }
      }
    }
    if (params.action === "wp_sms_activate_plugin") {
      var _current_plugin = document.querySelector("[data-slug=\"".concat(params.plugin_slug, "\"]"));
      if (_current_plugin) {
        var _statusLable = _current_plugin.parentElement.parentElement.querySelector('.wpsms-postbox-addon__status');
        if (_statusLable) {
          _statusLable.remove();
        }
        var _statusSpan2 = document.createElement('span');
        _statusSpan2.classList.add('wps-postbox-addon__status', 'wps-postbox-addon__status--purple');
        _statusSpan2.textContent = wps_js._('activating');
        if (_current_plugin && _current_plugin.parentElement.parentElement) {
          _current_plugin.parentElement.parentElement.insertBefore(_statusSpan2, _current_plugin.parentElement.parentElement.firstChild);
          _current_plugin.style.display = 'none';
        }
      }
    }
    jQuery.ajax({
      url: wps_js.global.admin_url + 'admin-ajax.php',
      type: 'GET',
      dataType: 'json',
      data: params,
      timeout: 30000,
      success: function success(data) {
        if (button) button.classList.remove('wps-loading-button');
        if (data.success) {
          if (button) button.classList.add('disabled');
          if (params.action === "wp_sms_download_plugin") {
            var current_plugin_checkbox = document.querySelector("[data-slug=\"".concat(params.plugin_slug, "\"]"));
            if (current_plugin_checkbox) {
              var loadingStatus = current_plugin_checkbox.parentElement.parentElement.querySelector('.wpsms-postbox-addon__status--purple');
              var updatedLable = current_plugin_checkbox.parentElement.parentElement.querySelector('.wpsms-postbox-addon__label--updated');
              if (loadingStatus) {
                loadingStatus.remove();
              }
              if (updatedLable) {
                updatedLable.remove();
              }
              var _statusSpan3 = document.createElement('span');
              _statusSpan3.classList.add('wps-postbox-addon__status', 'wps-postbox-addon__status--installed');
              _statusSpan3.textContent = wps_js._('already_installed');
              if (current_plugin_checkbox && current_plugin_checkbox.parentElement.parentElement) {
                current_plugin_checkbox.parentElement.parentElement.insertBefore(_statusSpan3, current_plugin_checkbox.parentElement.parentElement.firstChild);
              }
              if (params.tab === 'downloads') {
                _statusSpan3.parentElement.parentElement.classList.add('wps-addon__download__item--disabled');
              }
              if (params.tab === 'get-started') {
                var activeBtn = document.createElement('a');
                activeBtn.classList.add('wps-postbox-addon__button', 'button-activate-addon', 'js-addon-active-plugin-btn');
                activeBtn.textContent = wps_js._('active');
                activeBtn.setAttribute('data-slug', params.plugin_slug);
                current_plugin_checkbox.parentElement.insertBefore(activeBtn, _statusSpan3.nextSibling);
                var showMoreBtn = document.querySelector('.js-addon-show-more');
                active_addon_plugin_btn = document.querySelectorAll('.js-addon-active-plugin-btn');
                toggleActiveAll();
                if (showMoreBtn) {
                  showMoreBtn.classList.remove('wpsms-hide');
                }
              }
              current_plugin_checkbox.remove();
            }
          }
          if (params.action === "wp_sms_activate_plugin") {
            var _current_plugin_checkbox = document.querySelector("[data-slug=\"".concat(params.plugin_slug, "\"]"));
            if (_current_plugin_checkbox) {
              var _loadingStatus = _current_plugin_checkbox.parentElement.parentElement.querySelector('.wpsms-postbox-addon__status--purple');
              if (_loadingStatus) {
                _loadingStatus.remove();
              }
              var _statusSpan4 = document.createElement('span');
              _statusSpan4.classList.add('wps-postbox-addon__status', 'wps-postbox-addon__status--success');
              _statusSpan4.textContent = wps_js._('activated');
              _current_plugin_checkbox.parentElement.parentElement.insertBefore(_statusSpan4, _current_plugin_checkbox.parentElement.parentElement.firstChild);
              _current_plugin_checkbox.remove();
            }
          }
        } else {
          errorHandel(params, button, data);
        }
        if (callback) callback();
      },
      error: function error(xhr, status, _error) {
        if (button) button.classList.remove('wps-loading-button');
        if (callback) callback();
        errorHandel(params, button, _error);
      }
    });
  };
  if (license_input && active_license_btn) {
    var toggleButtonState = function toggleButtonState() {
      license_input.classList.remove('wpsms-danger', 'wpsms-warning');
      toggleAlertBox(active_license_btn);
      if (license_input.value.trim() === '') {
        active_license_btn.classList.add('disabled');
        active_license_btn.disabled = true;
      } else {
        active_license_btn.classList.remove('disabled');
        active_license_btn.disabled = false;
      }
    }; // Initial check when the page loads
    toggleButtonState();

    // Listen for input event to enable button when typing
    license_input.addEventListener('input', function () {
      toggleButtonState();
    });
  }
  if (active_license_btn) {
    active_license_btn.addEventListener('click', function (event) {
      event.stopPropagation();
      // Get and trim the license key input value
      var license_key = license_input.value.trim();
      if (license_key) {
        var active_params = _objectSpread({
          'license_key': license_key
        }, params);
        sendAjaxRequest(active_params, active_license_btn);
      }
    });
  }
  if (active_license_btn) {
    active_license_btn.addEventListener('click', function (event) {
      event.stopPropagation();
      // Get and trim the license key input value
      var license_key = license_input.value.trim();
      if (license_key) {
        var active_params = _objectSpread({
          'license_key': license_key
        }, params);
        sendAjaxRequest(active_params, active_license_btn);
      }
    });
  }
  if (license_buttons.length > 0) {
    license_buttons.forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.stopPropagation();
        var isActive = this.classList.contains('active');
        document.querySelectorAll('.js-wpsms-addon-license-button').forEach(function (otherButton) {
          otherButton.classList.remove('active');
          otherButton.closest('.wpsms-postbox-addon__item').classList.remove('active');
        });
        if (!isActive) {
          this.classList.add('active');
          var closestItem = this.closest('.wpsms-postbox-addon__item');
          if (closestItem) {
            closestItem.classList.add('active');
            var active_input = closestItem.querySelector('.wpsms-addon__item__update_license input');
            var active_button = closestItem.querySelector('.wpsms-addon__item__update_license button');
            if (active_input && active_button) {
              var _toggleButtonState = function _toggleButtonState() {
                active_input.classList.remove('wps-danger', 'wps-warning');
                toggleAlertBox(active_button);
                if (active_input.value.trim() === '') {
                  active_button.classList.add('disabled');
                  active_button.disabled = true;
                } else {
                  active_button.classList.remove('disabled');
                  active_button.disabled = false;
                }
              }; // Initial check when the page loads
              _toggleButtonState();

              // Listen for input event to enable button when typing
              active_input.addEventListener('input', function () {
                _toggleButtonState();
              });
            }
            if (active_button) {
              active_button.addEventListener('click', function (event) {
                event.stopPropagation();
                // Get and trim the license key input value
                var license_key = active_input.value.trim();
                var addon_slug = active_input.dataset.addonSlug;
                if (license_key && addon_slug) {
                  var active_params = _objectSpread({
                    'license_key': license_key,
                    'addon_slug': addon_slug
                  }, params);
                  sendAjaxRequest(active_params, active_button);
                }
              });
            }
          }
        }
      });
    });
  }
});

/***/ }),

/***/ 848:
/***/ (() => {

jQuery(document).ready(function ($) {
  WPSmsStatsWidget.init();
});
var WPSmsStatsWidget = {
  init: function init() {
    this.setElements();
    this.checkIfTwoWayIsActive();
    this.showTwoWayModalIfNotActive();
    this.calculateCounts();
    this.initChart();
    this.addEventListener();
  },
  setElements: function setElements() {
    this.elements = {
      context: jQuery('.wp-sms-widgets.stats-widget .chart canvas'),
      timeFrameSelect: jQuery('.wp-sms-widgets.stats-widget select.time-frame'),
      smsDirection: jQuery('.wp-sms-widgets.stats-widget select.sms-direction'),
      totalsDiv: jQuery('.wp-sms-widgets.stats-widget table.totals tr'),
      twoWayPromotion: jQuery('.wp-sms-widgets.stats-widget .two-way-promotion')
    };
  },
  checkIfTwoWayIsActive: function checkIfTwoWayIsActive() {
    if (typeof WP_Sms_Admin_Dashboard_Object['received-messages-stats'] == 'undefined') {
      this.twoWayIsNotActive = true;
      WP_Sms_Admin_Dashboard_Object['received-messages-stats'] = WP_Sms_Admin_Dashboard_Object['send-messages-stats'];
    }
  },
  showTwoWayModalIfNotActive: function showTwoWayModalIfNotActive() {
    var direction = this.elements.smsDirection.val();
    if (direction == 'received-messages-stats' && this.twoWayIsNotActive == true) {
      this.elements.twoWayPromotion.show();
      this.elements.totalsDiv.addClass('blur');
      this.elements.context.addClass('blur');
    } else {
      this.elements.twoWayPromotion.hide();
      this.elements.totalsDiv.removeClass('blur');
      this.elements.context.removeClass('blur');
    }
  },
  getChartData: function getChartData() {
    var timeFrame = this.elements.timeFrameSelect.val();
    var direction = this.elements.smsDirection.val();
    var datasets = timeFrame && direction ? WP_Sms_Admin_Dashboard_Object[direction][timeFrame] : null;
    var localization = WP_Sms_Admin_Dashboard_Object.localization;
    switch (direction) {
      case 'send-messages-stats':
        return {
          datasets: [{
            label: localization.successful,
            backgroundColor: 'rgba(0, 190, 86, 0.4)',
            borderColor: 'rgba(0, 148, 67, 1)',
            borderWidth: 1,
            fill: true,
            data: datasets['successful'],
            tension: 0.4
          }, {
            label: localization.failed,
            backgroundColor: 'rgba(255, 99, 132, 0.5)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1,
            fill: true,
            data: datasets['failure'],
            tension: 0.4
          }]
        };
      case 'received-messages-stats':
        return {
          datasets: [{
            label: localization.successful,
            backgroundColor: 'rgba(0, 190, 86, 0.4)',
            borderColor: 'rgba(0, 148, 67, 1)',
            borderWidth: 1,
            fill: true,
            data: datasets['successful'],
            tension: 0.4
          }, {
            label: localization.failed,
            backgroundColor: 'rgba(255, 99, 132, 0.4)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1,
            fill: true,
            data: datasets['failure'],
            tension: 0.4
          }, {
            label: localization.plain,
            backgroundColor: 'rgba(156, 156, 156, 0.3)',
            borderColor: 'rgb(73, 80, 87)',
            borderWidth: 1,
            fill: true,
            data: datasets['plain'],
            tension: 0.4
          }]
        };
    }
  },
  calculateCounts: function calculateCounts() {
    var _totals$plain;
    var timeFrame = this.elements.timeFrameSelect.val();
    var direction = this.elements.smsDirection.val();
    var datasets = timeFrame && direction ? WP_Sms_Admin_Dashboard_Object[direction][timeFrame] : null;
    var localization = WP_Sms_Admin_Dashboard_Object.localization;
    var totals = {};
    var _loop = function _loop() {
      if (Object.hasOwnProperty.call(datasets, key)) {
        var element = datasets[key];
        totals[key] = Object.keys(element).reduce(function (sum, key) {
          return sum + parseFloat(element[key] || 0);
        }, 0);
      }
    };
    for (var key in datasets) {
      _loop();
    }
    switch (direction) {
      case 'send-messages-stats':
        this.elements.totalsDiv.html("\n                        <td class='successful'>\n                            <img src=\"data:image/svg+xml,%3C%3Fxml version='1.0' encoding='utf-8'%3F%3E%3Csvg version='1.1' id='Layer_1' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' x='0px' y='0px' width='96px' height='96px' viewBox='0 0 96 96' enable-background='new 0 0 96 96' xml:space='preserve'%3E%3Cg%3E%3Cpath fill-rule='evenodd' clip-rule='evenodd' fill='%236BBE66' d='M48,0c26.51,0,48,21.49,48,48S74.51,96,48,96S0,74.51,0,48 S21.49,0,48,0L48,0z M26.764,49.277c0.644-3.734,4.906-5.813,8.269-3.79c0.305,0.182,0.596,0.398,0.867,0.646l0.026,0.025 c1.509,1.446,3.2,2.951,4.876,4.443l1.438,1.291l17.063-17.898c1.019-1.067,1.764-1.757,3.293-2.101 c5.235-1.155,8.916,5.244,5.206,9.155L46.536,63.366c-2.003,2.137-5.583,2.332-7.736,0.291c-1.234-1.146-2.576-2.312-3.933-3.489 c-2.35-2.042-4.747-4.125-6.701-6.187C26.993,52.809,26.487,50.89,26.764,49.277L26.764,49.277z'/%3E%3C/g%3E%3C/svg%3E\">\n                            ".concat(totals.successful, " ").concat(localization.successful, "\n                        </td>\n                        <td class='failure'>\n                            <img src=\"data:image/svg+xml,%3Csvg id='Layer_1' data-name='Layer 1' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 122.88 122.88'%3E%3Cdefs%3E%3Cstyle%3E.cls-1%7Bfill:%23eb0100;%7D.cls-1,.cls-2%7Bfill-rule:evenodd;%7D.cls-2%7Bfill:%23fff;%7D%3C/style%3E%3C/defs%3E%3Ctitle%3Ecancel%3C/title%3E%3Cpath class='cls-1' d='M61.44,0A61.44,61.44,0,1,1,0,61.44,61.44,61.44,0,0,1,61.44,0Z'/%3E%3Cpath class='cls-2' d='M35.38,49.72c-2.16-2.13-3.9-3.47-1.19-6.1l8.74-8.53c2.77-2.8,4.39-2.66,7,0L61.68,46.86,73.39,35.15c2.14-2.17,3.47-3.91,6.1-1.2L88,42.69c2.8,2.77,2.66,4.4,0,7L76.27,61.44,88,73.21c2.65,2.58,2.79,4.21,0,7l-8.54,8.74c-2.63,2.71-4,1-6.1-1.19L61.68,76,49.9,87.81c-2.58,2.64-4.2,2.78-7,0l-8.74-8.53c-2.71-2.63-1-4,1.19-6.1L47.1,61.44,35.38,49.72Z'/%3E%3C/svg%3E\">\n                            ").concat(totals.failure, " ").concat(localization.failed, "\n                        </td>\n                    "));
        break;
      case 'received-messages-stats':
        this.elements.totalsDiv.html("\n                        <td class='successful'>\n                            <img src=\"data:image/svg+xml,%3C%3Fxml version='1.0' encoding='utf-8'%3F%3E%3Csvg version='1.1' id='Layer_1' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' x='0px' y='0px' width='96px' height='96px' viewBox='0 0 96 96' enable-background='new 0 0 96 96' xml:space='preserve'%3E%3Cg%3E%3Cpath fill-rule='evenodd' clip-rule='evenodd' fill='%236BBE66' d='M48,0c26.51,0,48,21.49,48,48S74.51,96,48,96S0,74.51,0,48 S21.49,0,48,0L48,0z M26.764,49.277c0.644-3.734,4.906-5.813,8.269-3.79c0.305,0.182,0.596,0.398,0.867,0.646l0.026,0.025 c1.509,1.446,3.2,2.951,4.876,4.443l1.438,1.291l17.063-17.898c1.019-1.067,1.764-1.757,3.293-2.101 c5.235-1.155,8.916,5.244,5.206,9.155L46.536,63.366c-2.003,2.137-5.583,2.332-7.736,0.291c-1.234-1.146-2.576-2.312-3.933-3.489 c-2.35-2.042-4.747-4.125-6.701-6.187C26.993,52.809,26.487,50.89,26.764,49.277L26.764,49.277z'/%3E%3C/g%3E%3C/svg%3E\">\n                            ".concat(totals.successful, " ").concat(localization.successful, "\n                        </td>\n                        <td class='failure'>\n                            <img src=\"data:image/svg+xml,%3Csvg id='Layer_1' data-name='Layer 1' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 122.88 122.88'%3E%3Cdefs%3E%3Cstyle%3E.cls-1%7Bfill:%23eb0100;%7D.cls-1,.cls-2%7Bfill-rule:evenodd;%7D.cls-2%7Bfill:%23fff;%7D%3C/style%3E%3C/defs%3E%3Ctitle%3Ecancel%3C/title%3E%3Cpath class='cls-1' d='M61.44,0A61.44,61.44,0,1,1,0,61.44,61.44,61.44,0,0,1,61.44,0Z'/%3E%3Cpath class='cls-2' d='M35.38,49.72c-2.16-2.13-3.9-3.47-1.19-6.1l8.74-8.53c2.77-2.8,4.39-2.66,7,0L61.68,46.86,73.39,35.15c2.14-2.17,3.47-3.91,6.1-1.2L88,42.69c2.8,2.77,2.66,4.4,0,7L76.27,61.44,88,73.21c2.65,2.58,2.79,4.21,0,7l-8.54,8.74c-2.63,2.71-4,1-6.1-1.19L61.68,76,49.9,87.81c-2.58,2.64-4.2,2.78-7,0l-8.74-8.53c-2.71-2.63-1-4,1.19-6.1L47.1,61.44,35.38,49.72Z'/%3E%3C/svg%3E\">\n                            ").concat(totals.failure, " ").concat(localization.failed, "\n                        </td>\n                        <td class='plain'>\n                            <img src=\"data:image/svg+xml,%3Csvg id='Layer_1' data-name='Layer 1' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 121.86 122.88'%3E%3Ctitle%3Ecomment%3C/title%3E%3Cpath d='M30.28,110.09,49.37,91.78A3.84,3.84,0,0,1,52,90.72h60a2.15,2.15,0,0,0,2.16-2.16V9.82a2.16,2.16,0,0,0-.64-1.52A2.19,2.19,0,0,0,112,7.66H9.82A2.24,2.24,0,0,0,7.65,9.82V88.55a2.19,2.19,0,0,0,2.17,2.16H26.46a3.83,3.83,0,0,1,3.82,3.83v15.55ZM28.45,63.56a3.83,3.83,0,1,1,0-7.66h53a3.83,3.83,0,0,1,0,7.66Zm0-24.86a3.83,3.83,0,1,1,0-7.65h65a3.83,3.83,0,0,1,0,7.65ZM53.54,98.36,29.27,121.64a3.82,3.82,0,0,1-6.64-2.59V98.36H9.82A9.87,9.87,0,0,1,0,88.55V9.82A9.9,9.9,0,0,1,9.82,0H112a9.87,9.87,0,0,1,9.82,9.82V88.55A9.85,9.85,0,0,1,112,98.36Z'/%3E%3C/svg%3E\">\n                            ").concat((_totals$plain = totals.plain) !== null && _totals$plain !== void 0 ? _totals$plain : 0, " ").concat(localization.plain, "\n                        </td>\n                    "));
        break;
    }
  },
  addEventListener: function addEventListener() {
    var action = function () {
      this.showTwoWayModalIfNotActive();
      if (this.elements.timeFrameSelect.val() && this.elements.smsDirection.val()) {
        var chart = this.chart;
        chart.data = this.getChartData();
        this.calculateCounts();
        chart.update();
      }
    }.bind(this);
    this.elements.timeFrameSelect.on('change', action);
    this.elements.smsDirection.on('change', action);
  },
  initChart: function initChart() {
    if (this.elements.timeFrameSelect.val() && this.elements.smsDirection.val()) {
      var ctx = this.elements.context.get(0);
      this.chart = new Chart(ctx, {
        type: 'line',
        data: this.getChartData(),
        options: {
          tooltips: {
            mode: 'index'
          },
          interaction: {
            intersect: false
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                stepSize: 1
              }
            }
          }
        }
      });
    }
  }
};

/***/ }),

/***/ 647:
/***/ (() => {

jQuery(document).ready(function () {
  wpSmsExport.init();
});
var wpSmsExport = {
  /**
   * initialize functions
   */
  init: function init() {
    this.setFields();
    this.addEventListener();
  },
  /**
   * initialize JQ selectors
   */
  setFields: function setFields() {
    this.exportForm = jQuery('.js-wpSmsExportForm');
    this.exportGroup = jQuery('#wpsms_groups');
  },
  addEventListener: function addEventListener() {
    this.exportForm.on('submit', function (event) {
      // avoid to execute the actual submit of the form
      event.preventDefault();

      // get type of data from a hidden input in the form
      var type = jQuery('.wp-sms-export-type').val();

      // generating request body data
      var requestBody = {
        'type': type
      };
      if (type == 'subscriber') {
        Object.assign(requestBody, {
          'groupIds': this.exportGroup.val()
        });
      }

      // send AJAX request
      jQuery.ajax({
        url: WP_Sms_Admin_Object.ajaxUrls["export"],
        type: 'GET',
        xhrFields: {
          responseType: 'blob'
        },
        contentType: 'application/json',
        data: requestBody,
        // enabling loader
        beforeSend: function beforeSend() {
          jQuery('.js-wpSmsExportButton').attr('disabled', 'disabled');
          jQuery('.wpsms-sendsms__overlay').css('display', 'flex');
        },
        // successful request
        success: function success(blob, status, xhr) {
          jQuery('.js-wpSmsExportButton').prop('disabled', false);
          jQuery('.wpsms-sendsms__overlay').css('display', 'none');
          jQuery('.wpsms-export-popup .wp-sms-popup-messages').removeClass('notice notice-error');
          jQuery('.wpsms-export-popup .wp-sms-popup-messages').addClass('notice notice-success');
          jQuery('.wpsms-export-popup .wp-sms-popup-messages').html('<p>The data exported successfully.</p>');
          var fileName = xhr.getResponseHeader('Content-Disposition');
          fileName = fileName.slice(fileName.indexOf('filename') + 9);
          var downloadUrl = window.URL.createObjectURL(blob);
          var URL = window.URL;
          var a = document.createElement('a');
          if (typeof a.download === 'undefined') {
            window.location.href = downloadUrl;
          } else {
            a.href = downloadUrl;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
          }

          //clean up
          setTimeout(function () {
            URL.revokeObjectURL(downloadUrl);
          }, 100);
        },
        // failed request
        error: function error(data, response, xhr) {
          jQuery('.js-wpSmsExportButton').prop('disabled', false);
          jQuery('.wpsms-sendsms__overlay').css('display', 'none');
          jQuery('.wpsms-export-popup .wp-sms-popup-messages').removeClass('notice notice-success');
          jQuery('.wpsms-export-popup .wp-sms-popup-messages').addClass('notice notice-error');
          jQuery('.wpsms-export-popup .wp-sms-popup-messages').html('<p>Failed to export the data.</p>');
        }
      });
    }.bind(this));
  }
};

/***/ }),

/***/ 639:
/***/ (() => {

// Send sms from the WooCommerce order page
jQuery(document).ready(function () {
  wooCommerceOrderPage.init();
});
var wooCommerceOrderPage = {
  /**
   * initialize functionality
   */
  init: function init() {
    this.setFields();
    this.addSendSMSEventListeners();
    this.addNoteEventListeners();
    this.setupNotesMetabox();
  },
  setFields: function setFields() {
    this.SmsMetabox = jQuery('#wpsms-woocommerceSendSMS');
    this.NotesMetabox = jQuery('#woocommerce-order-notes');
  },
  sendSMS: function sendSMS() {
    var receiver = this.SmsMetabox.find('select[name="phone_number"]').val();
    var message = this.SmsMetabox.find('textarea[name="message_content"]').val();
    var orderId = WP_Sms_Admin_Object.order_id;
    var requestBody = {
      message: message,
      recipients: 'numbers',
      numbers: [receiver],
      notification_handler: 'WooCommerceOrderNotification',
      handler_id: orderId,
      sender: WP_Sms_Admin_Object.senderID
    };
    jQuery.ajax(WP_Sms_Admin_Object.restUrls.sendSms, {
      headers: {
        'X-WP-Nonce': WP_Sms_Admin_Object.nonce
      },
      dataType: 'json',
      type: 'post',
      contentType: 'application/json',
      data: JSON.stringify(requestBody),
      beforeSend: function () {
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__overlay').css('display', 'flex');
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__variables__shortCodes').slideUp();
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__tryAgain').hide();
      }.bind(this),
      success: function (data, status, xhr) {
        this.SmsMetabox.find('.wpsms-orderSmsMetabox').fadeOut();
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report p').html(data.message);
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report').removeClass('error');
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report').addClass('success');
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__receiver p').html(receiver);
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__message p').html(message);
        this.SmsMetabox.find(' .wpsms-orderSmsMetabox__result').fadeIn();
      }.bind(this),
      error: function (data, status, xhr) {
        var errorMessage = data.responseJSON.message ? data.responseJSON.message : data.responseJSON.error.message;
        this.SmsMetabox.find('.wpsms-orderSmsMetabox').fadeOut();
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report').removeClass('success');
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report').addClass('error');
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report p').html(errorMessage);
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__tryAgain').show();
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result').fadeIn();
      }.bind(this)
    });
  },
  addSendSMSEventListeners: function addSendSMSEventListeners() {
    var _this = this;
    // Try again
    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__tryAgain').on('click', function (event) {
      event.preventDefault();
      _this.SmsMetabox.find('.wpsms-orderSmsMetabox__result').fadeOut();
      _this.SmsMetabox.find('.wpsms-orderSmsMetabox__overlay').css('display', 'none');
      _this.SmsMetabox.find('.wpsms-orderSmsMetabox').fadeIn();
    });

    // Set event listener for the send sms button
    this.SmsMetabox.find('button[name="send_sms"]').on('click', function (event) {
      event.preventDefault();
      _this.sendSMS();
    });

    // Set event listener for shortcode blocks
    this.SmsMetabox.find('.wpsms-orderSmsMetabox__variables__shortCodes code').on('click', function () {
      var codeValue = jQuery(this).text();
      var textarea = document.getElementById('message_content');

      // Get the current cursor position in the textarea
      var cursorPos = textarea.selectionStart;

      // Get the text before and after the cursor position
      var textBeforeCursor = textarea.value.substring(0, cursorPos);
      var textAfterCursor = textarea.value.substring(cursorPos);

      // Insert the clicked code value at the cursor position and update the textarea value
      codeValue = ' ' + codeValue;
      textarea.value = textBeforeCursor + codeValue + textAfterCursor;

      // Set the new cursor position after the inserted code value
      textarea.setSelectionRange(cursorPos + codeValue.length, cursorPos + codeValue.length);
    });

    // Set event listener for shortcodes collapsable
    this.SmsMetabox.find('.wpsms-orderSmsMetabox__variables__header').on('click', function () {
      jQuery(this).next('.wpsms-orderSmsMetabox__variables__shortCodes').slideToggle();
      jQuery(this).find('.wpsms-orderSmsMetabox__variables__icon').toggleClass('expanded');
    });
  },
  addNoteEventListeners: function addNoteEventListeners() {
    var _this2 = this;
    // Set up an event listener for adding notes
    this.NotesMetabox.find('button.add_note').on('click', function (event) {
      _this2.sendNoteSMS();
    });

    // Show and hide sms to customer elements
    this.NotesMetabox.find('select[name=order_note_type]').on('change', function () {
      var noteType = _this2.NotesMetabox.find('select[name=order_note_type]').val();
      _this2.NotesMetabox.find('.wpsms-addNoteMetabox__elements').toggle(noteType === 'customer');
    });
  },
  setupNotesMetabox: function setupNotesMetabox() {
    // Set up needed fields in the order notes metabox
    jQuery('#woocommerce-order-notes div.add_note').append('<div class="wpsms-addNoteMetabox__elements">' + '<label for="wpsms_note_send">' + '<input type="checkbox" id="wpsms_note_send" name="wpsms_note_send">' + WP_Sms_Admin_Object.lang.checkbox_label + '</label>' + '<div class="wpsms-addNoteMetabox__result__report">' + '<span class="wpsms-addNoteMetabox__result__icon"></span>' + '<p></p>' + '</div>' + '</div>');
  },
  sendNoteSMS: function sendNoteSMS() {
    var message = this.NotesMetabox.find('textarea[name=order_note]').val();
    var sendSMS = this.NotesMetabox.find('input[name=wpsms_note_send]').prop('checked');
    var noteType = this.NotesMetabox.find('select[name=order_note_type]').val();
    var receiver = WP_Sms_Admin_Object.receiver;
    var orderId = WP_Sms_Admin_Object.order_id;
    if (!sendSMS || !message || noteType !== 'customer') {
      return;
    }
    var requestBody = {
      message: message,
      recipients: 'numbers',
      numbers: [receiver],
      notification_handler: 'WooCommerceOrderNotification',
      handler_id: orderId,
      sender: WP_Sms_Admin_Object.senderID
    };
    jQuery.ajax(WP_Sms_Admin_Object.restUrls.sendSms, {
      headers: {
        'X-WP-Nonce': WP_Sms_Admin_Object.nonce
      },
      dataType: 'json',
      type: 'post',
      contentType: 'application/json',
      data: JSON.stringify(requestBody),
      beforeSend: function () {
        this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report').removeClass('error success');
        this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report').fadeOut();
      }.bind(this),
      success: function (data, status, xhr) {
        this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report p').html(data.message);
        this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report').addClass('success');
        this.NotesMetabox.find(' .wpsms-addNoteMetabox__result__report').fadeIn();
      }.bind(this),
      error: function (data, status, xhr) {
        var errorMessage = data.responseJSON.message ? data.responseJSON.message : data.responseJSON.error.message;
        this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report').addClass('error');
        this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report p').html(errorMessage);
        this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report').fadeIn();
      }.bind(this)
    });
  }
};

/***/ }),

/***/ 550:
/***/ (() => {

// Privacy Page Ajax
jQuery(document).ready(function () {
  wpSmsPrivacyPage.init();
});
var wpSmsPrivacyPage = {
  elements: {},
  init: function init() {
    this.elements.form = jQuery('.wpsms-privacyPage__Form');
    this.elements.form.find('input[type=submit]').on('click', function (event) {
      event.preventDefault();
      var type = event.target.name;
      var mobileNumber = jQuery(event.target).closest('div').find('input[type="tel"]').val();
      var formData = new FormData();
      formData.append('mobileNumber', mobileNumber);
      formData.append('type', type);
      jQuery.ajax({
        url: WP_Sms_Admin_Object.ajaxUrls.privacyData,
        method: 'POST',
        contentType: false,
        cache: false,
        processData: false,
        data: formData,
        beforeSend: function beforeSend() {
          jQuery('.wpsms-privacyPage__Result__Container').hide();
          jQuery('.wpsms-privacyPage__Result__Container').empty();
        },
        success: function success(data, response, xhr) {
          // If the file is generated
          if (data.data.file_url) {
            window.open(data.data.file_url);
            jQuery('.wpsms-privacyPage__Result__Container').html(data.data.message);
          }
          jQuery('.wpsms-privacyPage__Result__Container').html(data.data.message);
          jQuery('.wpsms-privacyPage__Result__Container').show();
        },
        error: function error(data, response, xhr) {
          jQuery('.wpsms-privacyPage__Result__Container').html(data.responseJSON.data.message);
          jQuery('.wpsms-privacyPage__Result__Container').show();
        }
      });
    });
  }
};

/***/ }),

/***/ 28:
/***/ (() => {

jQuery(document).ready(function () {
  wpsmsRepeatingMessages.init();
  jQuery("#wp_get_message").counter({
    count: 'up',
    goal: 'sky',
    msg: WP_Sms_Admin_Object.messageMsg
  });
  if (WP_Sms_Admin_Object.proIsActive) {
    // Ensure the flatpickr function is available
    if (typeof jQuery("#datepicker").flatpickr === 'function') {
      jQuery("#datepicker").flatpickr({
        enableTime: true,
        dateFormat: "Y-m-d H:i:00",
        time_24hr: true,
        minuteIncrement: 10,
        // should be a number, not a string
        minDate: WP_Sms_Admin_Object.currentDateTime,
        disableMobile: true,
        defaultDate: WP_Sms_Admin_Object.currentDateTime
      });
    }

    // Event listener for schedule status checkbox
    jQuery("#schedule_status").on('change', function () {
      if (jQuery(this).is(":checked")) {
        jQuery('#schedule_date').show();
      } else {
        jQuery('#schedule_date').hide();
      }
    });
  }
  jQuery(".preview__message__number").html(jQuery("#wp_get_sender").val());
  if (jQuery("#wp_get_message").val()) {
    jQuery(".preview__message__message").html(jQuery("#wp_get_message").val());
  }
  jQuery("#wp_get_sender").on('keyup', function () {
    jQuery(".preview__message__number").html(jQuery("#wp_get_sender").val());
  });
  jQuery("#wp_get_message").on('keyup', function () {
    messageAutoScroll();
    var message = jQuery("#wp_get_message").val();
    var messageWithLineBreak = message.replace(/(\r\n|\n|\r)/gm, "<br>");
    jQuery(".preview__message__message").html(messageWithLineBreak);
    isRtl("#wp_get_message", ".preview__message__message");
  });

  // For receivers in message preview
  function updateReceiverPreview() {
    var toFieldValue = jQuery("#select_sender").find('option:selected').text();
    jQuery(".preview__message__receiver").text(toFieldValue);
  }
  updateReceiverPreview();
  jQuery("#select_sender").on('change', function () {
    updateReceiverPreview();
  });
  jQuery('button[name="SendSMS"]').on('click', function (e) {
    e.preventDefault();
    sendSMS();
  });
  jQuery('#SendSMSAgain').on('click', function () {
    jQuery('.sendsms-content .summary').fadeOut();
    jQuery('#content').trigger('click');
    jQuery('button[name="SendSMS"]').removeClass('inactive');
    hideResult();
  });
  function hideResult() {
    jQuery('.wpsms-sendsms-result').fadeOut();
  }
  jQuery('.sendsms-content .previous-button').on('click', hideResult);
  jQuery('.sendsms-content .next-button').on('click', hideResult);
  jQuery('.sendsms-tabs .tab').on('click', hideResult);

  /**
   * Upload Media
   */
  var $uploadButton = jQuery('.wpsms-upload-button');
  var $removeButton = jQuery('.wpsms-remove-button');
  var $imageElement = jQuery('.wpsms-mms-image');

  // on upload button click
  $uploadButton.on('click', function (e) {
    e.preventDefault();
    var button = jQuery(this),
      wpsms_uploader = wp.media({
        title: 'Insert image',
        library: {
          type: ['image']
        },
        button: {
          text: 'Use this image'
        },
        multiple: false
      }).on('select', function () {
        var attachment = wpsms_uploader.state().get('selection').first().toJSON();
        button.html('<img width="300" src="' + attachment.url + '">');
        $imageElement.val(attachment.url);
        $removeButton.show();
      }).open();
  });

  // on remove button click
  $removeButton.on('click', function (e) {
    e.preventDefault();
    jQuery(this).hide();
    $imageElement.val('');
    $uploadButton.html('Upload image');
  });

  /**
   * Manage Send SMS New Page
   */
  var WpSendSMSPageManager = {
    getFields: function getFields() {
      this.fields = {
        contentTab: {
          element: jQuery('.wpsms-sendsms .tab#content')
        },
        receiverTab: {
          element: jQuery('.wpsms-sendsms .tab#receiver')
        },
        optionsTab: {
          element: jQuery('.wpsms-sendsms .tab#options')
        },
        sendTab: {
          element: jQuery('.wpsms-sendsms .tab#send')
        },
        allTab: {
          element: jQuery('.wpsms-sendsms .tab')
        },
        fromField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .from-field')
        },
        toField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .to-field')
        },
        groupField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .wpsms-group-field')
        },
        usersField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .wpsms-users-field')
        },
        searchUserField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .wpsms-search-user-field')
        },
        numbersField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .wpsms-numbers-field')
        },
        bulkField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .bulk-field')
        },
        contentField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .content-field')
        },
        mmsMediaField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .mms-media-field')
        },
        scheduleField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .schedule-field')
        },
        setDateField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .set-date-field')
        },
        repeatField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .repeat-field')
        },
        repeatEveryField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .repeat-every-field')
        },
        repeatEndField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .repeat-end-field')
        },
        flashField: {
          element: jQuery('.wpsms-sendsms .sendsms-content .flash-field')
        },
        summary: {
          element: jQuery('.wpsms-sendsms .sendsms-content .summary')
        },
        submitButton: {
          element: jQuery('.wpsms-sendsms .sendsms-content .sendsms-button')
        },
        sendAgainButton: {
          element: jQuery('.wpsms-sendsms .sendsms-content .sendsms-again-button')
        },
        nextButton: {
          element: jQuery('#wpbody-content .next-button')
        },
        prevButton: {
          element: jQuery('#wpbody-content .previous-button')
        }
      };
    },
    addEventListener: function addEventListener() {
      var self = this;
      self.manageNavigationKeys();
      self.fields.allTab.element.on('click', function () {
        self.fields.allTab.element.removeClass('active passed');
        jQuery(this).addClass('active');
        var prevElements = jQuery(this).prevAll();
        prevElements.addClass('passed');
        self.manageFieldsVisibility();
        self.manageNavigationKeys();
      });
      self.fields.nextButton.element.on('click', function () {
        var activeTab = jQuery('.wpsms-sendsms .tab.active');
        var nextTab = activeTab.next('.tab');
        if (nextTab.length > 0) {
          self.fields.allTab.element.removeClass('active passed');
          nextTab.addClass('active');
          var prevElements = nextTab.prevAll();
          prevElements.addClass('passed');
          self.manageFieldsVisibility();
        }
        self.manageNavigationKeys();
      });
      self.fields.prevButton.element.on('click', function () {
        var activeTab = jQuery('.wpsms-sendsms .tab.active');
        var prevTab = activeTab.prev('.tab');
        if (prevTab.length > 0) {
          self.fields.allTab.element.removeClass('active passed');
          prevTab.addClass('active');
          var prevElements = prevTab.prevAll();
          prevElements.addClass('passed');
          self.manageFieldsVisibility();
        }
        self.manageNavigationKeys();
      });
      self.fields.toField.element.find('select').on('change', function () {
        self.manageRecipients();
      });
      self.fields.scheduleField.element.find('input[type="checkbox"]').on('change', function () {
        self.manageProOptions();
      });
      self.fields.repeatField.element.find('input[type="checkbox"]').on('change', function () {
        self.manageProOptions();
      });
    },
    manageProOptions: function manageProOptions() {
      var activeTab = jQuery('.wpsms-sendsms .tab.active');
      var activeTabId = activeTab.attr("id");
      var scheduleFieldState = jQuery("#schedule_status").is(":checked");
      var repeatFieldState = jQuery("#wpsms_repeat_status").is(":checked");
      if (activeTabId == 'options' && scheduleFieldState) {
        this.fields.setDateField.element.fadeIn();
        this.fields.repeatField.element.fadeIn();
      } else {
        this.fields.setDateField.element.hide();
        this.fields.repeatField.element.hide();
      }
      if (activeTabId == 'options' && scheduleFieldState && repeatFieldState) {
        this.fields.repeatEveryField.element.fadeIn();
        this.fields.repeatEndField.element.fadeIn();
      } else {
        this.fields.repeatEveryField.element.hide();
        this.fields.repeatEndField.element.hide();
      }
    },
    manageNavigationKeys: function manageNavigationKeys() {
      var activeTab = jQuery('.wpsms-sendsms .tab.active');
      var prevTab = activeTab.prev('.tab');
      var prevTabs = activeTab.prevAll();
      var nextTab = activeTab.next('.tab');
      var nextTabs = activeTab.nextAll();
      if (nextTabs.length < 1) {
        this.fields.nextButton.element.addClass('inactive');
      } else {
        this.fields.nextButton.element.removeClass('inactive');
      }
      if (prevTabs.length < 1) {
        this.fields.prevButton.element.addClass('inactive');
      } else {
        this.fields.prevButton.element.removeClass('inactive');
      }
    },
    manageFieldsVisibility: function manageFieldsVisibility() {
      var activeTab = jQuery('.wpsms-sendsms .tab.active');
      var activeTabId = activeTab.attr("id");

      // Firstly hide all fields
      var fields = [this.fields.fromField, this.fields.toField, this.fields.searchUserField, this.fields.groupField, this.fields.usersField, this.fields.numbersField, this.fields.bulkField, this.fields.contentField, this.fields.mmsMediaField, this.fields.scheduleField, this.fields.setDateField, this.fields.repeatField, this.fields.repeatEveryField, this.fields.repeatEndField, this.fields.flashField, this.fields.summary, this.fields.submitButton, this.fields.sendAgainButton];

      // Loop through the fields and hide each one
      for (var _i = 0, _fields = fields; _i < _fields.length; _i++) {
        var field = _fields[_i];
        field.element.hide();
      }

      // Disable send sms button
      this.fields.submitButton.element.prop('disabled', true);

      // Secondly show fields based on the selected tab
      switch (activeTabId) {
        case 'content':
          this.fields.contentField.element.fadeIn();
          break;
        case 'receiver':
          this.fields.fromField.element.fadeIn();
          this.fields.toField.element.fadeIn();
          this.manageRecipients();
          break;
        case 'options':
          this.fields.bulkField.element.fadeIn();
          this.fields.mmsMediaField.element.fadeIn();
          this.fields.scheduleField.element.fadeIn();
          this.fields.flashField.element.fadeIn();
          this.manageProOptions();
          break;
        case 'send':
          this.fields.summary.element.fadeIn();
          this.fields.submitButton.element.fadeIn();
          this.fields.submitButton.element.prop('disabled', false);
          break;
      }
    },
    manageRecipients: function manageRecipients() {
      var activeTabId = jQuery('.wpsms-sendsms .tab.active').attr("id");
      var toFieldState = this.fields.toField.element.find('select option:selected').attr("id");
      if (activeTabId !== 'receiver') {
        return;
      }

      // Firstly hide all the related fields
      jQuery(".wpsms-value").hide();
      switch (toFieldState) {
        case 'wp_subscribe_username':
          jQuery(".wpsms-group").fadeIn();
          break;
        case 'wp_roles':
          jQuery(".wpsms-roles").fadeIn();
          break;
        case 'wp_users':
          jQuery(".wpsms-users").fadeIn();
          break;
        case 'wc_users':
          jQuery(".wpsms-wc-users").fadeIn();
          break;
        case 'bp_users':
          jQuery(".wpsms-bp-users").fadeIn();
          jQuery(".wpsms-search-user-field").fadeIn();
          break;
        case 'wp_tellephone':
          jQuery(".wpsms-numbers").fadeIn();
          jQuery("#wp_get_number").focus();
          break;
        case 'wp_role':
          jQuery(".wprole-group").fadeIn();
          break;
      }
    },
    addSearchUserEventListener: function addSearchUserEventListener() {
      var selectedOptions = [];
      var selectElement = jQuery('.wpsms-sendsms .wpsms-search-user select.js-wpsms-select2');

      // Store selected options when an option is selected
      selectElement.on('select2:select', function (e) {
        var selectedOption = e.params.data;
        if (selectedOption) {
          // Check if the selected option is not already in the selectedOptions array
          var index = selectedOptions.findIndex(function (option) {
            return option.id == selectedOption.id;
          });
          if (index == -1) {
            selectedOptions.push(selectedOption);
          }
        }
      });

      // Remove unselected option when an option is unselected
      selectElement.on('select2:unselect', function (e) {
        var unselectedOption = e.params.data;
        if (unselectedOption) {
          // Check if the selected option is not already in the selectedOptions array
          var indexToRemove = selectedOptions.findIndex(function (option) {
            return option.id == unselectedOption.id;
          });
          if (indexToRemove !== -1) {
            selectedOptions.splice(indexToRemove, 1);
          }
        }
      });
      selectElement.select2({
        ajax: {
          url: WP_Sms_Admin_Object.restUrls.users,
          method: 'GET',
          dataType: 'json',
          headers: {
            'X-WP-Nonce': WP_Sms_Admin_Object.nonce
          },
          data: function data(params) {
            return {
              search: params.term
            };
          },
          processResults: function processResults(users) {
            var results = [];
            // Process each user
            users.forEach(function (user) {
              if (user.id && user.id > 0) {
                optionTitle = user.slug + ' ( ' + user.name + ' )';
                // Check if the user is not already in the selectedOptions array
                var index = selectedOptions.findIndex(function (option) {
                  return option.id == user.id;
                });
                if (index == -1) {
                  results.push({
                    id: user.id,
                    text: optionTitle
                  });
                }
              }
            });

            // Return the processed results
            return {
              results: results
            };
          }
        },
        templateResult: function templateResult(result) {
          return jQuery('<span>' + result.text + '</span>');
        },
        escapeMarkup: function escapeMarkup(markup) {
          return markup;
        }
      });
    },
    init: function init() {
      this.getFields();
      this.addEventListener();
      this.addSearchUserEventListener();
      this.manageFieldsVisibility();
    }
  };
  WpSendSMSPageManager.init();
});
function isRtl(input, output) {
  jQuery(input).off('keypress').on('keypress', function (e) {
    setTimeout(function () {
      if (jQuery(input).val().length > 1) {
        return;
      } else {
        var RTL_Regex = /[\u0591-\u07FF\uFB1D-\uFDFD\uFE70-\uFEFC]/;
        var isRTL = RTL_Regex.test(String.fromCharCode(e.which));
        var Direction = isRTL ? 'rtl' : 'ltr';
        jQuery(input).css({
          'direction': Direction
        });
        if (isRTL) {
          jQuery(output).css({
            'direction': 'rtl'
          });
        } else {
          jQuery(output).css({
            'direction': 'ltr'
          });
        }
      }
    });
  });
}
function scrollToTop() {
  jQuery('html, body').animate({
    scrollTop: 0
  }, 1000);
}
function closeNotice() {
  jQuery(".wpsms-sendsms-result").fadeOut();
}
function clearForm() {
  jQuery(".preview__message__message").html('');
  jQuery("#repeat-interval").val(1);
  jQuery("#repeat-forever").prop("checked", false);
  jQuery("#schedule_status").prop("checked", false);
  jQuery("#wpsms_repeat_status").prop("checked", false);
  jQuery("#repeat-interval-unit").val("day");
  jQuery(".wpsms-mms-image").val([]).trigger('change');
  jQuery(".js-wpsms-select2").val([]).trigger('change');
  jQuery("#wp_get_number").val('').trigger('change');
  jQuery("#wp_get_message").val('').trigger('change');
}
function sendSMS() {
  var smsFrom = jQuery("#wp_get_sender").val(),
    smsTo = {
      type: jQuery("select[name='wp_send_to'] option:selected").val()
    },
    smsMessage = jQuery("#wp_get_message").val(),
    smsMedia = jQuery(".wpsms-mms-image").val(),
    smsScheduled = {
      scheduled: jQuery("#schedule_status").is(":checked")
    },
    smsRepeating = wpsmsRepeatingMessages.getData(),
    smsFlash = jQuery('[name="wp_flash"]:checked').val();
  if (smsTo.type === "subscribers") {
    smsTo.groups = jQuery('.wpsms-group select[name="wpsms_groups[]"]').val();
  } else if (smsTo.type === "roles") {
    smsTo.roles = jQuery('select[name="wpsms_roles[]"]').val();
  } else if (smsTo.type === "users") {
    smsTo.users = jQuery('select[name="wpsms_users[]"]').val();
  } else if (smsTo.type === "numbers") {
    smsTo.numbers = jQuery('textarea[name="wp_get_number"]').val();
    smsTo.numbers = smsTo.numbers.replace(/\n/g, ",").split(",");
  }
  if (smsScheduled.scheduled) {
    smsScheduled.date = jQuery("#datepicker").val();
  }
  var requestBody = {
    sender: smsFrom,
    recipients: smsTo.type,
    group_ids: smsTo.groups,
    role_ids: smsTo.roles,
    users: smsTo.users,
    message: smsMessage,
    numbers: smsTo.numbers,
    flash: smsFlash,
    media_urls: [smsMedia],
    schedule: smsScheduled.date,
    repeat: smsRepeating
  };
  requestBody = wp.hooks.applyFilters('wp_sms_send_request_body', requestBody);
  jQuery('.wpsms-sendsms-result').fadeOut();
  jQuery.ajax(WP_Sms_Admin_Object.restUrls.sendSms, {
    headers: {
      'X-WP-Nonce': WP_Sms_Admin_Object.nonce
    },
    dataType: 'json',
    type: 'post',
    contentType: 'application/json',
    data: JSON.stringify(requestBody),
    beforeSend: function beforeSend() {
      jQuery(".wpsms-sendsms__overlay").css('display', 'flex');
      jQuery('button[name="SendSMS"]').fadeOut();
    },
    success: function success(data, status, xhr) {
      Object.keys(smsTo).forEach(function (key) {
        delete smsTo[key];
      });
      jQuery(".wpsms-remove-button").trigger('click');
      jQuery(".wpsms-sendsms__overlay").css('display', 'none');
      jQuery('.wpsms-sendsms-result').removeClass('error');
      jQuery('.wpsms-sendsms-result').addClass('success');
      jQuery('.wpsms-sendsms-result p').html(data.message);
      jQuery('#wpsms_account_credit').html(data.data.balance);
      jQuery('.wpsms-sendsms-result').fadeIn();
      jQuery('#SendSMSAgain').fadeIn();
      // clearForm();
      scrollToTop();
    },
    error: function error(data, status, xhr) {
      jQuery('.wpsms-sendsms-result').removeClass('success');
      jQuery('.wpsms-sendsms-result').addClass('error');
      jQuery('.wpsms-sendsms-result p').html(data.responseJSON.error.message);
      jQuery('.wpsms-sendsms-result').fadeIn();
      jQuery(".wpsms-sendsms__overlay").css('display', 'none');
      jQuery('button[name="SendSMS"]').removeClass('inactive');
      scrollToTop();
    }
  });
}
function messageAutoScroll() {
  jQuery('.preview__message__message-wrapper').scrollTop(jQuery('.preview__message__message').height());
}
var wpsmsRepeatingMessages = {
  init: function init() {
    if (!WP_Sms_Admin_Object.proIsActive) return;
    this.setElements();
    this.initElements();
    this.handleFieldsVisibility();
    this.handleEndDateField();
  },
  setElements: function setElements() {
    this.elements = {
      statusCheckbox: jQuery('#wpsms_repeat_status'),
      parentCheckbox: jQuery('#schedule_status'),
      subFields: jQuery('.repeat-subfield'),
      repeatInterval: jQuery('#repeat-interval'),
      repeatUnit: jQuery('#repeat-interval-unit'),
      endDatepicker: jQuery('#repeat_ends_on'),
      foreverCheckbox: jQuery('#repeat-forever')
    };
  },
  initElements: function initElements() {
    // Ensure the endDatepicker element exists and flatpickr is a function
    if (this.elements.endDatepicker && typeof this.elements.endDatepicker.flatpickr === 'function') {
      this.elements.endDatepicker.flatpickr({
        enableTime: true,
        dateFormat: "Y-m-d H:i:00",
        time_24hr: true,
        minuteIncrement: 10,
        // should be a number, not a string
        minDate: WP_Sms_Admin_Object.currentDateTime,
        disableMobile: true,
        defaultDate: WP_Sms_Admin_Object.currentDateTime
      });
    }
  },
  handleFieldsVisibility: function handleFieldsVisibility() {
    var handler = function () {
      if (this.elements.parentCheckbox.is(':checked')) {
        this.elements.statusCheckbox.closest('tr').show();
      } else {
        this.elements.statusCheckbox.closest('tr').hide();
      }
      if (this.elements.parentCheckbox.is(':checked') && this.elements.statusCheckbox.is(':checked')) {
        this.elements.subFields.show();
        this.isActive = true;
      } else {
        this.elements.subFields.hide();
        this.isActive = false;
      }
    }.bind(this);
    handler();

    //Event listeners
    this.elements.statusCheckbox.on('change', handler);
    this.elements.parentCheckbox.on('change', handler);
  },
  handleEndDateField: function handleEndDateField() {
    var handler = function () {
      if (this.elements.foreverCheckbox.is(':checked')) {
        this.elements.endDatepicker.attr('disabled', 'disabled');
      } else {
        this.elements.endDatepicker.prop('disabled', false);
      }
    }.bind(this);
    handler();

    //Event listener
    this.elements.foreverCheckbox.on('change', handler);
  },
  getData: function getData() {
    if (!this.isActive) return;
    var elements = this.elements;
    var data = {
      interval: {
        value: elements.repeatInterval.val(),
        unit: elements.repeatUnit.val()
      }
    };
    elements.foreverCheckbox.is(':checked') ? data.repeatForever = true : data.endDate = elements.endDatepicker.val();
    return data;
  }
};

/***/ }),

/***/ 72:
/***/ (() => {

function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _createForOfIteratorHelper(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e) { throw _e; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e2) { didErr = true; err = _e2; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }
function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && iter[Symbol.iterator] != null || iter["@@iterator"] != null) return Array.from(iter); }
function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
jQuery(document).ready(function ($) {
  jQuery('body').on('thickbox:removed', function () {
    jQuery('.iti__country-container').trigger('click');
  });
  if (jQuery('#subscribe-meta-box').length) {
    WpSmsMetaBox.init();
  }
  var tablenavPages = document.querySelector('.wpsms-wrap__main .tablenav-pages');
  if (tablenavPages && tablenavPages.classList.contains('no-pages')) {
    // Remove margin and padding
    tablenavPages.parentElement.style.margin = '0';
    tablenavPages.parentElement.style.padding = '0';
    tablenavPages.parentElement.style.height = '0';
  }
  if (jQuery('.js-wpsms-chatbox-preview').length) {
    jQuery('.wpsms-chatbox').hide();
    $('.js-wpsms-chatbox-preview').click(function (e) {
      e.preventDefault();
      $('.wpsms-chatbox').fadeToggle();
    });
  }
  var WpSmsSelect2 = $('.js-wpsms-select2');
  var WpSmsExportForm = $('.js-wpSmsExportForm');
  function matchCustom(params, data) {
    // If there are no search terms, return all of the data
    if ($.trim(params.term) === '') {
      return data;
    }

    // Do not display the item if there is no 'text' property
    if (typeof data.text === 'undefined') {
      return null;
    }

    // `params.term` should be the term that is used for searching
    // `data.text` is the text that is displayed for the data object
    if (data.text.indexOf(params.term) > -1 || data.element.getAttribute('value') !== null && data.element.getAttribute('value').toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
      var modifiedData = $.extend({}, data, true);
      modifiedData.text += ' (matched)';

      // You can return modified objects from here
      // This includes matching the `children` how you want in nested data sets
      return modifiedData;
    }

    // Return `null` if the term should not be displayed
    return null;
  }
  var WpSmsSelect2Options = {
    placeholder: "Please select"
  };
  if (WpSmsExportForm.length) {
    WpSmsSelect2Options.dropdownParent = WpSmsSelect2.parent();
  }

  // Select2
  window.WpSmsSelect2 = WpSmsSelect2;
  WpSmsSelect2.select2(WpSmsSelect2Options);

  // Auto submit the gateways form, after changing value
  $("#wpsms_settings\\[gateway_name\\]").on('change', function () {
    $('input[name="submit"]').click();
  });

  //Initiate Color Picker
  if ($('.wpsms-color-picker').length) {
    $('.wpsms-color-picker').wpColorPicker();
  }
  ;
  if ($('.repeater').length) {
    $('.repeater').repeater({
      initEmpty: false,
      show: function show() {
        $(this).slideDown();
        var uploadField = $(this).find('.wpsms_settings_upload_field');
        var uploadButton = $(this).find('.wpsms_settings_upload_button');
        // Check if repeater has upload filed
        if (uploadField.length && uploadButton.length) {
          // Create unique ID based on element's index
          var newFieldIndex = uploadButton.closest('[data-repeater-list]').children().length - 1;
          var newFieldID = uploadField.attr('id') + '[' + newFieldIndex + ']';
          // Assign a unique ID to upload fields to prevent conflict
          uploadField.attr('id', newFieldID);
          uploadButton.attr('data-target', newFieldID);
        }
        var checkbox = $(this).find('[type="checkbox"]');
        // Check if repeater has checkbox
        if (checkbox.length) {
          // Create unique ID based on element's index
          var _newFieldIndex = checkbox.closest('[data-repeater-list]').children().length - 1;
          var _newFieldID = checkbox.attr('id') + '[' + _newFieldIndex + ']';
          // Assign a unique ID to checkbox fields to prevent conflict
          checkbox.attr('id', _newFieldID);
          if (checkbox.next().is('label')) {
            checkbox.next().attr('for', _newFieldID);
          }
        }
      },
      hide: function hide(deleteElement) {
        if (confirm('Are you sure you want to delete this item?')) {
          $(this).slideUp(deleteElement);
        }
      },
      isFirstItemUndeletable: true
    });
  }
  if ($('.wpsms-tooltip').length) {
    $('.wpsms-tooltip').tooltipster({
      theme: 'tooltipster-flat',
      maxWidth: 400
    });
  }

  // Open WordPress media library when user clicks on upload button
  $(document).on('click', '.wpsms_settings_upload_button', function (e) {
    var mediaUploader = wp.media({
      library: {
        type: 'image'
      },
      multiple: false
    });
    mediaUploader.open();
    mediaUploader.on('select', function () {
      var attachment = mediaUploader.state().get('selection').first().toJSON();
      var targetInput = document.getElementById(e.target.dataset.target);
      targetInput.value = attachment.url;
    });
  });
});

/**
 * Meta Box
 * @type {{init: WpSmsMetaBox.init, setFields: WpSmsMetaBox.setFields}}
 */
var WpSmsMetaBox = {
  /**
   * Initialize Functions
   */
  init: function init() {
    this.setFields();
    this.insertShortcode();
  },
  /**
   * Initialize jQuery Selectors
   */
  setFields: function setFields() {
    this.fields = {
      short_codes: {
        element: jQuery('#wpsms-short-codes')
      }
    };
  },
  insertShortcode: function insertShortcode() {
    this.fields.short_codes.element.find("code").each(function (index) {
      jQuery(this).on('click', function () {
        var shortCodeValue = ' ' + jQuery(this).text() + ' ';
        jQuery('#wpsms-text-template').val(function (i, text) {
          var cursorPosition = jQuery(this)[0].selectionStart;
          return text.substring(0, cursorPosition) + shortCodeValue + text.substring(cursorPosition);
        });
      });
    });
  }
};
var ShowIfEnabled = /*#__PURE__*/function () {
  function ShowIfEnabled() {
    _classCallCheck(this, ShowIfEnabled);
    this.initialize();
  }
  return _createClass(ShowIfEnabled, [{
    key: "initialize",
    value: function initialize() {
      var _this = this;
      var elements = document.querySelectorAll('[class^="js-wpsms-show_if_"]');
      elements.forEach(function (element) {
        var classListArray = _toConsumableArray(element.className.split(' '));
        var toggleElement = function toggleElement() {
          var displayed = false;
          classListArray.forEach(function (className) {
            if (className.includes('_enabled') || className.includes('_disabled')) {
              var id = _this.extractId(element);
              var checkbox = document.querySelector("#wpsms_settings\\[".concat(id, "\\]"));
              if (checkbox) {
                if (checkbox.checked && className.includes('_enabled')) {
                  _this.toggleDisplay(element);
                } else if (!checkbox.checked && className.includes('_disabled')) {
                  _this.toggleDisplay(element);
                } else {
                  element.style.display = 'none';
                }
              }
            } else if (className.includes('_equal_')) {
              var _this$extractIdAndVal = _this.extractIdAndValue(className),
                _id = _this$extractIdAndVal.id,
                value = _this$extractIdAndVal.value;
              if (_id && value) {
                var item = document.querySelector("#wpsms_settings\\[".concat(_id, "\\], #wps_pp_settings\\[").concat(_id, "\\], #").concat(_id));
                if (item && item.type === 'select-one') {
                  if (item.value == value) {
                    if (!displayed) {
                      _this.toggleDisplay(element);
                      displayed = true;
                    }
                  }
                  if (item.value != value) {
                    if (!displayed) {
                      element.style.display = 'none';
                    }
                  }
                }
              }
            }
          });
        };
        toggleElement();
        classListArray.forEach(function (className) {
          if (className.includes('_enabled') || className.includes('_disabled')) {
            var id = _this.extractId(element);
            var checkbox = document.querySelector("#wpsms_settings\\[".concat(id, "\\]"));
            if (checkbox) {
              checkbox.addEventListener('change', toggleElement);
            }
          } else if (className.includes('_equal_')) {
            var _this$extractIdAndVal2 = _this.extractIdAndValue(className),
              _id2 = _this$extractIdAndVal2.id;
            if (_id2) {
              var item = document.querySelector("#wpsms_settings\\[".concat(_id2, "\\], #wps_pp_settings\\[").concat(_id2, "\\], #").concat(_id2));
              if (item && item.type === 'select-one') {
                item.addEventListener('change', toggleElement);
              }
            }
          }
        });
      });
    }
  }, {
    key: "toggleDisplay",
    value: function toggleDisplay(element) {
      var displayType = element.tagName.toLowerCase() === 'tr' ? 'table-row' : 'table-cell';
      element.style.display = displayType;
    }
  }, {
    key: "extractId",
    value: function extractId(element) {
      var classes = element.className.split(' ');
      var _iterator = _createForOfIteratorHelper(classes),
        _step;
      try {
        for (_iterator.s(); !(_step = _iterator.n()).done;) {
          var className = _step.value;
          if (className.startsWith('js-wpsms-show_if_')) {
            var id = className.replace('js-wpsms-show_if_', '').replace('_enabled', '').replace('_disabled', '');
            if (id) {
              return id;
            }
          }
        }
      } catch (err) {
        _iterator.e(err);
      } finally {
        _iterator.f();
      }
      return null;
    }
  }, {
    key: "extractIdAndValue",
    value: function extractIdAndValue(className) {
      var id, value;
      if (className.startsWith('js-wpsms-show_if_')) {
        var parts = className.split('_');
        var indexOfEqual = parts.indexOf('equal');
        if (indexOfEqual !== -1 && indexOfEqual > 2 && indexOfEqual < parts.length - 1) {
          id = parts.slice(2, indexOfEqual).join('_');
          value = parts.slice(indexOfEqual + 1).join('_');
        }
      }
      return {
        id: id,
        value: value
      };
    }
  }]);
}();
document.addEventListener('DOMContentLoaded', function () {
  var notices = document.querySelectorAll('.notice');
  var promotionModal = document.querySelector('.promotion-modal');
  if (notices.length > 0 && (document.body.classList.contains('post-type-wpsms-command') || document.body.classList.contains('post-type-sms-campaign') || document.body.classList.contains('sms_page_wp-sms') || document.body.classList.contains('sms-woo-pro_page_wp-sms-woo-pro-cart-abandonment') || document.body.classList.contains('sms-woo-pro_page_wp-sms-woo-pro-settings'))) {
    notices.forEach(function (notice) {
      notice.classList.remove('inline');
      if (promotionModal) {
        notice.style.display = 'none';
      }
    });
  }
  new ShowIfEnabled();
});

/***/ }),

/***/ 717:
/***/ (function(module, exports, __webpack_require__) {

var __WEBPACK_AMD_DEFINE_FACTORY__, __WEBPACK_AMD_DEFINE_RESULT__;function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
function _get2() { if (typeof Reflect !== "undefined" && Reflect.get) { _get2 = Reflect.get.bind(); } else { _get2 = function _get(target, property, receiver) { var base = _superPropBase(target, property); if (!base) return; var desc = Object.getOwnPropertyDescriptor(base, property); if (desc.get) { return desc.get.call(arguments.length < 3 ? target : receiver); } return desc.value; }; } return _get2.apply(this, arguments); }
function _superPropBase(object, property) { while (!Object.prototype.hasOwnProperty.call(object, property)) { object = _getPrototypeOf(object); if (object === null) break; } return object; }
function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _iterableToArrayLimit(r, l) { var t = null == r ? null : "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t["return"] && (u = t["return"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }
function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(self, call) { if (call && (_typeof(call) === "object" || typeof call === "function")) { return call; } else if (call !== void 0) { throw new TypeError("Derived constructors may only return object or undefined"); } return _assertThisInitialized(self); }
function _assertThisInitialized(self) { if (self === void 0) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return self; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(o) { _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function _getPrototypeOf(o) { return o.__proto__ || Object.getPrototypeOf(o); }; return _getPrototypeOf(o); }
function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function"); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, writable: true, configurable: true } }); Object.defineProperty(subClass, "prototype", { writable: false }); if (superClass) _setPrototypeOf(subClass, superClass); }
function _setPrototypeOf(o, p) { _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function _setPrototypeOf(o, p) { o.__proto__ = p; return o; }; return _setPrototypeOf(o, p); }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _createForOfIteratorHelper(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e80) { throw _e80; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e81) { didErr = true; err = _e81; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }
function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && iter[Symbol.iterator] != null || iter["@@iterator"] != null) return Array.from(iter); }
function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
/*!
 * Chart.js v3.7.1
 * https://www.chartjs.org
 * (c) 2022 Chart.js Contributors
 * Released under the MIT License
 */
!function (t, e) {
  "object" == ( false ? 0 : _typeof(exports)) && "undefined" != "object" ? module.exports = e() :  true ? !(__WEBPACK_AMD_DEFINE_FACTORY__ = (e),
		__WEBPACK_AMD_DEFINE_RESULT__ = (typeof __WEBPACK_AMD_DEFINE_FACTORY__ === 'function' ?
		(__WEBPACK_AMD_DEFINE_FACTORY__.call(exports, __webpack_require__, exports, module)) :
		__WEBPACK_AMD_DEFINE_FACTORY__),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__)) : 0;
}(this, function () {
  "use strict";

  var t = "undefined" == typeof window ? function (t) {
    return t();
  } : window.requestAnimationFrame;
  function e(e, i, s) {
    var n = s || function (t) {
      return Array.prototype.slice.call(t);
    };
    var o = !1,
      a = [];
    return function () {
      for (var _len = arguments.length, s = new Array(_len), _key = 0; _key < _len; _key++) {
        s[_key] = arguments[_key];
      }
      a = n(s), o || (o = !0, t.call(window, function () {
        o = !1, e.apply(i, a);
      }));
    };
  }
  function i(t, e) {
    var i;
    return function () {
      for (var _len2 = arguments.length, s = new Array(_len2), _key2 = 0; _key2 < _len2; _key2++) {
        s[_key2] = arguments[_key2];
      }
      return e ? (clearTimeout(i), i = setTimeout(t, e, s)) : t.apply(this, s), e;
    };
  }
  var s = function s(t) {
      return "start" === t ? "left" : "end" === t ? "right" : "center";
    },
    n = function n(t, e, i) {
      return "start" === t ? e : "end" === t ? i : (e + i) / 2;
    },
    o = function o(t, e, i, s) {
      return t === (s ? "left" : "right") ? i : "center" === t ? (e + i) / 2 : e;
    };
  var a = new ( /*#__PURE__*/function () {
    function _class() {
      _classCallCheck(this, _class);
      this._request = null, this._charts = new Map(), this._running = !1, this._lastDate = void 0;
    }
    return _createClass(_class, [{
      key: "_notify",
      value: function _notify(t, e, i, s) {
        var n = e.listeners[s],
          o = e.duration;
        n.forEach(function (s) {
          return s({
            chart: t,
            initial: e.initial,
            numSteps: o,
            currentStep: Math.min(i - e.start, o)
          });
        });
      }
    }, {
      key: "_refresh",
      value: function _refresh() {
        var _this = this;
        this._request || (this._running = !0, this._request = t.call(window, function () {
          _this._update(), _this._request = null, _this._running && _this._refresh();
        }));
      }
    }, {
      key: "_update",
      value: function _update() {
        var _this2 = this;
        var t = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : Date.now();
        var e = 0;
        this._charts.forEach(function (i, s) {
          if (!i.running || !i.items.length) return;
          var n = i.items;
          var o,
            a = n.length - 1,
            r = !1;
          for (; a >= 0; --a) o = n[a], o._active ? (o._total > i.duration && (i.duration = o._total), o.tick(t), r = !0) : (n[a] = n[n.length - 1], n.pop());
          r && (s.draw(), _this2._notify(s, i, t, "progress")), n.length || (i.running = !1, _this2._notify(s, i, t, "complete"), i.initial = !1), e += n.length;
        }), this._lastDate = t, 0 === e && (this._running = !1);
      }
    }, {
      key: "_getAnims",
      value: function _getAnims(t) {
        var e = this._charts;
        var i = e.get(t);
        return i || (i = {
          running: !1,
          initial: !0,
          items: [],
          listeners: {
            complete: [],
            progress: []
          }
        }, e.set(t, i)), i;
      }
    }, {
      key: "listen",
      value: function listen(t, e, i) {
        this._getAnims(t).listeners[e].push(i);
      }
    }, {
      key: "add",
      value: function add(t, e) {
        var _this$_getAnims$items;
        e && e.length && (_this$_getAnims$items = this._getAnims(t).items).push.apply(_this$_getAnims$items, _toConsumableArray(e));
      }
    }, {
      key: "has",
      value: function has(t) {
        return this._getAnims(t).items.length > 0;
      }
    }, {
      key: "start",
      value: function start(t) {
        var e = this._charts.get(t);
        e && (e.running = !0, e.start = Date.now(), e.duration = e.items.reduce(function (t, e) {
          return Math.max(t, e._duration);
        }, 0), this._refresh());
      }
    }, {
      key: "running",
      value: function running(t) {
        if (!this._running) return !1;
        var e = this._charts.get(t);
        return !!(e && e.running && e.items.length);
      }
    }, {
      key: "stop",
      value: function stop(t) {
        var e = this._charts.get(t);
        if (!e || !e.items.length) return;
        var i = e.items;
        var s = i.length - 1;
        for (; s >= 0; --s) i[s].cancel();
        e.items = [], this._notify(t, e, Date.now(), "complete");
      }
    }, {
      key: "remove",
      value: function remove(t) {
        return this._charts["delete"](t);
      }
    }]);
  }())();
  /*!
   * @kurkle/color v0.1.9
   * https://github.com/kurkle/color#readme
   * (c) 2020 Jukka Kurkela
   * Released under the MIT License
   */
  var r = {
      0: 0,
      1: 1,
      2: 2,
      3: 3,
      4: 4,
      5: 5,
      6: 6,
      7: 7,
      8: 8,
      9: 9,
      A: 10,
      B: 11,
      C: 12,
      D: 13,
      E: 14,
      F: 15,
      a: 10,
      b: 11,
      c: 12,
      d: 13,
      e: 14,
      f: 15
    },
    l = "0123456789ABCDEF",
    h = function h(t) {
      return l[15 & t];
    },
    c = function c(t) {
      return l[(240 & t) >> 4] + l[15 & t];
    },
    d = function d(t) {
      return (240 & t) >> 4 == (15 & t);
    };
  function u(t) {
    var e = function (t) {
      return d(t.r) && d(t.g) && d(t.b) && d(t.a);
    }(t) ? h : c;
    return t ? "#" + e(t.r) + e(t.g) + e(t.b) + (t.a < 255 ? e(t.a) : "") : t;
  }
  function f(t) {
    return t + .5 | 0;
  }
  var g = function g(t, e, i) {
    return Math.max(Math.min(t, i), e);
  };
  function p(t) {
    return g(f(2.55 * t), 0, 255);
  }
  function m(t) {
    return g(f(255 * t), 0, 255);
  }
  function x(t) {
    return g(f(t / 2.55) / 100, 0, 1);
  }
  function b(t) {
    return g(f(100 * t), 0, 100);
  }
  var _ = /^rgba?\(\s*([-+.\d]+)(%)?[\s,]+([-+.e\d]+)(%)?[\s,]+([-+.e\d]+)(%)?(?:[\s,/]+([-+.e\d]+)(%)?)?\s*\)$/;
  var y = /^(hsla?|hwb|hsv)\(\s*([-+.e\d]+)(?:deg)?[\s,]+([-+.e\d]+)%[\s,]+([-+.e\d]+)%(?:[\s,]+([-+.e\d]+)(%)?)?\s*\)$/;
  function v(t, e, i) {
    var s = e * Math.min(i, 1 - i),
      n = function n(e) {
        var n = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : (e + t / 30) % 12;
        return i - s * Math.max(Math.min(n - 3, 9 - n, 1), -1);
      };
    return [n(0), n(8), n(4)];
  }
  function w(t, e, i) {
    var s = function s(_s2) {
      var n = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : (_s2 + t / 60) % 6;
      return i - i * e * Math.max(Math.min(n, 4 - n, 1), 0);
    };
    return [s(5), s(3), s(1)];
  }
  function M(t, e, i) {
    var s = v(t, 1, .5);
    var n;
    for (e + i > 1 && (n = 1 / (e + i), e *= n, i *= n), n = 0; n < 3; n++) s[n] *= 1 - e - i, s[n] += e;
    return s;
  }
  function k(t) {
    var e = t.r / 255,
      i = t.g / 255,
      s = t.b / 255,
      n = Math.max(e, i, s),
      o = Math.min(e, i, s),
      a = (n + o) / 2;
    var r, l, h;
    return n !== o && (h = n - o, l = a > .5 ? h / (2 - n - o) : h / (n + o), r = n === e ? (i - s) / h + (i < s ? 6 : 0) : n === i ? (s - e) / h + 2 : (e - i) / h + 4, r = 60 * r + .5), [0 | r, l || 0, a];
  }
  function S(t, e, i, s) {
    return (Array.isArray(e) ? t(e[0], e[1], e[2]) : t(e, i, s)).map(m);
  }
  function P(t, e, i) {
    return S(v, t, e, i);
  }
  function D(t) {
    return (t % 360 + 360) % 360;
  }
  function C(t) {
    var e = y.exec(t);
    var i,
      s = 255;
    if (!e) return;
    e[5] !== i && (s = e[6] ? p(+e[5]) : m(+e[5]));
    var n = D(+e[2]),
      o = +e[3] / 100,
      a = +e[4] / 100;
    return i = "hwb" === e[1] ? function (t, e, i) {
      return S(M, t, e, i);
    }(n, o, a) : "hsv" === e[1] ? function (t, e, i) {
      return S(w, t, e, i);
    }(n, o, a) : P(n, o, a), {
      r: i[0],
      g: i[1],
      b: i[2],
      a: s
    };
  }
  var O = {
      x: "dark",
      Z: "light",
      Y: "re",
      X: "blu",
      W: "gr",
      V: "medium",
      U: "slate",
      A: "ee",
      T: "ol",
      S: "or",
      B: "ra",
      C: "lateg",
      D: "ights",
      R: "in",
      Q: "turquois",
      E: "hi",
      P: "ro",
      O: "al",
      N: "le",
      M: "de",
      L: "yello",
      F: "en",
      K: "ch",
      G: "arks",
      H: "ea",
      I: "ightg",
      J: "wh"
    },
    A = {
      OiceXe: "f0f8ff",
      antiquewEte: "faebd7",
      aqua: "ffff",
      aquamarRe: "7fffd4",
      azuY: "f0ffff",
      beige: "f5f5dc",
      bisque: "ffe4c4",
      black: "0",
      blanKedOmond: "ffebcd",
      Xe: "ff",
      XeviTet: "8a2be2",
      bPwn: "a52a2a",
      burlywood: "deb887",
      caMtXe: "5f9ea0",
      KartYuse: "7fff00",
      KocTate: "d2691e",
      cSO: "ff7f50",
      cSnflowerXe: "6495ed",
      cSnsilk: "fff8dc",
      crimson: "dc143c",
      cyan: "ffff",
      xXe: "8b",
      xcyan: "8b8b",
      xgTMnPd: "b8860b",
      xWay: "a9a9a9",
      xgYF: "6400",
      xgYy: "a9a9a9",
      xkhaki: "bdb76b",
      xmagFta: "8b008b",
      xTivegYF: "556b2f",
      xSange: "ff8c00",
      xScEd: "9932cc",
      xYd: "8b0000",
      xsOmon: "e9967a",
      xsHgYF: "8fbc8f",
      xUXe: "483d8b",
      xUWay: "2f4f4f",
      xUgYy: "2f4f4f",
      xQe: "ced1",
      xviTet: "9400d3",
      dAppRk: "ff1493",
      dApskyXe: "bfff",
      dimWay: "696969",
      dimgYy: "696969",
      dodgerXe: "1e90ff",
      fiYbrick: "b22222",
      flSOwEte: "fffaf0",
      foYstWAn: "228b22",
      fuKsia: "ff00ff",
      gaRsbSo: "dcdcdc",
      ghostwEte: "f8f8ff",
      gTd: "ffd700",
      gTMnPd: "daa520",
      Way: "808080",
      gYF: "8000",
      gYFLw: "adff2f",
      gYy: "808080",
      honeyMw: "f0fff0",
      hotpRk: "ff69b4",
      RdianYd: "cd5c5c",
      Rdigo: "4b0082",
      ivSy: "fffff0",
      khaki: "f0e68c",
      lavFMr: "e6e6fa",
      lavFMrXsh: "fff0f5",
      lawngYF: "7cfc00",
      NmoncEffon: "fffacd",
      ZXe: "add8e6",
      ZcSO: "f08080",
      Zcyan: "e0ffff",
      ZgTMnPdLw: "fafad2",
      ZWay: "d3d3d3",
      ZgYF: "90ee90",
      ZgYy: "d3d3d3",
      ZpRk: "ffb6c1",
      ZsOmon: "ffa07a",
      ZsHgYF: "20b2aa",
      ZskyXe: "87cefa",
      ZUWay: "778899",
      ZUgYy: "778899",
      ZstAlXe: "b0c4de",
      ZLw: "ffffe0",
      lime: "ff00",
      limegYF: "32cd32",
      lRF: "faf0e6",
      magFta: "ff00ff",
      maPon: "800000",
      VaquamarRe: "66cdaa",
      VXe: "cd",
      VScEd: "ba55d3",
      VpurpN: "9370db",
      VsHgYF: "3cb371",
      VUXe: "7b68ee",
      VsprRggYF: "fa9a",
      VQe: "48d1cc",
      VviTetYd: "c71585",
      midnightXe: "191970",
      mRtcYam: "f5fffa",
      mistyPse: "ffe4e1",
      moccasR: "ffe4b5",
      navajowEte: "ffdead",
      navy: "80",
      Tdlace: "fdf5e6",
      Tive: "808000",
      TivedBb: "6b8e23",
      Sange: "ffa500",
      SangeYd: "ff4500",
      ScEd: "da70d6",
      pOegTMnPd: "eee8aa",
      pOegYF: "98fb98",
      pOeQe: "afeeee",
      pOeviTetYd: "db7093",
      papayawEp: "ffefd5",
      pHKpuff: "ffdab9",
      peru: "cd853f",
      pRk: "ffc0cb",
      plum: "dda0dd",
      powMrXe: "b0e0e6",
      purpN: "800080",
      YbeccapurpN: "663399",
      Yd: "ff0000",
      Psybrown: "bc8f8f",
      PyOXe: "4169e1",
      saddNbPwn: "8b4513",
      sOmon: "fa8072",
      sandybPwn: "f4a460",
      sHgYF: "2e8b57",
      sHshell: "fff5ee",
      siFna: "a0522d",
      silver: "c0c0c0",
      skyXe: "87ceeb",
      UXe: "6a5acd",
      UWay: "708090",
      UgYy: "708090",
      snow: "fffafa",
      sprRggYF: "ff7f",
      stAlXe: "4682b4",
      tan: "d2b48c",
      teO: "8080",
      tEstN: "d8bfd8",
      tomato: "ff6347",
      Qe: "40e0d0",
      viTet: "ee82ee",
      JHt: "f5deb3",
      wEte: "ffffff",
      wEtesmoke: "f5f5f5",
      Lw: "ffff00",
      LwgYF: "9acd32"
    };
  var T;
  function L(t) {
    T || (T = function () {
      var t = {},
        e = Object.keys(A),
        i = Object.keys(O);
      var s, n, o, a, r;
      for (s = 0; s < e.length; s++) {
        for (a = r = e[s], n = 0; n < i.length; n++) o = i[n], r = r.replace(o, O[o]);
        o = parseInt(A[a], 16), t[r] = [o >> 16 & 255, o >> 8 & 255, 255 & o];
      }
      return t;
    }(), T.transparent = [0, 0, 0, 0]);
    var e = T[t.toLowerCase()];
    return e && {
      r: e[0],
      g: e[1],
      b: e[2],
      a: 4 === e.length ? e[3] : 255
    };
  }
  function R(t, e, i) {
    if (t) {
      var _s3 = k(t);
      _s3[e] = Math.max(0, Math.min(_s3[e] + _s3[e] * i, 0 === e ? 360 : 1)), _s3 = P(_s3), t.r = _s3[0], t.g = _s3[1], t.b = _s3[2];
    }
  }
  function E(t, e) {
    return t ? Object.assign(e || {}, t) : t;
  }
  function I(t) {
    var e = {
      r: 0,
      g: 0,
      b: 0,
      a: 255
    };
    return Array.isArray(t) ? t.length >= 3 && (e = {
      r: t[0],
      g: t[1],
      b: t[2],
      a: 255
    }, t.length > 3 && (e.a = m(t[3]))) : (e = E(t, {
      r: 0,
      g: 0,
      b: 0,
      a: 1
    })).a = m(e.a), e;
  }
  function z(t) {
    return "r" === t.charAt(0) ? function (t) {
      var e = _.exec(t);
      var i,
        s,
        n,
        o = 255;
      if (e) {
        if (e[7] !== i) {
          var _t2 = +e[7];
          o = 255 & (e[8] ? p(_t2) : 255 * _t2);
        }
        return i = +e[1], s = +e[3], n = +e[5], i = 255 & (e[2] ? p(i) : i), s = 255 & (e[4] ? p(s) : s), n = 255 & (e[6] ? p(n) : n), {
          r: i,
          g: s,
          b: n,
          a: o
        };
      }
    }(t) : C(t);
  }
  var F = /*#__PURE__*/function () {
    function F(t) {
      _classCallCheck(this, F);
      if (t instanceof F) return t;
      var e = _typeof(t);
      var i;
      var s, n, o;
      "object" === e ? i = I(t) : "string" === e && (o = (s = t).length, "#" === s[0] && (4 === o || 5 === o ? n = {
        r: 255 & 17 * r[s[1]],
        g: 255 & 17 * r[s[2]],
        b: 255 & 17 * r[s[3]],
        a: 5 === o ? 17 * r[s[4]] : 255
      } : 7 !== o && 9 !== o || (n = {
        r: r[s[1]] << 4 | r[s[2]],
        g: r[s[3]] << 4 | r[s[4]],
        b: r[s[5]] << 4 | r[s[6]],
        a: 9 === o ? r[s[7]] << 4 | r[s[8]] : 255
      })), i = n || L(t) || z(t)), this._rgb = i, this._valid = !!i;
    }
    return _createClass(F, [{
      key: "valid",
      get: function get() {
        return this._valid;
      }
    }, {
      key: "rgb",
      get: function get() {
        var t = E(this._rgb);
        return t && (t.a = x(t.a)), t;
      },
      set: function set(t) {
        this._rgb = I(t);
      }
    }, {
      key: "rgbString",
      value: function rgbString() {
        return this._valid ? (t = this._rgb) && (t.a < 255 ? "rgba(".concat(t.r, ", ").concat(t.g, ", ").concat(t.b, ", ").concat(x(t.a), ")") : "rgb(".concat(t.r, ", ").concat(t.g, ", ").concat(t.b, ")")) : this._rgb;
        var t;
      }
    }, {
      key: "hexString",
      value: function hexString() {
        return this._valid ? u(this._rgb) : this._rgb;
      }
    }, {
      key: "hslString",
      value: function hslString() {
        return this._valid ? function (t) {
          if (!t) return;
          var e = k(t),
            i = e[0],
            s = b(e[1]),
            n = b(e[2]);
          return t.a < 255 ? "hsla(".concat(i, ", ").concat(s, "%, ").concat(n, "%, ").concat(x(t.a), ")") : "hsl(".concat(i, ", ").concat(s, "%, ").concat(n, "%)");
        }(this._rgb) : this._rgb;
      }
    }, {
      key: "mix",
      value: function mix(t, e) {
        var i = this;
        if (t) {
          var _s4 = i.rgb,
            _n2 = t.rgb;
          var _o2;
          var _a2 = e === _o2 ? .5 : e,
            _r = 2 * _a2 - 1,
            _l = _s4.a - _n2.a,
            _h = ((_r * _l == -1 ? _r : (_r + _l) / (1 + _r * _l)) + 1) / 2;
          _o2 = 1 - _h, _s4.r = 255 & _h * _s4.r + _o2 * _n2.r + .5, _s4.g = 255 & _h * _s4.g + _o2 * _n2.g + .5, _s4.b = 255 & _h * _s4.b + _o2 * _n2.b + .5, _s4.a = _a2 * _s4.a + (1 - _a2) * _n2.a, i.rgb = _s4;
        }
        return i;
      }
    }, {
      key: "clone",
      value: function clone() {
        return new F(this.rgb);
      }
    }, {
      key: "alpha",
      value: function alpha(t) {
        return this._rgb.a = m(t), this;
      }
    }, {
      key: "clearer",
      value: function clearer(t) {
        return this._rgb.a *= 1 - t, this;
      }
    }, {
      key: "greyscale",
      value: function greyscale() {
        var t = this._rgb,
          e = f(.3 * t.r + .59 * t.g + .11 * t.b);
        return t.r = t.g = t.b = e, this;
      }
    }, {
      key: "opaquer",
      value: function opaquer(t) {
        return this._rgb.a *= 1 + t, this;
      }
    }, {
      key: "negate",
      value: function negate() {
        var t = this._rgb;
        return t.r = 255 - t.r, t.g = 255 - t.g, t.b = 255 - t.b, this;
      }
    }, {
      key: "lighten",
      value: function lighten(t) {
        return R(this._rgb, 2, t), this;
      }
    }, {
      key: "darken",
      value: function darken(t) {
        return R(this._rgb, 2, -t), this;
      }
    }, {
      key: "saturate",
      value: function saturate(t) {
        return R(this._rgb, 1, t), this;
      }
    }, {
      key: "desaturate",
      value: function desaturate(t) {
        return R(this._rgb, 1, -t), this;
      }
    }, {
      key: "rotate",
      value: function rotate(t) {
        return function (t, e) {
          var i = k(t);
          i[0] = D(i[0] + e), i = P(i), t.r = i[0], t.g = i[1], t.b = i[2];
        }(this._rgb, t), this;
      }
    }]);
  }();
  function B(t) {
    return new F(t);
  }
  var V = function V(t) {
    return t instanceof CanvasGradient || t instanceof CanvasPattern;
  };
  function W(t) {
    return V(t) ? t : B(t);
  }
  function N(t) {
    return V(t) ? t : B(t).saturate(.5).darken(.1).hexString();
  }
  function H() {}
  var j = function () {
    var t = 0;
    return function () {
      return t++;
    };
  }();
  function $(t) {
    return null == t;
  }
  function Y(t) {
    if (Array.isArray && Array.isArray(t)) return !0;
    var e = Object.prototype.toString.call(t);
    return "[object" === e.substr(0, 7) && "Array]" === e.substr(-6);
  }
  function U(t) {
    return null !== t && "[object Object]" === Object.prototype.toString.call(t);
  }
  var X = function X(t) {
    return ("number" == typeof t || t instanceof Number) && isFinite(+t);
  };
  function q(t, e) {
    return X(t) ? t : e;
  }
  function K(t, e) {
    return void 0 === t ? e : t;
  }
  var G = function G(t, e) {
      return "string" == typeof t && t.endsWith("%") ? parseFloat(t) / 100 : t / e;
    },
    Z = function Z(t, e) {
      return "string" == typeof t && t.endsWith("%") ? parseFloat(t) / 100 * e : +t;
    };
  function J(t, e, i) {
    if (t && "function" == typeof t.call) return t.apply(i, e);
  }
  function Q(t, e, i, s) {
    var n, o, a;
    if (Y(t)) {
      if (o = t.length, s) for (n = o - 1; n >= 0; n--) e.call(i, t[n], n);else for (n = 0; n < o; n++) e.call(i, t[n], n);
    } else if (U(t)) for (a = Object.keys(t), o = a.length, n = 0; n < o; n++) e.call(i, t[a[n]], a[n]);
  }
  function tt(t, e) {
    var i, s, n, o;
    if (!t || !e || t.length !== e.length) return !1;
    for (i = 0, s = t.length; i < s; ++i) if (n = t[i], o = e[i], n.datasetIndex !== o.datasetIndex || n.index !== o.index) return !1;
    return !0;
  }
  function et(t) {
    if (Y(t)) return t.map(et);
    if (U(t)) {
      var _e2 = Object.create(null),
        _i2 = Object.keys(t),
        _s5 = _i2.length;
      var _n3 = 0;
      for (; _n3 < _s5; ++_n3) _e2[_i2[_n3]] = et(t[_i2[_n3]]);
      return _e2;
    }
    return t;
  }
  function it(t) {
    return -1 === ["__proto__", "prototype", "constructor"].indexOf(t);
  }
  function st(t, e, i, s) {
    if (!it(t)) return;
    var n = e[t],
      o = i[t];
    U(n) && U(o) ? nt(n, o, s) : e[t] = et(o);
  }
  function nt(t, e, i) {
    var s = Y(e) ? e : [e],
      n = s.length;
    if (!U(t)) return t;
    var o = (i = i || {}).merger || st;
    for (var _a3 = 0; _a3 < n; ++_a3) {
      if (!U(e = s[_a3])) continue;
      var _n4 = Object.keys(e);
      for (var _s6 = 0, _a4 = _n4.length; _s6 < _a4; ++_s6) o(_n4[_s6], t, e, i);
    }
    return t;
  }
  function ot(t, e) {
    return nt(t, e, {
      merger: at
    });
  }
  function at(t, e, i) {
    if (!it(t)) return;
    var s = e[t],
      n = i[t];
    U(s) && U(n) ? ot(s, n) : Object.prototype.hasOwnProperty.call(e, t) || (e[t] = et(n));
  }
  function rt(t, e) {
    var i = t.indexOf(".", e);
    return -1 === i ? t.length : i;
  }
  function lt(t, e) {
    if ("" === e) return t;
    var i = 0,
      s = rt(e, i);
    for (; t && s > i;) t = t[e.substr(i, s - i)], i = s + 1, s = rt(e, i);
    return t;
  }
  function ht(t) {
    return t.charAt(0).toUpperCase() + t.slice(1);
  }
  var ct = function ct(t) {
      return void 0 !== t;
    },
    dt = function dt(t) {
      return "function" == typeof t;
    },
    ut = function ut(t, e) {
      if (t.size !== e.size) return !1;
      var _iterator = _createForOfIteratorHelper(t),
        _step;
      try {
        for (_iterator.s(); !(_step = _iterator.n()).done;) {
          var _i3 = _step.value;
          if (!e.has(_i3)) return !1;
        }
      } catch (err) {
        _iterator.e(err);
      } finally {
        _iterator.f();
      }
      return !0;
    };
  function ft(t) {
    return "mouseup" === t.type || "click" === t.type || "contextmenu" === t.type;
  }
  var gt = Object.create(null),
    pt = Object.create(null);
  function mt(t, e) {
    if (!e) return t;
    var i = e.split(".");
    for (var _e3 = 0, _s7 = i.length; _e3 < _s7; ++_e3) {
      var _s8 = i[_e3];
      t = t[_s8] || (t[_s8] = Object.create(null));
    }
    return t;
  }
  function xt(t, e, i) {
    return "string" == typeof e ? nt(mt(t, e), i) : nt(mt(t, ""), e);
  }
  var bt = new ( /*#__PURE__*/function () {
    function _class2(t) {
      _classCallCheck(this, _class2);
      this.animation = void 0, this.backgroundColor = "rgba(0,0,0,0.1)", this.borderColor = "rgba(0,0,0,0.1)", this.color = "#666", this.datasets = {}, this.devicePixelRatio = function (t) {
        return t.chart.platform.getDevicePixelRatio();
      }, this.elements = {}, this.events = ["mousemove", "mouseout", "click", "touchstart", "touchmove"], this.font = {
        family: "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif",
        size: 12,
        style: "normal",
        lineHeight: 1.2,
        weight: null
      }, this.hover = {}, this.hoverBackgroundColor = function (t, e) {
        return N(e.backgroundColor);
      }, this.hoverBorderColor = function (t, e) {
        return N(e.borderColor);
      }, this.hoverColor = function (t, e) {
        return N(e.color);
      }, this.indexAxis = "x", this.interaction = {
        mode: "nearest",
        intersect: !0
      }, this.maintainAspectRatio = !0, this.onHover = null, this.onClick = null, this.parsing = !0, this.plugins = {}, this.responsive = !0, this.scale = void 0, this.scales = {}, this.showLine = !0, this.drawActiveElementsOnTop = !0, this.describe(t);
    }
    return _createClass(_class2, [{
      key: "set",
      value: function set(t, e) {
        return xt(this, t, e);
      }
    }, {
      key: "get",
      value: function get(t) {
        return mt(this, t);
      }
    }, {
      key: "describe",
      value: function describe(t, e) {
        return xt(pt, t, e);
      }
    }, {
      key: "override",
      value: function override(t, e) {
        return xt(gt, t, e);
      }
    }, {
      key: "route",
      value: function route(t, e, i, s) {
        var n = mt(this, t),
          o = mt(this, i),
          a = "_" + e;
        Object.defineProperties(n, _defineProperty(_defineProperty({}, a, {
          value: n[e],
          writable: !0
        }), e, {
          enumerable: !0,
          get: function get() {
            var t = this[a],
              e = o[s];
            return U(t) ? Object.assign({}, e, t) : K(t, e);
          },
          set: function set(t) {
            this[a] = t;
          }
        }));
      }
    }]);
  }())({
    _scriptable: function _scriptable(t) {
      return !t.startsWith("on");
    },
    _indexable: function _indexable(t) {
      return "events" !== t;
    },
    hover: {
      _fallback: "interaction"
    },
    interaction: {
      _scriptable: !1,
      _indexable: !1
    }
  });
  var _t = Math.PI,
    yt = 2 * _t,
    vt = yt + _t,
    wt = Number.POSITIVE_INFINITY,
    Mt = _t / 180,
    kt = _t / 2,
    St = _t / 4,
    Pt = 2 * _t / 3,
    Dt = Math.log10,
    Ct = Math.sign;
  function Ot(t) {
    var e = Math.round(t);
    t = Lt(t, e, t / 1e3) ? e : t;
    var i = Math.pow(10, Math.floor(Dt(t))),
      s = t / i;
    return (s <= 1 ? 1 : s <= 2 ? 2 : s <= 5 ? 5 : 10) * i;
  }
  function At(t) {
    var e = [],
      i = Math.sqrt(t);
    var s;
    for (s = 1; s < i; s++) t % s == 0 && (e.push(s), e.push(t / s));
    return i === (0 | i) && e.push(i), e.sort(function (t, e) {
      return t - e;
    }).pop(), e;
  }
  function Tt(t) {
    return !isNaN(parseFloat(t)) && isFinite(t);
  }
  function Lt(t, e, i) {
    return Math.abs(t - e) < i;
  }
  function Rt(t, e) {
    var i = Math.round(t);
    return i - e <= t && i + e >= t;
  }
  function Et(t, e, i) {
    var s, n, o;
    for (s = 0, n = t.length; s < n; s++) o = t[s][i], isNaN(o) || (e.min = Math.min(e.min, o), e.max = Math.max(e.max, o));
  }
  function It(t) {
    return t * (_t / 180);
  }
  function zt(t) {
    return t * (180 / _t);
  }
  function Ft(t) {
    if (!X(t)) return;
    var e = 1,
      i = 0;
    for (; Math.round(t * e) / e !== t;) e *= 10, i++;
    return i;
  }
  function Bt(t, e) {
    var i = e.x - t.x,
      s = e.y - t.y,
      n = Math.sqrt(i * i + s * s);
    var o = Math.atan2(s, i);
    return o < -.5 * _t && (o += yt), {
      angle: o,
      distance: n
    };
  }
  function Vt(t, e) {
    return Math.sqrt(Math.pow(e.x - t.x, 2) + Math.pow(e.y - t.y, 2));
  }
  function Wt(t, e) {
    return (t - e + vt) % yt - _t;
  }
  function Nt(t) {
    return (t % yt + yt) % yt;
  }
  function Ht(t, e, i, s) {
    var n = Nt(t),
      o = Nt(e),
      a = Nt(i),
      r = Nt(o - n),
      l = Nt(a - n),
      h = Nt(n - o),
      c = Nt(n - a);
    return n === o || n === a || s && o === a || r > l && h < c;
  }
  function jt(t, e, i) {
    return Math.max(e, Math.min(i, t));
  }
  function $t(t) {
    return jt(t, -32768, 32767);
  }
  function Yt(t, e, i) {
    var s = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : 1e-6;
    return t >= Math.min(e, i) - s && t <= Math.max(e, i) + s;
  }
  function Ut(t) {
    return !t || $(t.size) || $(t.family) ? null : (t.style ? t.style + " " : "") + (t.weight ? t.weight + " " : "") + t.size + "px " + t.family;
  }
  function Xt(t, e, i, s, n) {
    var o = e[n];
    return o || (o = e[n] = t.measureText(n).width, i.push(n)), o > s && (s = o), s;
  }
  function qt(t, e, i, s) {
    var n = (s = s || {}).data = s.data || {},
      o = s.garbageCollect = s.garbageCollect || [];
    s.font !== e && (n = s.data = {}, o = s.garbageCollect = [], s.font = e), t.save(), t.font = e;
    var a = 0;
    var r = i.length;
    var l, h, c, d, u;
    for (l = 0; l < r; l++) if (d = i[l], null != d && !0 !== Y(d)) a = Xt(t, n, o, a, d);else if (Y(d)) for (h = 0, c = d.length; h < c; h++) u = d[h], null == u || Y(u) || (a = Xt(t, n, o, a, u));
    t.restore();
    var f = o.length / 2;
    if (f > i.length) {
      for (l = 0; l < f; l++) delete n[o[l]];
      o.splice(0, f);
    }
    return a;
  }
  function Kt(t, e, i) {
    var s = t.currentDevicePixelRatio,
      n = 0 !== i ? Math.max(i / 2, .5) : 0;
    return Math.round((e - n) * s) / s + n;
  }
  function Gt(t, e) {
    (e = e || t.getContext("2d")).save(), e.resetTransform(), e.clearRect(0, 0, t.width, t.height), e.restore();
  }
  function Zt(t, e, i, s) {
    var n, o, a, r, l;
    var h = e.pointStyle,
      c = e.rotation,
      d = e.radius;
    var u = (c || 0) * Mt;
    if (h && "object" == _typeof(h) && (n = h.toString(), "[object HTMLImageElement]" === n || "[object HTMLCanvasElement]" === n)) return t.save(), t.translate(i, s), t.rotate(u), t.drawImage(h, -h.width / 2, -h.height / 2, h.width, h.height), void t.restore();
    if (!(isNaN(d) || d <= 0)) {
      switch (t.beginPath(), h) {
        default:
          t.arc(i, s, d, 0, yt), t.closePath();
          break;
        case "triangle":
          t.moveTo(i + Math.sin(u) * d, s - Math.cos(u) * d), u += Pt, t.lineTo(i + Math.sin(u) * d, s - Math.cos(u) * d), u += Pt, t.lineTo(i + Math.sin(u) * d, s - Math.cos(u) * d), t.closePath();
          break;
        case "rectRounded":
          l = .516 * d, r = d - l, o = Math.cos(u + St) * r, a = Math.sin(u + St) * r, t.arc(i - o, s - a, l, u - _t, u - kt), t.arc(i + a, s - o, l, u - kt, u), t.arc(i + o, s + a, l, u, u + kt), t.arc(i - a, s + o, l, u + kt, u + _t), t.closePath();
          break;
        case "rect":
          if (!c) {
            r = Math.SQRT1_2 * d, t.rect(i - r, s - r, 2 * r, 2 * r);
            break;
          }
          u += St;
        case "rectRot":
          o = Math.cos(u) * d, a = Math.sin(u) * d, t.moveTo(i - o, s - a), t.lineTo(i + a, s - o), t.lineTo(i + o, s + a), t.lineTo(i - a, s + o), t.closePath();
          break;
        case "crossRot":
          u += St;
        case "cross":
          o = Math.cos(u) * d, a = Math.sin(u) * d, t.moveTo(i - o, s - a), t.lineTo(i + o, s + a), t.moveTo(i + a, s - o), t.lineTo(i - a, s + o);
          break;
        case "star":
          o = Math.cos(u) * d, a = Math.sin(u) * d, t.moveTo(i - o, s - a), t.lineTo(i + o, s + a), t.moveTo(i + a, s - o), t.lineTo(i - a, s + o), u += St, o = Math.cos(u) * d, a = Math.sin(u) * d, t.moveTo(i - o, s - a), t.lineTo(i + o, s + a), t.moveTo(i + a, s - o), t.lineTo(i - a, s + o);
          break;
        case "line":
          o = Math.cos(u) * d, a = Math.sin(u) * d, t.moveTo(i - o, s - a), t.lineTo(i + o, s + a);
          break;
        case "dash":
          t.moveTo(i, s), t.lineTo(i + Math.cos(u) * d, s + Math.sin(u) * d);
      }
      t.fill(), e.borderWidth > 0 && t.stroke();
    }
  }
  function Jt(t, e, i) {
    return i = i || .5, !e || t && t.x > e.left - i && t.x < e.right + i && t.y > e.top - i && t.y < e.bottom + i;
  }
  function Qt(t, e) {
    t.save(), t.beginPath(), t.rect(e.left, e.top, e.right - e.left, e.bottom - e.top), t.clip();
  }
  function te(t) {
    t.restore();
  }
  function ee(t, e, i, s, n) {
    if (!e) return t.lineTo(i.x, i.y);
    if ("middle" === n) {
      var _s9 = (e.x + i.x) / 2;
      t.lineTo(_s9, e.y), t.lineTo(_s9, i.y);
    } else "after" === n != !!s ? t.lineTo(e.x, i.y) : t.lineTo(i.x, e.y);
    t.lineTo(i.x, i.y);
  }
  function ie(t, e, i, s) {
    if (!e) return t.lineTo(i.x, i.y);
    t.bezierCurveTo(s ? e.cp1x : e.cp2x, s ? e.cp1y : e.cp2y, s ? i.cp2x : i.cp1x, s ? i.cp2y : i.cp1y, i.x, i.y);
  }
  function se(t, e, i, s, n) {
    var o = arguments.length > 5 && arguments[5] !== undefined ? arguments[5] : {};
    var a = Y(e) ? e : [e],
      r = o.strokeWidth > 0 && "" !== o.strokeColor;
    var l, h;
    for (t.save(), t.font = n.string, function (t, e) {
      e.translation && t.translate(e.translation[0], e.translation[1]);
      $(e.rotation) || t.rotate(e.rotation);
      e.color && (t.fillStyle = e.color);
      e.textAlign && (t.textAlign = e.textAlign);
      e.textBaseline && (t.textBaseline = e.textBaseline);
    }(t, o), l = 0; l < a.length; ++l) h = a[l], r && (o.strokeColor && (t.strokeStyle = o.strokeColor), $(o.strokeWidth) || (t.lineWidth = o.strokeWidth), t.strokeText(h, i, s, o.maxWidth)), t.fillText(h, i, s, o.maxWidth), ne(t, i, s, h, o), s += n.lineHeight;
    t.restore();
  }
  function ne(t, e, i, s, n) {
    if (n.strikethrough || n.underline) {
      var _o3 = t.measureText(s),
        _a5 = e - _o3.actualBoundingBoxLeft,
        _r2 = e + _o3.actualBoundingBoxRight,
        _l2 = i - _o3.actualBoundingBoxAscent,
        _h2 = i + _o3.actualBoundingBoxDescent,
        _c = n.strikethrough ? (_l2 + _h2) / 2 : _h2;
      t.strokeStyle = t.fillStyle, t.beginPath(), t.lineWidth = n.decorationWidth || 2, t.moveTo(_a5, _c), t.lineTo(_r2, _c), t.stroke();
    }
  }
  function oe(t, e) {
    var i = e.x,
      s = e.y,
      n = e.w,
      o = e.h,
      a = e.radius;
    t.arc(i + a.topLeft, s + a.topLeft, a.topLeft, -kt, _t, !0), t.lineTo(i, s + o - a.bottomLeft), t.arc(i + a.bottomLeft, s + o - a.bottomLeft, a.bottomLeft, _t, kt, !0), t.lineTo(i + n - a.bottomRight, s + o), t.arc(i + n - a.bottomRight, s + o - a.bottomRight, a.bottomRight, kt, 0, !0), t.lineTo(i + n, s + a.topRight), t.arc(i + n - a.topRight, s + a.topRight, a.topRight, 0, -kt, !0), t.lineTo(i + a.topLeft, s);
  }
  function ae(t, e, i) {
    i = i || function (i) {
      return t[i] < e;
    };
    var s,
      n = t.length - 1,
      o = 0;
    for (; n - o > 1;) s = o + n >> 1, i(s) ? o = s : n = s;
    return {
      lo: o,
      hi: n
    };
  }
  var re = function re(t, e, i) {
      return ae(t, i, function (s) {
        return t[s][e] < i;
      });
    },
    le = function le(t, e, i) {
      return ae(t, i, function (s) {
        return t[s][e] >= i;
      });
    };
  function he(t, e, i) {
    var s = 0,
      n = t.length;
    for (; s < n && t[s] < e;) s++;
    for (; n > s && t[n - 1] > i;) n--;
    return s > 0 || n < t.length ? t.slice(s, n) : t;
  }
  var ce = ["push", "pop", "shift", "splice", "unshift"];
  function de(t, e) {
    t._chartjs ? t._chartjs.listeners.push(e) : (Object.defineProperty(t, "_chartjs", {
      configurable: !0,
      enumerable: !1,
      value: {
        listeners: [e]
      }
    }), ce.forEach(function (e) {
      var i = "_onData" + ht(e),
        s = t[e];
      Object.defineProperty(t, e, {
        configurable: !0,
        enumerable: !1,
        value: function value() {
          for (var _len3 = arguments.length, e = new Array(_len3), _key3 = 0; _key3 < _len3; _key3++) {
            e[_key3] = arguments[_key3];
          }
          var n = s.apply(this, e);
          return t._chartjs.listeners.forEach(function (t) {
            "function" == typeof t[i] && t[i].apply(t, e);
          }), n;
        }
      });
    }));
  }
  function ue(t, e) {
    var i = t._chartjs;
    if (!i) return;
    var s = i.listeners,
      n = s.indexOf(e);
    -1 !== n && s.splice(n, 1), s.length > 0 || (ce.forEach(function (e) {
      delete t[e];
    }), delete t._chartjs);
  }
  function fe(t) {
    var e = new Set();
    var i, s;
    for (i = 0, s = t.length; i < s; ++i) e.add(t[i]);
    return e.size === s ? t : Array.from(e);
  }
  function ge() {
    return "undefined" != typeof window && "undefined" != typeof document;
  }
  function pe(t) {
    var e = t.parentNode;
    return e && "[object ShadowRoot]" === e.toString() && (e = e.host), e;
  }
  function me(t, e, i) {
    var s;
    return "string" == typeof t ? (s = parseInt(t, 10), -1 !== t.indexOf("%") && (s = s / 100 * e.parentNode[i])) : s = t, s;
  }
  var xe = function xe(t) {
    return window.getComputedStyle(t, null);
  };
  function be(t, e) {
    return xe(t).getPropertyValue(e);
  }
  var _e = ["top", "right", "bottom", "left"];
  function ye(t, e, i) {
    var s = {};
    i = i ? "-" + i : "";
    for (var _n5 = 0; _n5 < 4; _n5++) {
      var _o4 = _e[_n5];
      s[_o4] = parseFloat(t[e + "-" + _o4 + i]) || 0;
    }
    return s.width = s.left + s.right, s.height = s.top + s.bottom, s;
  }
  function ve(t, e) {
    var i = e.canvas,
      s = e.currentDevicePixelRatio,
      n = xe(i),
      o = "border-box" === n.boxSizing,
      a = ye(n, "padding"),
      r = ye(n, "border", "width"),
      _ref = function (t, e) {
        var i = t["native"] || t,
          s = i.touches,
          n = s && s.length ? s[0] : i,
          o = n.offsetX,
          a = n.offsetY;
        var r,
          l,
          h = !1;
        if (function (t, e, i) {
          return (t > 0 || e > 0) && (!i || !i.shadowRoot);
        }(o, a, i.target)) r = o, l = a;else {
          var _t3 = e.getBoundingClientRect();
          r = n.clientX - _t3.left, l = n.clientY - _t3.top, h = !0;
        }
        return {
          x: r,
          y: l,
          box: h
        };
      }(t, i),
      l = _ref.x,
      h = _ref.y,
      c = _ref.box,
      d = a.left + (c && r.left),
      u = a.top + (c && r.top);
    var f = e.width,
      g = e.height;
    return o && (f -= a.width + r.width, g -= a.height + r.height), {
      x: Math.round((l - d) / f * i.width / s),
      y: Math.round((h - u) / g * i.height / s)
    };
  }
  var we = function we(t) {
    return Math.round(10 * t) / 10;
  };
  function Me(t, e, i, s) {
    var n = xe(t),
      o = ye(n, "margin"),
      a = me(n.maxWidth, t, "clientWidth") || wt,
      r = me(n.maxHeight, t, "clientHeight") || wt,
      l = function (t, e, i) {
        var s, n;
        if (void 0 === e || void 0 === i) {
          var _o5 = pe(t);
          if (_o5) {
            var _t4 = _o5.getBoundingClientRect(),
              _a6 = xe(_o5),
              _r3 = ye(_a6, "border", "width"),
              _l3 = ye(_a6, "padding");
            e = _t4.width - _l3.width - _r3.width, i = _t4.height - _l3.height - _r3.height, s = me(_a6.maxWidth, _o5, "clientWidth"), n = me(_a6.maxHeight, _o5, "clientHeight");
          } else e = t.clientWidth, i = t.clientHeight;
        }
        return {
          width: e,
          height: i,
          maxWidth: s || wt,
          maxHeight: n || wt
        };
      }(t, e, i);
    var h = l.width,
      c = l.height;
    if ("content-box" === n.boxSizing) {
      var _t5 = ye(n, "border", "width"),
        _e4 = ye(n, "padding");
      h -= _e4.width + _t5.width, c -= _e4.height + _t5.height;
    }
    return h = Math.max(0, h - o.width), c = Math.max(0, s ? Math.floor(h / s) : c - o.height), h = we(Math.min(h, a, l.maxWidth)), c = we(Math.min(c, r, l.maxHeight)), h && !c && (c = we(h / 2)), {
      width: h,
      height: c
    };
  }
  function ke(t, e, i) {
    var s = e || 1,
      n = Math.floor(t.height * s),
      o = Math.floor(t.width * s);
    t.height = n / s, t.width = o / s;
    var a = t.canvas;
    return a.style && (i || !a.style.height && !a.style.width) && (a.style.height = "".concat(t.height, "px"), a.style.width = "".concat(t.width, "px")), (t.currentDevicePixelRatio !== s || a.height !== n || a.width !== o) && (t.currentDevicePixelRatio = s, a.height = n, a.width = o, t.ctx.setTransform(s, 0, 0, s, 0, 0), !0);
  }
  var Se = function () {
    var t = !1;
    try {
      var _e5 = {
        get passive() {
          return t = !0, !1;
        }
      };
      window.addEventListener("test", null, _e5), window.removeEventListener("test", null, _e5);
    } catch (t) {}
    return t;
  }();
  function Pe(t, e) {
    var i = be(t, e),
      s = i && i.match(/^(\d+)(\.\d+)?px$/);
    return s ? +s[1] : void 0;
  }
  function De(t, e) {
    return "native" in t ? {
      x: t.x,
      y: t.y
    } : ve(t, e);
  }
  function Ce(t, e, i, s) {
    var n = t.controller,
      o = t.data,
      a = t._sorted,
      r = n._cachedMeta.iScale;
    if (r && e === r.axis && "r" !== e && a && o.length) {
      var _t6 = r._reversePixels ? le : re;
      if (!s) return _t6(o, e, i);
      if (n._sharedOptions) {
        var _s10 = o[0],
          _n6 = "function" == typeof _s10.getRange && _s10.getRange(e);
        if (_n6) {
          var _s11 = _t6(o, e, i - _n6),
            _a7 = _t6(o, e, i + _n6);
          return {
            lo: _s11.lo,
            hi: _a7.hi
          };
        }
      }
    }
    return {
      lo: 0,
      hi: o.length - 1
    };
  }
  function Oe(t, e, i, s, n) {
    var o = t.getSortedVisibleDatasetMetas(),
      a = i[e];
    for (var _t7 = 0, _i4 = o.length; _t7 < _i4; ++_t7) {
      var _o$_t = o[_t7],
        _i5 = _o$_t.index,
        _r4 = _o$_t.data,
        _Ce = Ce(o[_t7], e, a, n),
        _l4 = _Ce.lo,
        _h3 = _Ce.hi;
      for (var _t8 = _l4; _t8 <= _h3; ++_t8) {
        var _e6 = _r4[_t8];
        _e6.skip || s(_e6, _i5, _t8);
      }
    }
  }
  function Ae(t, e, i, s) {
    var n = [];
    if (!Jt(e, t.chartArea, t._minPadding)) return n;
    return Oe(t, i, e, function (t, i, o) {
      t.inRange(e.x, e.y, s) && n.push({
        element: t,
        datasetIndex: i,
        index: o
      });
    }, !0), n;
  }
  function Te(t, e, i, s, n) {
    var o = [];
    var a = function (t) {
      var e = -1 !== t.indexOf("x"),
        i = -1 !== t.indexOf("y");
      return function (t, s) {
        var n = e ? Math.abs(t.x - s.x) : 0,
          o = i ? Math.abs(t.y - s.y) : 0;
        return Math.sqrt(Math.pow(n, 2) + Math.pow(o, 2));
      };
    }(i);
    var r = Number.POSITIVE_INFINITY;
    return Oe(t, i, e, function (i, l, h) {
      var c = i.inRange(e.x, e.y, n);
      if (s && !c) return;
      var d = i.getCenterPoint(n);
      if (!Jt(d, t.chartArea, t._minPadding) && !c) return;
      var u = a(e, d);
      u < r ? (o = [{
        element: i,
        datasetIndex: l,
        index: h
      }], r = u) : u === r && o.push({
        element: i,
        datasetIndex: l,
        index: h
      });
    }), o;
  }
  function Le(t, e, i, s, n) {
    return Jt(e, t.chartArea, t._minPadding) ? "r" !== i || s ? Te(t, e, i, s, n) : function (t, e, i, s) {
      var n = [];
      return Oe(t, i, e, function (t, i, o) {
        var _t$getProps = t.getProps(["startAngle", "endAngle"], s),
          a = _t$getProps.startAngle,
          r = _t$getProps.endAngle,
          _Bt = Bt(t, {
            x: e.x,
            y: e.y
          }),
          l = _Bt.angle;
        Ht(l, a, r) && n.push({
          element: t,
          datasetIndex: i,
          index: o
        });
      }), n;
    }(t, e, i, n) : [];
  }
  function Re(t, e, i, s) {
    var n = De(e, t),
      o = [],
      a = i.axis,
      r = "x" === a ? "inXRange" : "inYRange";
    var l = !1;
    return function (t, e) {
      var i = t.getSortedVisibleDatasetMetas();
      var s, n, o;
      for (var _t9 = 0, _a8 = i.length; _t9 < _a8; ++_t9) {
        var _i$_t = i[_t9];
        s = _i$_t.index;
        n = _i$_t.data;
        for (var _t10 = 0, _i6 = n.length; _t10 < _i6; ++_t10) o = n[_t10], o.skip || e(o, s, _t10);
      }
    }(t, function (t, e, i) {
      t[r](n[a], s) && o.push({
        element: t,
        datasetIndex: e,
        index: i
      }), t.inRange(n.x, n.y, s) && (l = !0);
    }), i.intersect && !l ? [] : o;
  }
  var Ee = {
    modes: {
      index: function index(t, e, i, s) {
        var n = De(e, t),
          o = i.axis || "x",
          a = i.intersect ? Ae(t, n, o, s) : Le(t, n, o, !1, s),
          r = [];
        return a.length ? (t.getSortedVisibleDatasetMetas().forEach(function (t) {
          var e = a[0].index,
            i = t.data[e];
          i && !i.skip && r.push({
            element: i,
            datasetIndex: t.index,
            index: e
          });
        }), r) : [];
      },
      dataset: function dataset(t, e, i, s) {
        var n = De(e, t),
          o = i.axis || "xy";
        var a = i.intersect ? Ae(t, n, o, s) : Le(t, n, o, !1, s);
        if (a.length > 0) {
          var _e7 = a[0].datasetIndex,
            _i7 = t.getDatasetMeta(_e7).data;
          a = [];
          for (var _t11 = 0; _t11 < _i7.length; ++_t11) a.push({
            element: _i7[_t11],
            datasetIndex: _e7,
            index: _t11
          });
        }
        return a;
      },
      point: function point(t, e, i, s) {
        return Ae(t, De(e, t), i.axis || "xy", s);
      },
      nearest: function nearest(t, e, i, s) {
        return Le(t, De(e, t), i.axis || "xy", i.intersect, s);
      },
      x: function x(t, e, i, s) {
        return Re(t, e, {
          axis: "x",
          intersect: i.intersect
        }, s);
      },
      y: function y(t, e, i, s) {
        return Re(t, e, {
          axis: "y",
          intersect: i.intersect
        }, s);
      }
    }
  };
  var Ie = new RegExp(/^(normal|(\d+(?:\.\d+)?)(px|em|%)?)$/),
    ze = new RegExp(/^(normal|italic|initial|inherit|unset|(oblique( -?[0-9]?[0-9]deg)?))$/);
  function Fe(t, e) {
    var i = ("" + t).match(Ie);
    if (!i || "normal" === i[1]) return 1.2 * e;
    switch (t = +i[2], i[3]) {
      case "px":
        return t;
      case "%":
        t /= 100;
    }
    return e * t;
  }
  function Be(t, e) {
    var i = {},
      s = U(e),
      n = s ? Object.keys(e) : e,
      o = U(t) ? s ? function (i) {
        return K(t[i], t[e[i]]);
      } : function (e) {
        return t[e];
      } : function () {
        return t;
      };
    var _iterator2 = _createForOfIteratorHelper(n),
      _step2;
    try {
      for (_iterator2.s(); !(_step2 = _iterator2.n()).done;) {
        var _t12 = _step2.value;
        i[_t12] = +o(_t12) || 0;
      }
    } catch (err) {
      _iterator2.e(err);
    } finally {
      _iterator2.f();
    }
    return i;
  }
  function Ve(t) {
    return Be(t, {
      top: "y",
      right: "x",
      bottom: "y",
      left: "x"
    });
  }
  function We(t) {
    return Be(t, ["topLeft", "topRight", "bottomLeft", "bottomRight"]);
  }
  function Ne(t) {
    var e = Ve(t);
    return e.width = e.left + e.right, e.height = e.top + e.bottom, e;
  }
  function He(t, e) {
    t = t || {}, e = e || bt.font;
    var i = K(t.size, e.size);
    "string" == typeof i && (i = parseInt(i, 10));
    var s = K(t.style, e.style);
    s && !("" + s).match(ze) && (console.warn('Invalid font style specified: "' + s + '"'), s = "");
    var n = {
      family: K(t.family, e.family),
      lineHeight: Fe(K(t.lineHeight, e.lineHeight), i),
      size: i,
      style: s,
      weight: K(t.weight, e.weight),
      string: ""
    };
    return n.string = Ut(n), n;
  }
  function je(t, e, i, s) {
    var n,
      o,
      a,
      r = !0;
    for (n = 0, o = t.length; n < o; ++n) if (a = t[n], void 0 !== a && (void 0 !== e && "function" == typeof a && (a = a(e), r = !1), void 0 !== i && Y(a) && (a = a[i % a.length], r = !1), void 0 !== a)) return s && !r && (s.cacheable = !1), a;
  }
  function $e(t, e, i) {
    var s = t.min,
      n = t.max,
      o = Z(e, (n - s) / 2),
      a = function a(t, e) {
        return i && 0 === t ? 0 : t + e;
      };
    return {
      min: a(s, -Math.abs(o)),
      max: a(n, o)
    };
  }
  function Ye(t, e) {
    return Object.assign(Object.create(t), e);
  }
  var Ue = ["left", "top", "right", "bottom"];
  function Xe(t, e) {
    return t.filter(function (t) {
      return t.pos === e;
    });
  }
  function qe(t, e) {
    return t.filter(function (t) {
      return -1 === Ue.indexOf(t.pos) && t.box.axis === e;
    });
  }
  function Ke(t, e) {
    return t.sort(function (t, i) {
      var s = e ? i : t,
        n = e ? t : i;
      return s.weight === n.weight ? s.index - n.index : s.weight - n.weight;
    });
  }
  function Ge(t, e) {
    var i = function (t) {
        var e = {};
        var _iterator3 = _createForOfIteratorHelper(t),
          _step3;
        try {
          for (_iterator3.s(); !(_step3 = _iterator3.n()).done;) {
            var _i8 = _step3.value;
            var _t13 = _i8.stack,
              _s12 = _i8.pos,
              _n7 = _i8.stackWeight;
            if (!_t13 || !Ue.includes(_s12)) continue;
            var _o6 = e[_t13] || (e[_t13] = {
              count: 0,
              placed: 0,
              weight: 0,
              size: 0
            });
            _o6.count++, _o6.weight += _n7;
          }
        } catch (err) {
          _iterator3.e(err);
        } finally {
          _iterator3.f();
        }
        return e;
      }(t),
      s = e.vBoxMaxWidth,
      n = e.hBoxMaxHeight;
    var o, a, r;
    for (o = 0, a = t.length; o < a; ++o) {
      r = t[o];
      var _a9 = r.box.fullSize,
        _l5 = i[r.stack],
        _h4 = _l5 && r.stackWeight / _l5.weight;
      r.horizontal ? (r.width = _h4 ? _h4 * s : _a9 && e.availableWidth, r.height = n) : (r.width = s, r.height = _h4 ? _h4 * n : _a9 && e.availableHeight);
    }
    return i;
  }
  function Ze(t, e, i, s) {
    return Math.max(t[i], e[i]) + Math.max(t[s], e[s]);
  }
  function Je(t, e) {
    t.top = Math.max(t.top, e.top), t.left = Math.max(t.left, e.left), t.bottom = Math.max(t.bottom, e.bottom), t.right = Math.max(t.right, e.right);
  }
  function Qe(t, e, i, s) {
    var n = i.pos,
      o = i.box,
      a = t.maxPadding;
    if (!U(n)) {
      i.size && (t[n] -= i.size);
      var _e8 = s[i.stack] || {
        size: 0,
        count: 1
      };
      _e8.size = Math.max(_e8.size, i.horizontal ? o.height : o.width), i.size = _e8.size / _e8.count, t[n] += i.size;
    }
    o.getPadding && Je(a, o.getPadding());
    var r = Math.max(0, e.outerWidth - Ze(a, t, "left", "right")),
      l = Math.max(0, e.outerHeight - Ze(a, t, "top", "bottom")),
      h = r !== t.w,
      c = l !== t.h;
    return t.w = r, t.h = l, i.horizontal ? {
      same: h,
      other: c
    } : {
      same: c,
      other: h
    };
  }
  function ti(t, e) {
    var i = e.maxPadding;
    function s(t) {
      var s = {
        left: 0,
        top: 0,
        right: 0,
        bottom: 0
      };
      return t.forEach(function (t) {
        s[t] = Math.max(e[t], i[t]);
      }), s;
    }
    return s(t ? ["left", "right"] : ["top", "bottom"]);
  }
  function ei(t, e, i, s) {
    var n = [];
    var o, a, r, l, h, c;
    for (o = 0, a = t.length, h = 0; o < a; ++o) {
      r = t[o], l = r.box, l.update(r.width || e.w, r.height || e.h, ti(r.horizontal, e));
      var _Qe = Qe(e, i, r, s),
        _a10 = _Qe.same,
        _d = _Qe.other;
      h |= _a10 && n.length, c = c || _d, l.fullSize || n.push(r);
    }
    return h && ei(n, e, i, s) || c;
  }
  function ii(t, e, i, s, n) {
    t.top = i, t.left = e, t.right = e + s, t.bottom = i + n, t.width = s, t.height = n;
  }
  function si(t, e, i, s) {
    var n = i.padding;
    var o = e.x,
      a = e.y;
    var _iterator4 = _createForOfIteratorHelper(t),
      _step4;
    try {
      for (_iterator4.s(); !(_step4 = _iterator4.n()).done;) {
        var _r5 = _step4.value;
        var _t14 = _r5.box,
          _l6 = s[_r5.stack] || {
            count: 1,
            placed: 0,
            weight: 1
          },
          _h5 = _r5.stackWeight / _l6.weight || 1;
        if (_r5.horizontal) {
          var _s13 = e.w * _h5,
            _o7 = _l6.size || _t14.height;
          ct(_l6.start) && (a = _l6.start), _t14.fullSize ? ii(_t14, n.left, a, i.outerWidth - n.right - n.left, _o7) : ii(_t14, e.left + _l6.placed, a, _s13, _o7), _l6.start = a, _l6.placed += _s13, a = _t14.bottom;
        } else {
          var _s14 = e.h * _h5,
            _a11 = _l6.size || _t14.width;
          ct(_l6.start) && (o = _l6.start), _t14.fullSize ? ii(_t14, o, n.top, _a11, i.outerHeight - n.bottom - n.top) : ii(_t14, o, e.top + _l6.placed, _a11, _s14), _l6.start = o, _l6.placed += _s14, o = _t14.right;
        }
      }
    } catch (err) {
      _iterator4.e(err);
    } finally {
      _iterator4.f();
    }
    e.x = o, e.y = a;
  }
  bt.set("layout", {
    autoPadding: !0,
    padding: {
      top: 0,
      right: 0,
      bottom: 0,
      left: 0
    }
  });
  var ni = {
    addBox: function addBox(t, e) {
      t.boxes || (t.boxes = []), e.fullSize = e.fullSize || !1, e.position = e.position || "top", e.weight = e.weight || 0, e._layers = e._layers || function () {
        return [{
          z: 0,
          draw: function draw(t) {
            e.draw(t);
          }
        }];
      }, t.boxes.push(e);
    },
    removeBox: function removeBox(t, e) {
      var i = t.boxes ? t.boxes.indexOf(e) : -1;
      -1 !== i && t.boxes.splice(i, 1);
    },
    configure: function configure(t, e, i) {
      e.fullSize = i.fullSize, e.position = i.position, e.weight = i.weight;
    },
    update: function update(t, e, i, s) {
      if (!t) return;
      var n = Ne(t.options.layout.padding),
        o = Math.max(e - n.width, 0),
        a = Math.max(i - n.height, 0),
        r = function (t) {
          var e = function (t) {
              var e = [];
              var i, s, n, o, a, r;
              for (i = 0, s = (t || []).length; i < s; ++i) {
                var _n8, _n8$options, _n8$options$stackWeig;
                n = t[i], (_n8 = n, o = _n8.position, _n8$options = _n8.options, a = _n8$options.stack, _n8$options$stackWeig = _n8$options.stackWeight, r = _n8$options$stackWeig === void 0 ? 1 : _n8$options$stackWeig), e.push({
                  index: i,
                  box: n,
                  pos: o,
                  horizontal: n.isHorizontal(),
                  weight: n.weight,
                  stack: a && o + a,
                  stackWeight: r
                });
              }
              return e;
            }(t),
            i = Ke(e.filter(function (t) {
              return t.box.fullSize;
            }), !0),
            s = Ke(Xe(e, "left"), !0),
            n = Ke(Xe(e, "right")),
            o = Ke(Xe(e, "top"), !0),
            a = Ke(Xe(e, "bottom")),
            r = qe(e, "x"),
            l = qe(e, "y");
          return {
            fullSize: i,
            leftAndTop: s.concat(o),
            rightAndBottom: n.concat(l).concat(a).concat(r),
            chartArea: Xe(e, "chartArea"),
            vertical: s.concat(n).concat(l),
            horizontal: o.concat(a).concat(r)
          };
        }(t.boxes),
        l = r.vertical,
        h = r.horizontal;
      Q(t.boxes, function (t) {
        "function" == typeof t.beforeLayout && t.beforeLayout();
      });
      var c = l.reduce(function (t, e) {
          return e.box.options && !1 === e.box.options.display ? t : t + 1;
        }, 0) || 1,
        d = Object.freeze({
          outerWidth: e,
          outerHeight: i,
          padding: n,
          availableWidth: o,
          availableHeight: a,
          vBoxMaxWidth: o / 2 / c,
          hBoxMaxHeight: a / 2
        }),
        u = Object.assign({}, n);
      Je(u, Ne(s));
      var f = Object.assign({
          maxPadding: u,
          w: o,
          h: a,
          x: n.left,
          y: n.top
        }, n),
        g = Ge(l.concat(h), d);
      ei(r.fullSize, f, d, g), ei(l, f, d, g), ei(h, f, d, g) && ei(l, f, d, g), function (t) {
        var e = t.maxPadding;
        function i(i) {
          var s = Math.max(e[i] - t[i], 0);
          return t[i] += s, s;
        }
        t.y += i("top"), t.x += i("left"), i("right"), i("bottom");
      }(f), si(r.leftAndTop, f, d, g), f.x += f.w, f.y += f.h, si(r.rightAndBottom, f, d, g), t.chartArea = {
        left: f.left,
        top: f.top,
        right: f.left + f.w,
        bottom: f.top + f.h,
        height: f.h,
        width: f.w
      }, Q(r.chartArea, function (e) {
        var i = e.box;
        Object.assign(i, t.chartArea), i.update(f.w, f.h, {
          left: 0,
          top: 0,
          right: 0,
          bottom: 0
        });
      });
    }
  };
  function oi(t) {
    var e = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : [""];
    var i = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : t;
    var s = arguments.length > 3 ? arguments[3] : undefined;
    var n = arguments.length > 4 && arguments[4] !== undefined ? arguments[4] : function () {
      return t[0];
    };
    ct(s) || (s = mi("_fallback", t));
    var o = _defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty({}, Symbol.toStringTag, "Object"), "_cacheable", !0), "_scopes", t), "_rootScopes", i), "_fallback", s), "_getTarget", n), "override", function override(n) {
      return oi([n].concat(_toConsumableArray(t)), e, i, s);
    });
    return new Proxy(o, {
      deleteProperty: function deleteProperty(e, i) {
        return delete e[i], delete e._keys, delete t[0][i], !0;
      },
      get: function get(i, s) {
        return ci(i, s, function () {
          return function (t, e, i, s) {
            var n;
            var _iterator5 = _createForOfIteratorHelper(e),
              _step5;
            try {
              for (_iterator5.s(); !(_step5 = _iterator5.n()).done;) {
                var _o9 = _step5.value;
                if (n = mi(li(_o9, t), i), ct(n)) return hi(t, n) ? gi(i, s, t, n) : n;
              }
            } catch (err) {
              _iterator5.e(err);
            } finally {
              _iterator5.f();
            }
          }(s, e, t, i);
        });
      },
      getOwnPropertyDescriptor: function getOwnPropertyDescriptor(t, e) {
        return Reflect.getOwnPropertyDescriptor(t._scopes[0], e);
      },
      getPrototypeOf: function getPrototypeOf() {
        return Reflect.getPrototypeOf(t[0]);
      },
      has: function has(t, e) {
        return xi(t).includes(e);
      },
      ownKeys: function ownKeys(t) {
        return xi(t);
      },
      set: function set(t, e, i) {
        var s = t._storage || (t._storage = n());
        return t[e] = s[e] = i, delete t._keys, !0;
      }
    });
  }
  function ai(t, e, i, s) {
    var n = {
      _cacheable: !1,
      _proxy: t,
      _context: e,
      _subProxy: i,
      _stack: new Set(),
      _descriptors: ri(t, s),
      setContext: function setContext(e) {
        return ai(t, e, i, s);
      },
      override: function override(n) {
        return ai(t.override(n), e, i, s);
      }
    };
    return new Proxy(n, {
      deleteProperty: function deleteProperty(e, i) {
        return delete e[i], delete t[i], !0;
      },
      get: function get(t, e, i) {
        return ci(t, e, function () {
          return function (t, e, i) {
            var s = t._proxy,
              n = t._context,
              o = t._subProxy,
              a = t._descriptors;
            var r = s[e];
            dt(r) && a.isScriptable(e) && (r = function (t, e, i, s) {
              var n = i._proxy,
                o = i._context,
                a = i._subProxy,
                r = i._stack;
              if (r.has(t)) throw new Error("Recursion detected: " + Array.from(r).join("->") + "->" + t);
              r.add(t), e = e(o, a || s), r["delete"](t), hi(t, e) && (e = gi(n._scopes, n, t, e));
              return e;
            }(e, r, t, i));
            Y(r) && r.length && (r = function (t, e, i, s) {
              var n = i._proxy,
                o = i._context,
                a = i._subProxy,
                r = i._descriptors;
              if (ct(o.index) && s(t)) e = e[o.index % e.length];else if (U(e[0])) {
                var _i9 = e,
                  _s15 = n._scopes.filter(function (t) {
                    return t !== _i9;
                  });
                e = [];
                var _iterator6 = _createForOfIteratorHelper(_i9),
                  _step6;
                try {
                  for (_iterator6.s(); !(_step6 = _iterator6.n()).done;) {
                    var _l7 = _step6.value;
                    var _i10 = gi(_s15, n, t, _l7);
                    e.push(ai(_i10, o, a && a[t], r));
                  }
                } catch (err) {
                  _iterator6.e(err);
                } finally {
                  _iterator6.f();
                }
              }
              return e;
            }(e, r, t, a.isIndexable));
            hi(e, r) && (r = ai(r, n, o && o[e], a));
            return r;
          }(t, e, i);
        });
      },
      getOwnPropertyDescriptor: function getOwnPropertyDescriptor(e, i) {
        return e._descriptors.allKeys ? Reflect.has(t, i) ? {
          enumerable: !0,
          configurable: !0
        } : void 0 : Reflect.getOwnPropertyDescriptor(t, i);
      },
      getPrototypeOf: function getPrototypeOf() {
        return Reflect.getPrototypeOf(t);
      },
      has: function has(e, i) {
        return Reflect.has(t, i);
      },
      ownKeys: function ownKeys() {
        return Reflect.ownKeys(t);
      },
      set: function set(e, i, s) {
        return t[i] = s, delete e[i], !0;
      }
    });
  }
  function ri(t) {
    var e = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {
      scriptable: !0,
      indexable: !0
    };
    var _t$_scriptable = t._scriptable,
      i = _t$_scriptable === void 0 ? e.scriptable : _t$_scriptable,
      _t$_indexable = t._indexable,
      s = _t$_indexable === void 0 ? e.indexable : _t$_indexable,
      _t$_allKeys = t._allKeys,
      n = _t$_allKeys === void 0 ? e.allKeys : _t$_allKeys;
    return {
      allKeys: n,
      scriptable: i,
      indexable: s,
      isScriptable: dt(i) ? i : function () {
        return i;
      },
      isIndexable: dt(s) ? s : function () {
        return s;
      }
    };
  }
  var li = function li(t, e) {
      return t ? t + ht(e) : e;
    },
    hi = function hi(t, e) {
      return U(e) && "adapters" !== t && (null === Object.getPrototypeOf(e) || e.constructor === Object);
    };
  function ci(t, e, i) {
    if (Object.prototype.hasOwnProperty.call(t, e)) return t[e];
    var s = i();
    return t[e] = s, s;
  }
  function di(t, e, i) {
    return dt(t) ? t(e, i) : t;
  }
  var ui = function ui(t, e) {
    return !0 === t ? e : "string" == typeof t ? lt(e, t) : void 0;
  };
  function fi(t, e, i, s, n) {
    var _iterator7 = _createForOfIteratorHelper(e),
      _step7;
    try {
      for (_iterator7.s(); !(_step7 = _iterator7.n()).done;) {
        var _o10 = _step7.value;
        var _e9 = ui(i, _o10);
        if (_e9) {
          t.add(_e9);
          var _o11 = di(_e9._fallback, i, n);
          if (ct(_o11) && _o11 !== i && _o11 !== s) return _o11;
        } else if (!1 === _e9 && ct(s) && i !== s) return null;
      }
    } catch (err) {
      _iterator7.e(err);
    } finally {
      _iterator7.f();
    }
    return !1;
  }
  function gi(t, e, i, s) {
    var n = e._rootScopes,
      o = di(e._fallback, i, s),
      a = [].concat(_toConsumableArray(t), _toConsumableArray(n)),
      r = new Set();
    r.add(s);
    var l = pi(r, a, i, o || i, s);
    return null !== l && (!ct(o) || o === i || (l = pi(r, a, o, l, s), null !== l)) && oi(Array.from(r), [""], n, o, function () {
      return function (t, e, i) {
        var s = t._getTarget();
        e in s || (s[e] = {});
        var n = s[e];
        if (Y(n) && U(i)) return i;
        return n;
      }(e, i, s);
    });
  }
  function pi(t, e, i, s, n) {
    for (; i;) i = fi(t, e, i, s, n);
    return i;
  }
  function mi(t, e) {
    var _iterator8 = _createForOfIteratorHelper(e),
      _step8;
    try {
      for (_iterator8.s(); !(_step8 = _iterator8.n()).done;) {
        var _i11 = _step8.value;
        if (!_i11) continue;
        var _e10 = _i11[t];
        if (ct(_e10)) return _e10;
      }
    } catch (err) {
      _iterator8.e(err);
    } finally {
      _iterator8.f();
    }
  }
  function xi(t) {
    var e = t._keys;
    return e || (e = t._keys = function (t) {
      var e = new Set();
      var _iterator9 = _createForOfIteratorHelper(t),
        _step9;
      try {
        for (_iterator9.s(); !(_step9 = _iterator9.n()).done;) {
          var _i12 = _step9.value;
          var _iterator10 = _createForOfIteratorHelper(Object.keys(_i12).filter(function (t) {
              return !t.startsWith("_");
            })),
            _step10;
          try {
            for (_iterator10.s(); !(_step10 = _iterator10.n()).done;) {
              var _t15 = _step10.value;
              e.add(_t15);
            }
          } catch (err) {
            _iterator10.e(err);
          } finally {
            _iterator10.f();
          }
        }
      } catch (err) {
        _iterator9.e(err);
      } finally {
        _iterator9.f();
      }
      return Array.from(e);
    }(t._scopes)), e;
  }
  var bi = Number.EPSILON || 1e-14,
    _i = function _i(t, e) {
      return e < t.length && !t[e].skip && t[e];
    },
    yi = function yi(t) {
      return "x" === t ? "y" : "x";
    };
  function vi(t, e, i, s) {
    var n = t.skip ? e : t,
      o = e,
      a = i.skip ? e : i,
      r = Vt(o, n),
      l = Vt(a, o);
    var h = r / (r + l),
      c = l / (r + l);
    h = isNaN(h) ? 0 : h, c = isNaN(c) ? 0 : c;
    var d = s * h,
      u = s * c;
    return {
      previous: {
        x: o.x - d * (a.x - n.x),
        y: o.y - d * (a.y - n.y)
      },
      next: {
        x: o.x + u * (a.x - n.x),
        y: o.y + u * (a.y - n.y)
      }
    };
  }
  function wi(t) {
    var e = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : "x";
    var i = yi(e),
      s = t.length,
      n = Array(s).fill(0),
      o = Array(s);
    var a,
      r,
      l,
      h = _i(t, 0);
    for (a = 0; a < s; ++a) if (r = l, l = h, h = _i(t, a + 1), l) {
      if (h) {
        var _t16 = h[e] - l[e];
        n[a] = 0 !== _t16 ? (h[i] - l[i]) / _t16 : 0;
      }
      o[a] = r ? h ? Ct(n[a - 1]) !== Ct(n[a]) ? 0 : (n[a - 1] + n[a]) / 2 : n[a - 1] : n[a];
    }
    !function (t, e, i) {
      var s = t.length;
      var n,
        o,
        a,
        r,
        l,
        h = _i(t, 0);
      for (var _c2 = 0; _c2 < s - 1; ++_c2) l = h, h = _i(t, _c2 + 1), l && h && (Lt(e[_c2], 0, bi) ? i[_c2] = i[_c2 + 1] = 0 : (n = i[_c2] / e[_c2], o = i[_c2 + 1] / e[_c2], r = Math.pow(n, 2) + Math.pow(o, 2), r <= 9 || (a = 3 / Math.sqrt(r), i[_c2] = n * a * e[_c2], i[_c2 + 1] = o * a * e[_c2])));
    }(t, n, o), function (t, e) {
      var i = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : "x";
      var s = yi(i),
        n = t.length;
      var o,
        a,
        r,
        l = _i(t, 0);
      for (var _h6 = 0; _h6 < n; ++_h6) {
        if (a = r, r = l, l = _i(t, _h6 + 1), !r) continue;
        var _n9 = r[i],
          _c3 = r[s];
        a && (o = (_n9 - a[i]) / 3, r["cp1".concat(i)] = _n9 - o, r["cp1".concat(s)] = _c3 - o * e[_h6]), l && (o = (l[i] - _n9) / 3, r["cp2".concat(i)] = _n9 + o, r["cp2".concat(s)] = _c3 + o * e[_h6]);
      }
    }(t, o, e);
  }
  function Mi(t, e, i) {
    return Math.max(Math.min(t, i), e);
  }
  function ki(t, e, i, s, n) {
    var o, a, r, l;
    if (e.spanGaps && (t = t.filter(function (t) {
      return !t.skip;
    })), "monotone" === e.cubicInterpolationMode) wi(t, n);else {
      var _i13 = s ? t[t.length - 1] : t[0];
      for (o = 0, a = t.length; o < a; ++o) r = t[o], l = vi(_i13, r, t[Math.min(o + 1, a - (s ? 0 : 1)) % a], e.tension), r.cp1x = l.previous.x, r.cp1y = l.previous.y, r.cp2x = l.next.x, r.cp2y = l.next.y, _i13 = r;
    }
    e.capBezierPoints && function (t, e) {
      var i,
        s,
        n,
        o,
        a,
        r = Jt(t[0], e);
      for (i = 0, s = t.length; i < s; ++i) a = o, o = r, r = i < s - 1 && Jt(t[i + 1], e), o && (n = t[i], a && (n.cp1x = Mi(n.cp1x, e.left, e.right), n.cp1y = Mi(n.cp1y, e.top, e.bottom)), r && (n.cp2x = Mi(n.cp2x, e.left, e.right), n.cp2y = Mi(n.cp2y, e.top, e.bottom)));
    }(t, i);
  }
  var Si = function Si(t) {
      return 0 === t || 1 === t;
    },
    Pi = function Pi(t, e, i) {
      return -Math.pow(2, 10 * (t -= 1)) * Math.sin((t - e) * yt / i);
    },
    Di = function Di(t, e, i) {
      return Math.pow(2, -10 * t) * Math.sin((t - e) * yt / i) + 1;
    },
    Ci = {
      linear: function linear(t) {
        return t;
      },
      easeInQuad: function easeInQuad(t) {
        return t * t;
      },
      easeOutQuad: function easeOutQuad(t) {
        return -t * (t - 2);
      },
      easeInOutQuad: function easeInOutQuad(t) {
        return (t /= .5) < 1 ? .5 * t * t : -.5 * (--t * (t - 2) - 1);
      },
      easeInCubic: function easeInCubic(t) {
        return t * t * t;
      },
      easeOutCubic: function easeOutCubic(t) {
        return (t -= 1) * t * t + 1;
      },
      easeInOutCubic: function easeInOutCubic(t) {
        return (t /= .5) < 1 ? .5 * t * t * t : .5 * ((t -= 2) * t * t + 2);
      },
      easeInQuart: function easeInQuart(t) {
        return t * t * t * t;
      },
      easeOutQuart: function easeOutQuart(t) {
        return -((t -= 1) * t * t * t - 1);
      },
      easeInOutQuart: function easeInOutQuart(t) {
        return (t /= .5) < 1 ? .5 * t * t * t * t : -.5 * ((t -= 2) * t * t * t - 2);
      },
      easeInQuint: function easeInQuint(t) {
        return t * t * t * t * t;
      },
      easeOutQuint: function easeOutQuint(t) {
        return (t -= 1) * t * t * t * t + 1;
      },
      easeInOutQuint: function easeInOutQuint(t) {
        return (t /= .5) < 1 ? .5 * t * t * t * t * t : .5 * ((t -= 2) * t * t * t * t + 2);
      },
      easeInSine: function easeInSine(t) {
        return 1 - Math.cos(t * kt);
      },
      easeOutSine: function easeOutSine(t) {
        return Math.sin(t * kt);
      },
      easeInOutSine: function easeInOutSine(t) {
        return -.5 * (Math.cos(_t * t) - 1);
      },
      easeInExpo: function easeInExpo(t) {
        return 0 === t ? 0 : Math.pow(2, 10 * (t - 1));
      },
      easeOutExpo: function easeOutExpo(t) {
        return 1 === t ? 1 : 1 - Math.pow(2, -10 * t);
      },
      easeInOutExpo: function easeInOutExpo(t) {
        return Si(t) ? t : t < .5 ? .5 * Math.pow(2, 10 * (2 * t - 1)) : .5 * (2 - Math.pow(2, -10 * (2 * t - 1)));
      },
      easeInCirc: function easeInCirc(t) {
        return t >= 1 ? t : -(Math.sqrt(1 - t * t) - 1);
      },
      easeOutCirc: function easeOutCirc(t) {
        return Math.sqrt(1 - (t -= 1) * t);
      },
      easeInOutCirc: function easeInOutCirc(t) {
        return (t /= .5) < 1 ? -.5 * (Math.sqrt(1 - t * t) - 1) : .5 * (Math.sqrt(1 - (t -= 2) * t) + 1);
      },
      easeInElastic: function easeInElastic(t) {
        return Si(t) ? t : Pi(t, .075, .3);
      },
      easeOutElastic: function easeOutElastic(t) {
        return Si(t) ? t : Di(t, .075, .3);
      },
      easeInOutElastic: function easeInOutElastic(t) {
        var e = .1125;
        return Si(t) ? t : t < .5 ? .5 * Pi(2 * t, e, .45) : .5 + .5 * Di(2 * t - 1, e, .45);
      },
      easeInBack: function easeInBack(t) {
        var e = 1.70158;
        return t * t * ((e + 1) * t - e);
      },
      easeOutBack: function easeOutBack(t) {
        var e = 1.70158;
        return (t -= 1) * t * ((e + 1) * t + e) + 1;
      },
      easeInOutBack: function easeInOutBack(t) {
        var e = 1.70158;
        return (t /= .5) < 1 ? t * t * ((1 + (e *= 1.525)) * t - e) * .5 : .5 * ((t -= 2) * t * ((1 + (e *= 1.525)) * t + e) + 2);
      },
      easeInBounce: function easeInBounce(t) {
        return 1 - Ci.easeOutBounce(1 - t);
      },
      easeOutBounce: function easeOutBounce(t) {
        var e = 7.5625,
          i = 2.75;
        return t < 1 / i ? e * t * t : t < 2 / i ? e * (t -= 1.5 / i) * t + .75 : t < 2.5 / i ? e * (t -= 2.25 / i) * t + .9375 : e * (t -= 2.625 / i) * t + .984375;
      },
      easeInOutBounce: function easeInOutBounce(t) {
        return t < .5 ? .5 * Ci.easeInBounce(2 * t) : .5 * Ci.easeOutBounce(2 * t - 1) + .5;
      }
    };
  function Oi(t, e, i, s) {
    return {
      x: t.x + i * (e.x - t.x),
      y: t.y + i * (e.y - t.y)
    };
  }
  function Ai(t, e, i, s) {
    return {
      x: t.x + i * (e.x - t.x),
      y: "middle" === s ? i < .5 ? t.y : e.y : "after" === s ? i < 1 ? t.y : e.y : i > 0 ? e.y : t.y
    };
  }
  function Ti(t, e, i, s) {
    var n = {
        x: t.cp2x,
        y: t.cp2y
      },
      o = {
        x: e.cp1x,
        y: e.cp1y
      },
      a = Oi(t, n, i),
      r = Oi(n, o, i),
      l = Oi(o, e, i),
      h = Oi(a, r, i),
      c = Oi(r, l, i);
    return Oi(h, c, i);
  }
  var Li = new Map();
  function Ri(t, e, i) {
    return function (t, e) {
      e = e || {};
      var i = t + JSON.stringify(e);
      var s = Li.get(i);
      return s || (s = new Intl.NumberFormat(t, e), Li.set(i, s)), s;
    }(e, i).format(t);
  }
  function Ei(t, e, i) {
    return t ? function (t, e) {
      return {
        x: function x(i) {
          return t + t + e - i;
        },
        setWidth: function setWidth(t) {
          e = t;
        },
        textAlign: function textAlign(t) {
          return "center" === t ? t : "right" === t ? "left" : "right";
        },
        xPlus: function xPlus(t, e) {
          return t - e;
        },
        leftForLtr: function leftForLtr(t, e) {
          return t - e;
        }
      };
    }(e, i) : {
      x: function x(t) {
        return t;
      },
      setWidth: function setWidth(t) {},
      textAlign: function textAlign(t) {
        return t;
      },
      xPlus: function xPlus(t, e) {
        return t + e;
      },
      leftForLtr: function leftForLtr(t, e) {
        return t;
      }
    };
  }
  function Ii(t, e) {
    var i, s;
    "ltr" !== e && "rtl" !== e || (i = t.canvas.style, s = [i.getPropertyValue("direction"), i.getPropertyPriority("direction")], i.setProperty("direction", e, "important"), t.prevTextDirection = s);
  }
  function zi(t, e) {
    void 0 !== e && (delete t.prevTextDirection, t.canvas.style.setProperty("direction", e[0], e[1]));
  }
  function Fi(t) {
    return "angle" === t ? {
      between: Ht,
      compare: Wt,
      normalize: Nt
    } : {
      between: Yt,
      compare: function compare(t, e) {
        return t - e;
      },
      normalize: function normalize(t) {
        return t;
      }
    };
  }
  function Bi(_ref2) {
    var t = _ref2.start,
      e = _ref2.end,
      i = _ref2.count,
      s = _ref2.loop,
      n = _ref2.style;
    return {
      start: t % i,
      end: e % i,
      loop: s && (e - t + 1) % i == 0,
      style: n
    };
  }
  function Vi(t, e, i) {
    if (!i) return [t];
    var s = i.property,
      n = i.start,
      o = i.end,
      a = e.length,
      _Fi = Fi(s),
      r = _Fi.compare,
      l = _Fi.between,
      h = _Fi.normalize,
      _ref3 = function (t, e, i) {
        var s = i.property,
          n = i.start,
          o = i.end,
          _Fi2 = Fi(s),
          a = _Fi2.between,
          r = _Fi2.normalize,
          l = e.length;
        var h,
          c,
          d = t.start,
          u = t.end,
          f = t.loop;
        if (f) {
          for (d += l, u += l, h = 0, c = l; h < c && a(r(e[d % l][s]), n, o); ++h) d--, u--;
          d %= l, u %= l;
        }
        return u < d && (u += l), {
          start: d,
          end: u,
          loop: f,
          style: t.style
        };
      }(t, e, i),
      c = _ref3.start,
      d = _ref3.end,
      u = _ref3.loop,
      f = _ref3.style,
      g = [];
    var p,
      m,
      x,
      b = !1,
      _ = null;
    var y = function y() {
        return b || l(n, x, p) && 0 !== r(n, x);
      },
      v = function v() {
        return !b || 0 === r(o, p) || l(o, x, p);
      };
    for (var _t17 = c, _i14 = c; _t17 <= d; ++_t17) m = e[_t17 % a], m.skip || (p = h(m[s]), p !== x && (b = l(p, n, o), null === _ && y() && (_ = 0 === r(p, n) ? _t17 : _i14), null !== _ && v() && (g.push(Bi({
      start: _,
      end: _t17,
      loop: u,
      count: a,
      style: f
    })), _ = null), _i14 = _t17, x = p));
    return null !== _ && g.push(Bi({
      start: _,
      end: d,
      loop: u,
      count: a,
      style: f
    })), g;
  }
  function Wi(t, e) {
    var i = [],
      s = t.segments;
    for (var _n10 = 0; _n10 < s.length; _n10++) {
      var _o12 = Vi(s[_n10], t.points, e);
      _o12.length && i.push.apply(i, _toConsumableArray(_o12));
    }
    return i;
  }
  function Ni(t, e) {
    var i = t.points,
      s = t.options.spanGaps,
      n = i.length;
    if (!n) return [];
    var o = !!t._loop,
      _ref4 = function (t, e, i, s) {
        var n = 0,
          o = e - 1;
        if (i && !s) for (; n < e && !t[n].skip;) n++;
        for (; n < e && t[n].skip;) n++;
        for (n %= e, i && (o += n); o > n && t[o % e].skip;) o--;
        return o %= e, {
          start: n,
          end: o
        };
      }(i, n, o, s),
      a = _ref4.start,
      r = _ref4.end;
    if (!0 === s) return Hi(t, [{
      start: a,
      end: r,
      loop: o
    }], i, e);
    return Hi(t, function (t, e, i, s) {
      var n = t.length,
        o = [];
      var a,
        r = e,
        l = t[e];
      for (a = e + 1; a <= i; ++a) {
        var _i15 = t[a % n];
        _i15.skip || _i15.stop ? l.skip || (s = !1, o.push({
          start: e % n,
          end: (a - 1) % n,
          loop: s
        }), e = r = _i15.stop ? a : null) : (r = a, l.skip && (e = a)), l = _i15;
      }
      return null !== r && o.push({
        start: e % n,
        end: r % n,
        loop: s
      }), o;
    }(i, a, r < a ? r + n : r, !!t._fullLoop && 0 === a && r === n - 1), i, e);
  }
  function Hi(t, e, i, s) {
    return s && s.setContext && i ? function (t, e, i, s) {
      var n = t._chart.getContext(),
        o = ji(t.options),
        a = t._datasetIndex,
        r = t.options.spanGaps,
        l = i.length,
        h = [];
      var c = o,
        d = e[0].start,
        u = d;
      function f(t, e, s, n) {
        var o = r ? -1 : 1;
        if (t !== e) {
          for (t += l; i[t % l].skip;) t -= o;
          for (; i[e % l].skip;) e += o;
          t % l != e % l && (h.push({
            start: t % l,
            end: e % l,
            loop: s,
            style: n
          }), c = n, d = e % l);
        }
      }
      var _iterator11 = _createForOfIteratorHelper(e),
        _step11;
      try {
        for (_iterator11.s(); !(_step11 = _iterator11.n()).done;) {
          var _t18 = _step11.value;
          d = r ? d : _t18.start;
          var _e11 = void 0,
            _o13 = i[d % l];
          for (u = d + 1; u <= _t18.end; u++) {
            var _r6 = i[u % l];
            _e11 = ji(s.setContext(Ye(n, {
              type: "segment",
              p0: _o13,
              p1: _r6,
              p0DataIndex: (u - 1) % l,
              p1DataIndex: u % l,
              datasetIndex: a
            }))), $i(_e11, c) && f(d, u - 1, _t18.loop, c), _o13 = _r6, c = _e11;
          }
          d < u - 1 && f(d, u - 1, _t18.loop, c);
        }
      } catch (err) {
        _iterator11.e(err);
      } finally {
        _iterator11.f();
      }
      return h;
    }(t, e, i, s) : e;
  }
  function ji(t) {
    return {
      backgroundColor: t.backgroundColor,
      borderCapStyle: t.borderCapStyle,
      borderDash: t.borderDash,
      borderDashOffset: t.borderDashOffset,
      borderJoinStyle: t.borderJoinStyle,
      borderWidth: t.borderWidth,
      borderColor: t.borderColor
    };
  }
  function $i(t, e) {
    return e && JSON.stringify(t) !== JSON.stringify(e);
  }
  var Yi = Object.freeze({
    __proto__: null,
    easingEffects: Ci,
    color: W,
    getHoverColor: N,
    noop: H,
    uid: j,
    isNullOrUndef: $,
    isArray: Y,
    isObject: U,
    isFinite: X,
    finiteOrDefault: q,
    valueOrDefault: K,
    toPercentage: G,
    toDimension: Z,
    callback: J,
    each: Q,
    _elementsEqual: tt,
    clone: et,
    _merger: st,
    merge: nt,
    mergeIf: ot,
    _mergerIf: at,
    _deprecated: function _deprecated(t, e, i, s) {
      void 0 !== e && console.warn(t + ': "' + i + '" is deprecated. Please use "' + s + '" instead');
    },
    resolveObjectKey: lt,
    _capitalize: ht,
    defined: ct,
    isFunction: dt,
    setsEqual: ut,
    _isClickEvent: ft,
    toFontString: Ut,
    _measureText: Xt,
    _longestText: qt,
    _alignPixel: Kt,
    clearCanvas: Gt,
    drawPoint: Zt,
    _isPointInArea: Jt,
    clipArea: Qt,
    unclipArea: te,
    _steppedLineTo: ee,
    _bezierCurveTo: ie,
    renderText: se,
    addRoundedRectPath: oe,
    _lookup: ae,
    _lookupByKey: re,
    _rlookupByKey: le,
    _filterBetween: he,
    listenArrayEvents: de,
    unlistenArrayEvents: ue,
    _arrayUnique: fe,
    _createResolver: oi,
    _attachContext: ai,
    _descriptors: ri,
    splineCurve: vi,
    splineCurveMonotone: wi,
    _updateBezierControlPoints: ki,
    _isDomSupported: ge,
    _getParentNode: pe,
    getStyle: be,
    getRelativePosition: ve,
    getMaximumSize: Me,
    retinaScale: ke,
    supportsEventListenerOptions: Se,
    readUsedSize: Pe,
    fontString: function fontString(t, e, i) {
      return e + " " + t + "px " + i;
    },
    requestAnimFrame: t,
    throttled: e,
    debounce: i,
    _toLeftRightCenter: s,
    _alignStartEnd: n,
    _textX: o,
    _pointInLine: Oi,
    _steppedInterpolation: Ai,
    _bezierInterpolation: Ti,
    formatNumber: Ri,
    toLineHeight: Fe,
    _readValueToProps: Be,
    toTRBL: Ve,
    toTRBLCorners: We,
    toPadding: Ne,
    toFont: He,
    resolve: je,
    _addGrace: $e,
    createContext: Ye,
    PI: _t,
    TAU: yt,
    PITAU: vt,
    INFINITY: wt,
    RAD_PER_DEG: Mt,
    HALF_PI: kt,
    QUARTER_PI: St,
    TWO_THIRDS_PI: Pt,
    log10: Dt,
    sign: Ct,
    niceNum: Ot,
    _factorize: At,
    isNumber: Tt,
    almostEquals: Lt,
    almostWhole: Rt,
    _setMinAndMaxByKey: Et,
    toRadians: It,
    toDegrees: zt,
    _decimalPlaces: Ft,
    getAngleFromPoint: Bt,
    distanceBetweenPoints: Vt,
    _angleDiff: Wt,
    _normalizeAngle: Nt,
    _angleBetween: Ht,
    _limitValue: jt,
    _int16Range: $t,
    _isBetween: Yt,
    getRtlAdapter: Ei,
    overrideTextDirection: Ii,
    restoreTextDirection: zi,
    _boundSegment: Vi,
    _boundSegments: Wi,
    _computeSegments: Ni
  });
  var Ui = /*#__PURE__*/function () {
    function Ui() {
      _classCallCheck(this, Ui);
    }
    return _createClass(Ui, [{
      key: "acquireContext",
      value: function acquireContext(t, e) {}
    }, {
      key: "releaseContext",
      value: function releaseContext(t) {
        return !1;
      }
    }, {
      key: "addEventListener",
      value: function addEventListener(t, e, i) {}
    }, {
      key: "removeEventListener",
      value: function removeEventListener(t, e, i) {}
    }, {
      key: "getDevicePixelRatio",
      value: function getDevicePixelRatio() {
        return 1;
      }
    }, {
      key: "getMaximumSize",
      value: function getMaximumSize(t, e, i, s) {
        return e = Math.max(0, e || t.width), i = i || t.height, {
          width: e,
          height: Math.max(0, s ? Math.floor(e / s) : i)
        };
      }
    }, {
      key: "isAttached",
      value: function isAttached(t) {
        return !0;
      }
    }, {
      key: "updateConfig",
      value: function updateConfig(t) {}
    }]);
  }();
  var Xi = /*#__PURE__*/function (_Ui) {
    function Xi() {
      _classCallCheck(this, Xi);
      return _callSuper(this, Xi, arguments);
    }
    _inherits(Xi, _Ui);
    return _createClass(Xi, [{
      key: "acquireContext",
      value: function acquireContext(t) {
        return t && t.getContext && t.getContext("2d") || null;
      }
    }, {
      key: "updateConfig",
      value: function updateConfig(t) {
        t.options.animation = !1;
      }
    }]);
  }(Ui);
  var qi = {
      touchstart: "mousedown",
      touchmove: "mousemove",
      touchend: "mouseup",
      pointerenter: "mouseenter",
      pointerdown: "mousedown",
      pointermove: "mousemove",
      pointerup: "mouseup",
      pointerleave: "mouseout",
      pointerout: "mouseout"
    },
    Ki = function Ki(t) {
      return null === t || "" === t;
    };
  var Gi = !!Se && {
    passive: !0
  };
  function Zi(t, e, i) {
    t.canvas.removeEventListener(e, i, Gi);
  }
  function Ji(t, e) {
    var _iterator12 = _createForOfIteratorHelper(t),
      _step12;
    try {
      for (_iterator12.s(); !(_step12 = _iterator12.n()).done;) {
        var _i16 = _step12.value;
        if (_i16 === e || _i16.contains(e)) return !0;
      }
    } catch (err) {
      _iterator12.e(err);
    } finally {
      _iterator12.f();
    }
  }
  function Qi(t, e, i) {
    var s = t.canvas,
      n = new MutationObserver(function (t) {
        var e = !1;
        var _iterator13 = _createForOfIteratorHelper(t),
          _step13;
        try {
          for (_iterator13.s(); !(_step13 = _iterator13.n()).done;) {
            var _i17 = _step13.value;
            e = e || Ji(_i17.addedNodes, s), e = e && !Ji(_i17.removedNodes, s);
          }
        } catch (err) {
          _iterator13.e(err);
        } finally {
          _iterator13.f();
        }
        e && i();
      });
    return n.observe(document, {
      childList: !0,
      subtree: !0
    }), n;
  }
  function ts(t, e, i) {
    var s = t.canvas,
      n = new MutationObserver(function (t) {
        var e = !1;
        var _iterator14 = _createForOfIteratorHelper(t),
          _step14;
        try {
          for (_iterator14.s(); !(_step14 = _iterator14.n()).done;) {
            var _i18 = _step14.value;
            e = e || Ji(_i18.removedNodes, s), e = e && !Ji(_i18.addedNodes, s);
          }
        } catch (err) {
          _iterator14.e(err);
        } finally {
          _iterator14.f();
        }
        e && i();
      });
    return n.observe(document, {
      childList: !0,
      subtree: !0
    }), n;
  }
  var es = new Map();
  var is = 0;
  function ss() {
    var t = window.devicePixelRatio;
    t !== is && (is = t, es.forEach(function (e, i) {
      i.currentDevicePixelRatio !== t && e();
    }));
  }
  function ns(t, i, s) {
    var n = t.canvas,
      o = n && pe(n);
    if (!o) return;
    var a = e(function (t, e) {
        var i = o.clientWidth;
        s(t, e), i < o.clientWidth && s();
      }, window),
      r = new ResizeObserver(function (t) {
        var e = t[0],
          i = e.contentRect.width,
          s = e.contentRect.height;
        0 === i && 0 === s || a(i, s);
      });
    return r.observe(o), function (t, e) {
      es.size || window.addEventListener("resize", ss), es.set(t, e);
    }(t, a), r;
  }
  function os(t, e, i) {
    i && i.disconnect(), "resize" === e && function (t) {
      es["delete"](t), es.size || window.removeEventListener("resize", ss);
    }(t);
  }
  function as(t, i, s) {
    var n = t.canvas,
      o = e(function (e) {
        null !== t.ctx && s(function (t, e) {
          var i = qi[t.type] || t.type,
            _ve = ve(t, e),
            s = _ve.x,
            n = _ve.y;
          return {
            type: i,
            chart: e,
            "native": t,
            x: void 0 !== s ? s : null,
            y: void 0 !== n ? n : null
          };
        }(e, t));
      }, t, function (t) {
        var e = t[0];
        return [e, e.offsetX, e.offsetY];
      });
    return function (t, e, i) {
      t.addEventListener(e, i, Gi);
    }(n, i, o), o;
  }
  var rs = /*#__PURE__*/function (_Ui2) {
    function rs() {
      _classCallCheck(this, rs);
      return _callSuper(this, rs, arguments);
    }
    _inherits(rs, _Ui2);
    return _createClass(rs, [{
      key: "acquireContext",
      value: function acquireContext(t, e) {
        var i = t && t.getContext && t.getContext("2d");
        return i && i.canvas === t ? (function (t, e) {
          var i = t.style,
            s = t.getAttribute("height"),
            n = t.getAttribute("width");
          if (t.$chartjs = {
            initial: {
              height: s,
              width: n,
              style: {
                display: i.display,
                height: i.height,
                width: i.width
              }
            }
          }, i.display = i.display || "block", i.boxSizing = i.boxSizing || "border-box", Ki(n)) {
            var _e12 = Pe(t, "width");
            void 0 !== _e12 && (t.width = _e12);
          }
          if (Ki(s)) if ("" === t.style.height) t.height = t.width / (e || 2);else {
            var _e13 = Pe(t, "height");
            void 0 !== _e13 && (t.height = _e13);
          }
        }(t, e), i) : null;
      }
    }, {
      key: "releaseContext",
      value: function releaseContext(t) {
        var e = t.canvas;
        if (!e.$chartjs) return !1;
        var i = e.$chartjs.initial;
        ["height", "width"].forEach(function (t) {
          var s = i[t];
          $(s) ? e.removeAttribute(t) : e.setAttribute(t, s);
        });
        var s = i.style || {};
        return Object.keys(s).forEach(function (t) {
          e.style[t] = s[t];
        }), e.width = e.width, delete e.$chartjs, !0;
      }
    }, {
      key: "addEventListener",
      value: function addEventListener(t, e, i) {
        this.removeEventListener(t, e);
        var s = t.$proxies || (t.$proxies = {}),
          n = {
            attach: Qi,
            detach: ts,
            resize: ns
          }[e] || as;
        s[e] = n(t, e, i);
      }
    }, {
      key: "removeEventListener",
      value: function removeEventListener(t, e) {
        var i = t.$proxies || (t.$proxies = {}),
          s = i[e];
        if (!s) return;
        (({
          attach: os,
          detach: os,
          resize: os
        })[e] || Zi)(t, e, s), i[e] = void 0;
      }
    }, {
      key: "getDevicePixelRatio",
      value: function getDevicePixelRatio() {
        return window.devicePixelRatio;
      }
    }, {
      key: "getMaximumSize",
      value: function getMaximumSize(t, e, i, s) {
        return Me(t, e, i, s);
      }
    }, {
      key: "isAttached",
      value: function isAttached(t) {
        var e = pe(t);
        return !(!e || !e.isConnected);
      }
    }]);
  }(Ui);
  function ls(t) {
    return !ge() || "undefined" != typeof OffscreenCanvas && t instanceof OffscreenCanvas ? Xi : rs;
  }
  var hs = Object.freeze({
    __proto__: null,
    _detectPlatform: ls,
    BasePlatform: Ui,
    BasicPlatform: Xi,
    DomPlatform: rs
  });
  var cs = "transparent",
    ds = {
      "boolean": function boolean(t, e, i) {
        return i > .5 ? e : t;
      },
      color: function color(t, e, i) {
        var s = W(t || cs),
          n = s.valid && W(e || cs);
        return n && n.valid ? n.mix(s, i).hexString() : e;
      },
      number: function number(t, e, i) {
        return t + (e - t) * i;
      }
    };
  var us = /*#__PURE__*/function () {
    function us(t, e, i, s) {
      _classCallCheck(this, us);
      var n = e[i];
      s = je([t.to, s, n, t.from]);
      var o = je([t.from, n, s]);
      this._active = !0, this._fn = t.fn || ds[t.type || _typeof(o)], this._easing = Ci[t.easing] || Ci.linear, this._start = Math.floor(Date.now() + (t.delay || 0)), this._duration = this._total = Math.floor(t.duration), this._loop = !!t.loop, this._target = e, this._prop = i, this._from = o, this._to = s, this._promises = void 0;
    }
    return _createClass(us, [{
      key: "active",
      value: function active() {
        return this._active;
      }
    }, {
      key: "update",
      value: function update(t, e, i) {
        if (this._active) {
          this._notify(!1);
          var _s16 = this._target[this._prop],
            _n11 = i - this._start,
            _o14 = this._duration - _n11;
          this._start = i, this._duration = Math.floor(Math.max(_o14, t.duration)), this._total += _n11, this._loop = !!t.loop, this._to = je([t.to, e, _s16, t.from]), this._from = je([t.from, _s16, e]);
        }
      }
    }, {
      key: "cancel",
      value: function cancel() {
        this._active && (this.tick(Date.now()), this._active = !1, this._notify(!1));
      }
    }, {
      key: "tick",
      value: function tick(t) {
        var e = t - this._start,
          i = this._duration,
          s = this._prop,
          n = this._from,
          o = this._loop,
          a = this._to;
        var r;
        if (this._active = n !== a && (o || e < i), !this._active) return this._target[s] = a, void this._notify(!0);
        e < 0 ? this._target[s] = n : (r = e / i % 2, r = o && r > 1 ? 2 - r : r, r = this._easing(Math.min(1, Math.max(0, r))), this._target[s] = this._fn(n, a, r));
      }
    }, {
      key: "wait",
      value: function wait() {
        var t = this._promises || (this._promises = []);
        return new Promise(function (e, i) {
          t.push({
            res: e,
            rej: i
          });
        });
      }
    }, {
      key: "_notify",
      value: function _notify(t) {
        var e = t ? "res" : "rej",
          i = this._promises || [];
        for (var _t19 = 0; _t19 < i.length; _t19++) i[_t19][e]();
      }
    }]);
  }();
  bt.set("animation", {
    delay: void 0,
    duration: 1e3,
    easing: "easeOutQuart",
    fn: void 0,
    from: void 0,
    loop: void 0,
    to: void 0,
    type: void 0
  });
  var fs = Object.keys(bt.animation);
  bt.describe("animation", {
    _fallback: !1,
    _indexable: !1,
    _scriptable: function _scriptable(t) {
      return "onProgress" !== t && "onComplete" !== t && "fn" !== t;
    }
  }), bt.set("animations", {
    colors: {
      type: "color",
      properties: ["color", "borderColor", "backgroundColor"]
    },
    numbers: {
      type: "number",
      properties: ["x", "y", "borderWidth", "radius", "tension"]
    }
  }), bt.describe("animations", {
    _fallback: "animation"
  }), bt.set("transitions", {
    active: {
      animation: {
        duration: 400
      }
    },
    resize: {
      animation: {
        duration: 0
      }
    },
    show: {
      animations: {
        colors: {
          from: "transparent"
        },
        visible: {
          type: "boolean",
          duration: 0
        }
      }
    },
    hide: {
      animations: {
        colors: {
          to: "transparent"
        },
        visible: {
          type: "boolean",
          easing: "linear",
          fn: function fn(t) {
            return 0 | t;
          }
        }
      }
    }
  });
  var gs = /*#__PURE__*/function () {
    function gs(t, e) {
      _classCallCheck(this, gs);
      this._chart = t, this._properties = new Map(), this.configure(e);
    }
    return _createClass(gs, [{
      key: "configure",
      value: function configure(t) {
        if (!U(t)) return;
        var e = this._properties;
        Object.getOwnPropertyNames(t).forEach(function (i) {
          var s = t[i];
          if (!U(s)) return;
          var n = {};
          for (var _i19 = 0, _fs = fs; _i19 < _fs.length; _i19++) {
            var _t20 = _fs[_i19];
            n[_t20] = s[_t20];
          }
          (Y(s.properties) && s.properties || [i]).forEach(function (t) {
            t !== i && e.has(t) || e.set(t, n);
          });
        });
      }
    }, {
      key: "_animateOptions",
      value: function _animateOptions(t, e) {
        var i = e.options,
          s = function (t, e) {
            if (!e) return;
            var i = t.options;
            if (!i) return void (t.options = e);
            i.$shared && (t.options = i = Object.assign({}, i, {
              $shared: !1,
              $animations: {}
            }));
            return i;
          }(t, i);
        if (!s) return [];
        var n = this._createAnimations(s, i);
        return i.$shared && function (t, e) {
          var i = [],
            s = Object.keys(e);
          for (var _e14 = 0; _e14 < s.length; _e14++) {
            var _n12 = t[s[_e14]];
            _n12 && _n12.active() && i.push(_n12.wait());
          }
          return Promise.all(i);
        }(t.options.$animations, i).then(function () {
          t.options = i;
        }, function () {}), n;
      }
    }, {
      key: "_createAnimations",
      value: function _createAnimations(t, e) {
        var i = this._properties,
          s = [],
          n = t.$animations || (t.$animations = {}),
          o = Object.keys(e),
          a = Date.now();
        var r;
        for (r = o.length - 1; r >= 0; --r) {
          var _l8 = o[r];
          if ("$" === _l8.charAt(0)) continue;
          if ("options" === _l8) {
            s.push.apply(s, _toConsumableArray(this._animateOptions(t, e)));
            continue;
          }
          var _h7 = e[_l8];
          var _c4 = n[_l8];
          var _d2 = i.get(_l8);
          if (_c4) {
            if (_d2 && _c4.active()) {
              _c4.update(_d2, _h7, a);
              continue;
            }
            _c4.cancel();
          }
          _d2 && _d2.duration ? (n[_l8] = _c4 = new us(_d2, t, _l8, _h7), s.push(_c4)) : t[_l8] = _h7;
        }
        return s;
      }
    }, {
      key: "update",
      value: function update(t, e) {
        if (0 === this._properties.size) return void Object.assign(t, e);
        var i = this._createAnimations(t, e);
        return i.length ? (a.add(this._chart, i), !0) : void 0;
      }
    }]);
  }();
  function ps(t, e) {
    var i = t && t.options || {},
      s = i.reverse,
      n = void 0 === i.min ? e : 0,
      o = void 0 === i.max ? e : 0;
    return {
      start: s ? o : n,
      end: s ? n : o
    };
  }
  function ms(t, e) {
    var i = [],
      s = t._getSortedDatasetMetas(e);
    var n, o;
    for (n = 0, o = s.length; n < o; ++n) i.push(s[n].index);
    return i;
  }
  function xs(t, e, i) {
    var s = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : {};
    var n = t.keys,
      o = "single" === s.mode;
    var a, r, l, h;
    if (null !== e) {
      for (a = 0, r = n.length; a < r; ++a) {
        if (l = +n[a], l === i) {
          if (s.all) continue;
          break;
        }
        h = t.values[l], X(h) && (o || 0 === e || Ct(e) === Ct(h)) && (e += h);
      }
      return e;
    }
  }
  function bs(t, e) {
    var i = t && t.options.stacked;
    return i || void 0 === i && void 0 !== e.stack;
  }
  function _s(t, e, i) {
    var s = t[e] || (t[e] = {});
    return s[i] || (s[i] = {});
  }
  function ys(t, e, i, s) {
    var _iterator15 = _createForOfIteratorHelper(e.getMatchingVisibleMetas(s).reverse()),
      _step15;
    try {
      for (_iterator15.s(); !(_step15 = _iterator15.n()).done;) {
        var _n13 = _step15.value;
        var _e15 = t[_n13.index];
        if (i && _e15 > 0 || !i && _e15 < 0) return _n13.index;
      }
    } catch (err) {
      _iterator15.e(err);
    } finally {
      _iterator15.f();
    }
    return null;
  }
  function vs(t, e) {
    var i = t.chart,
      s = t._cachedMeta,
      n = i._stacks || (i._stacks = {}),
      o = s.iScale,
      a = s.vScale,
      r = s.index,
      l = o.axis,
      h = a.axis,
      c = function (t, e, i) {
        return "".concat(t.id, ".").concat(e.id, ".").concat(i.stack || i.type);
      }(o, a, s),
      d = e.length;
    var u;
    for (var _t21 = 0; _t21 < d; ++_t21) {
      var _i20 = e[_t21],
        _o15 = _i20[l],
        _d3 = _i20[h];
      u = (_i20._stacks || (_i20._stacks = {}))[h] = _s(n, c, _o15), u[r] = _d3, u._top = ys(u, a, !0, s.type), u._bottom = ys(u, a, !1, s.type);
    }
  }
  function ws(t, e) {
    var i = t.scales;
    return Object.keys(i).filter(function (t) {
      return i[t].axis === e;
    }).shift();
  }
  function Ms(t, e) {
    var i = t.controller.index,
      s = t.vScale && t.vScale.axis;
    if (s) {
      e = e || t._parsed;
      var _iterator16 = _createForOfIteratorHelper(e),
        _step16;
      try {
        for (_iterator16.s(); !(_step16 = _iterator16.n()).done;) {
          var _t22 = _step16.value;
          var _e16 = _t22._stacks;
          if (!_e16 || void 0 === _e16[s] || void 0 === _e16[s][i]) return;
          delete _e16[s][i];
        }
      } catch (err) {
        _iterator16.e(err);
      } finally {
        _iterator16.f();
      }
    }
  }
  var ks = function ks(t) {
      return "reset" === t || "none" === t;
    },
    Ss = function Ss(t, e) {
      return e ? t : Object.assign({}, t);
    };
  var Ps = /*#__PURE__*/function () {
    function Ps(t, e) {
      _classCallCheck(this, Ps);
      this.chart = t, this._ctx = t.ctx, this.index = e, this._cachedDataOpts = {}, this._cachedMeta = this.getMeta(), this._type = this._cachedMeta.type, this.options = void 0, this._parsing = !1, this._data = void 0, this._objectData = void 0, this._sharedOptions = void 0, this._drawStart = void 0, this._drawCount = void 0, this.enableOptionSharing = !1, this.$context = void 0, this._syncList = [], this.initialize();
    }
    return _createClass(Ps, [{
      key: "initialize",
      value: function initialize() {
        var t = this._cachedMeta;
        this.configure(), this.linkScales(), t._stacked = bs(t.vScale, t), this.addElements();
      }
    }, {
      key: "updateIndex",
      value: function updateIndex(t) {
        this.index !== t && Ms(this._cachedMeta), this.index = t;
      }
    }, {
      key: "linkScales",
      value: function linkScales() {
        var t = this.chart,
          e = this._cachedMeta,
          i = this.getDataset(),
          s = function s(t, e, i, _s17) {
            return "x" === t ? e : "r" === t ? _s17 : i;
          },
          n = e.xAxisID = K(i.xAxisID, ws(t, "x")),
          o = e.yAxisID = K(i.yAxisID, ws(t, "y")),
          a = e.rAxisID = K(i.rAxisID, ws(t, "r")),
          r = e.indexAxis,
          l = e.iAxisID = s(r, n, o, a),
          h = e.vAxisID = s(r, o, n, a);
        e.xScale = this.getScaleForId(n), e.yScale = this.getScaleForId(o), e.rScale = this.getScaleForId(a), e.iScale = this.getScaleForId(l), e.vScale = this.getScaleForId(h);
      }
    }, {
      key: "getDataset",
      value: function getDataset() {
        return this.chart.data.datasets[this.index];
      }
    }, {
      key: "getMeta",
      value: function getMeta() {
        return this.chart.getDatasetMeta(this.index);
      }
    }, {
      key: "getScaleForId",
      value: function getScaleForId(t) {
        return this.chart.scales[t];
      }
    }, {
      key: "_getOtherScale",
      value: function _getOtherScale(t) {
        var e = this._cachedMeta;
        return t === e.iScale ? e.vScale : e.iScale;
      }
    }, {
      key: "reset",
      value: function reset() {
        this._update("reset");
      }
    }, {
      key: "_destroy",
      value: function _destroy() {
        var t = this._cachedMeta;
        this._data && ue(this._data, this), t._stacked && Ms(t);
      }
    }, {
      key: "_dataCheck",
      value: function _dataCheck() {
        var t = this.getDataset(),
          e = t.data || (t.data = []),
          i = this._data;
        if (U(e)) this._data = function (t) {
          var e = Object.keys(t),
            i = new Array(e.length);
          var s, n, o;
          for (s = 0, n = e.length; s < n; ++s) o = e[s], i[s] = {
            x: o,
            y: t[o]
          };
          return i;
        }(e);else if (i !== e) {
          if (i) {
            ue(i, this);
            var _t23 = this._cachedMeta;
            Ms(_t23), _t23._parsed = [];
          }
          e && Object.isExtensible(e) && de(e, this), this._syncList = [], this._data = e;
        }
      }
    }, {
      key: "addElements",
      value: function addElements() {
        var t = this._cachedMeta;
        this._dataCheck(), this.datasetElementType && (t.dataset = new this.datasetElementType());
      }
    }, {
      key: "buildOrUpdateElements",
      value: function buildOrUpdateElements(t) {
        var e = this._cachedMeta,
          i = this.getDataset();
        var s = !1;
        this._dataCheck();
        var n = e._stacked;
        e._stacked = bs(e.vScale, e), e.stack !== i.stack && (s = !0, Ms(e), e.stack = i.stack), this._resyncElements(t), (s || n !== e._stacked) && vs(this, e._parsed);
      }
    }, {
      key: "configure",
      value: function configure() {
        var t = this.chart.config,
          e = t.datasetScopeKeys(this._type),
          i = t.getOptionScopes(this.getDataset(), e, !0);
        this.options = t.createResolver(i, this.getContext()), this._parsing = this.options.parsing, this._cachedDataOpts = {};
      }
    }, {
      key: "parse",
      value: function parse(t, e) {
        var i = this._cachedMeta,
          s = this._data,
          n = i.iScale,
          o = i._stacked,
          a = n.axis;
        var r,
          l,
          h,
          c = 0 === t && e === s.length || i._sorted,
          d = t > 0 && i._parsed[t - 1];
        if (!1 === this._parsing) i._parsed = s, i._sorted = !0, h = s;else {
          h = Y(s[t]) ? this.parseArrayData(i, s, t, e) : U(s[t]) ? this.parseObjectData(i, s, t, e) : this.parsePrimitiveData(i, s, t, e);
          var _n14 = function _n14() {
            return null === l[a] || d && l[a] < d[a];
          };
          for (r = 0; r < e; ++r) i._parsed[r + t] = l = h[r], c && (_n14() && (c = !1), d = l);
          i._sorted = c;
        }
        o && vs(this, h);
      }
    }, {
      key: "parsePrimitiveData",
      value: function parsePrimitiveData(t, e, i, s) {
        var n = t.iScale,
          o = t.vScale,
          a = n.axis,
          r = o.axis,
          l = n.getLabels(),
          h = n === o,
          c = new Array(s);
        var d, u, f;
        for (d = 0, u = s; d < u; ++d) f = d + i, c[d] = _defineProperty(_defineProperty({}, a, h || n.parse(l[f], f)), r, o.parse(e[f], f));
        return c;
      }
    }, {
      key: "parseArrayData",
      value: function parseArrayData(t, e, i, s) {
        var n = t.xScale,
          o = t.yScale,
          a = new Array(s);
        var r, l, h, c;
        for (r = 0, l = s; r < l; ++r) h = r + i, c = e[h], a[r] = {
          x: n.parse(c[0], h),
          y: o.parse(c[1], h)
        };
        return a;
      }
    }, {
      key: "parseObjectData",
      value: function parseObjectData(t, e, i, s) {
        var n = t.xScale,
          o = t.yScale,
          _this$_parsing = this._parsing,
          _this$_parsing$xAxisK = _this$_parsing.xAxisKey,
          a = _this$_parsing$xAxisK === void 0 ? "x" : _this$_parsing$xAxisK,
          _this$_parsing$yAxisK = _this$_parsing.yAxisKey,
          r = _this$_parsing$yAxisK === void 0 ? "y" : _this$_parsing$yAxisK,
          l = new Array(s);
        var h, c, d, u;
        for (h = 0, c = s; h < c; ++h) d = h + i, u = e[d], l[h] = {
          x: n.parse(lt(u, a), d),
          y: o.parse(lt(u, r), d)
        };
        return l;
      }
    }, {
      key: "getParsed",
      value: function getParsed(t) {
        return this._cachedMeta._parsed[t];
      }
    }, {
      key: "getDataElement",
      value: function getDataElement(t) {
        return this._cachedMeta.data[t];
      }
    }, {
      key: "applyStack",
      value: function applyStack(t, e, i) {
        var s = this.chart,
          n = this._cachedMeta,
          o = e[t.axis];
        return xs({
          keys: ms(s, !0),
          values: e._stacks[t.axis]
        }, o, n.index, {
          mode: i
        });
      }
    }, {
      key: "updateRangeFromParsed",
      value: function updateRangeFromParsed(t, e, i, s) {
        var n = i[e.axis];
        var o = null === n ? NaN : n;
        var a = s && i._stacks[e.axis];
        s && a && (s.values = a, o = xs(s, n, this._cachedMeta.index)), t.min = Math.min(t.min, o), t.max = Math.max(t.max, o);
      }
    }, {
      key: "getMinMax",
      value: function getMinMax(t, e) {
        var i = this._cachedMeta,
          s = i._parsed,
          n = i._sorted && t === i.iScale,
          o = s.length,
          a = this._getOtherScale(t),
          r = function (t, e, i) {
            return t && !e.hidden && e._stacked && {
              keys: ms(i, !0),
              values: null
            };
          }(e, i, this.chart),
          l = {
            min: Number.POSITIVE_INFINITY,
            max: Number.NEGATIVE_INFINITY
          },
          _ref5 = function (t) {
            var _t$getUserBounds = t.getUserBounds(),
              e = _t$getUserBounds.min,
              i = _t$getUserBounds.max,
              s = _t$getUserBounds.minDefined,
              n = _t$getUserBounds.maxDefined;
            return {
              min: s ? e : Number.NEGATIVE_INFINITY,
              max: n ? i : Number.POSITIVE_INFINITY
            };
          }(a),
          h = _ref5.min,
          c = _ref5.max;
        var d, u;
        function f() {
          u = s[d];
          var e = u[a.axis];
          return !X(u[t.axis]) || h > e || c < e;
        }
        for (d = 0; d < o && (f() || (this.updateRangeFromParsed(l, t, u, r), !n)); ++d);
        if (n) for (d = o - 1; d >= 0; --d) if (!f()) {
          this.updateRangeFromParsed(l, t, u, r);
          break;
        }
        return l;
      }
    }, {
      key: "getAllParsedValues",
      value: function getAllParsedValues(t) {
        var e = this._cachedMeta._parsed,
          i = [];
        var s, n, o;
        for (s = 0, n = e.length; s < n; ++s) o = e[s][t.axis], X(o) && i.push(o);
        return i;
      }
    }, {
      key: "getMaxOverflow",
      value: function getMaxOverflow() {
        return !1;
      }
    }, {
      key: "getLabelAndValue",
      value: function getLabelAndValue(t) {
        var e = this._cachedMeta,
          i = e.iScale,
          s = e.vScale,
          n = this.getParsed(t);
        return {
          label: i ? "" + i.getLabelForValue(n[i.axis]) : "",
          value: s ? "" + s.getLabelForValue(n[s.axis]) : ""
        };
      }
    }, {
      key: "_update",
      value: function _update(t) {
        var e = this._cachedMeta;
        this.update(t || "default"), e._clip = function (t) {
          var e, i, s, n;
          return U(t) ? (e = t.top, i = t.right, s = t.bottom, n = t.left) : e = i = s = n = t, {
            top: e,
            right: i,
            bottom: s,
            left: n,
            disabled: !1 === t
          };
        }(K(this.options.clip, function (t, e, i) {
          if (!1 === i) return !1;
          var s = ps(t, i),
            n = ps(e, i);
          return {
            top: n.end,
            right: s.end,
            bottom: n.start,
            left: s.start
          };
        }(e.xScale, e.yScale, this.getMaxOverflow())));
      }
    }, {
      key: "update",
      value: function update(t) {}
    }, {
      key: "draw",
      value: function draw() {
        var t = this._ctx,
          e = this.chart,
          i = this._cachedMeta,
          s = i.data || [],
          n = e.chartArea,
          o = [],
          a = this._drawStart || 0,
          r = this._drawCount || s.length - a,
          l = this.options.drawActiveElementsOnTop;
        var h;
        for (i.dataset && i.dataset.draw(t, n, a, r), h = a; h < a + r; ++h) {
          var _e17 = s[h];
          _e17.hidden || (_e17.active && l ? o.push(_e17) : _e17.draw(t, n));
        }
        for (h = 0; h < o.length; ++h) o[h].draw(t, n);
      }
    }, {
      key: "getStyle",
      value: function getStyle(t, e) {
        var i = e ? "active" : "default";
        return void 0 === t && this._cachedMeta.dataset ? this.resolveDatasetElementOptions(i) : this.resolveDataElementOptions(t || 0, i);
      }
    }, {
      key: "getContext",
      value: function getContext(t, e, i) {
        var s = this.getDataset();
        var n;
        if (t >= 0 && t < this._cachedMeta.data.length) {
          var _e18 = this._cachedMeta.data[t];
          n = _e18.$context || (_e18.$context = function (t, e, i) {
            return Ye(t, {
              active: !1,
              dataIndex: e,
              parsed: void 0,
              raw: void 0,
              element: i,
              index: e,
              mode: "default",
              type: "data"
            });
          }(this.getContext(), t, _e18)), n.parsed = this.getParsed(t), n.raw = s.data[t], n.index = n.dataIndex = t;
        } else n = this.$context || (this.$context = function (t, e) {
          return Ye(t, {
            active: !1,
            dataset: void 0,
            datasetIndex: e,
            index: e,
            mode: "default",
            type: "dataset"
          });
        }(this.chart.getContext(), this.index)), n.dataset = s, n.index = n.datasetIndex = this.index;
        return n.active = !!e, n.mode = i, n;
      }
    }, {
      key: "resolveDatasetElementOptions",
      value: function resolveDatasetElementOptions(t) {
        return this._resolveElementOptions(this.datasetElementType.id, t);
      }
    }, {
      key: "resolveDataElementOptions",
      value: function resolveDataElementOptions(t, e) {
        return this._resolveElementOptions(this.dataElementType.id, e, t);
      }
    }, {
      key: "_resolveElementOptions",
      value: function _resolveElementOptions(t) {
        var _this3 = this;
        var e = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : "default";
        var i = arguments.length > 2 ? arguments[2] : undefined;
        var s = "active" === e,
          n = this._cachedDataOpts,
          o = t + "-" + e,
          a = n[o],
          r = this.enableOptionSharing && ct(i);
        if (a) return Ss(a, r);
        var l = this.chart.config,
          h = l.datasetElementScopeKeys(this._type, t),
          c = s ? ["".concat(t, "Hover"), "hover", t, ""] : [t, ""],
          d = l.getOptionScopes(this.getDataset(), h),
          u = Object.keys(bt.elements[t]),
          f = l.resolveNamedOptions(d, u, function () {
            return _this3.getContext(i, s);
          }, c);
        return f.$shared && (f.$shared = r, n[o] = Object.freeze(Ss(f, r))), f;
      }
    }, {
      key: "_resolveAnimations",
      value: function _resolveAnimations(t, e, i) {
        var s = this.chart,
          n = this._cachedDataOpts,
          o = "animation-".concat(e),
          a = n[o];
        if (a) return a;
        var r;
        if (!1 !== s.options.animation) {
          var _s18 = this.chart.config,
            _n15 = _s18.datasetAnimationScopeKeys(this._type, e),
            _o16 = _s18.getOptionScopes(this.getDataset(), _n15);
          r = _s18.createResolver(_o16, this.getContext(t, i, e));
        }
        var l = new gs(s, r && r.animations);
        return r && r._cacheable && (n[o] = Object.freeze(l)), l;
      }
    }, {
      key: "getSharedOptions",
      value: function getSharedOptions(t) {
        if (t.$shared) return this._sharedOptions || (this._sharedOptions = Object.assign({}, t));
      }
    }, {
      key: "includeOptions",
      value: function includeOptions(t, e) {
        return !e || ks(t) || this.chart._animationsDisabled;
      }
    }, {
      key: "updateElement",
      value: function updateElement(t, e, i, s) {
        ks(s) ? Object.assign(t, i) : this._resolveAnimations(e, s).update(t, i);
      }
    }, {
      key: "updateSharedOptions",
      value: function updateSharedOptions(t, e, i) {
        t && !ks(e) && this._resolveAnimations(void 0, e).update(t, i);
      }
    }, {
      key: "_setStyle",
      value: function _setStyle(t, e, i, s) {
        t.active = s;
        var n = this.getStyle(e, s);
        this._resolveAnimations(e, i, s).update(t, {
          options: !s && this.getSharedOptions(n) || n
        });
      }
    }, {
      key: "removeHoverStyle",
      value: function removeHoverStyle(t, e, i) {
        this._setStyle(t, i, "active", !1);
      }
    }, {
      key: "setHoverStyle",
      value: function setHoverStyle(t, e, i) {
        this._setStyle(t, i, "active", !0);
      }
    }, {
      key: "_removeDatasetHoverStyle",
      value: function _removeDatasetHoverStyle() {
        var t = this._cachedMeta.dataset;
        t && this._setStyle(t, void 0, "active", !1);
      }
    }, {
      key: "_setDatasetHoverStyle",
      value: function _setDatasetHoverStyle() {
        var t = this._cachedMeta.dataset;
        t && this._setStyle(t, void 0, "active", !0);
      }
    }, {
      key: "_resyncElements",
      value: function _resyncElements(t) {
        var e = this._data,
          i = this._cachedMeta.data;
        var _iterator17 = _createForOfIteratorHelper(this._syncList),
          _step17;
        try {
          for (_iterator17.s(); !(_step17 = _iterator17.n()).done;) {
            var _step17$value = _slicedToArray(_step17.value, 3),
              _t24 = _step17$value[0],
              _e19 = _step17$value[1],
              _i21 = _step17$value[2];
            this[_t24](_e19, _i21);
          }
        } catch (err) {
          _iterator17.e(err);
        } finally {
          _iterator17.f();
        }
        this._syncList = [];
        var s = i.length,
          n = e.length,
          o = Math.min(n, s);
        o && this.parse(0, o), n > s ? this._insertElements(s, n - s, t) : n < s && this._removeElements(n, s - n);
      }
    }, {
      key: "_insertElements",
      value: function _insertElements(t, e) {
        var i = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : !0;
        var s = this._cachedMeta,
          n = s.data,
          o = t + e;
        var a;
        var r = function r(t) {
          for (t.length += e, a = t.length - 1; a >= o; a--) t[a] = t[a - e];
        };
        for (r(n), a = t; a < o; ++a) n[a] = new this.dataElementType();
        this._parsing && r(s._parsed), this.parse(t, e), i && this.updateElements(n, t, e, "reset");
      }
    }, {
      key: "updateElements",
      value: function updateElements(t, e, i, s) {}
    }, {
      key: "_removeElements",
      value: function _removeElements(t, e) {
        var i = this._cachedMeta;
        if (this._parsing) {
          var _s19 = i._parsed.splice(t, e);
          i._stacked && Ms(i, _s19);
        }
        i.data.splice(t, e);
      }
    }, {
      key: "_sync",
      value: function _sync(t) {
        if (this._parsing) this._syncList.push(t);else {
          var _t25 = _slicedToArray(t, 3),
            _e20 = _t25[0],
            _i22 = _t25[1],
            _s20 = _t25[2];
          this[_e20](_i22, _s20);
        }
        this.chart._dataChanges.push([this.index].concat(_toConsumableArray(t)));
      }
    }, {
      key: "_onDataPush",
      value: function _onDataPush() {
        var t = arguments.length;
        this._sync(["_insertElements", this.getDataset().data.length - t, t]);
      }
    }, {
      key: "_onDataPop",
      value: function _onDataPop() {
        this._sync(["_removeElements", this._cachedMeta.data.length - 1, 1]);
      }
    }, {
      key: "_onDataShift",
      value: function _onDataShift() {
        this._sync(["_removeElements", 0, 1]);
      }
    }, {
      key: "_onDataSplice",
      value: function _onDataSplice(t, e) {
        e && this._sync(["_removeElements", t, e]);
        var i = arguments.length - 2;
        i && this._sync(["_insertElements", t, i]);
      }
    }, {
      key: "_onDataUnshift",
      value: function _onDataUnshift() {
        this._sync(["_insertElements", 0, arguments.length]);
      }
    }]);
  }();
  Ps.defaults = {}, Ps.prototype.datasetElementType = null, Ps.prototype.dataElementType = null;
  var Ds = /*#__PURE__*/function () {
    function Ds() {
      _classCallCheck(this, Ds);
      this.x = void 0, this.y = void 0, this.active = !1, this.options = void 0, this.$animations = void 0;
    }
    return _createClass(Ds, [{
      key: "tooltipPosition",
      value: function tooltipPosition(t) {
        var _this$getProps = this.getProps(["x", "y"], t),
          e = _this$getProps.x,
          i = _this$getProps.y;
        return {
          x: e,
          y: i
        };
      }
    }, {
      key: "hasValue",
      value: function hasValue() {
        return Tt(this.x) && Tt(this.y);
      }
    }, {
      key: "getProps",
      value: function getProps(t, e) {
        var _this4 = this;
        var i = this.$animations;
        if (!e || !i) return this;
        var s = {};
        return t.forEach(function (t) {
          s[t] = i[t] && i[t].active() ? i[t]._to : _this4[t];
        }), s;
      }
    }]);
  }();
  Ds.defaults = {}, Ds.defaultRoutes = void 0;
  var Cs = {
    values: function values(t) {
      return Y(t) ? t : "" + t;
    },
    numeric: function numeric(t, e, i) {
      if (0 === t) return "0";
      var s = this.chart.options.locale;
      var n,
        o = t;
      if (i.length > 1) {
        var _e21 = Math.max(Math.abs(i[0].value), Math.abs(i[i.length - 1].value));
        (_e21 < 1e-4 || _e21 > 1e15) && (n = "scientific"), o = function (t, e) {
          var i = e.length > 3 ? e[2].value - e[1].value : e[1].value - e[0].value;
          Math.abs(i) >= 1 && t !== Math.floor(t) && (i = t - Math.floor(t));
          return i;
        }(t, i);
      }
      var a = Dt(Math.abs(o)),
        r = Math.max(Math.min(-1 * Math.floor(a), 20), 0),
        l = {
          notation: n,
          minimumFractionDigits: r,
          maximumFractionDigits: r
        };
      return Object.assign(l, this.options.ticks.format), Ri(t, s, l);
    },
    logarithmic: function logarithmic(t, e, i) {
      if (0 === t) return "0";
      var s = t / Math.pow(10, Math.floor(Dt(t)));
      return 1 === s || 2 === s || 5 === s ? Cs.numeric.call(this, t, e, i) : "";
    }
  };
  var Os = {
    formatters: Cs
  };
  function As(t, e) {
    var i = t.options.ticks,
      s = i.maxTicksLimit || function (t) {
        var e = t.options.offset,
          i = t._tickSize(),
          s = t._length / i + (e ? 0 : 1),
          n = t._maxLength / i;
        return Math.floor(Math.min(s, n));
      }(t),
      n = i.major.enabled ? function (t) {
        var e = [];
        var i, s;
        for (i = 0, s = t.length; i < s; i++) t[i].major && e.push(i);
        return e;
      }(e) : [],
      o = n.length,
      a = n[0],
      r = n[o - 1],
      l = [];
    if (o > s) return function (t, e, i, s) {
      var n,
        o = 0,
        a = i[0];
      for (s = Math.ceil(s), n = 0; n < t.length; n++) n === a && (e.push(t[n]), o++, a = i[o * s]);
    }(e, l, n, o / s), l;
    var h = function (t, e, i) {
      var s = function (t) {
          var e = t.length;
          var i, s;
          if (e < 2) return !1;
          for (s = t[0], i = 1; i < e; ++i) if (t[i] - t[i - 1] !== s) return !1;
          return s;
        }(t),
        n = e.length / i;
      if (!s) return Math.max(n, 1);
      var o = At(s);
      for (var _t26 = 0, _e22 = o.length - 1; _t26 < _e22; _t26++) {
        var _e23 = o[_t26];
        if (_e23 > n) return _e23;
      }
      return Math.max(n, 1);
    }(n, e, s);
    if (o > 0) {
      var _t27, _i23;
      var _s21 = o > 1 ? Math.round((r - a) / (o - 1)) : null;
      for (Ts(e, l, h, $(_s21) ? 0 : a - _s21, a), _t27 = 0, _i23 = o - 1; _t27 < _i23; _t27++) Ts(e, l, h, n[_t27], n[_t27 + 1]);
      return Ts(e, l, h, r, $(_s21) ? e.length : r + _s21), l;
    }
    return Ts(e, l, h), l;
  }
  function Ts(t, e, i, s, n) {
    var o = K(s, 0),
      a = Math.min(K(n, t.length), t.length);
    var r,
      l,
      h,
      c = 0;
    for (i = Math.ceil(i), n && (r = n - s, i = r / Math.floor(r / i)), h = o; h < 0;) c++, h = Math.round(o + c * i);
    for (l = Math.max(o, 0); l < a; l++) l === h && (e.push(t[l]), c++, h = Math.round(o + c * i));
  }
  bt.set("scale", {
    display: !0,
    offset: !1,
    reverse: !1,
    beginAtZero: !1,
    bounds: "ticks",
    grace: 0,
    grid: {
      display: !0,
      lineWidth: 1,
      drawBorder: !0,
      drawOnChartArea: !0,
      drawTicks: !0,
      tickLength: 8,
      tickWidth: function tickWidth(t, e) {
        return e.lineWidth;
      },
      tickColor: function tickColor(t, e) {
        return e.color;
      },
      offset: !1,
      borderDash: [],
      borderDashOffset: 0,
      borderWidth: 1
    },
    title: {
      display: !1,
      text: "",
      padding: {
        top: 4,
        bottom: 4
      }
    },
    ticks: {
      minRotation: 0,
      maxRotation: 50,
      mirror: !1,
      textStrokeWidth: 0,
      textStrokeColor: "",
      padding: 3,
      display: !0,
      autoSkip: !0,
      autoSkipPadding: 3,
      labelOffset: 0,
      callback: Os.formatters.values,
      minor: {},
      major: {},
      align: "center",
      crossAlign: "near",
      showLabelBackdrop: !1,
      backdropColor: "rgba(255, 255, 255, 0.75)",
      backdropPadding: 2
    }
  }), bt.route("scale.ticks", "color", "", "color"), bt.route("scale.grid", "color", "", "borderColor"), bt.route("scale.grid", "borderColor", "", "borderColor"), bt.route("scale.title", "color", "", "color"), bt.describe("scale", {
    _fallback: !1,
    _scriptable: function _scriptable(t) {
      return !t.startsWith("before") && !t.startsWith("after") && "callback" !== t && "parser" !== t;
    },
    _indexable: function _indexable(t) {
      return "borderDash" !== t && "tickBorderDash" !== t;
    }
  }), bt.describe("scales", {
    _fallback: "scale"
  }), bt.describe("scale.ticks", {
    _scriptable: function _scriptable(t) {
      return "backdropPadding" !== t && "callback" !== t;
    },
    _indexable: function _indexable(t) {
      return "backdropPadding" !== t;
    }
  });
  var Ls = function Ls(t, e, i) {
    return "top" === e || "left" === e ? t[e] + i : t[e] - i;
  };
  function Rs(t, e) {
    var i = [],
      s = t.length / e,
      n = t.length;
    var o = 0;
    for (; o < n; o += s) i.push(t[Math.floor(o)]);
    return i;
  }
  function Es(t, e, i) {
    var s = t.ticks.length,
      n = Math.min(e, s - 1),
      o = t._startPixel,
      a = t._endPixel,
      r = 1e-6;
    var l,
      h = t.getPixelForTick(n);
    if (!(i && (l = 1 === s ? Math.max(h - o, a - h) : 0 === e ? (t.getPixelForTick(1) - h) / 2 : (h - t.getPixelForTick(n - 1)) / 2, h += n < e ? l : -l, h < o - r || h > a + r))) return h;
  }
  function Is(t) {
    return t.drawTicks ? t.tickLength : 0;
  }
  function zs(t, e) {
    if (!t.display) return 0;
    var i = He(t.font, e),
      s = Ne(t.padding);
    return (Y(t.text) ? t.text.length : 1) * i.lineHeight + s.height;
  }
  function Fs(t, e, i) {
    var n = s(t);
    return (i && "right" !== e || !i && "right" === e) && (n = function (t) {
      return "left" === t ? "right" : "right" === t ? "left" : t;
    }(n)), n;
  }
  var Bs = /*#__PURE__*/function (_Ds) {
    function Bs(t) {
      var _this5;
      _classCallCheck(this, Bs);
      _this5 = _callSuper(this, Bs), _this5.id = t.id, _this5.type = t.type, _this5.options = void 0, _this5.ctx = t.ctx, _this5.chart = t.chart, _this5.top = void 0, _this5.bottom = void 0, _this5.left = void 0, _this5.right = void 0, _this5.width = void 0, _this5.height = void 0, _this5._margins = {
        left: 0,
        right: 0,
        top: 0,
        bottom: 0
      }, _this5.maxWidth = void 0, _this5.maxHeight = void 0, _this5.paddingTop = void 0, _this5.paddingBottom = void 0, _this5.paddingLeft = void 0, _this5.paddingRight = void 0, _this5.axis = void 0, _this5.labelRotation = void 0, _this5.min = void 0, _this5.max = void 0, _this5._range = void 0, _this5.ticks = [], _this5._gridLineItems = null, _this5._labelItems = null, _this5._labelSizes = null, _this5._length = 0, _this5._maxLength = 0, _this5._longestTextCache = {}, _this5._startPixel = void 0, _this5._endPixel = void 0, _this5._reversePixels = !1, _this5._userMax = void 0, _this5._userMin = void 0, _this5._suggestedMax = void 0, _this5._suggestedMin = void 0, _this5._ticksLength = 0, _this5._borderValue = 0, _this5._cache = {}, _this5._dataLimitsCached = !1, _this5.$context = void 0;
      return _this5;
    }
    _inherits(Bs, _Ds);
    return _createClass(Bs, [{
      key: "init",
      value: function init(t) {
        this.options = t.setContext(this.getContext()), this.axis = t.axis, this._userMin = this.parse(t.min), this._userMax = this.parse(t.max), this._suggestedMin = this.parse(t.suggestedMin), this._suggestedMax = this.parse(t.suggestedMax);
      }
    }, {
      key: "parse",
      value: function parse(t, e) {
        return t;
      }
    }, {
      key: "getUserBounds",
      value: function getUserBounds() {
        var t = this._userMin,
          e = this._userMax,
          i = this._suggestedMin,
          s = this._suggestedMax;
        return t = q(t, Number.POSITIVE_INFINITY), e = q(e, Number.NEGATIVE_INFINITY), i = q(i, Number.POSITIVE_INFINITY), s = q(s, Number.NEGATIVE_INFINITY), {
          min: q(t, i),
          max: q(e, s),
          minDefined: X(t),
          maxDefined: X(e)
        };
      }
    }, {
      key: "getMinMax",
      value: function getMinMax(t) {
        var e,
          _this$getUserBounds = this.getUserBounds(),
          i = _this$getUserBounds.min,
          s = _this$getUserBounds.max,
          n = _this$getUserBounds.minDefined,
          o = _this$getUserBounds.maxDefined;
        if (n && o) return {
          min: i,
          max: s
        };
        var a = this.getMatchingVisibleMetas();
        for (var _r7 = 0, _l9 = a.length; _r7 < _l9; ++_r7) e = a[_r7].controller.getMinMax(this, t), n || (i = Math.min(i, e.min)), o || (s = Math.max(s, e.max));
        return i = o && i > s ? s : i, s = n && i > s ? i : s, {
          min: q(i, q(s, i)),
          max: q(s, q(i, s))
        };
      }
    }, {
      key: "getPadding",
      value: function getPadding() {
        return {
          left: this.paddingLeft || 0,
          top: this.paddingTop || 0,
          right: this.paddingRight || 0,
          bottom: this.paddingBottom || 0
        };
      }
    }, {
      key: "getTicks",
      value: function getTicks() {
        return this.ticks;
      }
    }, {
      key: "getLabels",
      value: function getLabels() {
        var t = this.chart.data;
        return this.options.labels || (this.isHorizontal() ? t.xLabels : t.yLabels) || t.labels || [];
      }
    }, {
      key: "beforeLayout",
      value: function beforeLayout() {
        this._cache = {}, this._dataLimitsCached = !1;
      }
    }, {
      key: "beforeUpdate",
      value: function beforeUpdate() {
        J(this.options.beforeUpdate, [this]);
      }
    }, {
      key: "update",
      value: function update(t, e, i) {
        var _this$options = this.options,
          s = _this$options.beginAtZero,
          n = _this$options.grace,
          o = _this$options.ticks,
          a = o.sampleSize;
        this.beforeUpdate(), this.maxWidth = t, this.maxHeight = e, this._margins = i = Object.assign({
          left: 0,
          right: 0,
          top: 0,
          bottom: 0
        }, i), this.ticks = null, this._labelSizes = null, this._gridLineItems = null, this._labelItems = null, this.beforeSetDimensions(), this.setDimensions(), this.afterSetDimensions(), this._maxLength = this.isHorizontal() ? this.width + i.left + i.right : this.height + i.top + i.bottom, this._dataLimitsCached || (this.beforeDataLimits(), this.determineDataLimits(), this.afterDataLimits(), this._range = $e(this, n, s), this._dataLimitsCached = !0), this.beforeBuildTicks(), this.ticks = this.buildTicks() || [], this.afterBuildTicks();
        var r = a < this.ticks.length;
        this._convertTicksToLabels(r ? Rs(this.ticks, a) : this.ticks), this.configure(), this.beforeCalculateLabelRotation(), this.calculateLabelRotation(), this.afterCalculateLabelRotation(), o.display && (o.autoSkip || "auto" === o.source) && (this.ticks = As(this, this.ticks), this._labelSizes = null), r && this._convertTicksToLabels(this.ticks), this.beforeFit(), this.fit(), this.afterFit(), this.afterUpdate();
      }
    }, {
      key: "configure",
      value: function configure() {
        var t,
          e,
          i = this.options.reverse;
        this.isHorizontal() ? (t = this.left, e = this.right) : (t = this.top, e = this.bottom, i = !i), this._startPixel = t, this._endPixel = e, this._reversePixels = i, this._length = e - t, this._alignToPixels = this.options.alignToPixels;
      }
    }, {
      key: "afterUpdate",
      value: function afterUpdate() {
        J(this.options.afterUpdate, [this]);
      }
    }, {
      key: "beforeSetDimensions",
      value: function beforeSetDimensions() {
        J(this.options.beforeSetDimensions, [this]);
      }
    }, {
      key: "setDimensions",
      value: function setDimensions() {
        this.isHorizontal() ? (this.width = this.maxWidth, this.left = 0, this.right = this.width) : (this.height = this.maxHeight, this.top = 0, this.bottom = this.height), this.paddingLeft = 0, this.paddingTop = 0, this.paddingRight = 0, this.paddingBottom = 0;
      }
    }, {
      key: "afterSetDimensions",
      value: function afterSetDimensions() {
        J(this.options.afterSetDimensions, [this]);
      }
    }, {
      key: "_callHooks",
      value: function _callHooks(t) {
        this.chart.notifyPlugins(t, this.getContext()), J(this.options[t], [this]);
      }
    }, {
      key: "beforeDataLimits",
      value: function beforeDataLimits() {
        this._callHooks("beforeDataLimits");
      }
    }, {
      key: "determineDataLimits",
      value: function determineDataLimits() {}
    }, {
      key: "afterDataLimits",
      value: function afterDataLimits() {
        this._callHooks("afterDataLimits");
      }
    }, {
      key: "beforeBuildTicks",
      value: function beforeBuildTicks() {
        this._callHooks("beforeBuildTicks");
      }
    }, {
      key: "buildTicks",
      value: function buildTicks() {
        return [];
      }
    }, {
      key: "afterBuildTicks",
      value: function afterBuildTicks() {
        this._callHooks("afterBuildTicks");
      }
    }, {
      key: "beforeTickToLabelConversion",
      value: function beforeTickToLabelConversion() {
        J(this.options.beforeTickToLabelConversion, [this]);
      }
    }, {
      key: "generateTickLabels",
      value: function generateTickLabels(t) {
        var e = this.options.ticks;
        var i, s, n;
        for (i = 0, s = t.length; i < s; i++) n = t[i], n.label = J(e.callback, [n.value, i, t], this);
      }
    }, {
      key: "afterTickToLabelConversion",
      value: function afterTickToLabelConversion() {
        J(this.options.afterTickToLabelConversion, [this]);
      }
    }, {
      key: "beforeCalculateLabelRotation",
      value: function beforeCalculateLabelRotation() {
        J(this.options.beforeCalculateLabelRotation, [this]);
      }
    }, {
      key: "calculateLabelRotation",
      value: function calculateLabelRotation() {
        var t = this.options,
          e = t.ticks,
          i = this.ticks.length,
          s = e.minRotation || 0,
          n = e.maxRotation;
        var o,
          a,
          r,
          l = s;
        if (!this._isVisible() || !e.display || s >= n || i <= 1 || !this.isHorizontal()) return void (this.labelRotation = s);
        var h = this._getLabelSizes(),
          c = h.widest.width,
          d = h.highest.height,
          u = jt(this.chart.width - c, 0, this.maxWidth);
        o = t.offset ? this.maxWidth / i : u / (i - 1), c + 6 > o && (o = u / (i - (t.offset ? .5 : 1)), a = this.maxHeight - Is(t.grid) - e.padding - zs(t.title, this.chart.options.font), r = Math.sqrt(c * c + d * d), l = zt(Math.min(Math.asin(jt((h.highest.height + 6) / o, -1, 1)), Math.asin(jt(a / r, -1, 1)) - Math.asin(jt(d / r, -1, 1)))), l = Math.max(s, Math.min(n, l))), this.labelRotation = l;
      }
    }, {
      key: "afterCalculateLabelRotation",
      value: function afterCalculateLabelRotation() {
        J(this.options.afterCalculateLabelRotation, [this]);
      }
    }, {
      key: "beforeFit",
      value: function beforeFit() {
        J(this.options.beforeFit, [this]);
      }
    }, {
      key: "fit",
      value: function fit() {
        var t = {
            width: 0,
            height: 0
          },
          e = this.chart,
          _this$options2 = this.options,
          i = _this$options2.ticks,
          s = _this$options2.title,
          n = _this$options2.grid,
          o = this._isVisible(),
          a = this.isHorizontal();
        if (o) {
          var _o17 = zs(s, e.options.font);
          if (a ? (t.width = this.maxWidth, t.height = Is(n) + _o17) : (t.height = this.maxHeight, t.width = Is(n) + _o17), i.display && this.ticks.length) {
            var _this$_getLabelSizes = this._getLabelSizes(),
              _e24 = _this$_getLabelSizes.first,
              _s22 = _this$_getLabelSizes.last,
              _n16 = _this$_getLabelSizes.widest,
              _o18 = _this$_getLabelSizes.highest,
              _r8 = 2 * i.padding,
              _l10 = It(this.labelRotation),
              _h8 = Math.cos(_l10),
              _c5 = Math.sin(_l10);
            if (a) {
              var _e25 = i.mirror ? 0 : _c5 * _n16.width + _h8 * _o18.height;
              t.height = Math.min(this.maxHeight, t.height + _e25 + _r8);
            } else {
              var _e26 = i.mirror ? 0 : _h8 * _n16.width + _c5 * _o18.height;
              t.width = Math.min(this.maxWidth, t.width + _e26 + _r8);
            }
            this._calculatePadding(_e24, _s22, _c5, _h8);
          }
        }
        this._handleMargins(), a ? (this.width = this._length = e.width - this._margins.left - this._margins.right, this.height = t.height) : (this.width = t.width, this.height = this._length = e.height - this._margins.top - this._margins.bottom);
      }
    }, {
      key: "_calculatePadding",
      value: function _calculatePadding(t, e, i, s) {
        var _this$options3 = this.options,
          _this$options3$ticks = _this$options3.ticks,
          n = _this$options3$ticks.align,
          o = _this$options3$ticks.padding,
          a = _this$options3.position,
          r = 0 !== this.labelRotation,
          l = "top" !== a && "x" === this.axis;
        if (this.isHorizontal()) {
          var _a12 = this.getPixelForTick(0) - this.left,
            _h9 = this.right - this.getPixelForTick(this.ticks.length - 1);
          var _c6 = 0,
            _d4 = 0;
          r ? l ? (_c6 = s * t.width, _d4 = i * e.height) : (_c6 = i * t.height, _d4 = s * e.width) : "start" === n ? _d4 = e.width : "end" === n ? _c6 = t.width : (_c6 = t.width / 2, _d4 = e.width / 2), this.paddingLeft = Math.max((_c6 - _a12 + o) * this.width / (this.width - _a12), 0), this.paddingRight = Math.max((_d4 - _h9 + o) * this.width / (this.width - _h9), 0);
        } else {
          var _i24 = e.height / 2,
            _s23 = t.height / 2;
          "start" === n ? (_i24 = 0, _s23 = t.height) : "end" === n && (_i24 = e.height, _s23 = 0), this.paddingTop = _i24 + o, this.paddingBottom = _s23 + o;
        }
      }
    }, {
      key: "_handleMargins",
      value: function _handleMargins() {
        this._margins && (this._margins.left = Math.max(this.paddingLeft, this._margins.left), this._margins.top = Math.max(this.paddingTop, this._margins.top), this._margins.right = Math.max(this.paddingRight, this._margins.right), this._margins.bottom = Math.max(this.paddingBottom, this._margins.bottom));
      }
    }, {
      key: "afterFit",
      value: function afterFit() {
        J(this.options.afterFit, [this]);
      }
    }, {
      key: "isHorizontal",
      value: function isHorizontal() {
        var _this$options4 = this.options,
          t = _this$options4.axis,
          e = _this$options4.position;
        return "top" === e || "bottom" === e || "x" === t;
      }
    }, {
      key: "isFullSize",
      value: function isFullSize() {
        return this.options.fullSize;
      }
    }, {
      key: "_convertTicksToLabels",
      value: function _convertTicksToLabels(t) {
        var e, i;
        for (this.beforeTickToLabelConversion(), this.generateTickLabels(t), e = 0, i = t.length; e < i; e++) $(t[e].label) && (t.splice(e, 1), i--, e--);
        this.afterTickToLabelConversion();
      }
    }, {
      key: "_getLabelSizes",
      value: function _getLabelSizes() {
        var t = this._labelSizes;
        if (!t) {
          var _e27 = this.options.ticks.sampleSize;
          var _i25 = this.ticks;
          _e27 < _i25.length && (_i25 = Rs(_i25, _e27)), this._labelSizes = t = this._computeLabelSizes(_i25, _i25.length);
        }
        return t;
      }
    }, {
      key: "_computeLabelSizes",
      value: function _computeLabelSizes(t, e) {
        var i = this.ctx,
          s = this._longestTextCache,
          n = [],
          o = [];
        var a,
          r,
          l,
          h,
          c,
          d,
          u,
          f,
          g,
          p,
          m,
          x = 0,
          b = 0;
        for (a = 0; a < e; ++a) {
          if (h = t[a].label, c = this._resolveTickFontOptions(a), i.font = d = c.string, u = s[d] = s[d] || {
            data: {},
            gc: []
          }, f = c.lineHeight, g = p = 0, $(h) || Y(h)) {
            if (Y(h)) for (r = 0, l = h.length; r < l; ++r) m = h[r], $(m) || Y(m) || (g = Xt(i, u.data, u.gc, g, m), p += f);
          } else g = Xt(i, u.data, u.gc, g, h), p = f;
          n.push(g), o.push(p), x = Math.max(g, x), b = Math.max(p, b);
        }
        !function (t, e) {
          Q(t, function (t) {
            var i = t.gc,
              s = i.length / 2;
            var n;
            if (s > e) {
              for (n = 0; n < s; ++n) delete t.data[i[n]];
              i.splice(0, s);
            }
          });
        }(s, e);
        var _ = n.indexOf(x),
          y = o.indexOf(b),
          v = function v(t) {
            return {
              width: n[t] || 0,
              height: o[t] || 0
            };
          };
        return {
          first: v(0),
          last: v(e - 1),
          widest: v(_),
          highest: v(y),
          widths: n,
          heights: o
        };
      }
    }, {
      key: "getLabelForValue",
      value: function getLabelForValue(t) {
        return t;
      }
    }, {
      key: "getPixelForValue",
      value: function getPixelForValue(t, e) {
        return NaN;
      }
    }, {
      key: "getValueForPixel",
      value: function getValueForPixel(t) {}
    }, {
      key: "getPixelForTick",
      value: function getPixelForTick(t) {
        var e = this.ticks;
        return t < 0 || t > e.length - 1 ? null : this.getPixelForValue(e[t].value);
      }
    }, {
      key: "getPixelForDecimal",
      value: function getPixelForDecimal(t) {
        this._reversePixels && (t = 1 - t);
        var e = this._startPixel + t * this._length;
        return $t(this._alignToPixels ? Kt(this.chart, e, 0) : e);
      }
    }, {
      key: "getDecimalForPixel",
      value: function getDecimalForPixel(t) {
        var e = (t - this._startPixel) / this._length;
        return this._reversePixels ? 1 - e : e;
      }
    }, {
      key: "getBasePixel",
      value: function getBasePixel() {
        return this.getPixelForValue(this.getBaseValue());
      }
    }, {
      key: "getBaseValue",
      value: function getBaseValue() {
        var t = this.min,
          e = this.max;
        return t < 0 && e < 0 ? e : t > 0 && e > 0 ? t : 0;
      }
    }, {
      key: "getContext",
      value: function getContext(t) {
        var e = this.ticks || [];
        if (t >= 0 && t < e.length) {
          var _i26 = e[t];
          return _i26.$context || (_i26.$context = function (t, e, i) {
            return Ye(t, {
              tick: i,
              index: e,
              type: "tick"
            });
          }(this.getContext(), t, _i26));
        }
        return this.$context || (this.$context = Ye(this.chart.getContext(), {
          scale: this,
          type: "scale"
        }));
      }
    }, {
      key: "_tickSize",
      value: function _tickSize() {
        var t = this.options.ticks,
          e = It(this.labelRotation),
          i = Math.abs(Math.cos(e)),
          s = Math.abs(Math.sin(e)),
          n = this._getLabelSizes(),
          o = t.autoSkipPadding || 0,
          a = n ? n.widest.width + o : 0,
          r = n ? n.highest.height + o : 0;
        return this.isHorizontal() ? r * i > a * s ? a / i : r / s : r * s < a * i ? r / i : a / s;
      }
    }, {
      key: "_isVisible",
      value: function _isVisible() {
        var t = this.options.display;
        return "auto" !== t ? !!t : this.getMatchingVisibleMetas().length > 0;
      }
    }, {
      key: "_computeGridLineItems",
      value: function _computeGridLineItems(t) {
        var e = this.axis,
          i = this.chart,
          s = this.options,
          n = s.grid,
          o = s.position,
          a = n.offset,
          r = this.isHorizontal(),
          l = this.ticks.length + (a ? 1 : 0),
          h = Is(n),
          c = [],
          d = n.setContext(this.getContext()),
          u = d.drawBorder ? d.borderWidth : 0,
          f = u / 2,
          g = function g(t) {
            return Kt(i, t, u);
          };
        var p, m, x, b, _, y, v, w, M, k, S, P;
        if ("top" === o) p = g(this.bottom), y = this.bottom - h, w = p - f, k = g(t.top) + f, P = t.bottom;else if ("bottom" === o) p = g(this.top), k = t.top, P = g(t.bottom) - f, y = p + f, w = this.top + h;else if ("left" === o) p = g(this.right), _ = this.right - h, v = p - f, M = g(t.left) + f, S = t.right;else if ("right" === o) p = g(this.left), M = t.left, S = g(t.right) - f, _ = p + f, v = this.left + h;else if ("x" === e) {
          if ("center" === o) p = g((t.top + t.bottom) / 2 + .5);else if (U(o)) {
            var _t28 = Object.keys(o)[0],
              _e28 = o[_t28];
            p = g(this.chart.scales[_t28].getPixelForValue(_e28));
          }
          k = t.top, P = t.bottom, y = p + f, w = y + h;
        } else if ("y" === e) {
          if ("center" === o) p = g((t.left + t.right) / 2);else if (U(o)) {
            var _t29 = Object.keys(o)[0],
              _e29 = o[_t29];
            p = g(this.chart.scales[_t29].getPixelForValue(_e29));
          }
          _ = p - f, v = _ - h, M = t.left, S = t.right;
        }
        var D = K(s.ticks.maxTicksLimit, l),
          C = Math.max(1, Math.ceil(l / D));
        for (m = 0; m < l; m += C) {
          var _t30 = n.setContext(this.getContext(m)),
            _e30 = _t30.lineWidth,
            _s24 = _t30.color,
            _o19 = n.borderDash || [],
            _l11 = _t30.borderDashOffset,
            _h10 = _t30.tickWidth,
            _d5 = _t30.tickColor,
            _u = _t30.tickBorderDash || [],
            _f = _t30.tickBorderDashOffset;
          x = Es(this, m, a), void 0 !== x && (b = Kt(i, x, _e30), r ? _ = v = M = S = b : y = w = k = P = b, c.push({
            tx1: _,
            ty1: y,
            tx2: v,
            ty2: w,
            x1: M,
            y1: k,
            x2: S,
            y2: P,
            width: _e30,
            color: _s24,
            borderDash: _o19,
            borderDashOffset: _l11,
            tickWidth: _h10,
            tickColor: _d5,
            tickBorderDash: _u,
            tickBorderDashOffset: _f
          }));
        }
        return this._ticksLength = l, this._borderValue = p, c;
      }
    }, {
      key: "_computeLabelItems",
      value: function _computeLabelItems(t) {
        var e = this.axis,
          i = this.options,
          s = i.position,
          n = i.ticks,
          o = this.isHorizontal(),
          a = this.ticks,
          r = n.align,
          l = n.crossAlign,
          h = n.padding,
          c = n.mirror,
          d = Is(i.grid),
          u = d + h,
          f = c ? -h : u,
          g = -It(this.labelRotation),
          p = [];
        var m,
          x,
          b,
          _,
          y,
          v,
          w,
          M,
          k,
          S,
          P,
          D,
          C = "middle";
        if ("top" === s) v = this.bottom - f, w = this._getXAxisLabelAlignment();else if ("bottom" === s) v = this.top + f, w = this._getXAxisLabelAlignment();else if ("left" === s) {
          var _t31 = this._getYAxisLabelAlignment(d);
          w = _t31.textAlign, y = _t31.x;
        } else if ("right" === s) {
          var _t32 = this._getYAxisLabelAlignment(d);
          w = _t32.textAlign, y = _t32.x;
        } else if ("x" === e) {
          if ("center" === s) v = (t.top + t.bottom) / 2 + u;else if (U(s)) {
            var _t33 = Object.keys(s)[0],
              _e31 = s[_t33];
            v = this.chart.scales[_t33].getPixelForValue(_e31) + u;
          }
          w = this._getXAxisLabelAlignment();
        } else if ("y" === e) {
          if ("center" === s) y = (t.left + t.right) / 2 - u;else if (U(s)) {
            var _t34 = Object.keys(s)[0],
              _e32 = s[_t34];
            y = this.chart.scales[_t34].getPixelForValue(_e32);
          }
          w = this._getYAxisLabelAlignment(d).textAlign;
        }
        "y" === e && ("start" === r ? C = "top" : "end" === r && (C = "bottom"));
        var O = this._getLabelSizes();
        for (m = 0, x = a.length; m < x; ++m) {
          b = a[m], _ = b.label;
          var _t35 = n.setContext(this.getContext(m));
          M = this.getPixelForTick(m) + n.labelOffset, k = this._resolveTickFontOptions(m), S = k.lineHeight, P = Y(_) ? _.length : 1;
          var _e33 = P / 2,
            _i27 = _t35.color,
            _r9 = _t35.textStrokeColor,
            _h11 = _t35.textStrokeWidth;
          var _d6 = void 0;
          if (o ? (y = M, D = "top" === s ? "near" === l || 0 !== g ? -P * S + S / 2 : "center" === l ? -O.highest.height / 2 - _e33 * S + S : -O.highest.height + S / 2 : "near" === l || 0 !== g ? S / 2 : "center" === l ? O.highest.height / 2 - _e33 * S : O.highest.height - P * S, c && (D *= -1)) : (v = M, D = (1 - P) * S / 2), _t35.showLabelBackdrop) {
            var _e34 = Ne(_t35.backdropPadding),
              _i28 = O.heights[m],
              _s25 = O.widths[m];
            var _n17 = v + D - _e34.top,
              _o20 = y - _e34.left;
            switch (C) {
              case "middle":
                _n17 -= _i28 / 2;
                break;
              case "bottom":
                _n17 -= _i28;
            }
            switch (w) {
              case "center":
                _o20 -= _s25 / 2;
                break;
              case "right":
                _o20 -= _s25;
            }
            _d6 = {
              left: _o20,
              top: _n17,
              width: _s25 + _e34.width,
              height: _i28 + _e34.height,
              color: _t35.backdropColor
            };
          }
          p.push({
            rotation: g,
            label: _,
            font: k,
            color: _i27,
            strokeColor: _r9,
            strokeWidth: _h11,
            textOffset: D,
            textAlign: w,
            textBaseline: C,
            translation: [y, v],
            backdrop: _d6
          });
        }
        return p;
      }
    }, {
      key: "_getXAxisLabelAlignment",
      value: function _getXAxisLabelAlignment() {
        var _this$options5 = this.options,
          t = _this$options5.position,
          e = _this$options5.ticks;
        if (-It(this.labelRotation)) return "top" === t ? "left" : "right";
        var i = "center";
        return "start" === e.align ? i = "left" : "end" === e.align && (i = "right"), i;
      }
    }, {
      key: "_getYAxisLabelAlignment",
      value: function _getYAxisLabelAlignment(t) {
        var _this$options6 = this.options,
          e = _this$options6.position,
          _this$options6$ticks = _this$options6.ticks,
          i = _this$options6$ticks.crossAlign,
          s = _this$options6$ticks.mirror,
          n = _this$options6$ticks.padding,
          o = t + n,
          a = this._getLabelSizes().widest.width;
        var r, l;
        return "left" === e ? s ? (l = this.right + n, "near" === i ? r = "left" : "center" === i ? (r = "center", l += a / 2) : (r = "right", l += a)) : (l = this.right - o, "near" === i ? r = "right" : "center" === i ? (r = "center", l -= a / 2) : (r = "left", l = this.left)) : "right" === e ? s ? (l = this.left + n, "near" === i ? r = "right" : "center" === i ? (r = "center", l -= a / 2) : (r = "left", l -= a)) : (l = this.left + o, "near" === i ? r = "left" : "center" === i ? (r = "center", l += a / 2) : (r = "right", l = this.right)) : r = "right", {
          textAlign: r,
          x: l
        };
      }
    }, {
      key: "_computeLabelArea",
      value: function _computeLabelArea() {
        if (this.options.ticks.mirror) return;
        var t = this.chart,
          e = this.options.position;
        return "left" === e || "right" === e ? {
          top: 0,
          left: this.left,
          bottom: t.height,
          right: this.right
        } : "top" === e || "bottom" === e ? {
          top: this.top,
          left: 0,
          bottom: this.bottom,
          right: t.width
        } : void 0;
      }
    }, {
      key: "drawBackground",
      value: function drawBackground() {
        var t = this.ctx,
          e = this.options.backgroundColor,
          i = this.left,
          s = this.top,
          n = this.width,
          o = this.height;
        e && (t.save(), t.fillStyle = e, t.fillRect(i, s, n, o), t.restore());
      }
    }, {
      key: "getLineWidthForValue",
      value: function getLineWidthForValue(t) {
        var e = this.options.grid;
        if (!this._isVisible() || !e.display) return 0;
        var i = this.ticks.findIndex(function (e) {
          return e.value === t;
        });
        if (i >= 0) {
          return e.setContext(this.getContext(i)).lineWidth;
        }
        return 0;
      }
    }, {
      key: "drawGrid",
      value: function drawGrid(t) {
        var e = this.options.grid,
          i = this.ctx,
          s = this._gridLineItems || (this._gridLineItems = this._computeGridLineItems(t));
        var n, o;
        var a = function a(t, e, s) {
          s.width && s.color && (i.save(), i.lineWidth = s.width, i.strokeStyle = s.color, i.setLineDash(s.borderDash || []), i.lineDashOffset = s.borderDashOffset, i.beginPath(), i.moveTo(t.x, t.y), i.lineTo(e.x, e.y), i.stroke(), i.restore());
        };
        if (e.display) for (n = 0, o = s.length; n < o; ++n) {
          var _t36 = s[n];
          e.drawOnChartArea && a({
            x: _t36.x1,
            y: _t36.y1
          }, {
            x: _t36.x2,
            y: _t36.y2
          }, _t36), e.drawTicks && a({
            x: _t36.tx1,
            y: _t36.ty1
          }, {
            x: _t36.tx2,
            y: _t36.ty2
          }, {
            color: _t36.tickColor,
            width: _t36.tickWidth,
            borderDash: _t36.tickBorderDash,
            borderDashOffset: _t36.tickBorderDashOffset
          });
        }
      }
    }, {
      key: "drawBorder",
      value: function drawBorder() {
        var t = this.chart,
          e = this.ctx,
          i = this.options.grid,
          s = i.setContext(this.getContext()),
          n = i.drawBorder ? s.borderWidth : 0;
        if (!n) return;
        var o = i.setContext(this.getContext(0)).lineWidth,
          a = this._borderValue;
        var r, l, h, c;
        this.isHorizontal() ? (r = Kt(t, this.left, n) - n / 2, l = Kt(t, this.right, o) + o / 2, h = c = a) : (h = Kt(t, this.top, n) - n / 2, c = Kt(t, this.bottom, o) + o / 2, r = l = a), e.save(), e.lineWidth = s.borderWidth, e.strokeStyle = s.borderColor, e.beginPath(), e.moveTo(r, h), e.lineTo(l, c), e.stroke(), e.restore();
      }
    }, {
      key: "drawLabels",
      value: function drawLabels(t) {
        if (!this.options.ticks.display) return;
        var e = this.ctx,
          i = this._computeLabelArea();
        i && Qt(e, i);
        var s = this._labelItems || (this._labelItems = this._computeLabelItems(t));
        var n, o;
        for (n = 0, o = s.length; n < o; ++n) {
          var _t37 = s[n],
            _i29 = _t37.font,
            _o21 = _t37.label;
          _t37.backdrop && (e.fillStyle = _t37.backdrop.color, e.fillRect(_t37.backdrop.left, _t37.backdrop.top, _t37.backdrop.width, _t37.backdrop.height)), se(e, _o21, 0, _t37.textOffset, _i29, _t37);
        }
        i && te(e);
      }
    }, {
      key: "drawTitle",
      value: function drawTitle() {
        var t = this.ctx,
          _this$options7 = this.options,
          e = _this$options7.position,
          i = _this$options7.title,
          s = _this$options7.reverse;
        if (!i.display) return;
        var o = He(i.font),
          a = Ne(i.padding),
          r = i.align;
        var l = o.lineHeight / 2;
        "bottom" === e || "center" === e || U(e) ? (l += a.bottom, Y(i.text) && (l += o.lineHeight * (i.text.length - 1))) : l += a.top;
        var _ref6 = function (t, e, i, s) {
            var o = t.top,
              a = t.left,
              r = t.bottom,
              l = t.right,
              h = t.chart,
              c = h.chartArea,
              d = h.scales;
            var u,
              f,
              g,
              p = 0;
            var m = r - o,
              x = l - a;
            if (t.isHorizontal()) {
              if (f = n(s, a, l), U(i)) {
                var _t38 = Object.keys(i)[0],
                  _s26 = i[_t38];
                g = d[_t38].getPixelForValue(_s26) + m - e;
              } else g = "center" === i ? (c.bottom + c.top) / 2 + m - e : Ls(t, i, e);
              u = l - a;
            } else {
              if (U(i)) {
                var _t39 = Object.keys(i)[0],
                  _s27 = i[_t39];
                f = d[_t39].getPixelForValue(_s27) - x + e;
              } else f = "center" === i ? (c.left + c.right) / 2 - x + e : Ls(t, i, e);
              g = n(s, r, o), p = "left" === i ? -kt : kt;
            }
            return {
              titleX: f,
              titleY: g,
              maxWidth: u,
              rotation: p
            };
          }(this, l, e, r),
          h = _ref6.titleX,
          c = _ref6.titleY,
          d = _ref6.maxWidth,
          u = _ref6.rotation;
        se(t, i.text, 0, 0, o, {
          color: i.color,
          maxWidth: d,
          rotation: u,
          textAlign: Fs(r, e, s),
          textBaseline: "middle",
          translation: [h, c]
        });
      }
    }, {
      key: "draw",
      value: function draw(t) {
        this._isVisible() && (this.drawBackground(), this.drawGrid(t), this.drawBorder(), this.drawTitle(), this.drawLabels(t));
      }
    }, {
      key: "_layers",
      value: function _layers() {
        var _this6 = this;
        var t = this.options,
          e = t.ticks && t.ticks.z || 0,
          i = K(t.grid && t.grid.z, -1);
        return this._isVisible() && this.draw === Bs.prototype.draw ? [{
          z: i,
          draw: function draw(t) {
            _this6.drawBackground(), _this6.drawGrid(t), _this6.drawTitle();
          }
        }, {
          z: i + 1,
          draw: function draw() {
            _this6.drawBorder();
          }
        }, {
          z: e,
          draw: function draw(t) {
            _this6.drawLabels(t);
          }
        }] : [{
          z: e,
          draw: function draw(t) {
            _this6.draw(t);
          }
        }];
      }
    }, {
      key: "getMatchingVisibleMetas",
      value: function getMatchingVisibleMetas(t) {
        var e = this.chart.getSortedVisibleDatasetMetas(),
          i = this.axis + "AxisID",
          s = [];
        var n, o;
        for (n = 0, o = e.length; n < o; ++n) {
          var _o22 = e[n];
          _o22[i] !== this.id || t && _o22.type !== t || s.push(_o22);
        }
        return s;
      }
    }, {
      key: "_resolveTickFontOptions",
      value: function _resolveTickFontOptions(t) {
        return He(this.options.ticks.setContext(this.getContext(t)).font);
      }
    }, {
      key: "_maxDigits",
      value: function _maxDigits() {
        var t = this._resolveTickFontOptions(0).lineHeight;
        return (this.isHorizontal() ? this.width : this.height) / t;
      }
    }]);
  }(Ds);
  var Vs = /*#__PURE__*/function () {
    function Vs(t, e, i) {
      _classCallCheck(this, Vs);
      this.type = t, this.scope = e, this.override = i, this.items = Object.create(null);
    }
    return _createClass(Vs, [{
      key: "isForType",
      value: function isForType(t) {
        return Object.prototype.isPrototypeOf.call(this.type.prototype, t.prototype);
      }
    }, {
      key: "register",
      value: function register(t) {
        var e = Object.getPrototypeOf(t);
        var i;
        (function (t) {
          return "id" in t && "defaults" in t;
        })(e) && (i = this.register(e));
        var s = this.items,
          n = t.id,
          o = this.scope + "." + n;
        if (!n) throw new Error("class does not have id: " + t);
        return n in s || (s[n] = t, function (t, e, i) {
          var s = nt(Object.create(null), [i ? bt.get(i) : {}, bt.get(e), t.defaults]);
          bt.set(e, s), t.defaultRoutes && function (t, e) {
            Object.keys(e).forEach(function (i) {
              var s = i.split("."),
                n = s.pop(),
                o = [t].concat(s).join("."),
                a = e[i].split("."),
                r = a.pop(),
                l = a.join(".");
              bt.route(o, n, l, r);
            });
          }(e, t.defaultRoutes);
          t.descriptors && bt.describe(e, t.descriptors);
        }(t, o, i), this.override && bt.override(t.id, t.overrides)), o;
      }
    }, {
      key: "get",
      value: function get(t) {
        return this.items[t];
      }
    }, {
      key: "unregister",
      value: function unregister(t) {
        var e = this.items,
          i = t.id,
          s = this.scope;
        i in e && delete e[i], s && i in bt[s] && (delete bt[s][i], this.override && delete gt[i]);
      }
    }]);
  }();
  var Ws = new ( /*#__PURE__*/function () {
    function _class3() {
      _classCallCheck(this, _class3);
      this.controllers = new Vs(Ps, "datasets", !0), this.elements = new Vs(Ds, "elements"), this.plugins = new Vs(Object, "plugins"), this.scales = new Vs(Bs, "scales"), this._typedRegistries = [this.controllers, this.scales, this.elements];
    }
    return _createClass(_class3, [{
      key: "add",
      value: function add() {
        for (var _len4 = arguments.length, t = new Array(_len4), _key4 = 0; _key4 < _len4; _key4++) {
          t[_key4] = arguments[_key4];
        }
        this._each("register", t);
      }
    }, {
      key: "remove",
      value: function remove() {
        for (var _len5 = arguments.length, t = new Array(_len5), _key5 = 0; _key5 < _len5; _key5++) {
          t[_key5] = arguments[_key5];
        }
        this._each("unregister", t);
      }
    }, {
      key: "addControllers",
      value: function addControllers() {
        for (var _len6 = arguments.length, t = new Array(_len6), _key6 = 0; _key6 < _len6; _key6++) {
          t[_key6] = arguments[_key6];
        }
        this._each("register", t, this.controllers);
      }
    }, {
      key: "addElements",
      value: function addElements() {
        for (var _len7 = arguments.length, t = new Array(_len7), _key7 = 0; _key7 < _len7; _key7++) {
          t[_key7] = arguments[_key7];
        }
        this._each("register", t, this.elements);
      }
    }, {
      key: "addPlugins",
      value: function addPlugins() {
        for (var _len8 = arguments.length, t = new Array(_len8), _key8 = 0; _key8 < _len8; _key8++) {
          t[_key8] = arguments[_key8];
        }
        this._each("register", t, this.plugins);
      }
    }, {
      key: "addScales",
      value: function addScales() {
        for (var _len9 = arguments.length, t = new Array(_len9), _key9 = 0; _key9 < _len9; _key9++) {
          t[_key9] = arguments[_key9];
        }
        this._each("register", t, this.scales);
      }
    }, {
      key: "getController",
      value: function getController(t) {
        return this._get(t, this.controllers, "controller");
      }
    }, {
      key: "getElement",
      value: function getElement(t) {
        return this._get(t, this.elements, "element");
      }
    }, {
      key: "getPlugin",
      value: function getPlugin(t) {
        return this._get(t, this.plugins, "plugin");
      }
    }, {
      key: "getScale",
      value: function getScale(t) {
        return this._get(t, this.scales, "scale");
      }
    }, {
      key: "removeControllers",
      value: function removeControllers() {
        for (var _len10 = arguments.length, t = new Array(_len10), _key10 = 0; _key10 < _len10; _key10++) {
          t[_key10] = arguments[_key10];
        }
        this._each("unregister", t, this.controllers);
      }
    }, {
      key: "removeElements",
      value: function removeElements() {
        for (var _len11 = arguments.length, t = new Array(_len11), _key11 = 0; _key11 < _len11; _key11++) {
          t[_key11] = arguments[_key11];
        }
        this._each("unregister", t, this.elements);
      }
    }, {
      key: "removePlugins",
      value: function removePlugins() {
        for (var _len12 = arguments.length, t = new Array(_len12), _key12 = 0; _key12 < _len12; _key12++) {
          t[_key12] = arguments[_key12];
        }
        this._each("unregister", t, this.plugins);
      }
    }, {
      key: "removeScales",
      value: function removeScales() {
        for (var _len13 = arguments.length, t = new Array(_len13), _key13 = 0; _key13 < _len13; _key13++) {
          t[_key13] = arguments[_key13];
        }
        this._each("unregister", t, this.scales);
      }
    }, {
      key: "_each",
      value: function _each(t, e, i) {
        var _this7 = this;
        _toConsumableArray(e).forEach(function (e) {
          var s = i || _this7._getRegistryForType(e);
          i || s.isForType(e) || s === _this7.plugins && e.id ? _this7._exec(t, s, e) : Q(e, function (e) {
            var s = i || _this7._getRegistryForType(e);
            _this7._exec(t, s, e);
          });
        });
      }
    }, {
      key: "_exec",
      value: function _exec(t, e, i) {
        var s = ht(t);
        J(i["before" + s], [], i), e[t](i), J(i["after" + s], [], i);
      }
    }, {
      key: "_getRegistryForType",
      value: function _getRegistryForType(t) {
        for (var _e35 = 0; _e35 < this._typedRegistries.length; _e35++) {
          var _i30 = this._typedRegistries[_e35];
          if (_i30.isForType(t)) return _i30;
        }
        return this.plugins;
      }
    }, {
      key: "_get",
      value: function _get(t, e, i) {
        var s = e.get(t);
        if (void 0 === s) throw new Error('"' + t + '" is not a registered ' + i + ".");
        return s;
      }
    }]);
  }())();
  var Ns = /*#__PURE__*/function () {
    function Ns() {
      _classCallCheck(this, Ns);
      this._init = [];
    }
    return _createClass(Ns, [{
      key: "notify",
      value: function notify(t, e, i, s) {
        "beforeInit" === e && (this._init = this._createDescriptors(t, !0), this._notify(this._init, t, "install"));
        var n = s ? this._descriptors(t).filter(s) : this._descriptors(t),
          o = this._notify(n, t, e, i);
        return "afterDestroy" === e && (this._notify(n, t, "stop"), this._notify(this._init, t, "uninstall")), o;
      }
    }, {
      key: "_notify",
      value: function _notify(t, e, i, s) {
        s = s || {};
        var _iterator18 = _createForOfIteratorHelper(t),
          _step18;
        try {
          for (_iterator18.s(); !(_step18 = _iterator18.n()).done;) {
            var _n18 = _step18.value;
            var _t40 = _n18.plugin;
            if (!1 === J(_t40[i], [e, s, _n18.options], _t40) && s.cancelable) return !1;
          }
        } catch (err) {
          _iterator18.e(err);
        } finally {
          _iterator18.f();
        }
        return !0;
      }
    }, {
      key: "invalidate",
      value: function invalidate() {
        $(this._cache) || (this._oldCache = this._cache, this._cache = void 0);
      }
    }, {
      key: "_descriptors",
      value: function _descriptors(t) {
        if (this._cache) return this._cache;
        var e = this._cache = this._createDescriptors(t);
        return this._notifyStateChanges(t), e;
      }
    }, {
      key: "_createDescriptors",
      value: function _createDescriptors(t, e) {
        var i = t && t.config,
          s = K(i.options && i.options.plugins, {}),
          n = function (t) {
            var e = [],
              i = Object.keys(Ws.plugins.items);
            for (var _t41 = 0; _t41 < i.length; _t41++) e.push(Ws.getPlugin(i[_t41]));
            var s = t.plugins || [];
            for (var _t42 = 0; _t42 < s.length; _t42++) {
              var _i31 = s[_t42];
              -1 === e.indexOf(_i31) && e.push(_i31);
            }
            return e;
          }(i);
        return !1 !== s || e ? function (t, e, i, s) {
          var n = [],
            o = t.getContext();
          for (var _a13 = 0; _a13 < e.length; _a13++) {
            var _r10 = e[_a13],
              _l12 = Hs(i[_r10.id], s);
            null !== _l12 && n.push({
              plugin: _r10,
              options: js(t.config, _r10, _l12, o)
            });
          }
          return n;
        }(t, n, s, e) : [];
      }
    }, {
      key: "_notifyStateChanges",
      value: function _notifyStateChanges(t) {
        var e = this._oldCache || [],
          i = this._cache,
          s = function s(t, e) {
            return t.filter(function (t) {
              return !e.some(function (e) {
                return t.plugin.id === e.plugin.id;
              });
            });
          };
        this._notify(s(e, i), t, "stop"), this._notify(s(i, e), t, "start");
      }
    }]);
  }();
  function Hs(t, e) {
    return e || !1 !== t ? !0 === t ? {} : t : null;
  }
  function js(t, e, i, s) {
    var n = t.pluginScopeKeys(e),
      o = t.getOptionScopes(i, n);
    return t.createResolver(o, s, [""], {
      scriptable: !1,
      indexable: !1,
      allKeys: !0
    });
  }
  function $s(t, e) {
    var i = bt.datasets[t] || {};
    return ((e.datasets || {})[t] || {}).indexAxis || e.indexAxis || i.indexAxis || "x";
  }
  function Ys(t, e) {
    return "x" === t || "y" === t ? t : e.axis || ("top" === (i = e.position) || "bottom" === i ? "x" : "left" === i || "right" === i ? "y" : void 0) || t.charAt(0).toLowerCase();
    var i;
  }
  function Us(t) {
    var e = t.options || (t.options = {});
    e.plugins = K(e.plugins, {}), e.scales = function (t, e) {
      var i = gt[t.type] || {
          scales: {}
        },
        s = e.scales || {},
        n = $s(t.type, e),
        o = Object.create(null),
        a = Object.create(null);
      return Object.keys(s).forEach(function (t) {
        var e = s[t];
        if (!U(e)) return console.error("Invalid scale configuration for scale: ".concat(t));
        if (e._proxy) return console.warn("Ignoring resolver passed as options for scale: ".concat(t));
        var r = Ys(t, e),
          l = function (t, e) {
            return t === e ? "_index_" : "_value_";
          }(r, n),
          h = i.scales || {};
        o[r] = o[r] || t, a[t] = ot(Object.create(null), [{
          axis: r
        }, e, h[r], h[l]]);
      }), t.data.datasets.forEach(function (i) {
        var n = i.type || t.type,
          r = i.indexAxis || $s(n, e),
          l = (gt[n] || {}).scales || {};
        Object.keys(l).forEach(function (t) {
          var e = function (t, e) {
              var i = t;
              return "_index_" === t ? i = e : "_value_" === t && (i = "x" === e ? "y" : "x"), i;
            }(t, r),
            n = i[e + "AxisID"] || o[e] || e;
          a[n] = a[n] || Object.create(null), ot(a[n], [{
            axis: e
          }, s[n], l[t]]);
        });
      }), Object.keys(a).forEach(function (t) {
        var e = a[t];
        ot(e, [bt.scales[e.type], bt.scale]);
      }), a;
    }(t, e);
  }
  function Xs(t) {
    return (t = t || {}).datasets = t.datasets || [], t.labels = t.labels || [], t;
  }
  var qs = new Map(),
    Ks = new Set();
  function Gs(t, e) {
    var i = qs.get(t);
    return i || (i = e(), qs.set(t, i), Ks.add(i)), i;
  }
  var Zs = function Zs(t, e, i) {
    var s = lt(e, i);
    void 0 !== s && t.add(s);
  };
  var Js = /*#__PURE__*/function () {
    function Js(t) {
      _classCallCheck(this, Js);
      this._config = function (t) {
        return (t = t || {}).data = Xs(t.data), Us(t), t;
      }(t), this._scopeCache = new Map(), this._resolverCache = new Map();
    }
    return _createClass(Js, [{
      key: "platform",
      get: function get() {
        return this._config.platform;
      }
    }, {
      key: "type",
      get: function get() {
        return this._config.type;
      },
      set: function set(t) {
        this._config.type = t;
      }
    }, {
      key: "data",
      get: function get() {
        return this._config.data;
      },
      set: function set(t) {
        this._config.data = Xs(t);
      }
    }, {
      key: "options",
      get: function get() {
        return this._config.options;
      },
      set: function set(t) {
        this._config.options = t;
      }
    }, {
      key: "plugins",
      get: function get() {
        return this._config.plugins;
      }
    }, {
      key: "update",
      value: function update() {
        var t = this._config;
        this.clearCache(), Us(t);
      }
    }, {
      key: "clearCache",
      value: function clearCache() {
        this._scopeCache.clear(), this._resolverCache.clear();
      }
    }, {
      key: "datasetScopeKeys",
      value: function datasetScopeKeys(t) {
        return Gs(t, function () {
          return [["datasets.".concat(t), ""]];
        });
      }
    }, {
      key: "datasetAnimationScopeKeys",
      value: function datasetAnimationScopeKeys(t, e) {
        return Gs("".concat(t, ".transition.").concat(e), function () {
          return [["datasets.".concat(t, ".transitions.").concat(e), "transitions.".concat(e)], ["datasets.".concat(t), ""]];
        });
      }
    }, {
      key: "datasetElementScopeKeys",
      value: function datasetElementScopeKeys(t, e) {
        return Gs("".concat(t, "-").concat(e), function () {
          return [["datasets.".concat(t, ".elements.").concat(e), "datasets.".concat(t), "elements.".concat(e), ""]];
        });
      }
    }, {
      key: "pluginScopeKeys",
      value: function pluginScopeKeys(t) {
        var e = t.id;
        return Gs("".concat(this.type, "-plugin-").concat(e), function () {
          return [["plugins.".concat(e)].concat(_toConsumableArray(t.additionalOptionScopes || []))];
        });
      }
    }, {
      key: "_cachedScopes",
      value: function _cachedScopes(t, e) {
        var i = this._scopeCache;
        var s = i.get(t);
        return s && !e || (s = new Map(), i.set(t, s)), s;
      }
    }, {
      key: "getOptionScopes",
      value: function getOptionScopes(t, e, i) {
        var s = this.options,
          n = this.type,
          o = this._cachedScopes(t, i),
          a = o.get(e);
        if (a) return a;
        var r = new Set();
        e.forEach(function (e) {
          t && (r.add(t), e.forEach(function (e) {
            return Zs(r, t, e);
          })), e.forEach(function (t) {
            return Zs(r, s, t);
          }), e.forEach(function (t) {
            return Zs(r, gt[n] || {}, t);
          }), e.forEach(function (t) {
            return Zs(r, bt, t);
          }), e.forEach(function (t) {
            return Zs(r, pt, t);
          });
        });
        var l = Array.from(r);
        return 0 === l.length && l.push(Object.create(null)), Ks.has(e) && o.set(e, l), l;
      }
    }, {
      key: "chartOptionScopes",
      value: function chartOptionScopes() {
        var t = this.options,
          e = this.type;
        return [t, gt[e] || {}, bt.datasets[e] || {}, {
          type: e
        }, bt, pt];
      }
    }, {
      key: "resolveNamedOptions",
      value: function resolveNamedOptions(t, e, i) {
        var s = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : [""];
        var n = {
            $shared: !0
          },
          _Qs = Qs(this._resolverCache, t, s),
          o = _Qs.resolver,
          a = _Qs.subPrefixes;
        var r = o;
        if (function (t, e) {
          var _ri = ri(t),
            i = _ri.isScriptable,
            s = _ri.isIndexable;
          var _iterator19 = _createForOfIteratorHelper(e),
            _step19;
          try {
            for (_iterator19.s(); !(_step19 = _iterator19.n()).done;) {
              var _n19 = _step19.value;
              var _e36 = i(_n19),
                _o23 = s(_n19),
                _a14 = (_o23 || _e36) && t[_n19];
              if (_e36 && (dt(_a14) || tn(_a14)) || _o23 && Y(_a14)) return !0;
            }
          } catch (err) {
            _iterator19.e(err);
          } finally {
            _iterator19.f();
          }
          return !1;
        }(o, e)) {
          n.$shared = !1;
          r = ai(o, i = dt(i) ? i() : i, this.createResolver(t, i, a));
        }
        var _iterator20 = _createForOfIteratorHelper(e),
          _step20;
        try {
          for (_iterator20.s(); !(_step20 = _iterator20.n()).done;) {
            var _t43 = _step20.value;
            n[_t43] = r[_t43];
          }
        } catch (err) {
          _iterator20.e(err);
        } finally {
          _iterator20.f();
        }
        return n;
      }
    }, {
      key: "createResolver",
      value: function createResolver(t, e) {
        var i = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : [""];
        var s = arguments.length > 3 ? arguments[3] : undefined;
        var _Qs2 = Qs(this._resolverCache, t, i),
          n = _Qs2.resolver;
        return U(e) ? ai(n, e, void 0, s) : n;
      }
    }]);
  }();
  function Qs(t, e, i) {
    var s = t.get(e);
    s || (s = new Map(), t.set(e, s));
    var n = i.join();
    var o = s.get(n);
    if (!o) {
      o = {
        resolver: oi(e, i),
        subPrefixes: i.filter(function (t) {
          return !t.toLowerCase().includes("hover");
        })
      }, s.set(n, o);
    }
    return o;
  }
  var tn = function tn(t) {
    return U(t) && Object.getOwnPropertyNames(t).reduce(function (e, i) {
      return e || dt(t[i]);
    }, !1);
  };
  var en = ["top", "bottom", "left", "right", "chartArea"];
  function sn(t, e) {
    return "top" === t || "bottom" === t || -1 === en.indexOf(t) && "x" === e;
  }
  function nn(t, e) {
    return function (i, s) {
      return i[t] === s[t] ? i[e] - s[e] : i[t] - s[t];
    };
  }
  function on(t) {
    var e = t.chart,
      i = e.options.animation;
    e.notifyPlugins("afterRender"), J(i && i.onComplete, [t], e);
  }
  function an(t) {
    var e = t.chart,
      i = e.options.animation;
    J(i && i.onProgress, [t], e);
  }
  function rn(t) {
    return ge() && "string" == typeof t ? t = document.getElementById(t) : t && t.length && (t = t[0]), t && t.canvas && (t = t.canvas), t;
  }
  var ln = {},
    hn = function hn(t) {
      var e = rn(t);
      return Object.values(ln).filter(function (t) {
        return t.canvas === e;
      }).pop();
    };
  function cn(t, e, i) {
    var s = Object.keys(t);
    for (var _i32 = 0, _s28 = s; _i32 < _s28.length; _i32++) {
      var _n20 = _s28[_i32];
      var _s29 = +_n20;
      if (_s29 >= e) {
        var _o24 = t[_n20];
        delete t[_n20], (i > 0 || _s29 > e) && (t[_s29 + i] = _o24);
      }
    }
  }
  var dn = /*#__PURE__*/function () {
    function dn(t, e) {
      var _this8 = this;
      _classCallCheck(this, dn);
      var s = this.config = new Js(e),
        n = rn(t),
        o = hn(n);
      if (o) throw new Error("Canvas is already in use. Chart with ID '" + o.id + "' must be destroyed before the canvas can be reused.");
      var r = s.createResolver(s.chartOptionScopes(), this.getContext());
      this.platform = new (s.platform || ls(n))(), this.platform.updateConfig(s);
      var l = this.platform.acquireContext(n, r.aspectRatio),
        h = l && l.canvas,
        c = h && h.height,
        d = h && h.width;
      this.id = j(), this.ctx = l, this.canvas = h, this.width = d, this.height = c, this._options = r, this._aspectRatio = this.aspectRatio, this._layers = [], this._metasets = [], this._stacks = void 0, this.boxes = [], this.currentDevicePixelRatio = void 0, this.chartArea = void 0, this._active = [], this._lastEvent = void 0, this._listeners = {}, this._responsiveListeners = void 0, this._sortedMetasets = [], this.scales = {}, this._plugins = new Ns(), this.$proxies = {}, this._hiddenIndices = {}, this.attached = !1, this._animationsDisabled = void 0, this.$context = void 0, this._doResize = i(function (t) {
        return _this8.update(t);
      }, r.resizeDelay || 0), this._dataChanges = [], ln[this.id] = this, l && h ? (a.listen(this, "complete", on), a.listen(this, "progress", an), this._initialize(), this.attached && this.update()) : console.error("Failed to create chart: can't acquire context from the given item");
    }
    return _createClass(dn, [{
      key: "aspectRatio",
      get: function get() {
        var _this$options8 = this.options,
          t = _this$options8.aspectRatio,
          e = _this$options8.maintainAspectRatio,
          i = this.width,
          s = this.height,
          n = this._aspectRatio;
        return $(t) ? e && n ? n : s ? i / s : null : t;
      }
    }, {
      key: "data",
      get: function get() {
        return this.config.data;
      },
      set: function set(t) {
        this.config.data = t;
      }
    }, {
      key: "options",
      get: function get() {
        return this._options;
      },
      set: function set(t) {
        this.config.options = t;
      }
    }, {
      key: "_initialize",
      value: function _initialize() {
        return this.notifyPlugins("beforeInit"), this.options.responsive ? this.resize() : ke(this, this.options.devicePixelRatio), this.bindEvents(), this.notifyPlugins("afterInit"), this;
      }
    }, {
      key: "clear",
      value: function clear() {
        return Gt(this.canvas, this.ctx), this;
      }
    }, {
      key: "stop",
      value: function stop() {
        return a.stop(this), this;
      }
    }, {
      key: "resize",
      value: function resize(t, e) {
        a.running(this) ? this._resizeBeforeDraw = {
          width: t,
          height: e
        } : this._resize(t, e);
      }
    }, {
      key: "_resize",
      value: function _resize(t, e) {
        var i = this.options,
          s = this.canvas,
          n = i.maintainAspectRatio && this.aspectRatio,
          o = this.platform.getMaximumSize(s, t, e, n),
          a = i.devicePixelRatio || this.platform.getDevicePixelRatio(),
          r = this.width ? "resize" : "attach";
        this.width = o.width, this.height = o.height, this._aspectRatio = this.aspectRatio, ke(this, a, !0) && (this.notifyPlugins("resize", {
          size: o
        }), J(i.onResize, [this, o], this), this.attached && this._doResize(r) && this.render());
      }
    }, {
      key: "ensureScalesHaveIDs",
      value: function ensureScalesHaveIDs() {
        Q(this.options.scales || {}, function (t, e) {
          t.id = e;
        });
      }
    }, {
      key: "buildOrUpdateScales",
      value: function buildOrUpdateScales() {
        var _this9 = this;
        var t = this.options,
          e = t.scales,
          i = this.scales,
          s = Object.keys(i).reduce(function (t, e) {
            return t[e] = !1, t;
          }, {});
        var n = [];
        e && (n = n.concat(Object.keys(e).map(function (t) {
          var i = e[t],
            s = Ys(t, i),
            n = "r" === s,
            o = "x" === s;
          return {
            options: i,
            dposition: n ? "chartArea" : o ? "bottom" : "left",
            dtype: n ? "radialLinear" : o ? "category" : "linear"
          };
        }))), Q(n, function (e) {
          var n = e.options,
            o = n.id,
            a = Ys(o, n),
            r = K(n.type, e.dtype);
          void 0 !== n.position && sn(n.position, a) === sn(e.dposition) || (n.position = e.dposition), s[o] = !0;
          var l = null;
          if (o in i && i[o].type === r) l = i[o];else {
            l = new (Ws.getScale(r))({
              id: o,
              type: r,
              ctx: _this9.ctx,
              chart: _this9
            }), i[l.id] = l;
          }
          l.init(n, t);
        }), Q(s, function (t, e) {
          t || delete i[e];
        }), Q(i, function (t) {
          ni.configure(_this9, t, t.options), ni.addBox(_this9, t);
        });
      }
    }, {
      key: "_updateMetasets",
      value: function _updateMetasets() {
        var t = this._metasets,
          e = this.data.datasets.length,
          i = t.length;
        if (t.sort(function (t, e) {
          return t.index - e.index;
        }), i > e) {
          for (var _t44 = e; _t44 < i; ++_t44) this._destroyDatasetMeta(_t44);
          t.splice(e, i - e);
        }
        this._sortedMetasets = t.slice(0).sort(nn("order", "index"));
      }
    }, {
      key: "_removeUnreferencedMetasets",
      value: function _removeUnreferencedMetasets() {
        var _this10 = this;
        var t = this._metasets,
          e = this.data.datasets;
        t.length > e.length && delete this._stacks, t.forEach(function (t, i) {
          0 === e.filter(function (e) {
            return e === t._dataset;
          }).length && _this10._destroyDatasetMeta(i);
        });
      }
    }, {
      key: "buildOrUpdateControllers",
      value: function buildOrUpdateControllers() {
        var t = [],
          e = this.data.datasets;
        var i, s;
        for (this._removeUnreferencedMetasets(), i = 0, s = e.length; i < s; i++) {
          var _s30 = e[i];
          var _n21 = this.getDatasetMeta(i);
          var _o25 = _s30.type || this.config.type;
          if (_n21.type && _n21.type !== _o25 && (this._destroyDatasetMeta(i), _n21 = this.getDatasetMeta(i)), _n21.type = _o25, _n21.indexAxis = _s30.indexAxis || $s(_o25, this.options), _n21.order = _s30.order || 0, _n21.index = i, _n21.label = "" + _s30.label, _n21.visible = this.isDatasetVisible(i), _n21.controller) _n21.controller.updateIndex(i), _n21.controller.linkScales();else {
            var _e37 = Ws.getController(_o25),
              _bt$datasets$_o = bt.datasets[_o25],
              _s31 = _bt$datasets$_o.datasetElementType,
              _a15 = _bt$datasets$_o.dataElementType;
            Object.assign(_e37.prototype, {
              dataElementType: Ws.getElement(_a15),
              datasetElementType: _s31 && Ws.getElement(_s31)
            }), _n21.controller = new _e37(this, i), t.push(_n21.controller);
          }
        }
        return this._updateMetasets(), t;
      }
    }, {
      key: "_resetElements",
      value: function _resetElements() {
        var _this11 = this;
        Q(this.data.datasets, function (t, e) {
          _this11.getDatasetMeta(e).controller.reset();
        }, this);
      }
    }, {
      key: "reset",
      value: function reset() {
        this._resetElements(), this.notifyPlugins("reset");
      }
    }, {
      key: "update",
      value: function update(t) {
        var e = this.config;
        e.update();
        var i = this._options = e.createResolver(e.chartOptionScopes(), this.getContext()),
          s = this._animationsDisabled = !i.animation;
        if (this._updateScales(), this._checkEventBindings(), this._updateHiddenIndices(), this._plugins.invalidate(), !1 === this.notifyPlugins("beforeUpdate", {
          mode: t,
          cancelable: !0
        })) return;
        var n = this.buildOrUpdateControllers();
        this.notifyPlugins("beforeElementsUpdate");
        var o = 0;
        for (var _t45 = 0, _e38 = this.data.datasets.length; _t45 < _e38; _t45++) {
          var _this$getDatasetMeta = this.getDatasetMeta(_t45),
            _e39 = _this$getDatasetMeta.controller,
            _i33 = !s && -1 === n.indexOf(_e39);
          _e39.buildOrUpdateElements(_i33), o = Math.max(+_e39.getMaxOverflow(), o);
        }
        o = this._minPadding = i.layout.autoPadding ? o : 0, this._updateLayout(o), s || Q(n, function (t) {
          t.reset();
        }), this._updateDatasets(t), this.notifyPlugins("afterUpdate", {
          mode: t
        }), this._layers.sort(nn("z", "_idx"));
        var a = this._active,
          r = this._lastEvent;
        r ? this._eventHandler(r, !0) : a.length && this._updateHoverStyles(a, a, !0), this.render();
      }
    }, {
      key: "_updateScales",
      value: function _updateScales() {
        var _this12 = this;
        Q(this.scales, function (t) {
          ni.removeBox(_this12, t);
        }), this.ensureScalesHaveIDs(), this.buildOrUpdateScales();
      }
    }, {
      key: "_checkEventBindings",
      value: function _checkEventBindings() {
        var t = this.options,
          e = new Set(Object.keys(this._listeners)),
          i = new Set(t.events);
        ut(e, i) && !!this._responsiveListeners === t.responsive || (this.unbindEvents(), this.bindEvents());
      }
    }, {
      key: "_updateHiddenIndices",
      value: function _updateHiddenIndices() {
        var t = this._hiddenIndices,
          e = this._getUniformDataChanges() || [];
        var _iterator21 = _createForOfIteratorHelper(e),
          _step21;
        try {
          for (_iterator21.s(); !(_step21 = _iterator21.n()).done;) {
            var _step21$value = _step21.value,
              _i34 = _step21$value.method,
              _s32 = _step21$value.start,
              _n22 = _step21$value.count;
            cn(t, _s32, "_removeElements" === _i34 ? -_n22 : _n22);
          }
        } catch (err) {
          _iterator21.e(err);
        } finally {
          _iterator21.f();
        }
      }
    }, {
      key: "_getUniformDataChanges",
      value: function _getUniformDataChanges() {
        var t = this._dataChanges;
        if (!t || !t.length) return;
        this._dataChanges = [];
        var e = this.data.datasets.length,
          i = function i(e) {
            return new Set(t.filter(function (t) {
              return t[0] === e;
            }).map(function (t, e) {
              return e + "," + t.splice(1).join(",");
            }));
          },
          s = i(0);
        for (var _t46 = 1; _t46 < e; _t46++) if (!ut(s, i(_t46))) return;
        return Array.from(s).map(function (t) {
          return t.split(",");
        }).map(function (t) {
          return {
            method: t[1],
            start: +t[2],
            count: +t[3]
          };
        });
      }
    }, {
      key: "_updateLayout",
      value: function _updateLayout(t) {
        var _this13 = this;
        if (!1 === this.notifyPlugins("beforeLayout", {
          cancelable: !0
        })) return;
        ni.update(this, this.width, this.height, t);
        var e = this.chartArea,
          i = e.width <= 0 || e.height <= 0;
        this._layers = [], Q(this.boxes, function (t) {
          var _this13$_layers;
          i && "chartArea" === t.position || (t.configure && t.configure(), (_this13$_layers = _this13._layers).push.apply(_this13$_layers, _toConsumableArray(t._layers())));
        }, this), this._layers.forEach(function (t, e) {
          t._idx = e;
        }), this.notifyPlugins("afterLayout");
      }
    }, {
      key: "_updateDatasets",
      value: function _updateDatasets(t) {
        if (!1 !== this.notifyPlugins("beforeDatasetsUpdate", {
          mode: t,
          cancelable: !0
        })) {
          for (var _t47 = 0, _e40 = this.data.datasets.length; _t47 < _e40; ++_t47) this.getDatasetMeta(_t47).controller.configure();
          for (var _e41 = 0, _i35 = this.data.datasets.length; _e41 < _i35; ++_e41) this._updateDataset(_e41, dt(t) ? t({
            datasetIndex: _e41
          }) : t);
          this.notifyPlugins("afterDatasetsUpdate", {
            mode: t
          });
        }
      }
    }, {
      key: "_updateDataset",
      value: function _updateDataset(t, e) {
        var i = this.getDatasetMeta(t),
          s = {
            meta: i,
            index: t,
            mode: e,
            cancelable: !0
          };
        !1 !== this.notifyPlugins("beforeDatasetUpdate", s) && (i.controller._update(e), s.cancelable = !1, this.notifyPlugins("afterDatasetUpdate", s));
      }
    }, {
      key: "render",
      value: function render() {
        !1 !== this.notifyPlugins("beforeRender", {
          cancelable: !0
        }) && (a.has(this) ? this.attached && !a.running(this) && a.start(this) : (this.draw(), on({
          chart: this
        })));
      }
    }, {
      key: "draw",
      value: function draw() {
        var t;
        if (this._resizeBeforeDraw) {
          var _this$_resizeBeforeDr = this._resizeBeforeDraw,
            _t48 = _this$_resizeBeforeDr.width,
            _e42 = _this$_resizeBeforeDr.height;
          this._resize(_t48, _e42), this._resizeBeforeDraw = null;
        }
        if (this.clear(), this.width <= 0 || this.height <= 0) return;
        if (!1 === this.notifyPlugins("beforeDraw", {
          cancelable: !0
        })) return;
        var e = this._layers;
        for (t = 0; t < e.length && e[t].z <= 0; ++t) e[t].draw(this.chartArea);
        for (this._drawDatasets(); t < e.length; ++t) e[t].draw(this.chartArea);
        this.notifyPlugins("afterDraw");
      }
    }, {
      key: "_getSortedDatasetMetas",
      value: function _getSortedDatasetMetas(t) {
        var e = this._sortedMetasets,
          i = [];
        var s, n;
        for (s = 0, n = e.length; s < n; ++s) {
          var _n23 = e[s];
          t && !_n23.visible || i.push(_n23);
        }
        return i;
      }
    }, {
      key: "getSortedVisibleDatasetMetas",
      value: function getSortedVisibleDatasetMetas() {
        return this._getSortedDatasetMetas(!0);
      }
    }, {
      key: "_drawDatasets",
      value: function _drawDatasets() {
        if (!1 === this.notifyPlugins("beforeDatasetsDraw", {
          cancelable: !0
        })) return;
        var t = this.getSortedVisibleDatasetMetas();
        for (var _e43 = t.length - 1; _e43 >= 0; --_e43) this._drawDataset(t[_e43]);
        this.notifyPlugins("afterDatasetsDraw");
      }
    }, {
      key: "_drawDataset",
      value: function _drawDataset(t) {
        var e = this.ctx,
          i = t._clip,
          s = !i.disabled,
          n = this.chartArea,
          o = {
            meta: t,
            index: t.index,
            cancelable: !0
          };
        !1 !== this.notifyPlugins("beforeDatasetDraw", o) && (s && Qt(e, {
          left: !1 === i.left ? 0 : n.left - i.left,
          right: !1 === i.right ? this.width : n.right + i.right,
          top: !1 === i.top ? 0 : n.top - i.top,
          bottom: !1 === i.bottom ? this.height : n.bottom + i.bottom
        }), t.controller.draw(), s && te(e), o.cancelable = !1, this.notifyPlugins("afterDatasetDraw", o));
      }
    }, {
      key: "getElementsAtEventForMode",
      value: function getElementsAtEventForMode(t, e, i, s) {
        var n = Ee.modes[e];
        return "function" == typeof n ? n(this, t, i, s) : [];
      }
    }, {
      key: "getDatasetMeta",
      value: function getDatasetMeta(t) {
        var e = this.data.datasets[t],
          i = this._metasets;
        var s = i.filter(function (t) {
          return t && t._dataset === e;
        }).pop();
        return s || (s = {
          type: null,
          data: [],
          dataset: null,
          controller: null,
          hidden: null,
          xAxisID: null,
          yAxisID: null,
          order: e && e.order || 0,
          index: t,
          _dataset: e,
          _parsed: [],
          _sorted: !1
        }, i.push(s)), s;
      }
    }, {
      key: "getContext",
      value: function getContext() {
        return this.$context || (this.$context = Ye(null, {
          chart: this,
          type: "chart"
        }));
      }
    }, {
      key: "getVisibleDatasetCount",
      value: function getVisibleDatasetCount() {
        return this.getSortedVisibleDatasetMetas().length;
      }
    }, {
      key: "isDatasetVisible",
      value: function isDatasetVisible(t) {
        var e = this.data.datasets[t];
        if (!e) return !1;
        var i = this.getDatasetMeta(t);
        return "boolean" == typeof i.hidden ? !i.hidden : !e.hidden;
      }
    }, {
      key: "setDatasetVisibility",
      value: function setDatasetVisibility(t, e) {
        this.getDatasetMeta(t).hidden = !e;
      }
    }, {
      key: "toggleDataVisibility",
      value: function toggleDataVisibility(t) {
        this._hiddenIndices[t] = !this._hiddenIndices[t];
      }
    }, {
      key: "getDataVisibility",
      value: function getDataVisibility(t) {
        return !this._hiddenIndices[t];
      }
    }, {
      key: "_updateVisibility",
      value: function _updateVisibility(t, e, i) {
        var s = i ? "show" : "hide",
          n = this.getDatasetMeta(t),
          o = n.controller._resolveAnimations(void 0, s);
        ct(e) ? (n.data[e].hidden = !i, this.update()) : (this.setDatasetVisibility(t, i), o.update(n, {
          visible: i
        }), this.update(function (e) {
          return e.datasetIndex === t ? s : void 0;
        }));
      }
    }, {
      key: "hide",
      value: function hide(t, e) {
        this._updateVisibility(t, e, !1);
      }
    }, {
      key: "show",
      value: function show(t, e) {
        this._updateVisibility(t, e, !0);
      }
    }, {
      key: "_destroyDatasetMeta",
      value: function _destroyDatasetMeta(t) {
        var e = this._metasets[t];
        e && e.controller && e.controller._destroy(), delete this._metasets[t];
      }
    }, {
      key: "_stop",
      value: function _stop() {
        var t, e;
        for (this.stop(), a.remove(this), t = 0, e = this.data.datasets.length; t < e; ++t) this._destroyDatasetMeta(t);
      }
    }, {
      key: "destroy",
      value: function destroy() {
        this.notifyPlugins("beforeDestroy");
        var t = this.canvas,
          e = this.ctx;
        this._stop(), this.config.clearCache(), t && (this.unbindEvents(), Gt(t, e), this.platform.releaseContext(e), this.canvas = null, this.ctx = null), this.notifyPlugins("destroy"), delete ln[this.id], this.notifyPlugins("afterDestroy");
      }
    }, {
      key: "toBase64Image",
      value: function toBase64Image() {
        var _this$canvas;
        return (_this$canvas = this.canvas).toDataURL.apply(_this$canvas, arguments);
      }
    }, {
      key: "bindEvents",
      value: function bindEvents() {
        this.bindUserEvents(), this.options.responsive ? this.bindResponsiveEvents() : this.attached = !0;
      }
    }, {
      key: "bindUserEvents",
      value: function bindUserEvents() {
        var _this14 = this;
        var t = this._listeners,
          e = this.platform,
          i = function i(_i36, s) {
            e.addEventListener(_this14, _i36, s), t[_i36] = s;
          },
          s = function s(t, e, i) {
            t.offsetX = e, t.offsetY = i, _this14._eventHandler(t);
          };
        Q(this.options.events, function (t) {
          return i(t, s);
        });
      }
    }, {
      key: "bindResponsiveEvents",
      value: function bindResponsiveEvents() {
        var _this15 = this;
        this._responsiveListeners || (this._responsiveListeners = {});
        var t = this._responsiveListeners,
          e = this.platform,
          i = function i(_i37, s) {
            e.addEventListener(_this15, _i37, s), t[_i37] = s;
          },
          s = function s(i, _s33) {
            t[i] && (e.removeEventListener(_this15, i, _s33), delete t[i]);
          },
          n = function n(t, e) {
            _this15.canvas && _this15.resize(t, e);
          };
        var o;
        var a = function a() {
          s("attach", a), _this15.attached = !0, _this15.resize(), i("resize", n), i("detach", o);
        };
        o = function o() {
          _this15.attached = !1, s("resize", n), _this15._stop(), _this15._resize(0, 0), i("attach", a);
        }, e.isAttached(this.canvas) ? a() : o();
      }
    }, {
      key: "unbindEvents",
      value: function unbindEvents() {
        var _this16 = this;
        Q(this._listeners, function (t, e) {
          _this16.platform.removeEventListener(_this16, e, t);
        }), this._listeners = {}, Q(this._responsiveListeners, function (t, e) {
          _this16.platform.removeEventListener(_this16, e, t);
        }), this._responsiveListeners = void 0;
      }
    }, {
      key: "updateHoverStyle",
      value: function updateHoverStyle(t, e, i) {
        var s = i ? "set" : "remove";
        var n, o, a, r;
        for ("dataset" === e && (n = this.getDatasetMeta(t[0].datasetIndex), n.controller["_" + s + "DatasetHoverStyle"]()), a = 0, r = t.length; a < r; ++a) {
          o = t[a];
          var _e44 = o && this.getDatasetMeta(o.datasetIndex).controller;
          _e44 && _e44[s + "HoverStyle"](o.element, o.datasetIndex, o.index);
        }
      }
    }, {
      key: "getActiveElements",
      value: function getActiveElements() {
        return this._active || [];
      }
    }, {
      key: "setActiveElements",
      value: function setActiveElements(t) {
        var _this17 = this;
        var e = this._active || [],
          i = t.map(function (_ref7) {
            var t = _ref7.datasetIndex,
              e = _ref7.index;
            var i = _this17.getDatasetMeta(t);
            if (!i) throw new Error("No dataset found at index " + t);
            return {
              datasetIndex: t,
              element: i.data[e],
              index: e
            };
          });
        !tt(i, e) && (this._active = i, this._lastEvent = null, this._updateHoverStyles(i, e));
      }
    }, {
      key: "notifyPlugins",
      value: function notifyPlugins(t, e, i) {
        return this._plugins.notify(this, t, e, i);
      }
    }, {
      key: "_updateHoverStyles",
      value: function _updateHoverStyles(t, e, i) {
        var s = this.options.hover,
          n = function n(t, e) {
            return t.filter(function (t) {
              return !e.some(function (e) {
                return t.datasetIndex === e.datasetIndex && t.index === e.index;
              });
            });
          },
          o = n(e, t),
          a = i ? t : n(t, e);
        o.length && this.updateHoverStyle(o, s.mode, !1), a.length && s.mode && this.updateHoverStyle(a, s.mode, !0);
      }
    }, {
      key: "_eventHandler",
      value: function _eventHandler(t, e) {
        var _this18 = this;
        var i = {
            event: t,
            replay: e,
            cancelable: !0,
            inChartArea: Jt(t, this.chartArea, this._minPadding)
          },
          s = function s(e) {
            return (e.options.events || _this18.options.events).includes(t["native"].type);
          };
        if (!1 === this.notifyPlugins("beforeEvent", i, s)) return;
        var n = this._handleEvent(t, e, i.inChartArea);
        return i.cancelable = !1, this.notifyPlugins("afterEvent", i, s), (n || i.changed) && this.render(), this;
      }
    }, {
      key: "_handleEvent",
      value: function _handleEvent(t, e, i) {
        var _this$_active = this._active,
          s = _this$_active === void 0 ? [] : _this$_active,
          n = this.options,
          o = e,
          a = this._getActiveElements(t, s, i, o),
          r = ft(t),
          l = function (t, e, i, s) {
            return i && "mouseout" !== t.type ? s ? e : t : null;
          }(t, this._lastEvent, i, r);
        i && (this._lastEvent = null, J(n.onHover, [t, a, this], this), r && J(n.onClick, [t, a, this], this));
        var h = !tt(a, s);
        return (h || e) && (this._active = a, this._updateHoverStyles(a, s, e)), this._lastEvent = l, h;
      }
    }, {
      key: "_getActiveElements",
      value: function _getActiveElements(t, e, i, s) {
        if ("mouseout" === t.type) return [];
        if (!i) return e;
        var n = this.options.hover;
        return this.getElementsAtEventForMode(t, n.mode, n, s);
      }
    }]);
  }();
  var un = function un() {
      return Q(dn.instances, function (t) {
        return t._plugins.invalidate();
      });
    },
    fn = !0;
  function gn() {
    throw new Error("This method is not implemented: Check that a complete date adapter is provided.");
  }
  Object.defineProperties(dn, {
    defaults: {
      enumerable: fn,
      value: bt
    },
    instances: {
      enumerable: fn,
      value: ln
    },
    overrides: {
      enumerable: fn,
      value: gt
    },
    registry: {
      enumerable: fn,
      value: Ws
    },
    version: {
      enumerable: fn,
      value: "3.7.1"
    },
    getChart: {
      enumerable: fn,
      value: hn
    },
    register: {
      enumerable: fn,
      value: function value() {
        Ws.add.apply(Ws, arguments), un();
      }
    },
    unregister: {
      enumerable: fn,
      value: function value() {
        Ws.remove.apply(Ws, arguments), un();
      }
    }
  });
  var pn = /*#__PURE__*/function () {
    function pn(t) {
      _classCallCheck(this, pn);
      this.options = t || {};
    }
    return _createClass(pn, [{
      key: "formats",
      value: function formats() {
        return gn();
      }
    }, {
      key: "parse",
      value: function parse(t, e) {
        return gn();
      }
    }, {
      key: "format",
      value: function format(t, e) {
        return gn();
      }
    }, {
      key: "add",
      value: function add(t, e, i) {
        return gn();
      }
    }, {
      key: "diff",
      value: function diff(t, e, i) {
        return gn();
      }
    }, {
      key: "startOf",
      value: function startOf(t, e, i) {
        return gn();
      }
    }, {
      key: "endOf",
      value: function endOf(t, e) {
        return gn();
      }
    }]);
  }();
  pn.override = function (t) {
    Object.assign(pn.prototype, t);
  };
  var mn = {
    _date: pn
  };
  function xn(t) {
    var e = t.iScale,
      i = function (t, e) {
        if (!t._cache.$bar) {
          var _i38 = t.getMatchingVisibleMetas(e);
          var _s34 = [];
          for (var _e45 = 0, _n24 = _i38.length; _e45 < _n24; _e45++) _s34 = _s34.concat(_i38[_e45].controller.getAllParsedValues(t));
          t._cache.$bar = fe(_s34.sort(function (t, e) {
            return t - e;
          }));
        }
        return t._cache.$bar;
      }(e, t.type);
    var s,
      n,
      o,
      a,
      r = e._length;
    var l = function l() {
      32767 !== o && -32768 !== o && (ct(a) && (r = Math.min(r, Math.abs(o - a) || r)), a = o);
    };
    for (s = 0, n = i.length; s < n; ++s) o = e.getPixelForValue(i[s]), l();
    for (a = void 0, s = 0, n = e.ticks.length; s < n; ++s) o = e.getPixelForTick(s), l();
    return r;
  }
  function bn(t, e, i, s) {
    return Y(t) ? function (t, e, i, s) {
      var n = i.parse(t[0], s),
        o = i.parse(t[1], s),
        a = Math.min(n, o),
        r = Math.max(n, o);
      var l = a,
        h = r;
      Math.abs(a) > Math.abs(r) && (l = r, h = a), e[i.axis] = h, e._custom = {
        barStart: l,
        barEnd: h,
        start: n,
        end: o,
        min: a,
        max: r
      };
    }(t, e, i, s) : e[i.axis] = i.parse(t, s), e;
  }
  function _n(t, e, i, s) {
    var n = t.iScale,
      o = t.vScale,
      a = n.getLabels(),
      r = n === o,
      l = [];
    var h, c, d, u;
    for (h = i, c = i + s; h < c; ++h) u = e[h], d = {}, d[n.axis] = r || n.parse(a[h], h), l.push(bn(u, d, o, h));
    return l;
  }
  function yn(t) {
    return t && void 0 !== t.barStart && void 0 !== t.barEnd;
  }
  function vn(t, e, i, s) {
    var n = e.borderSkipped;
    var o = {};
    if (!n) return void (t.borderSkipped = o);
    var _ref8 = function (t) {
        var e, i, s, n, o;
        return t.horizontal ? (e = t.base > t.x, i = "left", s = "right") : (e = t.base < t.y, i = "bottom", s = "top"), e ? (n = "end", o = "start") : (n = "start", o = "end"), {
          start: i,
          end: s,
          reverse: e,
          top: n,
          bottom: o
        };
      }(t),
      a = _ref8.start,
      r = _ref8.end,
      l = _ref8.reverse,
      h = _ref8.top,
      c = _ref8.bottom;
    "middle" === n && i && (t.enableBorderRadius = !0, (i._top || 0) === s ? n = h : (i._bottom || 0) === s ? n = c : (o[wn(c, a, r, l)] = !0, n = h)), o[wn(n, a, r, l)] = !0, t.borderSkipped = o;
  }
  function wn(t, e, i, s) {
    var n, o, a;
    return s ? (a = i, t = Mn(t = (n = t) === (o = e) ? a : n === a ? o : n, i, e)) : t = Mn(t, e, i), t;
  }
  function Mn(t, e, i) {
    return "start" === t ? e : "end" === t ? i : t;
  }
  function kn(t, _ref9, i) {
    var e = _ref9.inflateAmount;
    t.inflateAmount = "auto" === e ? 1 === i ? .33 : 0 : e;
  }
  var Sn = /*#__PURE__*/function (_Ps) {
    function Sn() {
      _classCallCheck(this, Sn);
      return _callSuper(this, Sn, arguments);
    }
    _inherits(Sn, _Ps);
    return _createClass(Sn, [{
      key: "parsePrimitiveData",
      value: function parsePrimitiveData(t, e, i, s) {
        return _n(t, e, i, s);
      }
    }, {
      key: "parseArrayData",
      value: function parseArrayData(t, e, i, s) {
        return _n(t, e, i, s);
      }
    }, {
      key: "parseObjectData",
      value: function parseObjectData(t, e, i, s) {
        var n = t.iScale,
          o = t.vScale,
          _this$_parsing2 = this._parsing,
          _this$_parsing2$xAxis = _this$_parsing2.xAxisKey,
          a = _this$_parsing2$xAxis === void 0 ? "x" : _this$_parsing2$xAxis,
          _this$_parsing2$yAxis = _this$_parsing2.yAxisKey,
          r = _this$_parsing2$yAxis === void 0 ? "y" : _this$_parsing2$yAxis,
          l = "x" === n.axis ? a : r,
          h = "x" === o.axis ? a : r,
          c = [];
        var d, u, f, g;
        for (d = i, u = i + s; d < u; ++d) g = e[d], f = {}, f[n.axis] = n.parse(lt(g, l), d), c.push(bn(lt(g, h), f, o, d));
        return c;
      }
    }, {
      key: "updateRangeFromParsed",
      value: function updateRangeFromParsed(t, e, i, s) {
        _get2(_getPrototypeOf(Sn.prototype), "updateRangeFromParsed", this).call(this, t, e, i, s);
        var n = i._custom;
        n && e === this._cachedMeta.vScale && (t.min = Math.min(t.min, n.min), t.max = Math.max(t.max, n.max));
      }
    }, {
      key: "getMaxOverflow",
      value: function getMaxOverflow() {
        return 0;
      }
    }, {
      key: "getLabelAndValue",
      value: function getLabelAndValue(t) {
        var e = this._cachedMeta,
          i = e.iScale,
          s = e.vScale,
          n = this.getParsed(t),
          o = n._custom,
          a = yn(o) ? "[" + o.start + ", " + o.end + "]" : "" + s.getLabelForValue(n[s.axis]);
        return {
          label: "" + i.getLabelForValue(n[i.axis]),
          value: a
        };
      }
    }, {
      key: "initialize",
      value: function initialize() {
        this.enableOptionSharing = !0, _get2(_getPrototypeOf(Sn.prototype), "initialize", this).call(this);
        this._cachedMeta.stack = this.getDataset().stack;
      }
    }, {
      key: "update",
      value: function update(t) {
        var e = this._cachedMeta;
        this.updateElements(e.data, 0, e.data.length, t);
      }
    }, {
      key: "updateElements",
      value: function updateElements(t, e, i, s) {
        var n = "reset" === s,
          o = this.index,
          a = this._cachedMeta.vScale,
          r = a.getBasePixel(),
          l = a.isHorizontal(),
          h = this._getRuler(),
          c = this.resolveDataElementOptions(e, s),
          d = this.getSharedOptions(c),
          u = this.includeOptions(s, d);
        this.updateSharedOptions(d, s, c);
        for (var _c7 = e; _c7 < e + i; _c7++) {
          var _e46 = this.getParsed(_c7),
            _i39 = n || $(_e46[a.axis]) ? {
              base: r,
              head: r
            } : this._calculateBarValuePixels(_c7),
            _f2 = this._calculateBarIndexPixels(_c7, h),
            _g = (_e46._stacks || {})[a.axis],
            _p = {
              horizontal: l,
              base: _i39.base,
              enableBorderRadius: !_g || yn(_e46._custom) || o === _g._top || o === _g._bottom,
              x: l ? _i39.head : _f2.center,
              y: l ? _f2.center : _i39.head,
              height: l ? _f2.size : Math.abs(_i39.size),
              width: l ? Math.abs(_i39.size) : _f2.size
            };
          u && (_p.options = d || this.resolveDataElementOptions(_c7, t[_c7].active ? "active" : s));
          var _m = _p.options || t[_c7].options;
          vn(_p, _m, _g, o), kn(_p, _m, h.ratio), this.updateElement(t[_c7], _c7, _p, s);
        }
      }
    }, {
      key: "_getStacks",
      value: function _getStacks(t, e) {
        var i = this._cachedMeta.iScale,
          s = i.getMatchingVisibleMetas(this._type),
          n = i.options.stacked,
          o = s.length,
          a = [];
        var r, l;
        for (r = 0; r < o; ++r) if (l = s[r], l.controller.options.grouped) {
          if (void 0 !== e) {
            var _t49 = l.controller.getParsed(e)[l.controller._cachedMeta.vScale.axis];
            if ($(_t49) || isNaN(_t49)) continue;
          }
          if ((!1 === n || -1 === a.indexOf(l.stack) || void 0 === n && void 0 === l.stack) && a.push(l.stack), l.index === t) break;
        }
        return a.length || a.push(void 0), a;
      }
    }, {
      key: "_getStackCount",
      value: function _getStackCount(t) {
        return this._getStacks(void 0, t).length;
      }
    }, {
      key: "_getStackIndex",
      value: function _getStackIndex(t, e, i) {
        var s = this._getStacks(t, i),
          n = void 0 !== e ? s.indexOf(e) : -1;
        return -1 === n ? s.length - 1 : n;
      }
    }, {
      key: "_getRuler",
      value: function _getRuler() {
        var t = this.options,
          e = this._cachedMeta,
          i = e.iScale,
          s = [];
        var n, o;
        for (n = 0, o = e.data.length; n < o; ++n) s.push(i.getPixelForValue(this.getParsed(n)[i.axis], n));
        var a = t.barThickness;
        return {
          min: a || xn(e),
          pixels: s,
          start: i._startPixel,
          end: i._endPixel,
          stackCount: this._getStackCount(),
          scale: i,
          grouped: t.grouped,
          ratio: a ? 1 : t.categoryPercentage * t.barPercentage
        };
      }
    }, {
      key: "_calculateBarValuePixels",
      value: function _calculateBarValuePixels(t) {
        var _this$_cachedMeta = this._cachedMeta,
          e = _this$_cachedMeta.vScale,
          i = _this$_cachedMeta._stacked,
          _this$options9 = this.options,
          s = _this$options9.base,
          n = _this$options9.minBarLength,
          o = s || 0,
          a = this.getParsed(t),
          r = a._custom,
          l = yn(r);
        var h,
          c,
          d = a[e.axis],
          u = 0,
          f = i ? this.applyStack(e, a, i) : d;
        f !== d && (u = f - d, f = d), l && (d = r.barStart, f = r.barEnd - r.barStart, 0 !== d && Ct(d) !== Ct(r.barEnd) && (u = 0), u += d);
        var g = $(s) || l ? u : s;
        var p = e.getPixelForValue(g);
        if (h = this.chart.getDataVisibility(t) ? e.getPixelForValue(u + f) : p, c = h - p, Math.abs(c) < n && (c = function (t, e, i) {
          return 0 !== t ? Ct(t) : (e.isHorizontal() ? 1 : -1) * (e.min >= i ? 1 : -1);
        }(c, e, o) * n, d === o && (p -= c / 2), h = p + c), p === e.getPixelForValue(o)) {
          var _t50 = Ct(c) * e.getLineWidthForValue(o) / 2;
          p += _t50, c -= _t50;
        }
        return {
          size: c,
          base: p,
          head: h,
          center: h + c / 2
        };
      }
    }, {
      key: "_calculateBarIndexPixels",
      value: function _calculateBarIndexPixels(t, e) {
        var i = e.scale,
          s = this.options,
          n = s.skipNull,
          o = K(s.maxBarThickness, 1 / 0);
        var a, r;
        if (e.grouped) {
          var _i40 = n ? this._getStackCount(t) : e.stackCount,
            _l13 = "flex" === s.barThickness ? function (t, e, i, s) {
              var n = e.pixels,
                o = n[t];
              var a = t > 0 ? n[t - 1] : null,
                r = t < n.length - 1 ? n[t + 1] : null;
              var l = i.categoryPercentage;
              null === a && (a = o - (null === r ? e.end - e.start : r - o)), null === r && (r = o + o - a);
              var h = o - (o - Math.min(a, r)) / 2 * l;
              return {
                chunk: Math.abs(r - a) / 2 * l / s,
                ratio: i.barPercentage,
                start: h
              };
            }(t, e, s, _i40) : function (t, e, i, s) {
              var n = i.barThickness;
              var o, a;
              return $(n) ? (o = e.min * i.categoryPercentage, a = i.barPercentage) : (o = n * s, a = 1), {
                chunk: o / s,
                ratio: a,
                start: e.pixels[t] - o / 2
              };
            }(t, e, s, _i40),
            _h12 = this._getStackIndex(this.index, this._cachedMeta.stack, n ? t : void 0);
          a = _l13.start + _l13.chunk * _h12 + _l13.chunk / 2, r = Math.min(o, _l13.chunk * _l13.ratio);
        } else a = i.getPixelForValue(this.getParsed(t)[i.axis], t), r = Math.min(o, e.min * e.ratio);
        return {
          base: a - r / 2,
          head: a + r / 2,
          center: a,
          size: r
        };
      }
    }, {
      key: "draw",
      value: function draw() {
        var t = this._cachedMeta,
          e = t.vScale,
          i = t.data,
          s = i.length;
        var n = 0;
        for (; n < s; ++n) null !== this.getParsed(n)[e.axis] && i[n].draw(this._ctx);
      }
    }]);
  }(Ps);
  Sn.id = "bar", Sn.defaults = {
    datasetElementType: !1,
    dataElementType: "bar",
    categoryPercentage: .8,
    barPercentage: .9,
    grouped: !0,
    animations: {
      numbers: {
        type: "number",
        properties: ["x", "y", "base", "width", "height"]
      }
    }
  }, Sn.overrides = {
    scales: {
      _index_: {
        type: "category",
        offset: !0,
        grid: {
          offset: !0
        }
      },
      _value_: {
        type: "linear",
        beginAtZero: !0
      }
    }
  };
  var Pn = /*#__PURE__*/function (_Ps2) {
    function Pn() {
      _classCallCheck(this, Pn);
      return _callSuper(this, Pn, arguments);
    }
    _inherits(Pn, _Ps2);
    return _createClass(Pn, [{
      key: "initialize",
      value: function initialize() {
        this.enableOptionSharing = !0, _get2(_getPrototypeOf(Pn.prototype), "initialize", this).call(this);
      }
    }, {
      key: "parsePrimitiveData",
      value: function parsePrimitiveData(t, e, i, s) {
        var n = _get2(_getPrototypeOf(Pn.prototype), "parsePrimitiveData", this).call(this, t, e, i, s);
        for (var _t51 = 0; _t51 < n.length; _t51++) n[_t51]._custom = this.resolveDataElementOptions(_t51 + i).radius;
        return n;
      }
    }, {
      key: "parseArrayData",
      value: function parseArrayData(t, e, i, s) {
        var n = _get2(_getPrototypeOf(Pn.prototype), "parseArrayData", this).call(this, t, e, i, s);
        for (var _t52 = 0; _t52 < n.length; _t52++) {
          var _s35 = e[i + _t52];
          n[_t52]._custom = K(_s35[2], this.resolveDataElementOptions(_t52 + i).radius);
        }
        return n;
      }
    }, {
      key: "parseObjectData",
      value: function parseObjectData(t, e, i, s) {
        var n = _get2(_getPrototypeOf(Pn.prototype), "parseObjectData", this).call(this, t, e, i, s);
        for (var _t53 = 0; _t53 < n.length; _t53++) {
          var _s36 = e[i + _t53];
          n[_t53]._custom = K(_s36 && _s36.r && +_s36.r, this.resolveDataElementOptions(_t53 + i).radius);
        }
        return n;
      }
    }, {
      key: "getMaxOverflow",
      value: function getMaxOverflow() {
        var t = this._cachedMeta.data;
        var e = 0;
        for (var _i41 = t.length - 1; _i41 >= 0; --_i41) e = Math.max(e, t[_i41].size(this.resolveDataElementOptions(_i41)) / 2);
        return e > 0 && e;
      }
    }, {
      key: "getLabelAndValue",
      value: function getLabelAndValue(t) {
        var e = this._cachedMeta,
          i = e.xScale,
          s = e.yScale,
          n = this.getParsed(t),
          o = i.getLabelForValue(n.x),
          a = s.getLabelForValue(n.y),
          r = n._custom;
        return {
          label: e.label,
          value: "(" + o + ", " + a + (r ? ", " + r : "") + ")"
        };
      }
    }, {
      key: "update",
      value: function update(t) {
        var e = this._cachedMeta.data;
        this.updateElements(e, 0, e.length, t);
      }
    }, {
      key: "updateElements",
      value: function updateElements(t, e, i, s) {
        var n = "reset" === s,
          _this$_cachedMeta2 = this._cachedMeta,
          o = _this$_cachedMeta2.iScale,
          a = _this$_cachedMeta2.vScale,
          r = this.resolveDataElementOptions(e, s),
          l = this.getSharedOptions(r),
          h = this.includeOptions(s, l),
          c = o.axis,
          d = a.axis;
        for (var _r11 = e; _r11 < e + i; _r11++) {
          var _e47 = t[_r11],
            _i42 = !n && this.getParsed(_r11),
            _l14 = {},
            _u2 = _l14[c] = n ? o.getPixelForDecimal(.5) : o.getPixelForValue(_i42[c]),
            _f3 = _l14[d] = n ? a.getBasePixel() : a.getPixelForValue(_i42[d]);
          _l14.skip = isNaN(_u2) || isNaN(_f3), h && (_l14.options = this.resolveDataElementOptions(_r11, _e47.active ? "active" : s), n && (_l14.options.radius = 0)), this.updateElement(_e47, _r11, _l14, s);
        }
        this.updateSharedOptions(l, s, r);
      }
    }, {
      key: "resolveDataElementOptions",
      value: function resolveDataElementOptions(t, e) {
        var i = this.getParsed(t);
        var s = _get2(_getPrototypeOf(Pn.prototype), "resolveDataElementOptions", this).call(this, t, e);
        s.$shared && (s = Object.assign({}, s, {
          $shared: !1
        }));
        var n = s.radius;
        return "active" !== e && (s.radius = 0), s.radius += K(i && i._custom, n), s;
      }
    }]);
  }(Ps);
  Pn.id = "bubble", Pn.defaults = {
    datasetElementType: !1,
    dataElementType: "point",
    animations: {
      numbers: {
        type: "number",
        properties: ["x", "y", "borderWidth", "radius"]
      }
    }
  }, Pn.overrides = {
    scales: {
      x: {
        type: "linear"
      },
      y: {
        type: "linear"
      }
    },
    plugins: {
      tooltip: {
        callbacks: {
          title: function title() {
            return "";
          }
        }
      }
    }
  };
  var Dn = /*#__PURE__*/function (_Ps3) {
    function Dn(t, e) {
      var _this19;
      _classCallCheck(this, Dn);
      _this19 = _callSuper(this, Dn, [t, e]), _this19.enableOptionSharing = !0, _this19.innerRadius = void 0, _this19.outerRadius = void 0, _this19.offsetX = void 0, _this19.offsetY = void 0;
      return _this19;
    }
    _inherits(Dn, _Ps3);
    return _createClass(Dn, [{
      key: "linkScales",
      value: function linkScales() {}
    }, {
      key: "parse",
      value: function parse(t, e) {
        var i = this.getDataset().data,
          s = this._cachedMeta;
        if (!1 === this._parsing) s._parsed = i;else {
          var _n25,
            _o26,
            _a16 = function _a16(t) {
              return +i[t];
            };
          if (U(i[t])) {
            var _this$_parsing$key = this._parsing.key,
              _t54 = _this$_parsing$key === void 0 ? "value" : _this$_parsing$key;
            _a16 = function _a16(e) {
              return +lt(i[e], _t54);
            };
          }
          for (_n25 = t, _o26 = t + e; _n25 < _o26; ++_n25) s._parsed[_n25] = _a16(_n25);
        }
      }
    }, {
      key: "_getRotation",
      value: function _getRotation() {
        return It(this.options.rotation - 90);
      }
    }, {
      key: "_getCircumference",
      value: function _getCircumference() {
        return It(this.options.circumference);
      }
    }, {
      key: "_getRotationExtents",
      value: function _getRotationExtents() {
        var t = yt,
          e = -yt;
        for (var _i43 = 0; _i43 < this.chart.data.datasets.length; ++_i43) if (this.chart.isDatasetVisible(_i43)) {
          var _s37 = this.chart.getDatasetMeta(_i43).controller,
            _n26 = _s37._getRotation(),
            _o27 = _s37._getCircumference();
          t = Math.min(t, _n26), e = Math.max(e, _n26 + _o27);
        }
        return {
          rotation: t,
          circumference: e - t
        };
      }
    }, {
      key: "update",
      value: function update(t) {
        var e = this.chart,
          i = e.chartArea,
          s = this._cachedMeta,
          n = s.data,
          o = this.getMaxBorderWidth() + this.getMaxOffset(n) + this.options.spacing,
          a = Math.max((Math.min(i.width, i.height) - o) / 2, 0),
          r = Math.min(G(this.options.cutout, a), 1),
          l = this._getRingWeight(this.index),
          _this$_getRotationExt = this._getRotationExtents(),
          h = _this$_getRotationExt.circumference,
          c = _this$_getRotationExt.rotation,
          _ref10 = function (t, e, i) {
            var s = 1,
              n = 1,
              o = 0,
              a = 0;
            if (e < yt) {
              var _r12 = t,
                _l15 = _r12 + e,
                _h13 = Math.cos(_r12),
                _c8 = Math.sin(_r12),
                _d7 = Math.cos(_l15),
                _u3 = Math.sin(_l15),
                _f4 = function _f4(t, e, s) {
                  return Ht(t, _r12, _l15, !0) ? 1 : Math.max(e, e * i, s, s * i);
                },
                _g2 = function _g2(t, e, s) {
                  return Ht(t, _r12, _l15, !0) ? -1 : Math.min(e, e * i, s, s * i);
                },
                _p2 = _f4(0, _h13, _d7),
                _m2 = _f4(kt, _c8, _u3),
                _x = _g2(_t, _h13, _d7),
                _b = _g2(_t + kt, _c8, _u3);
              s = (_p2 - _x) / 2, n = (_m2 - _b) / 2, o = -(_p2 + _x) / 2, a = -(_m2 + _b) / 2;
            }
            return {
              ratioX: s,
              ratioY: n,
              offsetX: o,
              offsetY: a
            };
          }(c, h, r),
          d = _ref10.ratioX,
          u = _ref10.ratioY,
          f = _ref10.offsetX,
          g = _ref10.offsetY,
          p = (i.width - o) / d,
          m = (i.height - o) / u,
          x = Math.max(Math.min(p, m) / 2, 0),
          b = Z(this.options.radius, x),
          _ = (b - Math.max(b * r, 0)) / this._getVisibleDatasetWeightTotal();
        this.offsetX = f * b, this.offsetY = g * b, s.total = this.calculateTotal(), this.outerRadius = b - _ * this._getRingWeightOffset(this.index), this.innerRadius = Math.max(this.outerRadius - _ * l, 0), this.updateElements(n, 0, n.length, t);
      }
    }, {
      key: "_circumference",
      value: function _circumference(t, e) {
        var i = this.options,
          s = this._cachedMeta,
          n = this._getCircumference();
        return e && i.animation.animateRotate || !this.chart.getDataVisibility(t) || null === s._parsed[t] || s.data[t].hidden ? 0 : this.calculateCircumference(s._parsed[t] * n / yt);
      }
    }, {
      key: "updateElements",
      value: function updateElements(t, e, i, s) {
        var n = "reset" === s,
          o = this.chart,
          a = o.chartArea,
          r = o.options.animation,
          l = (a.left + a.right) / 2,
          h = (a.top + a.bottom) / 2,
          c = n && r.animateScale,
          d = c ? 0 : this.innerRadius,
          u = c ? 0 : this.outerRadius,
          f = this.resolveDataElementOptions(e, s),
          g = this.getSharedOptions(f),
          p = this.includeOptions(s, g);
        var m,
          x = this._getRotation();
        for (m = 0; m < e; ++m) x += this._circumference(m, n);
        for (m = e; m < e + i; ++m) {
          var _e48 = this._circumference(m, n),
            _i44 = t[m],
            _o28 = {
              x: l + this.offsetX,
              y: h + this.offsetY,
              startAngle: x,
              endAngle: x + _e48,
              circumference: _e48,
              outerRadius: u,
              innerRadius: d
            };
          p && (_o28.options = g || this.resolveDataElementOptions(m, _i44.active ? "active" : s)), x += _e48, this.updateElement(_i44, m, _o28, s);
        }
        this.updateSharedOptions(g, s, f);
      }
    }, {
      key: "calculateTotal",
      value: function calculateTotal() {
        var t = this._cachedMeta,
          e = t.data;
        var i,
          s = 0;
        for (i = 0; i < e.length; i++) {
          var _n27 = t._parsed[i];
          null === _n27 || isNaN(_n27) || !this.chart.getDataVisibility(i) || e[i].hidden || (s += Math.abs(_n27));
        }
        return s;
      }
    }, {
      key: "calculateCircumference",
      value: function calculateCircumference(t) {
        var e = this._cachedMeta.total;
        return e > 0 && !isNaN(t) ? yt * (Math.abs(t) / e) : 0;
      }
    }, {
      key: "getLabelAndValue",
      value: function getLabelAndValue(t) {
        var e = this._cachedMeta,
          i = this.chart,
          s = i.data.labels || [],
          n = Ri(e._parsed[t], i.options.locale);
        return {
          label: s[t] || "",
          value: n
        };
      }
    }, {
      key: "getMaxBorderWidth",
      value: function getMaxBorderWidth(t) {
        var e = 0;
        var i = this.chart;
        var s, n, o, a, r;
        if (!t) for (s = 0, n = i.data.datasets.length; s < n; ++s) if (i.isDatasetVisible(s)) {
          o = i.getDatasetMeta(s), t = o.data, a = o.controller;
          break;
        }
        if (!t) return 0;
        for (s = 0, n = t.length; s < n; ++s) r = a.resolveDataElementOptions(s), "inner" !== r.borderAlign && (e = Math.max(e, r.borderWidth || 0, r.hoverBorderWidth || 0));
        return e;
      }
    }, {
      key: "getMaxOffset",
      value: function getMaxOffset(t) {
        var e = 0;
        for (var _i45 = 0, _s38 = t.length; _i45 < _s38; ++_i45) {
          var _t55 = this.resolveDataElementOptions(_i45);
          e = Math.max(e, _t55.offset || 0, _t55.hoverOffset || 0);
        }
        return e;
      }
    }, {
      key: "_getRingWeightOffset",
      value: function _getRingWeightOffset(t) {
        var e = 0;
        for (var _i46 = 0; _i46 < t; ++_i46) this.chart.isDatasetVisible(_i46) && (e += this._getRingWeight(_i46));
        return e;
      }
    }, {
      key: "_getRingWeight",
      value: function _getRingWeight(t) {
        return Math.max(K(this.chart.data.datasets[t].weight, 1), 0);
      }
    }, {
      key: "_getVisibleDatasetWeightTotal",
      value: function _getVisibleDatasetWeightTotal() {
        return this._getRingWeightOffset(this.chart.data.datasets.length) || 1;
      }
    }]);
  }(Ps);
  Dn.id = "doughnut", Dn.defaults = {
    datasetElementType: !1,
    dataElementType: "arc",
    animation: {
      animateRotate: !0,
      animateScale: !1
    },
    animations: {
      numbers: {
        type: "number",
        properties: ["circumference", "endAngle", "innerRadius", "outerRadius", "startAngle", "x", "y", "offset", "borderWidth", "spacing"]
      }
    },
    cutout: "50%",
    rotation: 0,
    circumference: 360,
    radius: "100%",
    spacing: 0,
    indexAxis: "r"
  }, Dn.descriptors = {
    _scriptable: function _scriptable(t) {
      return "spacing" !== t;
    },
    _indexable: function _indexable(t) {
      return "spacing" !== t;
    }
  }, Dn.overrides = {
    aspectRatio: 1,
    plugins: {
      legend: {
        labels: {
          generateLabels: function generateLabels(t) {
            var e = t.data;
            if (e.labels.length && e.datasets.length) {
              var _i47 = t.legend.options.labels.pointStyle;
              return e.labels.map(function (e, s) {
                var n = t.getDatasetMeta(0).controller.getStyle(s);
                return {
                  text: e,
                  fillStyle: n.backgroundColor,
                  strokeStyle: n.borderColor,
                  lineWidth: n.borderWidth,
                  pointStyle: _i47,
                  hidden: !t.getDataVisibility(s),
                  index: s
                };
              });
            }
            return [];
          }
        },
        onClick: function onClick(t, e, i) {
          i.chart.toggleDataVisibility(e.index), i.chart.update();
        }
      },
      tooltip: {
        callbacks: {
          title: function title() {
            return "";
          },
          label: function label(t) {
            var e = t.label;
            var i = ": " + t.formattedValue;
            return Y(e) ? (e = e.slice(), e[0] += i) : e += i, e;
          }
        }
      }
    }
  };
  var Cn = /*#__PURE__*/function (_Ps4) {
    function Cn() {
      _classCallCheck(this, Cn);
      return _callSuper(this, Cn, arguments);
    }
    _inherits(Cn, _Ps4);
    return _createClass(Cn, [{
      key: "initialize",
      value: function initialize() {
        this.enableOptionSharing = !0, _get2(_getPrototypeOf(Cn.prototype), "initialize", this).call(this);
      }
    }, {
      key: "update",
      value: function update(t) {
        var e = this._cachedMeta,
          i = e.dataset,
          _e$data = e.data,
          s = _e$data === void 0 ? [] : _e$data,
          n = e._dataset,
          o = this.chart._animationsDisabled;
        var _ref11 = function (t, e, i) {
            var s = e.length;
            var n = 0,
              o = s;
            if (t._sorted) {
              var _a17 = t.iScale,
                _r13 = t._parsed,
                _l16 = _a17.axis,
                _a$getUserBounds = _a17.getUserBounds(),
                _h14 = _a$getUserBounds.min,
                _c9 = _a$getUserBounds.max,
                _d8 = _a$getUserBounds.minDefined,
                _u4 = _a$getUserBounds.maxDefined;
              _d8 && (n = jt(Math.min(re(_r13, _a17.axis, _h14).lo, i ? s : re(e, _l16, _a17.getPixelForValue(_h14)).lo), 0, s - 1)), o = _u4 ? jt(Math.max(re(_r13, _a17.axis, _c9).hi + 1, i ? 0 : re(e, _l16, _a17.getPixelForValue(_c9)).hi + 1), n, s) - n : s - n;
            }
            return {
              start: n,
              count: o
            };
          }(e, s, o),
          a = _ref11.start,
          r = _ref11.count;
        this._drawStart = a, this._drawCount = r, function (t) {
          var e = t.xScale,
            i = t.yScale,
            s = t._scaleRanges,
            n = {
              xmin: e.min,
              xmax: e.max,
              ymin: i.min,
              ymax: i.max
            };
          if (!s) return t._scaleRanges = n, !0;
          var o = s.xmin !== e.min || s.xmax !== e.max || s.ymin !== i.min || s.ymax !== i.max;
          return Object.assign(s, n), o;
        }(e) && (a = 0, r = s.length), i._chart = this.chart, i._datasetIndex = this.index, i._decimated = !!n._decimated, i.points = s;
        var l = this.resolveDatasetElementOptions(t);
        this.options.showLine || (l.borderWidth = 0), l.segment = this.options.segment, this.updateElement(i, void 0, {
          animated: !o,
          options: l
        }, t), this.updateElements(s, a, r, t);
      }
    }, {
      key: "updateElements",
      value: function updateElements(t, e, i, s) {
        var n = "reset" === s,
          _this$_cachedMeta3 = this._cachedMeta,
          o = _this$_cachedMeta3.iScale,
          a = _this$_cachedMeta3.vScale,
          r = _this$_cachedMeta3._stacked,
          l = _this$_cachedMeta3._dataset,
          h = this.resolveDataElementOptions(e, s),
          c = this.getSharedOptions(h),
          d = this.includeOptions(s, c),
          u = o.axis,
          f = a.axis,
          _this$options10 = this.options,
          g = _this$options10.spanGaps,
          p = _this$options10.segment,
          m = Tt(g) ? g : Number.POSITIVE_INFINITY,
          x = this.chart._animationsDisabled || n || "none" === s;
        var b = e > 0 && this.getParsed(e - 1);
        for (var _h15 = e; _h15 < e + i; ++_h15) {
          var _e49 = t[_h15],
            _i48 = this.getParsed(_h15),
            _g3 = x ? _e49 : {},
            _2 = $(_i48[f]),
            _y = _g3[u] = o.getPixelForValue(_i48[u], _h15),
            _v = _g3[f] = n || _2 ? a.getBasePixel() : a.getPixelForValue(r ? this.applyStack(a, _i48, r) : _i48[f], _h15);
          _g3.skip = isNaN(_y) || isNaN(_v) || _2, _g3.stop = _h15 > 0 && _i48[u] - b[u] > m, p && (_g3.parsed = _i48, _g3.raw = l.data[_h15]), d && (_g3.options = c || this.resolveDataElementOptions(_h15, _e49.active ? "active" : s)), x || this.updateElement(_e49, _h15, _g3, s), b = _i48;
        }
        this.updateSharedOptions(c, s, h);
      }
    }, {
      key: "getMaxOverflow",
      value: function getMaxOverflow() {
        var t = this._cachedMeta,
          e = t.dataset,
          i = e.options && e.options.borderWidth || 0,
          s = t.data || [];
        if (!s.length) return i;
        var n = s[0].size(this.resolveDataElementOptions(0)),
          o = s[s.length - 1].size(this.resolveDataElementOptions(s.length - 1));
        return Math.max(i, n, o) / 2;
      }
    }, {
      key: "draw",
      value: function draw() {
        var t = this._cachedMeta;
        t.dataset.updateControlPoints(this.chart.chartArea, t.iScale.axis), _get2(_getPrototypeOf(Cn.prototype), "draw", this).call(this);
      }
    }]);
  }(Ps);
  Cn.id = "line", Cn.defaults = {
    datasetElementType: "line",
    dataElementType: "point",
    showLine: !0,
    spanGaps: !1
  }, Cn.overrides = {
    scales: {
      _index_: {
        type: "category"
      },
      _value_: {
        type: "linear"
      }
    }
  };
  var On = /*#__PURE__*/function (_Ps5) {
    function On(t, e) {
      var _this20;
      _classCallCheck(this, On);
      _this20 = _callSuper(this, On, [t, e]), _this20.innerRadius = void 0, _this20.outerRadius = void 0;
      return _this20;
    }
    _inherits(On, _Ps5);
    return _createClass(On, [{
      key: "getLabelAndValue",
      value: function getLabelAndValue(t) {
        var e = this._cachedMeta,
          i = this.chart,
          s = i.data.labels || [],
          n = Ri(e._parsed[t].r, i.options.locale);
        return {
          label: s[t] || "",
          value: n
        };
      }
    }, {
      key: "update",
      value: function update(t) {
        var e = this._cachedMeta.data;
        this._updateRadius(), this.updateElements(e, 0, e.length, t);
      }
    }, {
      key: "_updateRadius",
      value: function _updateRadius() {
        var t = this.chart,
          e = t.chartArea,
          i = t.options,
          s = Math.min(e.right - e.left, e.bottom - e.top),
          n = Math.max(s / 2, 0),
          o = (n - Math.max(i.cutoutPercentage ? n / 100 * i.cutoutPercentage : 1, 0)) / t.getVisibleDatasetCount();
        this.outerRadius = n - o * this.index, this.innerRadius = this.outerRadius - o;
      }
    }, {
      key: "updateElements",
      value: function updateElements(t, e, i, s) {
        var n = "reset" === s,
          o = this.chart,
          a = this.getDataset(),
          r = o.options.animation,
          l = this._cachedMeta.rScale,
          h = l.xCenter,
          c = l.yCenter,
          d = l.getIndexAngle(0) - .5 * _t;
        var u,
          f = d;
        var g = 360 / this.countVisibleElements();
        for (u = 0; u < e; ++u) f += this._computeAngle(u, s, g);
        for (u = e; u < e + i; u++) {
          var _e50 = t[u];
          var _i49 = f,
            _p3 = f + this._computeAngle(u, s, g),
            _m3 = o.getDataVisibility(u) ? l.getDistanceFromCenterForValue(a.data[u]) : 0;
          f = _p3, n && (r.animateScale && (_m3 = 0), r.animateRotate && (_i49 = _p3 = d));
          var _x2 = {
            x: h,
            y: c,
            innerRadius: 0,
            outerRadius: _m3,
            startAngle: _i49,
            endAngle: _p3,
            options: this.resolveDataElementOptions(u, _e50.active ? "active" : s)
          };
          this.updateElement(_e50, u, _x2, s);
        }
      }
    }, {
      key: "countVisibleElements",
      value: function countVisibleElements() {
        var _this21 = this;
        var t = this.getDataset(),
          e = this._cachedMeta;
        var i = 0;
        return e.data.forEach(function (e, s) {
          !isNaN(t.data[s]) && _this21.chart.getDataVisibility(s) && i++;
        }), i;
      }
    }, {
      key: "_computeAngle",
      value: function _computeAngle(t, e, i) {
        return this.chart.getDataVisibility(t) ? It(this.resolveDataElementOptions(t, e).angle || i) : 0;
      }
    }]);
  }(Ps);
  On.id = "polarArea", On.defaults = {
    dataElementType: "arc",
    animation: {
      animateRotate: !0,
      animateScale: !0
    },
    animations: {
      numbers: {
        type: "number",
        properties: ["x", "y", "startAngle", "endAngle", "innerRadius", "outerRadius"]
      }
    },
    indexAxis: "r",
    startAngle: 0
  }, On.overrides = {
    aspectRatio: 1,
    plugins: {
      legend: {
        labels: {
          generateLabels: function generateLabels(t) {
            var e = t.data;
            if (e.labels.length && e.datasets.length) {
              var _i50 = t.legend.options.labels.pointStyle;
              return e.labels.map(function (e, s) {
                var n = t.getDatasetMeta(0).controller.getStyle(s);
                return {
                  text: e,
                  fillStyle: n.backgroundColor,
                  strokeStyle: n.borderColor,
                  lineWidth: n.borderWidth,
                  pointStyle: _i50,
                  hidden: !t.getDataVisibility(s),
                  index: s
                };
              });
            }
            return [];
          }
        },
        onClick: function onClick(t, e, i) {
          i.chart.toggleDataVisibility(e.index), i.chart.update();
        }
      },
      tooltip: {
        callbacks: {
          title: function title() {
            return "";
          },
          label: function label(t) {
            return t.chart.data.labels[t.dataIndex] + ": " + t.formattedValue;
          }
        }
      }
    },
    scales: {
      r: {
        type: "radialLinear",
        angleLines: {
          display: !1
        },
        beginAtZero: !0,
        grid: {
          circular: !0
        },
        pointLabels: {
          display: !1
        },
        startAngle: 0
      }
    }
  };
  var An = /*#__PURE__*/function (_Dn) {
    function An() {
      _classCallCheck(this, An);
      return _callSuper(this, An, arguments);
    }
    _inherits(An, _Dn);
    return _createClass(An);
  }(Dn);
  An.id = "pie", An.defaults = {
    cutout: 0,
    rotation: 0,
    circumference: 360,
    radius: "100%"
  };
  var Tn = /*#__PURE__*/function (_Ps6) {
    function Tn() {
      _classCallCheck(this, Tn);
      return _callSuper(this, Tn, arguments);
    }
    _inherits(Tn, _Ps6);
    return _createClass(Tn, [{
      key: "getLabelAndValue",
      value: function getLabelAndValue(t) {
        var e = this._cachedMeta.vScale,
          i = this.getParsed(t);
        return {
          label: e.getLabels()[t],
          value: "" + e.getLabelForValue(i[e.axis])
        };
      }
    }, {
      key: "update",
      value: function update(t) {
        var e = this._cachedMeta,
          i = e.dataset,
          s = e.data || [],
          n = e.iScale.getLabels();
        if (i.points = s, "resize" !== t) {
          var _e51 = this.resolveDatasetElementOptions(t);
          this.options.showLine || (_e51.borderWidth = 0);
          var _o29 = {
            _loop: !0,
            _fullLoop: n.length === s.length,
            options: _e51
          };
          this.updateElement(i, void 0, _o29, t);
        }
        this.updateElements(s, 0, s.length, t);
      }
    }, {
      key: "updateElements",
      value: function updateElements(t, e, i, s) {
        var n = this.getDataset(),
          o = this._cachedMeta.rScale,
          a = "reset" === s;
        for (var _r14 = e; _r14 < e + i; _r14++) {
          var _e52 = t[_r14],
            _i51 = this.resolveDataElementOptions(_r14, _e52.active ? "active" : s),
            _l17 = o.getPointPositionForValue(_r14, n.data[_r14]),
            _h16 = a ? o.xCenter : _l17.x,
            _c10 = a ? o.yCenter : _l17.y,
            _d9 = {
              x: _h16,
              y: _c10,
              angle: _l17.angle,
              skip: isNaN(_h16) || isNaN(_c10),
              options: _i51
            };
          this.updateElement(_e52, _r14, _d9, s);
        }
      }
    }]);
  }(Ps);
  Tn.id = "radar", Tn.defaults = {
    datasetElementType: "line",
    dataElementType: "point",
    indexAxis: "r",
    showLine: !0,
    elements: {
      line: {
        fill: "start"
      }
    }
  }, Tn.overrides = {
    aspectRatio: 1,
    scales: {
      r: {
        type: "radialLinear"
      }
    }
  };
  var Ln = /*#__PURE__*/function (_Cn) {
    function Ln() {
      _classCallCheck(this, Ln);
      return _callSuper(this, Ln, arguments);
    }
    _inherits(Ln, _Cn);
    return _createClass(Ln);
  }(Cn);
  Ln.id = "scatter", Ln.defaults = {
    showLine: !1,
    fill: !1
  }, Ln.overrides = {
    interaction: {
      mode: "point"
    },
    plugins: {
      tooltip: {
        callbacks: {
          title: function title() {
            return "";
          },
          label: function label(t) {
            return "(" + t.label + ", " + t.formattedValue + ")";
          }
        }
      }
    },
    scales: {
      x: {
        type: "linear"
      },
      y: {
        type: "linear"
      }
    }
  };
  var Rn = Object.freeze({
    __proto__: null,
    BarController: Sn,
    BubbleController: Pn,
    DoughnutController: Dn,
    LineController: Cn,
    PolarAreaController: On,
    PieController: An,
    RadarController: Tn,
    ScatterController: Ln
  });
  function En(t, e, i) {
    var s = e.startAngle,
      n = e.pixelMargin,
      o = e.x,
      a = e.y,
      r = e.outerRadius,
      l = e.innerRadius;
    var h = n / r;
    t.beginPath(), t.arc(o, a, r, s - h, i + h), l > n ? (h = n / l, t.arc(o, a, l, i + h, s - h, !0)) : t.arc(o, a, n, i + kt, s - kt), t.closePath(), t.clip();
  }
  function In(t, e, i, s) {
    var n = Be(t.options.borderRadius, ["outerStart", "outerEnd", "innerStart", "innerEnd"]);
    var o = (i - e) / 2,
      a = Math.min(o, s * e / 2),
      r = function r(t) {
        var e = (i - Math.min(o, t)) * s / 2;
        return jt(t, 0, Math.min(o, e));
      };
    return {
      outerStart: r(n.outerStart),
      outerEnd: r(n.outerEnd),
      innerStart: jt(n.innerStart, 0, a),
      innerEnd: jt(n.innerEnd, 0, a)
    };
  }
  function zn(t, e, i, s) {
    return {
      x: i + t * Math.cos(e),
      y: s + t * Math.sin(e)
    };
  }
  function Fn(t, e, i, s, n) {
    var o = e.x,
      a = e.y,
      r = e.startAngle,
      l = e.pixelMargin,
      h = e.innerRadius,
      c = Math.max(e.outerRadius + s + i - l, 0),
      d = h > 0 ? h + s + i + l : 0;
    var u = 0;
    var f = n - r;
    if (s) {
      var _t56 = ((h > 0 ? h - s : 0) + (c > 0 ? c - s : 0)) / 2;
      u = (f - (0 !== _t56 ? f * _t56 / (_t56 + s) : f)) / 2;
    }
    var g = (f - Math.max(.001, f * c - i / _t) / c) / 2,
      p = r + g + u,
      m = n - g - u,
      _In = In(e, d, c, m - p),
      x = _In.outerStart,
      b = _In.outerEnd,
      _ = _In.innerStart,
      y = _In.innerEnd,
      v = c - x,
      w = c - b,
      M = p + x / v,
      k = m - b / w,
      S = d + _,
      P = d + y,
      D = p + _ / S,
      C = m - y / P;
    if (t.beginPath(), t.arc(o, a, c, M, k), b > 0) {
      var _e53 = zn(w, k, o, a);
      t.arc(_e53.x, _e53.y, b, k, m + kt);
    }
    var O = zn(P, m, o, a);
    if (t.lineTo(O.x, O.y), y > 0) {
      var _e54 = zn(P, C, o, a);
      t.arc(_e54.x, _e54.y, y, m + kt, C + Math.PI);
    }
    if (t.arc(o, a, d, m - y / d, p + _ / d, !0), _ > 0) {
      var _e55 = zn(S, D, o, a);
      t.arc(_e55.x, _e55.y, _, D + Math.PI, p - kt);
    }
    var A = zn(v, p, o, a);
    if (t.lineTo(A.x, A.y), x > 0) {
      var _e56 = zn(v, M, o, a);
      t.arc(_e56.x, _e56.y, x, p - kt, M);
    }
    t.closePath();
  }
  function Bn(t, e, i, s, n) {
    var o = e.options,
      a = o.borderWidth,
      r = o.borderJoinStyle,
      l = "inner" === o.borderAlign;
    a && (l ? (t.lineWidth = 2 * a, t.lineJoin = r || "round") : (t.lineWidth = a, t.lineJoin = r || "bevel"), e.fullCircles && function (t, e, i) {
      var s = e.x,
        n = e.y,
        o = e.startAngle,
        a = e.pixelMargin,
        r = e.fullCircles,
        l = Math.max(e.outerRadius - a, 0),
        h = e.innerRadius + a;
      var c;
      for (i && En(t, e, o + yt), t.beginPath(), t.arc(s, n, h, o + yt, o, !0), c = 0; c < r; ++c) t.stroke();
      for (t.beginPath(), t.arc(s, n, l, o, o + yt), c = 0; c < r; ++c) t.stroke();
    }(t, e, l), l && En(t, e, n), Fn(t, e, i, s, n), t.stroke());
  }
  var Vn = /*#__PURE__*/function (_Ds2) {
    function Vn(t) {
      var _this22;
      _classCallCheck(this, Vn);
      _this22 = _callSuper(this, Vn), _this22.options = void 0, _this22.circumference = void 0, _this22.startAngle = void 0, _this22.endAngle = void 0, _this22.innerRadius = void 0, _this22.outerRadius = void 0, _this22.pixelMargin = 0, _this22.fullCircles = 0, t && Object.assign(_assertThisInitialized(_this22), t);
      return _this22;
    }
    _inherits(Vn, _Ds2);
    return _createClass(Vn, [{
      key: "inRange",
      value: function inRange(t, e, i) {
        var s = this.getProps(["x", "y"], i),
          _Bt2 = Bt(s, {
            x: t,
            y: e
          }),
          n = _Bt2.angle,
          o = _Bt2.distance,
          _this$getProps2 = this.getProps(["startAngle", "endAngle", "innerRadius", "outerRadius", "circumference"], i),
          a = _this$getProps2.startAngle,
          r = _this$getProps2.endAngle,
          l = _this$getProps2.innerRadius,
          h = _this$getProps2.outerRadius,
          c = _this$getProps2.circumference,
          d = this.options.spacing / 2,
          u = K(c, r - a) >= yt || Ht(n, a, r),
          f = Yt(o, l + d, h + d);
        return u && f;
      }
    }, {
      key: "getCenterPoint",
      value: function getCenterPoint(t) {
        var _this$getProps3 = this.getProps(["x", "y", "startAngle", "endAngle", "innerRadius", "outerRadius", "circumference"], t),
          e = _this$getProps3.x,
          i = _this$getProps3.y,
          s = _this$getProps3.startAngle,
          n = _this$getProps3.endAngle,
          o = _this$getProps3.innerRadius,
          a = _this$getProps3.outerRadius,
          _this$options11 = this.options,
          r = _this$options11.offset,
          l = _this$options11.spacing,
          h = (s + n) / 2,
          c = (o + a + l + r) / 2;
        return {
          x: e + Math.cos(h) * c,
          y: i + Math.sin(h) * c
        };
      }
    }, {
      key: "tooltipPosition",
      value: function tooltipPosition(t) {
        return this.getCenterPoint(t);
      }
    }, {
      key: "draw",
      value: function draw(t) {
        var e = this.options,
          i = this.circumference,
          s = (e.offset || 0) / 2,
          n = (e.spacing || 0) / 2;
        if (this.pixelMargin = "inner" === e.borderAlign ? .33 : 0, this.fullCircles = i > yt ? Math.floor(i / yt) : 0, 0 === i || this.innerRadius < 0 || this.outerRadius < 0) return;
        t.save();
        var o = 0;
        if (s) {
          o = s / 2;
          var _e57 = (this.startAngle + this.endAngle) / 2;
          t.translate(Math.cos(_e57) * o, Math.sin(_e57) * o), this.circumference >= _t && (o = s);
        }
        t.fillStyle = e.backgroundColor, t.strokeStyle = e.borderColor;
        var a = function (t, e, i, s) {
          var n = e.fullCircles,
            o = e.startAngle,
            a = e.circumference;
          var r = e.endAngle;
          if (n) {
            Fn(t, e, i, s, o + yt);
            for (var _e58 = 0; _e58 < n; ++_e58) t.fill();
            isNaN(a) || (r = o + a % yt, a % yt == 0 && (r += yt));
          }
          return Fn(t, e, i, s, r), t.fill(), r;
        }(t, this, o, n);
        Bn(t, this, o, n, a), t.restore();
      }
    }]);
  }(Ds);
  function Wn(t, e) {
    var i = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : e;
    t.lineCap = K(i.borderCapStyle, e.borderCapStyle), t.setLineDash(K(i.borderDash, e.borderDash)), t.lineDashOffset = K(i.borderDashOffset, e.borderDashOffset), t.lineJoin = K(i.borderJoinStyle, e.borderJoinStyle), t.lineWidth = K(i.borderWidth, e.borderWidth), t.strokeStyle = K(i.borderColor, e.borderColor);
  }
  function Nn(t, e, i) {
    t.lineTo(i.x, i.y);
  }
  function Hn(t, e) {
    var i = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : {};
    var s = t.length,
      _i$start = i.start,
      n = _i$start === void 0 ? 0 : _i$start,
      _i$end = i.end,
      o = _i$end === void 0 ? s - 1 : _i$end,
      a = e.start,
      r = e.end,
      l = Math.max(n, a),
      h = Math.min(o, r),
      c = n < a && o < a || n > r && o > r;
    return {
      count: s,
      start: l,
      loop: e.loop,
      ilen: h < l && !c ? s + h - l : h - l
    };
  }
  function jn(t, e, i, s) {
    var n = e.points,
      o = e.options,
      _Hn = Hn(n, i, s),
      a = _Hn.count,
      r = _Hn.start,
      l = _Hn.loop,
      h = _Hn.ilen,
      c = function (t) {
        return t.stepped ? ee : t.tension || "monotone" === t.cubicInterpolationMode ? ie : Nn;
      }(o);
    var d,
      u,
      f,
      _ref12 = s || {},
      _ref12$move = _ref12.move,
      g = _ref12$move === void 0 ? !0 : _ref12$move,
      p = _ref12.reverse;
    for (d = 0; d <= h; ++d) u = n[(r + (p ? h - d : d)) % a], u.skip || (g ? (t.moveTo(u.x, u.y), g = !1) : c(t, f, u, p, o.stepped), f = u);
    return l && (u = n[(r + (p ? h : 0)) % a], c(t, f, u, p, o.stepped)), !!l;
  }
  function $n(t, e, i, s) {
    var n = e.points,
      _Hn2 = Hn(n, i, s),
      o = _Hn2.count,
      a = _Hn2.start,
      r = _Hn2.ilen,
      _ref13 = s || {},
      _ref13$move = _ref13.move,
      l = _ref13$move === void 0 ? !0 : _ref13$move,
      h = _ref13.reverse;
    var c,
      d,
      u,
      f,
      g,
      p,
      m = 0,
      x = 0;
    var b = function b(t) {
        return (a + (h ? r - t : t)) % o;
      },
      _ = function _() {
        f !== g && (t.lineTo(m, g), t.lineTo(m, f), t.lineTo(m, p));
      };
    for (l && (d = n[b(0)], t.moveTo(d.x, d.y)), c = 0; c <= r; ++c) {
      if (d = n[b(c)], d.skip) continue;
      var _e59 = d.x,
        _i52 = d.y,
        _s39 = 0 | _e59;
      _s39 === u ? (_i52 < f ? f = _i52 : _i52 > g && (g = _i52), m = (x * m + _e59) / ++x) : (_(), t.lineTo(_e59, _i52), u = _s39, x = 0, f = g = _i52), p = _i52;
    }
    _();
  }
  function Yn(t) {
    var e = t.options,
      i = e.borderDash && e.borderDash.length;
    return !(t._decimated || t._loop || e.tension || "monotone" === e.cubicInterpolationMode || e.stepped || i) ? $n : jn;
  }
  Vn.id = "arc", Vn.defaults = {
    borderAlign: "center",
    borderColor: "#fff",
    borderJoinStyle: void 0,
    borderRadius: 0,
    borderWidth: 2,
    offset: 0,
    spacing: 0,
    angle: void 0
  }, Vn.defaultRoutes = {
    backgroundColor: "backgroundColor"
  };
  var Un = "function" == typeof Path2D;
  function Xn(t, e, i, s) {
    Un && !e.options.segment ? function (t, e, i, s) {
      var n = e._path;
      n || (n = e._path = new Path2D(), e.path(n, i, s) && n.closePath()), Wn(t, e.options), t.stroke(n);
    }(t, e, i, s) : function (t, e, i, s) {
      var n = e.segments,
        o = e.options,
        a = Yn(e);
      var _iterator22 = _createForOfIteratorHelper(n),
        _step22;
      try {
        for (_iterator22.s(); !(_step22 = _iterator22.n()).done;) {
          var _r15 = _step22.value;
          Wn(t, o, _r15.style), t.beginPath(), a(t, e, _r15, {
            start: i,
            end: i + s - 1
          }) && t.closePath(), t.stroke();
        }
      } catch (err) {
        _iterator22.e(err);
      } finally {
        _iterator22.f();
      }
    }(t, e, i, s);
  }
  var qn = /*#__PURE__*/function (_Ds3) {
    function qn(t) {
      var _this23;
      _classCallCheck(this, qn);
      _this23 = _callSuper(this, qn), _this23.animated = !0, _this23.options = void 0, _this23._chart = void 0, _this23._loop = void 0, _this23._fullLoop = void 0, _this23._path = void 0, _this23._points = void 0, _this23._segments = void 0, _this23._decimated = !1, _this23._pointsUpdated = !1, _this23._datasetIndex = void 0, t && Object.assign(_assertThisInitialized(_this23), t);
      return _this23;
    }
    _inherits(qn, _Ds3);
    return _createClass(qn, [{
      key: "updateControlPoints",
      value: function updateControlPoints(t, e) {
        var i = this.options;
        if ((i.tension || "monotone" === i.cubicInterpolationMode) && !i.stepped && !this._pointsUpdated) {
          var _s40 = i.spanGaps ? this._loop : this._fullLoop;
          ki(this._points, i, t, _s40, e), this._pointsUpdated = !0;
        }
      }
    }, {
      key: "points",
      get: function get() {
        return this._points;
      },
      set: function set(t) {
        this._points = t, delete this._segments, delete this._path, this._pointsUpdated = !1;
      }
    }, {
      key: "segments",
      get: function get() {
        return this._segments || (this._segments = Ni(this, this.options.segment));
      }
    }, {
      key: "first",
      value: function first() {
        var t = this.segments,
          e = this.points;
        return t.length && e[t[0].start];
      }
    }, {
      key: "last",
      value: function last() {
        var t = this.segments,
          e = this.points,
          i = t.length;
        return i && e[t[i - 1].end];
      }
    }, {
      key: "interpolate",
      value: function interpolate(t, e) {
        var i = this.options,
          s = t[e],
          n = this.points,
          o = Wi(this, {
            property: e,
            start: s,
            end: s
          });
        if (!o.length) return;
        var a = [],
          r = function (t) {
            return t.stepped ? Ai : t.tension || "monotone" === t.cubicInterpolationMode ? Ti : Oi;
          }(i);
        var l, h;
        for (l = 0, h = o.length; l < h; ++l) {
          var _o$l = o[l],
            _h17 = _o$l.start,
            _c11 = _o$l.end,
            _d10 = n[_h17],
            _u5 = n[_c11];
          if (_d10 === _u5) {
            a.push(_d10);
            continue;
          }
          var _f5 = r(_d10, _u5, Math.abs((s - _d10[e]) / (_u5[e] - _d10[e])), i.stepped);
          _f5[e] = t[e], a.push(_f5);
        }
        return 1 === a.length ? a[0] : a;
      }
    }, {
      key: "pathSegment",
      value: function pathSegment(t, e, i) {
        return Yn(this)(t, this, e, i);
      }
    }, {
      key: "path",
      value: function path(t, e, i) {
        var s = this.segments,
          n = Yn(this);
        var o = this._loop;
        e = e || 0, i = i || this.points.length - e;
        var _iterator23 = _createForOfIteratorHelper(s),
          _step23;
        try {
          for (_iterator23.s(); !(_step23 = _iterator23.n()).done;) {
            var _a18 = _step23.value;
            o &= n(t, this, _a18, {
              start: e,
              end: e + i - 1
            });
          }
        } catch (err) {
          _iterator23.e(err);
        } finally {
          _iterator23.f();
        }
        return !!o;
      }
    }, {
      key: "draw",
      value: function draw(t, e, i, s) {
        var n = this.options || {};
        (this.points || []).length && n.borderWidth && (t.save(), Xn(t, this, i, s), t.restore()), this.animated && (this._pointsUpdated = !1, this._path = void 0);
      }
    }]);
  }(Ds);
  function Kn(t, e, i, s) {
    var n = t.options,
      _t$getProps2 = t.getProps([i], s),
      o = _t$getProps2[i];
    return Math.abs(e - o) < n.radius + n.hitRadius;
  }
  qn.id = "line", qn.defaults = {
    borderCapStyle: "butt",
    borderDash: [],
    borderDashOffset: 0,
    borderJoinStyle: "miter",
    borderWidth: 3,
    capBezierPoints: !0,
    cubicInterpolationMode: "default",
    fill: !1,
    spanGaps: !1,
    stepped: !1,
    tension: 0
  }, qn.defaultRoutes = {
    backgroundColor: "backgroundColor",
    borderColor: "borderColor"
  }, qn.descriptors = {
    _scriptable: !0,
    _indexable: function _indexable(t) {
      return "borderDash" !== t && "fill" !== t;
    }
  };
  var Gn = /*#__PURE__*/function (_Ds4) {
    function Gn(t) {
      var _this24;
      _classCallCheck(this, Gn);
      _this24 = _callSuper(this, Gn), _this24.options = void 0, _this24.parsed = void 0, _this24.skip = void 0, _this24.stop = void 0, t && Object.assign(_assertThisInitialized(_this24), t);
      return _this24;
    }
    _inherits(Gn, _Ds4);
    return _createClass(Gn, [{
      key: "inRange",
      value: function inRange(t, e, i) {
        var s = this.options,
          _this$getProps4 = this.getProps(["x", "y"], i),
          n = _this$getProps4.x,
          o = _this$getProps4.y;
        return Math.pow(t - n, 2) + Math.pow(e - o, 2) < Math.pow(s.hitRadius + s.radius, 2);
      }
    }, {
      key: "inXRange",
      value: function inXRange(t, e) {
        return Kn(this, t, "x", e);
      }
    }, {
      key: "inYRange",
      value: function inYRange(t, e) {
        return Kn(this, t, "y", e);
      }
    }, {
      key: "getCenterPoint",
      value: function getCenterPoint(t) {
        var _this$getProps5 = this.getProps(["x", "y"], t),
          e = _this$getProps5.x,
          i = _this$getProps5.y;
        return {
          x: e,
          y: i
        };
      }
    }, {
      key: "size",
      value: function size(t) {
        var e = (t = t || this.options || {}).radius || 0;
        e = Math.max(e, e && t.hoverRadius || 0);
        return 2 * (e + (e && t.borderWidth || 0));
      }
    }, {
      key: "draw",
      value: function draw(t, e) {
        var i = this.options;
        this.skip || i.radius < .1 || !Jt(this, e, this.size(i) / 2) || (t.strokeStyle = i.borderColor, t.lineWidth = i.borderWidth, t.fillStyle = i.backgroundColor, Zt(t, i, this.x, this.y));
      }
    }, {
      key: "getRange",
      value: function getRange() {
        var t = this.options || {};
        return t.radius + t.hitRadius;
      }
    }]);
  }(Ds);
  function Zn(t, e) {
    var _t$getProps3 = t.getProps(["x", "y", "base", "width", "height"], e),
      i = _t$getProps3.x,
      s = _t$getProps3.y,
      n = _t$getProps3.base,
      o = _t$getProps3.width,
      a = _t$getProps3.height;
    var r, l, h, c, d;
    return t.horizontal ? (d = a / 2, r = Math.min(i, n), l = Math.max(i, n), h = s - d, c = s + d) : (d = o / 2, r = i - d, l = i + d, h = Math.min(s, n), c = Math.max(s, n)), {
      left: r,
      top: h,
      right: l,
      bottom: c
    };
  }
  function Jn(t, e, i, s) {
    return t ? 0 : jt(e, i, s);
  }
  function Qn(t) {
    var e = Zn(t),
      i = e.right - e.left,
      s = e.bottom - e.top,
      n = function (t, e, i) {
        var s = t.options.borderWidth,
          n = t.borderSkipped,
          o = Ve(s);
        return {
          t: Jn(n.top, o.top, 0, i),
          r: Jn(n.right, o.right, 0, e),
          b: Jn(n.bottom, o.bottom, 0, i),
          l: Jn(n.left, o.left, 0, e)
        };
      }(t, i / 2, s / 2),
      o = function (t, e, i) {
        var _t$getProps4 = t.getProps(["enableBorderRadius"]),
          s = _t$getProps4.enableBorderRadius,
          n = t.options.borderRadius,
          o = We(n),
          a = Math.min(e, i),
          r = t.borderSkipped,
          l = s || U(n);
        return {
          topLeft: Jn(!l || r.top || r.left, o.topLeft, 0, a),
          topRight: Jn(!l || r.top || r.right, o.topRight, 0, a),
          bottomLeft: Jn(!l || r.bottom || r.left, o.bottomLeft, 0, a),
          bottomRight: Jn(!l || r.bottom || r.right, o.bottomRight, 0, a)
        };
      }(t, i / 2, s / 2);
    return {
      outer: {
        x: e.left,
        y: e.top,
        w: i,
        h: s,
        radius: o
      },
      inner: {
        x: e.left + n.l,
        y: e.top + n.t,
        w: i - n.l - n.r,
        h: s - n.t - n.b,
        radius: {
          topLeft: Math.max(0, o.topLeft - Math.max(n.t, n.l)),
          topRight: Math.max(0, o.topRight - Math.max(n.t, n.r)),
          bottomLeft: Math.max(0, o.bottomLeft - Math.max(n.b, n.l)),
          bottomRight: Math.max(0, o.bottomRight - Math.max(n.b, n.r))
        }
      }
    };
  }
  function to(t, e, i, s) {
    var n = null === e,
      o = null === i,
      a = t && !(n && o) && Zn(t, s);
    return a && (n || Yt(e, a.left, a.right)) && (o || Yt(i, a.top, a.bottom));
  }
  function eo(t, e) {
    t.rect(e.x, e.y, e.w, e.h);
  }
  function io(t, e) {
    var i = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : {};
    var s = t.x !== i.x ? -e : 0,
      n = t.y !== i.y ? -e : 0,
      o = (t.x + t.w !== i.x + i.w ? e : 0) - s,
      a = (t.y + t.h !== i.y + i.h ? e : 0) - n;
    return {
      x: t.x + s,
      y: t.y + n,
      w: t.w + o,
      h: t.h + a,
      radius: t.radius
    };
  }
  Gn.id = "point", Gn.defaults = {
    borderWidth: 1,
    hitRadius: 1,
    hoverBorderWidth: 1,
    hoverRadius: 4,
    pointStyle: "circle",
    radius: 3,
    rotation: 0
  }, Gn.defaultRoutes = {
    backgroundColor: "backgroundColor",
    borderColor: "borderColor"
  };
  var so = /*#__PURE__*/function (_Ds5) {
    function so(t) {
      var _this25;
      _classCallCheck(this, so);
      _this25 = _callSuper(this, so), _this25.options = void 0, _this25.horizontal = void 0, _this25.base = void 0, _this25.width = void 0, _this25.height = void 0, _this25.inflateAmount = void 0, t && Object.assign(_assertThisInitialized(_this25), t);
      return _this25;
    }
    _inherits(so, _Ds5);
    return _createClass(so, [{
      key: "draw",
      value: function draw(t) {
        var e = this.inflateAmount,
          _this$options12 = this.options,
          i = _this$options12.borderColor,
          s = _this$options12.backgroundColor,
          _Qn = Qn(this),
          n = _Qn.inner,
          o = _Qn.outer,
          a = (r = o.radius).topLeft || r.topRight || r.bottomLeft || r.bottomRight ? oe : eo;
        var r;
        t.save(), o.w === n.w && o.h === n.h || (t.beginPath(), a(t, io(o, e, n)), t.clip(), a(t, io(n, -e, o)), t.fillStyle = i, t.fill("evenodd")), t.beginPath(), a(t, io(n, e)), t.fillStyle = s, t.fill(), t.restore();
      }
    }, {
      key: "inRange",
      value: function inRange(t, e, i) {
        return to(this, t, e, i);
      }
    }, {
      key: "inXRange",
      value: function inXRange(t, e) {
        return to(this, t, null, e);
      }
    }, {
      key: "inYRange",
      value: function inYRange(t, e) {
        return to(this, null, t, e);
      }
    }, {
      key: "getCenterPoint",
      value: function getCenterPoint(t) {
        var _this$getProps6 = this.getProps(["x", "y", "base", "horizontal"], t),
          e = _this$getProps6.x,
          i = _this$getProps6.y,
          s = _this$getProps6.base,
          n = _this$getProps6.horizontal;
        return {
          x: n ? (e + s) / 2 : e,
          y: n ? i : (i + s) / 2
        };
      }
    }, {
      key: "getRange",
      value: function getRange(t) {
        return "x" === t ? this.width / 2 : this.height / 2;
      }
    }]);
  }(Ds);
  so.id = "bar", so.defaults = {
    borderSkipped: "start",
    borderWidth: 0,
    borderRadius: 0,
    inflateAmount: "auto",
    pointStyle: void 0
  }, so.defaultRoutes = {
    backgroundColor: "backgroundColor",
    borderColor: "borderColor"
  };
  var no = Object.freeze({
    __proto__: null,
    ArcElement: Vn,
    LineElement: qn,
    PointElement: Gn,
    BarElement: so
  });
  function oo(t) {
    if (t._decimated) {
      var _e60 = t._data;
      delete t._decimated, delete t._data, Object.defineProperty(t, "data", {
        value: _e60
      });
    }
  }
  function ao(t) {
    t.data.datasets.forEach(function (t) {
      oo(t);
    });
  }
  var ro = {
    id: "decimation",
    defaults: {
      algorithm: "min-max",
      enabled: !1
    },
    beforeElementsUpdate: function beforeElementsUpdate(t, e, i) {
      if (!i.enabled) return void ao(t);
      var s = t.width;
      t.data.datasets.forEach(function (e, n) {
        var o = e._data,
          a = e.indexAxis,
          r = t.getDatasetMeta(n),
          l = o || e.data;
        if ("y" === je([a, t.options.indexAxis])) return;
        if ("line" !== r.type) return;
        var h = t.scales[r.xAxisID];
        if ("linear" !== h.type && "time" !== h.type) return;
        if (t.options.parsing) return;
        var _ref14 = function (t, e) {
            var i = e.length;
            var s,
              n = 0;
            var o = t.iScale,
              _o$getUserBounds = o.getUserBounds(),
              a = _o$getUserBounds.min,
              r = _o$getUserBounds.max,
              l = _o$getUserBounds.minDefined,
              h = _o$getUserBounds.maxDefined;
            return l && (n = jt(re(e, o.axis, a).lo, 0, i - 1)), s = h ? jt(re(e, o.axis, r).hi + 1, n, i) - n : i - n, {
              start: n,
              count: s
            };
          }(r, l),
          c = _ref14.start,
          d = _ref14.count;
        if (d <= (i.threshold || 4 * s)) return void oo(e);
        var u;
        switch ($(o) && (e._data = l, delete e.data, Object.defineProperty(e, "data", {
          configurable: !0,
          enumerable: !0,
          get: function get() {
            return this._decimated;
          },
          set: function set(t) {
            this._data = t;
          }
        })), i.algorithm) {
          case "lttb":
            u = function (t, e, i, s, n) {
              var o = n.samples || s;
              if (o >= i) return t.slice(e, e + i);
              var a = [],
                r = (i - 2) / (o - 2);
              var l = 0;
              var h = e + i - 1;
              var c,
                d,
                u,
                f,
                g,
                p = e;
              for (a[l++] = t[p], c = 0; c < o - 2; c++) {
                var _s41 = void 0,
                  _n28 = 0,
                  _o30 = 0;
                var _h18 = Math.floor((c + 1) * r) + 1 + e,
                  _m4 = Math.min(Math.floor((c + 2) * r) + 1, i) + e,
                  _x3 = _m4 - _h18;
                for (_s41 = _h18; _s41 < _m4; _s41++) _n28 += t[_s41].x, _o30 += t[_s41].y;
                _n28 /= _x3, _o30 /= _x3;
                var _b2 = Math.floor(c * r) + 1 + e,
                  _3 = Math.min(Math.floor((c + 1) * r) + 1, i) + e,
                  _t$p = t[p],
                  _y2 = _t$p.x,
                  _v2 = _t$p.y;
                for (u = f = -1, _s41 = _b2; _s41 < _3; _s41++) f = .5 * Math.abs((_y2 - _n28) * (t[_s41].y - _v2) - (_y2 - t[_s41].x) * (_o30 - _v2)), f > u && (u = f, d = t[_s41], g = _s41);
                a[l++] = d, p = g;
              }
              return a[l++] = t[h], a;
            }(l, c, d, s, i);
            break;
          case "min-max":
            u = function (t, e, i, s) {
              var n,
                o,
                a,
                r,
                l,
                h,
                c,
                d,
                u,
                f,
                g = 0,
                p = 0;
              var m = [],
                x = e + i - 1,
                b = t[e].x,
                _ = t[x].x - b;
              for (n = e; n < e + i; ++n) {
                o = t[n], a = (o.x - b) / _ * s, r = o.y;
                var _e61 = 0 | a;
                if (_e61 === l) r < u ? (u = r, h = n) : r > f && (f = r, c = n), g = (p * g + o.x) / ++p;else {
                  var _i53 = n - 1;
                  if (!$(h) && !$(c)) {
                    var _e62 = Math.min(h, c),
                      _s42 = Math.max(h, c);
                    _e62 !== d && _e62 !== _i53 && m.push(_objectSpread(_objectSpread({}, t[_e62]), {}, {
                      x: g
                    })), _s42 !== d && _s42 !== _i53 && m.push(_objectSpread(_objectSpread({}, t[_s42]), {}, {
                      x: g
                    }));
                  }
                  n > 0 && _i53 !== d && m.push(t[_i53]), m.push(o), l = _e61, p = 0, u = f = r, h = c = d = n;
                }
              }
              return m;
            }(l, c, d, s);
            break;
          default:
            throw new Error("Unsupported decimation algorithm '".concat(i.algorithm, "'"));
        }
        e._decimated = u;
      });
    },
    destroy: function destroy(t) {
      ao(t);
    }
  };
  function lo(t, e, i) {
    var s = function (t) {
      var e = t.options,
        i = e.fill;
      var s = K(i && i.target, i);
      return void 0 === s && (s = !!e.backgroundColor), !1 !== s && null !== s && (!0 === s ? "origin" : s);
    }(t);
    if (U(s)) return !isNaN(s.value) && s;
    var n = parseFloat(s);
    return X(n) && Math.floor(n) === n ? ("-" !== s[0] && "+" !== s[0] || (n = e + n), !(n === e || n < 0 || n >= i) && n) : ["origin", "start", "end", "stack", "shape"].indexOf(s) >= 0 && s;
  }
  var ho = /*#__PURE__*/function () {
    function ho(t) {
      _classCallCheck(this, ho);
      this.x = t.x, this.y = t.y, this.radius = t.radius;
    }
    return _createClass(ho, [{
      key: "pathSegment",
      value: function pathSegment(t, e, i) {
        var s = this.x,
          n = this.y,
          o = this.radius;
        return e = e || {
          start: 0,
          end: yt
        }, t.arc(s, n, o, e.end, e.start, !0), !i.bounds;
      }
    }, {
      key: "interpolate",
      value: function interpolate(t) {
        var e = this.x,
          i = this.y,
          s = this.radius,
          n = t.angle;
        return {
          x: e + Math.cos(n) * s,
          y: i + Math.sin(n) * s,
          angle: n
        };
      }
    }]);
  }();
  function co(t) {
    return (t.scale || {}).getPointPositionForValue ? function (t) {
      var e = t.scale,
        i = t.fill,
        s = e.options,
        n = e.getLabels().length,
        o = [],
        a = s.reverse ? e.max : e.min,
        r = s.reverse ? e.min : e.max;
      var l, h, c;
      if (c = "start" === i ? a : "end" === i ? r : U(i) ? i.value : e.getBaseValue(), s.grid.circular) return h = e.getPointPositionForValue(0, a), new ho({
        x: h.x,
        y: h.y,
        radius: e.getDistanceFromCenterForValue(c)
      });
      for (l = 0; l < n; ++l) o.push(e.getPointPositionForValue(l, c));
      return o;
    }(t) : function (t) {
      var _t$scale = t.scale,
        e = _t$scale === void 0 ? {} : _t$scale,
        i = t.fill;
      var s,
        n = null;
      return "start" === i ? n = e.bottom : "end" === i ? n = e.top : U(i) ? n = e.getPixelForValue(i.value) : e.getBasePixel && (n = e.getBasePixel()), X(n) ? (s = e.isHorizontal(), {
        x: s ? n : null,
        y: s ? null : n
      }) : null;
    }(t);
  }
  function uo(t, e, i) {
    for (; e > t; e--) {
      var _t57 = i[e];
      if (!isNaN(_t57.x) && !isNaN(_t57.y)) break;
    }
    return e;
  }
  function fo(t, e, i) {
    var s = [];
    for (var _n29 = 0; _n29 < i.length; _n29++) {
      var _o31 = i[_n29],
        _go = go(_o31, e, "x"),
        _a19 = _go.first,
        _r16 = _go.last,
        _l18 = _go.point;
      if (!(!_l18 || _a19 && _r16)) if (_a19) s.unshift(_l18);else if (t.push(_l18), !_r16) break;
    }
    t.push.apply(t, s);
  }
  function go(t, e, i) {
    var s = t.interpolate(e, i);
    if (!s) return {};
    var n = s[i],
      o = t.segments,
      a = t.points;
    var r = !1,
      l = !1;
    for (var _t58 = 0; _t58 < o.length; _t58++) {
      var _e63 = o[_t58],
        _s43 = a[_e63.start][i],
        _h19 = a[_e63.end][i];
      if (Yt(n, _s43, _h19)) {
        r = n === _s43, l = n === _h19;
        break;
      }
    }
    return {
      first: r,
      last: l,
      point: s
    };
  }
  function po(t) {
    var e = t.chart,
      i = t.fill,
      s = t.line;
    if (X(i)) return function (t, e) {
      var i = t.getDatasetMeta(e);
      return i && t.isDatasetVisible(e) ? i.dataset : null;
    }(e, i);
    if ("stack" === i) return function (t) {
      var e = t.scale,
        i = t.index,
        s = t.line,
        n = [],
        o = s.segments,
        a = s.points,
        r = function (t, e) {
          var i = [],
            s = t.getMatchingVisibleMetas("line");
          for (var _t59 = 0; _t59 < s.length; _t59++) {
            var _n30 = s[_t59];
            if (_n30.index === e) break;
            _n30.hidden || i.unshift(_n30.dataset);
          }
          return i;
        }(e, i);
      r.push(mo({
        x: null,
        y: e.bottom
      }, s));
      for (var _t60 = 0; _t60 < o.length; _t60++) {
        var _e64 = o[_t60];
        for (var _t61 = _e64.start; _t61 <= _e64.end; _t61++) fo(n, a[_t61], r);
      }
      return new qn({
        points: n,
        options: {}
      });
    }(t);
    if ("shape" === i) return !0;
    var n = co(t);
    return n instanceof ho ? n : mo(n, s);
  }
  function mo(t, e) {
    var i = [],
      s = !1;
    return Y(t) ? (s = !0, i = t) : i = function (t, e) {
      var _ref15 = t || {},
        _ref15$x = _ref15.x,
        i = _ref15$x === void 0 ? null : _ref15$x,
        _ref15$y = _ref15.y,
        s = _ref15$y === void 0 ? null : _ref15$y,
        n = e.points,
        o = [];
      return e.segments.forEach(function (_ref16) {
        var t = _ref16.start,
          e = _ref16.end;
        e = uo(t, e, n);
        var a = n[t],
          r = n[e];
        null !== s ? (o.push({
          x: a.x,
          y: s
        }), o.push({
          x: r.x,
          y: s
        })) : null !== i && (o.push({
          x: i,
          y: a.y
        }), o.push({
          x: i,
          y: r.y
        }));
      }), o;
    }(t, e), i.length ? new qn({
      points: i,
      options: {
        tension: 0
      },
      _loop: s,
      _fullLoop: s
    }) : null;
  }
  function xo(t, e, i) {
    var s = t[e].fill;
    var n = [e];
    var o;
    if (!i) return s;
    for (; !1 !== s && -1 === n.indexOf(s);) {
      if (!X(s)) return s;
      if (o = t[s], !o) return !1;
      if (o.visible) return s;
      n.push(s), s = o.fill;
    }
    return !1;
  }
  function bo(t, e, i) {
    var s = e.segments,
      n = e.points;
    var o = !0,
      a = !1;
    t.beginPath();
    var _iterator24 = _createForOfIteratorHelper(s),
      _step24;
    try {
      for (_iterator24.s(); !(_step24 = _iterator24.n()).done;) {
        var _r17 = _step24.value;
        var _s44 = _r17.start,
          _l19 = _r17.end,
          _h20 = n[_s44],
          _c12 = n[uo(_s44, _l19, n)];
        o ? (t.moveTo(_h20.x, _h20.y), o = !1) : (t.lineTo(_h20.x, i), t.lineTo(_h20.x, _h20.y)), a = !!e.pathSegment(t, _r17, {
          move: a
        }), a ? t.closePath() : t.lineTo(_c12.x, i);
      }
    } catch (err) {
      _iterator24.e(err);
    } finally {
      _iterator24.f();
    }
    t.lineTo(e.first().x, i), t.closePath(), t.clip();
  }
  function _o(t, e, i, s) {
    if (s) return;
    var n = e[t],
      o = i[t];
    return "angle" === t && (n = Nt(n), o = Nt(o)), {
      property: t,
      start: n,
      end: o
    };
  }
  function yo(t, e, i, s) {
    return t && e ? s(t[i], e[i]) : t ? t[i] : e ? e[i] : 0;
  }
  function vo(t, e, i) {
    var _e$chart$chartArea = e.chart.chartArea,
      s = _e$chart$chartArea.top,
      n = _e$chart$chartArea.bottom,
      _ref17 = i || {},
      o = _ref17.property,
      a = _ref17.start,
      r = _ref17.end;
    "x" === o && (t.beginPath(), t.rect(a, s, r - a, n - s), t.clip());
  }
  function wo(t, e, i, s) {
    var n = e.interpolate(i, s);
    n && t.lineTo(n.x, n.y);
  }
  function Mo(t, e) {
    var i = e.line,
      s = e.target,
      n = e.property,
      o = e.color,
      a = e.scale,
      r = function (t, e, i) {
        var s = t.segments,
          n = t.points,
          o = e.points,
          a = [];
        var _iterator25 = _createForOfIteratorHelper(s),
          _step25;
        try {
          for (_iterator25.s(); !(_step25 = _iterator25.n()).done;) {
            var _t62 = _step25.value;
            var _s45 = _t62.start,
              _r18 = _t62.end;
            _r18 = uo(_s45, _r18, n);
            var _l20 = _o(i, n[_s45], n[_r18], _t62.loop);
            if (!e.segments) {
              a.push({
                source: _t62,
                target: _l20,
                start: n[_s45],
                end: n[_r18]
              });
              continue;
            }
            var _h21 = Wi(e, _l20);
            var _iterator26 = _createForOfIteratorHelper(_h21),
              _step26;
            try {
              for (_iterator26.s(); !(_step26 = _iterator26.n()).done;) {
                var _e65 = _step26.value;
                var _s46 = _o(i, o[_e65.start], o[_e65.end], _e65.loop),
                  _r19 = Vi(_t62, n, _s46);
                var _iterator27 = _createForOfIteratorHelper(_r19),
                  _step27;
                try {
                  for (_iterator27.s(); !(_step27 = _iterator27.n()).done;) {
                    var _t63 = _step27.value;
                    a.push({
                      source: _t63,
                      target: _e65,
                      start: _defineProperty({}, i, yo(_l20, _s46, "start", Math.max)),
                      end: _defineProperty({}, i, yo(_l20, _s46, "end", Math.min))
                    });
                  }
                } catch (err) {
                  _iterator27.e(err);
                } finally {
                  _iterator27.f();
                }
              }
            } catch (err) {
              _iterator26.e(err);
            } finally {
              _iterator26.f();
            }
          }
        } catch (err) {
          _iterator25.e(err);
        } finally {
          _iterator25.f();
        }
        return a;
      }(i, s, n);
    var _iterator28 = _createForOfIteratorHelper(r),
      _step28;
    try {
      for (_iterator28.s(); !(_step28 = _iterator28.n()).done;) {
        var _step28$value = _step28.value,
          _e66 = _step28$value.source,
          _l21 = _step28$value.target,
          _h22 = _step28$value.start,
          _c13 = _step28$value.end;
        var _e66$style = _e66.style,
          _e66$style2 = _e66$style === void 0 ? {} : _e66$style,
          _e66$style2$backgroun = _e66$style2.backgroundColor,
          _r20 = _e66$style2$backgroun === void 0 ? o : _e66$style2$backgroun,
          _d11 = !0 !== s;
        t.save(), t.fillStyle = _r20, vo(t, a, _d11 && _o(n, _h22, _c13)), t.beginPath();
        var _u6 = !!i.pathSegment(t, _e66);
        var _f6 = void 0;
        if (_d11) {
          _u6 ? t.closePath() : wo(t, s, _c13, n);
          var _e67 = !!s.pathSegment(t, _l21, {
            move: _u6,
            reverse: !0
          });
          _f6 = _u6 && _e67, _f6 || wo(t, s, _h22, n);
        }
        t.closePath(), t.fill(_f6 ? "evenodd" : "nonzero"), t.restore();
      }
    } catch (err) {
      _iterator28.e(err);
    } finally {
      _iterator28.f();
    }
  }
  function ko(t, e, i) {
    var s = po(e),
      n = e.line,
      o = e.scale,
      a = e.axis,
      r = n.options,
      l = r.fill,
      h = r.backgroundColor,
      _ref18 = l || {},
      _ref18$above = _ref18.above,
      c = _ref18$above === void 0 ? h : _ref18$above,
      _ref18$below = _ref18.below,
      d = _ref18$below === void 0 ? h : _ref18$below;
    s && n.points.length && (Qt(t, i), function (t, e) {
      var i = e.line,
        s = e.target,
        n = e.above,
        o = e.below,
        a = e.area,
        r = e.scale,
        l = i._loop ? "angle" : e.axis;
      t.save(), "x" === l && o !== n && (bo(t, s, a.top), Mo(t, {
        line: i,
        target: s,
        color: n,
        scale: r,
        property: l
      }), t.restore(), t.save(), bo(t, s, a.bottom)), Mo(t, {
        line: i,
        target: s,
        color: o,
        scale: r,
        property: l
      }), t.restore();
    }(t, {
      line: n,
      target: s,
      above: c,
      below: d,
      area: i,
      scale: o,
      axis: a
    }), te(t));
  }
  var So = {
    id: "filler",
    afterDatasetsUpdate: function afterDatasetsUpdate(t, e, i) {
      var s = (t.data.datasets || []).length,
        n = [];
      var o, a, r, l;
      for (a = 0; a < s; ++a) o = t.getDatasetMeta(a), r = o.dataset, l = null, r && r.options && r instanceof qn && (l = {
        visible: t.isDatasetVisible(a),
        index: a,
        fill: lo(r, a, s),
        chart: t,
        axis: o.controller.options.indexAxis,
        scale: o.vScale,
        line: r
      }), o.$filler = l, n.push(l);
      for (a = 0; a < s; ++a) l = n[a], l && !1 !== l.fill && (l.fill = xo(n, a, i.propagate));
    },
    beforeDraw: function beforeDraw(t, e, i) {
      var s = "beforeDraw" === i.drawTime,
        n = t.getSortedVisibleDatasetMetas(),
        o = t.chartArea;
      for (var _e68 = n.length - 1; _e68 >= 0; --_e68) {
        var _i54 = n[_e68].$filler;
        _i54 && (_i54.line.updateControlPoints(o, _i54.axis), s && ko(t.ctx, _i54, o));
      }
    },
    beforeDatasetsDraw: function beforeDatasetsDraw(t, e, i) {
      if ("beforeDatasetsDraw" !== i.drawTime) return;
      var s = t.getSortedVisibleDatasetMetas();
      for (var _e69 = s.length - 1; _e69 >= 0; --_e69) {
        var _i55 = s[_e69].$filler;
        _i55 && ko(t.ctx, _i55, t.chartArea);
      }
    },
    beforeDatasetDraw: function beforeDatasetDraw(t, e, i) {
      var s = e.meta.$filler;
      s && !1 !== s.fill && "beforeDatasetDraw" === i.drawTime && ko(t.ctx, s, t.chartArea);
    },
    defaults: {
      propagate: !0,
      drawTime: "beforeDatasetDraw"
    }
  };
  var Po = function Po(t, e) {
    var _t$boxHeight = t.boxHeight,
      i = _t$boxHeight === void 0 ? e : _t$boxHeight,
      _t$boxWidth = t.boxWidth,
      s = _t$boxWidth === void 0 ? e : _t$boxWidth;
    return t.usePointStyle && (i = Math.min(i, e), s = Math.min(s, e)), {
      boxWidth: s,
      boxHeight: i,
      itemHeight: Math.max(e, i)
    };
  };
  var Do = /*#__PURE__*/function (_Ds6) {
    function Do(t) {
      var _this26;
      _classCallCheck(this, Do);
      _this26 = _callSuper(this, Do), _this26._added = !1, _this26.legendHitBoxes = [], _this26._hoveredItem = null, _this26.doughnutMode = !1, _this26.chart = t.chart, _this26.options = t.options, _this26.ctx = t.ctx, _this26.legendItems = void 0, _this26.columnSizes = void 0, _this26.lineWidths = void 0, _this26.maxHeight = void 0, _this26.maxWidth = void 0, _this26.top = void 0, _this26.bottom = void 0, _this26.left = void 0, _this26.right = void 0, _this26.height = void 0, _this26.width = void 0, _this26._margins = void 0, _this26.position = void 0, _this26.weight = void 0, _this26.fullSize = void 0;
      return _this26;
    }
    _inherits(Do, _Ds6);
    return _createClass(Do, [{
      key: "update",
      value: function update(t, e, i) {
        this.maxWidth = t, this.maxHeight = e, this._margins = i, this.setDimensions(), this.buildLabels(), this.fit();
      }
    }, {
      key: "setDimensions",
      value: function setDimensions() {
        this.isHorizontal() ? (this.width = this.maxWidth, this.left = this._margins.left, this.right = this.width) : (this.height = this.maxHeight, this.top = this._margins.top, this.bottom = this.height);
      }
    }, {
      key: "buildLabels",
      value: function buildLabels() {
        var _this27 = this;
        var t = this.options.labels || {};
        var e = J(t.generateLabels, [this.chart], this) || [];
        t.filter && (e = e.filter(function (e) {
          return t.filter(e, _this27.chart.data);
        })), t.sort && (e = e.sort(function (e, i) {
          return t.sort(e, i, _this27.chart.data);
        })), this.options.reverse && e.reverse(), this.legendItems = e;
      }
    }, {
      key: "fit",
      value: function fit() {
        var t = this.options,
          e = this.ctx;
        if (!t.display) return void (this.width = this.height = 0);
        var i = t.labels,
          s = He(i.font),
          n = s.size,
          o = this._computeTitleHeight(),
          _Po = Po(i, n),
          a = _Po.boxWidth,
          r = _Po.itemHeight;
        var l, h;
        e.font = s.string, this.isHorizontal() ? (l = this.maxWidth, h = this._fitRows(o, n, a, r) + 10) : (h = this.maxHeight, l = this._fitCols(o, n, a, r) + 10), this.width = Math.min(l, t.maxWidth || this.maxWidth), this.height = Math.min(h, t.maxHeight || this.maxHeight);
      }
    }, {
      key: "_fitRows",
      value: function _fitRows(t, e, i, s) {
        var n = this.ctx,
          o = this.maxWidth,
          a = this.options.labels.padding,
          r = this.legendHitBoxes = [],
          l = this.lineWidths = [0],
          h = s + a;
        var c = t;
        n.textAlign = "left", n.textBaseline = "middle";
        var d = -1,
          u = -h;
        return this.legendItems.forEach(function (t, f) {
          var g = i + e / 2 + n.measureText(t.text).width;
          (0 === f || l[l.length - 1] + g + 2 * a > o) && (c += h, l[l.length - (f > 0 ? 0 : 1)] = 0, u += h, d++), r[f] = {
            left: 0,
            top: u,
            row: d,
            width: g,
            height: s
          }, l[l.length - 1] += g + a;
        }), c;
      }
    }, {
      key: "_fitCols",
      value: function _fitCols(t, e, i, s) {
        var n = this.ctx,
          o = this.maxHeight,
          a = this.options.labels.padding,
          r = this.legendHitBoxes = [],
          l = this.columnSizes = [],
          h = o - t;
        var c = a,
          d = 0,
          u = 0,
          f = 0,
          g = 0;
        return this.legendItems.forEach(function (t, o) {
          var p = i + e / 2 + n.measureText(t.text).width;
          o > 0 && u + s + 2 * a > h && (c += d + a, l.push({
            width: d,
            height: u
          }), f += d + a, g++, d = u = 0), r[o] = {
            left: f,
            top: u,
            col: g,
            width: p,
            height: s
          }, d = Math.max(d, p), u += s + a;
        }), c += d, l.push({
          width: d,
          height: u
        }), c;
      }
    }, {
      key: "adjustHitBoxes",
      value: function adjustHitBoxes() {
        if (!this.options.display) return;
        var t = this._computeTitleHeight(),
          e = this.legendHitBoxes,
          _this$options13 = this.options,
          i = _this$options13.align,
          s = _this$options13.labels.padding,
          o = _this$options13.rtl,
          a = Ei(o, this.left, this.width);
        if (this.isHorizontal()) {
          var _o32 = 0,
            _r21 = n(i, this.left + s, this.right - this.lineWidths[_o32]);
          var _iterator29 = _createForOfIteratorHelper(e),
            _step29;
          try {
            for (_iterator29.s(); !(_step29 = _iterator29.n()).done;) {
              var _l22 = _step29.value;
              _o32 !== _l22.row && (_o32 = _l22.row, _r21 = n(i, this.left + s, this.right - this.lineWidths[_o32])), _l22.top += this.top + t + s, _l22.left = a.leftForLtr(a.x(_r21), _l22.width), _r21 += _l22.width + s;
            }
          } catch (err) {
            _iterator29.e(err);
          } finally {
            _iterator29.f();
          }
        } else {
          var _o33 = 0,
            _r22 = n(i, this.top + t + s, this.bottom - this.columnSizes[_o33].height);
          var _iterator30 = _createForOfIteratorHelper(e),
            _step30;
          try {
            for (_iterator30.s(); !(_step30 = _iterator30.n()).done;) {
              var _l23 = _step30.value;
              _l23.col !== _o33 && (_o33 = _l23.col, _r22 = n(i, this.top + t + s, this.bottom - this.columnSizes[_o33].height)), _l23.top = _r22, _l23.left += this.left + s, _l23.left = a.leftForLtr(a.x(_l23.left), _l23.width), _r22 += _l23.height + s;
            }
          } catch (err) {
            _iterator30.e(err);
          } finally {
            _iterator30.f();
          }
        }
      }
    }, {
      key: "isHorizontal",
      value: function isHorizontal() {
        return "top" === this.options.position || "bottom" === this.options.position;
      }
    }, {
      key: "draw",
      value: function draw() {
        if (this.options.display) {
          var _t64 = this.ctx;
          Qt(_t64, this), this._draw(), te(_t64);
        }
      }
    }, {
      key: "_draw",
      value: function _draw() {
        var _this28 = this;
        var t = this.options,
          e = this.columnSizes,
          i = this.lineWidths,
          s = this.ctx,
          a = t.align,
          r = t.labels,
          l = bt.color,
          h = Ei(t.rtl, this.left, this.width),
          c = He(r.font),
          d = r.color,
          u = r.padding,
          f = c.size,
          g = f / 2;
        var p;
        this.drawTitle(), s.textAlign = h.textAlign("left"), s.textBaseline = "middle", s.lineWidth = .5, s.font = c.string;
        var _Po2 = Po(r, f),
          m = _Po2.boxWidth,
          x = _Po2.boxHeight,
          b = _Po2.itemHeight,
          _ = this.isHorizontal(),
          y = this._computeTitleHeight();
        p = _ ? {
          x: n(a, this.left + u, this.right - i[0]),
          y: this.top + u + y,
          line: 0
        } : {
          x: this.left + u,
          y: n(a, this.top + y + u, this.bottom - e[0].height),
          line: 0
        }, Ii(this.ctx, t.textDirection);
        var v = b + u;
        this.legendItems.forEach(function (w, M) {
          s.strokeStyle = w.fontColor || d, s.fillStyle = w.fontColor || d;
          var k = s.measureText(w.text).width,
            S = h.textAlign(w.textAlign || (w.textAlign = r.textAlign)),
            P = m + g + k;
          var D = p.x,
            C = p.y;
          h.setWidth(_this28.width), _ ? M > 0 && D + P + u > _this28.right && (C = p.y += v, p.line++, D = p.x = n(a, _this28.left + u, _this28.right - i[p.line])) : M > 0 && C + v > _this28.bottom && (D = p.x = D + e[p.line].width + u, p.line++, C = p.y = n(a, _this28.top + y + u, _this28.bottom - e[p.line].height));
          !function (t, e, i) {
            if (isNaN(m) || m <= 0 || isNaN(x) || x < 0) return;
            s.save();
            var n = K(i.lineWidth, 1);
            if (s.fillStyle = K(i.fillStyle, l), s.lineCap = K(i.lineCap, "butt"), s.lineDashOffset = K(i.lineDashOffset, 0), s.lineJoin = K(i.lineJoin, "miter"), s.lineWidth = n, s.strokeStyle = K(i.strokeStyle, l), s.setLineDash(K(i.lineDash, [])), r.usePointStyle) {
              var _o34 = {
                  radius: m * Math.SQRT2 / 2,
                  pointStyle: i.pointStyle,
                  rotation: i.rotation,
                  borderWidth: n
                },
                _a20 = h.xPlus(t, m / 2);
              Zt(s, _o34, _a20, e + g);
            } else {
              var _o35 = e + Math.max((f - x) / 2, 0),
                _a21 = h.leftForLtr(t, m),
                _r23 = We(i.borderRadius);
              s.beginPath(), Object.values(_r23).some(function (t) {
                return 0 !== t;
              }) ? oe(s, {
                x: _a21,
                y: _o35,
                w: m,
                h: x,
                radius: _r23
              }) : s.rect(_a21, _o35, m, x), s.fill(), 0 !== n && s.stroke();
            }
            s.restore();
          }(h.x(D), C, w), D = o(S, D + m + g, _ ? D + P : _this28.right, t.rtl), function (t, e, i) {
            se(s, i.text, t, e + b / 2, c, {
              strikethrough: i.hidden,
              textAlign: h.textAlign(i.textAlign)
            });
          }(h.x(D), C, w), _ ? p.x += P + u : p.y += v;
        }), zi(this.ctx, t.textDirection);
      }
    }, {
      key: "drawTitle",
      value: function drawTitle() {
        var t = this.options,
          e = t.title,
          i = He(e.font),
          o = Ne(e.padding);
        if (!e.display) return;
        var a = Ei(t.rtl, this.left, this.width),
          r = this.ctx,
          l = e.position,
          h = i.size / 2,
          c = o.top + h;
        var d,
          u = this.left,
          f = this.width;
        if (this.isHorizontal()) f = Math.max.apply(Math, _toConsumableArray(this.lineWidths)), d = this.top + c, u = n(t.align, u, this.right - f);else {
          var _e70 = this.columnSizes.reduce(function (t, e) {
            return Math.max(t, e.height);
          }, 0);
          d = c + n(t.align, this.top, this.bottom - _e70 - t.labels.padding - this._computeTitleHeight());
        }
        var g = n(l, u, u + f);
        r.textAlign = a.textAlign(s(l)), r.textBaseline = "middle", r.strokeStyle = e.color, r.fillStyle = e.color, r.font = i.string, se(r, e.text, g, d, i);
      }
    }, {
      key: "_computeTitleHeight",
      value: function _computeTitleHeight() {
        var t = this.options.title,
          e = He(t.font),
          i = Ne(t.padding);
        return t.display ? e.lineHeight + i.height : 0;
      }
    }, {
      key: "_getLegendItemAt",
      value: function _getLegendItemAt(t, e) {
        var i, s, n;
        if (Yt(t, this.left, this.right) && Yt(e, this.top, this.bottom)) for (n = this.legendHitBoxes, i = 0; i < n.length; ++i) if (s = n[i], Yt(t, s.left, s.left + s.width) && Yt(e, s.top, s.top + s.height)) return this.legendItems[i];
        return null;
      }
    }, {
      key: "handleEvent",
      value: function handleEvent(t) {
        var e = this.options;
        if (!function (t, e) {
          if ("mousemove" === t && (e.onHover || e.onLeave)) return !0;
          if (e.onClick && ("click" === t || "mouseup" === t)) return !0;
          return !1;
        }(t.type, e)) return;
        var i = this._getLegendItemAt(t.x, t.y);
        if ("mousemove" === t.type) {
          var _o36 = this._hoveredItem,
            _a22 = (n = i, null !== (s = _o36) && null !== n && s.datasetIndex === n.datasetIndex && s.index === n.index);
          _o36 && !_a22 && J(e.onLeave, [t, _o36, this], this), this._hoveredItem = i, i && !_a22 && J(e.onHover, [t, i, this], this);
        } else i && J(e.onClick, [t, i, this], this);
        var s, n;
      }
    }]);
  }(Ds);
  var Co = {
    id: "legend",
    _element: Do,
    start: function start(t, e, i) {
      var s = t.legend = new Do({
        ctx: t.ctx,
        options: i,
        chart: t
      });
      ni.configure(t, s, i), ni.addBox(t, s);
    },
    stop: function stop(t) {
      ni.removeBox(t, t.legend), delete t.legend;
    },
    beforeUpdate: function beforeUpdate(t, e, i) {
      var s = t.legend;
      ni.configure(t, s, i), s.options = i;
    },
    afterUpdate: function afterUpdate(t) {
      var e = t.legend;
      e.buildLabels(), e.adjustHitBoxes();
    },
    afterEvent: function afterEvent(t, e) {
      e.replay || t.legend.handleEvent(e.event);
    },
    defaults: {
      display: !0,
      position: "top",
      align: "center",
      fullSize: !0,
      reverse: !1,
      weight: 1e3,
      onClick: function onClick(t, e, i) {
        var s = e.datasetIndex,
          n = i.chart;
        n.isDatasetVisible(s) ? (n.hide(s), e.hidden = !0) : (n.show(s), e.hidden = !1);
      },
      onHover: null,
      onLeave: null,
      labels: {
        color: function color(t) {
          return t.chart.options.color;
        },
        boxWidth: 40,
        padding: 10,
        generateLabels: function generateLabels(t) {
          var e = t.data.datasets,
            _t$legend$options$lab = t.legend.options.labels,
            i = _t$legend$options$lab.usePointStyle,
            s = _t$legend$options$lab.pointStyle,
            n = _t$legend$options$lab.textAlign,
            o = _t$legend$options$lab.color;
          return t._getSortedDatasetMetas().map(function (t) {
            var a = t.controller.getStyle(i ? 0 : void 0),
              r = Ne(a.borderWidth);
            return {
              text: e[t.index].label,
              fillStyle: a.backgroundColor,
              fontColor: o,
              hidden: !t.visible,
              lineCap: a.borderCapStyle,
              lineDash: a.borderDash,
              lineDashOffset: a.borderDashOffset,
              lineJoin: a.borderJoinStyle,
              lineWidth: (r.width + r.height) / 4,
              strokeStyle: a.borderColor,
              pointStyle: s || a.pointStyle,
              rotation: a.rotation,
              textAlign: n || a.textAlign,
              borderRadius: 0,
              datasetIndex: t.index
            };
          }, this);
        }
      },
      title: {
        color: function color(t) {
          return t.chart.options.color;
        },
        display: !1,
        position: "center",
        text: ""
      }
    },
    descriptors: {
      _scriptable: function _scriptable(t) {
        return !t.startsWith("on");
      },
      labels: {
        _scriptable: function _scriptable(t) {
          return !["generateLabels", "filter", "sort"].includes(t);
        }
      }
    }
  };
  var Oo = /*#__PURE__*/function (_Ds7) {
    function Oo(t) {
      var _this29;
      _classCallCheck(this, Oo);
      _this29 = _callSuper(this, Oo), _this29.chart = t.chart, _this29.options = t.options, _this29.ctx = t.ctx, _this29._padding = void 0, _this29.top = void 0, _this29.bottom = void 0, _this29.left = void 0, _this29.right = void 0, _this29.width = void 0, _this29.height = void 0, _this29.position = void 0, _this29.weight = void 0, _this29.fullSize = void 0;
      return _this29;
    }
    _inherits(Oo, _Ds7);
    return _createClass(Oo, [{
      key: "update",
      value: function update(t, e) {
        var i = this.options;
        if (this.left = 0, this.top = 0, !i.display) return void (this.width = this.height = this.right = this.bottom = 0);
        this.width = this.right = t, this.height = this.bottom = e;
        var s = Y(i.text) ? i.text.length : 1;
        this._padding = Ne(i.padding);
        var n = s * He(i.font).lineHeight + this._padding.height;
        this.isHorizontal() ? this.height = n : this.width = n;
      }
    }, {
      key: "isHorizontal",
      value: function isHorizontal() {
        var t = this.options.position;
        return "top" === t || "bottom" === t;
      }
    }, {
      key: "_drawArgs",
      value: function _drawArgs(t) {
        var e = this.top,
          i = this.left,
          s = this.bottom,
          o = this.right,
          a = this.options,
          r = a.align;
        var l,
          h,
          c,
          d = 0;
        return this.isHorizontal() ? (h = n(r, i, o), c = e + t, l = o - i) : ("left" === a.position ? (h = i + t, c = n(r, s, e), d = -.5 * _t) : (h = o - t, c = n(r, e, s), d = .5 * _t), l = s - e), {
          titleX: h,
          titleY: c,
          maxWidth: l,
          rotation: d
        };
      }
    }, {
      key: "draw",
      value: function draw() {
        var t = this.ctx,
          e = this.options;
        if (!e.display) return;
        var i = He(e.font),
          n = i.lineHeight / 2 + this._padding.top,
          _this$_drawArgs = this._drawArgs(n),
          o = _this$_drawArgs.titleX,
          a = _this$_drawArgs.titleY,
          r = _this$_drawArgs.maxWidth,
          l = _this$_drawArgs.rotation;
        se(t, e.text, 0, 0, i, {
          color: e.color,
          maxWidth: r,
          rotation: l,
          textAlign: s(e.align),
          textBaseline: "middle",
          translation: [o, a]
        });
      }
    }]);
  }(Ds);
  var Ao = {
    id: "title",
    _element: Oo,
    start: function start(t, e, i) {
      !function (t, e) {
        var i = new Oo({
          ctx: t.ctx,
          options: e,
          chart: t
        });
        ni.configure(t, i, e), ni.addBox(t, i), t.titleBlock = i;
      }(t, i);
    },
    stop: function stop(t) {
      var e = t.titleBlock;
      ni.removeBox(t, e), delete t.titleBlock;
    },
    beforeUpdate: function beforeUpdate(t, e, i) {
      var s = t.titleBlock;
      ni.configure(t, s, i), s.options = i;
    },
    defaults: {
      align: "center",
      display: !1,
      font: {
        weight: "bold"
      },
      fullSize: !0,
      padding: 10,
      position: "top",
      text: "",
      weight: 2e3
    },
    defaultRoutes: {
      color: "color"
    },
    descriptors: {
      _scriptable: !0,
      _indexable: !1
    }
  };
  var To = new WeakMap();
  var Lo = {
    id: "subtitle",
    start: function start(t, e, i) {
      var s = new Oo({
        ctx: t.ctx,
        options: i,
        chart: t
      });
      ni.configure(t, s, i), ni.addBox(t, s), To.set(t, s);
    },
    stop: function stop(t) {
      ni.removeBox(t, To.get(t)), To["delete"](t);
    },
    beforeUpdate: function beforeUpdate(t, e, i) {
      var s = To.get(t);
      ni.configure(t, s, i), s.options = i;
    },
    defaults: {
      align: "center",
      display: !1,
      font: {
        weight: "normal"
      },
      fullSize: !0,
      padding: 0,
      position: "top",
      text: "",
      weight: 1500
    },
    defaultRoutes: {
      color: "color"
    },
    descriptors: {
      _scriptable: !0,
      _indexable: !1
    }
  };
  var Ro = {
    average: function average(t) {
      if (!t.length) return !1;
      var e,
        i,
        s = 0,
        n = 0,
        o = 0;
      for (e = 0, i = t.length; e < i; ++e) {
        var _i56 = t[e].element;
        if (_i56 && _i56.hasValue()) {
          var _t65 = _i56.tooltipPosition();
          s += _t65.x, n += _t65.y, ++o;
        }
      }
      return {
        x: s / o,
        y: n / o
      };
    },
    nearest: function nearest(t, e) {
      if (!t.length) return !1;
      var i,
        s,
        n,
        o = e.x,
        a = e.y,
        r = Number.POSITIVE_INFINITY;
      for (i = 0, s = t.length; i < s; ++i) {
        var _s47 = t[i].element;
        if (_s47 && _s47.hasValue()) {
          var _t66 = Vt(e, _s47.getCenterPoint());
          _t66 < r && (r = _t66, n = _s47);
        }
      }
      if (n) {
        var _t67 = n.tooltipPosition();
        o = _t67.x, a = _t67.y;
      }
      return {
        x: o,
        y: a
      };
    }
  };
  function Eo(t, e) {
    return e && (Y(e) ? Array.prototype.push.apply(t, e) : t.push(e)), t;
  }
  function Io(t) {
    return ("string" == typeof t || t instanceof String) && t.indexOf("\n") > -1 ? t.split("\n") : t;
  }
  function zo(t, e) {
    var i = e.element,
      s = e.datasetIndex,
      n = e.index,
      o = t.getDatasetMeta(s).controller,
      _o$getLabelAndValue = o.getLabelAndValue(n),
      a = _o$getLabelAndValue.label,
      r = _o$getLabelAndValue.value;
    return {
      chart: t,
      label: a,
      parsed: o.getParsed(n),
      raw: t.data.datasets[s].data[n],
      formattedValue: r,
      dataset: o.getDataset(),
      dataIndex: n,
      datasetIndex: s,
      element: i
    };
  }
  function Fo(t, e) {
    var i = t.chart.ctx,
      s = t.body,
      n = t.footer,
      o = t.title,
      a = e.boxWidth,
      r = e.boxHeight,
      l = He(e.bodyFont),
      h = He(e.titleFont),
      c = He(e.footerFont),
      d = o.length,
      u = n.length,
      f = s.length,
      g = Ne(e.padding);
    var p = g.height,
      m = 0,
      x = s.reduce(function (t, e) {
        return t + e.before.length + e.lines.length + e.after.length;
      }, 0);
    if (x += t.beforeBody.length + t.afterBody.length, d && (p += d * h.lineHeight + (d - 1) * e.titleSpacing + e.titleMarginBottom), x) {
      p += f * (e.displayColors ? Math.max(r, l.lineHeight) : l.lineHeight) + (x - f) * l.lineHeight + (x - 1) * e.bodySpacing;
    }
    u && (p += e.footerMarginTop + u * c.lineHeight + (u - 1) * e.footerSpacing);
    var b = 0;
    var _ = function _(t) {
      m = Math.max(m, i.measureText(t).width + b);
    };
    return i.save(), i.font = h.string, Q(t.title, _), i.font = l.string, Q(t.beforeBody.concat(t.afterBody), _), b = e.displayColors ? a + 2 + e.boxPadding : 0, Q(s, function (t) {
      Q(t.before, _), Q(t.lines, _), Q(t.after, _);
    }), b = 0, i.font = c.string, Q(t.footer, _), i.restore(), m += g.width, {
      width: m,
      height: p
    };
  }
  function Bo(t, e, i, s) {
    var n = i.x,
      o = i.width,
      a = t.width,
      _t$chartArea = t.chartArea,
      r = _t$chartArea.left,
      l = _t$chartArea.right;
    var h = "center";
    return "center" === s ? h = n <= (r + l) / 2 ? "left" : "right" : n <= o / 2 ? h = "left" : n >= a - o / 2 && (h = "right"), function (t, e, i, s) {
      var n = s.x,
        o = s.width,
        a = i.caretSize + i.caretPadding;
      return "left" === t && n + o + a > e.width || "right" === t && n - o - a < 0 || void 0;
    }(h, t, e, i) && (h = "center"), h;
  }
  function Vo(t, e, i) {
    var s = i.yAlign || e.yAlign || function (t, e) {
      var i = e.y,
        s = e.height;
      return i < s / 2 ? "top" : i > t.height - s / 2 ? "bottom" : "center";
    }(t, i);
    return {
      xAlign: i.xAlign || e.xAlign || Bo(t, e, i, s),
      yAlign: s
    };
  }
  function Wo(t, e, i, s) {
    var n = t.caretSize,
      o = t.caretPadding,
      a = t.cornerRadius,
      r = i.xAlign,
      l = i.yAlign,
      h = n + o,
      _We = We(a),
      c = _We.topLeft,
      d = _We.topRight,
      u = _We.bottomLeft,
      f = _We.bottomRight;
    var g = function (t, e) {
      var i = t.x,
        s = t.width;
      return "right" === e ? i -= s : "center" === e && (i -= s / 2), i;
    }(e, r);
    var p = function (t, e, i) {
      var s = t.y,
        n = t.height;
      return "top" === e ? s += i : s -= "bottom" === e ? n + i : n / 2, s;
    }(e, l, h);
    return "center" === l ? "left" === r ? g += h : "right" === r && (g -= h) : "left" === r ? g -= Math.max(c, u) + n : "right" === r && (g += Math.max(d, f) + n), {
      x: jt(g, 0, s.width - e.width),
      y: jt(p, 0, s.height - e.height)
    };
  }
  function No(t, e, i) {
    var s = Ne(i.padding);
    return "center" === e ? t.x + t.width / 2 : "right" === e ? t.x + t.width - s.right : t.x + s.left;
  }
  function Ho(t) {
    return Eo([], Io(t));
  }
  function jo(t, e) {
    var i = e && e.dataset && e.dataset.tooltip && e.dataset.tooltip.callbacks;
    return i ? t.override(i) : t;
  }
  var $o = /*#__PURE__*/function (_Ds8) {
    function $o(t) {
      var _this30;
      _classCallCheck(this, $o);
      _this30 = _callSuper(this, $o), _this30.opacity = 0, _this30._active = [], _this30._eventPosition = void 0, _this30._size = void 0, _this30._cachedAnimations = void 0, _this30._tooltipItems = [], _this30.$animations = void 0, _this30.$context = void 0, _this30.chart = t.chart || t._chart, _this30._chart = _this30.chart, _this30.options = t.options, _this30.dataPoints = void 0, _this30.title = void 0, _this30.beforeBody = void 0, _this30.body = void 0, _this30.afterBody = void 0, _this30.footer = void 0, _this30.xAlign = void 0, _this30.yAlign = void 0, _this30.x = void 0, _this30.y = void 0, _this30.height = void 0, _this30.width = void 0, _this30.caretX = void 0, _this30.caretY = void 0, _this30.labelColors = void 0, _this30.labelPointStyles = void 0, _this30.labelTextColors = void 0;
      return _this30;
    }
    _inherits($o, _Ds8);
    return _createClass($o, [{
      key: "initialize",
      value: function initialize(t) {
        this.options = t, this._cachedAnimations = void 0, this.$context = void 0;
      }
    }, {
      key: "_resolveAnimations",
      value: function _resolveAnimations() {
        var t = this._cachedAnimations;
        if (t) return t;
        var e = this.chart,
          i = this.options.setContext(this.getContext()),
          s = i.enabled && e.options.animation && i.animations,
          n = new gs(this.chart, s);
        return s._cacheable && (this._cachedAnimations = Object.freeze(n)), n;
      }
    }, {
      key: "getContext",
      value: function getContext() {
        return this.$context || (this.$context = (t = this.chart.getContext(), e = this, i = this._tooltipItems, Ye(t, {
          tooltip: e,
          tooltipItems: i,
          type: "tooltip"
        })));
        var t, e, i;
      }
    }, {
      key: "getTitle",
      value: function getTitle(t, e) {
        var i = e.callbacks,
          s = i.beforeTitle.apply(this, [t]),
          n = i.title.apply(this, [t]),
          o = i.afterTitle.apply(this, [t]);
        var a = [];
        return a = Eo(a, Io(s)), a = Eo(a, Io(n)), a = Eo(a, Io(o)), a;
      }
    }, {
      key: "getBeforeBody",
      value: function getBeforeBody(t, e) {
        return Ho(e.callbacks.beforeBody.apply(this, [t]));
      }
    }, {
      key: "getBody",
      value: function getBody(t, e) {
        var _this31 = this;
        var i = e.callbacks,
          s = [];
        return Q(t, function (t) {
          var e = {
              before: [],
              lines: [],
              after: []
            },
            n = jo(i, t);
          Eo(e.before, Io(n.beforeLabel.call(_this31, t))), Eo(e.lines, n.label.call(_this31, t)), Eo(e.after, Io(n.afterLabel.call(_this31, t))), s.push(e);
        }), s;
      }
    }, {
      key: "getAfterBody",
      value: function getAfterBody(t, e) {
        return Ho(e.callbacks.afterBody.apply(this, [t]));
      }
    }, {
      key: "getFooter",
      value: function getFooter(t, e) {
        var i = e.callbacks,
          s = i.beforeFooter.apply(this, [t]),
          n = i.footer.apply(this, [t]),
          o = i.afterFooter.apply(this, [t]);
        var a = [];
        return a = Eo(a, Io(s)), a = Eo(a, Io(n)), a = Eo(a, Io(o)), a;
      }
    }, {
      key: "_createItems",
      value: function _createItems(t) {
        var _this32 = this;
        var e = this._active,
          i = this.chart.data,
          s = [],
          n = [],
          o = [];
        var a,
          r,
          l = [];
        for (a = 0, r = e.length; a < r; ++a) l.push(zo(this.chart, e[a]));
        return t.filter && (l = l.filter(function (e, s, n) {
          return t.filter(e, s, n, i);
        })), t.itemSort && (l = l.sort(function (e, s) {
          return t.itemSort(e, s, i);
        })), Q(l, function (e) {
          var i = jo(t.callbacks, e);
          s.push(i.labelColor.call(_this32, e)), n.push(i.labelPointStyle.call(_this32, e)), o.push(i.labelTextColor.call(_this32, e));
        }), this.labelColors = s, this.labelPointStyles = n, this.labelTextColors = o, this.dataPoints = l, l;
      }
    }, {
      key: "update",
      value: function update(t, e) {
        var i = this.options.setContext(this.getContext()),
          s = this._active;
        var n,
          o = [];
        if (s.length) {
          var _t68 = Ro[i.position].call(this, s, this._eventPosition);
          o = this._createItems(i), this.title = this.getTitle(o, i), this.beforeBody = this.getBeforeBody(o, i), this.body = this.getBody(o, i), this.afterBody = this.getAfterBody(o, i), this.footer = this.getFooter(o, i);
          var _e71 = this._size = Fo(this, i),
            _a23 = Object.assign({}, _t68, _e71),
            _r24 = Vo(this.chart, i, _a23),
            _l24 = Wo(i, _a23, _r24, this.chart);
          this.xAlign = _r24.xAlign, this.yAlign = _r24.yAlign, n = {
            opacity: 1,
            x: _l24.x,
            y: _l24.y,
            width: _e71.width,
            height: _e71.height,
            caretX: _t68.x,
            caretY: _t68.y
          };
        } else 0 !== this.opacity && (n = {
          opacity: 0
        });
        this._tooltipItems = o, this.$context = void 0, n && this._resolveAnimations().update(this, n), t && i.external && i.external.call(this, {
          chart: this.chart,
          tooltip: this,
          replay: e
        });
      }
    }, {
      key: "drawCaret",
      value: function drawCaret(t, e, i, s) {
        var n = this.getCaretPosition(t, i, s);
        e.lineTo(n.x1, n.y1), e.lineTo(n.x2, n.y2), e.lineTo(n.x3, n.y3);
      }
    }, {
      key: "getCaretPosition",
      value: function getCaretPosition(t, e, i) {
        var s = this.xAlign,
          n = this.yAlign,
          o = i.caretSize,
          a = i.cornerRadius,
          _We2 = We(a),
          r = _We2.topLeft,
          l = _We2.topRight,
          h = _We2.bottomLeft,
          c = _We2.bottomRight,
          d = t.x,
          u = t.y,
          f = e.width,
          g = e.height;
        var p, m, x, b, _, y;
        return "center" === n ? (_ = u + g / 2, "left" === s ? (p = d, m = p - o, b = _ + o, y = _ - o) : (p = d + f, m = p + o, b = _ - o, y = _ + o), x = p) : (m = "left" === s ? d + Math.max(r, h) + o : "right" === s ? d + f - Math.max(l, c) - o : this.caretX, "top" === n ? (b = u, _ = b - o, p = m - o, x = m + o) : (b = u + g, _ = b + o, p = m + o, x = m - o), y = b), {
          x1: p,
          x2: m,
          x3: x,
          y1: b,
          y2: _,
          y3: y
        };
      }
    }, {
      key: "drawTitle",
      value: function drawTitle(t, e, i) {
        var s = this.title,
          n = s.length;
        var o, a, r;
        if (n) {
          var _l25 = Ei(i.rtl, this.x, this.width);
          for (t.x = No(this, i.titleAlign, i), e.textAlign = _l25.textAlign(i.titleAlign), e.textBaseline = "middle", o = He(i.titleFont), a = i.titleSpacing, e.fillStyle = i.titleColor, e.font = o.string, r = 0; r < n; ++r) e.fillText(s[r], _l25.x(t.x), t.y + o.lineHeight / 2), t.y += o.lineHeight + a, r + 1 === n && (t.y += i.titleMarginBottom - a);
        }
      }
    }, {
      key: "_drawColorBox",
      value: function _drawColorBox(t, e, i, s, n) {
        var o = this.labelColors[i],
          a = this.labelPointStyles[i],
          r = n.boxHeight,
          l = n.boxWidth,
          h = n.boxPadding,
          c = He(n.bodyFont),
          d = No(this, "left", n),
          u = s.x(d),
          f = r < c.lineHeight ? (c.lineHeight - r) / 2 : 0,
          g = e.y + f;
        if (n.usePointStyle) {
          var _e72 = {
              radius: Math.min(l, r) / 2,
              pointStyle: a.pointStyle,
              rotation: a.rotation,
              borderWidth: 1
            },
            _i57 = s.leftForLtr(u, l) + l / 2,
            _h23 = g + r / 2;
          t.strokeStyle = n.multiKeyBackground, t.fillStyle = n.multiKeyBackground, Zt(t, _e72, _i57, _h23), t.strokeStyle = o.borderColor, t.fillStyle = o.backgroundColor, Zt(t, _e72, _i57, _h23);
        } else {
          t.lineWidth = o.borderWidth || 1, t.strokeStyle = o.borderColor, t.setLineDash(o.borderDash || []), t.lineDashOffset = o.borderDashOffset || 0;
          var _e73 = s.leftForLtr(u, l - h),
            _i58 = s.leftForLtr(s.xPlus(u, 1), l - h - 2),
            _a24 = We(o.borderRadius);
          Object.values(_a24).some(function (t) {
            return 0 !== t;
          }) ? (t.beginPath(), t.fillStyle = n.multiKeyBackground, oe(t, {
            x: _e73,
            y: g,
            w: l,
            h: r,
            radius: _a24
          }), t.fill(), t.stroke(), t.fillStyle = o.backgroundColor, t.beginPath(), oe(t, {
            x: _i58,
            y: g + 1,
            w: l - 2,
            h: r - 2,
            radius: _a24
          }), t.fill()) : (t.fillStyle = n.multiKeyBackground, t.fillRect(_e73, g, l, r), t.strokeRect(_e73, g, l, r), t.fillStyle = o.backgroundColor, t.fillRect(_i58, g + 1, l - 2, r - 2));
        }
        t.fillStyle = this.labelTextColors[i];
      }
    }, {
      key: "drawBody",
      value: function drawBody(t, e, i) {
        var s = this.body,
          n = i.bodySpacing,
          o = i.bodyAlign,
          a = i.displayColors,
          r = i.boxHeight,
          l = i.boxWidth,
          h = i.boxPadding,
          c = He(i.bodyFont);
        var d = c.lineHeight,
          u = 0;
        var f = Ei(i.rtl, this.x, this.width),
          g = function g(i) {
            e.fillText(i, f.x(t.x + u), t.y + d / 2), t.y += d + n;
          },
          p = f.textAlign(o);
        var m, x, b, _, y, v, w;
        for (e.textAlign = o, e.textBaseline = "middle", e.font = c.string, t.x = No(this, p, i), e.fillStyle = i.bodyColor, Q(this.beforeBody, g), u = a && "right" !== p ? "center" === o ? l / 2 + h : l + 2 + h : 0, _ = 0, v = s.length; _ < v; ++_) {
          for (m = s[_], x = this.labelTextColors[_], e.fillStyle = x, Q(m.before, g), b = m.lines, a && b.length && (this._drawColorBox(e, t, _, f, i), d = Math.max(c.lineHeight, r)), y = 0, w = b.length; y < w; ++y) g(b[y]), d = c.lineHeight;
          Q(m.after, g);
        }
        u = 0, d = c.lineHeight, Q(this.afterBody, g), t.y -= n;
      }
    }, {
      key: "drawFooter",
      value: function drawFooter(t, e, i) {
        var s = this.footer,
          n = s.length;
        var o, a;
        if (n) {
          var _r25 = Ei(i.rtl, this.x, this.width);
          for (t.x = No(this, i.footerAlign, i), t.y += i.footerMarginTop, e.textAlign = _r25.textAlign(i.footerAlign), e.textBaseline = "middle", o = He(i.footerFont), e.fillStyle = i.footerColor, e.font = o.string, a = 0; a < n; ++a) e.fillText(s[a], _r25.x(t.x), t.y + o.lineHeight / 2), t.y += o.lineHeight + i.footerSpacing;
        }
      }
    }, {
      key: "drawBackground",
      value: function drawBackground(t, e, i, s) {
        var n = this.xAlign,
          o = this.yAlign,
          a = t.x,
          r = t.y,
          l = i.width,
          h = i.height,
          _We3 = We(s.cornerRadius),
          c = _We3.topLeft,
          d = _We3.topRight,
          u = _We3.bottomLeft,
          f = _We3.bottomRight;
        e.fillStyle = s.backgroundColor, e.strokeStyle = s.borderColor, e.lineWidth = s.borderWidth, e.beginPath(), e.moveTo(a + c, r), "top" === o && this.drawCaret(t, e, i, s), e.lineTo(a + l - d, r), e.quadraticCurveTo(a + l, r, a + l, r + d), "center" === o && "right" === n && this.drawCaret(t, e, i, s), e.lineTo(a + l, r + h - f), e.quadraticCurveTo(a + l, r + h, a + l - f, r + h), "bottom" === o && this.drawCaret(t, e, i, s), e.lineTo(a + u, r + h), e.quadraticCurveTo(a, r + h, a, r + h - u), "center" === o && "left" === n && this.drawCaret(t, e, i, s), e.lineTo(a, r + c), e.quadraticCurveTo(a, r, a + c, r), e.closePath(), e.fill(), s.borderWidth > 0 && e.stroke();
      }
    }, {
      key: "_updateAnimationTarget",
      value: function _updateAnimationTarget(t) {
        var e = this.chart,
          i = this.$animations,
          s = i && i.x,
          n = i && i.y;
        if (s || n) {
          var _i59 = Ro[t.position].call(this, this._active, this._eventPosition);
          if (!_i59) return;
          var _o37 = this._size = Fo(this, t),
            _a25 = Object.assign({}, _i59, this._size),
            _r26 = Vo(e, t, _a25),
            _l26 = Wo(t, _a25, _r26, e);
          s._to === _l26.x && n._to === _l26.y || (this.xAlign = _r26.xAlign, this.yAlign = _r26.yAlign, this.width = _o37.width, this.height = _o37.height, this.caretX = _i59.x, this.caretY = _i59.y, this._resolveAnimations().update(this, _l26));
        }
      }
    }, {
      key: "draw",
      value: function draw(t) {
        var e = this.options.setContext(this.getContext());
        var i = this.opacity;
        if (!i) return;
        this._updateAnimationTarget(e);
        var s = {
            width: this.width,
            height: this.height
          },
          n = {
            x: this.x,
            y: this.y
          };
        i = Math.abs(i) < .001 ? 0 : i;
        var o = Ne(e.padding),
          a = this.title.length || this.beforeBody.length || this.body.length || this.afterBody.length || this.footer.length;
        e.enabled && a && (t.save(), t.globalAlpha = i, this.drawBackground(n, t, s, e), Ii(t, e.textDirection), n.y += o.top, this.drawTitle(n, t, e), this.drawBody(n, t, e), this.drawFooter(n, t, e), zi(t, e.textDirection), t.restore());
      }
    }, {
      key: "getActiveElements",
      value: function getActiveElements() {
        return this._active || [];
      }
    }, {
      key: "setActiveElements",
      value: function setActiveElements(t, e) {
        var _this33 = this;
        var i = this._active,
          s = t.map(function (_ref19) {
            var t = _ref19.datasetIndex,
              e = _ref19.index;
            var i = _this33.chart.getDatasetMeta(t);
            if (!i) throw new Error("Cannot find a dataset at index " + t);
            return {
              datasetIndex: t,
              element: i.data[e],
              index: e
            };
          }),
          n = !tt(i, s),
          o = this._positionChanged(s, e);
        (n || o) && (this._active = s, this._eventPosition = e, this._ignoreReplayEvents = !0, this.update(!0));
      }
    }, {
      key: "handleEvent",
      value: function handleEvent(t, e) {
        var i = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : !0;
        if (e && this._ignoreReplayEvents) return !1;
        this._ignoreReplayEvents = !1;
        var s = this.options,
          n = this._active || [],
          o = this._getActiveElements(t, n, e, i),
          a = this._positionChanged(o, t),
          r = e || !tt(o, n) || a;
        return r && (this._active = o, (s.enabled || s.external) && (this._eventPosition = {
          x: t.x,
          y: t.y
        }, this.update(!0, e))), r;
      }
    }, {
      key: "_getActiveElements",
      value: function _getActiveElements(t, e, i, s) {
        var n = this.options;
        if ("mouseout" === t.type) return [];
        if (!s) return e;
        var o = this.chart.getElementsAtEventForMode(t, n.mode, n, i);
        return n.reverse && o.reverse(), o;
      }
    }, {
      key: "_positionChanged",
      value: function _positionChanged(t, e) {
        var i = this.caretX,
          s = this.caretY,
          n = this.options,
          o = Ro[n.position].call(this, t, e);
        return !1 !== o && (i !== o.x || s !== o.y);
      }
    }]);
  }(Ds);
  $o.positioners = Ro;
  var Yo = {
      id: "tooltip",
      _element: $o,
      positioners: Ro,
      afterInit: function afterInit(t, e, i) {
        i && (t.tooltip = new $o({
          chart: t,
          options: i
        }));
      },
      beforeUpdate: function beforeUpdate(t, e, i) {
        t.tooltip && t.tooltip.initialize(i);
      },
      reset: function reset(t, e, i) {
        t.tooltip && t.tooltip.initialize(i);
      },
      afterDraw: function afterDraw(t) {
        var e = t.tooltip,
          i = {
            tooltip: e
          };
        !1 !== t.notifyPlugins("beforeTooltipDraw", i) && (e && e.draw(t.ctx), t.notifyPlugins("afterTooltipDraw", i));
      },
      afterEvent: function afterEvent(t, e) {
        if (t.tooltip) {
          var _i60 = e.replay;
          t.tooltip.handleEvent(e.event, _i60, e.inChartArea) && (e.changed = !0);
        }
      },
      defaults: {
        enabled: !0,
        external: null,
        position: "average",
        backgroundColor: "rgba(0,0,0,0.8)",
        titleColor: "#fff",
        titleFont: {
          weight: "bold"
        },
        titleSpacing: 2,
        titleMarginBottom: 6,
        titleAlign: "left",
        bodyColor: "#fff",
        bodySpacing: 2,
        bodyFont: {},
        bodyAlign: "left",
        footerColor: "#fff",
        footerSpacing: 2,
        footerMarginTop: 6,
        footerFont: {
          weight: "bold"
        },
        footerAlign: "left",
        padding: 6,
        caretPadding: 2,
        caretSize: 5,
        cornerRadius: 6,
        boxHeight: function boxHeight(t, e) {
          return e.bodyFont.size;
        },
        boxWidth: function boxWidth(t, e) {
          return e.bodyFont.size;
        },
        multiKeyBackground: "#fff",
        displayColors: !0,
        boxPadding: 0,
        borderColor: "rgba(0,0,0,0)",
        borderWidth: 0,
        animation: {
          duration: 400,
          easing: "easeOutQuart"
        },
        animations: {
          numbers: {
            type: "number",
            properties: ["x", "y", "width", "height", "caretX", "caretY"]
          },
          opacity: {
            easing: "linear",
            duration: 200
          }
        },
        callbacks: {
          beforeTitle: H,
          title: function title(t) {
            if (t.length > 0) {
              var _e74 = t[0],
                _i61 = _e74.chart.data.labels,
                _s48 = _i61 ? _i61.length : 0;
              if (this && this.options && "dataset" === this.options.mode) return _e74.dataset.label || "";
              if (_e74.label) return _e74.label;
              if (_s48 > 0 && _e74.dataIndex < _s48) return _i61[_e74.dataIndex];
            }
            return "";
          },
          afterTitle: H,
          beforeBody: H,
          beforeLabel: H,
          label: function label(t) {
            if (this && this.options && "dataset" === this.options.mode) return t.label + ": " + t.formattedValue || t.formattedValue;
            var e = t.dataset.label || "";
            e && (e += ": ");
            var i = t.formattedValue;
            return $(i) || (e += i), e;
          },
          labelColor: function labelColor(t) {
            var e = t.chart.getDatasetMeta(t.datasetIndex).controller.getStyle(t.dataIndex);
            return {
              borderColor: e.borderColor,
              backgroundColor: e.backgroundColor,
              borderWidth: e.borderWidth,
              borderDash: e.borderDash,
              borderDashOffset: e.borderDashOffset,
              borderRadius: 0
            };
          },
          labelTextColor: function labelTextColor() {
            return this.options.bodyColor;
          },
          labelPointStyle: function labelPointStyle(t) {
            var e = t.chart.getDatasetMeta(t.datasetIndex).controller.getStyle(t.dataIndex);
            return {
              pointStyle: e.pointStyle,
              rotation: e.rotation
            };
          },
          afterLabel: H,
          afterBody: H,
          beforeFooter: H,
          footer: H,
          afterFooter: H
        }
      },
      defaultRoutes: {
        bodyFont: "font",
        footerFont: "font",
        titleFont: "font"
      },
      descriptors: {
        _scriptable: function _scriptable(t) {
          return "filter" !== t && "itemSort" !== t && "external" !== t;
        },
        _indexable: !1,
        callbacks: {
          _scriptable: !1,
          _indexable: !1
        },
        animation: {
          _fallback: !1
        },
        animations: {
          _fallback: "animation"
        }
      },
      additionalOptionScopes: ["interaction"]
    },
    Uo = Object.freeze({
      __proto__: null,
      Decimation: ro,
      Filler: So,
      Legend: Co,
      SubTitle: Lo,
      Title: Ao,
      Tooltip: Yo
    });
  function Xo(t, e, i, s) {
    var n = t.indexOf(e);
    if (-1 === n) return function (t, e, i, s) {
      return "string" == typeof e ? (i = t.push(e) - 1, s.unshift({
        index: i,
        label: e
      })) : isNaN(e) && (i = null), i;
    }(t, e, i, s);
    return n !== t.lastIndexOf(e) ? i : n;
  }
  var qo = /*#__PURE__*/function (_Bs) {
    function qo(t) {
      var _this34;
      _classCallCheck(this, qo);
      _this34 = _callSuper(this, qo, [t]), _this34._startValue = void 0, _this34._valueRange = 0, _this34._addedLabels = [];
      return _this34;
    }
    _inherits(qo, _Bs);
    return _createClass(qo, [{
      key: "init",
      value: function init(t) {
        var e = this._addedLabels;
        if (e.length) {
          var _t69 = this.getLabels();
          var _iterator31 = _createForOfIteratorHelper(e),
            _step31;
          try {
            for (_iterator31.s(); !(_step31 = _iterator31.n()).done;) {
              var _step31$value = _step31.value,
                _i62 = _step31$value.index,
                _s49 = _step31$value.label;
              _t69[_i62] === _s49 && _t69.splice(_i62, 1);
            }
          } catch (err) {
            _iterator31.e(err);
          } finally {
            _iterator31.f();
          }
          this._addedLabels = [];
        }
        _get2(_getPrototypeOf(qo.prototype), "init", this).call(this, t);
      }
    }, {
      key: "parse",
      value: function parse(t, e) {
        if ($(t)) return null;
        var i = this.getLabels();
        return function (t, e) {
          return null === t ? null : jt(Math.round(t), 0, e);
        }(e = isFinite(e) && i[e] === t ? e : Xo(i, t, K(e, t), this._addedLabels), i.length - 1);
      }
    }, {
      key: "determineDataLimits",
      value: function determineDataLimits() {
        var _this$getUserBounds2 = this.getUserBounds(),
          t = _this$getUserBounds2.minDefined,
          e = _this$getUserBounds2.maxDefined;
        var _this$getMinMax = this.getMinMax(!0),
          i = _this$getMinMax.min,
          s = _this$getMinMax.max;
        "ticks" === this.options.bounds && (t || (i = 0), e || (s = this.getLabels().length - 1)), this.min = i, this.max = s;
      }
    }, {
      key: "buildTicks",
      value: function buildTicks() {
        var t = this.min,
          e = this.max,
          i = this.options.offset,
          s = [];
        var n = this.getLabels();
        n = 0 === t && e === n.length - 1 ? n : n.slice(t, e + 1), this._valueRange = Math.max(n.length - (i ? 0 : 1), 1), this._startValue = this.min - (i ? .5 : 0);
        for (var _i63 = t; _i63 <= e; _i63++) s.push({
          value: _i63
        });
        return s;
      }
    }, {
      key: "getLabelForValue",
      value: function getLabelForValue(t) {
        var e = this.getLabels();
        return t >= 0 && t < e.length ? e[t] : t;
      }
    }, {
      key: "configure",
      value: function configure() {
        _get2(_getPrototypeOf(qo.prototype), "configure", this).call(this), this.isHorizontal() || (this._reversePixels = !this._reversePixels);
      }
    }, {
      key: "getPixelForValue",
      value: function getPixelForValue(t) {
        return "number" != typeof t && (t = this.parse(t)), null === t ? NaN : this.getPixelForDecimal((t - this._startValue) / this._valueRange);
      }
    }, {
      key: "getPixelForTick",
      value: function getPixelForTick(t) {
        var e = this.ticks;
        return t < 0 || t > e.length - 1 ? null : this.getPixelForValue(e[t].value);
      }
    }, {
      key: "getValueForPixel",
      value: function getValueForPixel(t) {
        return Math.round(this._startValue + this.getDecimalForPixel(t) * this._valueRange);
      }
    }, {
      key: "getBasePixel",
      value: function getBasePixel() {
        return this.bottom;
      }
    }]);
  }(Bs);
  function Ko(t, e, _ref20) {
    var i = _ref20.horizontal,
      s = _ref20.minRotation;
    var n = It(s),
      o = (i ? Math.sin(n) : Math.cos(n)) || .001,
      a = .75 * e * ("" + t).length;
    return Math.min(e / o, a);
  }
  qo.id = "category", qo.defaults = {
    ticks: {
      callback: qo.prototype.getLabelForValue
    }
  };
  var Go = /*#__PURE__*/function (_Bs2) {
    function Go(t) {
      var _this35;
      _classCallCheck(this, Go);
      _this35 = _callSuper(this, Go, [t]), _this35.start = void 0, _this35.end = void 0, _this35._startValue = void 0, _this35._endValue = void 0, _this35._valueRange = 0;
      return _this35;
    }
    _inherits(Go, _Bs2);
    return _createClass(Go, [{
      key: "parse",
      value: function parse(t, e) {
        return $(t) || ("number" == typeof t || t instanceof Number) && !isFinite(+t) ? null : +t;
      }
    }, {
      key: "handleTickRangeOptions",
      value: function handleTickRangeOptions() {
        var t = this.options.beginAtZero,
          _this$getUserBounds3 = this.getUserBounds(),
          e = _this$getUserBounds3.minDefined,
          i = _this$getUserBounds3.maxDefined;
        var s = this.min,
          n = this.max;
        var o = function o(t) {
            return s = e ? s : t;
          },
          a = function a(t) {
            return n = i ? n : t;
          };
        if (t) {
          var _t70 = Ct(s),
            _e75 = Ct(n);
          _t70 < 0 && _e75 < 0 ? a(0) : _t70 > 0 && _e75 > 0 && o(0);
        }
        if (s === n) {
          var _e76 = 1;
          (n >= Number.MAX_SAFE_INTEGER || s <= Number.MIN_SAFE_INTEGER) && (_e76 = Math.abs(.05 * n)), a(n + _e76), t || o(s - _e76);
        }
        this.min = s, this.max = n;
      }
    }, {
      key: "getTickLimit",
      value: function getTickLimit() {
        var t = this.options.ticks;
        var e,
          i = t.maxTicksLimit,
          s = t.stepSize;
        return s ? (e = Math.ceil(this.max / s) - Math.floor(this.min / s) + 1, e > 1e3 && (console.warn("scales.".concat(this.id, ".ticks.stepSize: ").concat(s, " would result generating up to ").concat(e, " ticks. Limiting to 1000.")), e = 1e3)) : (e = this.computeTickLimit(), i = i || 11), i && (e = Math.min(i, e)), e;
      }
    }, {
      key: "computeTickLimit",
      value: function computeTickLimit() {
        return Number.POSITIVE_INFINITY;
      }
    }, {
      key: "buildTicks",
      value: function buildTicks() {
        var t = this.options,
          e = t.ticks;
        var i = this.getTickLimit();
        i = Math.max(2, i);
        var s = function (t, e) {
          var i = [],
            s = t.bounds,
            n = t.step,
            o = t.min,
            a = t.max,
            r = t.precision,
            l = t.count,
            h = t.maxTicks,
            c = t.maxDigits,
            d = t.includeBounds,
            u = n || 1,
            f = h - 1,
            g = e.min,
            p = e.max,
            m = !$(o),
            x = !$(a),
            b = !$(l),
            _ = (p - g) / (c + 1);
          var y,
            v,
            w,
            M,
            k = Ot((p - g) / f / u) * u;
          if (k < 1e-14 && !m && !x) return [{
            value: g
          }, {
            value: p
          }];
          M = Math.ceil(p / k) - Math.floor(g / k), M > f && (k = Ot(M * k / f / u) * u), $(r) || (y = Math.pow(10, r), k = Math.ceil(k * y) / y), "ticks" === s ? (v = Math.floor(g / k) * k, w = Math.ceil(p / k) * k) : (v = g, w = p), m && x && n && Rt((a - o) / n, k / 1e3) ? (M = Math.round(Math.min((a - o) / k, h)), k = (a - o) / M, v = o, w = a) : b ? (v = m ? o : v, w = x ? a : w, M = l - 1, k = (w - v) / M) : (M = (w - v) / k, M = Lt(M, Math.round(M), k / 1e3) ? Math.round(M) : Math.ceil(M));
          var S = Math.max(Ft(k), Ft(v));
          y = Math.pow(10, $(r) ? S : r), v = Math.round(v * y) / y, w = Math.round(w * y) / y;
          var P = 0;
          for (m && (d && v !== o ? (i.push({
            value: o
          }), v < o && P++, Lt(Math.round((v + P * k) * y) / y, o, Ko(o, _, t)) && P++) : v < o && P++); P < M; ++P) i.push({
            value: Math.round((v + P * k) * y) / y
          });
          return x && d && w !== a ? i.length && Lt(i[i.length - 1].value, a, Ko(a, _, t)) ? i[i.length - 1].value = a : i.push({
            value: a
          }) : x && w !== a || i.push({
            value: w
          }), i;
        }({
          maxTicks: i,
          bounds: t.bounds,
          min: t.min,
          max: t.max,
          precision: e.precision,
          step: e.stepSize,
          count: e.count,
          maxDigits: this._maxDigits(),
          horizontal: this.isHorizontal(),
          minRotation: e.minRotation || 0,
          includeBounds: !1 !== e.includeBounds
        }, this._range || this);
        return "ticks" === t.bounds && Et(s, this, "value"), t.reverse ? (s.reverse(), this.start = this.max, this.end = this.min) : (this.start = this.min, this.end = this.max), s;
      }
    }, {
      key: "configure",
      value: function configure() {
        var t = this.ticks;
        var e = this.min,
          i = this.max;
        if (_get2(_getPrototypeOf(Go.prototype), "configure", this).call(this), this.options.offset && t.length) {
          var _s50 = (i - e) / Math.max(t.length - 1, 1) / 2;
          e -= _s50, i += _s50;
        }
        this._startValue = e, this._endValue = i, this._valueRange = i - e;
      }
    }, {
      key: "getLabelForValue",
      value: function getLabelForValue(t) {
        return Ri(t, this.chart.options.locale, this.options.ticks.format);
      }
    }]);
  }(Bs);
  var Zo = /*#__PURE__*/function (_Go) {
    function Zo() {
      _classCallCheck(this, Zo);
      return _callSuper(this, Zo, arguments);
    }
    _inherits(Zo, _Go);
    return _createClass(Zo, [{
      key: "determineDataLimits",
      value: function determineDataLimits() {
        var _this$getMinMax2 = this.getMinMax(!0),
          t = _this$getMinMax2.min,
          e = _this$getMinMax2.max;
        this.min = X(t) ? t : 0, this.max = X(e) ? e : 1, this.handleTickRangeOptions();
      }
    }, {
      key: "computeTickLimit",
      value: function computeTickLimit() {
        var t = this.isHorizontal(),
          e = t ? this.width : this.height,
          i = It(this.options.ticks.minRotation),
          s = (t ? Math.sin(i) : Math.cos(i)) || .001,
          n = this._resolveTickFontOptions(0);
        return Math.ceil(e / Math.min(40, n.lineHeight / s));
      }
    }, {
      key: "getPixelForValue",
      value: function getPixelForValue(t) {
        return null === t ? NaN : this.getPixelForDecimal((t - this._startValue) / this._valueRange);
      }
    }, {
      key: "getValueForPixel",
      value: function getValueForPixel(t) {
        return this._startValue + this.getDecimalForPixel(t) * this._valueRange;
      }
    }]);
  }(Go);
  function Jo(t) {
    return 1 === t / Math.pow(10, Math.floor(Dt(t)));
  }
  Zo.id = "linear", Zo.defaults = {
    ticks: {
      callback: Os.formatters.numeric
    }
  };
  var Qo = /*#__PURE__*/function (_Bs3) {
    function Qo(t) {
      var _this36;
      _classCallCheck(this, Qo);
      _this36 = _callSuper(this, Qo, [t]), _this36.start = void 0, _this36.end = void 0, _this36._startValue = void 0, _this36._valueRange = 0;
      return _this36;
    }
    _inherits(Qo, _Bs3);
    return _createClass(Qo, [{
      key: "parse",
      value: function parse(t, e) {
        var i = Go.prototype.parse.apply(this, [t, e]);
        if (0 !== i) return X(i) && i > 0 ? i : null;
        this._zero = !0;
      }
    }, {
      key: "determineDataLimits",
      value: function determineDataLimits() {
        var _this$getMinMax3 = this.getMinMax(!0),
          t = _this$getMinMax3.min,
          e = _this$getMinMax3.max;
        this.min = X(t) ? Math.max(0, t) : null, this.max = X(e) ? Math.max(0, e) : null, this.options.beginAtZero && (this._zero = !0), this.handleTickRangeOptions();
      }
    }, {
      key: "handleTickRangeOptions",
      value: function handleTickRangeOptions() {
        var _this$getUserBounds4 = this.getUserBounds(),
          t = _this$getUserBounds4.minDefined,
          e = _this$getUserBounds4.maxDefined;
        var i = this.min,
          s = this.max;
        var n = function n(e) {
            return i = t ? i : e;
          },
          o = function o(t) {
            return s = e ? s : t;
          },
          a = function a(t, e) {
            return Math.pow(10, Math.floor(Dt(t)) + e);
          };
        i === s && (i <= 0 ? (n(1), o(10)) : (n(a(i, -1)), o(a(s, 1)))), i <= 0 && n(a(s, -1)), s <= 0 && o(a(i, 1)), this._zero && this.min !== this._suggestedMin && i === a(this.min, 0) && n(a(i, -1)), this.min = i, this.max = s;
      }
    }, {
      key: "buildTicks",
      value: function buildTicks() {
        var t = this.options,
          e = function (t, e) {
            var i = Math.floor(Dt(e.max)),
              s = Math.ceil(e.max / Math.pow(10, i)),
              n = [];
            var o = q(t.min, Math.pow(10, Math.floor(Dt(e.min)))),
              a = Math.floor(Dt(o)),
              r = Math.floor(o / Math.pow(10, a)),
              l = a < 0 ? Math.pow(10, Math.abs(a)) : 1;
            do {
              n.push({
                value: o,
                major: Jo(o)
              }), ++r, 10 === r && (r = 1, ++a, l = a >= 0 ? 1 : l), o = Math.round(r * Math.pow(10, a) * l) / l;
            } while (a < i || a === i && r < s);
            var h = q(t.max, o);
            return n.push({
              value: h,
              major: Jo(o)
            }), n;
          }({
            min: this._userMin,
            max: this._userMax
          }, this);
        return "ticks" === t.bounds && Et(e, this, "value"), t.reverse ? (e.reverse(), this.start = this.max, this.end = this.min) : (this.start = this.min, this.end = this.max), e;
      }
    }, {
      key: "getLabelForValue",
      value: function getLabelForValue(t) {
        return void 0 === t ? "0" : Ri(t, this.chart.options.locale, this.options.ticks.format);
      }
    }, {
      key: "configure",
      value: function configure() {
        var t = this.min;
        _get2(_getPrototypeOf(Qo.prototype), "configure", this).call(this), this._startValue = Dt(t), this._valueRange = Dt(this.max) - Dt(t);
      }
    }, {
      key: "getPixelForValue",
      value: function getPixelForValue(t) {
        return void 0 !== t && 0 !== t || (t = this.min), null === t || isNaN(t) ? NaN : this.getPixelForDecimal(t === this.min ? 0 : (Dt(t) - this._startValue) / this._valueRange);
      }
    }, {
      key: "getValueForPixel",
      value: function getValueForPixel(t) {
        var e = this.getDecimalForPixel(t);
        return Math.pow(10, this._startValue + e * this._valueRange);
      }
    }]);
  }(Bs);
  function ta(t) {
    var e = t.ticks;
    if (e.display && t.display) {
      var _t71 = Ne(e.backdropPadding);
      return K(e.font && e.font.size, bt.font.size) + _t71.height;
    }
    return 0;
  }
  function ea(t, e, i, s, n) {
    return t === s || t === n ? {
      start: e - i / 2,
      end: e + i / 2
    } : t < s || t > n ? {
      start: e - i,
      end: e
    } : {
      start: e,
      end: e + i
    };
  }
  function ia(t) {
    var e = {
        l: t.left + t._padding.left,
        r: t.right - t._padding.right,
        t: t.top + t._padding.top,
        b: t.bottom - t._padding.bottom
      },
      i = Object.assign({}, e),
      s = [],
      n = [],
      o = t._pointLabels.length,
      a = t.options.pointLabels,
      r = a.centerPointLabels ? _t / o : 0;
    for (var _d12 = 0; _d12 < o; _d12++) {
      var _o38 = a.setContext(t.getPointLabelContext(_d12));
      n[_d12] = _o38.padding;
      var _u7 = t.getPointPosition(_d12, t.drawingArea + n[_d12], r),
        _f7 = He(_o38.font),
        _g4 = (l = t.ctx, h = _f7, c = Y(c = t._pointLabels[_d12]) ? c : [c], {
          w: qt(l, h.string, c),
          h: c.length * h.lineHeight
        });
      s[_d12] = _g4;
      var _p4 = Nt(t.getIndexAngle(_d12) + r),
        _m5 = Math.round(zt(_p4));
      sa(i, e, _p4, ea(_m5, _u7.x, _g4.w, 0, 180), ea(_m5, _u7.y, _g4.h, 90, 270));
    }
    var l, h, c;
    t.setCenterPoint(e.l - i.l, i.r - e.r, e.t - i.t, i.b - e.b), t._pointLabelItems = function (t, e, i) {
      var s = [],
        n = t._pointLabels.length,
        o = t.options,
        a = ta(o) / 2,
        r = t.drawingArea,
        l = o.pointLabels.centerPointLabels ? _t / n : 0;
      for (var _o39 = 0; _o39 < n; _o39++) {
        var _n31 = t.getPointPosition(_o39, r + a + i[_o39], l),
          _h24 = Math.round(zt(Nt(_n31.angle + kt))),
          _c14 = e[_o39],
          _d13 = aa(_n31.y, _c14.h, _h24),
          _u8 = na(_h24),
          _f8 = oa(_n31.x, _c14.w, _u8);
        s.push({
          x: _n31.x,
          y: _d13,
          textAlign: _u8,
          left: _f8,
          top: _d13,
          right: _f8 + _c14.w,
          bottom: _d13 + _c14.h
        });
      }
      return s;
    }(t, s, n);
  }
  function sa(t, e, i, s, n) {
    var o = Math.abs(Math.sin(i)),
      a = Math.abs(Math.cos(i));
    var r = 0,
      l = 0;
    s.start < e.l ? (r = (e.l - s.start) / o, t.l = Math.min(t.l, e.l - r)) : s.end > e.r && (r = (s.end - e.r) / o, t.r = Math.max(t.r, e.r + r)), n.start < e.t ? (l = (e.t - n.start) / a, t.t = Math.min(t.t, e.t - l)) : n.end > e.b && (l = (n.end - e.b) / a, t.b = Math.max(t.b, e.b + l));
  }
  function na(t) {
    return 0 === t || 180 === t ? "center" : t < 180 ? "left" : "right";
  }
  function oa(t, e, i) {
    return "right" === i ? t -= e : "center" === i && (t -= e / 2), t;
  }
  function aa(t, e, i) {
    return 90 === i || 270 === i ? t -= e / 2 : (i > 270 || i < 90) && (t -= e), t;
  }
  function ra(t, e, i, s) {
    var n = t.ctx;
    if (i) n.arc(t.xCenter, t.yCenter, e, 0, yt);else {
      var _i64 = t.getPointPosition(0, e);
      n.moveTo(_i64.x, _i64.y);
      for (var _o40 = 1; _o40 < s; _o40++) _i64 = t.getPointPosition(_o40, e), n.lineTo(_i64.x, _i64.y);
    }
  }
  Qo.id = "logarithmic", Qo.defaults = {
    ticks: {
      callback: Os.formatters.logarithmic,
      major: {
        enabled: !0
      }
    }
  };
  var la = /*#__PURE__*/function (_Go2) {
    function la(t) {
      var _this37;
      _classCallCheck(this, la);
      _this37 = _callSuper(this, la, [t]), _this37.xCenter = void 0, _this37.yCenter = void 0, _this37.drawingArea = void 0, _this37._pointLabels = [], _this37._pointLabelItems = [];
      return _this37;
    }
    _inherits(la, _Go2);
    return _createClass(la, [{
      key: "setDimensions",
      value: function setDimensions() {
        var t = this._padding = Ne(ta(this.options) / 2),
          e = this.width = this.maxWidth - t.width,
          i = this.height = this.maxHeight - t.height;
        this.xCenter = Math.floor(this.left + e / 2 + t.left), this.yCenter = Math.floor(this.top + i / 2 + t.top), this.drawingArea = Math.floor(Math.min(e, i) / 2);
      }
    }, {
      key: "determineDataLimits",
      value: function determineDataLimits() {
        var _this$getMinMax4 = this.getMinMax(!1),
          t = _this$getMinMax4.min,
          e = _this$getMinMax4.max;
        this.min = X(t) && !isNaN(t) ? t : 0, this.max = X(e) && !isNaN(e) ? e : 0, this.handleTickRangeOptions();
      }
    }, {
      key: "computeTickLimit",
      value: function computeTickLimit() {
        return Math.ceil(this.drawingArea / ta(this.options));
      }
    }, {
      key: "generateTickLabels",
      value: function generateTickLabels(t) {
        var _this38 = this;
        Go.prototype.generateTickLabels.call(this, t), this._pointLabels = this.getLabels().map(function (t, e) {
          var i = J(_this38.options.pointLabels.callback, [t, e], _this38);
          return i || 0 === i ? i : "";
        }).filter(function (t, e) {
          return _this38.chart.getDataVisibility(e);
        });
      }
    }, {
      key: "fit",
      value: function fit() {
        var t = this.options;
        t.display && t.pointLabels.display ? ia(this) : this.setCenterPoint(0, 0, 0, 0);
      }
    }, {
      key: "setCenterPoint",
      value: function setCenterPoint(t, e, i, s) {
        this.xCenter += Math.floor((t - e) / 2), this.yCenter += Math.floor((i - s) / 2), this.drawingArea -= Math.min(this.drawingArea / 2, Math.max(t, e, i, s));
      }
    }, {
      key: "getIndexAngle",
      value: function getIndexAngle(t) {
        return Nt(t * (yt / (this._pointLabels.length || 1)) + It(this.options.startAngle || 0));
      }
    }, {
      key: "getDistanceFromCenterForValue",
      value: function getDistanceFromCenterForValue(t) {
        if ($(t)) return NaN;
        var e = this.drawingArea / (this.max - this.min);
        return this.options.reverse ? (this.max - t) * e : (t - this.min) * e;
      }
    }, {
      key: "getValueForDistanceFromCenter",
      value: function getValueForDistanceFromCenter(t) {
        if ($(t)) return NaN;
        var e = t / (this.drawingArea / (this.max - this.min));
        return this.options.reverse ? this.max - e : this.min + e;
      }
    }, {
      key: "getPointLabelContext",
      value: function getPointLabelContext(t) {
        var e = this._pointLabels || [];
        if (t >= 0 && t < e.length) {
          var _i65 = e[t];
          return function (t, e, i) {
            return Ye(t, {
              label: i,
              index: e,
              type: "pointLabel"
            });
          }(this.getContext(), t, _i65);
        }
      }
    }, {
      key: "getPointPosition",
      value: function getPointPosition(t, e) {
        var i = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 0;
        var s = this.getIndexAngle(t) - kt + i;
        return {
          x: Math.cos(s) * e + this.xCenter,
          y: Math.sin(s) * e + this.yCenter,
          angle: s
        };
      }
    }, {
      key: "getPointPositionForValue",
      value: function getPointPositionForValue(t, e) {
        return this.getPointPosition(t, this.getDistanceFromCenterForValue(e));
      }
    }, {
      key: "getBasePosition",
      value: function getBasePosition(t) {
        return this.getPointPositionForValue(t || 0, this.getBaseValue());
      }
    }, {
      key: "getPointLabelPosition",
      value: function getPointLabelPosition(t) {
        var _this$_pointLabelItem = this._pointLabelItems[t],
          e = _this$_pointLabelItem.left,
          i = _this$_pointLabelItem.top,
          s = _this$_pointLabelItem.right,
          n = _this$_pointLabelItem.bottom;
        return {
          left: e,
          top: i,
          right: s,
          bottom: n
        };
      }
    }, {
      key: "drawBackground",
      value: function drawBackground() {
        var _this$options14 = this.options,
          t = _this$options14.backgroundColor,
          e = _this$options14.grid.circular;
        if (t) {
          var _i66 = this.ctx;
          _i66.save(), _i66.beginPath(), ra(this, this.getDistanceFromCenterForValue(this._endValue), e, this._pointLabels.length), _i66.closePath(), _i66.fillStyle = t, _i66.fill(), _i66.restore();
        }
      }
    }, {
      key: "drawGrid",
      value: function drawGrid() {
        var _this39 = this;
        var t = this.ctx,
          e = this.options,
          i = e.angleLines,
          s = e.grid,
          n = this._pointLabels.length;
        var o, a, r;
        if (e.pointLabels.display && function (t, e) {
          var i = t.ctx,
            s = t.options.pointLabels;
          for (var _n32 = e - 1; _n32 >= 0; _n32--) {
            var _e77 = s.setContext(t.getPointLabelContext(_n32)),
              _o41 = He(_e77.font),
              _t$_pointLabelItems$_ = t._pointLabelItems[_n32],
              _a26 = _t$_pointLabelItems$_.x,
              _r27 = _t$_pointLabelItems$_.y,
              _l27 = _t$_pointLabelItems$_.textAlign,
              _h25 = _t$_pointLabelItems$_.left,
              _c15 = _t$_pointLabelItems$_.top,
              _d14 = _t$_pointLabelItems$_.right,
              _u9 = _t$_pointLabelItems$_.bottom,
              _f9 = _e77.backdropColor;
            if (!$(_f9)) {
              var _t72 = Ne(_e77.backdropPadding);
              i.fillStyle = _f9, i.fillRect(_h25 - _t72.left, _c15 - _t72.top, _d14 - _h25 + _t72.width, _u9 - _c15 + _t72.height);
            }
            se(i, t._pointLabels[_n32], _a26, _r27 + _o41.lineHeight / 2, _o41, {
              color: _e77.color,
              textAlign: _l27,
              textBaseline: "middle"
            });
          }
        }(this, n), s.display && this.ticks.forEach(function (t, e) {
          if (0 !== e) {
            a = _this39.getDistanceFromCenterForValue(t.value);
            !function (t, e, i, s) {
              var n = t.ctx,
                o = e.circular,
                a = e.color,
                r = e.lineWidth;
              !o && !s || !a || !r || i < 0 || (n.save(), n.strokeStyle = a, n.lineWidth = r, n.setLineDash(e.borderDash), n.lineDashOffset = e.borderDashOffset, n.beginPath(), ra(t, i, o, s), n.closePath(), n.stroke(), n.restore());
            }(_this39, s.setContext(_this39.getContext(e - 1)), a, n);
          }
        }), i.display) {
          for (t.save(), o = n - 1; o >= 0; o--) {
            var _s51 = i.setContext(this.getPointLabelContext(o)),
              _n33 = _s51.color,
              _l28 = _s51.lineWidth;
            _l28 && _n33 && (t.lineWidth = _l28, t.strokeStyle = _n33, t.setLineDash(_s51.borderDash), t.lineDashOffset = _s51.borderDashOffset, a = this.getDistanceFromCenterForValue(e.ticks.reverse ? this.min : this.max), r = this.getPointPosition(o, a), t.beginPath(), t.moveTo(this.xCenter, this.yCenter), t.lineTo(r.x, r.y), t.stroke());
          }
          t.restore();
        }
      }
    }, {
      key: "drawBorder",
      value: function drawBorder() {}
    }, {
      key: "drawLabels",
      value: function drawLabels() {
        var _this40 = this;
        var t = this.ctx,
          e = this.options,
          i = e.ticks;
        if (!i.display) return;
        var s = this.getIndexAngle(0);
        var n, o;
        t.save(), t.translate(this.xCenter, this.yCenter), t.rotate(s), t.textAlign = "center", t.textBaseline = "middle", this.ticks.forEach(function (s, a) {
          if (0 === a && !e.reverse) return;
          var r = i.setContext(_this40.getContext(a)),
            l = He(r.font);
          if (n = _this40.getDistanceFromCenterForValue(_this40.ticks[a].value), r.showLabelBackdrop) {
            t.font = l.string, o = t.measureText(s.label).width, t.fillStyle = r.backdropColor;
            var _e78 = Ne(r.backdropPadding);
            t.fillRect(-o / 2 - _e78.left, -n - l.size / 2 - _e78.top, o + _e78.width, l.size + _e78.height);
          }
          se(t, s.label, 0, -n, l, {
            color: r.color
          });
        }), t.restore();
      }
    }, {
      key: "drawTitle",
      value: function drawTitle() {}
    }]);
  }(Go);
  la.id = "radialLinear", la.defaults = {
    display: !0,
    animate: !0,
    position: "chartArea",
    angleLines: {
      display: !0,
      lineWidth: 1,
      borderDash: [],
      borderDashOffset: 0
    },
    grid: {
      circular: !1
    },
    startAngle: 0,
    ticks: {
      showLabelBackdrop: !0,
      callback: Os.formatters.numeric
    },
    pointLabels: {
      backdropColor: void 0,
      backdropPadding: 2,
      display: !0,
      font: {
        size: 10
      },
      callback: function callback(t) {
        return t;
      },
      padding: 5,
      centerPointLabels: !1
    }
  }, la.defaultRoutes = {
    "angleLines.color": "borderColor",
    "pointLabels.color": "color",
    "ticks.color": "color"
  }, la.descriptors = {
    angleLines: {
      _fallback: "grid"
    }
  };
  var ha = {
      millisecond: {
        common: !0,
        size: 1,
        steps: 1e3
      },
      second: {
        common: !0,
        size: 1e3,
        steps: 60
      },
      minute: {
        common: !0,
        size: 6e4,
        steps: 60
      },
      hour: {
        common: !0,
        size: 36e5,
        steps: 24
      },
      day: {
        common: !0,
        size: 864e5,
        steps: 30
      },
      week: {
        common: !1,
        size: 6048e5,
        steps: 4
      },
      month: {
        common: !0,
        size: 2628e6,
        steps: 12
      },
      quarter: {
        common: !1,
        size: 7884e6,
        steps: 4
      },
      year: {
        common: !0,
        size: 3154e7
      }
    },
    ca = Object.keys(ha);
  function da(t, e) {
    return t - e;
  }
  function ua(t, e) {
    if ($(e)) return null;
    var i = t._adapter,
      _t$_parseOpts = t._parseOpts,
      s = _t$_parseOpts.parser,
      n = _t$_parseOpts.round,
      o = _t$_parseOpts.isoWeekday;
    var a = e;
    return "function" == typeof s && (a = s(a)), X(a) || (a = "string" == typeof s ? i.parse(a, s) : i.parse(a)), null === a ? null : (n && (a = "week" !== n || !Tt(o) && !0 !== o ? i.startOf(a, n) : i.startOf(a, "isoWeek", o)), +a);
  }
  function fa(t, e, i, s) {
    var n = ca.length;
    for (var _o42 = ca.indexOf(t); _o42 < n - 1; ++_o42) {
      var _t73 = ha[ca[_o42]],
        _n34 = _t73.steps ? _t73.steps : Number.MAX_SAFE_INTEGER;
      if (_t73.common && Math.ceil((i - e) / (_n34 * _t73.size)) <= s) return ca[_o42];
    }
    return ca[n - 1];
  }
  function ga(t, e, i) {
    if (i) {
      if (i.length) {
        var _ae = ae(i, e),
          _s52 = _ae.lo,
          _n35 = _ae.hi;
        t[i[_s52] >= e ? i[_s52] : i[_n35]] = !0;
      }
    } else t[e] = !0;
  }
  function pa(t, e, i) {
    var s = [],
      n = {},
      o = e.length;
    var a, r;
    for (a = 0; a < o; ++a) r = e[a], n[r] = a, s.push({
      value: r,
      major: !1
    });
    return 0 !== o && i ? function (t, e, i, s) {
      var n = t._adapter,
        o = +n.startOf(e[0].value, s),
        a = e[e.length - 1].value;
      var r, l;
      for (r = o; r <= a; r = +n.add(r, 1, s)) l = i[r], l >= 0 && (e[l].major = !0);
      return e;
    }(t, s, n, i) : s;
  }
  var ma = /*#__PURE__*/function (_Bs4) {
    function ma(t) {
      var _this41;
      _classCallCheck(this, ma);
      _this41 = _callSuper(this, ma, [t]), _this41._cache = {
        data: [],
        labels: [],
        all: []
      }, _this41._unit = "day", _this41._majorUnit = void 0, _this41._offsets = {}, _this41._normalized = !1, _this41._parseOpts = void 0;
      return _this41;
    }
    _inherits(ma, _Bs4);
    return _createClass(ma, [{
      key: "init",
      value: function init(t, e) {
        var i = t.time || (t.time = {}),
          s = this._adapter = new mn._date(t.adapters.date);
        ot(i.displayFormats, s.formats()), this._parseOpts = {
          parser: i.parser,
          round: i.round,
          isoWeekday: i.isoWeekday
        }, _get2(_getPrototypeOf(ma.prototype), "init", this).call(this, t), this._normalized = e.normalized;
      }
    }, {
      key: "parse",
      value: function parse(t, e) {
        return void 0 === t ? null : ua(this, t);
      }
    }, {
      key: "beforeLayout",
      value: function beforeLayout() {
        _get2(_getPrototypeOf(ma.prototype), "beforeLayout", this).call(this), this._cache = {
          data: [],
          labels: [],
          all: []
        };
      }
    }, {
      key: "determineDataLimits",
      value: function determineDataLimits() {
        var t = this.options,
          e = this._adapter,
          i = t.time.unit || "day";
        var _this$getUserBounds5 = this.getUserBounds(),
          s = _this$getUserBounds5.min,
          n = _this$getUserBounds5.max,
          o = _this$getUserBounds5.minDefined,
          a = _this$getUserBounds5.maxDefined;
        function r(t) {
          o || isNaN(t.min) || (s = Math.min(s, t.min)), a || isNaN(t.max) || (n = Math.max(n, t.max));
        }
        o && a || (r(this._getLabelBounds()), "ticks" === t.bounds && "labels" === t.ticks.source || r(this.getMinMax(!1))), s = X(s) && !isNaN(s) ? s : +e.startOf(Date.now(), i), n = X(n) && !isNaN(n) ? n : +e.endOf(Date.now(), i) + 1, this.min = Math.min(s, n - 1), this.max = Math.max(s + 1, n);
      }
    }, {
      key: "_getLabelBounds",
      value: function _getLabelBounds() {
        var t = this.getLabelTimestamps();
        var e = Number.POSITIVE_INFINITY,
          i = Number.NEGATIVE_INFINITY;
        return t.length && (e = t[0], i = t[t.length - 1]), {
          min: e,
          max: i
        };
      }
    }, {
      key: "buildTicks",
      value: function buildTicks() {
        var t = this.options,
          e = t.time,
          i = t.ticks,
          s = "labels" === i.source ? this.getLabelTimestamps() : this._generate();
        "ticks" === t.bounds && s.length && (this.min = this._userMin || s[0], this.max = this._userMax || s[s.length - 1]);
        var n = this.min,
          o = he(s, n, this.max);
        return this._unit = e.unit || (i.autoSkip ? fa(e.minUnit, this.min, this.max, this._getLabelCapacity(n)) : function (t, e, i, s, n) {
          for (var _o43 = ca.length - 1; _o43 >= ca.indexOf(i); _o43--) {
            var _i67 = ca[_o43];
            if (ha[_i67].common && t._adapter.diff(n, s, _i67) >= e - 1) return _i67;
          }
          return ca[i ? ca.indexOf(i) : 0];
        }(this, o.length, e.minUnit, this.min, this.max)), this._majorUnit = i.major.enabled && "year" !== this._unit ? function (t) {
          for (var _e79 = ca.indexOf(t) + 1, _i68 = ca.length; _e79 < _i68; ++_e79) if (ha[ca[_e79]].common) return ca[_e79];
        }(this._unit) : void 0, this.initOffsets(s), t.reverse && o.reverse(), pa(this, o, this._majorUnit);
      }
    }, {
      key: "initOffsets",
      value: function initOffsets(t) {
        var e,
          i,
          s = 0,
          n = 0;
        this.options.offset && t.length && (e = this.getDecimalForValue(t[0]), s = 1 === t.length ? 1 - e : (this.getDecimalForValue(t[1]) - e) / 2, i = this.getDecimalForValue(t[t.length - 1]), n = 1 === t.length ? i : (i - this.getDecimalForValue(t[t.length - 2])) / 2);
        var o = t.length < 3 ? .5 : .25;
        s = jt(s, 0, o), n = jt(n, 0, o), this._offsets = {
          start: s,
          end: n,
          factor: 1 / (s + 1 + n)
        };
      }
    }, {
      key: "_generate",
      value: function _generate() {
        var t = this._adapter,
          e = this.min,
          i = this.max,
          s = this.options,
          n = s.time,
          o = n.unit || fa(n.minUnit, e, i, this._getLabelCapacity(e)),
          a = K(n.stepSize, 1),
          r = "week" === o && n.isoWeekday,
          l = Tt(r) || !0 === r,
          h = {};
        var c,
          d,
          u = e;
        if (l && (u = +t.startOf(u, "isoWeek", r)), u = +t.startOf(u, l ? "day" : o), t.diff(i, e, o) > 1e5 * a) throw new Error(e + " and " + i + " are too far apart with stepSize of " + a + " " + o);
        var f = "data" === s.ticks.source && this.getDataTimestamps();
        for (c = u, d = 0; c < i; c = +t.add(c, a, o), d++) ga(h, c, f);
        return c !== i && "ticks" !== s.bounds && 1 !== d || ga(h, c, f), Object.keys(h).sort(function (t, e) {
          return t - e;
        }).map(function (t) {
          return +t;
        });
      }
    }, {
      key: "getLabelForValue",
      value: function getLabelForValue(t) {
        var e = this._adapter,
          i = this.options.time;
        return i.tooltipFormat ? e.format(t, i.tooltipFormat) : e.format(t, i.displayFormats.datetime);
      }
    }, {
      key: "_tickFormatFunction",
      value: function _tickFormatFunction(t, e, i, s) {
        var n = this.options,
          o = n.time.displayFormats,
          a = this._unit,
          r = this._majorUnit,
          l = a && o[a],
          h = r && o[r],
          c = i[e],
          d = r && h && c && c.major,
          u = this._adapter.format(t, s || (d ? h : l)),
          f = n.ticks.callback;
        return f ? J(f, [u, e, i], this) : u;
      }
    }, {
      key: "generateTickLabels",
      value: function generateTickLabels(t) {
        var e, i, s;
        for (e = 0, i = t.length; e < i; ++e) s = t[e], s.label = this._tickFormatFunction(s.value, e, t);
      }
    }, {
      key: "getDecimalForValue",
      value: function getDecimalForValue(t) {
        return null === t ? NaN : (t - this.min) / (this.max - this.min);
      }
    }, {
      key: "getPixelForValue",
      value: function getPixelForValue(t) {
        var e = this._offsets,
          i = this.getDecimalForValue(t);
        return this.getPixelForDecimal((e.start + i) * e.factor);
      }
    }, {
      key: "getValueForPixel",
      value: function getValueForPixel(t) {
        var e = this._offsets,
          i = this.getDecimalForPixel(t) / e.factor - e.end;
        return this.min + i * (this.max - this.min);
      }
    }, {
      key: "_getLabelSize",
      value: function _getLabelSize(t) {
        var e = this.options.ticks,
          i = this.ctx.measureText(t).width,
          s = It(this.isHorizontal() ? e.maxRotation : e.minRotation),
          n = Math.cos(s),
          o = Math.sin(s),
          a = this._resolveTickFontOptions(0).size;
        return {
          w: i * n + a * o,
          h: i * o + a * n
        };
      }
    }, {
      key: "_getLabelCapacity",
      value: function _getLabelCapacity(t) {
        var e = this.options.time,
          i = e.displayFormats,
          s = i[e.unit] || i.millisecond,
          n = this._tickFormatFunction(t, 0, pa(this, [t], this._majorUnit), s),
          o = this._getLabelSize(n),
          a = Math.floor(this.isHorizontal() ? this.width / o.w : this.height / o.h) - 1;
        return a > 0 ? a : 1;
      }
    }, {
      key: "getDataTimestamps",
      value: function getDataTimestamps() {
        var t,
          e,
          i = this._cache.data || [];
        if (i.length) return i;
        var s = this.getMatchingVisibleMetas();
        if (this._normalized && s.length) return this._cache.data = s[0].controller.getAllParsedValues(this);
        for (t = 0, e = s.length; t < e; ++t) i = i.concat(s[t].controller.getAllParsedValues(this));
        return this._cache.data = this.normalize(i);
      }
    }, {
      key: "getLabelTimestamps",
      value: function getLabelTimestamps() {
        var t = this._cache.labels || [];
        var e, i;
        if (t.length) return t;
        var s = this.getLabels();
        for (e = 0, i = s.length; e < i; ++e) t.push(ua(this, s[e]));
        return this._cache.labels = this._normalized ? t : this.normalize(t);
      }
    }, {
      key: "normalize",
      value: function normalize(t) {
        return fe(t.sort(da));
      }
    }]);
  }(Bs);
  function xa(t, e, i) {
    var _re, _t$r, _t$l, _re2, _t$r2, _t$l2;
    var s,
      n,
      o,
      a,
      r = 0,
      l = t.length - 1;
    i ? (e >= t[r].pos && e <= t[l].pos && (_re = re(t, "pos", e), r = _re.lo, l = _re.hi, _re), (_t$r = t[r], s = _t$r.pos, o = _t$r.time), (_t$l = t[l], n = _t$l.pos, a = _t$l.time)) : (e >= t[r].time && e <= t[l].time && (_re2 = re(t, "time", e), r = _re2.lo, l = _re2.hi, _re2), (_t$r2 = t[r], s = _t$r2.time, o = _t$r2.pos), (_t$l2 = t[l], n = _t$l2.time, a = _t$l2.pos));
    var h = n - s;
    return h ? o + (a - o) * (e - s) / h : o;
  }
  ma.id = "time", ma.defaults = {
    bounds: "data",
    adapters: {},
    time: {
      parser: !1,
      unit: !1,
      round: !1,
      isoWeekday: !1,
      minUnit: "millisecond",
      displayFormats: {}
    },
    ticks: {
      source: "auto",
      major: {
        enabled: !1
      }
    }
  };
  var ba = /*#__PURE__*/function (_ma) {
    function ba(t) {
      var _this42;
      _classCallCheck(this, ba);
      _this42 = _callSuper(this, ba, [t]), _this42._table = [], _this42._minPos = void 0, _this42._tableRange = void 0;
      return _this42;
    }
    _inherits(ba, _ma);
    return _createClass(ba, [{
      key: "initOffsets",
      value: function initOffsets() {
        var t = this._getTimestampsForTable(),
          e = this._table = this.buildLookupTable(t);
        this._minPos = xa(e, this.min), this._tableRange = xa(e, this.max) - this._minPos, _get2(_getPrototypeOf(ba.prototype), "initOffsets", this).call(this, t);
      }
    }, {
      key: "buildLookupTable",
      value: function buildLookupTable(t) {
        var e = this.min,
          i = this.max,
          s = [],
          n = [];
        var o, a, r, l, h;
        for (o = 0, a = t.length; o < a; ++o) l = t[o], l >= e && l <= i && s.push(l);
        if (s.length < 2) return [{
          time: e,
          pos: 0
        }, {
          time: i,
          pos: 1
        }];
        for (o = 0, a = s.length; o < a; ++o) h = s[o + 1], r = s[o - 1], l = s[o], Math.round((h + r) / 2) !== l && n.push({
          time: l,
          pos: o / (a - 1)
        });
        return n;
      }
    }, {
      key: "_getTimestampsForTable",
      value: function _getTimestampsForTable() {
        var t = this._cache.all || [];
        if (t.length) return t;
        var e = this.getDataTimestamps(),
          i = this.getLabelTimestamps();
        return t = e.length && i.length ? this.normalize(e.concat(i)) : e.length ? e : i, t = this._cache.all = t, t;
      }
    }, {
      key: "getDecimalForValue",
      value: function getDecimalForValue(t) {
        return (xa(this._table, t) - this._minPos) / this._tableRange;
      }
    }, {
      key: "getValueForPixel",
      value: function getValueForPixel(t) {
        var e = this._offsets,
          i = this.getDecimalForPixel(t) / e.factor - e.end;
        return xa(this._table, i * this._tableRange + this._minPos, !0);
      }
    }]);
  }(ma);
  ba.id = "timeseries", ba.defaults = ma.defaults;
  var _a = Object.freeze({
    __proto__: null,
    CategoryScale: qo,
    LinearScale: Zo,
    LogarithmicScale: Qo,
    RadialLinearScale: la,
    TimeScale: ma,
    TimeSeriesScale: ba
  });
  return dn.register(Rn, _a, no, Uo), dn.helpers = _objectSpread({}, Yi), dn._adapters = mn, dn.Animation = us, dn.Animations = gs, dn.animator = a, dn.controllers = Ws.controllers.items, dn.DatasetController = Ps, dn.Element = Ds, dn.elements = no, dn.Interaction = Ee, dn.layouts = ni, dn.platforms = hs, dn.Scale = Bs, dn.Ticks = Os, Object.assign(dn, Rn, _a, no, Uo, hs), dn.Chart = dn, "undefined" != typeof window && (window.Chart = dn), dn;
});

/***/ }),

/***/ 161:
/***/ (() => {

//Show the Modal ThickBox For Each Edit link
function wp_sms_edit_group(group_id, group_name) {
  tb_show(WP_Sms_Admin_Object.tag.group, WP_Sms_Admin_Object.ajaxUrls.group + '&group_id=' + group_id + '&group_name=' + encodeURIComponent(group_name) + '&width=400&height=125');
}
// Assign the function to the window object
window.wp_sms_edit_group = wp_sms_edit_group;

/***/ }),

/***/ 994:
/***/ (() => {

//Show the Modal ThickBox For Each Edit link
function wp_sms_edit_subscriber(subscriber_id) {
  if (typeof subscriber_id === 'number' && Number.isInteger(subscriber_id)) {
    tb_show(WP_Sms_Admin_Object.tag.subscribe, WP_Sms_Admin_Object.ajaxUrls.subscribe + '&subscriber_id=' + subscriber_id + '&width=400&height=310');
  }
}

// Assign the function to the window object
window.wp_sms_edit_subscriber = wp_sms_edit_subscriber;

/***/ }),

/***/ 181:
/***/ (() => {

function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }
function _iterableToArrayLimit(r, l) { var t = null == r ? null : "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t["return"] && (u = t["return"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }
function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }
jQuery(document).ready(function () {
  wpSmsImportSubscriber.init();
});
var wpSmsImportSubscriber = {
  init: function init() {
    this.setFields();
    this.uploadEventListener();
    this.selectColumnFileHeaderEventListener();
    this.selectOrAddGroup();
    this.disableSelectedOptions();
    this.bindImportRequestBody();
    this.refreshEventListener();
  },
  setFields: function setFields() {
    this.uploadForm = jQuery('.js-wpSmsUploadForm');
    this.importButton = jQuery('.js-wpSmsImportButton');
    this.uploadButton = jQuery('.js-wpSmsUploadButton');
    this.refreshButton = jQuery('.js-wpSmsRefreshButton');
    this.loadingSpinner = jQuery('.js-wpSmsOverlay');
    this.messageModal = jQuery('.js-wpSmsMessageModal');
    this.modalErrorMessage = jQuery('.js-wpSmsErrorMessage');
    this.importStep2 = jQuery('.js-WpSmsImportStep2');
    this.hasHeader = jQuery('.js-wpSmsFileHasHeader');
    this.importResult = jQuery('.js-WpSmsImportResult');
    this.importResultTable = jQuery('.js-WpSmsImportResult table tbody');
    this.requestBody = {};
    this.import_result = {};
    this.successUpload = 0;
  },
  uploadEventListener: function uploadEventListener() {
    var $this = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : this;
    $this.uploadForm.on('submit', function (event) {
      // avoid to execute the actual submit of the form
      event.preventDefault();
      var fileData = jQuery('#wp-sms-input-file')[0].files;
      var formData = new FormData();
      if (fileData.length > 0) {
        formData.append('file', fileData[0]);
      }

      // check whether the file has header
      var hasHeader = false;
      if ($this.hasHeader.is(':checked')) {
        hasHeader = true;
      }

      // send AJAX request
      jQuery.ajax({
        url: WP_Sms_Admin_Object.ajaxUrls.uploadSubscriberCsv + '&hasHeader=' + hasHeader,
        method: 'post',
        data: formData,
        contentType: false,
        cache: false,
        processData: false,
        // enabling loader
        beforeSend: function beforeSend() {
          $this.uploadButton.attr('disabled', 'disabled');
          $this.loadingSpinner.css('display', 'flex');
        },
        // successful request
        success: function success(response, data, xhr) {
          setTimeout(function () {
            $this.uploadButton.prop('disabled', false);
            $this.loadingSpinner.hide();
            $this.modalErrorMessage.removeClass('notice notice-error');
            $this.modalErrorMessage.addClass('notice notice-success');
            $this.modalErrorMessage.html('<p>' + response.data + '</p>');
            $this.messageModal.removeClass('hidden');
            $this.messageModal.addClass('not-hidden');
            jQuery('.js-WpSmsImportStep1').css('display', 'none');
            jQuery('#first-row-label').css('display', 'block');
            $this.uploadButton.hide();
            $this.importButton.show();
            var firstRow = JSON.parse(xhr.getResponseHeader("X-FirstRow-content"));
            firstRow.forEach(function (item) {
              jQuery('.js-wpSmsGroupSelect').before('<tr class="js-wpSmsDataTypeRow">' + '<td>' + item + '</td>' + '<td><span class="dashicons dashicons-arrow-right-alt"></span></td>' + '<td>' + '<select class="js-wpSmsImportColumnType">' + '<option value="0">Please Select</option>' + '<option value="name">Name</option>' + '<option value="mobile">Mobile</option>' + '<option value="group">Group ID</option>' + '</select>' + '</td>' + '</tr>');
            });
          }, 1000);
        },
        // failed request
        error: function error(data, response, xhr) {
          $this.uploadButton.prop('disabled', false);

          //disable loading spinner
          $this.loadingSpinner.css('display', 'none');

          //print error messages
          $this.modalErrorMessage.removeClass('notice notice-success');
          $this.modalErrorMessage.addClass('notice notice-error');
          $this.modalErrorMessage.html("<p>" + data.responseJSON.data + "</p>");
          $this.messageModal.removeClass('hidden');
          $this.messageModal.addClass('not-hidden');
        }
      });
    }.bind(this));
  },
  selectColumnFileHeaderEventListener: function selectColumnFileHeaderEventListener() {
    jQuery('body').on('change', '.js-wpSmsImportColumnType', function (event) {
      var isGroupSelected = false;
      jQuery('.js-wpSmsImportColumnType').each(function () {
        // check if the group id is selected
        if (jQuery(this).val() === 'group') {
          isGroupSelected = true;
        }
      });
      if (isGroupSelected) {
        jQuery('.js-wpSmsGroupSelect').css('display', 'none');
      } else {
        jQuery('.js-wpSmsGroupSelect').css('display', 'block');
      }
    });
  },
  selectOrAddGroup: function selectOrAddGroup() {
    jQuery('body').on('change', '.js-wpSmsGroupSelect select', function (event) {
      if (jQuery('.js-wpSmsGroupSelect select').val() === 'new_group') {
        jQuery('.js-wpSmsGroupName').css('display', 'block');
      } else {
        jQuery('.js-wpSmsGroupName').css('display', 'none');
      }
    });
  },
  disableSelectedOptions: function disableSelectedOptions() {
    jQuery('body').on('change', '.js-wpSmsImportColumnType', function (event) {
      var selectedOptions = [];
      jQuery('.js-wpSmsImportColumnType').each(function () {
        var value = jQuery(this).val();
        if (value !== '0' && !selectedOptions.includes(value)) {
          selectedOptions.push(value);
        }
        jQuery('.js-wpSmsImportColumnType option').each(function () {
          if (!selectedOptions.includes(jQuery(this).val())) {
            jQuery(this).attr('disabled', false);
          }
        });
        jQuery('.js-wpSmsImportColumnType option').each(function () {
          if (selectedOptions.includes(jQuery(this).val())) {
            jQuery(this).attr('disabled', true);
          }
        });
      });
    });
  },
  bindImportRequestBody: function bindImportRequestBody() {
    var $this = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : this;
    $this.importButton.on('click', function (event) {
      // avoid to execute the actual submit of the form
      event.preventDefault();
      var selectGroupColumn = jQuery('.js-wpSmsImportColumnType');
      selectGroupColumn.each(function (index) {
        if (jQuery(this).find('option:selected').val() !== '0') {
          var objectKey = jQuery(this).find('option:selected').val();
          $this.requestBody[objectKey] = index;
        }
      });
      if (!$this.requestBody.group) {
        var selectedGroupOption = jQuery('.js-wpSmsGroupSelect select').val();
        var groupName = jQuery('.js-wpSmsSelectGroupName').val();
        switch (selectedGroupOption) {
          case '0':
            $this.requestBody['state'] = 0;
            $this.requestBody['group'] = null;
            break;
          case 'new_group':
            $this.requestBody['state'] = 'new_group';
            $this.requestBody['group'] = groupName;
            break;
          default:
            $this.requestBody['state'] = 'existed_group';
            $this.requestBody['group'] = selectedGroupOption;
            break;
        }
      }
      if ($this.hasHeader.is(':checked')) {
        $this.requestBody.hasHeader = true;
      }
      jQuery('#TB_ajaxContent').animate({
        scrollTop: '0px'
      }, 300);
      $this.importEventListener(0);
    });
  },
  importEventListener: function importEventListener(startPoint) {
    var $this = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : this;
    $this.requestBody.startPoint = startPoint;
    jQuery.ajax({
      url: WP_Sms_Admin_Object.ajaxUrls.importSubscriberCsv,
      method: 'GET',
      data: $this.requestBody,
      // enabling loader
      beforeSend: function beforeSend() {
        $this.uploadButton.attr('disabled', 'disabled');
        $this.loadingSpinner.css('display', 'flex');
      },
      // successful request
      success: function success(request, data, response) {
        var isImportDone = response.responseJSON.data.importDone;
        var getStartPoint = response.responseJSON.data.startPoint;
        var totalSubscriber = response.responseJSON.data.count;
        var errors = response.responseJSON.data.errors;
        if (!isImportDone) {
          for (var _i = 0, _Object$entries = Object.entries(errors); _i < _Object$entries.length; _i++) {
            var _Object$entries$_i = _slicedToArray(_Object$entries[_i], 2),
              key = _Object$entries$_i[0],
              value = _Object$entries$_i[1];
            $this.import_result[key] = value;
          }
        }
        if (response.responseJSON.data.successUpload) {
          $this.successUpload += parseInt(response.responseJSON.data.successUpload);
        }
        if (isImportDone) {
          //disable loading spinner
          $this.loadingSpinner.css('display', 'none');
          $this.importStep2.css('display', 'none');
          $this.importButton.css('display', 'none');
          $this.refreshButton.css('display', 'block');

          //print error messages and result
          $this.messageModal.removeClass('hidden');
          $this.messageModal.addClass('not-hidden');
          $this.modalErrorMessage.removeClass('notice-error');
          $this.modalErrorMessage.addClass('notice-success');
          var $alert_message;
          switch ($this.successUpload) {
            case totalSubscriber:
              $alert_message = '<p>Subscribers have been imported successfully!</p>';
              break;
            case 0:
              $this.modalErrorMessage.removeClass('notice-success');
              $this.modalErrorMessage.addClass('notice-error');
              $alert_message = '<p>Subscribers have not been imported. Look for errors in the logs.</p>';
              break;
            default:
              $alert_message = '<p>' + $this.successUpload + ' of ' + totalSubscriber + ' subscribers have been imported successfully!</p>';
          }
          $this.modalErrorMessage.html($alert_message);
          if (!jQuery.isEmptyObject($this.import_result)) {
            $this.importResult.show();
            for (var _i2 = 0, _Object$entries2 = Object.entries($this.import_result); _i2 < _Object$entries2.length; _i2++) {
              var _Object$entries2$_i = _slicedToArray(_Object$entries2[_i2], 2),
                number = _Object$entries2$_i[0],
                failureMessage = _Object$entries2$_i[1];
              $this.importResultTable.append("<tr><td><code>" + number + "</code></td><td>" + failureMessage + "</td></tr>");
            }
          }
          return;
        }
        return $this.importEventListener(getStartPoint);
      },
      // failed request
      error: function error(response) {
        $this.uploadButton.prop('disabled', false);

        //disable loading spinner
        $this.loadingSpinner.css('display', 'none');

        //print error messages
        $this.messageModal.removeClass('hidden');
        $this.messageModal.addClass('not-hidden');
        $this.modalErrorMessage.removeClass('notice notice-success');
        $this.modalErrorMessage.addClass('notice notice-error');
        $this.modalErrorMessage.html("<p>" + response.responseJSON.data + "</p>");
      }
    });
  },
  refreshEventListener: function refreshEventListener() {
    var $this = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : this;
    $this.refreshButton.on('click', function (event) {
      // avoid to execute the actual submit of the form
      event.preventDefault();
      window.location.reload();
    });
  }
};

/***/ }),

/***/ 689:
/***/ (() => {

jQuery(document).ready(function () {
  quickReply.init();
});
var quickReply = {
  /**
   * initialize functions
   */

  init: function init() {
    this.setFields();
    this.addEventListener();
  },
  /**
   * initialize JQ selectors
   */

  setFields: function setFields() {
    this.fromNumber = jQuery('.js-replyModalToggle');
    this.toNumber = jQuery('.js-wpSmsQuickReplyTo');
    this.replyMessage = jQuery('.js-wpSmsQuickReplyMessage');
    this.submitButton = jQuery('.quick-reply-submit');
  },
  addEventListener: function addEventListener() {
    /**
     * copy clicked number contents to TickBox form
     */

    this.fromNumber.on('click', function (event) {
      // clear the form
      this.replyMessage.val('');
      jQuery('.wpsms-quick-reply-popup').removeClass('not-hidden');
      jQuery('.wpsms-quick-reply-popup').addClass('hidden');

      // copy value of clicked item into ThickBox's To field
      this.toNumber.attr('value', event.delegateTarget.dataset.number);

      // copy group id of subscribers to ThickBox's to field. This attribute only generate in Groups page
      if (this.fromNumber.attr('data-group-id')) {
        this.toNumber.attr('data-group-id', event.delegateTarget.dataset.groupId);
      }
    }.bind(this));

    /**
     * This function sends AJAX request
     */

    this.submitButton.on('click', function (event) {
      var data = this.bindData();

      //generating request body
      var requestBody = {
        sender: WP_Sms_Admin_Object.senderID,
        recipients: data.recipient,
        message: this.replyMessage.val(),
        numbers: data.numbers,
        group_ids: data.groupId,
        media_urls: []
      };
      jQuery.ajax({
        url: WP_Sms_Admin_Object.restUrls.sendSms,
        headers: {
          'X-WP-Nonce': WP_Sms_Admin_Object.nonce
        },
        dataType: 'json',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(requestBody),
        beforeSend: function beforeSend() {
          jQuery('input[name="SendSMS"]').attr('disabled', 'disabled');
          jQuery('.wpsms-sendsms__overlay').css('display', 'flex');
        },
        success: function success(data, status, xhr) {
          jQuery('.wpsms-sendsms__overlay').css('display', 'none');
          jQuery('.wpsms-quick-reply-popup .wp-sms-popup-messages').removeClass('notice notice-error');
          jQuery('.wpsms-quick-reply-popup .wp-sms-popup-messages').addClass('notice notice-success');
          jQuery('.wpsms-quick-reply-popup .wp-sms-popup-messages').html('<p>' + data.message + '</p>');
          jQuery('.wpsms-quick-reply-popup').removeClass('hidden');
          jQuery('.wpsms-quick-reply-popup').addClass('not-hidden');
          jQuery('input[name="SendSMS"]').prop('disabled', false);
          if (jQuery('.js-wpSmsQuickReply').attr('data-reload')) {
            location.reload();
          }
        },
        error: function error(data, status, xhr) {
          jQuery('.wpsms-sendsms__overlay').css('display', 'none');
          jQuery('.wpsms-quick-reply-popup .wp-sms-popup-messages').removeClass('notice notice-success');
          jQuery('.wpsms-quick-reply-popup .wp-sms-popup-messages').addClass('notice notice-error');
          jQuery('.wpsms-quick-reply-popup .wp-sms-popup-messages').html("<p>" + data.responseJSON.error.message + "</p>");
          jQuery('.wpsms-quick-reply-popup').removeClass('hidden');
          jQuery('.wpsms-quick-reply-popup').addClass('not-hidden');
          jQuery('input[name="SendSMS"]').prop('disabled', false);
        }
      });
    }.bind(this));
  },
  /**
   * generate request data
   * @returns string
   */

  bindData: function bindData() {
    var requestInfo = {};
    if (this.fromNumber.attr('data-group-id')) {
      requestInfo.recipient = 'subscribers';
      requestInfo.numbers = [];
      requestInfo.groupId = [this.toNumber.attr('data-group-id')];
    } else {
      requestInfo.recipient = 'numbers';
      requestInfo.numbers = [this.toNumber.attr('value')];
      requestInfo.groupId = [];
    }
    return requestInfo;
  }
};

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	__webpack_require__(689);
/******/ 	__webpack_require__(181);
/******/ 	__webpack_require__(72);
/******/ 	__webpack_require__(647);
/******/ 	__webpack_require__(28);
/******/ 	__webpack_require__(994);
/******/ 	__webpack_require__(161);
/******/ 	__webpack_require__(550);
/******/ 	__webpack_require__(639);
/******/ 	__webpack_require__(848);
/******/ 	// This entry module is referenced by other modules so it can't be inlined
/******/ 	__webpack_require__(717);
/******/ 	var __webpack_exports__ = __webpack_require__(859);
/******/ 	
/******/ })()
;