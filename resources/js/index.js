import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.css';
import '../scss/celerik.scss';

import { autoInit, init, register } from './core.js';

// Each library is loaded on demand, only on pages that contain that component.
register('grid', async (element, options) => (await import('./grid.js')).initGrid(element, options));
register('listview', async (element, options) => (await import('./listview.js')).initListView(element, options));
register('treeview', async (element, options) => (await import('./tree.js')).initTree(element, options));
register('treelist', async (element, options) => (await import('./treelist.js')).initTreeList(element, options));
register('taskboard', async (element, options) => (await import('./taskboard.js')).initTaskBoard(element, options));
register('usermenu', async (element, options) => (await import('./usermenu.js')).initUserMenu(element, options));
// The layout, the breadcrumb, cards, record views and stats are HTML and CSS (Bootstrap's offcanvas opens the sidebar on
// small screens): nothing to set up.
register('adminlayout', () => true);
register('breadcrumb', () => true);
register('card', () => true);
register('recordview', () => true);
register('stat', () => true);
register('stats', () => true);
register('accordion', async (element, options) => (await import('./layout.js')).initAccordion(element, options));
register('splitter', async (element, options) => (await import('./layout.js')).initSplitter(element, options));
register('tilelayout', async (element, options) => (await import('./tilelayout.js')).initTileLayout(element, options));
register('navigation', async (element, options) => (await import('./menu.js')).initNavigation(element, options));
register('menubutton', async (element) => (await import('./menu.js')).initMenuButton(element));
register('splitbutton', async (element) => (await import('./menu.js')).initMenuButton(element));
register('toolbar', async (element) => (await import('./menu.js')).initToolbar(element));
register('contextmenu', async (element, options) => (await import('./menu.js')).initContextMenu(element, options));
register('chart', async (element, options) => (await import('./chart.js')).initChart(element, options));
register('form', async (element, options) => (await import('./form.js')).initForm(element, options));
register('scheduler', async (element, options) => (await import('./scheduler.js')).initScheduler(element, options));
register('modal', async (element, options) => (await import('./modal.js')).initModal(element, options));
register('tabs', async (element, options) => (await import('./tabs.js')).initTabs(element, options));
register('notification', async (element, options) => (await import('./notification.js')).initNotification(element, options));
register('filemanager', async (element, options) => (await import('./filemanager.js')).initFileManager(element, options));
register('themebuilder', async (element, options) => (await import('./themebuilder.js')).initThemeBuilder(element, options));

// Shows a toast from JavaScript, e.g. after an AJAX request: Celerik.notify('success', 'Saved.').
// The message is always treated as text.
async function notify(type, message) {
  return (await import('./notification.js')).notify(type, message);
}

// What needs JavaScript is shown once it runs (e.g. actions that open a form).
document.documentElement.classList.add('celerik-js');

// Request actions (Action::request()) anywhere on the page, including rows a grid draws
// later: one listener asks for confirmation and sends the request, or opens the action's
// form (Action::form()).
document.addEventListener('submit', (event) => {
  const form = event.target;
  if (form instanceof HTMLFormElement && form.matches('form.celerik-action')) {
    event.preventDefault();
    const { submitter } = event;
    if (form.dataset.celerikFormModal) {
      import('./action-form.js').then(({ openActionForm }) => openActionForm(form));
      return;
    }
    import('./action.js').then(({ submitAction }) => submitAction(form, { submitter }));
  }
});

const Celerik = { register, init, notify };
window.Celerik = Celerik;

autoInit();

export default Celerik;
