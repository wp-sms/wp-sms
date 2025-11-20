import { j as jsxRuntimeExports } from "./main-D1KP0B5-.js";
import { j as useFieldContext, k as useStore } from "./use-save-settings-values-BPUIfNdJ.js";
import { I as Input } from "./input-DTxI4jvI.js";
import { F as FieldWrapper } from "./field-wrapper-BuuNVHwJ.js";
import "./alert-DiJsRLFO.js";
import "./sprintf-DmNrJSYG.js";
import "./index-W778R1kW.js";
const NumberField = ({ schema }) => {
  const field = useFieldContext();
  const errors = useStore(field.store, (state) => state.meta.errors);
  return /* @__PURE__ */ jsxRuntimeExports.jsx(FieldWrapper, { schema, errors, children: /* @__PURE__ */ jsxRuntimeExports.jsx(
    Input,
    {
      id: schema.key,
      type: "number",
      min: schema.min || void 0,
      max: schema.max || void 0,
      step: schema.step || void 0,
      defaultValue: String(schema.default || ""),
      value: String(field.state.value || ""),
      onBlur: field.handleBlur,
      onChange: (e) => field.handleChange(parseFloat(e.target.value) || 0),
      disabled: schema.readonly,
      "aria-invalid": !!errors.length
    }
  ) });
};
export {
  NumberField
};
