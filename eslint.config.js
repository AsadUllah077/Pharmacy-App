import vue from 'eslint-plugin-vue';
import ts from 'typescript-eslint';
import vueParser from 'vue-eslint-parser';
export default [
  ...ts.configs.recommended,
  ...vue.configs['flat/essential'],
  {
    files: ['**/*.vue'],
    languageOptions: { parser: vueParser, parserOptions: { parser: ts.parser } },
    rules: { 'vue/multi-word-component-names': 'off' },
  },
];
