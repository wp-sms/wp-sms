import { c as createLucideIcon, _ as __, j as jsxRuntimeExports, B as Button } from "./main-D1KP0B5-.js";
import { j as useFieldContext, k as useStore } from "./use-save-settings-values-BPUIfNdJ.js";
import { F as FieldWrapper } from "./field-wrapper-BuuNVHwJ.js";
import "./alert-DiJsRLFO.js";
import "./sprintf-DmNrJSYG.js";
import "./index-W778R1kW.js";
const __iconNode = [
  ["path", { d: "M12 13v8", key: "1l5pq0" }],
  ["path", { d: "M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242", key: "1pljnt" }],
  ["path", { d: "m8 17 4-4 4 4", key: "1quai1" }]
];
const CloudUpload = createLucideIcon("cloud-upload", __iconNode);
function useWordPressMediaUploader() {
  const openMediaUploader = (callback) => {
    if (typeof window !== "undefined" && window.wp && window.wp.media) {
      try {
        const mediaUploader = window.wp.media({
          title: __("Select Image", "wp-sms"),
          button: {
            text: __("Use this image", "wp-sms")
          },
          multiple: false
        });
        mediaUploader.on("select", () => {
          const attachment = mediaUploader.state().get("selection").first().toJSON();
          callback(attachment.url);
        });
        mediaUploader.open();
      } catch (error) {
        console.error("Error opening WordPress media uploader:", error);
        openFileInput(callback);
      }
    } else {
      console.warn("WordPress media uploader not available, using fallback");
      openFileInput(callback);
    }
  };
  const openFileInput = (callback) => {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";
    input.style.display = "none";
    input.onchange = (e) => {
      const file = e.target.files?.[0];
      if (file) {
        const url = URL.createObjectURL(file);
        callback(url);
        setTimeout(() => URL.revokeObjectURL(url), 1e3);
      }
      if (document.body.contains(input)) {
        document.body.removeChild(input);
      }
    };
    document.body.appendChild(input);
    input.click();
  };
  return { openMediaUploader };
}
const ImageField = ({ schema }) => {
  const field = useFieldContext();
  const { openMediaUploader } = useWordPressMediaUploader();
  const errors = useStore(field.store, (state) => state.meta.errors);
  return /* @__PURE__ */ jsxRuntimeExports.jsx(FieldWrapper, { errors, schema, children: /* @__PURE__ */ jsxRuntimeExports.jsxs(Button, { variant: "outline", type: "button", onClick: () => openMediaUploader(field.handleChange), children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(CloudUpload, {}),
    field.state.value ? __("Change Image", "wp-sms") : __("Select Image", "wp-sms")
  ] }) });
};
export {
  ImageField
};
