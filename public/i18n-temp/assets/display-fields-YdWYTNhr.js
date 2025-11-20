import { j as jsxRuntimeExports } from "./main-D1KP0B5-.js";
import { A as Alert, C as CircleAlert, b as AlertTitle, a as AlertDescription } from "./alert-DiJsRLFO.js";
import { S as Separator } from "./separator-BSI9ZTwI.js";
import { F as FieldWrapper } from "./field-wrapper-BuuNVHwJ.js";
import "./use-save-settings-values-BPUIfNdJ.js";
import "./sprintf-DmNrJSYG.js";
import "./index-W778R1kW.js";
const HtmlRenderer = ({ schema }) => {
  return schema.options ? /* @__PURE__ */ jsxRuntimeExports.jsx(FieldWrapper, { schema, errors: [], children: /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "font-light text-sm", dangerouslySetInnerHTML: { __html: schema.options } }) }) : null;
};
const Header = ({ schema }) => {
  return /* @__PURE__ */ jsxRuntimeExports.jsxs(FieldWrapper, { schema, errors: [], children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(Separator, { className: "my-2" }),
    /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "font-extrabold text-sm", children: schema.groupLabel })
  ] });
};
const Notice = ({ schema }) => {
  return /* @__PURE__ */ jsxRuntimeExports.jsxs(Alert, { variant: "default", children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(CircleAlert, {}),
    /* @__PURE__ */ jsxRuntimeExports.jsx(AlertTitle, { children: schema.label }),
    /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDescription, { children: schema.description })
  ] });
};
export {
  Header,
  HtmlRenderer,
  Notice
};
