function enableButton(buttonId, enable) {
	document.getElementById(buttonId).disabled = !enable;
}

function showError(element, icon = false) {
	element.parentElement.classList.add("has-error");
	if (icon) {
		var span = document.createElement("span");
		span.className = "glyphicon glyphicon-remove form-control-feedback";
		span.style.right = "15px";
		element.parentElement.appendChild(span);
	}
}

function hideError(element, icon = false) {
	element.parentElement.classList.remove("has-error");
	if (icon) {
		var span = element.parentElement.getElementsByTagName("span");
		for (var i = 0; i < span.length; i++) {
			element.parentElement.removeChild(span[i]);
		}
	}
}

function showSuccess(element, icon = false) {
	element.parentElement.classList.add("has-success");
	if (icon) {
		var span = document.createElement("span");
		span.className = "glyphicon glyphicon-ok form-control-feedback";
		span.style.right = "15px";
		element.parentElement.appendChild(span);
	}
}

function hideSuccess(element, icon = false) {
	element.parentElement.classList.remove("has-success");
	if (icon) {
		var span = element.parentElement.getElementsByTagName("span");
		for (var i = 0; i < span.length; i++) {
			element.parentElement.removeChild(span[i]);
		}
	}
}

function showWarning(element, icon = false) {
	element.parentElement.classList.add("has-warning");
	if (icon) {
		var span = document.createElement("span");
		span.className = "glyphicon glyphicon-warning-sign form-control-feedback";
		span.style.right = "15px";
		element.parentElement.appendChild(span);
	}
}

function hideWarning(element, icon = false) {
	element.parentElement.classList.remove("has-warning");
	if (icon) {
		var span = element.parentElement.getElementsByTagName("span");
		for (var i = 0; i < span.length; i++) {
			element.parentElement.removeChild(span[i]);
		}
	}
}

function validString(element, icon = false) {
	hideSuccess(element, icon);
	if (/^.{1,255}$/.test(element.value)) {
		hideError(element, icon);
	} else {
		showError(element, icon);
	}
}

function updateString(element, table, field, row_id, icon = false) {
	if (/^.{1,255}$/.test(element.value)) {
		ajaxPost("patchCtl.php?table="+table+"&field="+field+"&id="+row_id, "value="+element.value);
		showSuccess(element, icon);
	}
}

function validOrEmptyString(element, icon = false) {
	hideSuccess(element, icon);
	if (/^.{0,255}$/.test(element.value)) {
		hideError(element, icon);
	} else {
		showError(element, icon);
	}
}

function updateOrEmptyString(element, table, field, row_id, icon = false) {
	if (/^.{0,255}$/.test(element.value)) {
		ajaxPost("patchCtl.php?table="+table+"&field="+field+"&id="+row_id, "value="+element.value);
		showSuccess(element, icon);
	}
}

function validInteger(element, icon = false) {
	hideSuccess(element, icon);
	if (element.value != "" && element.value == parseInt(element.value)) {
		hideError(element, icon);
	} else {
		showError(element, icon);
	}
}

function updateInteger(element, table, field, row_id, icon = false) {
	if (element.value != "" && element.value == parseInt(element.value)) {
		ajaxPost("patchCtl.php?table="+table+"&field="+field+"&id="+row_id, "value="+element.value);
		showSuccess(element, icon);
	}
}

function validDate(element) {
	hideSuccess(element);
	if (/^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test(element.value)) {
		hideError(element);
	} else {
		showError(element);
	}
}

function updateDate(element, table, field, row_id) {
	if (/^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test(element.value)) {
		ajaxPost("patchCtl.php?patch_id="+row_id, "patch_released="+element.value);
		showSuccess(element);
	}
}

function updateTimestamp(row_id) {
	ajaxPost("patchCtl.php?title_id="+row_id, "title_modified=true");
}
 
function validNameId(element, icon = false) {
	hideSuccess(element, icon);
	if (existingIds.indexOf(element.value) == -1 && /^([A-Za-z0-9.-]){1,255}$/.test(element.value)) {
		hideError(element, icon);
	} else {
		showError(element, icon);
	}
}

function updateNameId(element, table, field, row_id, icon = false) {
	if (existingIds.indexOf(element.value) == -1 && /^([A-Za-z0-9.-]){1,255}$/.test(element.value)) {
		ajaxPost("patchCtl.php?table="+table+"&field="+field+"&id="+row_id, "value="+element.value);
		showSuccess(element, icon);
	}
}

function validEaKeyid(element, icon = false) {
	hideSuccess(element, icon);
	if (existingKeys.indexOf(element.value) == -1 && /^.{1,255}$/.test(element.value)) {
		hideError(element, icon);
	} else {
		showError(element, icon);
	}
}

function updateCriteria(element, operatorId, typeId, table, row_id) {
	ajaxPost("patchCtl.php?table="+table+"&field=name&id="+row_id, "value="+element.value);
	showSuccess(element);
	var operator = document.getElementById(operatorId);
	var state = (element.value != "Operating System Version");
	if (state == true && operator.value == "greater than"
		|| state == true && operator.value == "less than"
		|| state == true && operator.value == "greater than or equal"
		|| state == true && operator.value == "less than or equal") {
		operator.value = "is";
		ajaxPost("patchCtl.php?table="+table+"&field=operator&id="+row_id, "value="+operator.value);
		showWarning(operator);
	}
	var options = operator.getElementsByTagName("option");
	for (var i = 0; i < options.length; i++) {
		if (options[i].value == "greater than") { options[i].disabled = state; };
		if (options[i].value == "less than") { options[i].disabled = state; };
		if (options[i].value == "greater than or equal") { options[i].disabled = state; };
		if (options[i].value == "less than or equal") { options[i].disabled = state; };
	}
	var type = document.getElementById(typeId);
	if (extAttrKeys.indexOf(element.value) >= 0) {
		if (type.value != "extensionAttribute") {
			type.value = "extensionAttribute";
			ajaxPost("patchCtl.php?table="+table+"&field=type&id="+row_id, "value="+type.value);
		}
	} else {
		if (type.value != "recon") {
			type.value = "recon";
			ajaxPost("patchCtl.php?table="+table+"&field=type&id="+row_id, "value="+type.value);
		}
	}
}

function selectCriteria(element, typeId, operatorId) {
	var type = document.getElementById(typeId);
	if (extAttrKeys.indexOf(element.value) >= 0) {
		type.value = "extensionAttribute";
	} else {
		type.value = "recon";
	}
	var operator = document.getElementById(operatorId);
	operator.value = "is";
}

function validTitle(button, name, publisher, app_name, bundle_id, version, name_id) {
	var validName = /^.{1,255}$/.test(document.getElementById(name).value);
	var validPublisher = /^.{1,255}$/.test(document.getElementById(publisher).value);
	var validAppName = /^.{0,255}$/.test(document.getElementById(app_name).value);
	var validBundleId = /^.{0,255}$/.test(document.getElementById(bundle_id).value);
	var validVersion = /^.{1,255}$/.test(document.getElementById(version).value);
	var validNameId = existingIds.indexOf(document.getElementById(name_id).value) == -1 && /^([A-Za-z0-9.-]){1,255}$/.test(document.getElementById(name_id).value);
	enableButton(button, validName && validPublisher && validAppName && validBundleId && validVersion && validNameId);
}

function validEa(button, name, key_id) {
	var validName = /^.{1,255}$/.test(document.getElementById(name).value);
	var validKeyId = existingKeys.indexOf(document.getElementById(key_id).value) == -1 && /^.{1,255}$/.test(document.getElementById(key_id).value);
	enableButton(button, validKeyId && validName);
}

function validCriteria(button, sort_order, name, operator, type) {
	var validSortOrder = document.getElementById(sort_order).value != "" && document.getElementById(sort_order).value == parseInt(document.getElementById(sort_order).value);
	var validName = document.getElementById(name).value != "";
	var validOperator = document.getElementById(operator).value != "";
	var validType = document.getElementById(operator).value != "";
	enableButton(button, validSortOrder && validName && validOperator && validType);
}

function validPatch(button, sort_order, version, released, min_os) {
	var validSortOrder = document.getElementById(sort_order).value != "" && document.getElementById(sort_order).value == parseInt(document.getElementById(sort_order).value);
	var validVersion = /^.{1,255}$/.test(document.getElementById(version).value);
	var validReleased = /^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test(document.getElementById(released).value);
	var validMinOS = /^.{1,255}$/.test(document.getElementById(min_os).value);
	enableButton(button, validSortOrder && validVersion && validReleased && validMinOS);
}

function validComponent(button, name, version) {
	var validName = /^.{1,255}$/.test(document.getElementById(name).value);
	var validVersion = /^.{1,255}$/.test(document.getElementById(version).value);
	enableButton(button, validName && validVersion);
}

function validKillApp(button, app_name, bundle_id) {
	var validAppName = /^.{1,255}$/.test(document.getElementById(app_name).value);
	var validBundleId = /^.{1,255}$/.test(document.getElementById(bundle_id).value);
	enableButton(button, validAppName && validBundleId);
}

function getScriptType(e) {
	for (var t = [["#!/usr/bin/env bash", "sh"], ["#!/bin/sh", "sh"], ["#!/bin/bash", "sh"], ["#!/bin/csh", "sh"], ["#!/usr/bin/perl", "perl"], ["#!/usr/bin/python", "python"]], n=0; n<t.length; n++)
		if (0==e.indexOf(t[n][0]))
			return t[n][1];
	return "text"
}
