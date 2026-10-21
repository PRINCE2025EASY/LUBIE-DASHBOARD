import globals from 'globals';

export default [
  { ignores: ['public/assets/vendor/**', 'vendor/**', 'node_modules/**'] },
  {
    files: ['**/*.js'],
    languageOptions: { ecmaVersion: 2022, sourceType: 'module', globals: { ...globals.browser, ...globals.serviceworker } },
    rules: {
      'no-unused-vars': ['error', { args: 'none', caughtErrors: 'none' }],
      'no-undef': 'error', 'no-unreachable': 'error', 'no-dupe-keys': 'error', 'no-redeclare': 'error',
    },
  },
];
