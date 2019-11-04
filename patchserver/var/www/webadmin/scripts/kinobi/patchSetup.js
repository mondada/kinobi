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

var modal = "license";

$( document ).ready(function() {
	var logo_name = "kinobi-open-source";
	var logo_height = "36";
	var license_file = "scripts/kinobi/kinobi-open-source-license.html";
	var subs_url = "https://patch.kinobi.io/subscription/"

	if (cloud) {
		logo_name = "kinobi";
		logo_height = "30";
		license_file = "scripts/kinobi/kinobi-license.html";

		$( "#subs-url, #subs-token" ).prop("placeholder", "[Required]");
		$( "#subs-url" ).prop("readonly", true);
	}

	$( ".modal-header" ).append('<img src="images/' + logo_name + '-logo.svg" height="' + logo_height + '">');

	$( "#license-file" ).load(license_file);

	$( "#subs-url" ).val(subs_url);

	$( "#license-agree" ).change(function() {
		$( "#license-next-btn" ).prop("disabled", !this.checked);
	});

	$( "#license-next-btn" ).click(function() {
		if (cloud) {
			$( "#subscription-modal" ).modal("show");
		} else {
			$( "#user-modal" ).modal("show");
		}
		$( "#license-modal" ).modal("hide");
	});

	$( "#subs-back-btn" ).click(function() {
		$( "#license-modal" ).modal("show");
		$( "#subscription-modal" ).modal("hide");
	});

	$( "#subs-next-btn" ).click(function() {
		var subs_url = $( "#subs-url" ).val();
		var subs_token = $( "#subs-token" ).val();

		if (/^(?:http(s)?:\/\/)?[\w.-]+(?:\.[\w\.-]+)+[\w\-\._~:/?#[\]@!\$&'\(\)\*\+,;=.]+$/.test(subs_url)) {
			$( "#subs-url" ).parent("div").removeClass("has-error");
		} else {
			$( "#subs-url" ).parent("div").addClass("has-error");
		}

		$.post("patchCtl.php", { "setup_token": setup_token, "subs_url": subs_url, "subs_token":subs_token }).done(function(result) {
			if (result == "true") {
				$( "#subs-error-alert" ).addClass("hidden");
				$( "#subs-token" ).parent("div").removeClass("has-error");
				$( "#user-modal" ).modal("show");
				$( "#subscription-modal" ).modal("hide");
			}
			if (result == "false") {
				$( "#subs-error-msg" ).html("Invalid token. Please ensure the Token is entered exactly as it was provided.");
				$( "#subs-error-alert" ).removeClass("hidden");
				$( "#subs-token" ).parent("div").addClass("has-error");
			}
			if (result == "Unauthorized") {
				$( "#subs-error-msg" ).html("Unauthorized.");
				$( "#subs-error-alert" ).removeClass("hidden");
				document.location.href='index.php'
			}
		});
	});

	$( "#user-back-btn" ).click(function() {
		if (cloud) {
			$( "#subscription-modal" ).modal("show");
		} else {
			$( "#license-modal" ).modal("show");
		}
		$( "#user-modal" ).modal("hide");
	});

	$( "#user-next-btn" ).click(function() {
		var username = $( "#user-username" ).val();
		var password = $( "#user-password" ).val();
		var verify = $( "#user-verify" ).val();

		if (/^([A-Za-z0-9 ._-]){1,64}$/.test(username) && usernames.indexOf(username) == -1) {
			$( "#user-username" ).parent("div").removeClass("has-error");
		} else {
			$( "#user-username" ).parent("div").addClass("has-error");
			if (usernames.indexOf(username) == -1) {
				$( "#user-username-help" ).addClass("hidden");
			} else {
				$( "#user-username-help" ).removeClass("hidden");
			}
		}
		if (password !== verify) {
			$( "#user-password" ).parent("div").addClass("has-error");
			$( "#user-verify" ).parent("div").addClass("has-error");
			$( "#user-password-help" ).removeClass("hidden");
			$( "#user-verify-help" ).removeClass("hidden");
		} else {
			$( "#user-password-help" ).addClass("hidden");
			$( "#user-verify-help" ).addClass("hidden");
			if (/^.{1,128}$/.test(password)) {
				$( "#user-password" ).parent("div").removeClass("has-error");
				$( "#user-verify" ).parent("div").removeClass("has-error");
			} else {
				$( "#user-password" ).parent("div").addClass("has-error");
				$( "#user-verify" ).parent("div").addClass("has-error");
			}
		}
		if (0 == $("#user-modal div.form-group.has-error" ).length) {
			$( "#apply-setup" ).prop("disabled", false);
			$( "#setup-form" ).submit();
		}
	});

	$( "#" + modal + "-modal" ).modal("show");
});
