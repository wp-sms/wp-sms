import { c as createLucideIcon, r as reactExports, j as jsxRuntimeExports, B as Button, _ as __ } from "./main-D1KP0B5-.js";
import { j as useFieldContext, k as useStore } from "./use-save-settings-values-BPUIfNdJ.js";
import { I as Input } from "./input-DTxI4jvI.js";
import { F as FieldWrapper } from "./field-wrapper-BuuNVHwJ.js";
import "./alert-DiJsRLFO.js";
import "./sprintf-DmNrJSYG.js";
import "./index-W778R1kW.js";
const __iconNode$1 = [
  [
    "path",
    {
      d: "M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49",
      key: "ct8e1f"
    }
  ],
  ["path", { d: "M14.084 14.158a3 3 0 0 1-4.242-4.242", key: "151rxh" }],
  [
    "path",
    {
      d: "M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143",
      key: "13bj9a"
    }
  ],
  ["path", { d: "m2 2 20 20", key: "1ooewy" }]
];
const EyeOff = createLucideIcon("eye-off", __iconNode$1);
const __iconNode = [
  [
    "path",
    {
      d: "M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0",
      key: "1nclc0"
    }
  ],
  ["circle", { cx: "12", cy: "12", r: "3", key: "1v7zrd" }]
];
const Eye = createLucideIcon("eye", __iconNode);
const PasswordField = ({ schema }) => {
  const field = useFieldContext();
  const [showPassword, setShowPassword] = reactExports.useState(false);
  const errors = useStore(field.store, (state) => state.meta.errors);
  return /* @__PURE__ */ jsxRuntimeExports.jsx(FieldWrapper, { schema, errors, children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "relative", children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(
      Input,
      {
        id: schema.key,
        type: showPassword ? "text" : "password",
        placeholder: schema.placeholder,
        defaultValue: String(schema.default || ""),
        value: String(field.state.value || ""),
        onBlur: field.handleBlur,
        onChange: (e) => field.handleChange(e.target.value),
        disabled: schema.readonly,
        "aria-invalid": !!errors.length,
        className: "pr-10"
      }
    ),
    /* @__PURE__ */ jsxRuntimeExports.jsxs(
      Button,
      {
        type: "button",
        variant: "ghost",
        size: "sm",
        className: "absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent",
        onClick: () => setShowPassword(!showPassword),
        disabled: schema.readonly,
        children: [
          showPassword ? /* @__PURE__ */ jsxRuntimeExports.jsx(EyeOff, { className: "h-4 w-4" }) : /* @__PURE__ */ jsxRuntimeExports.jsx(Eye, { className: "h-4 w-4" }),
          /* @__PURE__ */ jsxRuntimeExports.jsx("span", { className: "sr-only", children: showPassword ? __("Hide password", "wp-sms") : __("Show password", "wp-sms") })
        ]
      }
    )
  ] }) });
};
export {
  PasswordField
};
