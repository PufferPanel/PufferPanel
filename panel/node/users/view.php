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

require_once('../../../src/core/core.php');

if($core->auth->isLoggedIn($_SERVER['REMOTE_ADDR'], $core->auth->getCookie('pp_auth_token'), $core->auth->getCookie('pp_server_hash')) === false){

	Components\Page::redirect($core->settings->get('master_url').'index.php?login');
	exit();
}

if($core->user->hasPermission('users.view') !== true)
	Components\Page::redirect('../index.php?error=no_permission');

$user = ORM::forTable('users')->selectMany('permissions', 'email')->where('uuid', $_GET['id'])->findOne();

	if($user === false)
		Components\Page::redirect('list.php?error');

	if(empty($user->permissions) || !is_array(json_decode($user->permissions, true)))
		Components\Page::redirect('list.php?error');

	$permissions = json_decode($user->permissions, true);
	if(!array_key_exists($core->server->getData('hash'), $permissions))
		Components\Page::redirect('list.php?error');

/*
* Display Page
*/
echo $twig->render(
		'node/users/view.html', array(
			'server' => $core->server->getData(),
			'permissions' => $core->user->twigListPermissions($permissions[$core->server->getData('hash')]),
			'user' => array('email' => $user->email),
			'xsrf' => $core->auth->XSRF(),
			'footer' => array(
				'seconds' => number_format((microtime(true) - $pageStartTime), 4)
			)
	));
?>