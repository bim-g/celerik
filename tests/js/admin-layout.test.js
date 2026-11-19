import { initUserMenu } from '../../resources/js/usermenu.js';
import { loadFixture } from './helpers.js';

const text = (node) => node.textContent.replace(/\s+/g, ' ').trim();

describe('admin layout', () => {
  beforeEach(() => {
    loadFixture('admin-layout');
  });

  test('a skip link first, then the app bar, the sidebar and the main landmark', () => {
    const skip = document.querySelector('main a, body a');
    expect(skip.classList.contains('celerik-skip-link')).toBe(true);
    expect(skip.getAttribute('href')).toBe('#app-main');
    const main = document.getElementById('app-main');
    expect(main.tagName).toBe('MAIN');
    expect(main.querySelector('h1').textContent).toBe('Ada Lovelace');
    expect(document.querySelector('header .navbar-brand').getAttribute('href')).toBe('/');

    const toggle = document.querySelector('.celerik-appbar-toggle');
    expect(toggle.getAttribute('aria-label')).toBe('Open navigation');
    expect(toggle.getAttribute('aria-controls')).toBe('app-sidebar');
    const sidebar = document.getElementById('app-sidebar');
    expect(sidebar.classList.contains('offcanvas-lg')).toBe(true);
    expect(document.getElementById(sidebar.getAttribute('aria-labelledby')).textContent).toBe('Navigation');
  });

  test('the navigation marks the current section and opens its group', () => {
    const nav = document.querySelector('nav.celerik-nav');
    expect(nav.getAttribute('aria-label')).toBe('Main');
    // /members/42 is not a link of its own: its section, All members, is current.
    const current = nav.querySelector('[aria-current]');
    expect(text(current)).toBe('All members');
    expect(current.getAttribute('aria-current')).toBe('true');
    expect(current.closest('details').open).toBe(true);
    expect(text(nav.querySelector('a[href="/members/invitations"]'))).toBe('Invitations 3');
  });

  test('the breadcrumb ends with the current page, not a link', () => {
    const crumbs = document.querySelector('nav.celerik-breadcrumb');
    expect(crumbs.getAttribute('aria-label')).toBe('Breadcrumb');
    const items = crumbs.querySelectorAll('li');
    expect(items).toHaveLength(3);
    expect(items[2].getAttribute('aria-current')).toBe('page');
    expect(items[2].querySelector('a')).toBeNull();
  });

  test('the user menu: Escape closes it and returns focus; a click or focus elsewhere closes it', () => {
    const menu = document.querySelector('.celerik-usermenu');
    const summary = menu.querySelector('summary');
    initUserMenu(menu);
    expect(summary.querySelector('.celerik-usermenu-initials').textContent).toBe('AL');
    expect(summary.querySelector('.celerik-usermenu-name').textContent).toBe('Ada Lovelace');
    expect(summary.querySelector('.celerik-usermenu-initials').getAttribute('aria-hidden')).toBe('true');

    menu.open = true;
    const profile = menu.querySelector('a[href="/profile"]');
    profile.focus();
    profile.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(menu.open).toBe(false);
    expect(document.activeElement).toBe(summary);

    menu.open = true;
    document.getElementById('app-main').dispatchEvent(new Event('pointerdown', { bubbles: true }));
    expect(menu.open).toBe(false);

    menu.open = true;
    profile.focus();
    profile.dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: document.querySelector('.navbar-brand') }));
    expect(menu.open).toBe(false);
  });

  test('the user menu holds links and actions, and the user\'s detail', () => {
    const panel = document.querySelector('.celerik-usermenu-panel');
    expect(panel.querySelector('.celerik-usermenu-detail').textContent).toBe('ada@example.com');
    expect(panel.querySelector('form.celerik-action').getAttribute('action')).toBe('/logout');
    expect(panel.querySelector('.celerik-usermenu-divider').getAttribute('aria-hidden')).toBe('true');
  });
});
