<?php
/**
* @package notponies
* @version 1.0.0-dev
* @copyright (c) 2009 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

define('NP_ROOT_PATH', dirname(__FILE__));

if (!file_exists(NP_ROOT_PATH . '/config.php'))
{
	echo 'Missing config.php.';
	exit;
}

// Bootstrap
require NP_ROOT_PATH . '/config.php';
require NP_ROOT_PATH . '/includes/constants.php';
require NP_ROOT_PATH . '/includes/classes/ideas.php';

define('IN_PHPBB', true);

$phpbb_root_path = (!isset($phpbb_root_path)) ? './../' : $phpbb_root_path;
$phpEx = (!isset($phpEx)) ? 'php' : $phpEx;

require $phpbb_root_path . 'common.' . $phpEx;

$user->session_begin();
$auth->acl($user->data);
$user->setup();

spl_autoload_register('ideas::autoload');
