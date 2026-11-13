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
 * \file    sms.php
 * \ingroup dolicraftallo
 * \brief   Contextual SMS sending and history page
 *
 * Scenarios:
 * - Direct access: /sms.php
 * - From thirdparty: /sms.php?action=presend&to=+33612345678&object_type=societe&object_id=123
 * - From contact:    /sms.php?action=presend&to=+33612345678&object_type=contact&object_id=456
 * - From invoice:    /sms.php?action=presend&to=+33612345678&object_type=facture&object_id=789
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
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');

// Load translation files
$langs->loadLangs(array("dolicraftallo@dolicraftallo", "bills", "companies"));

// Security check
if (!$user->hasRight('dolicraftallo', 'send_sms')) {
	accessforbidden();
}

// ---------------------------------------------------------------------------
// GET parameters
// ---------------------------------------------------------------------------
$action      = GETPOST('action', 'aZ09');
$toParam     = GETPOST('to', 'alphanohtml');
$objectType  = GETPOST('object_type', 'aZ09');
$objectId    = GETPOSTINT('object_id');

$sortfield   = GETPOST('sortfield', 'aZ09comma');
$sortorder   = GETPOST('sortorder', 'aZ09comma');
$page        = GETPOSTINT('page');

if (empty($sortfield)) {
	$sortfield = 'sl.datec';
}
if (empty($sortorder)) {
	$sortorder = 'DESC';
}

$limit  = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;

// ---------------------------------------------------------------------------
// Load contextual object (societe / contact / facture)
// ---------------------------------------------------------------------------
$contextLabel = '';
$contextUrl   = '';
$templateSuggestion = '';
$presend = ($action == 'presend' && !empty($toParam));

if (!empty($objectType) && $objectId > 0) {
	if ($objectType == 'societe') {
		$soc = new Societe($db);
		if ($soc->fetch($objectId) > 0) {
			$contextLabel = $soc->name;
			$contextUrl   = $soc->getNomUrl(1);
			$templateSuggestion = 'Bonjour, message de la part de '.$mysoc->name.' concernant votre dossier.';
		}
	} elseif ($objectType == 'contact') {
		$contact = new Contact($db);
		if ($contact->fetch($objectId) > 0) {
			$contextLabel = trim($contact->firstname.' '.$contact->lastname);
			$contextUrl   = $contact->getNomUrl(1);
		}
	} elseif ($objectType == 'facture') {
		$facture = new Facture($db);
		if ($facture->fetch($objectId) > 0) {
			$contextLabel = $facture->ref;
			$contextUrl   = $facture->getNomUrl(1);
			// Build invoice template
			$amountTTC     = price($facture->total_ttc, 0, $langs, 1, -1, 2);
			$dateLimReglement = dol_print_date($facture->date_lim_reglement, 'day');
			$templateSuggestion = 'Bonjour, votre facture '.$facture->ref.' d\'un montant de '.$amountTTC.' EUR arrive a echeance le '.$dateLimReglement.'. Merci de proceder au reglement.';
			// If no "to" param, try to get thirdparty phone
			if (empty($toParam) && $facture->socid > 0) {
				$socFact = new Societe($db);
				if ($socFact->fetch($facture->socid) > 0) {
					if (!empty($socFact->phone)) {
						$toParam = $socFact->phone;
					}
				}
			}
		}
	}
}

// ---------------------------------------------------------------------------
// Load "From" numbers
// ---------------------------------------------------------------------------
$alloNumbers = dolicraftalloGetNumbers($db, $user->id);

// ---------------------------------------------------------------------------
// View
// ---------------------------------------------------------------------------
$form = new Form($db);

$morejs = array();
$morecss = array();

llxHeader('', $langs->trans("SMS"), '', '', 0, 0, $morejs, $morecss, '', 'mod-dolicraftallo page-sms');

print load_fiche_titre($langs->trans("DolicraftAllo"), '', 'phone');

$head = dolicraftalloAdminPrepareHead();
print dol_get_fiche_head($head, 'sms', $langs->trans("DolicraftAllo"), -1, 'phone');

// ===================================================================
// SECTION 1: SEND SMS FORM
// ===================================================================
print '<div class="fichecenter">';

// Context banner when presend
if ($presend && !empty($contextUrl)) {
	print '<div class="info" style="margin-bottom: 12px;">';
	print '<i class="fas fa-link pictofixedwidth"></i>';
	print $langs->trans('SendingSMSInContext').' : '.$contextUrl;
	print '</div>';
}

print '<form id="sms_form">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">';
print '<i class="fas fa-paper-plane pictofixedwidth"></i>'.$langs->trans("SendSMS");
print '</td></tr>';

// --- FROM number ---
print '<tr class="oddeven">';
print '<td class="titlefield fieldrequired">'.$langs->trans("From").'</td>';
print '<td>';
if (!empty($alloNumbers)) {
	print '<select name="from_number" id="sms_from" class="flat minwidth300">';
	foreach ($alloNumbers as $numObj) {
		$selected = (!empty($numObj->is_default)) ? ' selected' : '';
		$lbl = dol_escape_htmltag($numObj->label.' - '.$numObj->allo_number);
		print '<option value="'.dol_escape_htmltag($numObj->allo_number).'"'.$selected.'>'.$lbl.'</option>';
	}
	print '</select>';
} else {
	print '<span class="opacitymedium">'.$langs->trans("NoNumberConfigured").'</span>';
	print ' <a href="'.dol_buildpath('/dolicraftallo/admin/numbers.php', 1).'" class="button smallpaddingimp">';
	print '<i class="fas fa-cog pictofixedwidth"></i>'.$langs->trans('Configure').'</a>';
}
print '</td>';
print '</tr>';

// --- TO field ---
print '<tr class="oddeven">';
print '<td class="fieldrequired">'.$langs->trans("To").'</td>';
print '<td>';

if ($presend && !empty($toParam)) {
	// Pre-filled mode: read-only input + context badge
	print '<input type="text" id="sms_to" name="to" class="flat minwidth300" value="'.dol_escape_htmltag($toParam).'" readonly />';
	if (!empty($contextLabel)) {
		print ' <span class="badge badge-status4" style="font-size: 0.95em;">';
		print '<i class="fas fa-user pictofixedwidth"></i>'.dol_escape_htmltag($contextLabel);
		print '</span>';
	}
} else {
	// Manual mode: editable input + contact dropdown
	print '<input type="text" id="sms_to" name="to" class="flat minwidth300" placeholder="+33612345678" value="'.dol_escape_htmltag($toParam).'" />';
	print '<br><br>';
	print '<span class="opacitymedium small">'.$langs->trans("Or").' : </span>';
	print '<select id="sms_contact" class="flat minwidth300">';
	print '<option value="">-- '.$langs->trans("ChooseAContact").' --</option>';

	// Fetch contacts with phone numbers
	$sqlcontacts = "SELECT sp.rowid, sp.firstname, sp.lastname, sp.phone, sp.phone_mobile, s.nom as soc_name";
	$sqlcontacts .= " FROM ".MAIN_DB_PREFIX."socpeople as sp";
	$sqlcontacts .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = sp.fk_soc";
	$sqlcontacts .= " WHERE sp.entity IN (".getEntity('socpeople').")";
	$sqlcontacts .= " AND (sp.phone IS NOT NULL AND sp.phone != '' OR sp.phone_mobile IS NOT NULL AND sp.phone_mobile != '')";
	$sqlcontacts .= " ORDER BY sp.lastname ASC, sp.firstname ASC";
	$sqlcontacts .= $db->plimit(500);

	$resqlcontacts = $db->query($sqlcontacts);
	if ($resqlcontacts) {
		while ($objc = $db->fetch_object($resqlcontacts)) {
			$phone = !empty($objc->phone_mobile) ? $objc->phone_mobile : $objc->phone;
			$label = trim($objc->firstname.' '.$objc->lastname);
			if (!empty($objc->soc_name)) {
				$label .= ' ('.$objc->soc_name.')';
			}
			$label .= ' - '.$phone;
			print '<option value="'.dol_escape_htmltag($phone).'">'.dol_escape_htmltag($label).'</option>';
		}
		$db->free($resqlcontacts);
	}
	print '</select>';
}

print '</td>';
print '</tr>';

// --- Template suggestion ---
if (!empty($templateSuggestion)) {
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("Template").'</td>';
	print '<td>';
	print '<div class="info" style="margin: 0; padding: 8px 12px;">';
	print '<i class="fas fa-lightbulb pictofixedwidth" style="color: #f0ad4e;"></i>';
	print '<em id="sms_template_text">'.dol_escape_htmltag($templateSuggestion).'</em>';
	print '<br><a href="#" id="sms_use_template" class="button smallpaddingimp" style="margin-top: 6px;">';
	print '<i class="fas fa-copy pictofixedwidth"></i>'.$langs->trans('UseTemplate').'</a>';
	print '</div>';
	print '</td>';
	print '</tr>';
}

// --- Message textarea ---
print '<tr class="oddeven">';
print '<td class="fieldrequired tdtop">'.$langs->trans("Message").'</td>';
print '<td>';
print '<textarea id="sms_message" name="message" class="flat" rows="5" style="width: 100%; max-width: 600px;" maxlength="1000" placeholder="'.$langs->trans("YourMessage").'..."></textarea>';
print '<br><span class="opacitymedium small"><span id="sms_charcount">0</span> / 1000 '.$langs->trans("characters").'</span>';
print '</td>';
print '</tr>';

print '</table>';

// Hidden fields for context
print '<input type="hidden" id="sms_related_object_type" name="related_object_type" value="'.dol_escape_htmltag($objectType).'" />';
print '<input type="hidden" id="sms_related_object_id" name="related_object_id" value="'.((int) $objectId).'" />';

// Send button
print '<div class="center" style="margin-top: 12px; margin-bottom: 20px;">';
print '<button type="button" class="button" id="btn_send_sms">';
print '<i class="fas fa-paper-plane pictofixedwidth"></i>'.$langs->trans("SendSMS");
print '</button>';
print '<div id="sms_send_result" style="margin-top: 10px;"></div>';
print '</div>';

print '</form>';
print '</div>'; // fichecenter

// ===================================================================
// SECTION 2: SMS HISTORY
// ===================================================================
$sql = "SELECT sl.rowid, sl.from_number, sl.to_number, sl.sms_type, sl.content, sl.status,";
$sql .= " sl.related_object_type, sl.related_object_id, sl.datec,";
$sql .= " sl.fk_soc, sl.fk_socpeople,";
$sql .= " s.nom as soc_name, sp.lastname as contact_lastname, sp.firstname as contact_firstname";
$sql .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_log as sl";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = sl.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON sp.rowid = sl.fk_socpeople";
$sql .= " WHERE sl.entity = ".((int) $conf->entity);

// If viewing in context, optionally filter to this object only
if (!empty($objectType) && $objectId > 0) {
	$sql .= " AND sl.related_object_type = '".$db->escape($objectType)."'";
	$sql .= " AND sl.related_object_id = ".((int) $objectId);
}

// Count total
$sqlcount = preg_replace('/^SELECT.*FROM /s', 'SELECT COUNT(*) as total FROM ', $sql);
$resqlcount = $db->query($sqlcount);
$nbtotalofrecords = 0;
if ($resqlcount) {
	$objcount = $db->fetch_object($resqlcount);
	$nbtotalofrecords = (int) $objcount->total;
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

// Build URL params to keep context in pagination
$param = '';
if (!empty($objectType)) {
	$param .= '&object_type='.urlencode($objectType);
}
if ($objectId > 0) {
	$param .= '&object_id='.$objectId;
}
if (!empty($toParam)) {
	$param .= '&to='.urlencode($toParam);
}
if ($action == 'presend') {
	$param .= '&action=presend';
}

$titleHistory = $langs->trans("RecentSMS");
if (!empty($objectType) && $objectId > 0 && !empty($contextLabel)) {
	$titleHistory .= ' - '.dol_escape_htmltag($contextLabel);
}

print_barre_liste(
	$titleHistory,
	$page,
	$_SERVER["PHP_SELF"],
	$param,
	$sortfield,
	$sortorder,
	'',
	$num,
	$nbtotalofrecords,
	'',
	0,
	'',
	'',
	$limit
);

print '<table class="noborder centpercent">';

// Header row
print '<tr class="liste_titre">';
print_liste_field_titre("Date", $_SERVER["PHP_SELF"], "sl.datec", "", $param, '', $sortfield, $sortorder);
print '<td class="liste_titre">'.$langs->trans("From").'</td>';
print '<td class="liste_titre">'.$langs->trans("To").'</td>';
print '<td class="liste_titre center">'.$langs->trans("Direction").'</td>';
print '<td class="liste_titre">'.$langs->trans("Message").'</td>';
print '<td class="liste_titre center">'.$langs->trans("Status").'</td>';
print '<td class="liste_titre">'.$langs->trans("LinkedObject").'</td>';
print '</tr>';

// Data rows
$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);

	print '<tr class="oddeven">';

	// Date
	print '<td class="nowraponall">'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';

	// From
	print '<td class="tdoverflowmax150">'.dol_escape_htmltag($obj->from_number).'</td>';

	// To + contact name
	print '<td class="tdoverflowmax150">'.dol_escape_htmltag($obj->to_number);
	if ($obj->fk_socpeople > 0 && !empty($obj->contact_lastname)) {
		$contactLink = new Contact($db);
		$contactLink->id = $obj->fk_socpeople;
		$contactLink->lastname = $obj->contact_lastname;
		$contactLink->firstname = $obj->contact_firstname;
		print '<br>'.$contactLink->getNomUrl(1);
	}
	print '</td>';

	// Direction badge
	print '<td class="center nowraponall">';
	if ($obj->sms_type == 'OUTBOUND') {
		print '<span class="badge badge-status1"><i class="fas fa-arrow-up pictofixedwidth"></i>OUTBOUND</span>';
	} else {
		print '<span class="badge badge-status4"><i class="fas fa-arrow-down pictofixedwidth"></i>INBOUND</span>';
	}
	print '</td>';

	// Message (truncated)
	print '<td class="tdoverflowmax300" title="'.dol_escape_htmltag($obj->content).'">';
	print dol_trunc(dol_escape_htmltag($obj->content), 80);
	print '</td>';

	// Status badge
	print '<td class="center nowraponall">';
	switch ($obj->status) {
		case 'delivered':
			print '<span class="badge badge-status4">'.$langs->trans("Delivered").'</span>';
			break;
		case 'sent':
			print '<span class="badge badge-status6">'.$langs->trans("Sent").'</span>';
			break;
		case 'failed':
			print '<span class="badge badge-status8">'.$langs->trans("Failed").'</span>';
			break;
		case 'pending':
			print '<span class="badge badge-status1">'.$langs->trans("Pending").'</span>';
			break;
		case 'received':
			print '<span class="badge badge-status4">'.$langs->trans("Received").'</span>';
			break;
		default:
			print '<span class="badge badge-status0">'.dol_escape_htmltag($obj->status).'</span>';
			break;
	}
	print '</td>';

	// Related object
	print '<td class="nowraponall">';
	if (!empty($obj->related_object_type) && $obj->related_object_id > 0) {
		if ($obj->related_object_type == 'facture') {
			$tmpFact = new Facture($db);
			if ($tmpFact->fetch($obj->related_object_id) > 0) {
				print $tmpFact->getNomUrl(1);
			} else {
				print '<i class="fas fa-file-invoice-dollar pictofixedwidth"></i>Facture #'.$obj->related_object_id;
			}
		} elseif ($obj->related_object_type == 'societe') {
			$tmpSoc = new Societe($db);
			if ($tmpSoc->fetch($obj->related_object_id) > 0) {
				print $tmpSoc->getNomUrl(1);
			} else {
				print '<i class="fas fa-building pictofixedwidth"></i>'.dol_escape_htmltag($obj->soc_name);
			}
		} elseif ($obj->related_object_type == 'contact') {
			$tmpContact = new Contact($db);
			if ($tmpContact->fetch($obj->related_object_id) > 0) {
				print $tmpContact->getNomUrl(1);
			} else {
				print '<i class="fas fa-address-book pictofixedwidth"></i>Contact #'.$obj->related_object_id;
			}
		} else {
			print dol_escape_htmltag(ucfirst($obj->related_object_type)).' #'.$obj->related_object_id;
		}
	} elseif ($obj->fk_soc > 0 && !empty($obj->soc_name)) {
		$tmpSoc2 = new Societe($db);
		$tmpSoc2->id = $obj->fk_soc;
		$tmpSoc2->name = $obj->soc_name;
		print $tmpSoc2->getNomUrl(1);
	}
	print '</td>';

	print '</tr>';
	$i++;
}

if ($num == 0) {
	print '<tr class="oddeven"><td colspan="7" class="opacitymedium center">';
	if (!empty($objectType) && $objectId > 0) {
		print $langs->trans("NoSMSForThisObject");
	} else {
		print $langs->trans("NoSMSYet");
	}
	print '</td></tr>';
}

print '</table>';
$db->free($resql);

print dol_get_fiche_end();

// ===================================================================
// JavaScript
// ===================================================================
$ajaxUrl = dol_buildpath('/dolicraftallo/ajax/allo.php', 1);
$csrfToken = newToken();

print '<script type="text/javascript">
$(document).ready(function() {

	// Character counter
	$("#sms_message").on("input", function() {
		var len = $(this).val().length;
		$("#sms_charcount").text(len);
		if (len > 900) {
			$("#sms_charcount").css("color", "#bc0000");
		} else {
			$("#sms_charcount").css("color", "");
		}
	});

	// Contact dropdown fills phone field (direct access mode only)
	$("#sms_contact").change(function() {
		var phone = $(this).val();
		if (phone) {
			$("#sms_to").val(phone);
		}
	});

	// Use template button
	$("#sms_use_template").click(function(e) {
		e.preventDefault();
		var tpl = $("#sms_template_text").text();
		if (tpl) {
			$("#sms_message").val(tpl);
			$("#sms_charcount").text(tpl.length);
		}
	});

	// Send SMS via AJAX
	$("#btn_send_sms").click(function() {
		var $btn = $(this);
		var $result = $("#sms_send_result");

		var from_number = $("#sms_from").val() || "";
		var to = $("#sms_to").val() ? $("#sms_to").val().trim() : "";
		var message = $("#sms_message").val() ? $("#sms_message").val().trim() : "";
		var related_object_type = $("#sms_related_object_type").val() || "";
		var related_object_id = $("#sms_related_object_id").val() || "0";

		// Validation
		if (!to) {
			$result.html(\'<div class="error">'.dol_escape_js($langs->trans('RecipientRequired')).'</div>\');
			return;
		}
		if (!message) {
			$result.html(\'<div class="error">'.dol_escape_js($langs->trans('MessageRequired')).'</div>\');
			return;
		}
		if (message.length > 1000) {
			$result.html(\'<div class="error">'.dol_escape_js($langs->trans('MessageTooLong')).'</div>\');
			return;
		}

		$btn.prop("disabled", true);
		$result.html(\'<i class="fas fa-spinner fa-spin"></i> '.dol_escape_js($langs->trans('SendingInProgress')).'\');

		$.ajax({
			url: "'.dol_escape_js($ajaxUrl).'",
			type: "POST",
			data: {
				action: "send_sms",
				from_number: from_number,
				to: to,
				message: message,
				related_object_type: related_object_type,
				related_object_id: related_object_id,
				token: "'.dol_escape_js($csrfToken).'"
			},
			dataType: "json",
			success: function(response) {
				if (response.success) {
					$result.html(\'<div class="ok"><i class="fas fa-check pictofixedwidth"></i>\' + (response.message || "SMS envoye avec succes") + \'</div>\');
					$("#sms_message").val("");
					$("#sms_charcount").text("0");
					// Reload page to refresh history after a short delay
					setTimeout(function() { location.reload(); }, 1500);
				} else {
					$result.html(\'<div class="error"><i class="fas fa-exclamation-triangle pictofixedwidth"></i>\' + (response.message || "Erreur inconnue") + \'</div>\');
				}
			},
			error: function(xhr) {
				$result.html(\'<div class="error"><i class="fas fa-exclamation-triangle pictofixedwidth"></i>Erreur de communication avec le serveur.</div>\');
			},
			complete: function() {
				$btn.prop("disabled", false);
			}
		});
	});

	// Make contact select searchable if select2 is available
	if (typeof $.fn.select2 === "function") {
		$("#sms_contact").select2({
			width: "300px",
			placeholder: "-- Rechercher un contact --",
			allowClear: true
		});
	}
});
</script>';

llxFooter();
$db->close();
