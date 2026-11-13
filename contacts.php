<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    contacts.php
 * \ingroup dolicraftallo
 * \brief   Contact synchronization page
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');

// Load translation files
$langs->loadLangs(array("dolicraftallo@dolicraftallo"));

// Security check
if (!$user->hasRight('dolicraftallo', 'sync')) {
	accessforbidden();
}

// Parameters
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');

if (empty($sortfield)) {
	$sortfield = 'cm.last_sync';
}
if (empty($sortorder)) {
	$sortorder = 'DESC';
}

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;

//
// View
//
$form = new Form($db);

llxHeader('', $langs->trans("Contacts"), '', '', 0, 0, '', '', '', 'mod-dolicraftallo page-contacts');

print load_fiche_titre($langs->trans("DolicraftAllo"), '', 'phone');

$head = dolicraftalloAdminPrepareHead();
print dol_get_fiche_head($head, 'contacts', $langs->trans("DolicraftAllo"), -1, 'phone');

// --- Stats ---
$sqlTotalMapped = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."dolicraftallo_contact_map WHERE entity = ".((int) $conf->entity);
$sqlTotalLinked = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."dolicraftallo_contact_map WHERE entity = ".((int) $conf->entity)." AND (fk_soc > 0 OR fk_socpeople > 0)";
$sqlTotalDolibarr = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."socpeople WHERE entity IN (".getEntity('socpeople').")";

$totalAllo = 0;
$totalLinked = 0;
$totalDolibarr = 0;

$resqltm = $db->query($sqlTotalMapped);
if ($resqltm) {
	$objtm = $db->fetch_object($resqltm);
	$totalAllo = $objtm->total;
}
$resqltl = $db->query($sqlTotalLinked);
if ($resqltl) {
	$objtl = $db->fetch_object($resqltl);
	$totalLinked = $objtl->total;
}
$resqltd = $db->query($sqlTotalDolibarr);
if ($resqltd) {
	$objtd = $db->fetch_object($resqltd);
	$totalDolibarr = $objtd->total;
}

print '<div class="fichecenter" style="margin-bottom: 15px;">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-chart-bar pictofixedwidth"></i>'.$langs->trans('Statistics').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('TotalAlloContacts').'</td><td><span class="badge badge-status1">'.$totalAllo.'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('TotalMappedToDolibarr').'</td><td><span class="badge badge-status4">'.$totalLinked.'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('TotalDolibarrContacts').'</td><td><span class="badge badge-status0">'.$totalDolibarr.'</span></td></tr>';
print '</table>';
print '</div>';

// Sync button
print '<div class="tabsAction" style="margin-bottom: 10px;">';
print '<button type="button" class="button" id="btn_sync_contacts">';
print '<i class="fas fa-sync pictofixedwidth"></i>'.$langs->trans("SyncContacts");
print '</button>';
print '<span id="sync_contacts_result" style="margin-left: 10px;"></span>';
print '</div>';

// Check if allo_name column exists
$hasAlloNameCol = false;
$sqlColCheck = "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS";
$sqlColCheck .= " WHERE TABLE_SCHEMA = '".$db->escape($db->database_name)."'";
$sqlColCheck .= " AND TABLE_NAME = '".MAIN_DB_PREFIX."dolicraftallo_contact_map'";
$sqlColCheck .= " AND COLUMN_NAME = 'allo_name'";
$resColCheck = $db->query($sqlColCheck);
if ($resColCheck) {
	$objCol = $db->fetch_object($resColCheck);
	$hasAlloNameCol = ((int) $objCol->cnt > 0);
}

// --- Mapping table ---
$sql = "SELECT cm.rowid, cm.allo_contact_id, cm.phone_number, cm.last_sync, cm.datec,";
$sql .= " cm.fk_soc, cm.fk_socpeople,";
if ($hasAlloNameCol) {
	$sql .= " cm.allo_name,";
}
$sql .= " s.nom as soc_name,";
$sql .= " sp.lastname as contact_lastname, sp.firstname as contact_firstname";
$sql .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_contact_map as cm";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = cm.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON sp.rowid = cm.fk_socpeople";
$sql .= " WHERE cm.entity = ".((int) $conf->entity);

// Count total
$sqlcount = preg_replace('/^SELECT.*FROM /s', 'SELECT COUNT(*) as total FROM ', $sql);
$resqlcount = $db->query($sqlcount);
$nbtotalofrecords = 0;
if ($resqlcount) {
	$objcount = $db->fetch_object($resqlcount);
	$nbtotalofrecords = $objcount->total;
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

$param = '';

print_barre_liste($langs->trans("Contacts").' Allo', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, '', 0, '', '', $limit);

print '<table class="noborder centpercent">';

// Header row
print '<tr class="liste_titre">';
print '<td class="liste_titre">'.$langs->trans('NamePhone').'</td>';
print_liste_field_titre("PhoneNumber", $_SERVER["PHP_SELF"], "cm.phone_number", "", $param, '', $sortfield, $sortorder);
print '<td class="liste_titre">'.$langs->trans('ThirdParty').'</td>';
print '<td class="liste_titre">'.$langs->trans('Contact').'</td>';
print_liste_field_titre("LastSync", $_SERVER["PHP_SELF"], "cm.last_sync", "", $param, '', $sortfield, $sortorder);
print '</tr>';

// Load classes for getNomUrl
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

// Data rows
$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);

	print '<tr class="oddeven">';

	// Contact name / phone - show readable name instead of raw ID
	print '<td>';
	$contactDisplayName = '';
	if ($hasAlloNameCol && !empty($obj->allo_name)) {
		$contactDisplayName = $obj->allo_name;
	}
	if (empty($contactDisplayName) && !empty($obj->contact_lastname)) {
		$contactDisplayName = trim($obj->contact_firstname.' '.$obj->contact_lastname);
	}
	if (!empty($contactDisplayName)) {
		print '<strong>'.dol_escape_htmltag($contactDisplayName).'</strong>';
	} else {
		// Fallback: show phone as name
		print '<strong>'.dol_escape_htmltag(!empty($obj->phone_number) ? $obj->phone_number : $obj->allo_contact_id).'</strong>';
	}
	print '</td>';

	// Phone number (prominent display)
	print '<td>';
	if (!empty($obj->phone_number)) {
		print '<a href="tel:'.dol_escape_htmltag($obj->phone_number).'" title="'.dol_escape_htmltag($langs->trans('Call')).'">';
		print '<i class="fas fa-phone pictofixedwidth"></i>'.dol_escape_htmltag($obj->phone_number);
		print '</a>';
	} else {
		print '<span class="opacitymedium">-</span>';
	}
	print '</td>';

	// Thirdparty link - use getNomUrl for proper Dolibarr link
	print '<td>';
	if ($obj->fk_soc > 0) {
		$soc = new Societe($db);
		if ($soc->fetch($obj->fk_soc) > 0) {
			print $soc->getNomUrl(1);
		} else {
			print '<span class="opacitymedium">-</span>';
		}
	} else {
		print '<span class="opacitymedium">-</span>';
	}
	print '</td>';

	// Contact link - use getNomUrl for proper Dolibarr link
	print '<td>';
	if ($obj->fk_socpeople > 0) {
		$contactObj = new Contact($db);
		if ($contactObj->fetch($obj->fk_socpeople) > 0) {
			print $contactObj->getNomUrl(1);
		} else {
			print '<span class="opacitymedium">-</span>';
		}
	} else {
		print '<span class="opacitymedium">-</span>';
	}
	print '</td>';

	// Last sync
	print '<td class="nowraponall">';
	if (!empty($obj->last_sync)) {
		print dol_print_date($db->jdate($obj->last_sync), 'dayhour');
	} else {
		print '<span class="opacitymedium">'.$langs->trans('Never').'</span>';
	}
	print '</td>';

	print '</tr>';
	$i++;
}

if ($num == 0) {
	print '<tr class="oddeven"><td colspan="5" class="opacitymedium center">'.$langs->trans('NoContactSynced').'</td></tr>';
}

print '</table>';

print dol_get_fiche_end();

// JavaScript for sync contacts
print '<script type="text/javascript">
$(document).ready(function() {
	$("#btn_sync_contacts").click(function() {
		var $btn = $(this);
		var $result = $("#sync_contacts_result");
		$btn.prop("disabled", true);
		$result.html("<i class=\"fas fa-spinner fa-spin\"></i> '.dol_escape_js($langs->trans('SyncInProgress')).'");

		$.ajax({
			url: "'.dol_buildpath('/dolicraftallo/ajax/allo.php', 1).'",
			type: "POST",
			data: {
				action: "sync_contacts",
				token: "'.newToken().'"
			},
			dataType: "json",
			success: function(response) {
				if (response.success) {
					var html = "<span class=\"badge badge-status4\">" + response.message + "</span>";
					if (response.stats) {
						html += "<br><small>Allo: " + (response.stats.total_allo || 0);
						html += " | Mappes: " + (response.stats.total_mapped || 0);
						html += " | Nouveaux: " + (response.stats.new_mapped || 0) + "</small>";
					}
					$result.html(html);
					setTimeout(function() { location.reload(); }, 2000);
				} else {
					$result.html("<span class=\"badge badge-status8\">Erreur : " + (response.message || "Erreur inconnue") + "</span>");
				}
			},
			error: function() {
				$result.html("<span class=\"badge badge-status8\">Erreur de communication</span>");
			},
			complete: function() {
				$btn.prop("disabled", false);
			}
		});
	});
});
</script>';

llxFooter();
$db->close();
