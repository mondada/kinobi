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

var name_ids = $.map(titles_json, function(el) { return el.name_id; });
var kinobi_btn_class = ($( "#import_title-modal" ).length ? false : "hidden");
var upload_btn_class = ($( "#upload_title-modal" ).length ? false : "hidden");
var import_btn_class = (kinobi_btn_class && upload_btn_class ? "hidden" : "btn-primary btn-sm");
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
	$( "title, #heading" ).text("Software Titles");
	$( "#patch" ).addClass("active");
	$( "#settings" ).attr("onclick", "document.location.href='patchSettings.php'");

	$( "#license-modal .modal-header" ).append('<img src="images/' + logo_name + '-logo.svg" height="' + logo_height + '">');
	$( "#license-file" ).load(license_file);
	$( "#license-agree" ).prop("checked", eula_accepted);

	if (!eula_accepted) {
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

	var sw_titles = $( "#sw-titles" ).DataTable({
		"buttons": [{
			"extend": "collection",
			"text": '<span class="glyphicon glyphicon-share-alt"></span> Import</span>',
			"className": import_btn_class,
			"autoClose": true,
			"buttons": [{
				"text": "From Kinobi",
				"className": kinobi_btn_class,
				"action": function(e, dt, node, config) {
					$( "#import_title-modal" ).modal();
				}
			}, {
				"text": "Upload JSON",
				"className": upload_btn_class,
				"action": function(e, dt, node, config) {
					$( "#upload_title-modal" ).modal();
				}
			}]
		}, {
			"text": '<span class="glyphicon glyphicon-plus"></span> New',
			"className": "btn-primary btn-sm",
			"action": function(e, dt, node, config) {
				$( "#new-title-modal" ).modal();
			}
		}],
		"columnDefs": [{
			"targets": 0,
			"data": null,
			"render": function(data, type, row, meta) {
				if (row.error.length == 0) {
					return '<div class="checkbox checkbox-primary checkbox-inline"><input type="checkbox" class="styled" value="' + row.id + '"' + (row.enabled ? ' checked' : '') + (row.source_id > 0 ? ' disabled' : '') + '/><label/></div>';
				} else {
					return '<a href="manageTitle.php?id=' + row.id + '"><span class="text-danger glyphicon glyphicon-exclamation-sign checkbox-error"></span></a>';
				}
			}
		}, {
			"targets": 1,
			"data": null,
			"render": function(data, type, row, meta) {
				return '<a href="/webadmin/manageTitle.php?id=' + row.id + '">' + row.name + '</a>';
			}
		}, {
			"targets": 4,
			"data": null,
			"render": function(data, type, row, meta) {
				return moment.unix(row.modified).utc().format("YYYY-MM-DDTHH:mm:ss") + "Z";
			}
		}, {
			"targets": -1,
			"className": "text-right",
			"data": null,
			"render": function(data, type, row, meta) {
				var action = (row.source_id == 0 ? "Delete" : "Remove");
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-title-modal">' + action + '</button>';
			}
		}],
		"columns": [{
			"data": null
		}, {
			"data": "name"
		}, {
			"data": "publisher"
		}, {
			"data": "current"
		}, {
			"data": null
		}, {
			"data": null,
			"orderable": false
		}],
		"data": titles_json,
		"dom": '<"row dataTables-header"<"col-xs-6 col-sm-4"f><"hidden-xs col-sm-4"i><"col-xs-6 col-sm-4"B>>' + '<"row dataTables-table"<"col-xs-12"t>>' + '<"row dataTables-footer"<"col-xs-10"p><"col-xs-2"l>>',
		"language": {
			"emptyTable": "No Titles",
			"info": "_START_ - _END_ of <strong>_TOTAL_</strong>",
			"infoEmpty": "0 - 0 of 0",
			"lengthMenu": "Show:&nbsp;&nbsp;_MENU_",
			"loadingRecords": "Please wait - loading...",
			"search": " ",
			"searchPlaceholder": "Filter Titles",
			"paginate": {
				"previous": '<span class="glyphicon glyphicon-chevron-left"></span>',
				"next": '<span class="glyphicon glyphicon-chevron-right"></span>'
			}
		},
		"lengthMenu": [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
		"order": [ 1, "asc" ],
		"pageLength": 10,
		"stateSave": true
	});

	$( ":input[type=search]" ).css("margin", 0);
	$( ":input[type=search][aria-controls=sw-titles]" ).addClear({
		"symbolClass": "glyphicon glyphicon-remove",
		"onClear": function() {
			sw_titles.search("").draw();
		}
	});
	if ($( ":input[type=search][aria-controls=sw-titles]" ).val() !== "") {
		sw_titles.search("").draw();
	}
	$( "select[name=sw-titles_length]" ).addClass("table-select");

	$( ".dataTables_wrapper" ).removeClass("form-inline");

	$( ".dataTables_filter" ).addClass("pull-left form-inline");

// 	$( ".dataTables_info" ).addClass("text-center");

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

	$( "#sw-titles tbody" ).on("change", "input", function() {
		$.post("patchCtl.php?title_id=" + this.value, { "title_enabled": this.checked });
	});

	$( "#sw-titles tbody" ).on("click", "button", function() {
		var data = sw_titles.row($( this ).parents("tr")).data();
		var action = $( this ).text();

		$( "#del-title-modal" ).on("show.bs.modal", function(event) {
			$( "#del-title-label" ).html(action + " " + data.name + "?");
			if (action == "Remove") {
				$( "#del-title-msg" ).html('Are you sure you want to remove ' + data.name + '?<br><small>' + data.name + ' may be imported again using an active subscription.');
				$( "#del-title-btn" ).removeClass("btn-danger").addClass("btn-primary");
			} else {
				$( "#del-title-msg" ).html('Are you sure you want to delete ' + data.name + '?<br><small>This action is permanent and cannot be undone.</small>');
				$( "#del-title-btn" ).removeClass("btn-primary").addClass("btn-danger");
			}
			$( "#del-title-btn" ).text(action);
			$( "#del-title-btn" ).val(data.id);
		});
	});

	// Maybe: Add real-time validation for individual fields
	// onFocus: Clear Error(s)
	// onKeyUp, onBlur: Validate

	$( "#new-title-btn" ).click(function() {
		var name = $( "#new-name" ).val();
		var publisher = $( "#new-publisher" ).val();
		var app_name = $( "#new-app-name" ).val();
		var bundle_id = $( "#new-bundle-id" ).val();
		var current = $( "#new-current" ).val();
		var name_id = $( "#new-name-id" ).val();

		if (/^.{1,255}$/.test(name)) {
			$( "#new-name" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-name" ).parent("div").addClass("has-error");
		}
		if (/^.{1,255}$/.test(publisher)) {
			$( "#new-publisher" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-publisher" ).parent("div").addClass("has-error");
		}
		if (/^.{0,255}$/.test(app_name)) {
			$( "#new-app-name" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-app-name" ).parent("div").addClass("has-error");
		}
		if (/^.{0,255}$/.test(bundle_id)) {
			$( "#new-bundle-id" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-bundle-id" ).parent("div").addClass("has-error");
		}
		if (/^.{1,255}$/.test(current)) {
			$( "#new-current" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-current" ).parent("div").addClass("has-error");
		}
		if (/^([A-Za-z0-9.-]){1,255}$/.test(name_id) && name_ids.indexOf(name_id) == -1) {
			$( "#new-name-id" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-name-id" ).parent("div").addClass("has-error");
			if (name_ids.indexOf(name_id) == -1) {
				$( "#new-name-id-help" ).addClass("hidden");
			} else {
				$( "#new-name-id-help" ).removeClass("hidden");
			}
		}
		if (0 == $("#new-title-modal div.form-group.has-error" ).length) {
			$( "#create-title" ).prop("disabled", false);
			$( "#titles-form" ).submit();
		}
	});

	if (pdo_error.length) {
		$( "title, #heading" ).html("Database Error");
		$( "#sw-titles_wrapper" ).addClass("hidden");
		$( ".alert-wrapper" ).css("border-bottom", "1px solid #ddd")
		error_msg = pdo_error;
	}

	if (error_msg.length) {
		$( "#error-alert" ).removeClass("hidden");
		$( "#error-msg" ).html(error_msg);
	}

	if (warning_msg.length) {
		$( "#warning-alert" ).removeClass("hidden");
		$( "#warning-msg" ).html(warning_msg);
	}

	if (success_msg.length) {
		$( "#success-alert" ).removeClass("hidden");
		$( "#success-msg" ).html(success_msg);
	}

	$( "select" ).selectpicker();
	$( ".selectpicker[data-style='btn-default btn-sm']" ).parent("div").css("height", "30px");
});
