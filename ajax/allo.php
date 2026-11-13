<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/allo.php
 * \ingroup dolicraftallo
 * \brief   AJAX endpoint for Allo API actions
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	http_response_code(500);
	echo json_encode(array('success' => false, 'message' => 'Include of main fails'));
	exit;
}

dol_include_once('/dolicraftallo/class/allo_api.class.php');
dol_include_once('/dolicraftallo/class/allo_sync.class.php');
dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');

// Load translation files
$langs->loadLangs(array("dolicraftallo@dolicraftallo"));

// Security check - minimum view permission
if (!$user->hasRight('dolicraftallo', 'view')) {
	http_response_code(403);
	echo json_encode(array('success' => false, 'message' => 'Access denied'));
	exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = GETPOST('action', 'aZ09');

// ============================================================
// ACTION: test_connection
// ============================================================
if ($action == 'test_connection') {
	$api = new DolicraftAlloAPI($db);
	$result = $api->testConnection();

	if ($result['success']) {
		echo json_encode(array(
			'success' => true,
			'message' => $langs->trans("ConnectionOk"),
			'data' => isset($result['data']) ? $result['data'] : null,
		));
	} else {
		echo json_encode(array(
			'success' => false,
			'message' => $result['message'],
		));
	}
	exit;
}

// ============================================================
// ACTION: sync_calls
// ============================================================
if ($action == 'sync_calls') {
	$sync = new DolicraftAlloSync($db);
	$stats = $sync->syncCalls();

	if (!is_array($stats)) {
		echo json_encode(array('success' => false, 'message' => $sync->error));
		exit;
	}

	echo json_encode(array(
		'success' => true,
		'message' => $stats['inserted'].' '.$langs->trans('CallsImported').', '.$stats['updated'].' '.$langs->trans('Updated'),
		'inserted' => $stats['inserted'],
		'updated' => $stats['updated'],
	));
	exit;
}

// ============================================================
// ACTION: send_sms
// ============================================================
if ($action == 'send_sms') {
	dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');

	// Check send_sms permission
	if (!$user->hasRight('dolicraftallo', 'send_sms')) {
		echo json_encode(array('success' => false, 'message' => $langs->trans('PermissionDenied')));
		exit;
	}

	$to = GETPOST('to', 'alphanohtml');
	$message = GETPOST('message', 'restricthtml');
	$fromNumber = GETPOST('from_number', 'alphanohtml');
	$relatedObjectType = GETPOST('related_object_type', 'alphanohtml');
	$relatedObjectId = GETPOSTINT('related_object_id');

	if (empty($to)) {
		echo json_encode(array('success' => false, 'message' => $langs->trans('RecipientRequired')));
		exit;
	}
	if (empty($message)) {
		echo json_encode(array('success' => false, 'message' => $langs->trans('MessageRequired')));
		exit;
	}
	if (strlen($message) > 1000) {
		echo json_encode(array('success' => false, 'message' => $langs->trans('MessageTooLong')));
		exit;
	}

	// Determine from_number: POST value, then default number, then legacy constant
	if (empty($fromNumber)) {
		$fromNumber = dolicraftalloGetDefaultNumber($db, $user->id);
	}

	if (empty($fromNumber)) {
		echo json_encode(array('success' => false, 'message' => $langs->trans('NoSenderNumber')));
		exit;
	}

	// Determine sender_id from the numbers table
	$senderId = '';
	$sqlNum = "SELECT sender_id FROM ".MAIN_DB_PREFIX."dolicraftallo_numbers";
	$sqlNum .= " WHERE entity = ".((int) $conf->entity);
	$sqlNum .= " AND allo_number = '".$db->escape($fromNumber)."'";
	$sqlNum .= $db->plimit(1);
	$resqlNum = $db->query($sqlNum);
	if ($resqlNum && $db->num_rows($resqlNum) > 0) {
		$objNum = $db->fetch_object($resqlNum);
		$senderId = $objNum->sender_id;
	}

	$api = new DolicraftAlloAPI($db);

	dol_syslog('DolicraftAllo send_sms: attempting to send SMS from '.$fromNumber.' to '.$to, LOG_INFO);

	// Use sendSmsFrance if sender_id is set, otherwise use sendSms
	if (!empty($senderId)) {
		$result = $api->sendSmsFrance($senderId, $to, $message);
	} else {
		$result = $api->sendSms($fromNumber, $to, $message);
	}

	if ($result === false) {
		dol_syslog('DolicraftAllo send_sms: failed - '.$api->getLastError(), LOG_WARNING);
		echo json_encode(array('success' => false, 'message' => $api->getLastError()));
		exit;
	}

	dol_syslog('DolicraftAllo send_sms: SMS sent successfully from '.$fromNumber.' to '.$to, LOG_INFO);

	// Match phone number to Dolibarr contact (same logic as sync_calls)
	$fkSoc = 0;
	$fkSocpeople = 0;
	$cleanNumber = ltrim($to, '+');

	$sqlsp = "SELECT sp.rowid, sp.fk_soc FROM ".MAIN_DB_PREFIX."socpeople as sp";
	$sqlsp .= " WHERE sp.entity IN (".getEntity('socpeople').")";
	$sqlsp .= " AND (sp.phone = '".$db->escape($to)."'";
	$sqlsp .= " OR sp.phone_mobile = '".$db->escape($to)."'";
	$sqlsp .= " OR sp.phone LIKE '%".dolicraftalloEscapeLike($db->escape($cleanNumber))."'";
	$sqlsp .= " OR sp.phone_mobile LIKE '%".dolicraftalloEscapeLike($db->escape($cleanNumber))."')";
	$sqlsp .= $db->plimit(1);

	$resqlsp = $db->query($sqlsp);
	if ($resqlsp && $db->num_rows($resqlsp) > 0) {
		$objsp = $db->fetch_object($resqlsp);
		$fkSocpeople = (int) $objsp->rowid;
		$fkSoc = (int) $objsp->fk_soc;
	}

	// If no contact found, search in societe
	if ($fkSoc == 0) {
		$sqlsoc = "SELECT s.rowid FROM ".MAIN_DB_PREFIX."societe as s";
		$sqlsoc .= " WHERE s.entity IN (".getEntity('societe').")";
		$sqlsoc .= " AND (s.phone = '".$db->escape($to)."'";
		$sqlsoc .= " OR s.phone LIKE '%".dolicraftalloEscapeLike($db->escape($cleanNumber))."')";
		$sqlsoc .= $db->plimit(1);

		$resqlsoc = $db->query($sqlsoc);
		if ($resqlsoc && $db->num_rows($resqlsoc) > 0) {
			$objsoc = $db->fetch_object($resqlsoc);
			$fkSoc = (int) $objsoc->rowid;
		}
	}

	// Log SMS in database with related object info
	$sqlins = "INSERT INTO ".MAIN_DB_PREFIX."dolicraftallo_sms_log";
	$sqlins .= " (entity, fk_soc, fk_socpeople, from_number, to_number, sms_type, content, status,";
	$sqlins .= " related_object_type, related_object_id, datec)";
	$sqlins .= " VALUES (";
	$sqlins .= ((int) $conf->entity).",";
	$sqlins .= ((int) $fkSoc).",";
	$sqlins .= ((int) $fkSocpeople).",";
	$sqlins .= "'".$db->escape($fromNumber)."',";
	$sqlins .= "'".$db->escape($to)."',";
	$sqlins .= "'OUTBOUND',";
	$sqlins .= "'".$db->escape($message)."',";
	$sqlins .= "'sent',";
	$sqlins .= "'".$db->escape($relatedObjectType)."',";
	$sqlins .= ((int) $relatedObjectId).",";
	$sqlins .= "'".$db->idate(dol_now())."'";
	$sqlins .= ")";

	$resqlins = $db->query($sqlins);
	if (!$resqlins) {
		dol_syslog('DolicraftAllo: SQL error on SMS log insert: '.$db->lasterror(), LOG_ERR);
	}

	echo json_encode(array(
		'success' => true,
		'message' => $langs->trans('SMSSentSuccess'),
	));
	exit;
}

// ============================================================
// ACTION: sync_contacts
// ============================================================
if ($action == 'sync_contacts') {
	// Check sync permission
	if (!$user->hasRight('dolicraftallo', 'sync')) {
		echo json_encode(array('success' => false, 'message' => $langs->trans('PermissionDenied')));
		exit;
	}

	$sync = new DolicraftAlloSync($db);
	$stats = $sync->syncContacts($user);

	if (!is_array($stats)) {
		echo json_encode(array('success' => false, 'message' => $sync->error));
		exit;
	}

	echo json_encode(array(
		'success' => true,
		'message' => $langs->trans('SyncComplete').' : '.$stats['total_allo'].' contacts Allo, '.$stats['new_created'].' '.$langs->trans('CreatedInDolibarr').', '.$stats['new_mapped'].' '.$langs->trans('NewMappings'),
		'stats' => $stats,
	));
	exit;
}

// Unknown action
echo json_encode(array('success' => false, 'message' => $langs->trans('UnknownAction')));
