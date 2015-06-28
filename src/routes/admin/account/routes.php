<?php
/*
    PufferPanel - A Game Server Management Panel
    Copyright (c) 2015 Dane Everitt

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

$klein->respond('GET', '/admin/account', function($request, $response, $service) use ($core) {

	$users = ORM::forTable('users')->findArray();

	$response->body($core->twig->render(
		'admin/account/find.html',
		array(
			'flash' => $service->flashes(),
			'users' => $users
		)
	))->send();

});

$klein->respond('GET', '/admin/account/new', function($request, $response, $service) use ($core) {

	$response->body($core->twig->render(
		'admin/account/new.html',
		array(
			'flash' => $service->flashes()
		)
	))->send();

});

$klein->respond('POST', '/admin/account/new', function($request, $response, $service) use ($core) {

	if(!preg_match('/^[\w-]{4,35}$/', $request->param('username'))) {

		$service->flash('<div class="alert alert-danger">The username provided is not valid. Usernames must be at least 4 characters, and no more than 35 characters long. Usernames may not contain special characters.</div>');
		$response->redirect('/admin/account/new')->send();
		return;

	}

	if(!filter_var($request->param('email'), FILTER_VALIDATE_EMAIL)) {

		$service->flash('<div class="alert alert-danger">The email provided was not valid.</div>');
		$response->redirect('/admin/account/new')->send();
		return;

	}

	if(!$core->auth->validatePasswordRequirements($request->param('pass')) || $request->param('pass') != $request->param('pass_2')) {

		$service->flash('<div class="alert alert-danger">The password provided did not meet the requirements, or did not match.</div>');
		$response->redirect('/admin/account/new')->send();
		return;

	}

	$query = ORM::forTable('users')->where_any_is(array(array('username' => $_POST['username']), array('email' => $_POST['email'])))->findOne();

	if($query) {

		$service->flash('<div class="alert alert-danger">An account with that username or email already exists in the system.</div>');
		$response->redirect('/admin/account/new')->send();
		return;

	}

	$user = ORM::forTable('users')->create();
	$user->set(array(
		'uuid' => $core->auth->gen_UUID(),
		'username' => $request->param('username'),
		'email' => $request->param('email'),
		'password' => $core->auth->hash($request->param('pass')),
		'language' => Settings::config()->default_language,
		'register_time' => time()
	));
	$user->save();

	/*
     * Send Email
     */
	$core->email->buildEmail('admin_newaccount', array(
		'PASS' => $request->param('pass'),
		'EMAIL' => $request->param('email')
	))->dispatch($request->param('email'), Settings::config()->company_name.' - Account Created');

	$service->flash('<div class="alert alert-success">Account has been successfully created.</div>');
	$response->redirect('/admin/account/view/'.$user->id())->send();

});

$klein->respond('POST', '/admin/account/delete', function($request, $response, $service) use ($core) {
	$servers = ORM::for_table('servers')->where('owner_id', $request->id)->count();
	
	if($servers === 0) {
		$subusers = ORM::for_table('subusers')->where('user', $_POST['id'])->count();
		if($subusers === 0) {
			$user = ORM::for_table('users')->find_one($_POST['id']);
			$user->delete();
			
			$service->flash('<div class="alert alert-success">The account you specified has been deleted successfully.</div>');
			$response->redirect('/admin/account')->send();
		} else {
			$service->flash('<div class="alert alert-danger">The user you specified could not be deleted because the user is a subuser of a server.</div>');
			$response->redirect('/admin/account')->send();
		}
	} else {
		$service->flash('<div class="alert alert-danger">The user you specified could not be deleted because the user has associated server(s).</div>');
		$response->redirect('/admin/account')->send();
	}
});

$klein->respond('GET', '/admin/account/view/[i:id]', function($request, $response, $service) use ($core) {

	if(!$core->user->rebuildData($request->param('id'))) {

		$service->flash('<div class="alert alert-danger">A user with that ID could not be found in the system.</div>');
		$response->redirect('/admin/account')->send();
		return;

	}

	$date1 = new \DateTime(date('Y-m-d', $core->user->getData('register_time')));
	$date2 = new \DateTime(date('Y-m-d', time()));

	$user = $core->user->getData();
	$user['register_time'] = date('F j, Y g:ia', $core->user->getData('register_time')).' ('.$date2->diff($date1)->format("%a Days Ago").')';

	/*
     * Select Servers Owned by the User
     */
	$servers = ORM::forTable('servers')->select('servers.*')->select('nodes.name', 'node_name')
		->join('nodes', array('servers.node', '=', 'nodes.id'))
		->where(array('servers.owner_id' => $core->user->getData('id'), 'servers.active' => 1))
		->findArray();

	$response->body($core->twig->render(
		'admin/account/view.html',
		array(
			'flash' => $service->flashes(),
			'user' => $user,
			'servers' => $servers
		)
	))->send();

});

$klein->respond('POST', '/admin/account/view/[i:id]/[:action]', function($request, $response, $service) use ($core) {

	if(!$core->user->rebuildData($request->param('id'))) {

		$service->flash('<div class="alert alert-danger">A user with that ID could not be found in the system.</div>');
		$response->redirect('/admin/account')->send();
		return;

	}

	if($request->param('action') == 'update') {

		if(!filter_var($request->param('email'), FILTER_VALIDATE_EMAIL)) {

			$service->flash('<div class="alert alert-danger">The email provided was not valid.</div>');
			$response->redirect('/admin/account/view/'.$request->param('id'))->send();
			return;

		}

		$user = ORM::forTable('users')->findOne($request->param('id'));
		$user->set(array(
			'email' => $request->param('email'),
			'root_admin' => $request->param('root_admin')
		));
		$user->save();

		$service->flash('<div class="alert alert-success">Account has been updated.</div>');
		$response->redirect('/admin/account/view/'.$request->param('id'))->send();

	}

	if($request->param('action') == 'password') {

		$user = ORM::forTable('users')->findOne($request->param('id'));
		$user->password = $core->auth->hash($request->param('pass'));

			if($request->param('email_user')) {

				$core->email->buildEmail('new_password_admin', array(
					'NEW_PASS' => $request->param('pass'),
					'EMAIL' => $user->email
				))->dispatch($user->email, Settings::config()->company_name.' - An Admin has Reset Your Password');

			}

			if($request->param('clear_session')) {

				$user->session_id = null;
				$user->session_ip = null;

			}

		$user->save();

		$service->flash('<div class="alert alert-success">Account password has been updated.</div>');
		$response->redirect('/admin/account/view/'.$request->param('id'))->send();

	}

});