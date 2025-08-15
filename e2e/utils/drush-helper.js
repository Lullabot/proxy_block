/**
 * @file
 * Custom Drush helper for test isolation without external dependencies.
 */

const { exec } = require('child_process');
const { promisify } = require('util');

const execAsync = promisify(exec);

/**
 * Execute Drush command in DDEV environment.
 * 
 * @param {string} command - Drush command to execute
 * @returns {Promise<string>} - Command output
 */
async function execDrush(command) {
  try {
    const { stdout, stderr } = await execAsync(`ddev drush ${command}`, {
      cwd: '/var/www/html'
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
    await execDrush('user:create admin --mail="admin@example.com" --password="admin"');
    await execDrush('user:role:add administrator admin');
    
    console.log('Created admin user successfully');
  } catch (error) {
    console.error('Failed to create admin user:', error);
    throw error;
  }
}

/**
 * Enable a module.
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