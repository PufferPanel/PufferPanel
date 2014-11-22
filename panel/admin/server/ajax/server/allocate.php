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

require_once('../src/core/core.php');

if($core->auth->isLoggedIn($_SERVER['REMOTE_ADDR'], $core->auth->getCookie('pp_auth_token'), null, true) !== true)
	Components\Page::redirect('../../../index.php');

if(!isset($_POST['sid']))
	Components\Page::redirect('../../find.php');

$core->server->rebuildData($_POST['sid']);
$core->user->rebuildData($core->server->getData('owner_id'));

/*
 * Validate Disk & Memory
 */
if(!is_numeric($_POST['alloc_mem']) || !is_numeric($_POST['alloc_disk']))
	Components\Page::redirect('../../view.php?id='.$_POST['sid'].'&error=alloc_mem|alloc_disk&disp=m_fail&tab=server_sett');

/*
 *	Validate Server Name
 */
if(!preg_match('/^[\w-]{4,35}$/', $_POST['server_name']))
	Components\Page::redirect('../../view.php?id=1&error=server_name&disp=n_fail&tab=server_sett');

error_log($core->server->nodeData('ip'),0);

/*
 * Check to see if GSD is online
 */
if(!fsockopen($core->server->nodeData('ip'),8003,$errCode,$errStr,3))
	Components\Page::redirect('../../view.php?id='.$_POST['sid'].'&disp=o_fail&tab=server_sett');

$server = ORM::forTable('servers')->findOne($core->server->getData('id'));
$server->name = $_POST['server_name'];
$server->max_ram = $_POST['alloc_mem'];
$server->disk_space = $_POST['alloc_disk'];
$server->save();

/*
 * Build the Data
 */
$url = "http://".$core->server->nodeData('ip').":".$core->server->nodeData('gsd_listen')."/gameservers/".$core->server->getData('gsd_id');
$data = array(
	"variables" => json_encode(array(
		"-Xmx" => $_POST['alloc_mem']."M"
	))
);

/*
 * Run Query Aganist GSD
 */
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($curl, CURLOPT_HTTPHEADER, array(
	'X-Access-Token: '.$core->server->nodeData('gsd_secret')
));
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
curl_exec($curl);
curl_close($curl);

Components\Page::redirect('../../view.php?id='.$_POST['sid'].'&tab=server_sett');
?>
