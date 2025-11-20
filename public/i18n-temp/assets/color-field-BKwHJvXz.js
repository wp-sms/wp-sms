import { j as jsxRuntimeExports } from "./main-D1KP0B5-.js";
import { j as useFieldContext, k as useStore } from "./use-save-settings-values-BPUIfNdJ.js";
import { I as Input } from "./input-DTxI4jvI.js";
import { F as FieldWrapper } from "./field-wrapper-BuuNVHwJ.js";
import "./alert-DiJsRLFO.js";
import "./sprintf-DmNrJSYG.js";
import "./index-W778R1kW.js";
const ColorField = ({ schema }) => {
  const field = useFieldContext();
  const errors = useStore(field.store, (state) => state.meta.errors);
  return /* @__PURE__ */ jsxRuntimeExports.jsx(FieldWrapper, { errors, schema, children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex items-center gap-2", children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(
      Input,
      {
        type: "color",
        id: schema.key,
        defaultValue: String(schema.default || "#000"),
        value: String(field.state.value),
        onBlur: field.handleBlur,
        onChange: (e) => field.handleChange(e.target.value),
        disabled: schema.readonly,
        "aria-invalid": !!errors.length,
        className: "w-12 h-10 p-1 border rounded cursor-pointer"
      }
    ),
    /* @__PURE__ */ jsxRuntimeExports.jsx(
      Input,
      {
        type: "text",
        placeholder: schema.placeholder,
        defaultValue: String(schema.default || "#000"),
        value: String(field.state.value),
        onBlur: field.handleBlur,
        onChange: (e) => field.handleChange(e.target.value),
        disabled: schema.readonly,
        "aria-invalid": !!errors.length,
        className: "flex-1"
      }
    )
  ] }) });
};
export {
  ColorField
};
