
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
	</script>

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

</body>

</html>