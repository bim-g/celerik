// Runs in the <head> of every demo page (site/demos/build.php), right after celerik.css and
// before the page renders: ?mode=dark sets Bootstrap's dark mode.
(function () {
  var params = new URLSearchParams(window.location.search);
  document.documentElement.setAttribute('data-bs-theme', params.get('mode') === 'dark' ? 'dark' : 'light');
}());

// Demos run without a server: requests to demo-api/ (Action::request() in the demos) are
// answered here with a message, and nothing is saved.
(function () {
  var send = window.fetch.bind(window);
  window.fetch = function (resource, init) {
    var url = new URL(typeof resource === 'string' ? resource : resource.url, window.location.href);
    if (url.pathname.indexOf('/demo-api/') === -1) {
      return send(resource, init);
    }
    // A grid cell edit (Grid::inlineEdit()) is accepted quietly: the grid announces it.
    var edit = init && init.body instanceof FormData && init.body.has('field');
    var body = edit ? '{}' : JSON.stringify({ message: 'Done. This demo has no server, so nothing was saved.' });
    return new Promise(function (resolve) {
      setTimeout(function () {
        resolve(new Response(body, { status: 200, headers: { 'Content-Type': 'application/json' } }));
      }, 300);
    });
  };
}());
