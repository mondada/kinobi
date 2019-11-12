<?php

/**
 * Kinobi - external patch source for Jamf Pro
 *
 * @author      Duncan McCracken <duncan.mccracken@mondada.coma.au>
 * @copyright   2018-2019 Mondada Pty Ltd
 * @link        https://mondada.github.io
 * @license     https://github.com/mondada/kinobi/blob/master/LICENSE
 * @version     1.3.1
 *
 */

?>

				<!-- About Modal -->
				<div class="modal fade" id="about-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-hidden="true">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header text-center"></div>
							<div class="modal-body">
								<div id="about-version" class="text-center">
									<p>Version 1.3.1</p>
									<p class="text-muted" style="font-size: 12px;"><a href="https://kinobi.io" target="_blank">kinobi.io</a><br><a href="https://mondada.github.io" target="_blank">mondada.github.io</a></p>
									<p class="text-muted" style="font-size: 12px;">Copyright &copy; 2018-2019 Mondada Pty Ltd.<br>All rights reserved.</p>
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" id="show-license" class="btn btn-default btn-sm pull-left" data-dismiss="modal" data-toggle="modal" data-target="#license-modal">License</button>
								<button type="button" class="btn btn-default btn-sm pull-right" data-dismiss="modal">Close</button>
							</div>
						</div><!-- /.modal-content -->
					</div><!-- /.modal-dialog -->
				</div><!-- /.modal -->

				<!-- Change Password Modal -->
				<div class="modal fade" id="change-passwd-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="change-passwd-label" aria-hidden="true">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h3 class="modal-title" id="change-passwd-label">Change Password</h3>
							</div>
							<div class="modal-body">
								<div class="panel panel-success hidden" style="margin-bottom: 1px; border-color: #4cae4c;">
									<div class="panel-body">
										<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span>Your password has been changed.</div>
									</div>
								</div>

								<div class="form-group">
									<label class="control-label" for="current-passwd">Current Password</label>
									<input type="password" autocomplete="off" class="form-control input-sm" name="current_passwd" id="current-passwd" aria-describedby="current-passwd-help" placeholder="[Required]"/>
									<span id="current-passwd-help" class="help-block hidden"><small>Incorrect Password</small></span>
								</div>

								<div class="form-group">
									<label class="control-label" for="new-passwd">New Password</label>
									<input type="password" autocomplete="off" class="form-control input-sm" name="new_passwd" id="new-passwd" aria-describedby="new-passwd-help" placeholder="[Required]"/>
									<span id="new-passwd-help" class="help-block hidden"><small>Did not match</small></span>
								</div>

								<div class="form-group">
									<label class="control-label" for="new-passwd-verify">Verify Password</label>
									<input type="password" autocomplete="off" class="form-control input-sm" name="new_passwd_verify" id="new-passwd-verify" aria-describedby="new-passwd-verify-help" placeholder="[Required]"/>
									<span id="new-passwd-verify-help" class="help-block hidden"><small>Did not match</small></span>
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" id="change-passwd-close" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
								<button type="button" id="change-passwd-save" class="btn btn-primary btn-sm">Save</button>
							</div>
						</div><!-- /.modal-content -->
					</div><!-- /.modal-dialog -->
				</div><!-- /.modal -->

				<!-- bootstrap-session-timeout -->
				<script type="text/javascript" src="scripts/bootstrap-session-timeout/bootstrap-session-timeout.min.js"></script>

				<script type="text/javascript">
					var username = "<?php echo (isset($_SESSION['username']) ? $_SESSION['username'] : ""); ?>";
					var maxlifetime = 900;
				</script>

				<script type="text/javascript" src="scripts/kinobi/footer.js"></script>
			</div><!-- /#page-content-wrapper -->
		</div><!-- /#wrapper -->
	</body>
</html>