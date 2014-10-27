<?php
/*
    PufferPanel - A Minecraft Server Management Panel
    Copyright (c) 2013 Dane Everitt

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see http://www.gnu.org/licenses/.
 */
namespace PufferPanel\Core;
use \ORM as ORM;

require_once('../../src/core/core.php');

if($core->auth->isLoggedIn($_SERVER['REMOTE_ADDR'], $core->auth->getCookie('pp_auth_token'), $core->auth->getCookie('pp_server_hash')) === false){

	Components\Page::redirect($core->settings->get('master_url').'index.php?login');
	exit();

}

if($core->user->hasPermission('manage.view') !== true)
	Components\Page::redirect('index.php?error=no_permission');

if(isset($_GET['do']) && $_GET['do'] == 'generate_password')
	exit($core->auth->keygen(mt_rand(12, 18)));

/*
 * Display Page
 */
echo $twig->render(
		'node/settings.html', array(
			'server' => array_merge($core->server->getData(), array('server_jar' => (str_replace(".jar", "", $core->server->getData('server_jar'))))),
			'node' => array(
				'fqdn' => $core->server->nodeData('fqdn')
			),
			'footer' => array(
				
				'seconds' => number_format((microtime(true) - $pageStartTime), 4)
			)
	));
?>
