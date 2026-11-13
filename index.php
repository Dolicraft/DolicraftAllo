<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    index.php
 * \ingroup dolicraftallo
 * \brief   Call history page (main page)
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');

// Load translation files
$langs->loadLangs(array("dolicraftallo@dolicraftallo"));

// Security check
if (!$user->hasRight('dolicraftallo', 'view')) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');
$search_phone = GETPOST('search_phone', 'alphanohtml');
$search_type = GETPOST('search_type', 'aZ09');
$search_date_start = dol_mktime(0, 0, 0, GETPOSTINT('search_date_startmonth'), GETPOSTINT('search_date_startday'), GETPOSTINT('search_date_startyear'));
$search_date_end = dol_mktime(23, 59, 59, GETPOSTINT('search_date_endmonth'), GETPOSTINT('search_date_endday'), GETPOSTINT('search_date_endyear'));
$search_allo_number = GETPOST('search_allo_number', 'alphanohtml');
$search_transcript = GETPOST('search_transcript', 'alphanohtml');

if (empty($sortfield)) {
	$sortfield = 'c.call_date';
}
if (empty($sortorder)) {
	$sortorder = 'DESC';
}

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;

// Reset filters
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_phone = '';
	$search_type = '';
	$search_date_start = '';
	$search_date_end = '';
	$search_allo_number = '';
	$search_transcript = '';
}

//
// View
//
$form = new Form($db);

llxHeader('', $langs->trans("JournalAppels"), '', '', 0, 0, '', '', '', 'mod-dolicraftallo page-index');

print load_fiche_titre($langs->trans("DolicraftAllo"), '', 'phone');

$head = dolicraftalloAdminPrepareHead();
print dol_get_fiche_head($head, 'calls', $langs->trans("DolicraftAllo"), -1, 'phone');

// Sync button
print '<div class="tabsAction" style="margin-bottom: 10px;">';
print '<button type="button" class="button" id="btn_sync_calls">';
print '<i class="fas fa-sync pictofixedwidth"></i>'.$langs->trans('SyncCalls');
print '</button>';
print '<span id="sync_result" style="margin-left: 10px;"></span>';
print '</div>';

// Build SQL query
$sql = "SELECT c.rowid, c.allo_call_id, c.from_number, c.to_number, c.call_type, c.call_result,";
$sql .= " c.duration_minutes, c.summary, c.recording_url, c.transcript, c.tag, c.call_date,";
$sql .= " c.fk_soc, c.fk_socpeople,";
$sql .= " s.nom as soc_name, sp.lastname as contact_lastname, sp.firstname as contact_firstname";
$sql .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log as c";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = c.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON sp.rowid = c.fk_socpeople";
$sql .= " WHERE c.entity = ".((int) $conf->entity);

if (!empty($search_phone)) {
	$sql .= natural_search(array('c.from_number', 'c.to_number'), $search_phone);
}
if (!empty($search_type)) {
	$sql .= " AND c.call_type = '".$db->escape($search_type)."'";
}
if (!empty($search_date_start)) {
	$sql .= " AND c.call_date >= '".$db->idate($search_date_start)."'";
}
if (!empty($search_date_end)) {
	$sql .= " AND c.call_date <= '".$db->idate($search_date_end)."'";
}
if (!empty($search_allo_number)) {
	$sql .= " AND (c.from_number = '".$db->escape($search_allo_number)."' OR c.to_number = '".$db->escape($search_allo_number)."')";
}
if (!empty($search_transcript)) {
	$sql .= natural_search('c.transcript', $search_transcript);
}

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

// List header with pagination
$param = '';
if (!empty($search_phone)) {
	$param .= '&search_phone='.urlencode($search_phone);
}
if (!empty($search_type)) {
	$param .= '&search_type='.urlencode($search_type);
}
if (!empty($search_allo_number)) {
	$param .= '&search_allo_number='.urlencode($search_allo_number);
}
if (!empty($search_transcript)) {
	$param .= '&search_transcript='.urlencode($search_transcript);
}

// Load Allo numbers for filter dropdown
$alloNumbers = dolicraftalloGetNumbers($db);

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';

print_barre_liste($langs->trans("JournalAppels"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, '', 0, '', '', $limit);

print '<table class="noborder centpercent">';

// Header row
print '<tr class="liste_titre">';
print_liste_field_titre("CallDate", $_SERVER["PHP_SELF"], "c.call_date", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("FromNumber", $_SERVER["PHP_SELF"], "c.from_number", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("ToNumber", $_SERVER["PHP_SELF"], "c.to_number", "", $param, '', $sortfield, $sortorder);
print '<td class="liste_titre">'.$langs->trans("CallType").'</td>';
print '<td class="liste_titre">'.$langs->trans("CallResult").'</td>';
print_liste_field_titre("Duration", $_SERVER["PHP_SELF"], "c.duration_minutes", "", $param, '', $sortfield, $sortorder, 'right ');
print '<td class="liste_titre">'.$langs->trans("Summary").'</td>';
print '<td class="liste_titre">'.$langs->trans("Transcript").'</td>';
print '<td class="liste_titre">'.$langs->trans("Tag").'</td>';
print '<td class="liste_titre">'.$langs->trans('AlloNumber').'</td>';
print '</tr>';

// Filter row
print '<tr class="liste_titre">';
// Date filter
print '<td class="liste_titre">';
print $form->selectDate($search_date_start, 'search_date_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans("From"));
print '<br>';
print $form->selectDate($search_date_end, 'search_date_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans("To"));
print '</td>';
// Phone filter
print '<td class="liste_titre" colspan="2">';
print '<input type="text" class="flat maxwidth150" name="search_phone" value="'.dol_escape_htmltag($search_phone).'" placeholder="'.$langs->trans('PhoneNumber').'...">';
print '</td>';
// Type filter
print '<td class="liste_titre">';
print '<select name="search_type" class="flat">';
print '<option value="">&nbsp;</option>';
print '<option value="INBOUND"'.($search_type == 'INBOUND' ? ' selected' : '').'>'.$langs->trans("INBOUND").'</option>';
print '<option value="OUTBOUND"'.($search_type == 'OUTBOUND' ? ' selected' : '').'>'.$langs->trans("OUTBOUND").'</option>';
print '<option value="MISSED"'.($search_type == 'MISSED' ? ' selected' : '').'>'.$langs->trans('MISSED').'</option>';
print '</select>';
print '</td>';
// Empty cells for result, duration, summary
print '<td class="liste_titre"></td>';
print '<td class="liste_titre right"></td>';
print '<td class="liste_titre"></td>';
// Transcript filter
print '<td class="liste_titre">';
print '<input type="text" class="flat maxwidth150" name="search_transcript" value="'.dol_escape_htmltag($search_transcript).'" placeholder="'.$langs->trans('SearchTranscript').'...">';
print '</td>';
// Tag empty cell
print '<td class="liste_titre"></td>';
// Numero Allo filter dropdown + search buttons
print '<td class="liste_titre right">';
print '<select name="search_allo_number" class="flat maxwidth150" style="margin-bottom:4px;">';
print '<option value="">&nbsp;</option>';
foreach ($alloNumbers as $numObj) {
	$selected = ($search_allo_number == $numObj->allo_number) ? ' selected' : '';
	$numLabel = dol_escape_htmltag($numObj->label.' ('.$numObj->allo_number.')');
	print '<option value="'.dol_escape_htmltag($numObj->allo_number).'"'.$selected.'>'.$numLabel.'</option>';
}
print '</select>';
print '<br>';
print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto('', 'search.png', '', 0, 1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto('', 'searchclear.png', '', 0, 1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
print '</td>';
print '</tr>';

// Data rows
$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);

	print '<tr class="oddeven">';

	// Date
	print '<td class="nowraponall">'.dol_print_date($db->jdate($obj->call_date), 'dayhour').'</td>';

	// From
	print '<td>'.dol_escape_htmltag($obj->from_number);
	if ($obj->fk_soc > 0 && !empty($obj->soc_name)) {
		print '<br><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$obj->fk_soc.'"><small>'.dol_escape_htmltag($obj->soc_name).'</small></a>';
	}
	print '</td>';

	// To
	print '<td>'.dol_escape_htmltag($obj->to_number);
	if ($obj->fk_socpeople > 0 && !empty($obj->contact_lastname)) {
		print '<br><a href="'.DOL_URL_ROOT.'/contact/card.php?id='.$obj->fk_socpeople.'"><small>'.dol_escape_htmltag(trim($obj->contact_firstname.' '.$obj->contact_lastname)).'</small></a>';
	}
	print '</td>';

	// Type badge
	print '<td>';
	if ($obj->call_type == 'INBOUND') {
		print '<span class="badge badge-status4"><i class="fas fa-phone-alt pictofixedwidth"></i>'.$langs->trans("INBOUND").'</span>';
	} elseif ($obj->call_type == 'OUTBOUND') {
		print '<span class="badge badge-status1"><i class="fas fa-phone-volume pictofixedwidth"></i>'.$langs->trans("OUTBOUND").'</span>';
	} else {
		print '<span class="badge badge-status8"><i class="fas fa-phone-slash pictofixedwidth"></i>'.$langs->trans('MISSED').'</span>';
	}
	print '</td>';

	// Result badge
	print '<td>';
	if ($obj->call_result == 'ANSWERED') {
		print '<span class="badge badge-status4">'.$langs->trans("ANSWERED").'</span>';
	} elseif ($obj->call_result == 'NOANSWER') {
		print '<span class="badge badge-status8">'.$langs->trans("NOANSWER").'</span>';
	} elseif ($obj->call_result == 'BUSY') {
		print '<span class="badge badge-status1">'.$langs->trans("BUSY").'</span>';
	} elseif ($obj->call_result == 'VOICEMAIL') {
		print '<span class="badge badge-status6">'.$langs->trans("VOICEMAIL").'</span>';
	} else {
		print '<span class="badge badge-status0">'.dol_escape_htmltag($obj->call_result).'</span>';
	}
	print '</td>';

	// Duration
	print '<td class="right">';
	if ($obj->duration_minutes > 0) {
		$min = floor($obj->duration_minutes);
		$sec = round(($obj->duration_minutes - $min) * 60);
		print $min.'m'.sprintf('%02d', $sec).'s';
	} else {
		print '-';
	}
	print '</td>';

	// Summary
	print '<td>';
	if (!empty($obj->summary)) {
		print dol_trunc(dol_escape_htmltag($obj->summary), 80);
	}
	if (!empty($obj->recording_url)) {
		print ' <a href="'.dol_escape_htmltag($obj->recording_url).'" target="_blank" rel="noopener noreferrer" title="'.dol_escape_htmltag($langs->trans('ListenRecording')).'"><i class="fas fa-play-circle"></i></a>';
	}
	print '</td>';

	// Transcript
	print '<td>';
	if (!empty($obj->transcript)) {
		print '<span class="fas fa-file-alt" style="cursor:pointer;color:#6b7280;" onclick="toggleTranscript('.$obj->rowid.')" title="'.dol_escape_htmltag($langs->trans('ShowTranscript')).'"></span>';
	}
	print '</td>';

	// Tag
	print '<td>';
	if (!empty($obj->tag)) {
		print '<span class="badge badge-status0">'.dol_escape_htmltag($obj->tag).'</span>';
	}
	print '</td>';

	// Numero Allo (show which configured number was involved)
	print '<td class="nowraponall">';
	// Determine which Allo number is involved (from or to)
	$matchedAlloNum = '';
	foreach ($alloNumbers as $numObj) {
		if ($obj->from_number == $numObj->allo_number || $obj->to_number == $numObj->allo_number) {
			$matchedAlloNum = $numObj->label;
			break;
		}
	}
	if (!empty($matchedAlloNum)) {
		print '<span class="badge badge-status1">'.dol_escape_htmltag($matchedAlloNum).'</span>';
	}
	print '</td>';

	print '</tr>';

	// Hidden transcript row
	if (!empty($obj->transcript)) {
		print '<tr id="transcript_row_'.$obj->rowid.'" style="display:none;">';
		print '<td colspan="11" style="padding:8px 16px;background:#f9fafb;">';
		print '<div style="max-height:200px;overflow-y:auto;white-space:pre-wrap;font-family:monospace;font-size:0.85em;color:#374151;line-height:1.6;">';
		print dol_escape_htmltag($obj->transcript);
		print '</div>';
		print '</td>';
		print '</tr>';
	}

	$i++;
}

if ($num == 0) {
	print '<tr class="oddeven"><td colspan="10" class="opacitymedium center">'.$langs->trans('NoCallsRecorded').'</td></tr>';
}

print '</table>';
print '</form>';

print dol_get_fiche_end();

// JavaScript for transcript toggle and sync calls
print '<script type="text/javascript">
function toggleTranscript(id) {
	var row = document.getElementById("transcript_row_" + id);
	if (row) {
		row.style.display = (row.style.display === "none" || row.style.display === "") ? "table-row" : "none";
	}
}
$(document).ready(function() {
	$("#btn_sync_calls").click(function() {
		var $btn = $(this);
		var $result = $("#sync_result");
		$btn.prop("disabled", true);
		$result.html("<i class=\"fas fa-spinner fa-spin\"></i> '.dol_escape_js($langs->trans('SyncInProgress')).'");

		$.ajax({
			url: "'.dol_buildpath('/dolicraftallo/ajax/allo.php', 1).'",
			type: "POST",
			data: {
				action: "sync_calls",
				token: "'.newToken().'"
			},
			dataType: "json",
			success: function(response) {
				if (response.success) {
					$result.html("<span class=\"badge badge-status4\">" + response.message + "</span>");
					setTimeout(function() { location.reload(); }, 1500);
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
