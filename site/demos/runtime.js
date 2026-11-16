// Runs in the <head> of every demo page (site/demos/build.php), right after celerik.css and
// before the page renders: ?mode=dark sets Bootstrap's dark mode.
(function () {
  var params = new URLSearchParams(window.location.search);
  document.documentElement.setAttribute('data-bs-theme', params.get('mode') === 'dark' ? 'dark' : 'light');
}());
