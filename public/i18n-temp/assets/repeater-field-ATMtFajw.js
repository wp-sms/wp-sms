import { c as createLucideIcon, j as jsxRuntimeExports, _ as __, r as reactExports, B as Button, g as cva } from "./main-D1KP0B5-.js";
import { s as sprintf } from "./sprintf-DmNrJSYG.js";
import { A as AlertDialog, l as AlertDialogTrigger, m as AlertDialogContent, n as AlertDialogHeader, o as AlertDialogTitle, p as AlertDialogDescription, q as AlertDialogFooter, r as AlertDialogCancel, t as AlertDialogAction, j as useFieldContext, k as useStore, v as shouldShowField, F as FieldRenderer } from "./use-save-settings-values-BPUIfNdJ.js";
import { F as FieldWrapper } from "./field-wrapper-BuuNVHwJ.js";
import "./alert-DiJsRLFO.js";
import "./index-W778R1kW.js";
const __iconNode$2 = [
  ["circle", { cx: "12", cy: "5", r: "1", key: "gxeob9" }],
  ["circle", { cx: "19", cy: "5", r: "1", key: "w8mnmm" }],
  ["circle", { cx: "5", cy: "5", r: "1", key: "lttvr7" }],
  ["circle", { cx: "12", cy: "12", r: "1", key: "41hilf" }],
  ["circle", { cx: "19", cy: "12", r: "1", key: "1wjl8i" }],
  ["circle", { cx: "5", cy: "12", r: "1", key: "1pcz8c" }],
  ["circle", { cx: "12", cy: "19", r: "1", key: "lyex9k" }],
  ["circle", { cx: "19", cy: "19", r: "1", key: "shf9b7" }],
  ["circle", { cx: "5", cy: "19", r: "1", key: "bfqh0e" }]
];
const Grip = createLucideIcon("grip", __iconNode$2);
const __iconNode$1 = [
  ["path", { d: "M5 12h14", key: "1ays0h" }],
  ["path", { d: "M12 5v14", key: "s699le" }]
];
const Plus = createLucideIcon("plus", __iconNode$1);
const __iconNode = [
  ["path", { d: "M3 6h18", key: "d0wm0j" }],
  ["path", { d: "M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6", key: "4alrt4" }],
  ["path", { d: "M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2", key: "v07s0e" }],
  ["line", { x1: "10", x2: "10", y1: "11", y2: "17", key: "1uufr5" }],
  ["line", { x1: "14", x2: "14", y1: "11", y2: "17", key: "xtxkd" }]
];
const Trash2 = createLucideIcon("trash-2", __iconNode);
const ConfirmAction = ({ children, onConfirm }) => {
  return /* @__PURE__ */ jsxRuntimeExports.jsxs(AlertDialog, { children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogTrigger, { asChild: true, children }),
    /* @__PURE__ */ jsxRuntimeExports.jsxs(AlertDialogContent, { children: [
      /* @__PURE__ */ jsxRuntimeExports.jsxs(AlertDialogHeader, { children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogTitle, { className: "!m-0", children: __("Are you absolutely sure?", "wp-sms") }),
        /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogDescription, { className: "!mt-1", children: __(
          "This action cannot be undone. This will permanently delete your account and remove your data from our servers.",
          "wp-sms"
        ) })
      ] }),
      /* @__PURE__ */ jsxRuntimeExports.jsxs(AlertDialogFooter, { children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogCancel, { children: __("Cancel", "wp-sms") }),
        /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogAction, { onClick: onConfirm, className: "bg-destructive hover:bg-destructive/90", children: __("Confirm", "wp-sms") })
      ] })
    ] })
  ] });
};
const layoutVariants = cva("", {
  variants: {
    layout: {
      "1-column": "grid grid-cols-1 gap-4",
      "2-column": "grid grid-cols-2 gap-4",
      "3-column": "grid grid-cols-3 gap-4",
      "4-column": "grid grid-cols-4 gap-4",
      "5-column": "grid grid-cols-5 gap-4",
      "6-column": "grid grid-cols-6 gap-4",
      "7-column": "grid grid-cols-7 gap-4",
      "8-column": "grid grid-cols-8 gap-4",
      "9-column": "grid grid-cols-9 gap-4",
      "10-column": "grid grid-cols-10 gap-4",
      "11-column": "grid grid-cols-11 gap-4",
      "12-column": "grid grid-cols-12 gap-4"
    }
  },
  defaultVariants: {
    layout: "2-column"
  }
});
const RepeaterField = ({ form, schema }) => {
  const field = useFieldContext();
  const errors = useStore(field.store, (state) => state.meta.errors);
  const fieldValue = field.state.value;
  const fieldsArray = reactExports.useMemo(() => {
    if (Array.isArray(fieldValue)) {
      if (fieldValue.every((item) => typeof item === "object" && item !== null)) {
        return fieldValue;
      }
    }
    return [];
  }, [fieldValue]);
  const formValues = useStore(form.baseStore, (state) => state.values);
  const layout = "2-column";
  const handleAddItem = reactExports.useCallback(() => {
    const firstItem = fieldsArray?.[0];
    const newFieldData = firstItem ? Object.fromEntries(Object.entries(firstItem).map(([key]) => [key, null])) : {};
    const newItem = { ...newFieldData, id: `item-${Date.now()}` };
    const newArray = [...fieldsArray, newItem];
    field.handleChange(newArray);
  }, [fieldsArray, field]);
  const handleRemoveItem = reactExports.useCallback(
    (idx) => {
      const newArray = fieldsArray.filter((_, index) => index !== idx);
      field.handleChange(newArray);
    },
    [fieldsArray, field]
  );
  reactExports.useCallback(
    (itemIndex, fieldKey, value) => {
      const newArray = [...fieldsArray];
      const currentItem = newArray[itemIndex];
      if (currentItem) {
        newArray[itemIndex] = { ...currentItem, [fieldKey]: value };
        field.handleChange(newArray);
      }
    },
    [fieldsArray, field]
  );
  return /* @__PURE__ */ jsxRuntimeExports.jsx(FieldWrapper, { errors, schema, children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex flex-col gap-y-4", children: [
    fieldsArray?.map((item, idx) => {
      return /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex flex-col gap-y-6 border border-border rounded-lg p-4", children: [
        /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex justify-between items-center", children: [
          /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex items-center gap-x-2", children: [
            /* @__PURE__ */ jsxRuntimeExports.jsx(Grip, { size: 20, className: "text-foreground" }),
            /* @__PURE__ */ jsxRuntimeExports.jsx("p", { className: "text-base font-medium text-foreground", children: sprintf(__("Item %s", "wp-sms"), idx + 1) })
          ] }),
          /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "flex items-center gap-x-2", children: /* @__PURE__ */ jsxRuntimeExports.jsx(ConfirmAction, { onConfirm: () => handleRemoveItem(idx), children: /* @__PURE__ */ jsxRuntimeExports.jsx(Button, { variant: "ghost", size: "icon", disabled: schema.readonly, children: /* @__PURE__ */ jsxRuntimeExports.jsx(Trash2, { className: "w-4 h-4" }) }) }) })
        ] }),
        schema.fieldGroups?.map((group) => {
          return /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: layoutVariants({ layout }), children: group?.fields?.map((subField) => {
            if (!shouldShowField(subField, formValues)) {
              return null;
            }
            return /* @__PURE__ */ jsxRuntimeExports.jsx(
              FieldRenderer,
              {
                form,
                schema: { ...subField, key: `${schema.key}.${idx}.${subField?.key}` }
              },
              `group-${group?.key}-field-${subField?.key}`
            );
          }) }, `${schema.key}-${item?.id || `item-${idx}`}`);
        })
      ] }, item?.id || `item-${idx}`);
    }),
    /* @__PURE__ */ jsxRuntimeExports.jsxs(
      Button,
      {
        onClick: handleAddItem,
        type: "button",
        variant: "outline",
        size: "sm",
        disabled: schema.readonly,
        className: "flex items-center justify-center gap-x-1 w-full",
        children: [
          /* @__PURE__ */ jsxRuntimeExports.jsx(Plus, { size: 18 }),
          /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: sprintf(__("Add %s", "wp-sms"), schema.fieldGroups?.[0]?.label || __("Item", "wp-sms")) })
        ]
      }
    )
  ] }) });
};
export {
  RepeaterField
};
