/**
 * Kinobi - external patch source for Jamf Pro
 *
 * @author      Duncan McCracken <duncan.mccracken@mondada.coma.au>
 * @copyright   2018-2019 Mondada Pty Ltd
 * @link        https://mondada.github.io
 * @license     https://github.com/mondada/kinobi/blob/master/LICENSE
 * @version     1.2
 *
 */

function getHTTPObj() {
	return new XMLHttpRequest();
}

function ajaxPost(url, data) {
	var http = getHTTPObj();
	http.open("POST", url, false);
	http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	http.send(data);
	return http.responseText;
}

function enableButton(buttonId, enable) {
	document.getElementById(buttonId).disabled = !enable;
}

function showError(element, labelId = false) {
	element.parentElement.classList.add("has-error");
	if (labelId) {
		document.getElementById(labelId).classList.add("text-danger");
	}
}

function hideError(element, labelId = false) {
	element.parentElement.classList.remove("has-error");
	if (labelId) {
		document.getElementById(labelId).classList.remove("text-danger");
	}
}

function showSuccess(element, offset = false) {
	var span = document.createElement("span");
	span.className = "glyphicon glyphicon-ok form-control-feedback text-success";
	if (offset) {
		span.style.right = offset + "px";
	}
	element.parentElement.appendChild(span);
}

function hideSuccess(element) {
	var span = element.parentElement.getElementsByTagName("span");
	for (var i = 0; i < span.length; i++) {
		if (span[i].classList.contains("form-control-feedback")) {
			element.parentElement.removeChild(span[i]);
		}
	}
}

function showWarning(element, offset = false) {
	var span = document.createElement("span");
	span.className = "glyphicon glyphicon-exclamation-sign form-control-feedback text-muted";
	if (offset) {
		span.style.right = offset + "px";
	}
	element.parentElement.appendChild(span);
}

function hideWarning(element) {
	var span = element.parentElement.getElementsByTagName("span");
	for (var i = 0; i < span.length; i++) {
		if (span[i].classList.contains("form-control-feedback")) {
			element.parentElement.removeChild(span[i]);
		}
	}
}

function validString(element, labelId = false) {
	hideSuccess(element);
	if (/^.{1,255}$/.test(element.value)) {
		hideError(element, labelId);
	} else {
		showError(element, labelId);
	}
}

function updateString(element, table, field, row_id, offset = false) {
	hideWarning(element);
	if (/^.{1,255}$/.test(element.value)) {
		ajaxPost("patchCtl.php?table="+table+"&field="+field+"&id="+row_id, "value="+element.value);
		showSuccess(element, offset);
	}
}

function validOrEmptyString(element, labelId = false) {
	hideSuccess(element);
	if (/^.{0,255}$/.test(element.value)) {
		hideError(element, labelId);
	} else {
		showError(element, labelId);
	}
}

function updateOrEmptyString(element, table, field, row_id, offset = false) {
	hideWarning(element);
	if (/^.{0,255}$/.test(element.value)) {
		ajaxPost("patchCtl.php?table="+table+"&field="+field+"&id="+row_id, "value="+element.value);
		showSuccess(element, offset);
	}
}

function validInteger(element, labelId = false) {
	hideSuccess(element);
	if (element.value != "" && element.value == parseInt(element.value)) {
		hideError(element, labelId);
	} else {
		showError(element, labelId);
	}
}

function updateInteger(element, table, field, row_id, offset = false) {
	hideWarning(element);
	if (element.value != "" && element.value == parseInt(element.value)) {
		ajaxPost("patchCtl.php?table="+table+"&field="+field+"&id="+row_id, "value="+element.value);
		showSuccess(element, offset);
	}
}

function validVersion(element, labelId = false) {
	hideSuccess(element);
	if (patchVersions.indexOf(element.value) == -1 && /^.{1,255}$/.test(element.value)) {
		hideError(element, labelId);
	} else {
		showError(element, labelId);
	}
}

function updateVersion(element, table, field, row_id, offset = false) {
	hideWarning(element);
	if (patchVersions.indexOf(element.value) == -1 && /^.{1,255}$/.test(element.value)) {
		ajaxPost("patchCtl.php?table="+table+"&field="+field+"&id="+row_id, "value="+element.value);
		showSuccess(element, offset);
	}
}

function validDate(element, labelId = false) {
	hideSuccess(element);
	if (/^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test(element.value)) {
		hideError(element, labelId);
	} else {
		showError(element, labelId);
	}
}

function updateDate(element, table, field, row_id, offset = false) {
	hideWarning(element);
	if (/^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test(element.value)) {
		ajaxPost("patchCtl.php?patch_id="+row_id, "patch_released="+element.value);
		showSuccess(element, offset);
	}
}

function updateTimestamp(row_id) {
	ajaxPost("patchCtl.php?title_id="+row_id, "title_modified=true");
}

function validNameId(element, labelId = false) {
	hideSuccess(element);
	if (existingIds.indexOf(element.value) == -1 && /^([A-Za-z0-9.-]){1,255}$/.test(element.value)) {
		hideError(element, labelId);
	} else {
		showError(element, labelId);
	}
}

function updateNameId(element, table, field, row_id, offset = false) {
	hideWarning(element);
	if (existingIds.indexOf(element.value) == -1 && /^([A-Za-z0-9.-]){1,255}$/.test(element.value)) {
		ajaxPost("patchCtl.php?table="+table+"&field="+field+"&id="+row_id, "value="+element.value);
		showSuccess(element, offset);
	}
}

function validEaKeyid(element, labelId = false) {
	hideSuccess(element);
	if (existingKeys.indexOf(element.value) == -1 && /^.{1,255}$/.test(element.value)) {
		hideError(element, labelId);
	} else {
		showError(element, labelId);
	}
}

function updateCriteria(element, operatorId, typeId, table, row_id, offset = false) {
	// Save Criteria
	hideWarning(element);
	ajaxPost("patchCtl.php?table="+table+"&field=name&id="+row_id, "value="+element.value);
	showSuccess(element, offset);
	// Update Operator
	switch (element.value) {
		case "Application Title":
			var options = ["is", "is not", "has", "does not have"];
			break;
		case "Operating System Version":
			var options = ["is", "is not", "like", "not like", "greater than", "less than", "greater than or equal", "less than or equal"];
			break;
		case "Boot Drive Available MB":
		case "Drive Capacity MB":
		case "Number of Processors":
		case "Processor Speed MHz":
		case "Total Number of Cores":
		case "Total RAM MB":
			var options = ["is", "is not", "more than", "less than"];
			break;
		default:
			var options = ["is", "is not", "like", "not like"];
	}
	var operator = document.getElementById(operatorId);
	var current = operator.value;
	while (operator.options.length) {
		operator.remove(0);
	}
	for (i = 0; i < options.length; i++) {
		var option = new Option(options[i], options[i]);
		operator.options.add(option);
	}
	if (options.indexOf(current) >= 0) {
		operator.value = current;
	} else {
		hideSuccess(operator);
		ajaxPost("patchCtl.php?table="+table+"&field=operator&id="+row_id, "value="+operator.value);
		showWarning(operator, offset);
	}
	// Update Type
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
	var validVersion = patchVersions.indexOf(document.getElementById(version).value) == -1 && /^.{1,255}$/.test(document.getElementById(version).value);
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
