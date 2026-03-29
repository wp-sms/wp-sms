import js from "@eslint/js";
import tseslint from "typescript-eslint";
import reactHooksPlugin from "eslint-plugin-react-hooks";
import reactPlugin from "eslint-plugin-react";
import jsxA11y from "eslint-plugin-jsx-a11y";
import globals from "globals";

const hooksRules = {
  "react-hooks/rules-of-hooks": "error",
  "react-hooks/exhaustive-deps": "warn",
};

const a11yOverrides = {
  // autoFocus is intentional in dialogs, search fields, and step transitions
  "jsx-a11y/no-autofocus": "off",
  // Our Label/FieldLabel components render <label> and receive htmlFor via spread props
  "jsx-a11y/label-has-associated-control": ["error", {
    labelComponents: ["Label", "FieldLabel"],
    labelAttributes: ["htmlFor"],
    controlComponents: ["Input", "Select", "Switch", "Textarea", "PhoneInput"],
    assert: "either",
    depth: 5,
  }],
};

export default [
  // 1. Global ignores
  {
    ignores: [
      "node_modules/",
      "vendor/",
      "public/",
      "packages/",
      "tests/",
      "*.config.*",
    ],
  },

  // 2. Base JS rules for all source files
  {
    files: ["resources/**/*.{js,jsx,ts,tsx}"],
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "module",
      globals: {
        ...globals.browser,
      },
      parserOptions: {
        ecmaFeatures: { jsx: true },
      },
    },
    rules: {
      ...js.configs.recommended.rules,
      "no-unused-vars": [
        "warn",
        { argsIgnorePattern: "^_", varsIgnorePattern: "^_" },
      ],
      "no-console": ["warn", { allow: ["warn", "error"] }],
    },
  },

  // 3. TypeScript layer for React source files
  ...tseslint.configs.recommended.map((config) => ({
    ...config,
    files: ["resources/react/src/**/*.{ts,tsx}"],
  })),
  {
    files: ["resources/react/src/**/*.{ts,tsx}"],
    rules: {
      "no-undef": "off", // TypeScript compiler handles this
      "no-unused-vars": "off", // Use TS version instead
      "@typescript-eslint/no-unused-vars": [
        "warn",
        { argsIgnorePattern: "^_", varsIgnorePattern: "^_" },
      ],
      "@typescript-eslint/no-explicit-any": "warn",
    },
  },

  // 4. React hooks + React + a11y rules for React source files
  {
    files: ["resources/react/src/**/*.{ts,tsx}"],
    plugins: {
      "react-hooks": reactHooksPlugin,
      react: reactPlugin,
      "jsx-a11y": jsxA11y,
    },
    settings: {
      react: { version: "19" },
    },
    rules: {
      ...hooksRules,
      ...jsxA11y.flatConfigs.recommended.rules,
      ...a11yOverrides,
      "react/jsx-key": "error",
      "react/react-in-jsx-scope": "off",
      "react/prop-types": "off",
      "react/no-direct-mutation-state": "error",
      "react/no-children-prop": "error",
      "react/jsx-no-literals": ["warn", {
        noStrings: false,
        ignoreProps: true,
        allowedStrings: ["·", "|", ":", "/", "+", "×", "-", "*", "(", ")", "@", ".", "✓", "%", "&ndash;", "&mdash;", "—", "#"],
      }],
    },
  },

  // 5. Preact layer — hooks + jsx-uses-vars + a11y to avoid false "unused" on JSX imports
  {
    files: ["resources/preact/src/**/*.{js,jsx}"],
    plugins: {
      "react-hooks": reactHooksPlugin,
      react: reactPlugin,
      "jsx-a11y": jsxA11y,
    },
    languageOptions: {
      globals: {
        wsmsAuth: "readonly",
      },
      parserOptions: {
        ecmaFeatures: { jsx: true },
      },
    },
    rules: {
      ...hooksRules,
      ...jsxA11y.flatConfigs.recommended.rules,
      ...a11yOverrides,
      "react/jsx-uses-vars": "error",
      "react/jsx-no-literals": ["warn", {
        noStrings: false,
        ignoreProps: true,
        allowedStrings: ["·", "|", ":", "/", "+", "×", "-", "*", "(", ")", "@", ".", "✓", "%", "&ndash;", "&mdash;", "—", "#"],
      }],
    },
  },

  // 6. WordPress blocks layer
  {
    files: ["resources/blocks/**/*.js"],
    plugins: {
      "react-hooks": reactHooksPlugin,
    },
    languageOptions: {
      globals: {
        wp: "readonly",
        wc: "readonly",
      },
      parserOptions: {
        ecmaFeatures: { jsx: true },
      },
    },
    rules: { ...hooksRules },
  },

  // 7. Entry scripts — base rules only (no extra plugins needed)
  {
    files: ["resources/entries/**/*.js"],
    languageOptions: {
      globals: {
        jQuery: "readonly",
        wp: "readonly",
      },
    },
  },
];
