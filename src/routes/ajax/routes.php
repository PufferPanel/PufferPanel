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

$klein->respond('POST', BASE_URL.'/ajax/status', function($request, $response) use ($core) {

	if(!$core->auth->isLoggedIn()) {
		$response->body('#FF9900');
	} else {

		if($request->param('server')) {

			$status = ORM::forTable('servers')
				->select('servers.hash', 's_hash')->select('nodes.fqdn')->select('nodes.ip')->select('nodes.daemon_secret')->select('nodes.daemon_listen')->select('servers.id')
				->join('nodes', array('servers.node', '=', 'nodes.id'))
				->where('servers.hash', $request->param('server'))
				->findOne();

			if(!$status) {
				$response->body('#FF9900');
				return;
			}
                        
            $core->daemon->reconstruct($status->id);

			if($core->daemon->check_status() !== 1) {
				$response->body('#E33200');
			} else {
				$response->body('#53B30C');
			}

		} else {
			$response->body('#FF9900');
		}

	}

});

$klein->respond('POST', BASE_URL.'/ajax/status/node', function($request, $response) use ($core) {

	if(!$core->auth->isLoggedIn()) {
		$response->body('#FF9900');
	} else {

		if($request->param('node')) {

			$status = ORM::forTable('nodes')->findOne($request->param('node'));

			if(!$status) {
				$response->body('#FF9900');
				return;
			}

			if(!$core->daemon->avaliable($status->ip, $status->daemon_listen, 1)) {
				$response->body('#E33200');
			} else {
				$response->body('#53B30C');
			}

		} else {
			$response->body('#FF9900');
		}

	}

});

include 'account/routes.php';
