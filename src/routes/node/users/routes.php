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
use \ORM;

$klein->respond('GET', '/node/users/[*]?', function($request, $response, $service, $app, $klein) use ($core) {

	if($core->settings->get('allow_subusers') != 1) {

		$response->code(403);
		$response->body($core->twig->render('node/403.html'))->send();
		$klein->skipRemaining();

	}

});

$klein->respond('GET', '/node/users', function($request, $response, $service) use ($core) {

	$response->body($core->twig->render('node/users/index.html', array(
		'flash' => $service->flashes(),
		'users' => $core->server->listAffiliatedUsers(),
		'server' => $core->server->getData()
	)))->send();

});

$klein->respond('GET', '/node/users/[:action]/[:id]?', function($request, $response, $service) use ($core) {

	if($request->param('action') == 'add') {

		$response->body($core->twig->render('node/users/add.html', array(
			'flash' => $service->flashes(),
			'xsrf' => $core->auth->XSRF(),
			'server' => $core->server->getData()
		)))->send();

	} else if($request->param('action') == 'edit' && $request->param('id')) {

		$user = ORM::forTable('users')->selectMany('permissions', 'email')->where('uuid', $request->param('id'))->findOne();

		if(!$user || empty($user->permissions) || !is_array(json_decode($user->permissions, true))) {

			$service->flash('<div class="alert alert-danger">An error occured when trying to access that subuser.</div>');
			$response->redirect('/node/users')->send();
			return;

		}

		$permissions = json_decode($user->permissions, true);
		if(!array_key_exists($core->server->getData('hash'), $permissions)) {

			$service->flash('<div class="alert alert-danger">An error occured when trying to access that subuser.</div>');
			$response->redirect('/node/users')->send();
			return;

		}

		$response->body($core->twig->render('node/users/edit.html', array(
			'flash' => $service->flashes(),
			'server' => $core->server->getData(),
			'permissions' => $core->user->twigListPermissions($permissions[$core->server->getData('hash')]['perms']),
			'user' => array('email' => $user->email),
			'xsrf' => $core->auth->XSRF()
		)))->send();

	} else if($request->param('action') == 'revoke' && $request->param('id')) {


	}

});

$klein->respond('POST', '/node/users/add', function($request, $response, $service) use ($core) {

	// new \PufferPanel\Core\Router\Node\Users();
	$core->routes = new Router\Router_Controller('Node\Users', $core->user);
	$core->routes = $core->routes->loadClass();

	if(!$core->routes->addSubuser($_POST)) {

		$service->flash($core->routes->retrieveLastError());
		$response->redirect('/node/users')->send();
		return;

	}

});

$klein->respond('POST', '/node/users/edit', function($request, $response, $service) use ($core) {

});