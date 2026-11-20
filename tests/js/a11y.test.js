import axe from 'axe-core';
import { initGrid } from '../../resources/js/grid.js';
import { initForm } from '../../resources/js/form.js';
import { readOptions } from '../../resources/js/core.js';
import { loadFixture } from './helpers.js';

// WCAG 2.1 A/AA rules. Color contrast cannot be computed in jsdom and is checked in the browser.
async function violations() {
  const results = await axe.run(document.body, {
    runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
    rules: { 'color-contrast': { enabled: false } },
  });
  return results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

describe('accessibility (axe-core, WCAG 2.1 AA)', () => {
  test('grid markup from PHP', async () => {
    loadFixture('grid');
    expect(await violations()).toEqual([]);
  });

  test('grid after DataTables enhancement', async () => {
    const table = loadFixture('grid');
    const grid = initGrid(table, readOptions(table));
    expect(await violations()).toEqual([]);
    grid.destroy();
  });

  test('grid with totals and groups, before and after DataTables', async () => {
    const table = loadFixture('grid-totals');
    expect(await violations()).toEqual([]);
    const grid = initGrid(table, readOptions(table));
    expect(await violations()).toEqual([]);
    grid.destroy();
  });

  test('grid with inline editing: PHP markup, then an open field with an error', async () => {
    const table = loadFixture('grid-edit');
    expect(await violations()).toEqual([]);
    const grid = initGrid(table, readOptions(table));
    table.querySelector('[data-celerik-edit="name"]').click();
    const field = table.querySelector('.celerik-grid-editor input');
    field.value = '';
    field.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    expect(field.getAttribute('aria-invalid')).toBe('true');
    expect(await violations()).toEqual([]);
    grid.destroy();
  });

  test('an action form in a modal: PHP markup, then open with errors from the server', async () => {
    const table = loadFixture('action-form');
    expect(await violations()).toEqual([]);
    const grid = initGrid(table, readOptions(table));
    window.bootstrap = await import('bootstrap');
    const { openActionForm, showErrors } = await import('../../resources/js/action-form.js');
    const form = document.getElementById('person-form');
    await initForm(form, readOptions(form));
    const modal = document.getElementById('person-form-modal');
    const shown = new Promise((resolve) => { modal.addEventListener('shown.bs.modal', resolve, { once: true }); });
    openActionForm(table.querySelector('form[data-celerik-form-modal]'), { fetch: jest.fn() });
    await shown;
    showErrors(form, { name: ['This name is taken.'], other: ['Try later.'] });
    expect(await violations()).toEqual([]);
    window.bootstrap.Modal.getInstance(document.getElementById('person-form-modal')).dispose();
    delete window.bootstrap;
    grid.destroy();
  });

  test('list view: PHP markup, enhanced with its pager, and with no match', async () => {
    const element = loadFixture('listview');
    expect(await violations()).toEqual([]);
    const { initListView } = await import('../../resources/js/listview.js');
    initListView(element, readOptions(element));
    expect(await violations()).toEqual([]);
    const search = element.querySelector('input[type=search]');
    search.value = 'zzz';
    search.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
    expect(await violations()).toEqual([]);
  });

  test('tree views: PHP markup, then trees with links, checkboxes and radio buttons', async () => {
    const { initTree } = await import('../../resources/js/tree.js');
    for (const name of ['treeview', 'treeview-links', 'treeview-select']) {
      const element = loadFixture(name);
      expect(await violations()).toEqual([]);
      initTree(element, readOptions(element), { fetch: jest.fn() });
      expect(await violations()).toEqual([]);
    }
  });

  test('tree list: PHP markup, then a treegrid', async () => {
    const element = loadFixture('treelist');
    expect(await violations()).toEqual([]);
    const { initTreeList } = await import('../../resources/js/treelist.js');
    initTreeList(element, readOptions(element));
    expect(await violations()).toEqual([]);
  });

  test('task board: PHP markup, then a card picked up', async () => {
    const element = loadFixture('taskboard');
    expect(await violations()).toEqual([]);
    const { initTaskBoard } = await import('../../resources/js/taskboard.js');
    initTaskBoard(element, readOptions(element), { fetch: jest.fn() });
    const card = element.querySelector('.celerik-board-card');
    card.focus();
    card.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true }));
    expect(await violations()).toEqual([]);
  });

  test('admin layout: app bar, navigation, breadcrumb and an open user menu', async () => {
    loadFixture('admin-layout');
    expect(await violations()).toEqual([]);
    document.querySelector('.celerik-usermenu').open = true;
    expect(await violations()).toEqual([]);
  });

  test('menus: PHP markup, a toolbar with an open menu, a horizontal navigation', async () => {
    const element = loadFixture('menus');
    expect(await violations()).toEqual([]);
    const { initToolbar, initMenuButton, initNavigation } = await import('../../resources/js/menu.js');
    initMenuButton(document.getElementById('export'));
    initMenuButton(document.getElementById('save'));
    initToolbar(element);
    document.getElementById('export-button').click();
    expect(await violations()).toEqual([]);

    const nav = loadFixture('nav-horizontal');
    initNavigation(nav, { horizontal: true });
    nav.querySelector('details').open = true;
    expect(await violations()).toEqual([]);
  });

  test('accordion, card and splitter: PHP markup, then enhanced', async () => {
    const { initAccordion, initSplitter } = await import('../../resources/js/layout.js');
    const accordion = loadFixture('accordion');
    expect(await violations()).toEqual([]);
    initAccordion(accordion, readOptions(accordion));
    expect(await violations()).toEqual([]);

    loadFixture('card');
    expect(await violations()).toEqual([]);

    const splitter = loadFixture('splitter');
    expect(await violations()).toEqual([]);
    initSplitter(splitter, readOptions(splitter));
    expect(await violations()).toEqual([]);
  });

  test('badges, avatars, progress, loader and skeleton', async () => {
    loadFixture('display');
    expect(await violations()).toEqual([]);
  });

  test('chips, tooltip and popover: PHP markup, then enhanced and open', async () => {
    const { initChip, initTooltip } = await import('../../resources/js/display.js');
    const { initUserMenu } = await import('../../resources/js/usermenu.js');
    const chips = loadFixture('chips').parentElement;
    expect(await violations()).toEqual([]);
    for (const chip of chips.querySelectorAll('[data-celerik="chip"]')) {
      initChip(chip, readOptions(chip));
    }
    chips.querySelector('.celerik-chip-remove').click();
    expect(await violations()).toEqual([]);

    const tooltip = loadFixture('tooltip');
    initTooltip(tooltip, readOptions(tooltip));
    tooltip.querySelector('button').focus();
    expect(await violations()).toEqual([]);

    const popover = loadFixture('popover');
    initUserMenu(popover);
    popover.open = true;
    expect(await violations()).toEqual([]);
  });

  test('timeline: a named ordered list with a current step', async () => {
    loadFixture('timeline');
    expect(await violations()).toEqual([]);
  });

  test('carousels: PHP markup, then enhanced and moved', async () => {
    const { initCarousel } = await import('../../resources/js/carousel.js');
    let carousel = loadFixture('carousel');
    expect(await violations()).toEqual([]);
    initCarousel(carousel, readOptions(carousel)).next();
    expect(await violations()).toEqual([]);

    carousel = loadFixture('carousel-autoplay');
    initCarousel(carousel, readOptions(carousel)).stop();
    expect(await violations()).toEqual([]);
  });

  test('stats: a named list of stat tiles with sparklines', async () => {
    loadFixture('stats');
    expect(await violations()).toEqual([]);
  });

  test('record view: sections of labels and values', async () => {
    loadFixture('recordview');
    expect(await violations()).toEqual([]);
  });

  test('tile layout: PHP markup, then enhanced with its tools shown', async () => {
    const { initTileLayout } = await import('../../resources/js/tilelayout.js');
    const layout = loadFixture('tilelayout');
    expect(await violations()).toEqual([]);
    initTileLayout(layout, readOptions(layout));
    layout.querySelector('.celerik-tile-handle').click();
    expect(await violations()).toEqual([]);
  });

  test('scheduler markup from PHP (no-JS fallback list)', async () => {
    loadFixture('scheduler');
    expect(await violations()).toEqual([]);
  });

  test('form with editor and uploads: PHP markup, then Quill and FilePond, then failed validation', async () => {
    const form = loadFixture('form-rich');
    expect(await violations()).toEqual([]);

    const parsley = await initForm(form, readOptions(form));
    expect(await violations()).toEqual([]);

    form.querySelector('textarea[name="body"]').celerikEditor.setText('', 'user');
    parsley.validate();
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    expect(await violations()).toEqual([]);
    parsley.destroy();
  }, 30000);

  test('file manager markup from PHP (elFinder itself is checked in tests/browser)', async () => {
    const element = loadFixture('filemanager');
    expect(element.getAttribute('role')).toBe('region');
    expect(element.getAttribute('aria-label')).toBe('Shared files');
    expect(await violations()).toEqual([]);
  });

  test('theme builder markup from PHP, with a failing contrast pair', async () => {
    const element = loadFixture('themebuilder');
    expect(element.querySelectorAll('.celerik-theme-builder__pair.is-fail')).toHaveLength(1);
    expect(await violations()).toEqual([]);
  });

  test('chart markup from PHP', async () => {
    loadFixture('chart');
    expect(await violations()).toEqual([]);
  });

  test('form markup from PHP, before and after failed validation', async () => {
    const form = loadFixture('form');
    expect(await violations()).toEqual([]);

    const parsley = await initForm(form, readOptions(form));
    parsley.validate();
    expect(await violations()).toEqual([]);
    parsley.destroy();
  });

  test('date, time and date range fields, before and after failed validation', async () => {
    const form = loadFixture('form-dates');
    expect(await violations()).toEqual([]);

    const parsley = await initForm(form, readOptions(form));
    parsley.validate();
    expect(await violations()).toEqual([]);
    parsley.destroy();
  });

  test('sections, fieldsets and columns, before and after failed validation', async () => {
    const form = loadFixture('form-layout');
    expect(await violations()).toEqual([]);

    const parsley = await initForm(form, readOptions(form));
    parsley.validate();
    expect(await violations()).toEqual([]);
    parsley.destroy();
  });

  test('a wizard, on each step and after failed validation', async () => {
    const form = loadFixture('form-wizard');
    expect(await violations()).toEqual([]);

    const parsley = await initForm(form, readOptions(form));
    form.querySelector('[data-celerik-wizard="next"]').click();
    expect(await violations()).toEqual([]);
    form.querySelector('[name="name"]').value = 'Ada';
    form.querySelector('[name="email"]').value = 'ada@example.com';
    form.querySelector('[data-celerik-wizard="next"]').click();
    expect(await violations()).toEqual([]);
    parsley.destroy();
  });

  test('a repeater, after adding rows and a failed validation', async () => {
    const form = loadFixture('form-repeater');
    expect(await violations()).toEqual([]);

    const parsley = await initForm(form, readOptions(form));
    form.querySelector('[data-celerik-repeater-add]').click();
    await new Promise((resolve) => setTimeout(resolve));
    parsley.validate();
    expect(await violations()).toEqual([]);
    parsley.destroy();
  });

  test('searchable selects, before and after Tom Select and a failed validation', async () => {
    const form = loadFixture('form-select');
    expect(await violations()).toEqual([]);

    const parsley = await initForm(form, readOptions(form));
    expect(await violations()).toEqual([]);
    parsley.validate();
    expect(await violations()).toEqual([]);
    parsley.destroy();
  });

  test('one-time codes, before and after the boxes and a failed validation', async () => {
    const form = loadFixture('form-otp');
    expect(await violations()).toEqual([]);

    const parsley = await initForm(form, readOptions(form));
    expect(await violations()).toEqual([]);
    parsley.validate();
    expect(await violations()).toEqual([]);
    parsley.destroy();
  });

  test('key-value lists, before and after adding a row and a failed validation', async () => {
    const form = loadFixture('form-key-value');
    expect(await violations()).toEqual([]);

    const parsley = await initForm(form, readOptions(form));
    form.querySelector('[data-celerik-key-value-add]').click();
    parsley.validate();
    expect(await violations()).toEqual([]);
    parsley.destroy();
  });

  test('radio, checkbox group, switch, range and color markup, before and after failed validation', async () => {
    const form = loadFixture('form-choices');
    expect(await violations()).toEqual([]);

    const parsley = await initForm(form, readOptions(form));
    parsley.validate();
    expect(await violations()).toEqual([]);
    parsley.destroy();
  });
});
