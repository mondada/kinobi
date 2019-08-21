
		</div>
		<!-- /#page-content-wrapper -->

	</div>
	<!-- /#wrapper -->

	<script>
		function showAbout() {
			$('#about-version').removeClass('hidden');
			$('#about-license').addClass('hidden');
			$('#show-license').removeClass('hidden');
			$('#show-version').addClass('hidden');
		}
		function showLicense() {
			$('#about-version').addClass('hidden');
			$('#about-license').removeClass('hidden');
			$('#show-license').addClass('hidden');
			$('#show-version').removeClass('hidden');
		}

		function validChangePass() {
			var current_pass = document.getElementById('current_pass');
			var change_pass = document.getElementById('change_pass');
			var change_verify = document.getElementById('change_verify');
			if (/^.{1,128}$/.test(current_pass.value)) {
				hideError(current_pass, 'current_pass_label');
			} else {
				showError(current_pass, 'current_pass_label');
			}
			if (/^.{1,128}$/.test(change_pass.value)) {
				hideError(change_pass, 'change_pass_label');
			} else {
				showError(change_pass, 'change_pass_label');
			}
			if (/^.{1,128}$/.test(change_verify.value) && change_verify.value == change_pass.value) {
				hideError(change_verify, 'change_verify_label');
			} else {
				showError(change_verify, 'change_verify_label');
			}
			if (/^.{1,128}$/.test(current_pass.value) && /^.{1,128}$/.test(change_verify.value) && change_verify.value == change_pass.value) {
				$('#save_change').prop('disabled', false);
			} else {
				$('#save_change').prop('disabled', true);
			}
		}
	</script>
<?php
// Change Password
if (isset($_POST['save_change'])) {
	$users = getSettingUsers($pdo);
	if ($users[$_SESSION['username']]['password'] == hash("sha256", $_POST['current_pass'])) {
		setSettingUser($pdo, $_SESSION['username'], "password", hash("sha256", $_POST['change_pass'])); ?>
	<script>
		$(document).ready(function() {
			$('#change_success-modal').modal('show');
		});
	</script>
<?php } else { ?>
	<script>
		$(document).ready(function() {
			showError(document.getElementById('current_pass'), 'current_pass_label');
			$('#change_pass-modal').modal('show');
			$('#current_pass_err').removeClass('hidden');
		});
	</script>
<?php }
} ?>
	<!-- About Modal -->
	<div class="modal fade" id="about-modal" tabindex="-1" role="dialog" aria-labelledby="about-label" aria-hidden="true">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header text-center">
					<img src="images/kinobi-logo.svg" height="45">
				</div>
				<div class="modal-body">
					<div id="about-version" class="text-center">
						<p>Version 1.2.1</p>
						<p class="text-muted" style="font-size: 12px;"><a href="https://kinobi.io" target="_blank">kinobi.io</a><br><a href="https://mondada.github.io" target="_blank">mondada.github.io</a></p>
						<p class="text-muted" style="font-size: 12px;">Copyright &copy; 2018-2019 Mondada Pty Ltd.<br>All rights reserved.</p>
					</div>

					<div id="about-license" class="hidden">
						<div class="well well-sm" style="max-height: 254px; overflow-y: scroll"><?php echo file_get_contents("../../kinobi/LICENSE"); ?></div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" id="show-license" class="btn btn-default btn-sm pull-left" onClick="showLicense();">License</button>
					<button type="button" id="show-version" class="btn btn-default btn-sm pull-left hidden" onClick="showAbout();">About</button>
					<button type="button" class="btn btn-default btn-sm pull-right" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
	<!-- /.modal -->

	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="POST" name="UserMenu" id="UserMenu">
		<!-- Change Password Modal -->
		<div class="modal fade" id="change_pass-modal" tabindex="-1" role="dialog">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h3 class="modal-title">Change Password</h3>
					</div>
					<div class="modal-body">
						<h5 id="current_pass_label"><strong>Current Password</strong></h5>
						<div class="form-group">
							<input type="password" name="current_pass" id="current_pass" class="form-control input-sm" aria-describedby="current_pass_err" onFocus="validChangePass();" onKeyUp="validChangePass();" onBlur="validChangePass();" placeholder="[Required]" value=""/>
							<span id="current_pass_err" class="help-block hidden">Incorrect Password</span>
						</div>
						<h5 id="change_pass_label"><strong>New Password</strong></h5>
						<div class="form-group">
							<input type="password" name="change_pass" id="change_pass" class="form-control input-sm" onFocus="validChangePass();" onKeyUp="validChangePass();" onBlur="validChangePass();" placeholder="[Required]" value=""/>
						</div>
						<h5 id="change_verify_label"><strong>Verify Password</strong></h5>
						<div class="form-group">
							<input type="password" name="change_verify" id="change_verify" class="form-control input-sm" onFocus="validChangePass();" onKeyUp="validChangePass();" onBlur="validChangePass();" placeholder="[Required]" value=""/>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
						<button type="submit" name="save_change" id="save_change" class="btn btn-primary btn-sm" disabled>Save</button>
					</div>
				</div>
			</div>
		</div>
		<!-- /.modal -->
	</form>

	<!-- Password Success Modal -->
	<div class="modal fade" id="change_success-modal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h3 class="modal-title">Change Password</h3>
				</div>
				<div class="modal-body">
					<div style="margin-bottom: 1px; border-color: #4cae4c;" class="panel panel-success">
						<div class="panel-body">
							<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span>Successfully changed password for <?php echo $_SESSION['username']; ?>.</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" data-dismiss="modal" class="btn btn-default btn-sm">Close</button>
				</div>
			</div>
		</div>
	</div>
	<!-- /.modal -->

</body>

</html>