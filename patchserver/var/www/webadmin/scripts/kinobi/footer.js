/**
 * Kinobi - external patch source for Jamf Pro
 *
 * @author      Duncan McCracken <duncan.mccracken@mondada.coma.au>
 * @copyright   2018-2019 Mondada Pty Ltd
 * @link        https://mondada.github.io
 * @license     https://github.com/mondada/kinobi/blob/master/LICENSE
 * @version     1.3
 *
 */

var brand_height = "34";

if (subs_type == "Kinobi" || subs_type == "Server") {
	brand_height = "28";
}

$.sessionTimeout({
	"keepAliveUrl": "patchCtl.php",
	"logoutUrl": "logout.php",
	"redirUrl": "logout.php",
	"warnAfter": maxlifetime * 1000 - 60000,
	"redirAfter": maxlifetime * 1000,
	"ignoreUserActivity": true,
	"message": "",
	"countdownMessage": "Your session will expire in {timer}.",
	"keepAliveButton": "Continue",
});

$( document ).ready(function() {
	$( ".navbar-brand" ).append('<img src="images/' + logo_name + '-logo-rev.svg" height="' + brand_height + '">');
	$( "#user-toggle" ).append(username + ' <span class="caret"></span>');
	$( "#about-modal .modal-header" ).append('<img src="images/' + logo_name + '-logo.svg" height="' + logo_height + '">');

	$( "#change-passwd-save" ).click(function() {
		var current_passwd = $( "#current-passwd" ).val()
		var new_passwd = $( "#new-passwd" ).val()
		var new_passwd_verify = $( "#new-passwd-verify" ).val()

		if (new_passwd !== new_passwd_verify || !(/^.{1,128}$/.test(new_passwd) && /^.{1,128}$/.test(new_passwd_verify))) {
			$( "#new-passwd" ).parent("div").addClass("has-error");
			$( "#new-passwd-verify" ).parent("div").addClass("has-error");
			if (new_passwd !== new_passwd_verify) {
				$( "#new-passwd-help" ).removeClass("hidden");
				$( "#new-passwd-verify-help" ).removeClass("hidden");
			} else {
				$( "#new-passwd-help" ).addClass("hidden");
				$( "#new-passwd-verify-help" ).addClass("hidden");
			}
		} else {
			$( "#current-passwd" ).parent("div").removeClass("has-error");
			$( "#current-passwd-help" ).addClass("hidden");
			$.post("patchCtl.php", { "current_passwd": current_passwd, "new_passwd": new_passwd }).done(function(result) {
				if (result) {
					$( "#change-passwd-modal div.form-group" ).addClass("hidden");
					$( "#change-passwd-modal div.panel-success" ).removeClass("hidden");
					$( "#change-passwd-save" ).addClass("hidden");
					$( "#change-passwd-close" ).removeClass("pull-left");
					$( "#change-passwd-close" ).text("Done");
				} else {
					$( "#current-passwd" ).parent("div").addClass("has-error");
					$( "#current-passwd-help" ).removeClass("hidden");
				}
			});
		}
	});

	$( "#change-passwd-modal" ).on("hidden.bs.modal", function (event) {
		$( "#change-passwd-modal div.panel-success" ).addClass("hidden");
		$( "#change-passwd-modal div.form-group" ).removeClass("hidden");
		$( "#change-passwd-modal div.form-group" ).removeClass("has-error");
		$( "#current-passwd-help" ).addClass("hidden");
		$( "#new-passwd-help" ).addClass("hidden");
		$( "#new-passwd-verify-help" ).addClass("hidden");
		$( "#change-passwd-modal input" ).val("");
		$( "#change-passwd-close" ).addClass("pull-left");
		$( "#change-passwd-close" ).text("Cancel");
		$( "#change-passwd-save" ).removeClass("hidden");
	});

	$( "#session-timeout-dialog" ).attr({"data-backdrop": "static", "data-keyboard": "false"});
	$( "#session-timeout-dialog .modal-dialog" ).addClass("modal-sm");
	$( "#session-timeout-dialog .modal-header" ).empty().append('<h3 class="modal-title">Session Expiring</h3>');
	$( "#session-timeout-dialog .modal-body p" ).addClass("text-muted");
	$( "#session-timeout-dialog button" ).addClass("btn-sm");
	$( "#session-timeout-dialog-logout" ).addClass("pull-left");
	$( "#session-timeout-dialog-keepalive" ).addClass("pull-right");
});
