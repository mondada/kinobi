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

var modal = "database";

$( document ).ready(function() {
	if (pdo_error.length) {
		$( "#database-error-alert" ).removeClass("hidden");

		$( "#database-error-msg" ).html(pdo_error);
	}

	$( "#dsn-token" ).val(dsn_token);

	if (cloud) {
		$( "#dsn-retry-btn" ).removeClass("btn-default pull-left").addClass("btn-primary");
	} else {
		$( "#dsn-prefix" ).parents("div.form-group").removeClass("hidden");

		$( "#dsn-prefix" ).selectpicker("val", dsn_json.dsn.prefix);

		if (dsn_json.dsn.prefix == "sqlite") {
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

		$( "#dsn-dbpath" ).val(dsn_json.dsn.dbpath);

		$.each(sqlite_dbs_json, function(index, db) {
			$( "#dsn-dbfile-select" ).append($( "<option>" ).attr("value", db).text(db));
		});
		$( "#dsn-dbfile-select" ).append($( "<option>" ).attr("value", "_NEW_").text("New..."));

		if (null !== dsn_json.dsn.dbpath) {
			$( "#dsn-dbfile-select" ).selectpicker("val", dsn_json.dsn.dbpath.replace(/.*\//, ""));
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

		$( "#dsn-host" ).val(dsn_json.dsn.host);

		$( "#dsn-port" ).val(dsn_json.dsn.port);

		$( "#dsn-dbname" ).val(dsn_json.dsn.dbname);

		$( "#dsn-dbuser" ).val(dsn_json.username);

		$( "#dsn-retry-btn" ).click(function() {
			$( "#dsn-apply" ).prop("disabled", true);
			$( "#dsn-form" ).submit();
		});

		$( "#dsn-apply-btn" ).removeClass("hidden");

		$( "#dsn-apply-btn" ).click(function() {
			var dsn_prefix = $( "#dsn-prefix" ).val();
			var dsn_dbfile_select = $( "#dsn-dbfile-select" ).val();
			var dsn_dbfile = $( "#dsn-dbfile" ).val();
			var dsn_host = $( "#dsn-host" ).val();
			var dsn_port = $( "#dsn-port" ).val();
			var dsn_dbname = $( "#dsn-dbname" ).val();
			var db_username = $( "#dsn-dbuser" ).val();
			var db_passwd = $( "#dsn-dbpass" ).val();

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
					$( "#dsn-apply" ).prop("disabled", false);
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
				if (/^[A-Za-z0-9._]{1,16}$/.test(db_username)) {
					$( "#dsn-dbuser" ).parent("div").removeClass("has-error");
				} else {
					$( "#dsn-dbuser" ).parent("div").addClass("has-error");
				}
				if (/^.{1,64}$/.test(db_passwd)) {
					$( "#dsn-dbpass" ).parent("div").removeClass("has-error");
				} else {
					$( "#dsn-dbpass" ).parent("div").addClass("has-error");
				}
				if (0 == $("#dsn-mysql div.form-group.has-error" ).length) {
					$( "#dsn-apply" ).prop("disabled", false);
					$( "#dsn-form" ).submit();
				}
			}
		});
	}

	$( "#" + modal + "-modal" ).modal("show");
});
