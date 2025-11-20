var PATTERN = /%(((\d+)\$)|(\(([$_a-zA-Z][$_a-zA-Z0-9]*)\)))?[ +0#-]*\d*(\.(\d+|\*))?(ll|[lhqL])?([cduxXefgsp%])/g;
function sprintf$1(string, ...args) {
  var i = 0;
  if (Array.isArray(args[0])) {
    args = /** @type {import('../types').SprintfArgs<T>[]} */
    /** @type {unknown} */
    args[0];
  }
  return string.replace(PATTERN, function() {
    var index, name, precision, type, value;
    index = arguments[3];
    name = arguments[5];
    precision = arguments[7];
    type = arguments[9];
    if (type === "%") {
      return "%";
    }
    if (precision === "*") {
      precision = args[i];
      i++;
    }
    if (name === void 0) {
      if (index === void 0) {
        index = i + 1;
      }
      i++;
      value = args[index - 1];
    } else if (args[0] && typeof args[0] === "object" && args[0].hasOwnProperty(name)) {
      value = args[0][name];
    }
    if (type === "f") {
      value = parseFloat(value) || 0;
    } else if (type === "d") {
      value = parseInt(value) || 0;
    }
    if (precision !== void 0) {
      if (type === "f") {
        value = value.toFixed(precision);
      } else if (type === "s") {
        value = value.substr(0, precision);
      }
    }
    return value !== void 0 && value !== null ? value : "";
  });
}
function sprintf(format, ...args) {
  return sprintf$1(format, ...args);
}
export {
  sprintf as s
};
