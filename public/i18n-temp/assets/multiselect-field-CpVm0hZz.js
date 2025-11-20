import { c as createLucideIcon, r as reactExports, j as jsxRuntimeExports, e as cn, g as cva, _ as __ } from "./main-D1KP0B5-.js";
import { B as Badge, j as useFieldContext, k as useStore } from "./use-save-settings-values-BPUIfNdJ.js";
import { s as sprintf } from "./sprintf-DmNrJSYG.js";
import { P as Popover, a as PopoverTrigger, b as PopoverContent, C as Command, c as CommandInput, d as CommandList, e as CommandEmpty, f as CommandGroup, g as CommandItem, h as CommandSeparator } from "./popover-L9ibBOwi.js";
import { S as Separator } from "./separator-BSI9ZTwI.js";
import { X } from "./x-B6KD4r7q.js";
import { C as ChevronDown, t as toOptions } from "./to-options-DMnkbffj.js";
import { C as Check } from "./check-CTl8VTxS.js";
import { F as FieldWrapper } from "./field-wrapper-BuuNVHwJ.js";
import "./alert-DiJsRLFO.js";
import "./index-W778R1kW.js";
import "./index-DNbzpgrM.js";
import "./index-Cit5KdOq.js";
const __iconNode$1 = [
  ["circle", { cx: "12", cy: "12", r: "10", key: "1mglay" }],
  ["path", { d: "m15 9-6 6", key: "1uzhvr" }],
  ["path", { d: "m9 9 6 6", key: "z0biqf" }]
];
const CircleX = createLucideIcon("circle-x", __iconNode$1);
const __iconNode = [
  [
    "path",
    {
      d: "m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72",
      key: "ul74o6"
    }
  ],
  ["path", { d: "m14 7 3 3", key: "1r5n42" }],
  ["path", { d: "M5 6v4", key: "ilb8ba" }],
  ["path", { d: "M19 14v4", key: "blhpug" }],
  ["path", { d: "M10 2v2", key: "7u0qdc" }],
  ["path", { d: "M7 8H3", key: "zfb6yr" }],
  ["path", { d: "M21 16h-4", key: "1cnmox" }],
  ["path", { d: "M11 3H9", key: "1obp7u" }]
];
const WandSparkles = createLucideIcon("wand-sparkles", __iconNode);
const multiSelectVariants = cva("m-1 ", {
  variants: {
    variant: {
      default: "border-foreground/10 text-foreground bg-card hover:bg-card/80",
      secondary: "border-foreground/10 bg-secondary text-secondary-foreground hover:bg-secondary/80",
      destructive: "border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80",
      inverted: "inverted"
    }
  },
  defaultVariants: {
    variant: "default"
  }
});
const MultiSelect = ({
  options,
  onValueChange,
  variant,
  defaultValue = [],
  placeholder = __("Select options", "wp-sms"),
  animation = 0,
  maxCount = 3,
  modalPopover = false,
  className
}) => {
  const [selectedValues, setSelectedValues] = reactExports.useState(defaultValue);
  const [isPopoverOpen, setIsPopoverOpen] = reactExports.useState(false);
  const [isAnimating, setIsAnimating] = reactExports.useState(false);
  const handleInputKeyDown = (event) => {
    if (event.key === "Enter") {
      setIsPopoverOpen(true);
    } else if (event.key === "Backspace" && !event.currentTarget.value) {
      const newSelectedValues = [...selectedValues];
      newSelectedValues.pop();
      setSelectedValues(newSelectedValues);
      onValueChange(newSelectedValues);
    }
  };
  const toggleOption = (option) => {
    const newSelectedValues = selectedValues.includes(option) ? selectedValues.filter((value) => value !== option) : [...selectedValues, option];
    setSelectedValues(newSelectedValues);
    onValueChange(newSelectedValues);
  };
  const handleClear = () => {
    setSelectedValues([]);
    onValueChange([]);
  };
  const handleTogglePopover = () => {
    setIsPopoverOpen((prev) => !prev);
  };
  const clearExtraOptions = () => {
    const newSelectedValues = selectedValues.slice(0, maxCount);
    setSelectedValues(newSelectedValues);
    onValueChange(newSelectedValues);
  };
  const toggleAll = () => {
    if (selectedValues.length === options.length) {
      handleClear();
    } else {
      const allValues = options.map((option) => option.value);
      setSelectedValues(allValues);
      onValueChange(allValues);
    }
  };
  return /* @__PURE__ */ jsxRuntimeExports.jsxs(Popover, { open: isPopoverOpen, onOpenChange: setIsPopoverOpen, modal: modalPopover, children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(PopoverTrigger, { asChild: true, children: /* @__PURE__ */ jsxRuntimeExports.jsx(
      "div",
      {
        onClick: handleTogglePopover,
        className: cn(
          "flex w-full p-1 rounded-md border border-input min-h-9 h-auto items-center justify-between bg-inherit hover:bg-inherit [&_svg]:pointer-events-auto",
          className
        ),
        children: selectedValues.length > 0 ? /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex justify-between items-center w-full", children: [
          /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex flex-wrap items-center", children: [
            selectedValues.slice(0, maxCount).map((value) => {
              const option = options.find((o) => o.value === value);
              const IconComponent = option?.icon;
              return /* @__PURE__ */ jsxRuntimeExports.jsxs(
                Badge,
                {
                  className: cn(isAnimating ? "animate-bounce" : "", multiSelectVariants({ variant })),
                  style: { animationDuration: `${animation}s` },
                  children: [
                    IconComponent && /* @__PURE__ */ jsxRuntimeExports.jsx(IconComponent, { className: "h-4 w-4 mr-2" }),
                    option?.label,
                    /* @__PURE__ */ jsxRuntimeExports.jsx(
                      "div",
                      {
                        className: "cursor-pointer",
                        onClick: () => {
                          toggleOption(value);
                        },
                        children: /* @__PURE__ */ jsxRuntimeExports.jsx(CircleX, { className: "ml-2 h-4 w-4 " })
                      }
                    )
                  ]
                },
                value
              );
            }),
            selectedValues.length > maxCount && /* @__PURE__ */ jsxRuntimeExports.jsxs(
              Badge,
              {
                className: cn(
                  "bg-transparent text-foreground border-foreground/1 hover:bg-transparent",
                  isAnimating ? "animate-bounce" : "",
                  multiSelectVariants({ variant })
                ),
                style: { animationDuration: `${animation}s` },
                children: [
                  sprintf(__("+ %s more", "wp-sms"), selectedValues.length - maxCount),
                  /* @__PURE__ */ jsxRuntimeExports.jsx(
                    CircleX,
                    {
                      className: "ml-2 h-4 w-4 cursor-pointer",
                      onClick: (event) => {
                        event.stopPropagation();
                        clearExtraOptions();
                      }
                    }
                  )
                ]
              }
            )
          ] }),
          /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex items-center justify-between", children: [
            /* @__PURE__ */ jsxRuntimeExports.jsx(
              X,
              {
                className: "h-4 mx-2 cursor-pointer text-muted-foreground",
                onClick: (event) => {
                  event.stopPropagation();
                  handleClear();
                }
              }
            ),
            /* @__PURE__ */ jsxRuntimeExports.jsx(Separator, { orientation: "vertical", className: "flex min-h-6 h-full" }),
            /* @__PURE__ */ jsxRuntimeExports.jsx(ChevronDown, { className: "h-4 mx-2 cursor-pointer text-muted-foreground" })
          ] })
        ] }) : /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex items-center justify-between w-full mx-auto", children: [
          /* @__PURE__ */ jsxRuntimeExports.jsx("span", { className: "text-sm text-muted-foreground mx-3", children: placeholder }),
          /* @__PURE__ */ jsxRuntimeExports.jsx(ChevronDown, { className: "h-4 cursor-pointer text-muted-foreground mx-2" })
        ] })
      }
    ) }),
    /* @__PURE__ */ jsxRuntimeExports.jsx(PopoverContent, { className: "w-auto p-0 z-[200]", align: "start", onEscapeKeyDown: () => setIsPopoverOpen(false), children: /* @__PURE__ */ jsxRuntimeExports.jsxs(Command, { children: [
      /* @__PURE__ */ jsxRuntimeExports.jsx(CommandInput, { placeholder: __("Search...", "wp-sms"), onKeyDown: handleInputKeyDown }),
      /* @__PURE__ */ jsxRuntimeExports.jsxs(CommandList, { children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx(CommandEmpty, { children: __("No results found.", "wp-sms") }),
        /* @__PURE__ */ jsxRuntimeExports.jsxs(CommandGroup, { children: [
          /* @__PURE__ */ jsxRuntimeExports.jsxs(CommandItem, { onSelect: toggleAll, className: "cursor-pointer", children: [
            /* @__PURE__ */ jsxRuntimeExports.jsx(
              "div",
              {
                className: cn(
                  "mr-2 flex h-4 w-4 items-center justify-center rounded-sm border border-primary",
                  selectedValues.length === options.length ? "bg-primary text-primary-foreground" : "opacity-50 [&_svg]:invisible"
                ),
                children: /* @__PURE__ */ jsxRuntimeExports.jsx(Check, { className: "h-4 w-4" })
              }
            ),
            /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: __("Select All", "wp-sms") })
          ] }, "all"),
          options.map((option) => {
            const isSelected = selectedValues.includes(option.value);
            return /* @__PURE__ */ jsxRuntimeExports.jsxs(
              CommandItem,
              {
                onSelect: () => toggleOption(option.value),
                className: "cursor-pointer",
                children: [
                  /* @__PURE__ */ jsxRuntimeExports.jsx(
                    "div",
                    {
                      className: cn(
                        "mr-2 flex h-4 w-4 items-center justify-center rounded-sm border border-primary",
                        isSelected ? "bg-primary text-primary-foreground" : "opacity-50 [&_svg]:invisible"
                      ),
                      children: /* @__PURE__ */ jsxRuntimeExports.jsx(Check, { className: "h-4 w-4" })
                    }
                  ),
                  option.icon && /* @__PURE__ */ jsxRuntimeExports.jsx(option.icon, { className: "mr-2 h-4 w-4 text-muted-foreground" }),
                  /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: option.label })
                ]
              },
              option.value
            );
          })
        ] }),
        /* @__PURE__ */ jsxRuntimeExports.jsx(CommandSeparator, {}),
        /* @__PURE__ */ jsxRuntimeExports.jsx(CommandGroup, { children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex items-center justify-between", children: [
          selectedValues.length > 0 && /* @__PURE__ */ jsxRuntimeExports.jsxs(jsxRuntimeExports.Fragment, { children: [
            /* @__PURE__ */ jsxRuntimeExports.jsx(CommandItem, { onSelect: handleClear, className: "flex-1 justify-center cursor-pointer", children: __("Clear", "wp-sms") }),
            /* @__PURE__ */ jsxRuntimeExports.jsx(Separator, { orientation: "vertical", className: "flex min-h-6 h-full" })
          ] }),
          /* @__PURE__ */ jsxRuntimeExports.jsx(
            CommandItem,
            {
              onSelect: () => setIsPopoverOpen(false),
              className: "flex-1 justify-center cursor-pointer max-w-full",
              children: __("Close", "wp-sms")
            }
          )
        ] }) })
      ] })
    ] }) }),
    animation > 0 && selectedValues.length > 0 && /* @__PURE__ */ jsxRuntimeExports.jsx(
      WandSparkles,
      {
        className: cn(
          "cursor-pointer my-2 text-foreground bg-background w-3 h-3",
          isAnimating ? "" : "text-muted-foreground"
        ),
        onClick: () => setIsAnimating(!isAnimating)
      }
    )
  ] });
};
MultiSelect.displayName = "MultiSelect";
const MultiselectField = ({ schema }) => {
  const field = useFieldContext();
  const errors = useStore(field.store, (state) => state.meta.errors);
  const fieldValue = field.state.value;
  const selectedValues = Array.isArray(fieldValue) && fieldValue.every((item) => typeof item === "string") ? fieldValue : [];
  return /* @__PURE__ */ jsxRuntimeExports.jsx(FieldWrapper, { schema, errors, children: /* @__PURE__ */ jsxRuntimeExports.jsx(
    MultiSelect,
    {
      "aria-invalid": !!errors.length,
      defaultValue: selectedValues ?? [],
      options: toOptions(schema.options) ?? [],
      onValueChange: field.handleChange,
      onBlur: field.handleBlur
    }
  ) });
};
export {
  MultiselectField
};
