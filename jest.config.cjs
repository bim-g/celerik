module.exports = {
  testEnvironment: 'jsdom',
  roots: ['<rootDir>/tests/js'],
  setupFiles: ['<rootDir>/tests/js/setup.js'],
  transform: {
    '\\.m?js$': 'babel-jest',
  },
  // These packages ship ES modules only for the browser; transform them too.
  transformIgnorePatterns: ['/node_modules/(?!(preact|@fullcalendar|quill|parchment)/)'],
  moduleNameMapper: {
    // Stylesheets imported by Quill and FilePond modules are irrelevant in jsdom.
    '\\.css$': '<rootDir>/tests/js/style-stub.js',
    // Vite's ?url imports (the PDF.js worker) are a file name in the bundle.
    '\\?url$': '<rootDir>/tests/js/style-stub.js',
    // Quill imports lodash-es (644 ES module files that every worker would transform);
    // the CommonJS build has the same API and needs no transform. Tests only.
    '^lodash-es$': 'lodash',
    // The bundle imports the ES module builds of the DataTables Buttons; Jest loads their
    // CommonJS builds, which read window.DataTable (set in setup.js).
    '^(datatables\\.net-buttons/js/.*)\\.mjs$': '$1.js',
  },
  // With a cold cache (always the case in CI) the first test that imports Quill or
  // FilePond spends several seconds transforming them, beyond Jest's 5 s default.
  testTimeout: 30000,
  collectCoverageFrom: ['resources/js/**/*.js', '!resources/js/index.js'],
};
