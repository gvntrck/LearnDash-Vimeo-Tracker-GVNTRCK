<?php
/**
 * Plugin Name: LearnDash Vimeo Tracker GVNTRCK
 * Plugin URI: https://github.com/gvntrck/LearnDash-Vimeo-Tracker-GVNTRCK
 * Description: Rastreia o tempo de visualização de vídeos Vimeo em cursos LearnDash, salvando o progresso do aluno no banco de dados.
 * Version: 1.9.0
 * Author: GVNTRCK
 * Author URI: https://github.com/gvntrck
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learndash-vimeo-tracker-gvntrck
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/gvntrck/LearnDash-Vimeo-Tracker-GVNTRCK',
    __FILE__,
    'LearnDash-Vimeo-Tracker-GVNTRCK'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

//Optional: If you're using a private repository, specify the access token like this:
$myUpdateChecker->setAuthentication('your-token-here');




define('LDVT_VERSION', '1.9.0');
define('LDVT_PLUGIN_FILE', __FILE__);
define('LDVT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LDVT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LDVT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('LDVT_SETTINGS_OPTION', 'ldvt_settings');
define('LDVT_ADMIN_BOOTSTRAP_VERSION', '5.3.8');

require_once LDVT_PLUGIN_DIR . 'includes/bootstrap.php';
