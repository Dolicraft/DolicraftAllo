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
 * \file    inbox.php
 * \ingroup dolicraftallo
 * \brief   SMS Inbox with conversation threading
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
dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');

// Load translation files
$langs->loadLangs(array("dolicraftallo@dolicraftallo"));

// Security check
if (!$user->hasRight('dolicraftallo', 'view')) {
	accessforbidden();
}

// Parameters
$contactNumber = GETPOST('contact_number', 'alphanohtml');

// ---------------------------------------------------------------------------
// Load our Allo numbers to identify "other" contacts
// ---------------------------------------------------------------------------
$alloNumbers = dolicraftalloGetNumbers($db, $user->id);
$alloNumbersList = array();
foreach ($alloNumbers as $numObj) {
	$alloNumbersList[] = $numObj->allo_number;
}

// ---------------------------------------------------------------------------
// Build contact list: group by the OTHER number (not our Allo number)
// ---------------------------------------------------------------------------
$contacts = array();

if (!empty($alloNumbersList)) {
	// Build CASE expression to determine the "other" number
	$caseFrom = array();
	$caseTo = array();
	foreach ($alloNumbersList as $num) {
		$caseFrom[] = "sl.from_number = '".$db->escape($num)."'";
		$caseTo[] = "sl.to_number = '".$db->escape($num)."'";
	}

	// The "other" number: if from_number is one of ours, then the other is to_number, otherwise from_number
	$otherNumberExpr = "CASE WHEN (".implode(' OR ', $caseFrom).") THEN sl.to_number ELSE sl.from_number END";

	$sql = "SELECT other_number, MAX(sl.datec) as last_date, COUNT(sl.rowid) as total_messages,";
	$sql .= " SUM(CASE WHEN sl.sms_type = 'INBOUND' AND sl.status != 'read' THEN 1 ELSE 0 END) as unread_count,";
	$sql .= " SUBSTRING_INDEX(GROUP_CONCAT(sl.content ORDER BY sl.datec DESC SEPARATOR '|||'), '|||', 1) as last_message";
	$sql .= " FROM (SELECT sl.rowid, sl.from_number, sl.to_number, sl.sms_type, sl.content, sl.status, sl.datec,";
	$sql .= " ".$otherNumberExpr." as other_number";
	$sql .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_log as sl";
	$sql .= " WHERE sl.entity = ".((int) $conf->entity);
	$sql .= ") as sl";
	$sql .= " WHERE sl.other_number IS NOT NULL AND sl.other_number != ''";
	$sql .= " GROUP BY other_number";
	$sql .= " ORDER BY last_date DESC";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$contacts[] = $obj;
		}
		$db->free($resql);
	}
}

// For each contact, try to find a matching socpeople name
$contactNames = array();
foreach ($contacts as $c) {
	$phone = $c->other_number;
	$cleanPhone = ltrim($phone, '+');

	$sqlsp = "SELECT sp.firstname, sp.lastname, sp.rowid FROM ".MAIN_DB_PREFIX."socpeople as sp";
	$sqlsp .= " WHERE sp.entity IN (".getEntity('socpeople').")";
	$sqlsp .= " AND (sp.phone = '".$db->escape($phone)."'";
	$sqlsp .= " OR sp.phone_mobile = '".$db->escape($phone)."'";
	$sqlsp .= " OR sp.phone LIKE '%".$db->escape($cleanPhone)."'";
	$sqlsp .= " OR sp.phone_mobile LIKE '%".$db->escape($cleanPhone)."')";
	$sqlsp .= $db->plimit(1);

	$resqlsp = $db->query($sqlsp);
	if ($resqlsp && $db->num_rows($resqlsp) > 0) {
		$objsp = $db->fetch_object($resqlsp);
		$contactNames[$phone] = trim($objsp->firstname.' '.$objsp->lastname);
	}
}

// ---------------------------------------------------------------------------
// Load conversation messages if a contact is selected
// ---------------------------------------------------------------------------
$messages = array();
if (!empty($contactNumber)) {
	$sqlMsg = "SELECT sl.rowid, sl.from_number, sl.to_number, sl.sms_type, sl.content, sl.status, sl.datec";
	$sqlMsg .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_log as sl";
	$sqlMsg .= " WHERE sl.entity = ".((int) $conf->entity);
	$sqlMsg .= " AND (sl.from_number = '".$db->escape($contactNumber)."' OR sl.to_number = '".$db->escape($contactNumber)."')";
	$sqlMsg .= " ORDER BY sl.datec ASC";

	$resqlMsg = $db->query($sqlMsg);
	if ($resqlMsg) {
		while ($obj = $db->fetch_object($resqlMsg)) {
			$messages[] = $obj;
		}
		$db->free($resqlMsg);
	}
}

// Get default Allo number for reply form
$defaultAlloNumber = dolicraftalloGetDefaultNumber($db, $user->id);

// ---------------------------------------------------------------------------
// View
// ---------------------------------------------------------------------------
$form = new Form($db);

llxHeader('', $langs->trans("Inbox"), '', '', 0, 0, array(), array(), '', 'mod-dolicraftallo page-inbox');

print load_fiche_titre($langs->trans("DolicraftAllo"), '', 'phone');

$head = dolicraftalloAdminPrepareHead();
print dol_get_fiche_head($head, 'inbox', $langs->trans("DolicraftAllo"), -1, 'phone');

// ===================================================================
// INBOX LAYOUT: Left panel (contacts) + Right panel (conversation)
// ===================================================================

print '<div style="display: flex; border: 1px solid #ccc; border-radius: 4px; height: 600px; overflow: hidden; background: #fff;">';

// ---------------------------------------------------------------
// LEFT PANEL: Contact list
// ---------------------------------------------------------------
print '<div style="width: 300px; min-width: 300px; border-right: 1px solid #ccc; overflow-y: auto; background: #fafafa;">';

if (empty($contacts)) {
	print '<div style="padding: 20px; text-align: center; color: #888;">';
	print '<i class="fas fa-inbox" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>';
	print $langs->trans("NoSMSYet");
	print '</div>';
} else {
	foreach ($contacts as $c) {
		$phone = $c->other_number;
		$isSelected = ($phone === $contactNumber);
		$bgColor = $isSelected ? '#e3efff' : 'transparent';
		$borderLeft = $isSelected ? '3px solid #3b82f6' : '3px solid transparent';

		$displayName = isset($contactNames[$phone]) ? $contactNames[$phone] : $phone;
		$lastMsg = dol_trunc($c->last_message, 40);
		$lastDate = dol_print_date($db->jdate($c->last_date), '%H:%M %d/%m');
		$unread = (int) $c->unread_count;

		$url = $_SERVER['PHP_SELF'].'?contact_number='.urlencode($phone);

		print '<a href="'.dol_escape_htmltag($url).'" style="display: block; padding: 10px 12px; border-bottom: 1px solid #eee; text-decoration: none; color: inherit; background: '.$bgColor.'; border-left: '.$borderLeft.';">';

		// Top row: name + date
		print '<div style="display: flex; justify-content: space-between; align-items: center;">';
		print '<strong style="font-size: 0.95em; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 180px;">'.dol_escape_htmltag($displayName).'</strong>';
		print '<span style="font-size: 0.75em; color: #888; white-space: nowrap;">'.$lastDate.'</span>';
		print '</div>';

		// Bottom row: preview + unread badge
		print '<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">';
		print '<span style="font-size: 0.85em; color: #666; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 210px;">'.dol_escape_htmltag($lastMsg).'</span>';
		if ($unread > 0) {
			print '<span style="background: #3b82f6; color: #fff; border-radius: 50%; min-width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75em; font-weight: bold;">'.$unread.'</span>';
		}
		print '</div>';

		// Show phone number if name is different
		if (isset($contactNames[$phone])) {
			print '<div style="font-size: 0.75em; color: #aaa; margin-top: 2px;">'.dol_escape_htmltag($phone).'</div>';
		}

		print '</a>';
	}
}

print '</div>'; // end left panel

// ---------------------------------------------------------------
// RIGHT PANEL: Conversation view
// ---------------------------------------------------------------
print '<div style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">';

if (empty($contactNumber)) {
	// Empty state: no conversation selected
	print '<div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #888;">';
	print '<div style="text-align: center;">';
	print '<i class="fas fa-comments" style="font-size: 3em; margin-bottom: 15px; display: block; color: #ccc;"></i>';
	print '<p style="font-size: 1.1em;">'.$langs->trans("SelectAConversation").'</p>';
	print '</div>';
	print '</div>';
} else {
	// Conversation header
	$headerName = isset($contactNames[$contactNumber]) ? $contactNames[$contactNumber] : $contactNumber;
	print '<div style="padding: 12px 16px; border-bottom: 1px solid #ccc; background: #f8f9fa; display: flex; align-items: center;">';
	print '<i class="fas fa-user-circle" style="font-size: 1.5em; color: #888; margin-right: 10px;"></i>';
	print '<div>';
	print '<strong style="font-size: 1.05em;">'.dol_escape_htmltag($headerName).'</strong>';
	if (isset($contactNames[$contactNumber])) {
		print '<br><span style="font-size: 0.85em; color: #888;">'.dol_escape_htmltag($contactNumber).'</span>';
	}
	print '</div>';
	print '</div>';

	// Messages area
	print '<div id="inbox_messages" style="flex: 1; overflow-y: auto; padding: 16px; background: #f0f0f0;">';

	if (empty($messages)) {
		print '<div style="text-align: center; color: #888; margin-top: 40px;">';
		print '<i class="fas fa-comment-slash" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>';
		print $langs->trans("NoSMSYet");
		print '</div>';
	} else {
		foreach ($messages as $msg) {
			$isOutbound = ($msg->sms_type == 'OUTBOUND');
			$align = $isOutbound ? 'flex-end' : 'flex-start';
			$bgBubble = $isOutbound ? '#3b82f6' : '#f3f4f6';
			$textColor = $isOutbound ? '#ffffff' : '#1f2937';
			$timeColor = $isOutbound ? 'rgba(255,255,255,0.7)' : '#9ca3af';
			$msgTime = dol_print_date($db->jdate($msg->datec), '%H:%M');
			$msgDate = dol_print_date($db->jdate($msg->datec), '%d/%m/%Y');

			print '<div style="display: flex; justify-content: '.$align.'; margin-bottom: 8px;">';
			print '<div style="max-width: 75%; background: '.$bgBubble.'; color: '.$textColor.'; padding: 10px 14px; border-radius: 12px; word-wrap: break-word;">';

			// Message content
			print '<div style="font-size: 0.95em; line-height: 1.4;">'.dol_nl2br(dol_escape_htmltag($msg->content)).'</div>';

			// Footer: time + status
			print '<div style="display: flex; justify-content: flex-end; align-items: center; gap: 6px; margin-top: 4px;">';
			print '<span style="font-size: 0.72em; color: '.$timeColor.';">'.$msgDate.' '.$msgTime.'</span>';

			if ($isOutbound) {
				$statusIcon = '';
				$statusTitle = '';
				switch ($msg->status) {
					case 'delivered':
						$statusIcon = 'fa-check-double';
						$statusTitle = $langs->trans('Delivered');
						break;
					case 'sent':
						$statusIcon = 'fa-check';
						$statusTitle = $langs->trans('Sent');
						break;
					case 'failed':
						$statusIcon = 'fa-exclamation-circle';
						$statusTitle = $langs->trans('Failed');
						break;
					case 'pending':
						$statusIcon = 'fa-clock';
						$statusTitle = $langs->trans('Pending');
						break;
					default:
						$statusIcon = 'fa-check';
						$statusTitle = dol_escape_htmltag($msg->status);
						break;
				}
				print '<i class="fas '.$statusIcon.'" style="font-size: 0.72em; color: '.$timeColor.';" title="'.dol_escape_htmltag($statusTitle).'"></i>';
			}

			print '</div>'; // footer
			print '</div>'; // bubble
			print '</div>'; // row
		}
	}

	print '</div>'; // messages area

	// Reply form
	print '<div style="padding: 12px 16px; border-top: 1px solid #ccc; background: #fff;">';
	print '<div id="inbox_send_result" style="margin-bottom: 6px;"></div>';
	print '<div style="display: flex; gap: 8px; align-items: flex-end;">';

	// From number select (compact)
	print '<select id="inbox_from" class="flat" style="width: 140px; font-size: 0.85em;">';
	foreach ($alloNumbers as $numObj) {
		$selected = (!empty($numObj->is_default)) ? ' selected' : '';
		print '<option value="'.dol_escape_htmltag($numObj->allo_number).'"'.$selected.'>'.dol_escape_htmltag($numObj->label).'</option>';
	}
	print '</select>';

	// Message textarea
	print '<textarea id="inbox_message" class="flat" rows="2" style="flex: 1; resize: none; font-size: 0.95em;" placeholder="'.$langs->trans("YourMessage").'..." maxlength="1000"></textarea>';

	// Send button
	print '<button type="button" id="btn_inbox_send" class="button" style="white-space: nowrap;">';
	print '<i class="fas fa-paper-plane"></i>';
	print '</button>';

	print '</div>'; // flex row
	print '</div>'; // reply form
}

print '</div>'; // end right panel
print '</div>'; // end inbox layout

print dol_get_fiche_end();

// ===================================================================
// JavaScript
// ===================================================================
$ajaxUrl = dol_buildpath('/dolicraftallo/ajax/allo.php', 1);
$csrfToken = newToken();

print '<script type="text/javascript">
$(document).ready(function() {

	// Scroll messages to bottom on load
	var $msgArea = $("#inbox_messages");
	if ($msgArea.length) {
		$msgArea.scrollTop($msgArea[0].scrollHeight);
	}

	// Send reply via AJAX
	$("#btn_inbox_send").click(function() {
		var $btn = $(this);
		var $result = $("#inbox_send_result");
		var from_number = $("#inbox_from").val() || "";
		var message = $("#inbox_message").val() ? $("#inbox_message").val().trim() : "";
		var to = "'.dol_escape_js($contactNumber).'";

		if (!message) {
			$result.html(\'<div class="error">'.dol_escape_js($langs->trans('MessageRequired')).'</div>\');
			return;
		}

		$btn.prop("disabled", true);
		$result.html(\'<i class="fas fa-spinner fa-spin"></i>\');

		$.ajax({
			url: "'.dol_escape_js($ajaxUrl).'",
			type: "POST",
			data: {
				action: "send_sms",
				from_number: from_number,
				to: to,
				message: message,
				related_object_type: "",
				related_object_id: 0,
				token: "'.dol_escape_js($csrfToken).'"
			},
			dataType: "json",
			success: function(response) {
				if (response.success) {
					$result.html("");
					// Append the new message bubble without reload
					var now = new Date();
					var timeStr = ("0" + now.getHours()).slice(-2) + ":" + ("0" + now.getMinutes()).slice(-2);
					var dateStr = ("0" + now.getDate()).slice(-2) + "/" + ("0" + (now.getMonth()+1)).slice(-2) + "/" + now.getFullYear();
					var bubble = \'<div style="display: flex; justify-content: flex-end; margin-bottom: 8px;">\';
					bubble += \'<div style="max-width: 75%; background: #3b82f6; color: #ffffff; padding: 10px 14px; border-radius: 12px; word-wrap: break-word;">\';
					bubble += \'<div style="font-size: 0.95em; line-height: 1.4;">\' + $("<div>").text(message).html().replace(/\\n/g, "<br>") + \'</div>\';
					bubble += \'<div style="display: flex; justify-content: flex-end; align-items: center; gap: 6px; margin-top: 4px;">\';
					bubble += \'<span style="font-size: 0.72em; color: rgba(255,255,255,0.7);">\' + dateStr + \' \' + timeStr + \'</span>\';
					bubble += \'<i class="fas fa-check" style="font-size: 0.72em; color: rgba(255,255,255,0.7);" title="Sent"></i>\';
					bubble += \'</div></div></div>\';

					$msgArea.append(bubble);
					$msgArea.scrollTop($msgArea[0].scrollHeight);
					$("#inbox_message").val("");
				} else {
					$result.html(\'<div class="error"><i class="fas fa-exclamation-triangle pictofixedwidth"></i>\' + (response.message || "Erreur") + \'</div>\');
				}
			},
			error: function() {
				$result.html(\'<div class="error"><i class="fas fa-exclamation-triangle pictofixedwidth"></i>Erreur de communication</div>\');
			},
			complete: function() {
				$btn.prop("disabled", false);
			}
		});
	});

	// Send on Enter (Shift+Enter for newline)
	$("#inbox_message").keydown(function(e) {
		if (e.keyCode === 13 && !e.shiftKey) {
			e.preventDefault();
			$("#btn_inbox_send").click();
		}
	});
});
</script>';

llxFooter();
$db->close();
