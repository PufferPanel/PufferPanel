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
require_once('../../../core/framework/framework.core.php');

if($core->framework->auth->isLoggedIn($_SERVER['REMOTE_ADDR'], $core->framework->auth->getCookie('pp_auth_token'), true) !== true){
	$core->framework->page->redirect('../../../index.php');
}

if(isset($_GET['do']) && $_GET['do'] == 'generate_password')
	exit($core->framework->auth->keygen(12));
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>PufferPanel - Create New Server</title>
	
	<!-- Stylesheets -->
	<link href='http://fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet'>
	<link rel="stylesheet" href="../../../assets/css/style.css">
	
	<!-- Optimize for mobile devices -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
	
	<!-- jQuery & JS files -->
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
	<script src="../../../assets/javascript/jquery.cookie.js"></script>
</head>
<body>
	<div id="top-bar">
		<div class="page-full-width cf">
			<ul id="nav" class="fl">
				<li><a href="../../../account.php" class="round button dark"><i class="fa fa-user"></i>&nbsp;&nbsp; <strong><?php echo $core->framework->user->getData('username'); ?></strong></a></li>
			</ul>
			<ul id="nav" class="fr">
				<li><a href="../../../servers.php" class="round button dark"><i class="fa fa-sign-out"></i></a></li>
				<li><a href="../../../logout.php" class="round button dark"><i class="fa fa-power-off"></i></a></li>
			</ul>
		</div>	
	</div>
	<div id="header-with-tabs">
		<div class="page-full-width cf">
		</div>
	</div>
	<div id="content">
		<div class="page-full-width cf">
			<?php include('../../../core/templates/admin_sidebar.php'); ?>
			<div class="side-content fr">
				<div class="content-module">
					<div class="content-module-heading cf">
						<h3 class="fl">Create New Server</h3>
					</div>
					<div class="content-module-main cf">
					<?php 
						
						if(isset($_GET['disp']) && !empty($_GET['disp'])){
						
							switch($_GET['disp']){
							
								case 'missing_args':
									echo '<div class="error-box">Not all arguments were passed by the script.</div>';
									break;
								case 's_fail':
									echo '<div class="error-box">The server name you entered does not meet the requirements. Must be at least 4 characters, and no more than 35. Server name can only contain a-zA-Z0-9_-</div>';
									break;
								case 'n_fail':
									echo '<div class="error-box">The node selected does not seem to exist.</div>';
									break;
								case 'ip_fail':
									echo '<div class="error-box">The selected IP does not exist.</div>';
									break;
								case 'port_fail':
									echo '<div class="error-box">The selected port does not exist.</div>';
									break;
								case 'port_full':
									echo '<div class="error-box">The selected port is already in use.</div>';
									break;
								case 'e_fail':
									echo '<div class="error-box">The email you entered is invalid.</div>';
									break;
								case 'p_fail':
									echo '<div class="error-box">The passwords you entered did not match or were not at least 8 characters.</div>';
									break;
								case 'a_fail':
									echo '<div class="error-box">Account with that email does not exist in the system.</div>';
									break;
								case 'm_fail':
									echo '<div class="error-box">You entered a non-number for Disk and/or Memory.</div>';
									break;
								case 'b_fail':
									echo '<div class="error-box">You entered a non-number for Backup Files and/or Disk Space.</div>';
									break;
								case 'j_fail':
									echo '<div class="error-box">Default JAR does not exist. Create it first</div>';
									break;
							
							}
						
						}
					
					?>
						<fieldset>
							<form action="ajax/new/create.php" method="POST">
								<p>
									<label for="server_name">Server Name</label>
									<input type="text" autocomplete="off" name="server_name" class="round default-width-input" />
									<em>Character Limits: a-zA-Z0-9_- (Max 35 characters)</em>
								</p>
								<p>
									<label for="node">Node</label>
									<select name="node" id="getNode" class="round default-width-input">
										<?php
											$selectData = $mysql->prepare("SELECT * FROM `nodes`");
											$selectData->execute(array());
											while($node = $selectData->fetch()){
											
												echo '<option value="'.$node['id'].'">'.$node['node'].'</option>';
											
											}
										?>
									</select><i class="fa fa-angle-down select-arrow"></i>
								</p>
								<p>
									<label for="email">Owner Email</label>
									<input type="text" autocomplete="off" name="email" value="<?php if(isset($_GET['email'])) echo $_GET['email']; ?>" class="round default-width-input" />
								</p>
								<div class="stripe-separator"><!--  --></div>
								<span id="updateList">
									<p>
										<label for="server_ip">Assign IP Address</label>
										<select name="server_ip" class="round default-width-input">
											<option value="---">Select a Node</option>
										</select><i class="fa fa-angle-down select-arrow"></i>
									</p>
									<p>
										<label for="server_port">Assign Port</label>
										<select name="server_port" class="round default-width-input">
											<option value="---">Select a Node</option>
										</select><i class="fa fa-angle-down select-arrow"></i>
									</p>
								</span>
								<p>
									<label for="email">Allocate Memory (in MB)</label>
									<input type="text" autocomplete="off" name="alloc_mem" class="round default-width-input" />
								</p>
								<p>
									<label for="email">Allocate Disk Space (in MB)</label>
									<input type="text" autocomplete="off" name="alloc_disk" class="round default-width-input" />
								</p>
								<div class="stripe-separator"><!--  --></div>
								<div class="warning-box round" style="display: none;" id="gen_pass"></div>
								<p>
									<label for="pass">SFTP Password (<a href="#" id="gen_pass_bttn">Generate</a>)</label>
									<input type="password" autocomplete="off" name="sftp_pass" class="round default-width-input" />
									<em>Minimum Length 8 characters. Suggested 12.</em>
								</p>
								<p>
									<label for="pass_2">SFTP Password (Again)</label>
									<input type="password" autocomplete="off" name="sftp_pass_2" class="round default-width-input" />
								</p>
								<div class="stripe-separator"><!--  --></div>
								<p>
									<label for="backup_disk">Backup Disk Space (in MB)</label>
									<input type="text" autocomplete="off" name="backup_disk" class="round default-width-input" />
								</p>
								<p>
									<label for="backup_files">Backup Max Files</label>
									<input type="text" autocomplete="off" name="backup_files" class="round default-width-input" />
								</p>
								<div class="stripe-separator"><!--  --></div>
								<p><em>To add a server to this user please go to the add new server page.</em></p>
								<input type="submit" value="Create User" class="round blue ic-right-arrow" />
							</form>
						</fieldset>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script type="text/javascript">
		$("#gen_pass_bttn").click(function(){
			$.ajax({
				type: "GET",
				url: "add.php?do=generate_password",
				success: function(data) {
					$("#gen_pass").html('Generated Password: '+data);
					$("#gen_pass").slideDown();
					$('input[name="sftp_pass"]').val(data);
					$('input[name="sftp_pass_2"]').val(data);
					return false;
				}
			});
			return false;
		});
		function updatePortList(){
			$("#server_ip").change(function() {
			    var ip = $(this).val().replace(/\./g, "\\.");
			    $("[id^=node_]").hide();
			    $("#node_"+ip).show();
			});
		}
		function updateList(){
			var activeNode = $('#getNode').val();
			$.ajax({
				type: "POST",
				url: "ajax/new/load_list.php",
				data: {'node' : activeNode},
				success: function(data) {
					$('#updateList').html(data);
					updatePortList();
					return false;
				}
			});
			return false;
		}
		$(document).ready(function(){
			updateList();
		});
		$('#getNode').change(function(){
			updateList();
		});
		$.urlParam = function(name){
		    var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(decodeURIComponent(window.location.href));
		    if (results==null){
		       return null;
		    }
		    else{
		       return results[1] || 0;
		    }
		}
		if($.urlParam('error') != null){
		
			var field = $.urlParam('error');
			var exploded = field.split('|');
			
				$.each(exploded, function(key, value) {
					
					$('[name="' + value + '"]').addClass('error-input');
					
				});
				
			var obj = $.parseJSON($.cookie('__TMP_pp_admin_newserver'));
			
				$.each(obj, function(key, value) {
					
					$('[name="' + key + '"]').val(value);
					
				});
		
		}
	</script>
	<div id="footer">
		<p>Copyright &copy; 2012 - 2013. All Rights Reserved.<br />Running PufferPanel Version 0.4.2 Beta distributed by <a href="http://pufferfi.sh">Puffer Enterprises</a>.</p>
	</div>
</body>
</html>
