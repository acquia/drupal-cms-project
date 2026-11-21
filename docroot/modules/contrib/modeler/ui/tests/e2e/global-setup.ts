import { FullConfig } from '@playwright/test';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Global setup function that runs once before all tests.
 *
 * Use this for:
 * - Starting the test server
 * - Setting up test database
 * - Preparing test fixtures
 * - Authentication setup (if needed)
 */
async function globalSetup(config: FullConfig) {
  console.log('Starting E2E test setup...');

  // Verify the built assets exist
  const { existsSync } = await import('fs');

  const distDir = join(__dirname, '../../../dist');
  const bundleJs = join(distDir, 'modeler.bundle.js');
  const bundleCss = join(distDir, 'modeler.bundle.css');

  if (!existsSync(bundleJs)) {
    console.error('Error: modeler.bundle.js not found. Run "npm run build" first.');
    process.exit(1);
  }

  if (!existsSync(bundleCss)) {
    console.error('Error: modeler.bundle.css not found. Run "npm run build" first.');
    process.exit(1);
  }

  console.log('Built assets verified.');

  // You can start the test server here if needed:
  // const { spawn } = await import('child_process');
  // const server = spawn('npm', ['run', 'e2e:server'], {
  //   cwd: join(__dirname, '..'),
  //   stdio: 'inherit'
  // });
  // process.env.E2E_SERVER_PID = server.pid?.toString();

  console.log('E2E setup complete.');
}

export default globalSetup;
