import { b as useLocation, _ as __, j as jsxRuntimeExports, B as Button, L as Link, S as Settings, O as Outlet } from "./main-D1KP0B5-.js";
import { s as sprintf } from "./sprintf-DmNrJSYG.js";
import { u as useSidebar, S as Sidebar, a as SidebarHeader, b as SidebarMenu, c as SidebarMenuItem, M as MessageSquare, P as PanelLeft, d as PanelLeftClose, e as SidebarContent, f as SidebarGroup, g as SidebarGroupLabel, h as SidebarGroupContent, i as SidebarMenuButton, R as RenderIcon, m as SidebarFooter, T as ThemeToggle, n as SidebarProvider, p as SidebarInput } from "./sidebar-provider-C3fqDUjy.js";
import "./input-DTxI4jvI.js";
import "./index-W778R1kW.js";
import "./x-B6KD4r7q.js";
import "./index-DNbzpgrM.js";
import "./index-Cit5KdOq.js";
const OTPSidebar = () => {
  const location = useLocation();
  const currentPath = location.pathname;
  const { state, toggleSidebar } = useSidebar();
  const pluginVersion = 7.2;
  const menuItems = [
    {
      key: "otp-activity",
      href: "/otp/activity",
      icon: "Activity",
      title: __("Activity", "wp-sms")
    },
    {
      key: "otp-logs",
      href: "/otp/logs",
      icon: "Logs",
      title: __("Logs", "wp-sms")
    },
    {
      key: "otp-authentication-channels",
      href: "/otp/authentication-channels",
      icon: "IdCard",
      title: __("Authentication Channels", "wp-sms")
    },
    {
      key: "otp-branding",
      href: "/otp/branding",
      icon: "Puzzle",
      title: __("Branding", "wp-sms")
    },
    {
      key: "otp-settings",
      href: "/otp/settings",
      icon: "Settings",
      title: __("Settings", "wp-sms")
    }
  ];
  const pluginVersionLabel = sprintf(__("Plugin Version %s", "wp-sms"), pluginVersion);
  return /* @__PURE__ */ jsxRuntimeExports.jsxs(Sidebar, { variant: "sidebar", collapsible: "icon", className: "border-r border-border", children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarHeader, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenu, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenuItem, { children: state === "collapsed" ? /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "flex justify-center p-2", children: /* @__PURE__ */ jsxRuntimeExports.jsxs(
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
          /* @__PURE__ */ jsxRuntimeExports.jsx("span", { className: "truncate text-xs", children: "OTP" })
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
    /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarContent, { children: /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarGroup, { children: [
      /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarGroupLabel, { children: __("Core Settings", "wp-sms") }),
      /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarGroupContent, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenu, { children: menuItems.map((item) => /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenuItem, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(
        SidebarMenuButton,
        {
          asChild: true,
          isActive: currentPath === `${item.href}`,
          tooltip: item.title,
          className: "data-[active=true]:bg-sidebar-active data-[active=true]:text-sidebar-active-foreground hover:data-[active=true]:bg-sidebar-active hover:data-[active=true]:text-sidebar-active-foreground transition-colors no-underline",
          children: /* @__PURE__ */ jsxRuntimeExports.jsxs(Link, { to: item.href, children: [
            /* @__PURE__ */ jsxRuntimeExports.jsx(RenderIcon, { iconName: item.icon }),
            /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: item.title })
          ] })
        }
      ) }, item.key)) }) })
    ] }) }),
    /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarFooter, { className: "sticky bottom-0", children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenu, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarMenuItem, { children: state === "collapsed" ? /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex flex-col items-center gap-2", children: [
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
};
function RouteComponent() {
  return /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "wrap flex w-full min-h-screen relative", children: /* @__PURE__ */ jsxRuntimeExports.jsxs(SidebarProvider, { children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(OTPSidebar, {}),
    /* @__PURE__ */ jsxRuntimeExports.jsx(SidebarInput, { children: /* @__PURE__ */ jsxRuntimeExports.jsx("main", { className: "p-6 flex-1", children: /* @__PURE__ */ jsxRuntimeExports.jsx(Outlet, {}) }) })
  ] }) });
}
export {
  RouteComponent as component
};
