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

var active_tab = localStorage.getItem("activeSettingsTab");
var usernames = $.map(users_json, function(el) { return el.username; });
var tmp_username = null;
var license_file = "scripts/kinobi/kinobi-open-source-license.html";
var logo_name = "kinobi-open-source";
var logo_height = "36";

if (subs_type == "Kinobi" || subs_type == "Server") {
	license_file = "scripts/kinobi/kinobi-license.html";
	logo_name = "kinobi";
	logo_height = "30";
}

if (subs_type == "Kinobi Pro") {
	license_file = "scripts/kinobi/kinobi-pro-license.html";
	logo_name = "kinobi-pro";
}

$( document ).ready(function() {
	if (netsus > 0) {
		$( "title, #heading" ).html("Patch Definitions");
		$( ".breadcrumb" ).prepend('<li><a href="settings.php"><small>Settings</small></a></li>');
		$( ".breadcrumb" ).prepend('<li class="active"><small>Services</small></li>');
		$( "#users-tab-link" ).parent("li").remove();
		$( "#users-tab" ).remove();
		if (netsus > 4) {
			$( "#service-status" ).parents("div.col-xs-3.text-right").removeClass("hidden");
			$( "#dashboard-wrapper, #dashboard-spacer" ).removeClass("hidden");
		}
	} else {
		$( "title, #heading" ).html("Settings");
	}

	$( "#settings" ).addClass("active");

	$( "#service-status" ).prop("checked", service).change();
	$( "#service-status" ).change(function() {
		service = $( this ).prop("checked");
		if (service) {
			$( "#patch" ).removeClass("hidden");
			if (db_json.dsn.prefix == "sqlite" || db_json.dsn.prefix == "mysql" && (db_json.dsn.host == "localhost" || db_json.dsn.host == "127.0.0.1")) {
				$( "#backup-tab-icon, #schedule-warning-alert" ).removeClass("hidden");
				$( "#backup-tab-link" ).css("color", "#d58512");
			}
			$.post("patchCtl.php", { "service": "enable" });
		} else {
			$( "#patch, #backup-tab-icon, #schedule-warning-alert" ).addClass("hidden");
			$( "#backup-tab-link" ).removeAttr("style");
			$( "[name='schedule']" ).prop("checked", false);
			scheduled_json = [];
			$.post("patchCtl.php", { "service": "disable" });
			$.post("patchCtl.php", { "schedule": "" });
		}
		$( "[name='schedule'], #retention, #manual-backup-btn" ).prop("disabled", !service);
	});

	$( "#dashboard-display" ).prop("checked", dashboard);
	$( "#dashboard-display" ).change(function() {
		dashboard = $( this ).prop("checked");
		$.post("patchCtl.php", { "dashboard": dashboard });
	});

	if (cloud) {
		$( "#database-tab-link" ).parent("li").remove();
		$( "#database-tab" ).remove();
		$( ".control-label[for='backups']").html("Available Backups <small>Click the backup filename to restore a backup archive.</small>");
		$( "#subscription-form input, #subscription-form button" ).prop("disabled", true);
		subs_host = "cloud";
	} else {
		$( ".control-label[for='backups']").html('Available Backups <small>Click the backup filename to download or restore a backup archive.<br>Backup archives are saved in <a data-toggle="modal" href="#backup-path-modal"><span style="font-family:monospace;">' + backup_json.path + '</span></a> on this server.</small>');
		$( "#backup-path" ).val(backup_json.path);
		subs_host = "self";
	}

	$( "a[data-toggle='tab']" ).on("show.bs.tab", function(e) {
		localStorage.setItem("activeSettingsTab", $( e.target ).attr("href"));
	});

	$( "#license-modal .modal-header" ).append('<img src="images/' + logo_name + '-logo.svg" height="' + logo_height + '">');
	$( "#license-file" ).load(license_file);
	$( "#license-agree" ).prop("checked", eula_accepted);

	if (!eula_accepted) {
		active_tab = "#subscription-tab";
		$( "#license-agree" ).parent("div").removeClass("hidden");
		$( "#license-close-btn" ).prop("disabled", true);
		$( "#license-modal" ).modal("show");
	}

	$( "#license-agree" ).change(function() {
		eula_accepted = this.checked;
		$.post("patchCtl.php", { "eula_accepted": eula_accepted });
		$( "#license-close-btn" ).prop("disabled", !eula_accepted);
	});

	$( "#license-modal" ).on("hide.bs.modal", function(event) {
		if (eula_accepted) {
			$( "#license-agree" ).parent("div").addClass("hidden");
		}
	});

	if (pdo_error.length && !cloud) {
		active_tab = "#database-tab";
		$( "#database-success-alert" ).addClass("hidden");
		$( "#database-tab-icon, #database-error-alert" ).removeClass("hidden");
		$( "#database-tab-link" ).css("color", "#a94442");
		$( "#database-error-msg" ).html(pdo_error);
		$( "#manual-backup-btn" ).prop("disabled", true);
		$( "#users-tab" ).addClass("disabled");
		$( "#users-tab-link" ).click(function(e) {
			e.preventDefault();
			return false;
		});
	} else {
		$( "#database-success-msg" ).html(pdo_connection_status);
		$( "#database-change-msg" ).html(pdo_connection_status + ". Log out for changes to take effect.");
	}

	if (null === active_tab) {
		active_tab = "#backup-tab";
	}

	$( "#top-tabs a[href='" + active_tab + "']" ).tab("show");

	if (service && scheduled_json.length == 0 && (db_json.dsn.prefix == "sqlite" || db_json.dsn.prefix == "mysql" && (db_json.dsn.host == "localhost" || db_json.dsn.host == "127.0.0.1"))) {
		$( "#patch, #backup-tab-icon, #schedule-warning-alert" ).removeClass("hidden");
		$( "#backup-tab-link" ).css("color", "#d58512");
	}

	if (backup_error.length > 0) {
		$( "#backup-tab-link" ).css("color", "#a94442");
		$( "#backup-tab-icon" ).addClass("glyphicon-exclamation-sign");
		$( "#backup-tab-icon" ).removeClass("glyphicon-warning-sign hidden");
		$( "#backup-error-alert" ).removeClass("hidden");
		$( "#backup-error-msg" ).html(backup_error);
	}

	if (backup_success.length > 0) {
		$( "#backup-success-msg" ).html(backup_success);
		$( "#backup-success-alert" ).removeClass("hidden");
	}

	if (restore_error.length > 0) {
		$( "#restore-tab-link" ).css("color", "#a94442");
		$( "#restore-tab-icon" ).addClass("glyphicon-exclamation-sign");
		$( "#restore-tab-icon" ).removeClass("hidden");
		$( "#restore-error-alert" ).removeClass("hidden");
		$( "#restore-error-msg" ).html(restore_error);
	}

	if (restore_success.length > 0) {
		$( "#restore-success-msg" ).html(restore_success);
		$( "#restore-success-alert" ).removeClass("hidden");
	}

	if (null === subs_json.url && null === subs_json.token) {
		$( "#subs-info-alert" ).removeClass("hidden");
		$( "#subs-info-msg" ).html('Register for a <a target="_blank" href="https://kinobi.io/payment-portal/?register=' + subs_host + '">Kinobi subscription</a> to provide patch definitions.')
	} else if (0 === subs_resp_json.expires) {
		$( "#subscription-tab-link" ).css("color", "#a94442");
		$( "#subscription-tab-icon" ).removeClass("hidden");
		$( "#subs-error-alert" ).removeClass("hidden");
		$( "#subs-error-msg" ).html("Invalid token. Please ensure the Server URL and Token values are entered exactly as they were provided.");
	} else if (subs_resp_json.expires > subs_resp_json.timestamp + (14*24*60*60)) {
		$( "#subs-success-alert" ).removeClass("hidden");
		$( "#subs-success-msg" ).html(subs_resp_json.type + " subscription expires: " + moment.unix(subs_resp_json.expires).utc().format("MMM Do, YYYY") + ".");
	} else if (subs_resp_json.expires > subs_resp_json.timestamp) {
		$( "#subscription-tab-link" ).css("color", "#d58512");
		$( "#subscription-tab-icon" ).addClass("glyphicon-warning-sign");
		$( "#subscription-tab-icon" ).removeClass("glyphicon-exclamation-sign hidden");
		$( "#subs-warning-alert" ).removeClass("hidden");
		$( "#subs-warning-msg" ).html(subs_resp_json.type + ' subscription expires: ' + moment.unix(subs_resp_json.expires).utc().format('MMM Do, YYYY') + '. <a target="_blank" href="' + subs_resp_json.renew + '">Click here to renew</a>.');
	} else {
		$( "#subscription-tab-link" ).css("color", "#a94442");
		$( "#subscription-tab-icon" ).removeClass("hidden");
		$( "#subs-error-alert" ).removeClass("hidden");
		$( "#subs-error-msg" ).html(subs_resp_json.type + ' subscription expired: ' + moment.unix(subs_resp_json.expires).utc().format('MMM Do, YYYY') + '. <a target="_blank" href="' + subs_resp_json.renew + '">Click here to renew</a>.');
	}

	users = $( "#users" ).DataTable( {
		"buttons": [{
			"text": '<span class="glyphicon glyphicon-plus"></span> New',
			"className": "btn-primary btn-sm",
			"action": function(e, dt, node, config) {
				$( "#user-modal" ).modal();
			}
		}],
		"columnDefs": [{
			"targets": 0,
			"data": null,
			"render": function(data, type, row, meta) {
				if (null === row.expires || moment.unix(row.expires) > moment.utc()) {
					return '<div class="checkbox checkbox-primary checkbox-inline"><input type="checkbox" class="styled" value="' + row.username + '"' + (row.disabled !== 1 ? ' checked' : '') + (row.username == current_user ? ' disabled' : '') + '/><label/></div>';
				} else {
					return '<span class="text-danger glyphicon glyphicon-exclamation-sign checkbox-error"></span>';
				}
			}
		}, {
			"targets": 1,
			"data": null,
			"render": function(data, type, row, meta) {
				return '<a data-toggle="modal" href="#user-modal">' + row.username + '</a>'
			}
		}, {
			"targets": 2,
			"data": null,
			"render": function(data, type, row, meta) {
				if (null !== row.token) {
					return row.token;
				} else {
					return "&lt;No Token&gt;";
				}
			}
		}, {
			"targets": 3,
			"data": null,
			"render": function(data, type, row, meta) {
				if (null !== row.expires) {
					return moment.unix(row.expires).utc().format("YYYY-MM-DDTHH:mm:ss") + "Z";
				} else {
					return "&lt;Never&gt;";
				}
			}
		}, {
			"targets": 4,
			"data": null,
			"render": function(data, type, row, meta) {
				if (row.disabled === 1) {
					return "Disabled";
				} else {
					if (row.web === 1 || row.api === 1) {
						return "Full Access";
					} else if (row.api === 0) {
						return "Read Endpoints";
					} else {
						return "No Access";
					}
				}
			}
		}, {
			"targets": -1,
			"className": "text-right",
			"data": null,
			"render": function(data, type, row, meta) {
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-user-modal"' + (row.username == current_user ? ' disabled' : '') + '>Delete</button>';
			}
		}],
		"columns": [{
			"data": null,
			"orderable": false
		}, {
			"data": "username",
		}, {
			"data": "token",
		}, {
			"data": "expires",
			"visible": false
		}, {
			"data": null,
		}, {
			"data": null,
			"orderable": false
		}],
		"data": users_json,
		"dom": '<"row dataTables-header"<"col-xs-6 col-sm-4"f><"hidden-xs col-sm-4"i><"col-xs-6 col-sm-4"B>>' + '<"row dataTables-table"<"col-xs-12"t>>' + '<"row dataTables-footer"<"col-xs-10"p><"col-xs-2"l>>',
		"language": {
			"emptyTable": "No Users",
			"info": "_START_ - _END_ of <strong>_TOTAL_</strong>",
			"infoEmpty": "0 - 0 of 0",
			"lengthMenu": "Show:&nbsp;&nbsp;_MENU_",
			"loadingRecords": "Please wait - loading...",
			"search": " ",
			"searchPlaceholder": "Filter Users",
			"paginate": {
				"previous": '<span class="glyphicon glyphicon-chevron-left"></span>',
				"next": '<span class="glyphicon glyphicon-chevron-right"></span>'
			}
		},
		"lengthMenu": [ [5, 10, 25, -1], [5, 10, 25, "All"] ],
		"order": [ 1, "asc" ],
		"pageLength": 10,
		"stateSave": false
	});

	$( ":input[type=search][aria-controls=users]" ).addClear({
		"symbolClass": "glyphicon glyphicon-remove",
		"onClear": function() {
			users.search("").draw();
		}
	});
	if ($( ":input[type=search][aria-controls=users]" ).val() !== "") {
		users.search("").draw();
	}
	$( "select[name=users_length]" ).addClass("table-select");

	if (users_json.length == 1) {
		$( "#users tbody input, #users tbody button" ).prop("disabled", true);
	}

	if ("undefined" !== typeof subs_resp_json.type && "Server" == subs_resp_json.type) {
		users.column(3).visible(true);
	}

	if (pdo_error.length) {
		users.buttons().disable();
		$( "#subs-url, #subs-token, #subscribe-btn" ).prop("disabled", true);
	}

	$( "#user-expires-datetimepicker" ).datetimepicker({
		format: "YYYY-MM-DDTHH:mm:ss\\Z"
	});

	$( "#user-modal" ).on("hide.bs.modal", function(event) {
		$( "#user-label" ).html("New User");
		$( "#create-user" ).prop("disabled", true);
		$( "#save-user" ).val(null);
		$( "#save-user" ).prop("disabled", true);
		$( "#user-username" ).val("");
		$( "#user-password" ).val("");
		$( "#user-verify" ).val("");
		$( "#user-privileges" ).prop("disabled", false).selectpicker("refresh");
		$( "#user-access" ).prop("disabled", false).selectpicker("refresh");
		$( "#user-access" ).selectpicker("val", 1);
		$( "#user-reset-passwd" ).prop("checked", false);
		$( "#user-privileges" ).selectpicker("val", 1);
		$( "#user-expires" ).val("");
		$( "#user-token" ).prop("checked", true);
		$( "#user-username, #user-password, #user-verify, #user-expires" ).parent("div").removeClass("has-error");
		$( "#user-expires" ).parent("div").removeClass("has-warning");
		$( "#user-username-help, #user-password-help, #user-verify-help" ).addClass("hidden");
		if (tmp_username) {
			usernames.push(tmp_username);
		}
		tmp_username = null;
	});

	$( "#users tbody" ).on("change", "input", function() {
		var data = users.row( $( this ).parents("tr") ).data();
		$.post("patchCtl.php", { "disable_user": this.value, "user_access": (this.checked ? 1 : 0) });
		data.disabled = (this.checked ? null : 1);
		if (data.disabled === 1) {
			access = "Disabled";
		} else {
			if (data.web === 1 || data.api === 1) {
				access = "Full Access";
			} else if (data.api === 0) {
				access = "Read Endpoints";
			} else {
				access = "No Access";
			}
		}
		$( this ).parents("tr").find("td").eq(4).text(access);
	});

	$( "#users tbody" ).on("click", "a", function() {
		var data = users.row( $(this).parents("tr") ).data();
		tmp_username = usernames.splice(usernames.indexOf( data.username ), 1)
		$( "#user-label" ).html(data.username);
		$( "#save-user" ).val(data.username);
		$( "#user-username" ).val(data.username);
		if (data.username == current_user) {
			$( "#user-privileges" ).prop("disabled", true).selectpicker("refresh");
			$( "#user-access" ).prop("disabled", true).selectpicker("refresh");
		}
		$( "#user-access" ).selectpicker("val", (data.disabled === 1) ? 0 : 1);
		$( "#user-reset-passwd" ).prop("checked", data.reset);
		$( "#user-privileges" ).selectpicker("val", (data.web !== null || data.api === 1) ? 1 : (data.api === 0 ? 0 : "none"));
		if (null !== data.expires) {
			$( "#user-expires" ).val(moment.unix(data.expires).utc().format("YYYY-MM-DDTHH:mm:ss") + "Z");
			if (moment.utc() > moment.unix(data.expires)) {
				$( "#user-expires" ).parent("div").addClass("has-warning");
			}
		}
		$( "#user-token" ).prop("checked", false);
	});

	$( "#user-expires" ).on("focus", function() {
		$( this ).parent("div").removeClass("has-error has-warning");
	});

	$( "#save-user-btn" ).click(function() {
		var username = $( "#user-username" ).val();
		var password = $( "#user-password" ).val();
		var verify = $( "#user-verify" ).val();
		var expires = $( "#user-expires" ).val();

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
			if (null === tmp_username) {
				if (/^.{1,255}$/.test(password)) {
					$( "#user-password" ).parent("div").removeClass("has-error");
					$( "#user-verify" ).parent("div").removeClass("has-error");
				} else {
					$( "#user-password" ).parent("div").addClass("has-error");
					$( "#user-verify" ).parent("div").addClass("has-error");
				}
			} else {
				$( "#user-password" ).parent("div").removeClass("has-error");
				$( "#user-verify" ).parent("div").removeClass("has-error");
			}
		}
		if (/^$/.test(expires) || /^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test(expires)) {
			$( "#user-expires" ).parent("div").removeClass("has-error");
		} else {
			$( "#user-expires" ).parent("div").addClass("has-error");
		}
		if (0 == $("#user-modal div.form-group.has-error" ).length) {
			if (tmp_username) {
				$( "#save-user" ).prop("disabled", false);
			} else {
				$( "#create-user" ).prop("disabled", false);
			}
			$( "#users-form" ).submit();
		}
	});

	$( "#users tbody" ).on("click", "button", function() {
		var data = users.row( $(this).parents("tr") ).data();
		$( "#del-user-modal" ).on("show.bs.modal", function(event) {
			$( "#del-user-label" ).text("Delete " + data.username + "?");
			$( "#del-user-msg" ).html('Are you sure you want to delete ' + data.username + '?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-user-btn" ).val(data.username);
		});
	});

	$( "#del-user-modal" ).on("hide.bs.modal", function(event) {
		$( "#del-user-btn" ).val("");
	});

	$( "#dsn-prefix, #backup-type" ).selectpicker("val", db_json.dsn.prefix);

	if (db_json.dsn.prefix == "sqlite") {
		$( "#dsn-sqlite" ).removeClass("hidden");
	} else {
		$( "#dsn-mysql" ).removeClass("hidden");
	}

	$( "#dsn-prefix" ).on("change", function() {
		if ($( this ).val() == "sqlite") {
			$( "#dsn-mysql" ).addClass("hidden");
			$( "#dsn-sqlite" ).removeClass("hidden");
		} else {
			$( "#dsn-sqlite" ).addClass("hidden");
			$( "#dsn-mysql" ).removeClass("hidden");
		}
	});

	$( "#dsn-dbpath" ).val(db_json.dsn.dbpath);

	$.each(sqlite_dbs_json, function(index, db) {
		$( "#dsn-dbfile-select" ).append($( "<option>" ).attr("value", db).text(db));
	});
	$( "#dsn-dbfile-select" ).append($( "<option>" ).attr("value", "_NEW_").text("New..."));

	if (null !== db_json.dsn.dbpath) {
		$( "#dsn-dbfile-select" ).selectpicker("val", db_json.dsn.dbpath.replace(/.*\//, ""));
	} else {
		$( "#dsn-dbfile-select" ).selectpicker("val", "_NEW_");
		$( "#dsn-dbfile" ).parent("div").removeClass("hidden");
	}

	$( "#dsn-dbfile-select" ).on("change", function() {
		if ($( this ).val() == "_NEW_") {
			$( "#dsn-dbfile" ).parent("div").removeClass("hidden");
		} else {
			$( "#dsn-dbfile" ).parent("div").addClass("hidden");
		}
	});

	$( "#dsn-host" ).val(db_json.dsn.host);

	$( "#dsn-port" ).val(db_json.dsn.port);

	$( "#dsn-dbname" ).val(db_json.dsn.dbname);

	$( "#dsn-dbuser" ).val(db_json.username);

	$( "#dsn-save-btn" ).click(function() {
		var dsn_prefix = $( "#dsn-prefix" ).val();
		var dsn_dbfile_select = $( "#dsn-dbfile-select" ).val();
		var dsn_dbfile = $( "#dsn-dbfile" ).val();
		var dsn_host = $( "#dsn-host" ).val();
		var dsn_port = $( "#dsn-port" ).val();
		var dsn_dbname = $( "#dsn-dbname" ).val();
		var dsn_dbuser = $( "#dsn-dbuser" ).val();
		var dsn_dbpass = $( "#dsn-dbpass" ).val();

		$( ".control-label[for='dsn-dbfile']").removeAttr("style");
		$( "#dsn-dbfile" ).parent("div").removeClass("has-error");

		$( ".control-label[for='dsn-host-port']").removeAttr("style");
		$( "#dsn-host, #dsn-port, #dsn-dbname, #dsn-dbuser, #dsn-dbpass" ).parent("div").removeClass("has-error");

		if (dsn_prefix == "sqlite") {
			if (dsn_dbfile_select == "_NEW_") {
				if (/^[A-Za-z0-9._-]{1,64}$/.test(dsn_dbfile) && sqlite_dbs_json.indexOf(dsn_dbfile) == -1) {
					$( "#dsn-dbpath" ).val(sqlite_dir + dsn_dbfile);
				} else {
					$( ".control-label[for='dsn-dbfile']").css("color", "#a94442");
					$( "#dsn-dbfile" ).parent("div").addClass("has-error");
				}
			} else {
				$( "#dsn-dbpath" ).val(sqlite_dir + dsn_dbfile_select);
			}
			if (0 == $("#dsn-sqlite div.form-group.has-error" ).length) {
				$( "#dsn-save" ).prop("disabled", false);
				$( "#dsn-form" ).submit();
			}
		} else {
			if (/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$|^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/.test(dsn_host)) {
				$( "#dsn-host" ).parent("div").removeClass("has-error");
			} else {
				$( "#dsn-host" ).parent("div").addClass("has-error");
			}
			if (/^\d+$/.test(dsn_port) && dsn_port >= 0 && dsn_port <= 65535) {
				$( "#dsn-port" ).parent("div").removeClass("has-error");
			} else {
				$( "#dsn-port" ).parent("div").addClass("has-error");
			}
			if ($("#dsn-host-port div.form-group.has-error" ).length > 0) {
				$( ".control-label[for='dsn-host-port']").css("color", "#a94442");
			}
			if (/^[A-Za-z0-9_]{1,64}$/.test(dsn_dbname)) {
				$( "#dsn-dbname" ).parent("div").removeClass("has-error");
			} else {
				$( "#dsn-dbname" ).parent("div").addClass("has-error");
			}
			if (/^[A-Za-z0-9._]{1,16}$/.test(dsn_dbuser)) {
				$( "#dsn-dbuser" ).parent("div").removeClass("has-error");
			} else {
				$( "#dsn-dbuser" ).parent("div").addClass("has-error");
			}
			if (/^.{1,64}$/.test(dsn_dbpass)) {
				$( "#dsn-dbpass" ).parent("div").removeClass("has-error");
			} else {
				$( "#dsn-dbpass" ).parent("div").addClass("has-error");
			}
			if (0 == $("#dsn-mysql div.form-group.has-error" ).length) {
				$( "#dsn-save" ).prop("disabled", false);
				$( "#dsn-form" ).submit();
			}
		}
	});

	// Backup Schedule
	$.each(scheduled_json, function(index, day) {
		$( ":checkbox[name=schedule][value=" + day + "]" ).prop("checked", true);
	});

	$( ":checkbox[name=schedule]" ).on("change", function() {
		day = parseInt(this.value);
		if (scheduled_json.indexOf(day) >= 0) {
			scheduled_json.splice(scheduled_json.indexOf(day), 1);
		}
		if (this.checked) {
			scheduled_json.push(day);
		}
		scheduled_json.sort();
		$.post("patchCtl.php", { "schedule": scheduled_json.join() });
		if (service && scheduled_json.length == 0 && (db_json.dsn.prefix == "sqlite" || db_json.dsn.prefix == "mysql" && (db_json.dsn.host == "localhost" || db_json.dsn.host == "127.0.0.1"))) {
			$( "#patch, #backup-tab-icon, #schedule-warning-alert" ).removeClass("hidden");
			$( "#backup-tab-link" ).css("color", "#d58512");
		} else {
			$( "#patch, #backup-tab-icon, #schedule-warning-alert" ).addClass("hidden");
			$( "#backup-tab-link" ).removeAttr("style");
		}
	});

	// Backup Retention
	$( "#backup-retention" ).val(backup_json.retention);

	$( "#backup-retention" ).parents("div.form-group").on("click", function() {
		$( this ).removeClass("has-error has-success has-feedback");
		$( ".control-label[for='backup-retention']" ).removeAttr("style");
		$( "#backup-retention" ).val(backup_json.retention);
	});
	$( "#backup-retention" ).on("change", function() {
		if (/^\d+$/.test(this.value) && this.value > 0 && this.value < 31) {
			$.post("patchCtl.php", { "retention": this.value });
			$( "#backup-retention" ).parent("div").addClass("has-success has-feedback");
			backup_json.retention == this.value
		} else {
			$( this ).parent("div").addClass("has-error");
			$( ".control-label[for='backup-retention']").css("color", "#a94442");
		}
	});

	// Backups
	backups = $( "#backups" ).DataTable( {
		"buttons": [{
			"text": '<span class="glyphicon glyphicon-plus"></span> Upload',
			"className": 'btn-primary btn-sm btn-table',
			"action": function(e, dt, node, config) {
				$( "#upload-backup-modal" ).modal();
			}
		}],
		"columnDefs": [{
			"targets": 0,
			"data": null,
			"render": function(data, type, row, meta) {
				return '<div class="dropdown"><a href="#" id="' + row.file + '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">' + row.file + '</a><ul class="dropdown-menu" aria-labelledby="' + row.file + '">' + (cloud ? '' : '<li><a href="patchCtl.php?download=' + row.file + '">Download</a></li>') + '<li' + (pdo_error.length > 0 || row.type.toLowerCase() != db_json.dsn.prefix ? ' class="disabled"><a' : '><a data-toggle="modal" href="#restore-backup-modal"') + '>Restore</a></li></div></ul>';
			}
		}, {
			"targets": 2,
			"data": null,
			"render": function(data, type, row, meta) {
				return moment.unix(row.date).utc().format("YYYY-MM-DDTHH:mm:ss") + "Z";
			}
		}, {
			"targets": 3,
			"data": null,
			"render": function(data, type, row, meta) {
				var units = ["B", "kB", "MB", "GB", "TB"];
				if (row.size == 0) return "0 B";
				var i = parseInt(Math.floor(Math.log(row.size) / Math.log(1024)));
				return Math.round(row.size / Math.pow(1024, i), 2) + " " + units[i];
			}
		}, {
			"targets": -1,
			"className": "text-right",
			"data": null,
			"render": function(data, type, row, meta) {
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-backup-modal">Delete</button>';
			}
		}],
		"columns": [{
			"data": "file"
		}, {
			"data": "type"
		}, {
			"data": "date"
		}, {
			"data": "size"
		}, {
			"data": null,
			"orderable": false
		}],
		"data": backups_json,
		"dom": '<"row dataTables-header"<"col-xs-6 col-sm-4"f><"hidden-xs col-sm-4"i><"col-xs-6 col-sm-4"B>>' + '<"row dataTables-table"<"col-xs-12"t>>' + '<"row dataTables-footer"<"col-xs-10"p><"col-xs-2"l>>',
		"language": {
			"emptyTable": "No Backups",
			"info": "_START_ - _END_ of <strong>_TOTAL_</strong>",
			"infoEmpty": "0 - 0 of 0",
			"lengthMenu": "Show:&nbsp;&nbsp;_MENU_",
			"loadingRecords": "Please wait - loading...",
			"search": " ",
			"searchPlaceholder": "Filter Backups",
			"paginate": {
				"previous": '<span class="glyphicon glyphicon-chevron-left"></span>',
				"next": '<span class="glyphicon glyphicon-chevron-right"></span>'
			}
		},
		"lengthMenu": [ [5, 10, 25, -1], [5, 10, 25, "All"] ],
		"order": [ 2, 'desc' ],
		"pageLength": 10,
		"stateSave": false
	});

	$( ":input[type=search][aria-controls=backups]" ).addClear({
		symbolClass: "glyphicon glyphicon-remove",
		onClear: function() {
			$( "#backups" ).DataTable().search("").draw();
		}
	});

	if ($( ":input[type=search][aria-controls=backups]" ).val() !== "") {
		$( "#backups" ).DataTable().search("").draw();
	}

	$( ":input[type=search]" ).css("margin", 0);
	$( "select[name=backups_length]" ).addClass("table-select");

	$( "#backups tbody" ).on("click", "a", function() {
		var data = backups.row( $(this).parents("tr") ).data();
		$( "#restore-backup-label" ).html("Restore " + data.file + "?");
		$( "#restore-backup-msg" ).html('Are you sure you want to restore ' + data.file + '?');
		$( "#restore-backup-btn" ).val(data.file);
	});

	$( "#backups tbody" ).on("click", "button", function() {
		var data = backups.row( $(this).parents("tr") ).data();
		$( "#del-backup-modal" ).on("show.bs.modal", function(event) {
			$( "#del-backup-label" ).html("Delete " + data.file + "?");
			$( "#del-backup-msg" ).html('Are you sure you want to delete ' + data.file + '?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-backup-btn" ).val(data.file);
		});
	});

	$( ".dataTables_wrapper" ).removeClass("form-inline");

	$( ".dataTables_filter" ).addClass("pull-left form-inline");

	$( ".dt-buttons button" ).css("width", "75px");
	$( ".dt-buttons" ).addClass("pull-right").removeClass("btn-group dt-buttons")
	$( ".btn-primary" ).removeClass("btn-default");

	$( ".dataTables_paginate" ).addClass("pull-left");

	$( ".dataTables_length" ).addClass("pull-right");
	$( ".table-select" ).selectpicker({
		"style": "btn-default btn-sm",
		"width": "65px",
		"container": "body"
	});

	$( "select" ).selectpicker();
	$( ".selectpicker[data-style='btn-default btn-sm']" ).parent("div").css("height", "30px");

	// Subscription
	$( "#subs-url" ).val(subs_json.url);

	$( "#subs-token" ).val(subs_json.token);

	$( "#subscribe-btn" ).click(function() {
		var subs_url = $( "#subs-url" ).val();
		var subs_token = $( "#subs-token" ).val();

		if (/^(?:http(s)?:\/\/)?[\w.-]+(?:\.[\w\.-]+)+[\w\-\._~:/?#[\]@!\$&'\(\)\*\+,;=.]+$/.test(subs_url)) {
			$( "#subs-url" ).parent("div").removeClass("has-error");
		} else {
			$( "#subs-url" ).parent("div").addClass("has-error");
		}

		if (subs_token.length == 32) {
			$( "#subs-token" ).parent("div").removeClass("has-error");
		} else {
			$( "#subs-token" ).parent("div").addClass("has-error");
		}

		if (subs_url.length == 0 && subs_token.length == 0) {
			$( "#subs-url" ).parent("div").removeClass("has-error");
			$( "#subs-token" ).parent("div").removeClass("has-error");
		}

		if (0 == $("#subscription-form div.form-group.has-error" ).length) {
			$( "#subscribe" ).prop("disabled", false);
			$( "#subscription-form" ).submit();
		}
	});

	// Check-In Frequency
	if ("undefined" !== typeof subs_resp_json.type && "Server" !== subs_resp_json.type) {
		$( "#subs-refresh" ).parents("div.page-content-alt").removeClass("hidden");
	}

	$( "#subs-refresh" ).selectpicker("val", subs_json.refresh);

	$( "#subs-refresh" ).parents("div.form-group").on("click", function() {
		$( this ).removeClass("has-error has-success has-feedback");
	});
	$( "#subs-refresh" ).on("change", function() {
		$.post("patchCtl.php", { "subs_refresh": this.value });
		$( "#subs-refresh" ).parents("div.form-group").addClass("has-success has-feedback");
	});

	// Require Endpoint Authentication
	if ("undefined" !== typeof typeof subs_resp_json.type && "Server" === subs_resp_json.type) {
		$( "#user-expires" ).parents("div.form-group").removeClass("hidden");
		$( "#user-privileges" ).parents("div.form-group").removeClass("hidden");
		$( "#api-req-auth" ).parents("div.page-content-alt").removeClass("hidden");
	}

	$( "#api-req-auth" ).prop("checked", api_json.reqauth);

	$( "#api-req-auth" ).on("change", function() {
		$.post("patchCtl.php", { "api_reqauth": this.checked });
	});

	// API Auto-Enable
	if (subs_resp_json.endpoint) {
		$( "#api-auto-enable" ).parents("div.page-content").removeClass("hidden");
		$( "#api-auto-enable" ).prop("checked", api_json.auto);
	}

	$( "#api-auto-enable" ).on("change", function() {
		$.post("patchCtl.php", { "api_auto": this.checked });
	});
});
