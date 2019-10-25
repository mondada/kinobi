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

var logo_name = "kinobi-open-source";
var logo_height = "36";

if (subs_type == "Kinobi" || subs_type == "Server") {
	logo_name = "kinobi";
	logo_height = "30";
}
if (subs_type == "Kinobi Pro") {
	logo_name = "kinobi-pro";
}

$( document ).ready(function() {
	$( ".modal-header" ).append('<img src="images/' + logo_name + '-logo.svg" height="' + logo_height + '">');

	if (login_error.length) {
		$( "#login-error-msg" ).html(login_error);
		$( "#login-error-alert" ).removeClass("hidden");
	}

	$( "#change-passwd" ).val(username);

	$( "#change-passwd-save" ).click(function() {
		var new_passwd = $( "#new-passwd" ).val()
		var new_passwd_verify = $( "#new-passwd-verify" ).val()

		if (new_passwd !== new_passwd_verify) {
			$( "#new-passwd" ).parent("div").addClass("has-error");
			$( "#new-passwd-verify" ).parent("div").addClass("has-error");
			$( "#new-passwd-help" ).removeClass("hidden");
			$( "#new-passwd-verify-help" ).removeClass("hidden");
		} else {
			$( "#new-passwd-help" ).addClass("hidden");
			$( "#new-passwd-verify-help" ).addClass("hidden");
			if (/^.{1,128}$/.test(new_passwd)) {
				$( "#new-passwd" ).parent("div").removeClass("has-error");
				$( "#new-passwd-verify" ).parent("div").removeClass("has-error");
			} else {
				$( "#new-passwd" ).parent("div").addClass("has-error");
				$( "#new-passwd-verify" ).parent("div").addClass("has-error");
			}
		}
		if (0 == $("#change-passwd-modal div.form-group.has-error" ).length) {
			$( "#change-passwd" ).prop("disabled", false);
			$( "#change-passwd-form" ).submit();
		}
	});


	$( "#" + modal + "-modal" ).modal("show");
});
