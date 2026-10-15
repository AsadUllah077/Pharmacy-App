import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests/browser',
  fullyParallel: false,
  workers: 1,
  timeout: 30000,
  use: {
    baseURL: 'http://127.0.0.1:8085',
    browserName: 'chromium',
    channel: 'chrome',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: {
    command:
      'php -S 127.0.0.1:8085 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
    cwd: './public',
    url: 'http://127.0.0.1:8085/login',
    reuseExistingServer: true,
  },
});
