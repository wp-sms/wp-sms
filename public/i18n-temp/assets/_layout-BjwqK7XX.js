import { c as createLucideIcon, r as reactExports, j as jsxRuntimeExports, u as useComposedRefs, a as useWordPressService, b as useLocation, _ as __, B as Button, L as Link, S as Settings, O as Outlet } from "./main-D1KP0B5-.js";
import { s as sprintf } from "./sprintf-DmNrJSYG.js";
import { u as useSidebar, S as Sidebar, a as SidebarHeader, b as SidebarMenu, c as SidebarMenuItem, M as MessageSquare, P as PanelLeft, d as PanelLeftClose, e as SidebarContent, f as SidebarGroup, g as SidebarGroupLabel, h as SidebarGroupContent, i as SidebarMenuButton, R as RenderIcon, j as SidebarMenuSub, k as SidebarMenuSubItem, l as SidebarMenuSubButton, m as SidebarFooter, T as ThemeToggle, n as SidebarProvider, o as SidebarInset } from "./sidebar-provider-C3fqDUjy.js";
import { u as useControllableState, c as createContextScope, a as useId, P as Primitive, b as composeEventHandlers, d as Presence, e as useLayoutEffect2 } from "./index-W778R1kW.js";
import "./input-DTxI4jvI.js";
import "./x-B6KD4r7q.js";
import "./index-DNbzpgrM.js";
import "./index-Cit5KdOq.js";
const __iconNode = [["path", { d: "m9 18 6-6-6-6", key: "mthhwq" }]];
const ChevronRight = createLucideIcon("chevron-right", __iconNode);
var COLLAPSIBLE_NAME = "Collapsible";
var [createCollapsibleContext] = createContextScope(COLLAPSIBLE_NAME);
var [CollapsibleProvider, useCollapsibleContext] = createCollapsibleContext(COLLAPSIBLE_NAME);
var Collapsible$1 = reactExports.forwardRef(
  (props, forwardedRef) => {
    const {
      __scopeCollapsible,
      open: openProp,
      defaultOpen,
      disabled,
      onOpenChange,
      ...collapsibleProps
    } = props;
    const [open, setOpen] = useControllableState({
      prop: openProp,
      defaultProp: defaultOpen ?? false,
      onChange: onOpenChange,
      caller: COLLAPSIBLE_NAME
    });
    return /* @__PURE__ */ jsxRuntimeExports.jsx(
      CollapsibleProvider,
      {
        scope: __scopeCollapsible,
        disabled,
        contentId: useId(),
        open,
        onOpenToggle: reactExports.useCallback(() => setOpen((prevOpen) => !prevOpen), [setOpen]),
        children: /* @__PURE__ */ jsxRuntimeExports.jsx(
          Primitive.div,
          {
            "data-state": getState(open),
            "data-disabled": disabled ? "" : void 0,
            ...collapsibleProps,
            ref: forwardedRef
          }
        )
      }
    );
  }
);
Collapsible$1.displayName = COLLAPSIBLE_NAME;
var TRIGGER_NAME = "CollapsibleTrigger";
var CollapsibleTrigger$1 = reactExports.forwardRef(
  (props, forwardedRef) => {
    const { __scopeCollapsible, ...triggerProps } = props;
    const context = useCollapsibleContext(TRIGGER_NAME, __scopeCollapsible);
    return /* @__PURE__ */ jsxRuntimeExports.jsx(
      Primitive.button,
      {
        type: "button",
        "aria-controls": context.contentId,
        "aria-expanded": context.open || false,
        "data-state": getState(context.open),
        "data-disabled": context.disabled ? "" : void 0,
        disabled: context.disabled,
        ...triggerProps,
        ref: forwardedRef,
        onClick: composeEventHandlers(props.onClick, context.onOpenToggle)
      }
    );
  }
);
CollapsibleTrigger$1.displayName = TRIGGER_NAME;
var CONTENT_NAME = "CollapsibleContent";
var CollapsibleContent$1 = reactExports.forwardRef(
  (props, forwardedRef) => {
    const { forceMount, ...contentProps } = props;
    const context = useCollapsibleContext(CONTENT_NAME, props.__scopeCollapsible);
    return /* @__PURE__ */ jsxRuntimeExports.jsx(Presence, { present: forceMount || context.open, children: ({ present }) => /* @__PURE__ */ jsxRuntimeExports.jsx(CollapsibleContentImpl, { ...contentProps, ref: forwardedRef, present }) });
  }
);
CollapsibleContent$1.displayName = CONTENT_NAME;
var CollapsibleContentImpl = reactExports.forwardRef((props, forwardedRef) => {
  const { __scopeCollapsible, present, children, ...contentProps } = props;
  const context = useCollapsibleContext(CONTENT_NAME, __scopeCollapsible);
  const [isPresent, setIsPresent] = reactExports.useState(present);
  const ref = reactExports.useRef(null);
  const composedRefs = useComposedRefs(forwardedRef, ref);
  const heightRef = reactExports.useRef(0);
  const height = heightRef.current;
  const widthRef = reactExports.useRef(0);
  const width = widthRef.current;
  const isOpen = context.open || isPresent;
  const isMountAnimationPreventedRef = reactExports.useRef(isOpen);
  const originalStylesRef = reactExports.useRef(void 0);
  reactExports.useEffect(() => {
    const rAF = requestAnimationFrame(() => isMountAnimationPreventedRef.current = false);
    return () => cancelAnimationFrame(rAF);
  }, []);
  useLayoutEffect2(() => {
    const node = ref.current;
    if (node) {
      originalStylesRef.current = originalStylesRef.current || {
        transitionDuration: node.style.transitionDuration,
        animationName: node.style.animationName
      };
      node.style.transitionDuration = "0s";
      node.style.animationName = "none";
      const rect = node.getBoundingClientRect();
      heightRef.current = rect.height;
      widthRef.current = rect.width;
      if (!isMountAnimationPreventedRef.current) {
        node.style.transitionDuration = originalStylesRef.current.transitionDuration;
        node.style.animationName = originalStylesRef.current.animationName;
      }
      setIsPresent(present);
    }
  }, [context.open, present]);
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    Primitive.div,
    {
      "data-state": getState(context.open),
      "data-disabled": context.disabled ? "" : void 0,
      id: context.contentId,
      hidden: !isOpen,
      ...contentProps,
      ref: composedRefs,
      style: {
        [`--radix-collapsible-content-height`]: height ? `${height}px` : void 0,
        [`--radix-collapsible-content-width`]: width ? `${width}px` : void 0,
        ...props.style
      },
      children: isOpen && children
    }
  );
});
function getState(open) {
  return open ? "open" : "closed";
}
var Root = Collapsible$1;
function Collapsible({ ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(Root, { "data-slot": "collapsible", ...props });
}
function CollapsibleTrigger({ ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(CollapsibleTrigger$1, { "data-slot": "collapsible-trigger", ...props });
}
function CollapsibleContent({ ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(CollapsibleContent$1, { "data-slot": "collapsible-content", ...props });
}
function SettingsSidebar() {
  const {
    layout: { sidebar },
    globals: { pluginVersion }
  } = useWordPressService();
  const location = useLocation();
  const currentPath = location.pathname;
  const { state, toggleSidebar } = useSidebar();
  const pluginVersionLabel = sprintf(__("Plugin Version %s", "wp-sms"), pluginVersion);
  return /* @__PURE__ */ jsxRuntimeExports.jsxs(Sidebar, { variant: "sidebar", collapsible: "icon", className: "border-r border-border", children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarHeader, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenu, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenuItem, { children: state === "collapsed" ? /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "flex justify-center px-2", children: /* @__PURE__ */ jsxRuntimeExports.jsxs(
      "button",
      {
        onClick: toggleSidebar,
        className: "group flex aspect-square size-10 items-center justify-center rounded-lg bg-gradient-primary text-sidebar-primary-foreground hover:bg-gradient-primary/80 transition-all duration-200 cursor-pointer",
        title: __("Expand sidebar", "wp-sms"),
        children: [
          /* @__PURE__ */ jsxRuntimeExports.jsx(MessageSquare, { className: "size-5 group-hover:hidden" }),
          /* @__PURE__ */ jsxRuntimeExports.jsx(PanelLeft, { className: "size-5 hidden group-hover:block" })
        ]
      }
    ) }) : /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex items-center gap-2 px-2", children: [
      /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex items-center gap-2 flex-1", children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "flex aspect-square size-8 items-center justify-center rounded-lg bg-gradient-primary text-sidebar-primary-foreground", children: /* @__PURE__ */ jsxRuntimeExports.jsx(MessageSquare, { className: "size-4" }) }),
        /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "grid flex-1 text-start text-sm leading-tight", children: [
          /* @__PURE__ */ jsxRuntimeExports.jsx("span", { className: "truncate font-semibold", children: "WP SMS Plugin" }),
          /* @__PURE__ */ jsxRuntimeExports.jsx("span", { className: "truncate text-xs", children: __("Settings Dashboard", "wp-sms") })
        ] })
      ] }),
      /* @__PURE__ */ jsxRuntimeExports.jsxs(
        Button,
        {
          variant: "ghost",
          size: "sm",
          onClick: toggleSidebar,
          className: "h-8 w-8 p-0 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground rtl:[&>svg]:scale-x-[-1]",
          children: [
            /* @__PURE__ */ jsxRuntimeExports.jsx(PanelLeftClose, { className: "h-4 w-4" }),
            /* @__PURE__ */ jsxRuntimeExports.jsx("span", { className: "sr-only", children: __("Toggle Sidebar", "wp-sms") })
          ]
        }
      )
    ] }) }) }) }),
    /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarContent, { children: [
      sidebar?.core && /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarGroup, { children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarGroupLabel, { children: __("Core Settings", "wp-sms") }),
        /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarGroupContent, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenu, { children: Object.entries(sidebar?.core ?? {})?.map(([_, item]) => /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenuItem, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(
          SidebarMenuButton,
          {
            asChild: true,
            isActive: currentPath === `/settings/${item.name}`,
            tooltip: item.label,
            className: "data-[active=true]:bg-sidebar-active data-[active=true]:text-sidebar-active-foreground hover:data-[active=true]:bg-sidebar-active hover:data-[active=true]:text-sidebar-active-foreground transition-colors no-underline",
            children: /* @__PURE__ */ jsxRuntimeExports.jsxs(Link, { to: "/settings/$name", params: { name: item.name }, children: [
              /* @__PURE__ */ jsxRuntimeExports.jsx(RenderIcon, { iconName: item.icon }),
              /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: item.label })
            ] })
          }
        ) }, `core-item-${item?.name}-${item.label}`)) }) })
      ] }),
      sidebar?.addons && /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarGroup, { children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarGroupLabel, { children: __("Addons", "wp-sms") }),
        /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarGroupContent, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenu, { children: Object.entries(sidebar?.addons ?? {})?.map(([_, item]) => /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenuItem, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(
          SidebarMenuButton,
          {
            asChild: true,
            isActive: currentPath === `/settings/${item.name}`,
            tooltip: item.label,
            className: "data-[active=true]:bg-sidebar-active data-[active=true]:text-sidebar-active-foreground hover:data-[active=true]:bg-sidebar-active hover:data-[active=true]:text-sidebar-active-foreground transition-colors no-underline",
            children: /* @__PURE__ */ jsxRuntimeExports.jsxs(Link, { to: "/settings/$name", params: { name: item.name }, children: [
              /* @__PURE__ */ jsxRuntimeExports.jsx(RenderIcon, { iconName: item.icon }),
              /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: item.label })
            ] })
          }
        ) }, `addon-item-${item?.name}-${item.label}`)) }) })
      ] }),
      sidebar?.integrations && state !== "collapsed" && /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarGroup, { children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarGroupLabel, { children: __("Integrations", "wp-sms") }),
        /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarGroupContent, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenu, { children: Object.entries(sidebar?.integrations.children ?? {})?.map(([key, item]) => {
          return /* @__PURE__ */ jsxRuntimeExports.jsx(
            Collapsible,
            {
              asChild: true,
              defaultOpen: Object.entries(item?.children ?? {}).some(([_, subItem]) => {
                return currentPath === `/settings/${subItem?.name}`;
              }),
              className: "group/collapsible",
              children: /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarMenuItem, { children: [
                /* @__PURE__ */ jsxRuntimeExports.jsx(CollapsibleTrigger, { asChild: true, children: /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarMenuButton, { tooltip: item.label, children: [
                  /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: item.label }),
                  /* @__PURE__ */ jsxRuntimeExports.jsx(ChevronRight, { className: "ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 rtl:rotate-180" })
                ] }) }),
                /* @__PURE__ */ jsxRuntimeExports.jsx(CollapsibleContent, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenuSub, { children: Object.entries(item.children ?? {})?.map(([_, subItem]) => /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenuSubItem, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(
                  SidebarMenuSubButton,
                  {
                    asChild: true,
                    isActive: currentPath === `/settings/${subItem.name}`,
                    className: "data-[active=true]:bg-sidebar-active data-[active=true]:text-sidebar-active-foreground hover:data-[active=true]:bg-sidebar-active hover:data-[active=true]:text-sidebar-active-foreground transition-colors no-underline",
                    children: /* @__PURE__ */ jsxRuntimeExports.jsx(Link, { to: "/settings/$name", params: { name: subItem.name }, children: /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: subItem.label }) })
                  }
                ) }, `nested-item-${item.label}-${subItem?.name}`)) }) })
              ] })
            },
            `integrations-item-${item?.label}-${key}`
          );
        }) }) })
      ] })
    ] }),
    /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarFooter, { className: "sticky bottom-0 bg-sidebar", children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenu, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenuItem, { children: state === "collapsed" ? /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex flex-col items-center gap-2", children: [
      /* @__PURE__ */ jsxRuntimeExports.jsx(ThemeToggle, {}),
      /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarMenuButton, { tooltip: pluginVersionLabel, children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx(Settings, {}),
        /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: pluginVersionLabel })
      ] })
    ] }) : /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex items-center justify-between w-full", children: [
      /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarMenuButton, { tooltip: void 0, children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx(Settings, {}),
        /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: pluginVersionLabel })
      ] }),
      /* @__PURE__ */ jsxRuntimeExports.jsx(ThemeToggle, {})
    ] }) }) }) })
  ] });
}
function RouteComponent() {
  return /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "wrap flex w-full min-h-screen relative", children: /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarProvider, { children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(SettingsSidebar, {}),
    /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarInset, { children: /* @__PURE__ */ jsxRuntimeExports.jsx("main", { className: "p-6 flex-1 ", children: /* @__PURE__ */ jsxRuntimeExports.jsx(Outlet, {}) }) })
  ] }) });
}
export {
  RouteComponent as component
};
