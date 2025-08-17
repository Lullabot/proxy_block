/**
 * @file
 * Custom Drush helper for test isolation without external dependencies.
 */

const { exec } = require('child_process');
const { promisify } = require('util');
const fs = require('fs');

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

    let drushCommand;
    let workingDir;

    if (isDdev) {
      drushCommand = `ddev drush ${command}`;
      workingDir = '/var/www/html';
    } else {
      // Find the Drupal root directory
      const currentDir = process.cwd();

      // If we're in the module directory, navigate up to find the Drupal root
      if (currentDir.includes('web/modules/contrib/')) {
        // Extract the path up to the Drupal root
        const drupalRootPath = currentDir.substring(
          0,
          currentDir.indexOf('web/modules/contrib/'),
        );
        // Run drush from Drupal root using php (like main CI workflow)
        drushCommand = `php vendor/bin/drush --root=web ${command}`;
        // Set working directory to the Drupal root (where vendor/ and composer.json are)
        workingDir = drupalRootPath;
      } else {
        // Fallback: assume we're already at the Drupal root
        drushCommand = `php vendor/bin/drush --root=web ${command}`;
        workingDir = currentDir;
      }
    }

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
    // Delete any existing admin user first
    try {
      await execDrush('user:cancel admin --delete-content');
    } catch (e) {
      // User doesn't exist, which is fine
    }

    // Try to create admin user with explicit UID 1 for super admin privileges
    // UID 1 bypasses all permission checks in Drupal
    try {
      await execDrush(
        'user:create admin --mail="admin@example.com" --password="admin" --uid=1',
      );
    } catch (e) {
      // If UID 1 is taken, create without UID specification
      console.warn(
        'Could not create user with UID 1, creating without:',
        e.message,
      );
      await execDrush(
        'user:create admin --mail="admin@example.com" --password="admin"',
      );
    }

    // Add administrator role
    await execDrush('user:role:add administrator admin');

    // Make sure the administrator role has all necessary permissions for block management
    await execDrush('role:perm:add administrator "administer blocks"');
    await execDrush('role:perm:add administrator "administer block layout"');
    await execDrush('role:perm:add administrator "administer themes"');
    await execDrush(
      'role:perm:add administrator "access administration pages"',
    );
    await execDrush(
      'role:perm:add administrator "view the administration theme"',
    );
    await execDrush('role:perm:add administrator "access content"');
    await execDrush(
      'role:perm:add administrator "administer site configuration"',
    );
    await execDrush('role:perm:add administrator "use text format basic_html"');
    await execDrush('role:perm:add administrator "use text format full_html"');

    console.log('Created admin user with comprehensive permissions');
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
    // First check if module is already enabled
    try {
      const output = await execDrush(`pm:list --status=enabled --format=list`);
      if (output.includes(moduleName)) {
        console.log(`Module ${moduleName} is already enabled`);
        return;
      }
    } catch (listError) {
      // If we can't check the status, try to enable anyway
      console.warn(`Could not check module status: ${listError.message}`);
    }

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
