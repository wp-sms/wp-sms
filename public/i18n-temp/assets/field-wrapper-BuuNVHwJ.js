import { r as reactExports, j as jsxRuntimeExports, Z as createSlot, e as cn, N as toast, _ as __, $ as clsx } from "./main-D1KP0B5-.js";
import { T as TagBadge } from "./use-save-settings-values-BPUIfNdJ.js";
var NODES = [
  "a",
  "button",
  "div",
  "form",
  "h2",
  "h3",
  "img",
  "input",
  "label",
  "li",
  "nav",
  "ol",
  "p",
  "select",
  "span",
  "svg",
  "ul"
];
var Primitive = NODES.reduce((primitive, node) => {
  const Slot = createSlot(`Primitive.${node}`);
  const Node = reactExports.forwardRef((props, forwardedRef) => {
    const { asChild, ...primitiveProps } = props;
    const Comp = asChild ? Slot : node;
    if (typeof window !== "undefined") {
      window[Symbol.for("radix-ui")] = true;
    }
    return /* @__PURE__ */ jsxRuntimeExports.jsx(Comp, { ...primitiveProps, ref: forwardedRef });
  });
  Node.displayName = `Primitive.${node}`;
  return { ...primitive, [node]: Node };
}, {});
var NAME = "Label";
var Label$1 = reactExports.forwardRef((props, forwardedRef) => {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    Primitive.label,
    {
      ...props,
      ref: forwardedRef,
      onMouseDown: (event) => {
        const target = event.target;
        if (target.closest("button, input, select, textarea")) return;
        props.onMouseDown?.(event);
        if (!event.defaultPrevented && event.detail > 1) event.preventDefault();
      }
    }
  );
});
Label$1.displayName = NAME;
var Root = Label$1;
function Label({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    Root,
    {
      "data-slot": "label",
      className: cn(
        "flex items-center gap-2 text-sm leading-none font-medium select-none",
        "group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-70",
        "peer-disabled:cursor-not-allowed peer-disabled:opacity-70",
        className
      ),
      ...props
    }
  );
}
function useStableCallback(callback, dependencies) {
  const memoizedCallback = reactExports.useCallback(callback, dependencies);
  const stableCallbackRef = reactExports.useRef(memoizedCallback);
  reactExports.useEffect(() => {
    stableCallbackRef.current = memoizedCallback;
  }, [memoizedCallback]);
  return reactExports.useCallback((...args) => {
    return stableCallbackRef.current(...args);
  }, []);
}
const FieldDescription = ({ text }) => {
  const handleClick = useStableCallback(async (event) => {
    const target = event.target;
    if (target.tagName === "CODE") {
      const textToCopy = target.textContent || "";
      try {
        await navigator.clipboard.writeText(textToCopy);
        toast.success(__("Code snippet copied to clipboard", "wp-sms"));
      } catch {
      }
    }
    if (target.tagName === "A" && target.classList.contains("js-wpsms-chatbox-preview")) {
      event.preventDefault();
      if (window.toggleWpSmsChatbox) {
        window.toggleWpSmsChatbox();
      }
    }
  }, []);
  if (!text) {
    return null;
  }
  const hasHtmlTags = /<[^>]*>/g.test(text);
  if (hasHtmlTags) {
    return /* @__PURE__ */ jsxRuntimeExports.jsx(
      "div",
      {
        className: "text-xs text-muted-foreground [&_code]:cursor-pointer !m-0",
        dangerouslySetInnerHTML: { __html: text },
        onClick: handleClick
      }
    );
  }
  return /* @__PURE__ */ jsxRuntimeExports.jsx("p", { className: "text-xs text-muted-foreground !m-0", children: text });
};
const FieldWrapper = ({
  schema,
  errors,
  children
}) => {
  return /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: clsx("flex flex-col gap-1.5", schema.readonly && "opacity-70"), children: [
    /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex items-center gap-2", children: [
      schema.type !== "checkbox" && /* @__PURE__ */ jsxRuntimeExports.jsx(Label, { className: clsx(!!errors.length && "text-destructive"), htmlFor: schema.key, children: schema.label }),
      schema.tag && schema.type !== "checkbox" && /* @__PURE__ */ jsxRuntimeExports.jsx(TagBadge, { tag: schema.tag })
    ] }),
    /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex gap-1.5 flex-col", children: [
      /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: clsx(schema.readonly && "pointer-events-none", schema.type === "checkbox" && "flex gap-2"), children: [
        children,
        schema.type === "checkbox" && /* @__PURE__ */ jsxRuntimeExports.jsxs(Label, { className: clsx(!!errors.length && "text-destructive"), htmlFor: schema.key, children: [
          schema.label,
          schema.tag && schema.type === "checkbox" && /* @__PURE__ */ jsxRuntimeExports.jsx(TagBadge, { tag: schema.tag })
        ] })
      ] }),
      /* @__PURE__ */ jsxRuntimeExports.jsx(FieldDescription, { text: schema.description }),
      !!errors.length && /* @__PURE__ */ jsxRuntimeExports.jsx("p", { className: "text-xs font-normal text-destructive", children: errors.join(". ") })
    ] })
  ] });
};
export {
  FieldWrapper as F,
  Primitive as P
};
