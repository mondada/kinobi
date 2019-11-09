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

var active_tab = localStorage.getItem("activeTitleTab");
var upload_btn_class = ($( "#upload_patch-modal" ).length ? false : "hidden");
var import_btn_class = (upload_btn_class ? "hidden" : "btn-primary btn-sm");
var patch_versions = $.map(patches_json, function(el) { return el.version; });
var patches_enabled = $.map(patches_json, function(el) { if (el.enabled) { return el.version; } });
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

var criteria_opts = [
	"Application Bundle ID",
	"Application Title",
	"Application Version",
	"Architecture Type",
	"Boot Drive Available MB",
	"Drive Capacity MB",
	"Make",
	"Model",
	"Model Identifier",
	"Number of Processors",
	"Operating System",
	"Operating System Build",
	"Operating System Name",
	"Operating System Version",
	"Optical Drive",
	"Platform",
	"Processor Speed MHz",
	"Processor Type",
	"SMC Version",
	"Total Number of Cores",
	"Total RAM MB"
];

function getOperators(criteria_opt) {
	switch (criteria_opt) {
		case "Application Title":
			return ["is", "is not", "has", "does not have"];
			break;
		case "Operating System Version":
			return ["is", "is not", "like", "not like", "greater than", "less than", "greater than or equal", "less than or equal"];
			break;
		case "Boot Drive Available MB":
		case "Drive Capacity MB":
		case "Number of Processors":
		case "Processor Speed MHz":
		case "Total Number of Cores":
		case "Total RAM MB":
			return ["is", "is not", "more than", "less than"];
			break;
		default:
			return ["is", "is not", "like", "not like", "matches regex", "does not match regex"];
	}
}

function getScriptType(script) {
	for (var t = [["#!/usr/bin/env bash", "sh"], ["#!/bin/sh", "sh"], ["#!/bin/bash", "sh"], ["#!/bin/csh", "sh"], ["#!/usr/bin/perl", "perl"], ["#!/usr/bin/python", "python"]], n = 0; n < t.length; n++)
		if (0 == script.indexOf(t[n][0])) {
			return t[n][1];
		}
	return "text"
}

function setModeEditor(editor) {
	var currentMode = "ace/mode/" + getScriptType(editor.getValue());
	editor.session.setMode(currentMode);
}

$( document ).ready(function() {
	$( "title, #heading" ).html(title_json.name);
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

	$( "a[data-toggle='tab']" ).on("show.bs.tab", function(e) {
		localStorage.setItem("activeTitleTab", $(e.target).attr("href"));
	});

	if (null === active_tab) {
		active_tab = "#title-tab";
	}

	$( "#top-tabs a[href='" + active_tab + "']" ).tab("show");

	$( "#title-name" ).val(title_json.name);
	$( "#title-publisher" ).val(title_json.publisher);
	$( "#title-app-name" ).val(title_json.app_name);
	$( "#title-bundle-id" ).val(title_json.bundle_id);
	$( "#title-current" ).val(title_json.current);
	$( "#title-name-id" ).val(title_json.name_id);

	if (patches_enabled.length > 0) {
		$( "#title-current" ).addClass("hidden");
		$.each(patches_enabled, function(index, version) {
			$( "#title-current-select" ).append($( "<option>" ).attr("value", version).text(version));
		});
		$( "#title-current-select" ).val(title_json.current);
		$( "#title-current-select" ).removeClass("hidden");
	} else {
		patches_error_msg = "At least one patch must be enabled for the definition to be valid.";
		$( "#patches-error-msg" ).html(patches_error_msg);
		$( "#patches-error-alert" ).removeClass("hidden");
		$( "#patches-tab-link" ).css("color", "#a94442");
		$( "#patches-tab-icon" ).removeClass("hidden");
	}

	if (title_json.source_id > 0) {
		$( "#title-name, #title-publisher, #title-app-name, #title-bundle-id, #title-current, #title-current-select, #title-name-id" ).prop("disabled", true);
		if (patches_json.length) {
			$( "#title-override" ).removeClass("hidden");
		}
	}

	if (override) {
		$( "#title-override-checkbox" ).prop("checked", true);
		$( "#title-current-select" ).prop("disabled", false);
	}

	if (!title_json.enabled) {
		title_warning_msg = "This software title is disabled.";
		if (requirements_json.length > 0 && patches_enabled.length > 0) {
			title_warning_msg = title_warning_msg + ' <a href="">Click here to enable it</a>.';
		}
		$( "#title-warning-msg" ).html(title_warning_msg);
		$( "#title-warning-alert" ).removeClass("hidden");
		$( "#spacer-msg" ).html(title_warning_msg);
		$( "#spacer-alert" ).removeClass("hidden");
	}

	$( "#title-warning-alert" ).on("click", "a", function() {
		$.post("patchCtl.php?title_id=" + title_json.id, { "title_enabled": true })
		$( "#title-warning-alert" ).addClass("hidden");
	});

	$( "#title-name" ).on("focus", function() {
		$( this ).val(title_json.name);
		$( this ).parent("div").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='title-name']").removeAttr("style");
	});
	$( "#title-name" ).on("change", function() {
		name = $( this ).val();
		if (/^.{1,255}$/.test(name)) {
			if (name !== title_json.name) {
				$.post("patchCtl.php?id=" + title_json.id, { "table": "titles", "field": "name", "value": name }).done(function(result) {
					if (result) {
						$( "#title-name" ).parent("div").addClass("has-success has-feedback");
						$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
						$( "title, #heading" ).text(name);
						title_json.name = name;
					} else {
						$( "#title-name" ).parent("div").addClass("has-error");
						$( ".control-label[for='title-name']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parent("div").addClass("has-error");
			$( ".control-label[for='title-name']").css("color", "#a94442");
		}
	});

	$( "#title-publisher" ).on("focus", function() {
		$( this ).val(title_json.publisher);
		$( this ).parent("div").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='title-publisher']").removeAttr("style");
	});
	$( "#title-publisher" ).on("change", function() {
		publisher = $( this ).val();
		if (/^.{1,255}$/.test(publisher)) {
			if (publisher !== title_json.publisher) {
				$.post("patchCtl.php?id=" + title_json.id, { "table": "titles", "field": "publisher", "value": publisher }).done(function(result) {
					if (result) {
						$( "#title-publisher" ).parent("div").addClass("has-success has-feedback");
						$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
						title_json.publisher = publisher;
					} else {
						$( "#title-publisher" ).parent("div").addClass("has-error");
						$( ".control-label[for='title-publisher']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parent("div").addClass("has-error");
			$( ".control-label[for='title-publisher']").css("color", "#a94442");
		}
	});

	$( "#title-app-name" ).on("focus", function() {
		$( this ).val(title_json.app_name);
		$( this ).parent("div").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='title-app-name']").removeAttr("style");
	});
	$( "#title-app-name" ).on("change", function() {
		app_name = $( this ).val();
		if (/^.{0,255}$/.test(app_name)) {
			if (app_name !== title_json.app_name) {
				$.post("patchCtl.php?id=" + title_json.id, { "table": "titles", "field": "app_name", "value": app_name }).done(function(result) {
					if (result) {
						$( "#title-app-name" ).parent("div").addClass("has-success has-feedback");
						$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
						title_json.app_name = app_name;
					} else {
						$( "#title-app-name" ).parent("div").addClass("has-error");
						$( ".control-label[for='title-app-name']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parent("div").addClass("has-error");
			$( ".control-label[for='title-app-name']").css("color", "#a94442");
		}
	});

	$( "#title-bundle-id" ).on("focus", function() {
		$( this ).val(title_json.bundle_id);
		$( this ).parent("div").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='title-bundle-id']").removeAttr("style");
	});
	$( "#title-bundle-id" ).on("change", function() {
		bundle_id = $( this ).val();
		if (/^.{0,255}$/.test(bundle_id)) {
			if (bundle_id !== title_json.bundle_id) {
				$.post("patchCtl.php?id=" + title_json.id, { "table": "titles", "field": "bundle_id", "value": bundle_id }).done(function(result) {
					if (result) {
						$( "#title-bundle-id" ).parent("div").addClass("has-success has-feedback");
						$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
						title_json.bundle_id = bundle_id;
					} else {
						$( "#title-bundle-id" ).parent("div").addClass("has-error");
						$( ".control-label[for='title-bundle-id']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parent("div").addClass("has-error");
			$( ".control-label[for='title-bundle-id']").css("color", "#a94442");
		}
	});

	$( "#title-current" ).on("focus", function() {
		$( this ).val(title_json.current);
		$( this ).parents("div.form-group").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='title-current']").removeAttr("style");
	});
	$( "#title-current-select" ).parents("div.form-group").on("click", function() {
		$( "#title-current-select" ).val(title_json.current);
		$( this ).removeClass("has-error has-success has-feedback");
		$( ".control-label[for='title-current']").removeAttr("style");
	});
	$( "#title-current, #title-current-select" ).on("change", function() {
		current = $( this ).val();
		if (/^.{1,255}$/.test(current)) {
			if (current !== title_json.current) {
				if ($( "#title-override-checkbox" ).prop("checked")) {
					$.post("patchCtl.php?override=" + title_json.name_id, { "current": current }).done(function(result) {
						if (result) {
							$( "#title-current, #title-current-select" ).parents("div.form-group").addClass("has-success has-feedback");
							$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
							title_json.current = current;
						} else {
							$( "#title-current" ).parents("div.form-group").addClass("has-error");
							$( ".control-label[for='title-current']").css("color", "#a94442");
						}
					});
				} else {
					$.post("patchCtl.php?id=" + title_json.id, { "table": "titles", "field": "current", "value": current }).done(function(result) {
						if (result) {
							$( "#title-current, #title-current-select" ).parents("div.form-group").addClass("has-success has-feedback");
							$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
							title_json.current = current;
						} else {
							$( "#title-current" ).parents("div.form-group").addClass("has-error");
							$( ".control-label[for='title-current']").css("color", "#a94442");
						}
					});
				}
			}
		} else {
			$( this ).parents("div.form-group").addClass("has-error");
			$( ".control-label[for='title-current']").css("color", "#a94442");
		}
	});

	$( "#title-override-checkbox" ).on("change", function() {
		$( "#title-current-select" ).prop("disabled", !this.checked);
		if (this.checked) {
			$.post("patchCtl.php?override=" + title_json.name_id, { "current": title_json.current });
		} else {
			$.post("patchCtl.php?override=" + title_json.name_id, { "current": null });
			$( "#title-current-select" ).val(patches_json[0].version);
		}
		$( ".selectpicker" ).selectpicker("refresh");
	});

	$( "#title-name-id" ).on("focus", function() {
		name_ids.splice(name_ids.indexOf( title_json.name_id ), 1)
		$( this ).val(title_json.name_id);
		$( this ).parent("div").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='title-name-id']").removeAttr("style");
		$( "#title-name-id-help" ).addClass("hidden");
	});
	$( "#title-name-id" ).on("change", function() {
		name_id = $( this ).val();
		if (/^([A-Za-z0-9._-]){1,255}$/.test(name_id) && name_ids.indexOf(name_id) == -1) {
			if (name_id !== title_json.name_id) {
				$.post("patchCtl.php?id=" + title_json.id, { "table": "titles", "field": "name_id", "value": name_id }).done(function(result) {
					if (result) {
						$( "#title-name-id" ).parent("div").addClass("has-success has-feedback");
						$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
						title_json.name_id = name_id;
					} else {
						$( "#title-name-id" ).parent("div").addClass("has-error");
						$( ".control-label[for='title-name-id']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parent("div").addClass("has-error");
			if (name_ids.indexOf(name_id) == -1) {
				$( "#title-name-id-help" ).addClass("hidden");
			} else {
				$( "#title-name-id-help" ).removeClass("hidden");
			}
			$( ".control-label[for='title-name-id']").css("color", "#a94442");
		}
	});
	$( "#title-name-id" ).on("blur", function() {
		name_ids.push(title_json.name_id);
	});

	// Extension Attributes
	var tmp_key_id = null;

	var ea_editor = ace.edit("ea-editor");
	ea_editor.setShowPrintMargin(false);
	ea_editor.getSession().on("change", function(e) {
		setModeEditor(ea_editor);
	});

	var ext_attrs = $( "#ext-attrs" ).DataTable({
		"buttons": [{
			"text": '<span class="glyphicon glyphicon-plus"></span> New',
			"className": "btn-primary btn-sm",
			"action": function( e, dt, node, config ) {
				$( "#edit-ea-label" ).text("New Extension Attribute");
				$( "#ea-name" ).val("");
				$( "#ea-key-id" ).val("");
				$( "#save-ea" ).val("");
				$( "#edit-ea-modal" ).modal("show");
			}
		}],
		"columnDefs": [{
			"targets": 0,
			"data": null,
			"render": function(data, type, row, meta) {
				return '<a href="#edit-ea-modal" data-toggle="modal">' + row.name + '</a>';
			}
		}, {
			"targets": -1,
			"className": "text-right",
			"data": null,
			"render": function(data, type, row, meta) {
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-ea-modal">Delete</button>';
			}
		}],
		"data": ext_attrs_json,
		"dom": '<"row dataTables-header"<"col-xs-12"B>>' + '<"row dataTables-table"<"col-xs-12"t>>',
		"info": false,
		"language": {
			"emptyTable": "No Extension Attributes",
			"loadingRecords": "Please wait - loading...",
		},
		"lengthChange": false,
		"order": [ 0, "asc" ],
		"paging": false,
		"searching": false,
		"stateSave": true
	});

	if (ext_attrs_json.length || title_json.source_id > 0) {
		ext_attrs.buttons().disable();
	}

	if (title_json.source_id > 0) {
		$( "#ext-attrs button" ).prop("disabled", true);
		$( "#ea-name, #ea-key-id, #save-ea-btn" ).prop("disabled", true);
	}

	$( "#edit-ea-modal" ).on("show.bs.modal", function(event) {
		ea_editor.navigateFileStart();
		if (title_json.source_id > 0) {
			ea_editor.setReadOnly(true);
			ea_editor.session.setMode("ace/mode/text");
		}
	});

	$( "#edit-ea-modal" ).on("hide.bs.modal", function(event) {
		$( "#ea-name" ).parent("div").removeClass("has-error");
		$( "#ea-key-id-help" ).addClass("hidden");
		$( "#ea-key-id" ).parent("div").removeClass("has-error");
		if (tmp_key_id) {
			key_ids.push(tmp_key_id);
		}
		tmp_key_id = null;
		ea_editor.setValue("");
	});

	$( "#ext-attrs tbody" ).on("click", "a", function() {
		var data = ext_attrs.row( $(this).parents("tr") ).data();
		tmp_key_id = key_ids.splice(key_ids.indexOf( data.key_id ), 1)
		$( "#edit-ea-label" ).text(data.name);
		$( "#ea-name" ).val(data.name);
		$( "#ea-key-id" ).val(data.key_id);
		$( "#save-ea" ).val(data.id);
		ea_editor.setValue(data.script);
	});

	$( "#cancel-ea-btn" ).click(function() {
		$( "#edit-ea-modal" ).modal("hide");
	});

	$( "#save-ea-btn" ).click(function() {
		var name = $( "#ea-name" ).val();
		var key_id = $( "#ea-key-id" ).val();
		if (/^.{1,255}$/.test(name)) {
			$( "#ea-name" ).parent("div").removeClass("has-error");
		} else {
			$( "#ea-name" ).parent("div").addClass("has-error");
		}
		if (/^([A-Za-z0-9.-]){1,255}$/.test(key_id) && key_ids.indexOf(key_id) == -1) {
			$( "#ea-key-id" ).parent("div").removeClass("has-error");
		} else {
			$( "#ea-key-id" ).parent("div").addClass("has-error");
			if (key_ids.indexOf(key_id) == -1) {
				$( "#ea-key-id-help" ).addClass("hidden");
			} else {
				$( "#ea-key-id-help" ).removeClass("hidden");
			}
		}
		if (0 == $("#edit-ea-modal div.form-group.has-error" ).length) {
			$( "#ea-script" ).val(ea_editor.getValue());
			if (tmp_key_id) {
				$( "#save-ea" ).prop("disabled", false);
			} else {
				$( "#create-ea" ).prop("disabled", false);
			}
			$( "#ext-attrs-form" ).submit();
		}
	});

	$( "#ext-attrs tbody" ).on("click", "button", function() {
		var data = ext_attrs.row( $(this).parents("tr") ).data();
		$( "#del-ea-modal" ).on("show.bs.modal", function(event) {
			$( "#del-ea-label" ).text("Delete " + data.name + "?");
			$( "#del-ea-msg" ).html('Are you sure you want to delete ' + data.name + '?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-ea-btn" ).val(data.id);
		});
	});

	$( "#del-ea-modal" ).on("hide.bs.modal", function(event) {
		$( "#del-ea-btn" ).val("");
	});

	if (title_json.source_id > 0) {
		$( "#ext-attrs button" ).prop("disabled", true);
		$( "#ea-name, #ea-key-id, #save-ea-btn" ).prop("disabled", true);
	}

	// Requirements
	var requirements = $( "#requirements" ).DataTable({
		"columnDefs": [{
			"targets": 0,
			"data": null,
			"render": function(data, type, row, meta) {
				return '<div class="form-group' + (row.sort_order == 0 ? ' hidden' : '') + '"><select class="selectpicker is_and" data-style="btn-default btn-sm" data-width="100%" data-container="body"><option value="1">and</option><option value="0">or</option></select></div>';
			}
		}, {
			"targets": 1,
			"data": null,
			"render": function(data, type, row, meta) {
				return '<div class="form-group"><select class="selectpicker criteria" data-style="btn-default btn-sm" data-width="100%" data-container="body"></select></div>';
			}
		}, {
			"targets": 2,
			"data": null,
			"render": function(data, type, row, meta) {
				return '<div class="form-group"><select class="selectpicker operator" data-style="btn-default btn-sm" data-width="100%" data-container="body"></select></div>';
			}
		}, {
			"targets": 3,
			"data": null,
			"render": function(data, type, row, meta) {
				return '<div class="form-group"><input type="text" class="form-control input-sm" value="' + row.value + '"/></div>';
			}
		}, {
			"targets": -1,
			"className": "text-right",
			"data": null,
			"render": function(data, type, row, meta) {
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-rqmt-modal">Delete</button>';
			}
		}],
		"data": requirements_json,
		"dom": '<"row dataTables-table"<"col-xs-12"t>>',
		"info": false,
		"lengthChange": false,
		"ordering": false,
		"paging": false,
		"searching": false
	});

	if (!requirements_json.length) {
		rqmts_error_msg = "At least one requirement is required for the definition to be valid.";
		$( "#rqmts-error-msg" ).html(rqmts_error_msg);
		$( "#rqmts-error-alert" ).removeClass("hidden");
		$( "#rqmts-tab-link" ).css("color", "#a94442");
 		$( "#rqmts-tab-icon" ).removeClass("hidden");
		$( "#requirements tbody" ).addClass("hidden");
	}

	if (!title_json.enabled) {
		if (requirements_json.length > 0 && patches_enabled.length > 0) {
			title_warning_msg = title_warning_msg + ' <a href="">Click here to enable it</a>.';
		}
	}

	$( "#requirements tbody select.is_and" ).each(function(index) {
		var data = requirements.row($( this ).parents("tr")).data();
		$( this ).selectpicker("val", data.is_and);
	});

	$( "#requirements tbody select.criteria" ).each(function(index) {
		var data = requirements.row($( this ).parents("tr")).data();
		var criteria_select = $( this );
		$.map(ext_attrs_json, function(ea) {
			criteria_select.append($( "<option>" ).attr("value", ea.key_id).text(ea.name));
		});
		$.each(criteria_opts, function(index, value) {
			criteria_select.append($( "<option>" ).attr("value", value).text(value));
		});
		$( this ).selectpicker("val", data.name);
	});

	$( "#requirements tbody select.operator" ).each(function(index) {
		var data = requirements.row($( this ).parents("tr")).data();
		var operator_opts = getOperators(data.name);
		var operator_select = $( this );
		$.each(operator_opts, function(index, value) {
			operator_select.append($( "<option>" ).attr("value", value).text(value));
		});
		$( this ).selectpicker("val", data.operator);
	});

	if (title_json.source_id > 0) {
		$( "#requirements select, #requirements input, #requirements button" ).prop("disabled", true);
	}

	$( "#requirements tbody .bootstrap-select" ).on("click", function() {
		$( this ).parents("div.form-group").removeClass("has-error has-warning has-success has-feedback").find("span.form-control-feedback").remove();
	});

	$( "#requirements tbody select.is_and" ).on("change", function() {
		var element = $( this );
		var data = requirements.row(element.parents("tr")).data();
		$.post("patchCtl.php?id=" + data.id, { "table": "requirements", "field": "is_and", "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
				data.is_and = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#requirements tbody select.criteria" ).on("change", function() {
		var element = $( this );
		var data = requirements.row(element.parents("tr")).data();
		$.post("patchCtl.php?id=" + data.id, { "table": "requirements", "field": "name", "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
				var operator_opts = getOperators(element.val());
				var operator_select = element.parents("tr").find("select.operator");
				operator_select.empty();
				$.each(operator_opts, function(index, value) {
					operator_select.append($( "<option>" ).attr("value", value).text(value));
				});
				operator_select.selectpicker("refresh");
				if (operator_opts.indexOf(data.operator) == -1) {
					operator_select.selectpicker("val", operator_opts[0]);
					operator_select.parents("div.form-group").addClass("has-warning has-feedback").find("span.form-control-feedback").remove();
					operator_select.parents("div.form-group").append('<span class="glyphicon glyphicon-warning-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
					$.post("patchCtl.php?id=" + data.id, { "table": "requirements", "field": "operator", "value": operator_opts[0] });
					data.operator = operator_opts[0];
				} else {
					operator_select.selectpicker("val", data.operator);
				}
				if (criteria_opts.indexOf(element.val()) == -1) {
					type = "extensionAttribute";
				} else {
					type = "recon";
				}
				if (data.type != type) {
					$.post("patchCtl.php?id=" + data.id, { "table": "requirements", "field": "type", "value": type });
					data.type = type;
				}
				data.name = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#requirements tbody select.operator" ).on("change", function() {
		var element = $( this );
		var data = requirements.row(element.parents("tr")).data();
		$.post("patchCtl.php?id=" + data.id, { "table": "requirements", "field": "operator", "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
				data.operator = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#requirements tbody input" ).on("focus", function() {
		var data = requirements.row($( this ).parents("tr")).data();
		$( this ).val(data.value);
		$( this ).parents("div.form-group").removeClass("has-error has-warning has-success has-feedback").find("span.form-control-feedback").remove();
	});
	$( "#requirements tbody input" ).on("change", function() {
		var element = $( this );
		var data = requirements.row(element.parents("tr")).data();
		if (/^.{0,255}$/.test(element.val())) {
			$.post("patchCtl.php?id=" + data.id, { "table": "requirements", "field": "value", "value": element.val() }).done(function(result) {
				if (result) {
					element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
					$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
					data.value = element.val();
				} else {
					element.parents("div.form-group").addClass("has-error");
				}
			});
		} else {
			element.parents("div.form-group").addClass("has-error");
		}
	});

	$( "#requirements tbody" ).on("click", "button", function() {
		var data = requirements.row( $(this).parents("tr") ).data();
		$( "#del-rqmt-modal" ).on("show.bs.modal", function(event) {
// 			$( "#del-rqmt-msg" ).html('Are you sure you want to delete <em>' + data.name + ' ' + data.operator + ' ' + (data.value.length ? data.value : '""') + '</em> ?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-rqmt-btn" ).val(data.id);
		});
	});

	$.map(ext_attrs_json, function(ea) {
		$( "#new-rqmt-name" ).append($( "<option>" ).attr("value", ea.key_id).text(ea.name));
	});
	$.each(criteria_opts, function(index, value) {
		$( "#new-rqmt-name" ).append($( "<option>" ).attr("value", value).text(value));
	});

	$( "#new-rqmt-modal" ).on("show.bs.modal", function(event) {
		$( "#new-rqmt-name" ).selectpicker("val", " ");
		$( "#new-rqmt-operator" ).val("is");
		$( "#new-rqmt-value" ).val("");
		$( "#new-rqmt-type" ).val("recon");
		$( "#new-rqmt-is-and" ).val(1);
		$( "#new-rqmt-sort-order" ).val((requirements_json.length ? parseInt(requirements_json[requirements_json.length - 1].sort_order) + 1 : 0));
		if (ext_attrs_json.length == 0 && requirements_json.length == 0) {
			$( "#new-rqmt-name" ).selectpicker("val", "Application Bundle ID");
			$( "#new-rqmt-value" ).val(title_json.bundle_id);
		} else if (ext_attrs_json.length == 1 && requirements_json.length == 0) {
			$( "#new-rqmt-name" ).selectpicker("val", ext_attrs_json[0].key_id);
			$( "#new-rqmt-type" ).val("extensionAttribute");
		} else {
			$( "#new-rqmt-btn" ).prop("disabled", true);
		}
	});

	$( "#new-rqmt-name" ).on("change", function() {
		$( "#new-rqmt-btn" ).prop("disabled", $( "#new-rqmt-name" ).val() == " ");
	});

	$( "#del-rqmt-modal" ).on("hide.bs.modal", function(event) {
		$( "#del-rqmt-btn" ).val("");
	});

	patches = $( "#patches" ).DataTable({
		"buttons": [{
			"extend": "collection",
			"text": '<span class="glyphicon glyphicon-share-alt"></span> Import',
			"className": import_btn_class,
			"autoClose": true,
			"buttons": [{
				"text": "Upload JSON",
				"className": upload_btn_class,
				"action": function ( e, dt, node, config ) {
					$( "#upload_patch-modal" ).modal();
				}
			}]
		}, {
			"text": '<span class="glyphicon glyphicon-plus"></span> New',
			"className": "btn-primary btn-sm",
			"action": function ( e, dt, node, config ) {
				$( "#new-patch-modal" ).modal();
			}
		}],
		"columnDefs": [{
			"targets": 0,
			"data": null,
			"render": function(data, type, row, meta) {
				if (row.error.length == 0) {
					return '<div class="checkbox checkbox-primary checkbox-inline"><input type="checkbox" class="styled" value="' + row.id + '"' + (row.enabled ? ' checked' : '') + (title_json.source_id > 0 ? ' disabled' : '') + '/><label/></div>';
				} else {
					return '<a href="managePatch.php?id=' + row.id + '"><span class="text-danger glyphicon glyphicon-exclamation-sign checkbox-error"></span></a>';
				}
			}
		}, {
			"targets": 2,
			"data": null,
			"render": function(data, type, row, meta) {
				return '<a href="managePatch.php?id=' + row.id + '">' + row.version + '</a>';
			}
		}, {
			"targets": 3,
			"data": null,
			"render": function(data, type, row, meta) {
				return moment.unix(row.released).utc().format("YYYY-MM-DDTHH:mm:ss") + "Z";
			}
		}, {
			"targets": 4,
			"data": null,
			"render": function(data, type, row, meta) {
				return (row.standalone == 0 ? "No" : "Yes");
			}
		}, {
			"targets": 5,
			"data": null,
			"render": function(data, type, row, meta) {
				return (row.reboot == 0 ? "No" : "Yes");
			}
		}, {
			"targets": -1,
			"className": "text-right",
			"data": null,
			"render": function(data, type, row, meta) {
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-patch-modal"' + (title_json.source_id > 0 ? ' disabled' : '') + '>Delete</button>';
			}
		}],
		"columns": [{
			"data": "enabled"
		}, {
			"data": "sort_order"
		}, {
			"data": "version"
		}, {
			"data": "released"
		}, {
			"data": "standalone"
		}, {
			"data": "reboot"
		}, {
			"data": "min_os"
		}, {
			"data": null,
			"orderable": false
		}],
		"data": patches_json,
		"dom": '<"row dataTables-header"<"col-xs-6 col-sm-4"f><"hidden-xs col-sm-4"i><"col-xs-6 col-sm-4"B>>' + '<"row dataTables-table"<"col-xs-12"t>>' + '<"row dataTables-footer"<"col-xs-10"p><"col-xs-2"l>>',
		"language": {
			"emptyTable": "No Patches",
			"info": "_START_ - _END_ of <strong>_TOTAL_</strong>",
			"infoEmpty": "0 - 0 of 0",
			"lengthMenu": "Show:&nbsp;&nbsp;_MENU_",
			"loadingRecords": "Please wait - loading...",
			"search": " ",
			"searchPlaceholder": "Filter Patches",
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
	$( ":input[type=search][aria-controls=patches]" ).addClear({
		symbolClass: "glyphicon glyphicon-remove",
		onClear: function() {
			$( "#patches" ).DataTable().search("").draw();
		}
	});

	if ($( ":input[type=search][aria-controls=patches]" ).val() !== "") {
		$( "#patches" ).DataTable().search("").draw();
	}

	$( "select[name=patches_length]" ).addClass("table-select");

	if (title_json.source_id > 0) {
		$( "#patches" ).DataTable().buttons().disable();
	}

	if (patches_success_msg.length) {
		$( "#patches-success-alert" ).removeClass("hidden");
		$( "#patches-success-msg" ).html(patches_success_msg);
	}

	$( "#patches tbody" ).on("change", "input", function() {
		var patch_id = this;
		var current_select = $( "#title-current-select" );
		$.post("patchCtl.php?patch_id=" + patch_id.value, { "patch_enabled": patch_id.checked });
		$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
		$.each(patches_json, function(index, data) {
			if (data.id == patch_id.value) {
				data.enabled = (patch_id.checked);
			}
		});
		patches_enabled = $.map(patches_json, function(el) { if (el.enabled) { return el.version; } });
		current_select.empty();
		$.each(patches_enabled, function(index, value) {
			current_select.append($( "<option>" ).attr("value", value).text(value));
		});
		current_select.selectpicker("refresh");
		if (patches_enabled.indexOf(title_json.current) == -1) {
			$.post("patchCtl.php?id=" + title_json.id, { "table": "titles", "field": "current", "value": patches_enabled[0] });
			$.post("patchCtl.php?title_id=" + title_json.id, { "title_modified": true });
			current_select.selectpicker("val", patches_enabled[0]);
			title_json.current = patches_enabled[0];
		} else {
			current_select.selectpicker("val", title_json.current);
		}
		if (patches_enabled.length == 0) {
			patches_error_msg = "At least one patch must be enabled for the definition to be valid.";
			$( "#patches-error-msg" ).html(patches_error_msg);
			$( "#patches-error-alert" ).removeClass("hidden");
			$( "#patches-tab-link" ).css("color", "#a94442");
 			$( "#patches-tab-icon" ).removeClass("hidden");
			title_warning_msg = "This software title is disabled.";
			$( "#title-warning-msg" ).html(title_warning_msg);
			$( "#title-warning-alert" ).removeClass("hidden");
			$( "#spacer-msg" ).html(title_warning_msg);
			$( "#spacer-alert" ).removeClass("hidden");
			$.post("patchCtl.php?title_id=" + title_json.id, { "title_enabled": false });
			title_json.enabled = false;
			$( "#title-current" ).removeClass("hidden");
			$( "#title-current-select" ).addClass("hidden");
			$( "#title-current-select" ).parent("div").addClass("hidden");
		} else {
			$( "#patches-error-alert" ).addClass("hidden");
			$( "#patches-tab-link" ).removeAttr("style");
 			$( "#patches-tab-icon" ).addClass("hidden");
			title_warning_msg = 'This software title is disabled. <a href="">Click here to enable it</a>.';
			$( "#title-warning-msg" ).html(title_warning_msg);
			$( "#spacer-msg" ).html(title_warning_msg);
			$( "#title-current" ).addClass("hidden");
			$( "#title-current-select" ).removeClass("hidden");
			$( "#title-current-select" ).parent("div").removeClass("hidden");
		}
	});

	$( "#patches tbody" ).on("click", "button", function() {
		var data = patches.row($( this ).parents("tr")).data();

		$( "#del-patch-modal" ).on("show.bs.modal", function(event) {
			$( "#del-patch-label" ).text("Delete Version " + data.version + "?");
			$( "#del-patch-msg" ).html('Are you sure you want to delete version ' + data.version + '?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-patch-btn" ).val(data.id);
		});
	});

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

	$( "#new-patch-datetimepicker" ).datetimepicker({
		format: "YYYY-MM-DDTHH:mm:ss\\Z"
	});

	$( "#new-patch-modal" ).on("show.bs.modal", function(event) {
		if (patch_versions.indexOf(title_json.current) == -1) {
			$( "#new-patch-version" ).val(title_json.current);
		}
		$( "#new-patch-released" ).val(moment.utc().format("YYYY-MM-DDTHH:mm:ss") + "Z");
		$( "#new-patch-reboot" ).selectpicker("val", 0);
		if (patches_json.length) {
			$( "#new-patch-min-os" ).val(patches_json[0].min_os);
		}
	});

	$( "#new-patch-btn" ).click(function() {
		var sort_order = $( "#new-patch-sort-order" ).val();
		var version = $( "#new-patch-version" ).val();
		var released = $( "#new-patch-released" ).val();
		var min_os = $( "#new-patch-min-os" ).val();

		if (/^\d+$/.test(sort_order)) {
			$( "#new-patch-sort-order" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-patch-sort-order" ).parent("div").addClass("has-error");
		}
		if (/^.{1,255}$/.test(version) && patch_versions.indexOf(version) == -1) {
			$( "#new-patch-version" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-patch-version" ).parent("div").addClass("has-error");
			if (patch_versions.indexOf(version) == -1) {
				$( "#new-patch-version-help" ).addClass("hidden");
			} else {
				$( "#new-patch-version-help" ).removeClass("hidden");
			}
		}
		if (/^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test(released)) {
			$( "#new-patch-released" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-patch-released" ).parent("div").addClass("has-error");
		}
		if (/^.{1,255}$/.test(min_os)) {
			$( "#new-patch-min-os" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-patch-min-os" ).parent("div").addClass("has-error");
		}

		if (0 == $("#new-patch-modal div.form-group.has-error" ).length) {
			$( "#create-patch" ).prop("disabled", false);
			$( "#patches-form" ).submit();
		}
	});

	if (pdo_error.length) {
		$( "title, #heading" ).html("Database Error");
		$( ".tab-content" ).addClass("hidden");
		$( ".nav-tabs-wrapper" ).addClass("hidden");
		error_msg = pdo_error;
	}

	if (!title_json) {
		$( "title, #heading" ).html("Not Found");
		error_msg = "The requested title was not found on this server.";
		$( ".tab-content" ).addClass("hidden");
		$( ".nav-tabs-wrapper" ).addClass("hidden");
	}

	if (error_msg.length) {
		$( "#title-warning-alert" ).addClass("hidden");
		$( "#error-alert" ).removeClass("hidden");
		$( "#error-msg" ).html(error_msg);
		$( "#spacer-alert" ).removeClass("hidden");
		$( "#spacer-msg" ).html(error_msg);
	} else {
		$( ".breadcrumb" ).prepend('<li><a href="patchTitles.php"><small>Software Titles</small></a></li>');
	}

	$( "select" ).selectpicker();
	$( ".selectpicker[data-style='btn-default btn-sm']" ).parent("div").css("height", "30px");
});
