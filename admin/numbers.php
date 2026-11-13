<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    admin/numbers.php
 * \ingroup dolicraftallo
 * \brief   Manage Allo phone numbers
 */

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
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');
dol_include_once('/dolicraftallo/class/allo_api.class.php');

$langs->loadLangs(array('admin', 'dolicraftallo@dolicraftallo'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

if ($action == 'add') {
	$number = GETPOST('allo_number', 'alphanohtml');
	$numberManual = GETPOST('allo_number_manual', 'alphanohtml');
	if (!empty($numberManual)) {
		$number = $numberManual;
	}
	$label = GETPOST('label', 'alphanohtml');
	$senderId = GETPOST('sender_id', 'alphanohtml');
	$fkUser = GETPOSTINT('fk_user');
	$isDefault = GETPOSTINT('is_default');

	// Clean number
	$number = preg_replace('/[^0-9+]/', '', $number);

	if (empty($number)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("PhoneNumber")), null, 'errors');
	} else {
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolicraftallo_numbers";
		$sql .= " (entity, allo_number, label, sender_id, fk_user, is_default, active, datec)";
		$sql .= " VALUES (".((int) $conf->entity).", '".$db->escape($number)."', '".$db->escape($label)."'";
		$sql .= ", '".$db->escape($senderId)."', ".((int) $fkUser).", ".((int) $isDefault).", 1, '".$db->idate(dol_now())."')";

		if ($isDefault) {
			// Reset other defaults
			$db->query("UPDATE ".MAIN_DB_PREFIX."dolicraftallo_numbers SET is_default = 0 WHERE entity = ".((int) $conf->entity));
		}

		$resql = $db->query($sql);
		if ($resql) {
			setEventMessages($langs->trans("NumberAdded"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error").' : '.$db->lasterror(), null, 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

if ($action == 'delete' && GETPOSTINT('id') > 0) {
	$id = GETPOSTINT('id');
	$sql = "DELETE FROM ".MAIN_DB_PREFIX."dolicraftallo_numbers WHERE rowid = ".((int) $id)." AND entity = ".((int) $conf->entity);
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('DolicraftAllo: SQL error on delete: '.$db->lasterror(), LOG_ERR);
		setEventMessages($langs->trans("Error").' : '.$db->lasterror(), null, 'errors');
	} else {
		setEventMessages($langs->trans("NumberDeleted"), null, 'mesgs');
	}
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

if ($action == 'setdefault' && GETPOSTINT('id') > 0) {
	$id = GETPOSTINT('id');
	$resql = $db->query("UPDATE ".MAIN_DB_PREFIX."dolicraftallo_numbers SET is_default = 0 WHERE entity = ".((int) $conf->entity));
	if (!$resql) {
		dol_syslog('DolicraftAllo: SQL error on setdefault reset: '.$db->lasterror(), LOG_ERR);
	}
	$resql = $db->query("UPDATE ".MAIN_DB_PREFIX."dolicraftallo_numbers SET is_default = 1 WHERE rowid = ".((int) $id)." AND entity = ".((int) $conf->entity));
	if (!$resql) {
		dol_syslog('DolicraftAllo: SQL error on setdefault: '.$db->lasterror(), LOG_ERR);
	}
	setEventMessages($langs->trans("DefaultNumberSet"), null, 'mesgs');
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

if ($action == 'toggleactive' && GETPOSTINT('id') > 0) {
	$id = GETPOSTINT('id');
	$resql = $db->query("UPDATE ".MAIN_DB_PREFIX."dolicraftallo_numbers SET active = IF(active=1, 0, 1) WHERE rowid = ".((int) $id)." AND entity = ".((int) $conf->entity));
	if (!$resql) {
		dol_syslog('DolicraftAllo: SQL error on toggleactive: '.$db->lasterror(), LOG_ERR);
	}
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans('AlloNumbers'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans('AlloNumbers'), $linkback, 'title_setup');

$head = dolicraftalloAdminPrepareHead();
print dol_get_fiche_head($head, 'numbers', $langs->trans('DolicraftAllo'), -1, 'dolicraftallo@dolicraftallo');

// ---- Fetch from API: available numbers ----
$apiNumbers = array();
$api = new DolicraftAlloAPI($db);
$apiResult = $api->getPhoneNumbers();
if ($apiResult !== false && isset($apiResult['data'])) {
	$apiNumbers = is_array($apiResult['data']) ? $apiResult['data'] : array();
}

// ---- List configured numbers ----
print load_fiche_titre($langs->trans('ConfiguredNumbers'), '', 'object_list');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("PhoneNumber").'</td>';
print '<td>'.$langs->trans("Label").'</td>';
print '<td>'.$langs->trans("SenderID").'</td>';
print '<td>'.$langs->trans("AssignedUser").'</td>';
print '<td class="center">'.$langs->trans("Default").'</td>';
print '<td class="center">'.$langs->trans("Active").'</td>';
print '<td class="center">'.$langs->trans("Actions").'</td>';
print '</tr>';

$sql = "SELECT n.rowid, n.allo_number, n.label, n.sender_id, n.fk_user, n.is_default, n.active, n.datec";
$sql .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_numbers as n";
$sql .= " WHERE n.entity = ".((int) $conf->entity);
$sql .= " ORDER BY n.is_default DESC, n.label ASC";

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	if ($num > 0) {
		while ($obj = $db->fetch_object($resql)) {
			print '<tr class="oddeven">';
			print '<td><strong>'.dol_escape_htmltag($obj->allo_number).'</strong></td>';
			print '<td>'.dol_escape_htmltag($obj->label).'</td>';
			print '<td>'.($obj->sender_id ? dol_escape_htmltag($obj->sender_id) : '<span class="opacitymedium">-</span>').'</td>';

			// User
			print '<td>';
			if ($obj->fk_user > 0) {
				$userStatic = new User($db);
				$userStatic->fetch($obj->fk_user);
				print $userStatic->getNomUrl(1);
			} else {
				print '<span class="opacitymedium">'.$langs->trans("AllUsers").'</span>';
			}
			print '</td>';

			// Default
			print '<td class="center">';
			if ($obj->is_default) {
				print '<span class="badge badge-status4">'.$langs->trans("Yes").'</span>';
			} else {
				print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdefault&id='.$obj->rowid.'&token='.newToken().'" class="opacitymedium">'.$langs->trans("SetAsDefault").'</a>';
			}
			print '</td>';

			// Active
			print '<td class="center">';
			$activeLabel = $obj->active ? '<span class="badge badge-status4">'.$langs->trans("Enabled").'</span>' : '<span class="badge badge-status8">'.$langs->trans("Disabled").'</span>';
			print '<a href="'.$_SERVER["PHP_SELF"].'?action=toggleactive&id='.$obj->rowid.'&token='.newToken().'">'.$activeLabel.'</a>';
			print '</td>';

			// Actions
			print '<td class="center">';
			print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete&id='.$obj->rowid.'&token='.newToken().'" onclick="return confirm(\''.$langs->trans("ConfirmDelete").'\');">';
			print '<span class="fas fa-trash-alt" style="color:#ef4444;" title="'.$langs->trans("Delete").'"></span>';
			print '</a>';
			print '</td>';

			print '</tr>';
		}
	} else {
		print '<tr class="oddeven"><td colspan="7" class="opacitymedium">'.$langs->trans("NoNumberConfigured").'</td></tr>';
	}
	$db->free($resql);
}

print '</table>';
print '</div>';

print '<br>';

// ---- Add number form ----
print load_fiche_titre($langs->trans('AddNumber'), '', 'object_add');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="add">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Phone number (with API suggestions or manual input)
print '<tr class="oddeven"><td class="titlefield fieldrequired">'.$langs->trans("PhoneNumber").'</td><td>';
if (!empty($apiNumbers)) {
	print '<select name="allo_number" id="allo_number_select" class="flat minwidth300" onchange="document.getElementById(\'allo_number_manual\').value=\'\';">';
	print '<option value="">-- '.$langs->trans("SelectNumber").' --</option>';
	foreach ($apiNumbers as $apiNum) {
		$num = is_array($apiNum) ? ($apiNum['number'] ?? '') : $apiNum;
		$numLabel = is_array($apiNum) && !empty($apiNum['name']) ? $apiNum['name'].' ('.$num.')' : $num;
		if ($num) {
			print '<option value="'.dol_escape_htmltag($num).'">'.dol_escape_htmltag($numLabel).'</option>';
		}
	}
	print '</select>';
	print '<br><span class="opacitymedium small">'.$langs->trans("Or").'</span><br>';
	print '<input type="text" name="allo_number_manual" id="allo_number_manual" class="flat minwidth200" placeholder="+33612345678" onchange="document.getElementById(\'allo_number_select\').value=\'\';">';
} else {
	print '<input type="text" name="allo_number" class="flat minwidth200" placeholder="+33612345678" value="'.GETPOST('allo_number', 'alphanohtml').'">';
}
print '</td></tr>';

// Label
print '<tr class="oddeven"><td>'.$langs->trans("Label").'</td><td>';
print '<input type="text" name="label" class="flat minwidth200" placeholder="'.$langs->trans("LabelExample").'" value="'.GETPOST('label', 'alphanohtml').'">';
print '</td></tr>';

// Sender ID (France)
print '<tr class="oddeven"><td>'.$langs->trans("SenderID").'<br><span class="opacitymedium small">'.$langs->trans("SenderIDHelp").'</span></td><td>';
print '<input type="text" name="sender_id" class="flat minwidth150" maxlength="11" placeholder="Dolicraft" value="'.GETPOST('sender_id', 'alphanohtml').'">';
print '</td></tr>';

// Assigned user
print '<tr class="oddeven"><td>'.$langs->trans("AssignedUser").'<br><span class="opacitymedium small">'.$langs->trans("AssignedUserHelp").'</span></td><td>';
print $form->select_dolusers(GETPOSTINT('fk_user'), 'fk_user', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'flat minwidth200');
print '</td></tr>';

// Default
print '<tr class="oddeven"><td>'.$langs->trans("SetAsDefault").'</td><td>';
print '<input type="checkbox" name="is_default" value="1">';
print '</td></tr>';

print '</table>';

print '<br>';
print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Add").'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
