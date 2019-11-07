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

var active_tab = localStorage.getItem("activeCompTab");
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

$( document ).ready(function() {
	$( "title" ).html(component_json.name + " (" + component_json.version + ")");
	$( "#heading" ).html(component_json.name + " (" + component_json.version + ")");
	$( "#patch" ).addClass("active");
	$( "#settings" ).attr("onclick", "document.location.href='patchSettings.php'");

	i = components_json.findIndex(a => a.version == component_json.version) - 1;
	if (i >= 0) {
		$( "#prev-btn" ).removeClass("disabled");
		$( "#prev-btn" ).attr("href", "manageComponent.php?id=" + components_json[i].id);
	}
	i = components_json.findIndex(a => a.version == component_json.version) + 1;
	if (components_json.length > i) {
		$( "#next-btn" ).removeClass("disabled");
		$( "#next-btn" ).attr("href", "manageComponent.php?id=" + components_json[i].id);
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
		localStorage.setItem("activeCompTab", $(e.target).attr("href"));
	});

	if (null === active_tab) {
		active_tab = "#component-tab";
	}

	$( "#top-tabs a[href='" + active_tab + "']" ).tab("show");

	$( "#component-name" ).val(component_json.name);
	$( "#component-version" ).val(component_json.version);

	if (component_json.source_id > 0) {
		$( "#component-name, #component-version" ).prop("disabled", true);
	}
	$( "#component-name" ).on("focus", function() {
		$( this ).val(component_json.name);
		$( this ).parent("div").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='component-name']").removeAttr("style");
	});
	$( "#component-name" ).on("change", function() {
		name = $( this ).val();
		if (/^.{1,255}$/.test(name)) {
			if (name !== component_json.name) {
				$.post("patchCtl.php?id=" + component_json.id, { "table": "components", "field": "name", "value": name }).done(function(result) {
					if (result) {
						$( "#component-name" ).parent("div").addClass("has-success has-feedback");
						$.post("patchCtl.php?component_id=" + component_json.id, { "component_modified": true });
						$( "title, #heading" ).html(name + " (" + component_json.version + ")");
						component_json.name = name;
					} else {
						$( "#component-name" ).parent("div").addClass("has-error");
						$( ".control-label[for='component-name']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parent("div").addClass("has-error");
			$( ".control-label[for='component-name']").css("color", "#a94442");
		}
	});

	$( "#component-version" ).on("focus", function() {
		$( this ).val(component_json.version);
		$( this ).parent("div").removeClass("has-error has-success has-feedback");
		$( ".control-label[for='component-version']").removeAttr("style");
	});
	$( "#component-version" ).on("change", function() {
		version = $( this ).val();
		if (/^.{1,255}$/.test(version)) {
			if (version !== component_json.version) {
				$.post("patchCtl.php?id=" + component_json.id, { "table": "components", "field": "version", "value": version }).done(function(result) {
					if (result) {
						$( "#component-version" ).parent("div").addClass("has-success has-feedback");
						$.post("patchCtl.php?component_id=" + component_json.id, { "component_modified": true });
						$( "title, #heading" ).html(component_json.name + " (" + version + ")");
						component_json.version = version;
					} else {
						$( "#component-version" ).parent("div").addClass("has-error");
						$( ".control-label[for='component-version']").css("color", "#a94442");
					}
				});
			}
		} else {
			$( this ).parent("div").addClass("has-error");
			$( ".control-label[for='component-version']").css("color", "#a94442");
		}
	});

	// 	Criteria
	var criteria = $( "#criteria" ).DataTable({
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
				return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#del-criteria-modal">Delete</button>';
			}
		}],
		"data": criteria_json,
		"dom": '<"row dataTables-table"<"col-xs-12"t>>',
		"info": false,
		"lengthChange": false,
		"ordering": false,
		"paging": false,
		"searching": false
	});

	if (!criteria_json.length) {
		caps_error_msg = "At least one criteria is required for the component to be valid.";
		$( "#criteria-error-msg" ).html(caps_error_msg);
		$( "#criteria-error-alert" ).removeClass("hidden");
		$( "#criteria-tab-link" ).css("color", "#a94442");
		$( "#criteria-tab-icon" ).removeClass("hidden");
		$( "#criteria tbody" ).addClass("hidden");
	}

	$( "#criteria tbody select.is_and" ).each(function(index) {
		var data = criteria.row($( this ).parents("tr")).data();
		$( this ).selectpicker("val", data.is_and);
	});

	$( "#criteria tbody select.criteria" ).each(function(index) {
		var data = criteria.row($( this ).parents("tr")).data();
		var criteria_select = $( this );
		$.map(ext_attrs_json, function(ea) {
			criteria_select.append($( "<option>" ).attr("value", ea.key_id).text(ea.name));
		});
		$.each(criteria_opts, function(index, value) {
			criteria_select.append($( "<option>" ).attr("value", value).text(value));
		});
		$( this ).selectpicker("val", data.name);
	});

	$( "#criteria tbody select.operator" ).each(function(index) {
		var data = criteria.row($( this ).parents("tr")).data();
		var operator_opts = getOperators(data.name);
		var operator_select = $( this );
		$.each(operator_opts, function(index, value) {
			operator_select.append($( "<option>" ).attr("value", value).text(value));
		});
		$( this ).selectpicker("val", data.operator);
	});

	if (component_json.source_id > 0) {
		$( "#criteria select, #criteria input, #criteria button" ).prop("disabled", true);
	}

	$( "#criteria tbody .bootstrap-select" ).on("click", function() {
		$( this ).parents("div.form-group").removeClass("has-error has-warning has-success has-feedback").find("span.form-control-feedback").remove();
	});

	$( "#criteria tbody select.is_and" ).on("change", function() {
		var element = $( this );
		var data = criteria.row(element.parents("tr")).data();
		$.post("patchCtl.php?id=" + data.id, { "table": "criteria", "field": "is_and", "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + component_json.title_id, { "title_modified": true });
				data.is_and = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#criteria tbody select.criteria" ).on("change", function() {
		var element = $( this );
		var data = criteria.row(element.parents("tr")).data();
		$.post("patchCtl.php?id=" + data.id, { "table": "criteria", "field": "name", "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + component_json.title_id, { "title_modified": true });
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
					$.post("patchCtl.php?id=" + data.id, { "table": "criteria", "field": "operator", "value": operator_opts[0] });
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
					$.post("patchCtl.php?id=" + data.id, { "table": "criteria", "field": "type", "value": type });
					data.type = type;
				}
				data.name = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#criteria tbody select.operator" ).on("change", function() {
		var element = $( this );
		var data = criteria.row(element.parents("tr")).data();
		$.post("patchCtl.php?id=" + data.id, { "table": "criteria", "field": "operator", "value": element.val() }).done(function(result) {
			if (result) {
				element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
				$.post("patchCtl.php?title_id=" + component_json.title_id, { "title_modified": true });
				data.operator = element.val();
			} else {
				element.parents("div.form-group").addClass("has-error has-feedback").append('<span class="glyphicon glyphicon-exclamation-sign form-control-feedback hidden-xs" aria-hidden="true"></span>');
			}
		});
	});

	$( "#criteria tbody input" ).on("focus", function() {
		var data = criteria.row($( this ).parents("tr")).data();
		$( this ).val(data.value);
		$( this ).parents("div.form-group").removeClass("has-error has-warning has-success has-feedback").find("span.form-control-feedback").remove();
	});
	$( "#criteria tbody input" ).on("change", function() {
		var element = $( this );
		var data = criteria.row(element.parents("tr")).data();
		if (/^.{0,255}$/.test(element.val())) {
			$.post("patchCtl.php?id=" + data.id, { "table": "criteria", "field": "value", "value": element.val() }).done(function(result) {
				if (result) {
					element.parents("div.form-group").addClass("has-success has-feedback").append('<span class="glyphicon glyphicon-ok form-control-feedback hidden-xs" aria-hidden="true"></span>');
					$.post("patchCtl.php?title_id=" + component_json.title_id, { "title_modified": true });
					data.value = element.val();
				} else {
					element.parents("div.form-group").addClass("has-error");
				}
			});
		} else {
			element.parents("div.form-group").addClass("has-error");
		}
	});

	$( "#criteria tbody" ).on("click", "button", function() {
		var data = criteria.row( $(this).parents("tr") ).data();
		$( "#del-criteria-modal" ).on("show.bs.modal", function(event) {
			// $( "#del-criteria-msg" ).html('Are you sure you want to delete <em>' + data.name + ' ' + data.operator + ' ' + (data.value.length ? data.value : '""') + '</em> ?<br><small>This action is permanent and cannot be undone.</small>');
			$( "#del-criteria-btn" ).val(data.id);
		});
	});

	$.map(ext_attrs_json, function(ea) {
		$( "#new-criteria-name" ).append($( "<option>" ).attr("value", ea.key_id).text(ea.name));
	});
	$.each(criteria_opts, function(index, value) {
		$( "#new-criteria-name" ).append($( "<option>" ).attr("value", value).text(value));
	});

	$( "#new-criteria-modal" ).on("show.bs.modal", function(event) {
		$( "#new-criteria-name" ).selectpicker("val", " ");
		$( "#new-criteria-operator" ).val("is");
		$( "#new-criteria-value" ).val("");
		$( "#new-criteria-type" ).val("recon");
		$( "#new-criteria-is-and" ).val(1);
		$( "#new-criteria-sort-order" ).val((criteria_json.length ? parseInt(criteria_json[criteria_json.length - 1].sort_order) + 1 : 0));
		if (ext_attrs_json.length == 0 && criteria_json.length == 0) {
			$( "#new-criteria-name" ).selectpicker("val", "Application Bundle ID");
			$( "#new-criteria-value" ).val(component_json.bundle_id);
		} else if (ext_attrs_json.length == 0 && criteria_json.length == 1) {
			$( "#new-criteria-name" ).selectpicker("val", "Application Version");
			$( "#new-criteria-value" ).val(component_json.version);
		} else if (ext_attrs_json.length == 1 && criteria_json.length == 0) {
			$( "#new-criteria-name" ).selectpicker("val", ext_attrs_json[0].key_id);
			$( "#new-criteria-value" ).val(component_json.version);
			$( "#new-criteria-type" ).val("extensionAttribute");
		} else {
			$( "#new-criteria-btn" ).prop("disabled", true);
		}
	});

	$( "#new-criteria-name" ).on("change", function() {
		$( "#new-criteria-btn" ).prop("disabled", $( "#new-criteria-name" ).val() == " ");
	});

	$( "#del-criteria-modal" ).on("hide.bs.modal", function(event) {
		$( "#del-criteria-btn" ).val("");
	});

	$( ".dataTables_wrapper" ).removeClass("form-inline");

	if (component_json) {
		$( ".breadcrumb" ).prepend('<li><a href="managePatch.php?id=' + component_json.patch_id + '"><small>' + component_json.patch_version + '</small></a></li>');
		$( ".breadcrumb" ).prepend('<li><a href="manageTitle.php?id=' + component_json.title_id + '"><small>' + component_json.title_name + '</small></a></li>');
		$( ".breadcrumb" ).prepend('<li><a href="patchTitles.php"><small>Software Titles</small></a></li>');
	}

	if (pdo_error.length) {
		$( "title, #heading" ).html("Database Error");
		$( "#top-tabs" ).addClass("hidden");
		$( ".tab-content" ).addClass("hidden");
		error_msg = pdo_error;
	}

	if (!component_json) {
		$( "title, #heading" ).html("Not Found");
		error_msg = "The requested component was not found on this server.";
		$( "#top-tabs" ).addClass("hidden");
		$( ".tab-content" ).addClass("hidden");
	}

	if (error_msg.length) {
		$( "#error-alert" ).removeClass("hidden");
		$( "#error-msg" ).html(error_msg);
		$( "#spacer-alert" ).removeClass("hidden");
		$( "#spacer-msg" ).html(error_msg);
	}

	$( "select" ).selectpicker();
	$( ".selectpicker[data-style='btn-default btn-sm']" ).parent("div").css("height", "30px");
});
