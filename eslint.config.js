import js from "@eslint/js";
import tseslint from "typescript-eslint";
import reactHooksPlugin from "eslint-plugin-react-hooks";
import reactPlugin from "eslint-plugin-react";
import globals from "globals";

const hooksRules = {
  "react-hooks/rules-of-hooks": "error",
  "react-hooks/exhaustive-deps": "warn",
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

  // 4. React hooks + React rules for React source files
  {
    files: ["resources/react/src/**/*.{ts,tsx}"],
    plugins: {
      "react-hooks": reactHooksPlugin,
      react: reactPlugin,
    },
    settings: {
      react: { version: "19" },
    },
    rules: {
      ...hooksRules,
      "react/jsx-key": "error",
      "react/react-in-jsx-scope": "off",
      "react/prop-types": "off",
      "react/no-direct-mutation-state": "error",
      "react/no-children-prop": "error",
    },
  },

  // 5. Preact layer — hooks plugin only, no react rules
  {
    files: ["resources/preact/src/**/*.{js,jsx}"],
    plugins: {
      "react-hooks": reactHooksPlugin,
    },
    languageOptions: {
      globals: {
        wsmsAuth: "readonly",
      },
      parserOptions: {
        ecmaFeatures: { jsx: true },
      },
    },
    rules: { ...hooksRules },
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
