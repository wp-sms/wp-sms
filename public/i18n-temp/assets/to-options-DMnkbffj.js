import { c as createLucideIcon } from "./main-D1KP0B5-.js";
const __iconNode = [["path", { d: "m6 9 6 6 6-6", key: "qrunsl" }]];
const ChevronDown = createLucideIcon("chevron-down", __iconNode);
function toOptions(data) {
  if (!Array.isArray(data) && Object.values(data).some((option) => typeof option === "object")) {
    return Object.entries(data ?? {}).map(([key, value]) => {
      if (typeof value === "string") {
        return {
          value: key,
          label: value
        };
      }
      return {
        label: key,
        value: key,
        children: Object.entries(value).map(([k, v]) => ({
          value: k,
          label: v
        }))
      };
    });
  }
  if (!Array.isArray(data) && Object.values(data).every((option) => typeof option === "string")) {
    return Object.entries(data ?? {}).map(([key, value]) => {
      return {
        value: key || value,
        label: value || key
      };
    });
  }
  if (Array.isArray(data)) {
    return data?.map((opt) => {
      const optionValue = opt.value || Object.keys(opt)[0];
      const optionLabel = opt.label || Object.values(opt)[0];
      return {
        value: optionValue,
        label: optionLabel
      };
    });
  }
  return [];
}
export {
  ChevronDown as C,
  toOptions as t
};
