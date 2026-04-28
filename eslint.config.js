import js from "@eslint/js";
import globals from "globals";

export default [
  js.configs.recommended,
  {
    files: ["src/js/**/*.js"],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: "script",
      globals: {
        ...globals.browser,
        wp: "readonly",
        QRCode: "readonly",
        SPX_PHOTON_VCARD_USERS: "writable",
      },
    },
    rules: {
      "no-unused-vars": "error",
      "no-console": "warn",
      eqeqeq: "error",
      semi: ["error", "always"],
      "no-var": "error",
      "prefer-const": "error",
    },
  },
];
