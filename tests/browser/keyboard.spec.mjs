import { expect, test } from '@playwright/test';
import { axeViolations, openComponents } from './helpers.mjs';

// Keyboard behaviour does not depend on the theme: these run once, in the default theme.
test.beforeEach(async ({ page }) => {
  await openComponents(page);
});

test.describe('modal', () => {
  test('opens from the keyboard, traps focus, closes with Escape and returns focus', async ({ page }) => {
    const trigger = page.getByRole('button', { name: 'Contact us' });
    await trigger.focus();
    await page.keyboard.press('Enter');

    const dialog = page.getByRole('dialog', { name: 'Contact us' });
    await expect(dialog).toBeVisible();
    await expect.poll(() => dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);

    for (let i = 0; i < 8; i += 1) {
      await page.keyboard.press('Tab');
      expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);
    }

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(trigger).toBeFocused();
  });
});

test.describe('tabs', () => {
  test('arrow keys, Home and End move the selection, with one tab stop', async ({ page }) => {
    const tablist = page.getByRole('tablist', { name: 'Account settings' });
    const tab = (name) => tablist.getByRole('tab', { name });

    await tab('Profile').focus();
    await page.keyboard.press('ArrowRight');
    await expect(tab('Security')).toBeFocused();
    await expect(tab('Security')).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByRole('tabpanel', { name: 'Security' })).toBeVisible();

    await page.keyboard.press('End');
    await expect(tab('Billing')).toBeFocused();
    await page.keyboard.press('ArrowRight');
    await expect(tab('Profile')).toBeFocused();
    await page.keyboard.press('ArrowLeft');
    await expect(tab('Billing')).toBeFocused();
    await page.keyboard.press('Home');
    await expect(tab('Profile')).toBeFocused();

    // Roving tabindex: only the selected tab is in the Tab order.
    expect(await tablist.getByRole('tab').evaluateAll((tabs) => tabs.map((t) => t.tabIndex))).toEqual([0, -1, -1]);
  });
});

test.describe('grid', () => {
  test('sorts from the keyboard and announces the sort order', async ({ page }) => {
    // DataTables puts the sort control in the header as a named button.
    const header = page.locator('#users').getByRole('columnheader', { name: /Name/ });
    const sort = header.getByRole('button', { name: /Name: Activate to sort/ });
    await sort.focus();
    await expect(sort).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(header).toHaveAttribute('aria-sort', 'ascending');
    await page.keyboard.press('Enter');
    await expect(header).toHaveAttribute('aria-sort', 'descending');
  });

  test('pages from the keyboard', async ({ page }) => {
    const grid = page.locator('#users_wrapper');
    await grid.getByRole('link', { name: 'Next' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#users tbody')).toContainText('Grace Hopper');
  });

  test('column filters narrow a server-side grid from the keyboard, and Clear filters resets them', async ({ page }) => {
    const bar = page.getByRole('search', { name: 'Filters' });
    const rows = page.locator('#staff tbody tr');
    // The first cell holds the row's checkbox.
    const names = () => page.locator('#staff tbody td:nth-child(2)').allTextContents();
    // Each change asks the test server, which serves one request at a time: allow for a slow machine.
    const slow = { timeout: 15_000 };
    await expect(rows).toHaveCount(5, slow);

    // A select: the arrow keys choose an option, and the endpoint answers.
    await bar.getByLabel('Role').focus();
    await bar.getByLabel('Role').selectOption('admin');
    await expect.poll(names, slow).toEqual(['Ada Lovelace', 'Grace Hopper', 'Margaret Hamilton']);

    // A yes/no filter, then a date range whose last day is included.
    await bar.getByLabel('Active').selectOption('1');
    await expect.poll(names, slow).toEqual(['Ada Lovelace', 'Grace Hopper']);
    await bar.getByRole('group', { name: 'Joined' }).getByLabel('To').fill('2026-01-31');
    await expect.poll(names, slow).toEqual(['Ada Lovelace', 'Grace Hopper']);
    await bar.getByRole('group', { name: 'Joined' }).getByLabel('From').fill('2026-01-31');
    await expect.poll(names, slow).toEqual(['Ada Lovelace']);

    // Clear filters, then a text filter that matches part of a name, ignoring case.
    await bar.getByRole('button', { name: 'Clear filters' }).focus();
    await page.keyboard.press('Enter');
    await expect(rows).toHaveCount(5, slow);
    await expect(bar.getByLabel('Role')).toHaveValue('');
    await bar.getByRole('searchbox', { name: 'Name' }).focus();
    await page.keyboard.type('HAM');
    await expect.poll(names, slow).toEqual(['Margaret Hamilton']);
  });

  test('a row action asks first: Cancel and Escape keep the row; confirming deletes it and moves focus on', async ({ page }) => {
    const grid = page.locator('#users');
    const remove = grid.getByRole('button', { name: 'Delete Ada Lovelace' });
    await expect(grid.getByRole('link', { name: 'View Ada Lovelace' })).toHaveAttribute('href', '/users/1');

    // The dialog is an alert dialog titled by the question; Cancel has the focus.
    await remove.focus();
    await page.keyboard.press('Enter');
    const dialog = page.getByRole('alertdialog', { name: 'Delete Ada Lovelace?' });
    await expect(dialog).toBeVisible();
    await expect(dialog).toHaveAccessibleDescription('This cannot be undone.');
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeHidden();
    await expect(remove).toBeFocused();

    await page.keyboard.press('Enter');
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(remove).toBeFocused();
    await expect(grid.locator('tbody tr')).toHaveCount(2);

    // Confirmed: the endpoint answers with a message, shown in a toast; the row is gone and
    // focus is on the Delete button of the row that took its place.
    await page.keyboard.press('Enter');
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(dialog.getByRole('button', { name: 'Delete' })).toBeFocused();
    await page.keyboard.press('Enter');
    // The endpoint answers through the test server: allow for a slow machine.
    await expect(page.locator('.notyf')).toContainText('User 1 was deleted.', { timeout: 15_000 });
    await expect(grid.getByRole('button', { name: 'Delete Ada Lovelace' })).toHaveCount(0);
    await expect(grid.getByRole('button', { name: 'Delete Alan Turing' })).toBeFocused();
  });

  test('when nothing matches, the grid says so and its button clears the filters and the search', async ({ page }) => {
    const grid = page.locator('#staff');
    const slow = { timeout: 15_000 };
    await page.getByRole('search', { name: 'Filters' }).getByRole('searchbox', { name: 'Name' }).fill('zzz');
    await expect(grid.getByText('No rows match your search or filters')).toBeVisible(slow);
    expect(await axeViolations(page, { include: '#staff' })).toEqual([]);

    await grid.getByRole('button', { name: 'Clear filters and search' }).focus();
    await page.keyboard.press('Enter');
    await expect(grid.locator('tbody tr')).toHaveCount(5, slow);
    await expect(page.locator('#staff_wrapper .dt-search input')).toBeFocused();
  });

  test('a grouped server-side grid heads each group, totals the filtered rows and keeps groups when sorted', async ({ page }) => {
    const grid = page.locator('#budget');
    const slow = { timeout: 15_000 };
    const headings = grid.locator('tbody tr.celerik-grid-group');
    const items = () => grid.locator('tbody tr:not(.celerik-grid-group) td:nth-child(2)').allTextContents();

    await expect(headings).toHaveCount(2, slow);
    // The row counts come from the endpoint's meta.
    await expect(page.locator('#budget_wrapper .dt-info')).toHaveText('Showing 1 to 4 of 6 entries');
    await expect(headings.nth(0)).toHaveText(/Design\s*2 rows\s*Total 2,800\s*Average 3\.0/);
    // A group's count and totals cover the whole group: Dinner is on the next page.
    await expect(headings.nth(1)).toHaveText(/Sales\s*3 rows\s*Total 2,095\.50\s*Average 3\.3/);
    await expect(grid.locator('tfoot')).toHaveText(/Total 5,045\.50\s*Average 2\.8/);
    expect(await grid.getByRole('rowheader', { name: /Design/ }).count()).toBe(1);
    expect(await axeViolations(page, { include: '#budget' })).toEqual([]);

    // Sorting by Amount sorts within each group.
    await grid.getByRole('columnheader', { name: /Amount/ }).getByRole('button', { name: /Amount: Activate to sort/ }).focus();
    await page.keyboard.press('Enter');
    await expect.poll(items, slow).toEqual(['Fonts', 'Laptop', 'Dinner', 'Hotel']);

    await page.locator('#budget_wrapper .dt-search input').fill('Hotel');
    await expect(headings).toHaveCount(1, slow);
    await expect(headings).toHaveText(/Sales\s*1 row\s*Total 800\s*Average 5\.0/);
    await expect(grid.locator('tfoot')).toHaveText(/Total 800\s*Average 5\.0/);
  });

  test('a cell is edited in place from the keyboard: refused values keep the field, Escape cancels, Enter saves', async ({ page }) => {
    const grid = page.locator('#budget');
    const slow = { timeout: 15_000 };
    await expect(grid.locator('tr.celerik-grid-group')).toHaveCount(2, slow);

    // The editor checks the value first: the message is the field's description.
    const edit = grid.getByRole('button', { name: 'Edit Amount of Fonts' });
    await edit.focus();
    await page.keyboard.press('Enter');
    const amount = grid.getByRole('spinbutton', { name: 'Amount of Fonts' });
    await expect(amount).toBeFocused();
    await amount.fill('-1');
    await page.keyboard.press('Enter');
    await expect(amount).toHaveAttribute('aria-invalid', 'true');
    await expect(amount).toHaveAccessibleDescription('Enter 0 or more.');
    expect(await axeViolations(page, { include: '#budget' })).toEqual([]);

    // Escape: the old value, and focus back on the button.
    await page.keyboard.press('Escape');
    await expect(edit).toBeFocused();
    await expect(edit.locator('xpath=..')).toHaveText('300');

    // Enter: saved, announced, and focus back on the button.
    await page.keyboard.press('Enter');
    await amount.fill('350');
    await page.keyboard.press('Enter');
    await expect(edit).toBeFocused(slow);
    await expect(edit.locator('xpath=..')).toHaveText('350');
    await expect(page.locator('#budget_wrapper [role=status]').last()).toHaveText('Amount of Fonts saved.');

    // The endpoint refuses a value: the field stays open with its message.
    await grid.getByRole('button', { name: 'Edit Item of Fonts' }).click();
    const item = grid.getByRole('textbox', { name: 'Item of Fonts' });
    await item.fill('Hotel');
    await page.keyboard.press('Enter');
    await expect(item).toHaveAccessibleDescription('This item already exists.', slow);
    await expect(item).toBeFocused();
  });

  test('the Columns menu shows and hides columns from the keyboard, and remembers them', async ({ page }) => {
    const wrapper = page.locator('#users_wrapper');
    const button = wrapper.getByRole('button', { name: 'Columns' });
    const panel = wrapper.getByRole('group', { name: 'Show columns' });
    const header = page.locator('#users').getByRole('columnheader', { name: 'E-mail' });

    await button.focus();
    await page.keyboard.press('Enter');
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    // Name is always shown: it is not in the menu.
    await expect(panel.getByRole('checkbox')).toHaveCount(2);
    await panel.getByRole('checkbox', { name: 'E-mail' }).focus();
    await page.keyboard.press('Space');
    await expect(header).toHaveCount(0);

    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(button).toBeFocused();

    // The choice is kept for the next visit.
    await openComponents(page);
    await expect(page.locator('#users').getByRole('columnheader', { name: 'E-mail' })).toHaveCount(0);
    await wrapper.getByRole('button', { name: 'Columns' }).click();
    await panel.getByRole('checkbox', { name: 'E-mail' }).check();
    await expect(header).toBeVisible();
  });

  test('rows are selected from the keyboard, across pages, and a bulk action sends them', async ({ page }) => {
    const grid = page.locator('#staff');
    const bar = page.getByRole('group', { name: 'Selected rows' });
    const slow = { timeout: 15_000 };
    const all = grid.getByRole('checkbox', { name: 'Select all rows on this page' });
    await expect(bar.getByRole('status')).toHaveText('No rows selected');
    await expect(bar.getByRole('button', { name: 'Deactivate selected' })).toBeDisabled();

    await grid.getByRole('checkbox', { name: 'Select Ada Lovelace' }).focus();
    await page.keyboard.press('Space');
    await expect(bar.getByRole('status')).toHaveText('1 selected');
    // Some rows of the page are selected: the page's box is mixed.
    await expect(all).toHaveJSProperty('indeterminate', true);

    // The next page: its rows are not selected, and the selection is kept.
    // Sorted by name, Margaret Hamilton is the only row of page 2.
    await page.locator('#staff_wrapper').getByRole('link', { name: 'Next' }).click();
    await expect(page.locator('#staff_wrapper .dt-info')).toHaveText('Showing 6 to 6 of 6 entries', slow);
    await expect(grid.getByRole('checkbox', { name: 'Select Ada Lovelace' })).toHaveCount(0);
    await grid.getByRole('checkbox', { name: 'Select Margaret Hamilton' }).check();
    await expect(bar.getByRole('status')).toHaveText('2 selected');

    await bar.getByRole('button', { name: 'Deactivate selected' }).focus();
    await page.keyboard.press('Enter');
    const dialog = page.getByRole('alertdialog', { name: 'Deactivate 2 people?' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Deactivate selected' }).click();

    await expect(page.locator('.notyf')).toContainText('2 people were deactivated: Ada Lovelace, Margaret Hamilton.', slow);
    await expect(bar.getByRole('status')).toHaveText('No rows selected');
    await expect(all).toBeFocused();
  });

  test('an action opens its form in a modal: filled for the row, errors on the fields, saved and focus returns', async ({ page }) => {
    const grid = page.locator('#staff');
    const slow = { timeout: 15_000 };
    const edit = grid.getByRole('button', { name: 'Edit Ada Lovelace' });
    await edit.focus();
    await page.keyboard.press('Enter');

    const dialog = page.getByRole('dialog', { name: 'Edit Ada Lovelace' });
    await expect(dialog).toBeVisible();
    const name = dialog.getByRole('textbox', { name: /Name/ });
    await expect(name).toBeFocused();
    await expect(name).toHaveValue('Ada Lovelace');
    await expect(dialog.getByRole('combobox', { name: 'Role' })).toHaveValue('admin');

    // Escape: closed, focus back on the button.
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(edit).toBeFocused();

    // The endpoint refuses the name: the message is the field's description.
    await page.keyboard.press('Enter');
    await expect(name).toBeFocused();
    await name.fill('Alan Turing');
    await page.keyboard.press('Enter');
    await expect(name).toHaveAccessibleDescription('This name is taken.', slow);
    await expect(name).toBeFocused();
    await expect(name).toHaveAttribute('aria-invalid', 'true');

    // Saved: closed, its message shown, the rows reloaded, focus on the button again.
    await name.fill('Ada King');
    await dialog.getByRole('button', { name: 'Save' }).click();
    await expect(page.locator('.notyf')).toContainText('Ada King was updated.', slow);
    await expect(dialog).toBeHidden();
    await expect(grid.getByRole('button', { name: 'Edit Ada Lovelace' })).toBeFocused(slow);
  });

  test('a server-side grid\'s row action sends its request and reloads the page of rows', async ({ page }) => {
    const grid = page.locator('#staff');
    await grid.getByRole('button', { name: 'Deactivate Grace Hopper' }).click();
    await expect(page.locator('.notyf')).toContainText('Grace Hopper was deactivated.', { timeout: 15_000 });
    await expect(grid.getByRole('button', { name: 'Deactivate Grace Hopper' })).toBeFocused({ timeout: 15_000 });
  });
});

test.describe('form', () => {
  test('a failed submit moves focus to the first invalid field', async ({ page }) => {
    const form = page.locator('#signup');
    await form.getByRole('textbox', { name: /Name/ }).fill('');
    await form.getByRole('button', { name: 'Create account' }).focus();
    await page.keyboard.press('Enter');

    const name = form.getByRole('textbox', { name: /Name/ });
    await expect(name).toBeFocused();
    await expect(name).toHaveAttribute('aria-invalid', 'true');
  });

  test('with an error summary, a failed submit focuses the summary and its links reach the fields', async ({ page }) => {
    const form = page.locator('#article');
    await form.getByRole('textbox', { name: /Title/ }).fill('');
    await form.getByRole('button', { name: 'Publish' }).focus();
    await page.keyboard.press('Enter');

    const summary = form.locator('.celerik-error-summary');
    await expect(summary).toBeVisible();
    await expect(summary).toBeFocused();
    await expect(summary.getByRole('heading', { name: 'There is a problem' })).toBeVisible();
    expect(await axeViolations(page, { include: '#article' })).toEqual([]);

    await page.keyboard.press('Tab');
    await page.keyboard.press('Enter');
    await expect(form.getByRole('textbox', { name: /Title/ })).toBeFocused();
  });

  test('the rich text editor is reachable and editable from the keyboard', async ({ page }) => {
    const title = page.locator('#article').getByRole('textbox', { name: /Title/ });
    await title.focus();
    await page.keyboard.press('Tab');

    // Tab goes through the toolbar buttons, then into the editor.
    const editor = page.getByRole('textbox', { name: 'Article' });
    for (let i = 0; i < 20 && !(await editor.evaluate((element) => element === document.activeElement)); i += 1) {
      await page.keyboard.press('Tab');
    }
    await expect(editor).toBeFocused();
    await page.keyboard.type(' typed');
    await expect(editor).toContainText('typed');

    // Tab leaves the editor instead of inserting a tab (WCAG 2.1.2, No Keyboard Trap).
    await page.keyboard.press('Tab');
    await expect(editor).not.toBeFocused();
    expect(await editor.textContent()).not.toContain('	');
    await page.keyboard.press('Shift+Tab');
    await expect(editor).toBeFocused();
  });
});

test.describe('scheduler', () => {
  test('toolbar buttons work from the keyboard', async ({ page }) => {
    const calendar = page.locator('#team-calendar');
    const title = calendar.locator('.fc-toolbar-title');
    await expect(title).toHaveText(/November 2026/);

    await calendar.getByRole('button', { name: /Next/ }).focus();
    await page.keyboard.press('Enter');
    await expect(title).toHaveText(/December 2026/);
  });
});

test.describe('notification', () => {
  test('a toast can be dismissed from the keyboard', async ({ page }) => {
    // The error toast stays until dismissed; the others may expire during the test.
    const toast = page.locator('.celerik-toast-error');
    const dismiss = toast.getByRole('button', { name: /Dismiss/i });
    await expect(dismiss).toBeVisible();
    await dismiss.focus();
    await page.keyboard.press('Enter');
    await expect(toast).toHaveCount(0);
  });
});

test.describe('one-time code', () => {
  test('typing and pasting fill the boxes, Backspace empties them, and the box being filled shows the focus', async ({ page }) => {
    const input = page.getByRole('textbox', { name: 'Pairing code' });
    const boxes = () => page.locator('.celerik-otp:has(#preferences-pairing) .celerik-otp-slot').evaluateAll(
      (slots) => slots.map((slot) => slot.textContent).join('|'),
    );
    const activeRing = () => page.locator('.celerik-otp:has(#preferences-pairing) .celerik-otp-slot.is-active').evaluate(
      (slot) => getComputedStyle(slot).boxShadow,
    );

    await input.focus();
    await page.keyboard.type('12x');
    await expect(input).toHaveValue('12');
    expect(await boxes()).toBe('1|2||||');
    expect(await activeRing()).not.toBe('none');

    await page.keyboard.press('Backspace');
    await page.keyboard.press('Backspace');
    // A code pasted as written in a message.
    await page.keyboard.insertText('481 205');
    await expect(input).toHaveValue('481205');
    expect(await boxes()).toBe('4|8|1|2|0|5');

    await page.keyboard.press('Backspace');
    expect(await boxes()).toBe('4|8|1|2|0|');
    // The input covers the boxes: a click on one focuses it.
    await page.locator('h1').click();
    const first = page.locator('.celerik-otp:has(#preferences-pairing) .celerik-otp-slot').first();
    await first.scrollIntoViewIfNeeded();
    const box = await first.boundingBox();
    await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
    await expect(input).toBeFocused();
  });
});

test.describe('key-value list', () => {
  test('Add and Remove from the keyboard, the focus following, and a key used twice', async ({ page }) => {
    const pairs = () => page.locator('#preferences').evaluate((form) => [...new FormData(form)]
      .filter(([name]) => name.startsWith('labels['))
      .map(([, value]) => value)
      .join('|'));
    expect(await pairs()).toBe('env|prod');

    const add = page.getByRole('button', { name: 'Add row' });
    await add.focus();
    await page.keyboard.press('Enter');
    const key2 = page.getByLabel('Key 2', { exact: true });
    await expect(key2).toBeFocused();
    await page.keyboard.type('env');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('textbox', { name: 'Value 2' })).toBeFocused();
    await page.keyboard.type('dev');
    expect(await pairs()).toBe('env|prod|env|dev');

    // A key used twice is flagged in its row when the form is checked.
    await page.locator('#preferences').evaluate((form) => window.jQuery(form).parsley().validate());
    await expect(key2).toHaveClass(/is-invalid/);
    await expect(page.locator('#preferences-labels-group li').nth(1)).toContainText('This key is already used.');

    // Remove the first row: the second takes its place, its name and the focus.
    await page.getByRole('button', { name: 'Remove row 1' }).focus();
    await page.keyboard.press('Enter');
    const key1 = page.getByLabel('Key 1', { exact: true });
    await expect(key1).toBeFocused();
    await expect(key1).toHaveValue('env');
    await expect(key1).not.toHaveClass(/is-invalid/);
    expect(await pairs()).toBe('env|dev');
  });
});

test.describe('rating', () => {
  test('arrow keys change the rating, the focused star shows the focus, and it can be cleared', async ({ page }) => {
    const value = () => page.locator('#preferences').evaluate((form) => new FormData(form).get('satisfaction'));
    expect(await value()).toBe('3');

    const third = page.getByRole('radio', { name: '3 of 5' });
    await third.focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('radio', { name: '4 of 5' })).toBeFocused();
    expect(await value()).toBe('4');
    // The radio is hidden: its star shows the focus ring, and four stars are filled.
    const star = page.locator('label[for="preferences-satisfaction-4"]');
    expect(await star.evaluate((label) => getComputedStyle(label).boxShadow)).not.toBe('none');
    const filled = () => page.locator('#preferences-satisfaction-group .celerik-rating-star svg').evaluateAll(
      (svgs) => svgs.map((svg) => getComputedStyle(svg).fill !== 'transparent' && getComputedStyle(svg).fill !== 'rgba(0, 0, 0, 0)'),
    );
    expect(await filled()).toEqual([true, true, true, true, false]);

    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('radio', { name: 'No rating' })).toBeFocused();
    expect(await value()).toBe('');
    expect(await filled()).toEqual([false, false, false, false, false]);
  });

  test('a click on a star rates, hovering previews', async ({ page }) => {
    const value = () => page.locator('#preferences').evaluate((form) => new FormData(form).get('satisfaction'));
    const filled = () => page.locator('#preferences-satisfaction-group .celerik-rating-star svg').evaluateAll(
      (svgs) => svgs.filter((svg) => !['transparent', 'rgba(0, 0, 0, 0)'].includes(getComputedStyle(svg).fill)).length,
    );
    const star = (n) => page.locator(`label[for="preferences-satisfaction-${n}"]`);

    await star(5).hover();
    expect(await filled()).toBe(5);
    expect(await value()).toBe('3');

    await star(2).click();
    expect(await value()).toBe('2');
    await page.mouse.move(0, 0);
    expect(await filled()).toBe(2);
  });
});

test.describe('searchable select', () => {
  test('a remote select searches the endpoint and picks a person from the keyboard', async ({ page }) => {
    const box = page.getByRole('combobox', { name: 'Reviewer' });
    const select = page.locator('select[name="reviewer"]');
    await expect(select).toHaveValue('1');

    await box.focus();
    await page.keyboard.type('hop');
    const option = page.getByRole('option', { name: 'Grace Hopper' });
    await expect(option).toBeVisible({ timeout: 15_000 }); // through the test server
    // Only the match: the endpoint searched.
    await expect(page.locator('#preferences-reviewer-ts-dropdown [role="option"]')).toHaveCount(1);
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');

    await expect(select).toHaveValue('3');
    await expect(box).toBeFocused();
  });

  test('a multiple select adds and removes choices from the keyboard', async ({ page }) => {
    const box = page.getByRole('combobox', { name: 'Languages' });
    const values = () => page.locator('select[name="languages[]"]').evaluate(
      (select) => [...select.selectedOptions].map((option) => option.value),
    );
    expect(await values()).toEqual(['fr']);

    await box.focus();
    await page.keyboard.type('lin');
    await page.keyboard.press('Enter');
    await expect.poll(values).toEqual(['fr', 'ln']);

    await page.keyboard.press('Escape');
    await page.keyboard.press('Backspace');
    await expect.poll(values).toEqual(['fr']);
  });

  test('a tags field adds typed tags with Enter and a comma, and removes them', async ({ page }) => {
    const box = page.getByRole('combobox', { name: 'Skills' });
    const values = () => page.locator('select[name="skills[]"]').evaluate(
      (select) => [...select.selectedOptions].map((option) => option.value),
    );
    expect(await values()).toEqual(['PHP']);

    await box.focus();
    await page.keyboard.type('Rust');
    await expect(page.getByRole('option', { name: 'Add Rust' })).toBeVisible();
    await page.keyboard.press('Enter');
    await expect.poll(values).toEqual(['PHP', 'Rust']);

    await page.keyboard.type('Go,');
    await expect.poll(values).toEqual(['PHP', 'Rust', 'Go']);

    // "php" is already a tag: nothing to add.
    await page.keyboard.type('php');
    await expect(page.getByRole('option', { name: /^Add/ })).toHaveCount(0);
    await page.keyboard.press('Enter');
    await expect.poll(values).toEqual(['PHP', 'Rust', 'Go']);
    await page.keyboard.press('Backspace');
    await page.keyboard.press('Backspace');
    await page.keyboard.press('Backspace');

    // A tag typed and left with Tab is kept.
    await page.keyboard.type('Elm');
    await page.keyboard.press('Tab');
    await expect.poll(values).toEqual(['PHP', 'Rust', 'Go', 'Elm']);
    await expect(box).not.toBeFocused();

    await box.focus();
    await page.keyboard.press('Backspace');
    await expect.poll(values).toEqual(['PHP', 'Rust', 'Go']);
  });
});

test.describe('form section', () => {
  test('a collapsible section opens and closes from the keyboard', async ({ page }) => {
    const toggle = page.locator('#preferences-section-Availability-heading');
    const group = page.getByRole('group', { name: 'Availability' });
    await expect(group).toBeVisible();

    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(group).toBeHidden();
    await expect(toggle).toBeFocused();

    await page.keyboard.press('Space');
    await expect(group).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Call time')).toBeFocused();
  });
});

test.describe('form wizard', () => {
  test('moves through the steps from the keyboard, checking each one', async ({ page }) => {
    const name = page.getByLabel('Full name');
    const team = page.getByLabel('Team name');
    const next = page.locator('#join [data-celerik-wizard="next"]');

    // Enter on an empty step: it stays, with the error.
    await name.focus();
    await page.keyboard.press('Enter');
    await expect(name).toBeFocused();
    await expect(name).toHaveAttribute('aria-invalid', 'true');

    // Enter means Next; focus moves to the next step's heading, then into the step.
    await name.fill('Ada Lovelace');
    await page.keyboard.press('Enter');
    const heading = page.getByRole('heading', { name: 'Step 2 of 3: Your team' });
    await expect(heading).toBeFocused();
    await expect(name).toBeHidden();
    await page.keyboard.press('Tab');
    await expect(team).toBeFocused();

    await page.keyboard.type('Analytical Engine');
    await next.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('heading', { name: 'Step 3 of 3: Confirm' })).toBeFocused();
    await expect(page.locator('#join [type="submit"]')).toBeVisible();

    // Back, with Space.
    await page.locator('#join [data-celerik-wizard="previous"]').focus();
    await page.keyboard.press('Space');
    await expect(heading).toBeFocused();
    await expect(team).toHaveValue('Analytical Engine');
    await expect(page.locator('#join [aria-current="step"]')).toContainText('Your team');
  });
});

test.describe('form repeater', () => {
  test('rows are added, moved and removed from the keyboard', async ({ page }) => {
    // The repeater is on the wizard's second step.
    await page.getByLabel('Full name').fill('Ada Lovelace');
    await page.getByLabel('Full name').press('Enter');
    const add = page.getByRole('button', { name: 'Add a member' });
    const members = page.locator('#join-members .celerik-repeater-items > li');

    await add.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('textbox', { name: 'Member e-mail' })).toBeFocused();
    await page.keyboard.type('ada@example.com');
    await add.focus();
    await page.keyboard.press('Enter');
    await page.keyboard.type('grace@example.com');
    await expect(members).toHaveCount(2);

    // Move the second member up: the button keeps the focus, or passes it to Move down.
    const second = page.getByRole('group', { name: 'Member 2' });
    await second.getByRole('button', { name: 'Move up' }).focus();
    await page.keyboard.press('Enter');
    const first = page.getByRole('group', { name: 'Member 1' });
    await expect(first.getByRole('textbox')).toHaveValue('grace@example.com');
    await expect(first.getByRole('button', { name: 'Move down' })).toBeFocused();

    // Remove the first: focus goes to the member that takes its place.
    await first.getByRole('button', { name: 'Remove' }).focus();
    await page.keyboard.press('Space');
    await expect(members).toHaveCount(1);
    await expect(page.getByRole('group', { name: 'Member 1' }).getByRole('textbox')).toBeFocused();
    await expect(page.getByRole('group', { name: 'Member 1' }).getByRole('textbox')).toHaveValue('ada@example.com');
  });
});
