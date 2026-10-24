// Entry point. app.js is imported without a version query so every view module shares the
// same app.js instance (a second copy ran boot() twice: the theme button toggled twice and
// every API call was sent twice). The version query on this file busts the browser cache.
import './app.js';
