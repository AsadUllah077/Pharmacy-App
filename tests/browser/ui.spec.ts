import { test, expect, type Page } from '@playwright/test';

async function login(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Email address').fill('admin@example.test');
  await page.getByLabel('Password', { exact: true }).fill('ChangeMe123!Local');
  await page.getByRole('button', { name: 'Sign in to workspace' }).click();
  await expect(page.getByRole('heading', { name: 'Overview', exact: true })).toBeVisible();
}
async function noPageOverflow(page: Page) {
  const sizes = await page.evaluate(() => ({
    content: document.documentElement.scrollWidth,
    viewport: document.documentElement.clientWidth,
  }));
  expect(sizes.content, `Unexpected horizontal page overflow on ${page.url()}`).toBeLessThanOrEqual(
    sizes.viewport + 1,
  );
}

test('system theme, explicit choice, reload and Inertia navigation stay in sync', async ({
  page,
}) => {
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.goto('/login');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.getByRole('button', { name: 'Switch to light mode' }).click();
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  await login(page);
  await page.getByRole('button', { name: 'Switch to dark mode' }).click();
  await page.getByRole('link', { name: 'Medicines', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Medicines', exact: true })).toBeVisible();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.goto('/profile');
  await page.getByLabel('Color theme').selectOption('system');
  await page.emulateMedia({ colorScheme: 'light' });
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  await page.emulateMedia({ colorScheme: 'dark' });
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  expect(
    await page
      .locator('.panel')
      .first()
      .evaluate((element) => getComputedStyle(element).backgroundColor),
  ).toBe('rgb(22, 33, 47)');
});

for (const width of [320, 375, 768, 1024, 1440]) {
  test(`responsive pages at ${width}px in both themes`, async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.setViewportSize({ width, height: 900 });
    await login(page);
    for (const theme of ['light', 'dark']) {
      await page.goto('/profile');
      await page.getByLabel('Color theme').selectOption(theme);
      for (const route of [
        '/',
        '/pos',
        '/medicines',
        '/inventory',
        '/purchases',
        '/sales',
        '/accounting',
        '/expenses',
        '/administration',
        '/profile',
      ]) {
        await page.goto(route);
        await expect(page.locator('main h1')).toBeVisible();
        if (route === '/pos') await expect(page.locator('.product-card').first()).toBeVisible();
        await noPageOverflow(page);
      }
    }
    await page.goto('/medicines');
    await page.getByRole('button', { name: 'Add medicine' }).click();
    const modal = page.getByRole('dialog', { name: 'Add medicines' });
    await expect(modal).toBeVisible();
    const bounds = await modal.boundingBox();
    expect(bounds?.x).toBeGreaterThanOrEqual(0);
    expect((bounds?.x ?? 0) + (bounds?.width ?? 0)).toBeLessThanOrEqual(width);
    await noPageOverflow(page);
    await page.getByRole('button', { name: 'Cancel', exact: true }).click();
    if (width === 375 || width === 1440) {
      await page.goto('/');
      await page.screenshot({ path: `test-results/dashboard-dark-${width}.png`, fullPage: true });
      await page.goto('/pos');
      await expect(page.locator('.product-card').first()).toBeVisible();
      await page.screenshot({ path: `test-results/pos-dark-${width}.png`, fullPage: true });
    }
    expect(errors).toEqual([]);
  });
}

test('mobile drawer closes with backdrop or Escape and contains keyboard focus', async ({
  page,
}) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await login(page);
  const menu = page.getByRole('button', { name: 'Open menu' });
  await menu.click();
  const drawer = page.getByRole('dialog', { name: 'Workspace navigation' });
  await expect(drawer).toBeVisible();
  await expect(page.getByRole('button', { name: 'Close menu', exact: true })).toBeFocused();
  expect(await page.locator('body').evaluate((element) => getComputedStyle(element).overflow)).toBe(
    'hidden',
  );
  await page.keyboard.press('Shift+Tab');
  await expect(drawer.getByRole('button', { name: 'Sign out' })).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(drawer.getByRole('button', { name: 'Close menu', exact: true })).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(menu).toBeFocused();
  await expect(menu).toHaveAttribute('aria-expanded', 'false');
  await menu.click();
  await page.locator('.sidebar-backdrop').click({ position: { x: 330, y: 400 } });
  await expect(menu).toHaveAttribute('aria-expanded', 'false');
  await menu.click();
  await drawer.getByRole('link', { name: 'Medicines', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Medicines', exact: true })).toBeVisible();
  await expect(menu).toHaveAttribute('aria-expanded', 'false');
});

test('login and recovery fit narrow screens, and print stays light in dark mode', async ({
  page,
}) => {
  await page.setViewportSize({ width: 320, height: 700 });
  await page.emulateMedia({ colorScheme: 'dark' });
  for (const route of ['/login', '/forgot-password']) {
    await page.goto(route);
    await noPageOverflow(page);
    await expect(page.getByRole('button', { name: 'Switch to light mode' })).toBeVisible();
  }
  await login(page);
  await page.emulateMedia({ media: 'print' });
  expect(
    await page.locator('body').evaluate((element) => getComputedStyle(element).backgroundColor),
  ).toBe('rgb(255, 255, 255)');
  await expect(page.locator('.topbar')).toBeHidden();
});
