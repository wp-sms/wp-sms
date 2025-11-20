import { j as jsxRuntimeExports, e as cn } from "./main-D1KP0B5-.js";
import { j as useFieldContext, k as useStore } from "./use-save-settings-values-BPUIfNdJ.js";
import { F as FieldWrapper } from "./field-wrapper-BuuNVHwJ.js";
import "./alert-DiJsRLFO.js";
import "./sprintf-DmNrJSYG.js";
import "./index-W778R1kW.js";
function Textarea({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    "textarea",
    {
      "data-slot": "textarea",
      className: cn(
        "border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
        className
      ),
      ...props
    }
  );
}
const TextareaField = ({ schema }) => {
  const field = useFieldContext();
  const errors = useStore(field.store, (state) => state.meta.errors);
  return /* @__PURE__ */ jsxRuntimeExports.jsx(FieldWrapper, { schema, errors, children: /* @__PURE__ */ jsxRuntimeExports.jsx(
    Textarea,
    {
      id: schema.key,
      placeholder: schema.placeholder,
      value: String(field.state.value || ""),
      onBlur: field.handleBlur,
      onChange: (e) => field.handleChange(e.target.value),
      disabled: schema.readonly,
      rows: schema.rows || 3,
      "aria-invalid": !!errors.length
    }
  ) });
};
export {
  TextareaField
};
