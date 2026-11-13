<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/setup.php
 * \ingroup dolicraftallo
 * \brief   Configuration page for DolicraftAllo module
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
dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');

// Load translation files
$langs->loadLangs(array("admin", "dolicraftallo@dolicraftallo"));

// Security check - admin only
if (!$user->admin) {
	accessforbidden();
}

//
// Actions
//
$action = GETPOST('action', 'aZ09');

if ($action == 'update') {
	$error = 0;

	$apiKey = GETPOST('api_key', 'none');
	$alloNumber = GETPOST('allo_number', 'alphanohtml');
	$senderId = GETPOST('sender_id', 'alphanohtml');
	$syncContacts = GETPOSTINT('sync_contacts');
	$smsEnabled = GETPOSTINT('sms_enabled');

	if (!$error) {
		// Only update API key if changed from masked value
		if ($apiKey !== '**********' && $apiKey !== '') {
			dolibarr_set_const($db, 'DOLICRAFTALLO_API_KEY', $apiKey, 'chaine', 0, '', $conf->entity);
		}
		dolibarr_set_const($db, 'DOLICRAFTALLO_ALLO_NUMBER', $alloNumber, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'DOLICRAFTALLO_SENDER_ID', $senderId, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'DOLICRAFTALLO_SYNC_CONTACTS', $syncContacts ? '1' : '0', 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'DOLICRAFTALLO_SMS_ENABLED', $smsEnabled ? '1' : '0', 'chaine', 0, '', $conf->entity);

		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
}

//
// View
//
$form = new Form($db);

$page_name = "AlloSetup";
llxHeader('', $langs->trans($page_name), '', '', 0, 0, '', '', '', 'mod-dolicraftallo page-admin-setup');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = dolicraftalloAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'phone');

// Status badge — reflects "API key set" only, not actual API health.
// Real health is shown by the "Test connection" button below, which makes a live HTTP call.
$apiKeySet = getDolGlobalString('DOLICRAFTALLO_API_KEY');
if ($apiKeySet) {
	print '<div class="info" style="margin-bottom: 15px;">';
	print '<i class="fas fa-check-circle pictofixedwidth" style="color: #28a745;"></i>';
	print '<span class="badge badge-status4">'.$langs->trans("ApiKeyConfigured").'</span> ';
	print $langs->trans("AlloSetupDescConfigured");
	print '</div>';
} else {
	print '<div class="warning" style="margin-bottom: 15px;">';
	print '<i class="fas fa-exclamation-triangle pictofixedwidth"></i>';
	print '<span class="badge badge-status8">'.$langs->trans("NoApiKey").'</span> ';
	print $langs->trans("AlloSetupDesc");
	print '</div>';
}

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';

// --- API Settings ---
print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-key pictofixedwidth"></i>'.$langs->trans("ApiKey").'</td></tr>';

// API Key
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("ApiKey").'</td><td>';
$apiKeyDisplay = getDolGlobalString('DOLICRAFTALLO_API_KEY') ? '**********' : '';
print '<input type="password" name="api_key" class="flat minwidth300" value="'.dol_escape_htmltag($apiKeyDisplay).'" autocomplete="new-password">';
print '<br><span class="opacitymedium small">'.$langs->trans("ApiKeyScopesHelp").' <a href="'.dol_buildpath('/dolicraftallo/admin/documentation.php', 1).'#api-scopes">'.$langs->trans("Documentation").'</a></span>';
print '</td></tr>';

// Allo Number
print '<tr class="oddeven"><td>'.$langs->trans("AlloNumber").'</td><td>';
print '<input type="text" name="allo_number" class="flat minwidth300" value="'.dol_escape_htmltag(getDolGlobalString('DOLICRAFTALLO_ALLO_NUMBER')).'" placeholder="+33612345678">';
print '<br><span class="opacitymedium small">Format E.164 (ex: +33612345678)</span>';
print '</td></tr>';

// Sender ID
print '<tr class="oddeven"><td>'.$langs->trans("SenderId").'</td><td>';
print '<input type="text" name="sender_id" class="flat minwidth200" value="'.dol_escape_htmltag(getDolGlobalString('DOLICRAFTALLO_SENDER_ID')).'" placeholder="Dolicraft" maxlength="11">';
print '<br><span class="opacitymedium small">Alphanumerique, 3-11 caracteres (pour SMS France)</span>';
print '</td></tr>';

// --- Features ---
print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-sliders-h pictofixedwidth"></i>Fonctionnalites</td></tr>';

// Sync Contacts
print '<tr class="oddeven"><td>'.$langs->trans("SyncContactsEnabled").'</td><td>';
print '<input type="checkbox" name="sync_contacts" value="1"'.(getDolGlobalString('DOLICRAFTALLO_SYNC_CONTACTS', '1') ? ' checked' : '').'>';
print '</td></tr>';

// SMS Enabled
print '<tr class="oddeven"><td>'.$langs->trans("SmsEnabled").'</td><td>';
print '<input type="checkbox" name="sms_enabled" value="1"'.(getDolGlobalString('DOLICRAFTALLO_SMS_ENABLED', '1') ? ' checked' : '').'>';
print '</td></tr>';

print '</table>';

print '<div class="center" style="margin-top: 15px;">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

// Test connection button
print '<div class="center" style="margin-top: 10px;">';
print '<button type="button" class="button" id="btn_test_connection">';
print '<i class="fas fa-plug pictofixedwidth"></i>'.$langs->trans("TestConnection");
print '</button>';
print '<div id="test_result" style="margin-top: 10px;"></div>';
print '</div>';

print dol_get_fiche_end();

// JavaScript for test connection via AJAX
print '<script type="text/javascript">
$(document).ready(function() {
	$("#btn_test_connection").click(function() {
		var $btn = $(this);
		var $result = $("#test_result");
		$btn.prop("disabled", true);
		$result.html("<i class=\"fas fa-spinner fa-spin\"></i> Test en cours...");

		$.ajax({
			url: "'.dol_buildpath('/dolicraftallo/ajax/allo.php', 1).'",
			type: "POST",
			data: {
				action: "test_connection",
				token: "'.newToken().'"
			},
			dataType: "json",
			success: function(response) {
				if (response.success) {
					var html = "<span class=\"badge badge-status4\">'.$langs->trans("ConnectionOk").'</span>";
					if (response.data && response.data.data) {
						var numbers = response.data.data;
						if (Array.isArray(numbers) && numbers.length > 0) {
							html += "<br><small>Numeros disponibles : ";
							var numList = [];
							for (var i = 0; i < numbers.length; i++) {
								var n = numbers[i];
								numList.push((n.name ? n.name + " " : "") + (n.number || n));
							}
							html += numList.join(", ") + "</small>";
						}
					}
					$result.html(html);
				} else {
					$result.html("<span class=\"badge badge-status8\">'.$langs->trans("ConnectionError").'</span> " + (response.message || "Erreur inconnue"));
				}
			},
			error: function(xhr) {
				$result.html("<span class=\"badge badge-status8\">'.$langs->trans("ConnectionError").'</span> Erreur de communication");
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
