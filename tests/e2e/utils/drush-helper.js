/**
 * @file
 * Custom Drush helper for test isolation without external dependencies.
 */

const { exec } = require('child_process');
const { promisify } = require('util');

const execAsync = promisify(exec);

/**
 * Execute Drush command with environment-appropriate wrapper.
 *
 * @param {string} command - Drush command to execute
 * @return {Promise<string>} - Command output
 */
async function execDrush(command) {
  try {
    // Check if running in DDEV environment
    const isDdev = process.env.IS_DDEV_PROJECT === 'true';
    const drushCommand = isDdev
      ? `ddev drush ${command}`
      : `vendor/bin/drush ${command}`;
    const workingDir = isDdev ? '/var/www/html' : process.cwd();

    const { stdout, stderr } = await execAsync(drushCommand, {
      cwd: workingDir,
    });

    if (stderr && !stderr.includes('project list')) {
      console.warn('Drush stderr:', stderr);
    }

    return stdout.trim();
  } catch (error) {
    console.error(`Drush command failed: ${command}`, error);
    throw error;
  }
}

/**
 * Create a test admin user.
 */
async function createAdminUser() {
  try {
    // Try to delete existing admin user first (ignore errors)
    try {
      await execDrush('user:cancel admin --delete-content');
    } catch (e) {
      // User doesn't exist, which is fine
    }

    // Create admin user
    await execDrush(
      'user:create admin --mail="admin@example.com" --password="admin"',
    );
    await execDrush('user:role:add administrator admin');

    console.log('Created admin user successfully');
  } catch (error) {
    console.error('Failed to create admin user:', error);
    throw error;
  }
}

/**
 * Enable a module.
 * @param {string} moduleName - Name of the module to enable
 */
async function enableModule(moduleName) {
  try {
    await execDrush(`pm:enable ${moduleName} -y`);
    console.log(`Enabled module: ${moduleName}`);
  } catch (error) {
    console.error(`Failed to enable module ${moduleName}:`, error);
    throw error;
  }
}

/**
 * Clear cache.
 */
async function clearCache() {
  try {
    await execDrush('cache:rebuild');
    console.log('Cache cleared successfully');
  } catch (error) {
    console.error('Failed to clear cache:', error);
    throw error;
  }
}

module.exports = {
  execDrush,
  createAdminUser,
  enableModule,
  clearCache,
};
