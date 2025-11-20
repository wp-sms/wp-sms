import { r as reactExports, j as jsxRuntimeExports, B as Button, _ as __, e as cn } from "./main-D1KP0B5-.js";
import { j as useFieldContext, k as useStore } from "./use-save-settings-values-BPUIfNdJ.js";
import { P as Popover, a as PopoverTrigger, b as PopoverContent, C as Command, c as CommandInput, d as CommandList, e as CommandEmpty, f as CommandGroup, g as CommandItem } from "./popover-L9ibBOwi.js";
import { C as ChevronDown, t as toOptions } from "./to-options-DMnkbffj.js";
import { C as Check } from "./check-CTl8VTxS.js";
import { F as FieldWrapper } from "./field-wrapper-BuuNVHwJ.js";
import "./alert-DiJsRLFO.js";
import "./sprintf-DmNrJSYG.js";
import "./index-W778R1kW.js";
import "./index-DNbzpgrM.js";
import "./index-Cit5KdOq.js";
function Combobox({
  options,
  value: controlledValue,
  onValueChange,
  placeholder = __("Select option...", "wp-sms"),
  searchPlaceholder = __("Search...", "wp-sms"),
  emptyMessage = __("No option found.", "wp-sms"),
  buttonClassName,
  contentClassName,
  disabled = false
}) {
  const [open, setOpen] = reactExports.useState(false);
  const getFirstOptionValue = () => {
    if (options.length === 0) return "";
    const firstOption = options[0];
    if (firstOption.children && firstOption.children.length > 0) {
      return firstOption.children[0].value;
    }
    return firstOption.value;
  };
  const [internalValue, setInternalValue] = reactExports.useState(getFirstOptionValue());
  const commandListRef = reactExports.useRef(null);
  const isControlled = controlledValue !== void 0;
  const value = isControlled ? controlledValue || getFirstOptionValue() : internalValue;
  const handleValueChange = (newValue) => {
    if (!isControlled) {
      setInternalValue(newValue);
    }
    onValueChange?.(newValue);
  };
  const findSelectedLabel = () => {
    for (const option of options) {
      if (option.value === value) {
        return option.label;
      }
      if (option.children) {
        const child = option.children.find((child2) => child2.value === value);
        if (child) {
          return child.label;
        }
      }
    }
    return null;
  };
  reactExports.useEffect(() => {
    if (open && value) {
      const scrollToSelected = () => {
        const listElement = commandListRef.current;
        if (!listElement) return;
        const selectedItem = listElement.querySelector(`[data-value="${value}"]`) || listElement.querySelector(`[data-combobox-value="${value}"]`) || listElement.querySelector(`[cmdk-item][data-value="${value}"]`);
        if (selectedItem) {
          selectedItem.scrollIntoView({
            behavior: "smooth",
            block: "center",
            inline: "nearest"
          });
        }
      };
      requestAnimationFrame(() => {
        scrollToSelected();
        setTimeout(scrollToSelected, 100);
      });
    }
  }, [open, value]);
  return /* @__PURE__ */ jsxRuntimeExports.jsx("div", { children: /* @__PURE__ */ jsxRuntimeExports.jsxs(Popover, { open, onOpenChange: setOpen, children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(PopoverTrigger, { asChild: true, children: /* @__PURE__ */ jsxRuntimeExports.jsxs(
      Button,
      {
        variant: "outline",
        role: "combobox",
        "aria-expanded": open,
        disabled,
        className: cn(
          "border-input cursor-pointer data-[placeholder]:text-muted-foreground [&_svg:not([class*='text-'])]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 flex items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 data-[size=default]:h-9 data-[size=sm]:h-8 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 w-full",
          buttonClassName
        ),
        children: [
          value ? findSelectedLabel() : placeholder,
          /* @__PURE__ */ jsxRuntimeExports.jsx(ChevronDown, { className: "ml-auto size-4 shrink-0 opacity-50" })
        ]
      }
    ) }),
    /* @__PURE__ */ jsxRuntimeExports.jsx(PopoverContent, { align: "start", className: cn("w-full p-0", contentClassName), children: /* @__PURE__ */ jsxRuntimeExports.jsxs(Command, { defaultValue: value, children: [
      options.length > 10 && /* @__PURE__ */ jsxRuntimeExports.jsx(CommandInput, { placeholder: searchPlaceholder }),
      /* @__PURE__ */ jsxRuntimeExports.jsxs(CommandList, { ref: commandListRef, children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx(CommandEmpty, { children: emptyMessage }),
        options.map((option, index) => {
          if (option.children) {
            return /* @__PURE__ */ jsxRuntimeExports.jsx(CommandGroup, { heading: option.label, children: option.children.map((child, j) => /* @__PURE__ */ jsxRuntimeExports.jsxs(
              CommandItem,
              {
                value: child.value,
                "data-combobox-value": child.value,
                onSelect: (currentValue) => {
                  const newValue = currentValue === value ? "" : currentValue;
                  handleValueChange(newValue);
                  setOpen(false);
                },
                children: [
                  /* @__PURE__ */ jsxRuntimeExports.jsx(
                    Check,
                    {
                      className: cn("mr-2 h-4 w-4", value === child.value ? "opacity-100" : "opacity-0")
                    }
                  ),
                  child.label
                ]
              },
              `group-combobox-item-${child.value}-${j}`
            )) }, `combobox-group-${option.value}-${index}`);
          }
          return /* @__PURE__ */ jsxRuntimeExports.jsx(CommandGroup, { children: /* @__PURE__ */ jsxRuntimeExports.jsxs(
            CommandItem,
            {
              value: option.value,
              "data-combobox-value": option.value,
              onSelect: (currentValue) => {
                const newValue = currentValue === value ? "" : currentValue;
                handleValueChange(newValue);
                setOpen(false);
              },
              children: [
                /* @__PURE__ */ jsxRuntimeExports.jsx(Check, { className: cn("mr-2 h-4 w-4", value === option.value ? "opacity-100" : "opacity-0") }),
                option.label
              ]
            },
            `combobox-item-${option.value}-${index}`
          ) }, `combobox-group-${index}`);
        })
      ] })
    ] }) })
  ] }) });
}
const SelectField = ({ schema }) => {
  const field = useFieldContext();
  const errors = useStore(field.store, (state) => state.meta.errors);
  return /* @__PURE__ */ jsxRuntimeExports.jsx(FieldWrapper, { schema, errors, children: /* @__PURE__ */ jsxRuntimeExports.jsx(
    Combobox,
    {
      value: field.state.value || String(schema.default),
      onValueChange: (value) => field.handleChange(value),
      options: toOptions(schema.options),
      disabled: schema.readonly,
      placeholder: schema.placeholder
    }
  ) });
};
export {
  SelectField
};
