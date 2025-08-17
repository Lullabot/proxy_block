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
        // Use direct drush.php execution to avoid shell wrapper issues in CI
        drushCommand = `php vendor/drush/drush/drush.php --root=web ${command}`;
        // Set working directory to the Drupal root (where vendor/ and composer.json are)
        workingDir = drupalRootPath;
      } else {
        // Fallback: assume we're already at the Drupal root - use direct drush.php
        drushCommand = `php vendor/drush/drush/drush.php --root=web ${command}`;
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

    // Create admin user (--uid option not supported in this drush version)
    await execDrush(
      'user:create admin --mail="admin@example.com" --password="admin"',
    );

    // Add administrator role
    await execDrush('user:role:add administrator admin');

    // Since Drupal 10.3+, UID 1 bypass is disabled in CI environments
    // We need to explicitly grant ALL necessary permissions for block management

    // First, discover what permissions are actually available
    try {
      const allPerms = await execDrush('role:perm:list --format=yaml');
      console.log(
        'Available permissions sample:',
        allPerms.split('\n').slice(0, 20).join('\n'),
      );
    } catch (e) {
      console.warn('Could not list permissions:', e.message);
    }

    // Core block and theme administration permissions
    await execDrush('role:perm:add administrator "administer blocks"');

    // Try alternative permission names for block layout
    const blockLayoutPermissions = [
      'administer blocks', // This one exists
      'access block library', // Alternative
      'use contextual links', // For block configuration
    ];

    for (const permission of blockLayoutPermissions) {
      try {
        await execDrush(`role:perm:add administrator "${permission}"`);
        console.log(`Added permission: ${permission}`);
      } catch (e) {
        console.log(`Permission "${permission}" not found: ${e.message}`);
      }
    }

    await execDrush('role:perm:add administrator "administer themes"');

    // Essential administration access permissions
    await execDrush(
      'role:perm:add administrator "access administration pages"',
    );
    await execDrush(
      'role:perm:add administrator "view the administration theme"',
    );
    await execDrush(
      'role:perm:add administrator "administer site configuration"',
    );
    await execDrush('role:perm:add administrator "access toolbar"');

    // Content and system permissions
    await execDrush('role:perm:add administrator "access content"');
    await execDrush('role:perm:add administrator "access content overview"');
    await execDrush('role:perm:add administrator "administer content types"');
    await execDrush('role:perm:add administrator "administer nodes"');

    // Text format permissions
    await execDrush('role:perm:add administrator "use text format basic_html"');
    await execDrush('role:perm:add administrator "use text format full_html"');
    await execDrush(
      'role:perm:add administrator "use text format restricted_html"',
    );

    // Menu and URL alias permissions that might be needed
    await execDrush('role:perm:add administrator "administer menu"');
    await execDrush('role:perm:add administrator "administer url aliases"');

    // User administration permissions
    await execDrush('role:perm:add administrator "administer users"');
    await execDrush('role:perm:add administrator "administer permissions"');

    // Module administration permissions
    await execDrush('role:perm:add administrator "administer modules"');

    // Debug: Check what user ID was actually created
    try {
      const userInfo = await execDrush('user:information admin --format=yaml');
      console.log('Admin user info (YAML):', userInfo);
    } catch (e) {
      console.warn('Could not get user info:', e.message);
    }

    // Debug: Check what permissions the administrator role actually has
    try {
      const rolePerms = await execDrush(
        'role:perm:list administrator --format=yaml',
      );
      console.log('Administrator role permissions (YAML):', rolePerms);
    } catch (e) {
      console.warn('Could not get role permissions:', e.message);
    }

    // Debug: Try a specific permission check
    try {
      const permCheck = await execDrush('user:role:list admin --format=yaml');
      console.log('Admin user roles (YAML):', permCheck);
    } catch (e) {
      console.warn('Could not check user roles:', e.message);
    }

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
