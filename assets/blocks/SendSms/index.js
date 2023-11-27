/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./assets/src/blocks/SendSms/edit.js":
/*!*******************************************!*\
  !*** ./assets/src/blocks/SendSms/edit.js ***!
  \*******************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": function() { return /* binding */ edit; }
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);






/**
 * Edit function.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
function edit(_ref) {
  let {
    attributes,
    setAttributes
  } = _ref;
  // Destructure your attributes
  const {
    title,
    description,
    onlyLoggedUsers,
    userRole,
    maxCharacters,
    receiver,
    subscriberGroup
  } = attributes;

  // Define states
  const [showSubscriberGroup, setShowSubscriberGroup] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(receiver == 'subscribers');
  const [showUserRoles, setShowUserRoles] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(onlyLoggedUsers);

  // Handlers to update attributes
  const onChangeTitle = val => setAttributes({
    title: val
  });
  const onChangeDescription = val => setAttributes({
    description: val
  });
  const onChangeUserRole = val => setAttributes({
    userRole: val
  });
  const onChangeMaxCharacters = val => setAttributes({
    maxCharacters: val
  });
  const onChangeSubscriberGroup = val => setAttributes({
    subscriberGroup: val
  });
  const onChangeReceiver = function (val) {
    val == 'subscribers' ? setShowSubscriberGroup(true) : setShowSubscriberGroup(false);
    setAttributes({
      receiver: val
    });
  };
  const toggleLoggedUsers = function (val) {
    val ? setShowUserRoles(true) : setShowUserRoles(false);
    setAttributes({
      onlyLoggedUsers: !onlyLoggedUsers
    });
  };

  // Define the controls for block settings
  const blockSettings = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Settings', 'wp-sms'),
    initialOpen: true
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Only for logged in users', 'wp-sms'),
    checked: onlyLoggedUsers,
    onChange: toggleLoggedUsers
  }), showUserRoles && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RadioControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Select Role', 'wp-sms'),
    selected: userRole,
    options: wpSmsSendSmsBlockData.userRoleOptions,
    onChange: onChangeUserRole
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Max Characters', 'wp-sms'),
    value: maxCharacters,
    onChange: onChangeMaxCharacters,
    type: "number"
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Receiver', 'wp-sms'),
    value: receiver,
    options: [{
      label: 'Custom Number',
      value: 'numbers'
    }, {
      label: 'Admin',
      value: 'admin'
    }, {
      label: 'Subscribers',
      value: 'subscribers'
    }],
    onChange: onChangeReceiver
  }), showSubscriberGroup && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Subscriber Group', 'wp-sms'),
    value: subscriberGroup,
    options: wpSmsSendSmsBlockData.subscriberGroups,
    onChange: onChangeSubscriberGroup
  })));
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, blockSettings, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)(), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "wp-sms-block wp-sms-block--sendSms"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", {
    className: "wp-sms-block__title"
  }, "Send SMS"), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "wp-sms-block__main"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Title', 'wp-sms'),
    value: title,
    onChange: onChangeTitle
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Description', 'wp-sms'),
    value: description,
    onChange: onChangeDescription
  })))));
}

/***/ }),

/***/ "./assets/src/blocks/SendSms/index.css":
/*!*********************************************!*\
  !*** ./assets/src/blocks/SendSms/index.css ***!
  \*********************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ (function(module) {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ (function(module) {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ (function(module) {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ (function(module) {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ (function(module) {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "./assets/src/blocks/SendSms/block.json":
/*!**********************************************!*\
  !*** ./assets/src/blocks/SendSms/block.json ***!
  \**********************************************/
/***/ (function(module) {

module.exports = JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":2,"name":"wp-sms-blocks/send-sms","title":"Send SMS","category":"wp-sms-blocks","editorScript":"file:./index.js","editorStyle":"file:./index.css","attributes":{"title":{"type":"string"},"description":{"type":"string"}},"example":{"attributes":{"title":"Send SMS","description":"Send SMS"}}}');

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
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	!function() {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = function(module) {
/******/ 			var getter = module && module.__esModule ?
/******/ 				function() { return module['default']; } :
/******/ 				function() { return module; };
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
!function() {
/*!********************************************!*\
  !*** ./assets/src/blocks/SendSms/index.js ***!
  \********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _index_css__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./index.css */ "./assets/src/blocks/SendSms/index.css");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./edit */ "./assets/src/blocks/SendSms/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./block.json */ "./assets/src/blocks/SendSms/block.json");

/**
 * Send SMS
 */



/**
 * Internal dependencies
 */


(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_1__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_4__, {
  icon: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    width: "24",
    height: "24",
    viewBox: "0 0 24 24",
    fill: "none",
    xmlns: "http://www.w3.org/2000/svg"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    d: "M6.74086 17.5785C6.35715 17.5785 6.04673 17.8971 6.04673 18.2882C6.04673 18.6814 6.35715 19 6.74086 19V17.5785ZM21.9903 18.2882V19C22.3438 19 22.6391 18.7298 22.6801 18.3716L21.9903 18.2882ZM23.3052 6.70965L23.9951 6.79314C24.0188 6.59101 23.9563 6.38888 23.8248 6.23728C23.6933 6.08569 23.5036 6 23.3052 6V6.70965ZM8.84482 6.70965V6C8.56458 6 8.31021 6.17357 8.20458 6.43941C8.0968 6.70306 8.155 7.01065 8.35333 7.21278L8.84482 6.70965ZM15.1545 13.1734L14.6652 13.6743C14.911 13.927 15.3033 13.9534 15.5792 13.7336L15.1545 13.1734ZM3.06109 9.49992C2.67738 9.49992 2.36696 9.81849 2.36696 10.2118C2.36696 10.6028 2.67738 10.9214 3.06109 10.9214V9.49992ZM8.05584 10.9214C8.43955 10.9214 8.74997 10.6028 8.74997 10.2118C8.74997 9.81849 8.43955 9.49992 8.05584 9.49992V10.9214ZM0.694134 11.9233C0.31042 11.9233 0 12.2418 0 12.6351C0 13.0262 0.31042 13.3448 0.694134 13.3448V11.9233ZM10.4228 13.3448C10.8044 13.3448 11.1169 13.0262 11.1169 12.6351C11.1169 12.2418 10.8044 11.9233 10.4228 11.9233V13.3448ZM12.2638 16.0362C12.6453 16.0362 12.9557 15.7198 12.9557 15.3265C12.9557 14.9354 12.6453 14.6169 12.2638 14.6169V16.0362ZM4.37391 14.6169C3.99235 14.6169 3.68193 14.9354 3.68193 15.3265C3.68193 15.7198 3.99235 16.0362 4.37391 16.0362V14.6169ZM6.74086 19H21.9903V17.5785H6.74086V19ZM22.6801 18.3716L23.9951 6.79314L22.6176 6.62836L21.3026 18.2069L22.6801 18.3716ZM23.3052 6H8.84482V7.4215H23.3052V6ZM8.35333 7.21278L14.6652 13.6743L15.646 12.6703L9.33417 6.20872L8.35333 7.21278ZM15.5792 13.7336L23.7299 7.2721L22.8806 6.1494L14.7299 12.611L15.5792 13.7336ZM3.06109 10.9214H8.05584V9.49992H3.06109V10.9214ZM0.694134 13.3448H10.4228V11.9233H0.694134V13.3448ZM12.2638 14.6169H4.37391V16.0362H12.2638V14.6169Z",
    fill: "#F88E40"
  })),
  /**
   * @see ./edit.js
   */
  edit: _edit__WEBPACK_IMPORTED_MODULE_3__["default"]
});
}();
/******/ })()
;
//# sourceMappingURL=index.js.map