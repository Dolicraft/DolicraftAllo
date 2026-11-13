<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/templates.php
 * \ingroup dolicraftallo
 * \brief   SMS templates management page
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');

// Load translation files
$langs->loadLangs(array("admin", "dolicraftallo@dolicraftallo"));

// Security check - admin only
if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');

//
// Actions
//

// Add template
if ($action == 'add') {
	$label = GETPOST('label', 'alphanohtml');
	$content = GETPOST('content', 'restricthtml');
	$context = GETPOST('context', 'aZ09');
	$position = GETPOSTINT('position');

	if (empty($label)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesaliases("TemplateLabel")), null, 'errors');
	} elseif (empty($content)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesaliases("TemplateContent")), null, 'errors');
	} else {
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolicraftallo_sms_templates";
		$sql .= " (entity, label, content, context, position, active, datec, fk_user_creat)";
		$sql .= " VALUES (";
		$sql .= ((int) $conf->entity).",";
		$sql .= "'".$db->escape($label)."',";
		$sql .= "'".$db->escape($content)."',";
		$sql .= "'".$db->escape($context)."',";
		$sql .= ((int) $position).",";
		$sql .= "1,";
		$sql .= "'".$db->idate(dol_now())."',";
		$sql .= ((int) $user->id);
		$sql .= ")";

		$resql = $db->query($sql);
		if ($resql) {
			setEventMessages($langs->trans("TemplateAdded"), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
		$action = '';
	}
}

// Update template
if ($action == 'update' && $id > 0) {
	$label = GETPOST('label', 'alphanohtml');
	$content = GETPOST('content', 'restricthtml');
	$context = GETPOST('context', 'aZ09');
	$position = GETPOSTINT('position');

	if (empty($label)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesaliases("TemplateLabel")), null, 'errors');
	} elseif (empty($content)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesaliases("TemplateContent")), null, 'errors');
	} else {
		$sql = "UPDATE ".MAIN_DB_PREFIX."dolicraftallo_sms_templates SET";
		$sql .= " label = '".$db->escape($label)."',";
		$sql .= " content = '".$db->escape($content)."',";
		$sql .= " context = '".$db->escape($context)."',";
		$sql .= " position = ".((int) $position);
		$sql .= " WHERE rowid = ".((int) $id);
		$sql .= " AND entity = ".((int) $conf->entity);

		$resql = $db->query($sql);
		if ($resql) {
			setEventMessages($langs->trans("TemplateUpdated"), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
		$action = '';
	}
}

// Delete template
if ($action == 'confirm_delete' && $id > 0) {
	$sql = "DELETE FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_templates";
	$sql .= " WHERE rowid = ".((int) $id);
	$sql .= " AND entity = ".((int) $conf->entity);

	$resql = $db->query($sql);
	if ($resql) {
		setEventMessages($langs->trans("TemplateDeleted"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	$action = '';
}

// Toggle active
if ($action == 'toggleactive' && $id > 0) {
	$sql = "UPDATE ".MAIN_DB_PREFIX."dolicraftallo_sms_templates SET";
	$sql .= " active = IF(active = 1, 0, 1)";
	$sql .= " WHERE rowid = ".((int) $id);
	$sql .= " AND entity = ".((int) $conf->entity);

	$db->query($sql);
	$action = '';
}

//
// View
//
$form = new Form($db);

llxHeader('', $langs->trans("SMSTemplates"), '', '', 0, 0, '', '', '', 'mod-dolicraftallo page-admin-templates');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans("SMSTemplates"), $linkback, 'title_setup');

$head = dolicraftalloAdminPrepareHead();
print dol_get_fiche_head($head, 'templates', $langs->trans("DolicraftAllo"), -1, 'phone');

// Context options
$contextOptions = array(
	'' => $langs->trans('TemplateContextAll'),
	'all' => $langs->trans('TemplateContextAll'),
	'societe' => $langs->trans('ThirdParty'),
	'contact' => $langs->trans('Contact'),
	'facture' => $langs->trans('Invoice'),
	'propal' => $langs->trans('Proposal'),
);

// Available variables help
print '<div class="info" style="margin-bottom:15px;">';
print '<i class="fas fa-info-circle pictofixedwidth"></i>';
print '<strong>'.$langs->trans('AvailableVariables').'</strong>: ';
print '<code>{contact_name}</code>, <code>{company_name}</code>, <code>{phone}</code>, <code>{invoice_ref}</code>, <code>{invoice_amount}</code>, <code>{invoice_due_date}</code>, <code>{my_company}</code>';
print '</div>';

// Edit form (for editing existing template)
$editObj = null;
if ($action == 'edit' && $id > 0) {
	$sql = "SELECT rowid, label, content, context, position FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_templates";
	$sql .= " WHERE rowid = ".((int) $id)." AND entity = ".((int) $conf->entity);
	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		$editObj = $db->fetch_object($resql);
	}
}

// Add / Edit form
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
if ($editObj) {
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.((int) $editObj->rowid).'">';
} else {
	print '<input type="hidden" name="action" value="add">';
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">'.($editObj ? $langs->trans('EditTemplate') : $langs->trans('AddTemplate')).'</td>';
print '</tr>';

// Label
print '<tr class="oddeven"><td class="titlefield fieldrequired">'.$langs->trans("TemplateLabel").'</td><td>';
print '<input type="text" name="label" class="flat minwidth300" maxlength="200" value="'.dol_escape_htmltag($editObj ? $editObj->label : '').'" required>';
print '</td></tr>';

// Content
print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("TemplateContent").'</td><td>';
$contentVal = $editObj ? $editObj->content : '';
print '<textarea name="content" id="tpl_content" class="flat" style="width:100%;min-height:80px;" maxlength="1000" required>'.dol_escape_htmltag($contentVal).'</textarea>';
print '<div style="text-align:right;"><span id="tpl_counter" style="font-size:0.8em;color:#9ca3af;">'.dol_strlen($contentVal).' / 1000</span></div>';
print '</td></tr>';

// Context
print '<tr class="oddeven"><td>'.$langs->trans("TemplateContext").'</td><td>';
print '<select name="context" class="flat">';
foreach ($contextOptions as $key => $val) {
	$selected = ($editObj && $editObj->context == $key) ? ' selected' : '';
	print '<option value="'.dol_escape_htmltag($key).'"'.$selected.'>'.dol_escape_htmltag($val).'</option>';
}
print '</select>';
print '</td></tr>';

// Position
print '<tr class="oddeven"><td>'.$langs->trans("Position").'</td><td>';
print '<input type="number" name="position" class="flat" style="width:80px;" value="'.($editObj ? (int) $editObj->position : 0).'" min="0">';
print '</td></tr>';

print '</table>';

print '<div class="center" style="margin:10px 0;">';
print '<input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans("Save")).'">';
if ($editObj) {
	print ' <a class="button button-cancel" href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">'.$langs->trans("Cancel").'</a>';
}
print '</div>';
print '</form>';

// List existing templates
$sql = "SELECT rowid, label, content, context, active, position, datec";
$sql .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_templates";
$sql .= " WHERE entity = ".((int) $conf->entity);
$sql .= " ORDER BY position ASC, label ASC";

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);

	print '<table class="noborder centpercent" style="margin-top:20px;">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Position").'</td>';
	print '<td>'.$langs->trans("TemplateLabel").'</td>';
	print '<td>'.$langs->trans("TemplateContent").'</td>';
	print '<td>'.$langs->trans("TemplateContext").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '<td class="right">'.$langs->trans("Action").'</td>';
	print '</tr>';

	if ($num > 0) {
		$i = 0;
		while ($i < $num) {
			$obj = $db->fetch_object($resql);

			print '<tr class="oddeven">';

			// Position
			print '<td>'.((int) $obj->position).'</td>';

			// Label
			print '<td><strong>'.dol_escape_htmltag($obj->label).'</strong></td>';

			// Content preview
			print '<td>'.dol_escape_htmltag(dol_trunc($obj->content, 60)).'</td>';

			// Context
			$ctxLabel = isset($contextOptions[$obj->context]) ? $contextOptions[$obj->context] : $obj->context;
			print '<td>'.dol_escape_htmltag($ctxLabel).'</td>';

			// Active toggle
			print '<td class="center">';
			if ($obj->active) {
				print '<a href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?action=toggleactive&token='.newToken().'&id='.$obj->rowid.'">';
				print img_picto($langs->trans("Enabled"), 'switch_on');
				print '</a>';
			} else {
				print '<a href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?action=toggleactive&token='.newToken().'&id='.$obj->rowid.'">';
				print img_picto($langs->trans("Disabled"), 'switch_off');
				print '</a>';
			}
			print '</td>';

			// Actions
			print '<td class="right nowraponall">';
			print '<a href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?action=edit&token='.newToken().'&id='.$obj->rowid.'">';
			print img_picto($langs->trans("Edit"), 'edit');
			print '</a>';
			print ' &nbsp; ';
			print '<a href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?action=confirm_delete&token='.newToken().'&id='.$obj->rowid.'" onclick="return confirm(\''.dol_escape_js($langs->trans("ConfirmDelete")).'\');">';
			print img_picto($langs->trans("Delete"), 'delete');
			print '</a>';
			print '</td>';

			print '</tr>';
			$i++;
		}
	} else {
		print '<tr class="oddeven"><td colspan="6" class="opacitymedium center">'.$langs->trans('NoTemplates').'</td></tr>';
	}

	print '</table>';
	$db->free($resql);
}

print dol_get_fiche_end();

// JS for character counter
print '<script type="text/javascript">
$(document).ready(function() {
	$("#tpl_content").on("input", function() {
		var len = $(this).val().length;
		var counter = $("#tpl_counter");
		counter.text(len + " / 1000");
		counter.css("color", len > 900 ? "#ef4444" : "#9ca3af");
	});
});
</script>';

llxFooter();
$db->close();
