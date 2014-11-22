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
    Components\Page::redirect('../../../index.php?login');

if(!isset($_POST['permissions']))
    Components\Page::redirect('../global.php?error=general_setting');

$query = ORM::forTable('acp_settings')->rawExecute("
UPDATE acp_settings SET setting_val = CASE setting_ref
    WHEN 'use_api' THEN :enable_api
    WHEN 'force_online' THEN :force_online
    WHEN 'https' THEN :https
    WHEN 'allow_subusers' THEN :allow_subusers
    ELSE setting_val
END
", array(
    'enable_api' => (!in_array('use_api', $_POST['permissions'])) ? 0 : 1,
    'force_online' => (!in_array('force_online', $_POST['permissions'])) ? 0 : 1,
    'https' => (!in_array('https', $_POST['permissions'])) ? 0 : 1,
    'allow_subusers' => (!in_array('allow_subusers', $_POST['permissions'])) ? 0 : 1
));

Components\Page::redirect('../global.php');

?>