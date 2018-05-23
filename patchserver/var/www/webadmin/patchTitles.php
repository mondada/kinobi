<?php

include "inc/config.php";
include "inc/auth.php";
include "inc/functions.php";

$title = "Software Titles";

include "inc/header.php";

include "inc/dbConnect.php";

$sw_titles = array();

if (isset($pdo)) {

	// Create Software Title
	if (isset($_POST['create_title'])) {
		$name = $_POST['name'];
		$publisher = $_POST['publisher'];
		$app_name = $_POST['app_name'];
		$bundle_id = $_POST['bundle_id'];
		$modified = time();
		$current = $_POST['current'];
		$name_id = $_POST['name_id'];
		$stmt = $pdo->prepare('INSERT INTO titles (name, publisher, app_name, bundle_id, modified, current, name_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute([$name, $publisher, $app_name, $bundle_id, $modified, $current, $name_id]);
		if ($stmt->errorCode() == '00000') {
			$status_msg = "<div class=\"text-success\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-ok-sign\"></span> Created Software Title '<a href=\"manageTitle.php?id=".$pdo->lastInsertId()."\">".$name."</a>'</div>";
		} else {
			$status_msg = "<div class=\"text-danger\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Delete Software Title
	if (isset($_POST['delete_title'])) {
		$title_id = $_POST['delete_title'];
		$title_name = $_POST['delete_name'][$title_id];
		$stmt = $pdo->prepare('DELETE FROM titles WHERE id = ?');
		$stmt->execute([$title_id]);
		if ($stmt->errorCode() == '00000') {
			$status_msg = "<div class=\"text-success\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-ok-sign\"></span> Deleted Software Title '".$title_name."'</div>";
		} else {
			$status_msg = "<div class=\"text-danger\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Software Title Summary
	$stmt = $pdo->query('SELECT id, name_id, name, publisher, current, modified, enabled FROM titles ORDER BY publisher, name');
	while ($sw_title = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$sw_title['enabled'] = ($sw_title['enabled'] == "1") ? "1" : "0";
		$sw_title['error'] = array();
		$sw_title['requirements'] = $pdo->query('SELECT id FROM requirements WHERE title_id = "'.$sw_title['id'].'"')->fetchAll(PDO::FETCH_COLUMN);
		if (sizeof($sw_title['requirements']) == 0) {
			array_push($sw_title['error'], "requirements");
		}
		$sw_title['patches'] = $pdo->query('SELECT id FROM patches WHERE title_id = "'.$sw_title['id'].'" AND enabled = 1')->fetchAll(PDO::FETCH_COLUMN);
		if (sizeof($sw_title['patches']) == 0) {
			array_push($sw_title['error'], "patches");
		}
		if (sizeof($sw_title['error']) > 0 && $sw_title['enabled'] == "1") {
			$sw_title['enabled'] == "0";
			$disable = $pdo->prepare('UPDATE titles SET enabled = 0 WHERE id = ?');
			$disable->execute([$sw_title['id']]);
			if ($disable->errorCode() == '00000') {
				$status_msg = $status_msg."<div class=\"text-warning\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span> Software Title '".$sw_title['name']."' has been disabled.</div>";
			} else {
				$status_msg = $status_msg."<div class=\"text-danger\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span> ".$stmt->errorInfo()[2]."</div>";
			}
		}
		array_push($sw_titles, $sw_title);
	}

}

// ####################################################################
// End of GET/POST parsing
// ####################################################################

?>

<?php if (isset($pdo)) { ?>

<link rel="stylesheet" href="theme/awesome-bootstrap-checkbox.css"/>

<script type="text/javascript">
	var existingIds = [<?php echo "\"".implode('", "', array_map(function($el){ return $el['name_id']; }, $sw_titles))."\""; ?>];
</script>

<script type="text/javascript" src="scripts/patchValidation.js"></script>

<link rel="stylesheet" href="theme/dataTables.bootstrap.css" />

<script type="text/javascript" src="scripts/dataTables/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="scripts/dataTables/dataTables.bootstrap.min.js"></script>
<script type="text/javascript" src="scripts/Buttons/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="scripts/Buttons/buttons.bootstrap.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {
	$('#sw_titles').DataTable( {
		buttons: [ {
			text: '<span class="glyphicon glyphicon-plus"></span> New',
				action: function ( e, dt, node, config ) {
                    $("#createTitle").modal();
				}
			}
		],
		"dom": "<'row'<'col-sm-4'f><'col-sm-4'i><'col-sm-4'B>>" + "<'row'<'col-sm-12'tr>>" + "<'row'<'col-sm-5'l><'col-sm-7'p>>",
		"order": [ 1, 'asc' ],
		"lengthMenu": [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
		"columns": [
			{ "orderable": false },
			null,
			null,
			null,
			null,
			{ "orderable": false }
		]
	});
} );
</script>

<div class="description">&nbsp;</div>

<h2>Software Titles</h2>

<div class="row">
	<div class="col-xs-12 col-sm-12 col-lg-12">

		<form action="patchTitles.php" method="post" name="title" id="title">

			<hr>
			<?php echo (isset($status_msg) ? $status_msg : "<br>"); ?>

			<table id="sw_titles" class="table table-striped">
				<thead>
					<tr>
						<th>Enable</th>
						<th>Name</th>
						<th>Publisher</th>
						<th><nobr>Last Modified</nobr></th>
						<th><nobr>Current Version</nobr></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($sw_titles as $sw_title) { ?>
					<tr>
						<td>
							<div class="checkbox checkbox-primary">
								<input type="checkbox" class="styled" name="enable_title" id="enable_title" value="<?php echo $sw_title['id']; ?>" onChange="javascript:ajaxPost('patchCtl.php?title_id='+this.value, 'title_enabled='+this.checked);" <?php echo (sizeof($sw_title['error']) > 0) ? "disabled " : ""; ?><?php echo ($sw_title['enabled'] == "1" && sizeof($sw_title['error']) == 0) ? "checked " : ""; ?>/>
								<label/>
							</div>
						</td>
						<td nowrap><a href="manageTitle.php?id=<?php echo $sw_title['id']; ?>"><?php echo $sw_title['name']; ?></a></td>
						<td nowrap><?php echo $sw_title['publisher']; ?></td>
						<td nowrap><?php echo gmdate("Y-m-d\TH:i:s\Z", $sw_title['modified']); ?><!-- <?php echo gmdate("M j, Y \a\\t g:i A", $sw_title['modified']); ?> --></td>
						<td nowrap><?php echo $sw_title['current']; ?></td>
						<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#confirm_delete<?php echo $sw_title['id']; ?>">Delete</button></td>
					</tr>
					<?php } ?>
				</tobdy>
			</table>

			<div class="modal fade" id="createTitle" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
				<div class="modal-dialog" role="document">
					<div class="modal-content">
						<div class="modal-header">
							<h3 class="modal-title">New Software Title</h3>
						</div>
						<div class="modal-body">

							<h5 id="name_label"><strong>Name</strong> <small>Name of the patch management software title.</small></h5>
							<div class="form-group">
								<input type="text" name="name" id="name" class="form-control input-sm" onKeyUp="validString(this, 'name_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validString(this, 'name_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Required]" />
							</div>

							<h5 id="publisher_label"><strong>Publisher</strong> <small>Publisher of the patch management software title.</small></h5>
							<div class="form-group">
								<input type="text" name="publisher" id="publisher" class="form-control input-sm" onKeyUp="validString(this, 'publisher_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validString(this, 'publisher_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Required]" />
							</div>

							<h5 id="app_name_label"><strong>Application Name</strong> <small>Deprecated.</small></h5>
							<div class="form-group">
								<input type="text" name="app_name" id="app_name" class="form-control input-sm" onKeyUp="validOrEmptyString(this, 'app_name_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validOrEmptyString(this, 'app_name_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Optional]" />
							</div>

							<h5 id="bundle_id_label"><strong>Bundle Identifier</strong> <small>Deprecated.</small></h5>
							<div class="form-group">
								<input type="text" name="bundle_id" id="bundle_id" class="form-control input-sm" onKeyUp="validOrEmptyString(this, 'bundle_id_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validOrEmptyString(this, 'bundle_id_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Optional]" />
							</div>

							<h5 id="current_label"><strong>Current Version</strong> <small>Used for reporting the latest version of the patch management software title to Jamf Pro.</small></h5>
							<div class="form-group">
								<input type="text" name="current" id="current" class="form-control input-sm" onKeyUp="validString(this, 'current_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validString(this, 'current_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Required]" />
							</div>

							<h5 id="name_id_label"><strong>ID</strong> <small>Uniquely identifies this software title on the external source.<!-- <br><strong>Note:</strong> An <span style="font-family:monospace;">id</span> cannot be duplicated on an individual external source. --></small></h5>
							<div class="form-group">
								<input type="text" name="name_id" id="name_id" class="form-control input-sm" onKeyUp="validNameId(this, 'name_id_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validNameId(this, 'name_id_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Required]" />
							</div>

						</div>
						<div class="modal-footer">
							<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
							<button type="submit" name="create_title" id="create_title" class="btn btn-primary btn-sm" disabled >Save</button>
						</div>
					</div>
				</div>
			</div>

			<?php foreach ($sw_titles as $sw_title) { ?>
			<div class="modal fade" id="confirm_delete<?php echo $sw_title['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
				<div class="modal-dialog" role="document">
					<div class="modal-content">
						<div class="modal-header">
							<h3 class="modal-title" id="modalLabel">Delete '<?php echo $sw_title['name']; ?>'?</h3>
						</div>
						<div class="modal-body">
							<div class="text-muted">This action is permanent and cannot be undone.</div>
							<input type="hidden" name="delete_name[<?php echo $sw_title['id']; ?>]" value="<?php echo $sw_title['name']; ?>" />
						</div>
						<div class="modal-footer">
							<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
							<button type="submit" name="delete_title" id="delete_title" class="btn btn-danger btn-sm pull-right" value="<?php echo $sw_title['id']; ?>">Delete</button>
						</div>
					</div>
				</div>
			</div>
			<?php } ?>

		</form><!-- end form title -->

	</div><!-- /.col -->
</div><!-- /.row -->

<?php } else { ?>

<div class="row">
	<div class="col-xs-12 col-sm-12 col-lg-12">

		<hr>
		<br>

		<button type="button" class="btn btn-sm btn-default" value="Settings" onclick="document.location.href='patchDB.php'">

	</div><!-- /.col -->
</div><!-- /.row -->

<?php } ?>

<?php include "inc/footer.php"; ?>