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

var patch_versions = $.map(patches_json, function(el) { return el.version; });
var comps_error_msg = "";
var caps_error_msg = "";
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
if (patch_json && patch_json.bundle_id.length && patch_json.app_name.length) {
	var new_kill_apps = [{ "bundle_id": patch_json.bundle_id, "app_name": patch_json.app_name }];
}
if (title_kill_apps_json.length) {
	var new_kill_apps = title_kill_apps_json.filter(function(t) {
		return !kill_apps_json.some(function(p) {
			return (t.bundle_id == p.bundle_id && t.app_name == p.app_name);
		});
	});
} else if (patch_json && patch_json.bundle_id.length && patch_json.app_name.length) {
	var new_kill_apps = [{ "bundle_id": patch_json.bundle_id, "app_name": patch_json.app_name }];
} else {
	var new_kill_apps = [];
}

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

$( document ).ready(function() {
	$( "title" ).html(patch_json.name + " &rsaquo; " + patch_json.version);
	$( "#heading" ).html(patch_json.version);
	$( "#patch" ).addClass("active");
	$( "#settings" ).attr("onclick", "document.location.href='patchSettings.php'");

	i = patches_json.findIndex(a => a.version == patch_json.version) - 1;
	if (i >= 0) {
		$( "#prev-btn" ).removeClass("disabled");
		$( "#prev-btn" ).attr("href", "managePatch.php?id=" + patches_json[i].id);
	}
	i = patches_json.findIndex(a => a.version == patch_json.version) + 1;
	if (patches_json.length > i) {
		$( "#next-btn" ).removeClass("disabled");
		$( "#next-btn" ).attr("href", "managePatch.php?id=" + patches_json[i].id);
	}

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
		localStorage.setItem("activePatchTab", $(e.target).attr("href"));
	});

	var activePatchTab = localStorage.getItem("activePatchTab");

	if(activePatchTab){
		$( '#top-tabs a[href="' + activePatchTab + '"]' ).tab("show");
	}

	$( "#patch-sort-order" ).val(patch_json.sort_order);
	$( "#patch-version" ).val(patch_json.version);
	$( "#patch-released" ).val(moment.unix(patch_json.released).utc().format("YYYY-MM-DDTHH:mm:ss") + "Z");
	$( "#patch-datetimepicker" ).datetimepicker({
		format: "YYYY-MM-DDTHH:mm:ss\\Z"
	});
	$( "#patch-standalone" ).val(patch_json.standalone);
	$( "#patch-reboot" ).val(patch_json.reboot);
	$( "#patch-min-os" ).val(patch_json.min_os);

	if (patch_json.source_id > 0) {
		$( "#patch-sort-order, #patch-version, #patch-released, #patch-standalone, #patch-reboot, #patch-min-os" ).prop("disabled", true);
	}

	$( "#patch-sort-order" ).on("focus", function() {
		$( this ).val(patch_json.sort_order);
		$( this ).parent("div").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='patch-sort-order']").removeAttr("style");
	});
	$( "#patch-sort-order" ).on("change", function() {
		sort_order = $( this ).val();
		if (/^\d+$/.test(sort_order)) {
			if (sort_order !== patch_json.sort_order) {
				$.post("patchCtl.php?table=patches&field=sort_order&id=" + patch_json.id, { "value": sort_order }).done(function(result) {
					if (result) {
						$( "#patch-sort-order" ).parent("div").addClass("has-success has-feedback");
						$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
						patch_json.sort_order = sort_order;
					} else {
						$( "#patch-sort-order" ).parent("div").addClass("has-error");
						$( ".control-label[for='patch-sort-order']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parent("div").addClass("has-error");
			$( ".control-label[for='patch-sort-order']").css("color", "#a94442");
		}
	});

	$( "#patch-version" ).on("focus", function() {
		patch_versions.splice(patch_versions.indexOf( patch_json.version ), 1)
		$( this ).val(patch_json.version);
		$( this ).parent("div").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='patch-version']").removeAttr("style");
		$( "#patch-version-help" ).addClass("hidden");
	});
	$( "#patch-version" ).on("change", function() {
		version = $( this ).val();
		if (/^.{1,255}$/.test(version) && patch_versions.indexOf(version) == -1) {
			if (version !== patch_json.version) {
				$.post("patchCtl.php?table=patches&field=version&id=" + patch_json.id, { "value": version }).done(function(result) {
					if (result) {
						$( "#patch-version" ).parent("div").addClass("has-success has-feedback");
						$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
						patch_json.version = version;
					} else {
						$( "#patch-version" ).parent("div").addClass("has-error");
						$( ".control-label[for='patch-version']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parent("div").addClass("has-error");
			if (patch_versions.indexOf(version) == -1) {
				$( "#patch-version-help" ).addClass("hidden");
			} else {
				$( "#patch-version-help" ).removeClass("hidden");
			}
			$( ".control-label[for='patch-version']").css("color", "#a94442");
		}
	});
	$( "#patch-version" ).on("blur", function() {
		patch_versions.push(patch_json.version);
	});

	$( "#patch-released" ).on("focus", function() {
		$( this ).val(moment.unix(patch_json.released).utc().format("YYYY-MM-DDTHH:mm:ss") + "Z");
		$( this ).parents("div.form-group").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='patch-released']").removeAttr("style");
	});
	$( "#patch-released" ).on("blur", function() {
		released = moment($( this ).val(), "YYYY-MM-DDTHH:mm:ss\Z").unix();
		if (/^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test($( this ).val())) {
			if (released != patch_json.released) {
				$.post("patchCtl.php?table=patches&field=released&id=" + patch_json.id, { "value": released }).done(function(result) {
					if (result) {
						$( "#patch-released" ).parents("div.form-group").addClass("has-success has-feedback");
						$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
						patch_json.released = released;
					} else {
						$( "#patch-released" ).parents("div.form-group").addClass("has-error");
						$( ".control-label[for='patch-released']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parents("div.form-group").addClass("has-error");
			$( ".control-label[for='patch-released']").css("color", "#a94442");
		}
	});

	$( "#patch-standalone" ).parents("div.form-group").on("click", function() {
		$( "#patch-standalone" ).val(patch_json.standalone);
		$( this ).removeClass("has-error has-success has-feedback");
		$( ".control-label[for='patch-standalone']").removeAttr("style");
	});
	$( "#patch-standalone" ).on("change", function() {
		standalone = $( this ).val();
		if (standalone !== patch_json.standalone) {
			$.post("patchCtl.php?table=patches&field=standalone&id=" + patch_json.id, { "value": standalone }).done(function(result) {
				if (result) {
					$( "#patch-standalone" ).parents("div.form-group").addClass("has-success has-feedback");
					$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
					patch_json.standalone = standalone;
				} else {
					$( "#patch-standalone" ).parents("div.form-group").addClass("has-error");
					$( ".control-label[for='patch-standalone']").css("color", "#a94442");
				}
			});
		}
	});

	$( "#patch-reboot" ).parents("div.form-group").on("click", function() {
		$( "#patch-reboot" ).val(patch_json.reboot);
		$( this ).removeClass("has-error has-success has-feedback");
		$( ".control-label[for='patch-reboot']").removeAttr("style");
	});
	$( "#patch-reboot" ).on("change", function() {
		reboot = $( this ).val();
		if (reboot !== patch_json.reboot) {
			$.post("patchCtl.php?table=patches&field=reboot&id=" + patch_json.id, { "value": reboot }).done(function(result) {
				if (result) {
					$( "#patch-reboot" ).parents("div.form-group").addClass("has-success has-feedback");
					$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
					patch_json.reboot = reboot;
				} else {
					$( "#patch-reboot" ).parents("div.form-group").addClass("has-error");
					$( ".control-label[for='patch-reboot']").css("color", "#a94442");
				}
			});
		}
	});

	$( "#patch-min-os" ).on("focus", function() {
		$( this ).val(patch_json.min_os);
		$( this ).parent("div").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='patch-min-os']").removeAttr("style");
	});
	$( "#patch-min-os" ).on("change", function() {
		min_os = $( this ).val();
		if (/^.{1,255}$/.test(min_os)) {
			if (min_os !== patch_json.min_os) {
				$.post("patchCtl.php?table=patches&field=min_os&id=" + patch_json.id, { "value": min_os }).done(function(result) {
					if (result) {
						$( "#patch-min-os" ).parent("div").addClass("has-success has-feedback");
						$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
						patch_json.min_os = min_os;
					} else {
						$( "#patch-min-os" ).parent("div").addClass("has-error");
						$( ".control-label[for='patch-min-os']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parent("div").addClass("has-error");
			$( ".control-label[for='patch-min-os']").css("color", "#a94442");
		}
	});

	var components = $( "#components" ).DataTable( {
		"buttons": [{
			"text": '<span class="glyphicon glyphicon-plus"></span> New',
			"className": "btn-primary btn-sm",
			"action": function ( e, dt, node, config ) {
				$( "#new-comp-modal" ).modal("show");
			}
		}],
		"columnDefs": [{
			"targets": 0,
			"data": null,
			"render": function(data, type, row, meta) {
				if (row.criteria == 0) {
					return '<a href="manageComponent.php?id=' + row.id + '"><span class="text-danger glyphicon glyphicon-exclamation-sign checkbox-error"></span></a>';
				} else {
					return "";
				}
			}
		}, {
			"targets": 1,
			"data": null,
			"render": function(data, type, row, meta) {
				return '<a href="manageComponent.php?id=' + row.id + '">' + row.name + '</a>';
			}
		}, {
			"targets": -1,
			"className": "text-right",
			"data": null,
			"render": function(data, type, row, meta) {
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-comp-modal">Delete</button>';
			}
		}],
		"columns": [{
			"data": null,
			"orderable": false
		}, {
			"data": "name"
		}, {
			"data": "version"
		}, {
			"data": null,
			"orderable": false
		}],
		"data": components_json,
		"dom": '<"row dataTables-header"<"col-xs-12"B>>' + '<"row dataTables-table"<"col-xs-12"t>>',
		"info": false,
		"language": {
			"emptyTable": "No Components",
			"loadingRecords": "Please wait - loading...",
		},
		"lengthChange": false,
		"order": [ 1, "asc" ],
		"paging": false,
		"searching": false,
		"stateSave": true
	});

	if (patch_json.source_id > 0 || components_json.length > 0) {
		$( "#components" ).DataTable().buttons().disable();
	}

	if (patch_json.source_id > 0) {
		$( "#components button" ).prop("disabled", true);
	}

	if (!components_json.length) {
		comps_error_msg = "At least one component is required for the patch to be valid.";
	}
	$.each(components_json, function(index, data) {
		if (data.criteria == 0) {
			comps_error_msg = "At least one criteria is required for the component to be valid.";
		}
	});
	if (comps_error_msg.length) {
		$( "#components-error-msg" ).html(comps_error_msg);
		$( "#components-error-alert" ).removeClass("hidden");
		$( "#components-tab-link" ).css("color", "#a94442");
		$( "#components-tab-icon" ).removeClass("hidden");
	}

	$( "#new-comp-modal" ).on("show.bs.modal", function(event) {
		$( "#new-comp-name" ).parent("div").removeClass("has-error");
		$( "#new-comp-version" ).parent("div").removeClass("has-error");
		if (title_comp_names_json.length) {
			$( "#new-comp-name" ).val(title_comp_names_json[components_json.length]);
		} else {
			$( "#new-comp-name" ).val(patch_json.name);
		}
		$( "#new-comp-version" ).val(patch_json.version);
	});

	$( "#new-comp-btn" ).click(function() {
		var name = $( "#new-comp-name" ).val();
		var version = $( "#new-comp-version" ).val();

		if (/^.{1,255}$/.test(name)) {
			$( "#new-comp-name" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-comp-name" ).parent("div").addClass("has-error");
		}
		if (/^.{1,255}$/.test(version)) {
			$( "#new-comp-version" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-comp-version" ).parent("div").addClass("has-error");
		}

		if (0 == $("#new-comp-modal div.form-group.has-error" ).length) {
			$( "#create-comp" ).prop("disabled", false);
			$( "#components-form" ).submit();
		}
	});

	$( "#components tbody" ).on("click", "button", function() {
		var data = components.row($( this ).parents("tr")).data();

		$( "#del-comp-modal" ).on("show.bs.modal", function(event) {
			$( "#del-comp-label" ).text("Delete " + data.name + "?");
			$( "#del-comp-msg" ).html('Are you sure you want to delete ' + data.name + '?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-comp-btn" ).val(data.id);
		});
	});

	// 	Dependencies
	/* var dependencies = $( "#dependencies" ).DataTable({
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
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-dep-modal">Delete</button>';
			}
		}],
		"data": dependencies_json,
		"dom": '<"row dataTables-table"<"col-xs-12"t>>',
		"info": false,
		"lengthChange": false,
		"ordering": false,
		"paging": false,
		"searching": false
	});

	if (!dependencies_json.length) {
		$( "#dependencies tbody" ).addClass("hidden");
	}

	$( "#dependencies tbody select.is_and" ).each(function(index) {
		var data = dependencies.row($( this ).parents("tr")).data();
		$( this ).selectpicker("val", data.is_and);
	});

	$( "#dependencies tbody select.criteria" ).each(function(index) {
		var data = dependencies.row($( this ).parents("tr")).data();
		var criteria_select = $( this );
		$.map(ext_attrs_json, function(ea) {
			criteria_select.append($( "<option>" ).attr("value", ea.key_id).text(ea.name));
		});
		$.each(criteria_opts, function(index, value) {
			criteria_select.append($( "<option>" ).attr("value", value).text(value));
		});
		$( this ).selectpicker("val", data.name);
	});

	$( "#dependencies tbody select.operator" ).each(function(index) {
		var data = dependencies.row($( this ).parents("tr")).data();
		var operator_opts = getOperators(data.name);
		var operator_select = $( this );
		$.each(operator_opts, function(index, value) {
			operator_select.append($( "<option>" ).attr("value", value).text(value));
		});
		$( this ).selectpicker("val", data.operator);
	});

	if (patch_json.source_id > 0) {
		$( "#dependencies select, #dependencies input, #dependencies button" ).prop("disabled", true);
	}

	$( "#dependencies tbody .bootstrap-select" ).on("click", function() {
		$( this ).parents("div.form-group").removeClass("has-error has-warning has-success has-feedback").find("span.form-control-feedback").remove();
	});

	$( "#dependencies tbody select.is_and" ).on("change", function() {
		var element = $( this );
		var data = dependencies.row(element.parents("tr")).data();
		$.post("patchCtl.php?table=dependencies&field=is_and&id=" + data.id, { "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
				data.is_and = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#dependencies tbody select.criteria" ).on("change", function() {
		var element = $( this );
		var data = dependencies.row(element.parents("tr")).data();
		$.post("patchCtl.php?table=dependencies&field=name&id=" + data.id, { "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
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
					$.post("patchCtl.php?table=dependencies&field=operator&id=" + data.id, { "value": operator_opts[0] });
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
					$.post("patchCtl.php?table=dependencies&field=type&id=" + data.id, { "value": type });
					data.type = type;
				}
				data.name = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#dependencies tbody select.operator" ).on("change", function() {
		var element = $( this );
		var data = dependencies.row(element.parents("tr")).data();
		$.post("patchCtl.php?table=dependencies&field=operator&id=" + data.id, { "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
				data.operator = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#dependencies tbody input" ).on("focus", function() {
		var data = dependencies.row($( this ).parents("tr")).data();
		$( this ).val(data.value);
		$( this ).parents("div.form-group").removeClass("has-error has-warning has-success has-feedback").find("span.form-control-feedback").remove();
	});
	$( "#dependencies tbody input" ).on("change", function() {
		var element = $( this );
		var data = dependencies.row(element.parents("tr")).data();
		if (/^.{0,255}$/.test(data.value)) {
			$.post("patchCtl.php?table=dependencies&field=value&id=" + data.id, { "value": element.val() }).done(function(result) {
				if (result) {
					element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
					$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
					data.value = element.val();
				} else {
					element.parents("div.form-group").addClass("has-error");
				}
			});
		} else {
			element.parents("div.form-group").addClass("has-error");
		}
	});

	$( "#dependencies tbody" ).on("click", "button", function() {
		var data = dependencies.row( $(this).parents("tr") ).data();
		$( "#del-dep-modal" ).on("show.bs.modal", function(event) {
			// $( "#del-dep-msg" ).html('Are you sure you want to delete <em>' + data.name + ' ' + data.operator + ' ' + (data.value.length ? data.value : '""') + '</em> ?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-dep-btn" ).val(data.id);
		});
	});

	$.map(ext_attrs_json, function(ea) {
		$( "#new-dep-name" ).append($( "<option>" ).attr("value", ea.key_id).text(ea.name));
	});
	$.each(criteria_opts, function(index, value) {
		$( "#new-dep-name" ).append($( "<option>" ).attr("value", value).text(value));
	});

	$( "#new-dep-modal" ).on("show.bs.modal", function(event) {
		$( "#new-dep-name" ).selectpicker("val", " ");
		$( "#new-dep-operator" ).val("is");
		$( "#new-dep-value" ).val("");
		$( "#new-dep-type" ).val("recon");
		$( "#new-dep-is-and" ).val(1);
		$( "#new-dep-sort-order" ).val((dependencies_json.length ? parseInt(dependencies_json[dependencies_json.length - 1].sort_order) + 1 : 0));
		$( "#new-dep-btn" ).prop("disabled", true);
	});

	$( "#new-dep-name" ).on("change", function() {
		$( "#new-dep-btn" ).prop("disabled", $( "#new-dep-name" ).val() == " ");
	});

	$( "#del-dep-modal" ).on("hide.bs.modal", function(event) {
		$( "#del-dep-btn" ).val("");
	}); */

	// 	Capabilities
	var capabilities = $( "#capabilities" ).DataTable({
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
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-cap-modal">Delete</button>';
			}
		}],
		"data": capabilities_json,
		"dom": '<"row dataTables-table"<"col-xs-12"t>>',
		"info": false,
		"lengthChange": false,
		"ordering": false,
		"paging": false,
		"searching": false
	});

	if (!capabilities_json.length) {
		caps_error_msg = "At least one capability is required for the definition to be valid.";
		$( "#capabilities-error-msg" ).html(caps_error_msg);
		$( "#capabilities-error-alert" ).removeClass("hidden");
		$( "#capabilities-tab-link" ).css("color", "#a94442");
		$( "#capabilities-tab-icon" ).removeClass("hidden");
		$( "#capabilities tbody" ).addClass("hidden");
	}

	$( "#capabilities tbody select.is_and" ).each(function(index) {
		var data = capabilities.row($( this ).parents("tr")).data();
		$( this ).selectpicker("val", data.is_and);
	});

	$( "#capabilities tbody select.criteria" ).each(function(index) {
		var data = capabilities.row($( this ).parents("tr")).data();
		var criteria_select = $( this );
		$.map(ext_attrs_json, function(ea) {
			criteria_select.append($( "<option>" ).attr("value", ea.key_id).text(ea.name));
		});
		$.each(criteria_opts, function(index, value) {
			criteria_select.append($( "<option>" ).attr("value", value).text(value));
		});
		$( this ).selectpicker("val", data.name);
	});

	$( "#capabilities tbody select.operator" ).each(function(index) {
		var data = capabilities.row($( this ).parents("tr")).data();
		var operator_opts = getOperators(data.name);
		var operator_select = $( this );
		$.each(operator_opts, function(index, value) {
			operator_select.append($( "<option>" ).attr("value", value).text(value));
		});
		$( this ).selectpicker("val", data.operator);
	});

	if (patch_json.source_id > 0) {
		$( "#capabilities select, #capabilities input, #capabilities button" ).prop("disabled", true);
	}

	$( "#capabilities tbody .bootstrap-select" ).on("click", function() {
		$( this ).parents("div.form-group").removeClass("has-error has-warning has-success has-feedback").find("span.form-control-feedback").remove();
	});

	$( "#capabilities tbody select.is_and" ).on("change", function() {
		var element = $( this );
		var data = capabilities.row(element.parents("tr")).data();
		$.post("patchCtl.php?table=capabilities&field=is_and&id=" + data.id, { "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
				data.is_and = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#capabilities tbody select.criteria" ).on("change", function() {
		var element = $( this );
		var data = capabilities.row(element.parents("tr")).data();
		$.post("patchCtl.php?table=capabilities&field=name&id=" + data.id, { "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
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
					$.post("patchCtl.php?table=capabilities&field=operator&id=" + data.id, { "value": operator_opts[0] });
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
					$.post("patchCtl.php?table=capabilities&field=type&id=" + data.id, { "value": type });
					data.type = type;
				}
				data.name = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#capabilities tbody select.operator" ).on("change", function() {
		var element = $( this );
		var data = capabilities.row(element.parents("tr")).data();
		$.post("patchCtl.php?table=capabilities&field=operator&id=" + data.id, { "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
				data.operator = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#capabilities tbody input" ).on("focus", function() {
		var data = capabilities.row($( this ).parents("tr")).data();
		$( this ).val(data.value);
		$( this ).parents("div.form-group").removeClass("has-error has-warning has-success has-feedback").find("span.form-control-feedback").remove();
	});
	$( "#capabilities tbody input" ).on("change", function() {
		var element = $( this );
		var data = capabilities.row(element.parents("tr")).data();
		if (/^.{0,255}$/.test(data.value)) {
			$.post("patchCtl.php?table=capabilities&field=value&id=" + data.id, { "value": element.val() }).done(function(result) {
				if (result) {
					element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
					$.post("patchCtl.php?title_id=" + patch_json.title_id, { "title_modified": true });
					data.value = element.val();
				} else {
					element.parents("div.form-group").addClass("has-error");
				}
			});
		} else {
			element.parents("div.form-group").addClass("has-error");
		}
	});

	$( "#capabilities tbody" ).on("click", "button", function() {
		var data = capabilities.row( $(this).parents("tr") ).data();
		$( "#del-cap-modal" ).on("show.bs.modal", function(event) {
			// $( "#del-cap-msg" ).html('Are you sure you want to delete <em>' + data.name + ' ' + data.operator + ' ' + (data.value.length ? data.value : '""') + '</em> ?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-cap-btn" ).val(data.id);
		});
	});

	$.map(ext_attrs_json, function(ea) {
		$( "#new-cap-name" ).append($( "<option>" ).attr("value", ea.key_id).text(ea.name));
	});
	$.each(criteria_opts, function(index, value) {
		$( "#new-cap-name" ).append($( "<option>" ).attr("value", value).text(value));
	});

	$( "#new-cap-modal" ).on("show.bs.modal", function(event) {
		$( "#new-cap-name" ).selectpicker("val", " ");
		$( "#new-cap-operator" ).val("is");
		$( "#new-cap-value" ).val("");
		$( "#new-cap-type" ).val("recon");
		$( "#new-cap-is-and" ).val(1);
		$( "#new-cap-sort-order" ).val((capabilities_json.length ? parseInt(capabilities_json[capabilities_json.length - 1].sort_order) + 1 : 0));
		if (capabilities_json.length == 0) {
			$( "#new-cap-name" ).selectpicker("val", "Operating System Version");
			$( "#new-cap-operator" ).val("greater than or equal");
			$( "#new-cap-value" ).val(patch_json.min_os);
		} else {
			$( "#new-cap-btn" ).prop("disabled", true);
		}
	});

	$( "#new-cap-name" ).on("change", function() {
		$( "#new-cap-btn" ).prop("disabled", $( "#new-cap-name" ).val() == " ");
	});

	$( "#del-cap-modal" ).on("hide.bs.modal", function(event) {
		$( "#del-cap-btn" ).val("");
	});

	var kill_apps = $( "#kill-apps" ).DataTable( {
		"buttons": [{
			"text": '<span class="glyphicon glyphicon-plus"></span> New',
			"className": "btn-primary btn-sm",
			"action": function ( e, dt, node, config ) {
				$( "#new-kill-app-modal" ).modal();
			}
		}],
		"columnDefs": [{
			"targets": -1,
			"className": "text-right",
			"data": null,
			"render": function(data, type, row, meta) {
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-kill-app-modal">Delete</button>';
			}
		}],
		"columns": [{
			"data": "app_name"
		}, {
			"data": "bundle_id"
		}, {
			"data": null,
			"orderable": false
		}],
		"data": kill_apps_json,
		"dom": '<"row dataTables-header"<"col-xs-12"B>>' + '<"row dataTables-table"<"col-xs-12"t>>',
		"info": false,
		"language": {
			"emptyTable": "No Kill Applications",
			"loadingRecords": "Please wait - loading...",
		},
		"lengthChange": false,
		"ordering": false,
		"paging": false,
		"searching": false,
		"stateSave": true
	});

	if (patch_json.source_id > 0) {
		$( "#kill-apps" ).DataTable().buttons().disable();
		$( "#kill-apps button" ).prop("disabled", true);
	}

	$( "#kill-apps tbody" ).on("click", "button", function() {
		var data = kill_apps.row( $(this).parents("tr") ).data();
		$( "#del-kill-app-modal" ).on("show.bs.modal", function(event) {
			// $( "#del-cap-msg" ).html('Are you sure you want to delete <em>' + data.name + ' ' + data.operator + ' ' + (data.value.length ? data.value : '""') + '</em> ?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-kill-app-btn" ).val(data.id);
		});
	});

	$( "#kill-apps tbody" ).on("click", "button", function() {
		var data = kill_apps.row($( this ).parents("tr")).data();

		$( "#del-kill-app-modal" ).on("show.bs.modal", function(event) {
			$( "#del-kill-app-label" ).text("Delete " + data.app_name + "?");
			$( "#del-kill-app-msg" ).html('Are you sure you want to delete ' + data.app_name + '?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-kill-app-btn" ).val(data.id);
		});
	});

	$( "#new-kill-app-modal" ).on("show.bs.modal", function(event) {
		$( "#new-kill-app-name" ).parent("div").removeClass("has-error");
		$( "#new-kill-app-bundle-id" ).parent("div").removeClass("has-error");
		if (new_kill_apps.length) {
			$( "#new-kill-app-name" ).val(new_kill_apps[0].app_name);
			$( "#new-kill-app-bundle-id" ).val(new_kill_apps[0].bundle_id);
		}
	});

	$( "#new-kill-app-btn" ).click(function() {
		var name = $( "#new-kill-app-name" ).val();
		var version = $( "#new-kill-app-version" ).val();

		if (/^.{1,255}$/.test(name)) {
			$( "#new-kill-app-name" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-kill-app-name" ).parent("div").addClass("has-error");
		}
		if (/^.{1,255}$/.test(version)) {
			$( "#new-kill-app-bundle-id" ).parent("div").removeClass("has-error");
		} else {
			$( "#new-kill-app-bundle-id" ).parent("div").addClass("has-error");
		}

		if (0 == $("#new-kill-app-modal div.form-group.has-error" ).length) {
			$( "#create-kill-app" ).prop("disabled", false);
			$( "#kill-apps-form" ).submit();
		}
	});

	$( ".dataTables_wrapper" ).removeClass("form-inline");

	$( ".dt-buttons button" ).css("width", "75px");
	$( ".dt-buttons" ).addClass("pull-right").removeClass("btn-group dt-buttons")
	$( ".btn-primary" ).removeClass("btn-default");

	if (patch_json) {
		$( ".breadcrumb" ).prepend('<li><a href="manageTitle.php?id=' + patch_json.title_id + '"><small>' + patch_json.name + '</small></a></li>');
		$( ".breadcrumb" ).prepend('<li><a href="patchTitles.php"><small>Software Titles</small></a></li>');
	}

	if (0 == patch_json.enabled) {
		patch_warning_msg = "This patch is disabled.";
		if (!comps_error_msg.length && !caps_error_msg.length) {
			patch_warning_msg = patch_warning_msg + ' <a href="">Click here to enable it</a>.';
		}
		$( "#patch-warning-msg" ).html(patch_warning_msg);
		$( "#patch-warning-alert" ).removeClass("hidden");
		$( "#spacer-msg" ).html(patch_warning_msg);
		$( "#spacer-alert" ).removeClass("hidden");
		// $( "#patch-tab-link" ).css("color", "#8a6d3b");
		// $( "#patch-tab-icon" ).removeClass("hidden");
	}

	$( "#patch-warning-alert" ).on("click", "a", function() {
		$.post("patchCtl.php?patch_id=" + patch_json.id, { "patch_enabled": true })
		$( "#patch-warning-alert" ).addClass("hidden");
		// $( "#patch-tab-link" ).removeAttr("style");
		// $( "#patch-tab-icon" ).addClass("hidden");
	});

	if (pdo_error.length) {
		$( "title, #heading" ).html("Database Error");
		$( "#top-tabs" ).addClass("hidden");
		$( ".tab-content" ).addClass("hidden");
		error_msg = pdo_error;
	}

	if (!patch_json) {
		$( "title, #heading" ).html("Not Found");
		error_msg = "The requested patch was not found on this server.";
		$( "#top-tabs" ).addClass("hidden");
		$( ".tab-content" ).addClass("hidden");
	}

	if (error_msg.length) {
		$( "#patch-warning-alert" ).addClass("hidden");
		$( "#error-alert" ).removeClass("hidden");
		$( "#error-msg" ).html(error_msg);
		$( "#spacer-alert" ).removeClass("hidden");
		$( "#spacer-msg" ).html(error_msg);
	}

	$( "select" ).selectpicker();
	$( ".selectpicker[data-style='btn-default btn-sm']" ).parent("div").css("height", "30px");
});
