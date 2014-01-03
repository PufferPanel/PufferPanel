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
session_start();
require_once('../core/framework/framework.core.php');

if($core->framework->auth->isLoggedIn($_SERVER['REMOTE_ADDR'], $core->framework->auth->getCookie('pp_auth_token'), true) !== true){
	$core->framework->page->redirect('../index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include('../assets/include/header.php'); ?>
	<title>PufferPanel - Admin CP</title>
</head>
<body>
	<div class="container">
		<?php include('../core/templates/admin_top.php'); ?>
		<div class="row">
			<div class="col-3">
				<?php include('../core/templates/admin_sidebar.php'); ?>
			</div>
			<div class="col-9">
				<?php
					if(is_dir('install'))
						echo '<div class="alert alert-danger"><strong>WARNING!</strong> Please remove the install/ directory from PufferPanel immediately to prevent any possible security holes.</div>';
					else
						echo '<p>Welcome to PufferPanel Admin.</p>';
				?>
			</div>
		</div>
		<script type='text/javascript'>
			$( document ).ready(function() {
				$( "#admin-11" ).addClass( "active" );
			});
		</script>
		<div class="footer">
			<?php include('../assets/include/footer.php'); ?>
		</div>
	</div>
</body>
</html>