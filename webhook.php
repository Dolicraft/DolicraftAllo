<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 * License: GPL v3+
 *
 * Webhook receiver for Allo events.
 * This endpoint is called by Allo servers and does NOT require Dolibarr login.
 */

// Prevent Dolibarr from requiring authentication
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(array('error' => 'Method not allowed'));
	exit;
}

// Read raw POST body
$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);

dol_syslog('DolicraftAllo webhook received: '.$rawPayload, LOG_INFO);

if (!is_array($payload) || empty($payload['topic'])) {
	dol_syslog('DolicraftAllo webhook: invalid payload or missing topic', LOG_WARNING);
	http_response_code(200);
	echo json_encode(array('status' => 'ok', 'message' => 'Invalid payload'));
	exit;
}

// Optional: verify webhook secret
$webhookSecret = getDolGlobalString('DOLICRAFTALLO_WEBHOOK_SECRET');
if (!empty($webhookSecret)) {
	$signature = isset($_SERVER['HTTP_X_ALLO_SIGNATURE']) ? $_SERVER['HTTP_X_ALLO_SIGNATURE'] : '';
	$expectedSignature = hash_hmac('sha256', $rawPayload, $webhookSecret);
	if (!hash_equals($expectedSignature, $signature)) {
		dol_syslog('DolicraftAllo webhook: invalid signature', LOG_WARNING);
		http_response_code(200);
		echo json_encode(array('status' => 'ok', 'message' => 'Invalid signature'));
		exit;
	}
}

$topic = $payload['topic'];
$data = isset($payload['data']) ? $payload['data'] : $payload;

dol_syslog('DolicraftAllo webhook topic: '.$topic, LOG_INFO);

$now = dol_now();

switch ($topic) {
	case 'CALL_RECEIVED':
		handleCallReceived($db, $data, $now);
		break;

	case 'SMS_RECEIVED':
		handleSmsReceived($db, $data, $now);
		break;

	case 'SMS_SENT':
		handleSmsSent($db, $data, $now);
		break;

	case 'CONTACT_CREATED':
		handleContactCreated($db, $data, $now);
		break;

	case 'CONTACT_UPDATED':
		handleContactUpdated($db, $data, $now);
		break;

	default:
		dol_syslog('DolicraftAllo webhook: unknown topic '.$topic, LOG_WARNING);
		break;
}

// Always return 200 OK - Allo expects a response within 30 seconds
http_response_code(200);
echo json_encode(array('status' => 'ok'));
exit;


/* --------------------------------------------------------------------------
 * Handler functions
 * -------------------------------------------------------------------------- */

/**
 * Handle CALL_RECEIVED event
 *
 * @param DoliDB $db        Database handler
 * @param array  $data      Webhook data
 * @param int    $now       Current timestamp
 * @return void
 */
function handleCallReceived($db, $data, $now)
{
	global $conf, $user;

	$fromNumber = isset($data['from_number']) ? trim($data['from_number']) : '';
	$toNumber   = isset($data['to_number']) ? trim($data['to_number']) : '';
	$callType   = isset($data['call_type']) ? $data['call_type'] : 'INBOUND';
	$callResult = isset($data['call_result']) ? $data['call_result'] : 'ANSWERED';
	// Allo sends call duration in seconds. The DB column is `duration_minutes`
	// (decimal minutes), so convert: 120s -> 2.0 minutes.
	$durationSeconds = isset($data['duration']) ? (float) $data['duration'] : 0;
	$duration = $durationSeconds > 0 ? round($durationSeconds / 60, 4) : 0;
	// Use SQL datetime format ('YYYY-MM-DD HH:MM:SS'), not display format —
	// `dol_print_date(..., 'dayhour')` returns localized strings like
	// "05/31/2026 11:55 AM" which break the DATETIME column.
	$callDate   = isset($data['call_date']) ? $data['call_date'] : $db->idate($now);
	$summary    = isset($data['summary']) ? $data['summary'] : '';
	$recordUrl  = isset($data['recording_url']) ? $data['recording_url'] : '';
	$tag        = isset($data['tag']) ? $data['tag'] : '';
	$alloCallId = isset($data['call_id']) ? $data['call_id'] : '';

	// Match phone numbers to Dolibarr contacts/companies
	$fkContactFrom = findContactByPhone($db, $fromNumber);
	$fkContactTo   = findContactByPhone($db, $toNumber);
	$fkSocFrom     = findSocieteByPhone($db, $fromNumber);
	$fkSocTo       = findSocieteByPhone($db, $toNumber);

	$fkContact = $fkContactFrom ? $fkContactFrom : $fkContactTo;
	$fkSoc     = $fkSocFrom ? $fkSocFrom : $fkSocTo;

	// Insert into call log
	$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolicraftallo_call_log (";
	$sql .= "allo_call_id, from_number, to_number, call_type, call_result,";
	$sql .= " duration_minutes, call_date, summary, recording_url, tag,";
	$sql .= " fk_socpeople, fk_soc, datec, entity";
	$sql .= ") VALUES (";
	$sql .= "'".$db->escape($alloCallId)."',";
	$sql .= " '".$db->escape($fromNumber)."',";
	$sql .= " '".$db->escape($toNumber)."',";
	$sql .= " '".$db->escape($callType)."',";
	$sql .= " '".$db->escape($callResult)."',";
	$sql .= " ".((float) $duration).",";
	$sql .= " '".$db->escape($callDate)."',";
	$sql .= " '".$db->escape($summary)."',";
	$sql .= " '".$db->escape($recordUrl)."',";
	$sql .= " '".$db->escape($tag)."',";
	$sql .= " ".($fkContact ? (int) $fkContact : 0).",";
	$sql .= " ".($fkSoc ? (int) $fkSoc : 0).",";
	$sql .= " '".$db->idate($now)."',";
	$sql .= " ".((int) $conf->entity);
	$sql .= ")";

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('DolicraftAllo webhook CALL_RECEIVED: SQL error '.$db->lasterror(), LOG_ERR);
		return;
	}

	dol_syslog('DolicraftAllo webhook CALL_RECEIVED: inserted call log for '.$fromNumber.' -> '.$toNumber, LOG_INFO);

	// Optionally create an agenda event (actioncomm)
	if (getDolGlobalString('DOLICRAFTALLO_AUTO_CREATE_EVENT')) {
		$actioncomm = new ActionComm($db);
		$actioncomm->type_code  = 'AC_TEL';
		$actioncomm->code       = 'AC_DOLICRAFTALLO_CALL';
		$actioncomm->label      = 'Appel Allo: '.$fromNumber.' -> '.$toNumber;
		$actioncomm->note_private = $summary;
		$actioncomm->datep      = $now;
		$actioncomm->datef      = $now + $duration;
		$actioncomm->durationp  = $duration;
		$actioncomm->percentage = -1; // Not applicable
		$actioncomm->socid      = $fkSoc ? $fkSoc : 0;
		$actioncomm->contactid  = $fkContact ? $fkContact : 0;
		$actioncomm->authorid   = 0;
		$actioncomm->userownerid = 0;
		$actioncomm->entity     = $conf->entity;

		$result = $actioncomm->create($user);
		if ($result < 0) {
			dol_syslog('DolicraftAllo webhook CALL_RECEIVED: error creating actioncomm '.$actioncomm->error, LOG_ERR);
		} else {
			dol_syslog('DolicraftAllo webhook CALL_RECEIVED: created actioncomm id='.$result, LOG_INFO);
		}
	}
}

/**
 * Handle SMS_RECEIVED event
 *
 * @param DoliDB $db        Database handler
 * @param array  $data      Webhook data
 * @param int    $now       Current timestamp
 * @return void
 */
function handleSmsReceived($db, $data, $now)
{
	global $conf;

	$fromNumber = isset($data['from_number']) ? trim($data['from_number']) : '';
	$toNumber   = isset($data['to_number']) ? trim($data['to_number']) : '';
	$content    = isset($data['content']) ? $data['content'] : '';
	$smsDate    = isset($data['sms_date']) ? $data['sms_date'] : $db->idate($now);
	$alloSmsId  = isset($data['sms_id']) ? $data['sms_id'] : '';
	$status     = isset($data['status']) ? $data['status'] : 'delivered';

	$fkContact = findContactByPhone($db, $fromNumber);
	$fkSoc     = findSocieteByPhone($db, $fromNumber);

	$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolicraftallo_sms_log (";
	$sql .= "from_number, to_number, sms_type, content,";
	$sql .= " status, fk_socpeople, fk_soc, datec, entity";
	$sql .= ") VALUES (";
	$sql .= "'".$db->escape($fromNumber)."',";
	$sql .= " '".$db->escape($toNumber)."',";
	$sql .= " 'INBOUND',";
	$sql .= " '".$db->escape($content)."',";
	$sql .= " '".$db->escape($status)."',";
	$sql .= " ".($fkContact ? (int) $fkContact : 0).",";
	$sql .= " ".($fkSoc ? (int) $fkSoc : 0).",";
	$sql .= " '".$db->idate($now)."',";
	$sql .= " ".((int) $conf->entity);
	$sql .= ")";

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('DolicraftAllo webhook SMS_RECEIVED: SQL error '.$db->lasterror(), LOG_ERR);
		return;
	}

	dol_syslog('DolicraftAllo webhook SMS_RECEIVED: inserted SMS log from '.$fromNumber, LOG_INFO);
}

/**
 * Handle SMS_SENT event
 *
 * @param DoliDB $db        Database handler
 * @param array  $data      Webhook data
 * @param int    $now       Current timestamp
 * @return void
 */
function handleSmsSent($db, $data, $now)
{
	global $conf;

	$fromNumber = isset($data['from_number']) ? trim($data['from_number']) : '';
	$toNumber   = isset($data['to_number']) ? trim($data['to_number']) : '';
	$content    = isset($data['content']) ? $data['content'] : '';
	$smsDate    = isset($data['sms_date']) ? $data['sms_date'] : $db->idate($now);
	$alloSmsId  = isset($data['sms_id']) ? $data['sms_id'] : '';
	$status     = isset($data['status']) ? $data['status'] : 'sent';

	$fkContact = findContactByPhone($db, $toNumber);
	$fkSoc     = findSocieteByPhone($db, $toNumber);

	$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolicraftallo_sms_log (";
	$sql .= "from_number, to_number, sms_type, content,";
	$sql .= " status, fk_socpeople, fk_soc, datec, entity";
	$sql .= ") VALUES (";
	$sql .= "'".$db->escape($fromNumber)."',";
	$sql .= " '".$db->escape($toNumber)."',";
	$sql .= " 'OUTBOUND',";
	$sql .= " '".$db->escape($content)."',";
	$sql .= " '".$db->escape($status)."',";
	$sql .= " ".($fkContact ? (int) $fkContact : 0).",";
	$sql .= " ".($fkSoc ? (int) $fkSoc : 0).",";
	$sql .= " '".$db->idate($now)."',";
	$sql .= " ".((int) $conf->entity);
	$sql .= ")";

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('DolicraftAllo webhook SMS_SENT: SQL error '.$db->lasterror(), LOG_ERR);
		return;
	}

	dol_syslog('DolicraftAllo webhook SMS_SENT: inserted SMS log to '.$toNumber, LOG_INFO);
}

/**
 * Handle CONTACT_CREATED event
 *
 * @param DoliDB $db        Database handler
 * @param array  $data      Webhook data
 * @param int    $now       Current timestamp
 * @return void
 */
function handleContactCreated($db, $data, $now)
{
	global $conf;

	$alloContactId = isset($data['contact_id']) ? $data['contact_id'] : '';
	$phoneNumber   = isset($data['phone_number']) ? trim($data['phone_number']) : '';
	$name          = isset($data['name']) ? $data['name'] : '';
	$email         = isset($data['email']) ? $data['email'] : '';

	if (empty($phoneNumber) && empty($alloContactId)) {
		dol_syslog('DolicraftAllo webhook CONTACT_CREATED: no phone number or contact_id', LOG_WARNING);
		return;
	}

	// Check if phone number matches an existing Dolibarr contact
	$fkContact = findContactByPhone($db, $phoneNumber);
	$fkSoc     = findSocieteByPhone($db, $phoneNumber);

	if ($fkContact || $fkSoc) {
		// Create mapping
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolicraftallo_contact_map (";
		$sql .= "allo_contact_id, fk_socpeople, fk_soc, phone_number, allo_name,";
		$sql .= " datec, entity";
		$sql .= ") VALUES (";
		$sql .= "'".$db->escape($alloContactId)."',";
		$sql .= " ".($fkContact ? (int) $fkContact : 0).",";
		$sql .= " ".($fkSoc ? (int) $fkSoc : 0).",";
		$sql .= " '".$db->escape($phoneNumber)."',";
		$sql .= " '".$db->escape($name)."',";
		$sql .= " '".$db->idate($now)."',";
		$sql .= " ".((int) $conf->entity);
		$sql .= ")";

		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog('DolicraftAllo webhook CONTACT_CREATED: SQL error '.$db->lasterror(), LOG_ERR);
			return;
		}

		dol_syslog('DolicraftAllo webhook CONTACT_CREATED: mapped Allo contact '.$alloContactId.' to Dolibarr contact='.$fkContact.' soc='.$fkSoc, LOG_INFO);
	} else {
		dol_syslog('DolicraftAllo webhook CONTACT_CREATED: no matching Dolibarr contact for '.$phoneNumber, LOG_INFO);
	}
}

/**
 * Handle CONTACT_UPDATED event
 *
 * @param DoliDB $db        Database handler
 * @param array  $data      Webhook data
 * @param int    $now       Current timestamp
 * @return void
 */
function handleContactUpdated($db, $data, $now)
{
	global $conf;

	$alloContactId = isset($data['contact_id']) ? $data['contact_id'] : '';
	$phoneNumber   = isset($data['phone_number']) ? trim($data['phone_number']) : '';
	$name          = isset($data['name']) ? $data['name'] : '';
	$email         = isset($data['email']) ? $data['email'] : '';

	if (empty($alloContactId)) {
		dol_syslog('DolicraftAllo webhook CONTACT_UPDATED: no contact_id', LOG_WARNING);
		return;
	}

	// Check if mapping already exists
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."dolicraftallo_contact_map";
	$sql .= " WHERE allo_contact_id = '".$db->escape($alloContactId)."'";
	$sql .= " AND entity = ".((int) $conf->entity);

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('DolicraftAllo webhook CONTACT_UPDATED: SQL error '.$db->lasterror(), LOG_ERR);
		return;
	}

	// Re-match phone number in case it changed
	$fkContact = findContactByPhone($db, $phoneNumber);
	$fkSoc     = findSocieteByPhone($db, $phoneNumber);

	if ($db->num_rows($resql) > 0) {
		// Update existing mapping
		$sql = "UPDATE ".MAIN_DB_PREFIX."dolicraftallo_contact_map SET";
		$sql .= " phone_number = '".$db->escape($phoneNumber)."',";
		$sql .= " allo_name = '".$db->escape($name)."',";
		$sql .= " fk_socpeople = ".($fkContact ? (int) $fkContact : 0).",";
		$sql .= " fk_soc = ".($fkSoc ? (int) $fkSoc : 0).",";
		$sql .= " last_sync = '".$db->idate($now)."'";
		$sql .= " WHERE allo_contact_id = '".$db->escape($alloContactId)."'";
		$sql .= " AND entity = ".((int) $conf->entity);

		$resql2 = $db->query($sql);
		if (!$resql2) {
			dol_syslog('DolicraftAllo webhook CONTACT_UPDATED: SQL error on update '.$db->lasterror(), LOG_ERR);
			return;
		}

		dol_syslog('DolicraftAllo webhook CONTACT_UPDATED: updated mapping for Allo contact '.$alloContactId, LOG_INFO);
	} else {
		// No existing mapping - create one if we have a match
		if ($fkContact || $fkSoc) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolicraftallo_contact_map (";
			$sql .= "allo_contact_id, fk_socpeople, fk_soc, phone_number, allo_name,";
			$sql .= " datec, entity";
			$sql .= ") VALUES (";
			$sql .= "'".$db->escape($alloContactId)."',";
			$sql .= " ".($fkContact ? (int) $fkContact : 0).",";
			$sql .= " ".($fkSoc ? (int) $fkSoc : 0).",";
			$sql .= " '".$db->escape($phoneNumber)."',";
			$sql .= " '".$db->escape($name)."',";
			$sql .= " '".$db->idate($now)."',";
			$sql .= " ".((int) $conf->entity);
			$sql .= ")";

			$resql2 = $db->query($sql);
			if (!$resql2) {
				dol_syslog('DolicraftAllo webhook CONTACT_UPDATED: SQL error on insert '.$db->lasterror(), LOG_ERR);
				return;
			}

			dol_syslog('DolicraftAllo webhook CONTACT_UPDATED: created new mapping for Allo contact '.$alloContactId, LOG_INFO);
		} else {
			dol_syslog('DolicraftAllo webhook CONTACT_UPDATED: no matching Dolibarr contact for '.$phoneNumber, LOG_INFO);
		}
	}
}


/* --------------------------------------------------------------------------
 * Helper functions
 * -------------------------------------------------------------------------- */

/**
 * Find a Dolibarr contact (socpeople) by phone number
 * Searches phone_pro, phone_perso, phone_mobile
 *
 * @param DoliDB $db           Database handler
 * @param string $phoneNumber  Phone number to search
 * @return int|null            Contact rowid or null
 */
function findContactByPhone($db, $phoneNumber)
{
	if (empty($phoneNumber)) {
		return null;
	}

	$cleanNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);

	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."socpeople";
	$sql .= " WHERE (phone_pro = '".$db->escape($cleanNumber)."'";
	$sql .= " OR phone_perso = '".$db->escape($cleanNumber)."'";
	$sql .= " OR phone_mobile = '".$db->escape($cleanNumber)."'";
	$sql .= " OR phone_pro = '".$db->escape($phoneNumber)."'";
	$sql .= " OR phone_perso = '".$db->escape($phoneNumber)."'";
	$sql .= " OR phone_mobile = '".$db->escape($phoneNumber)."')";
	$sql .= " AND statut = 1";
	$sql .= " LIMIT 1";

	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		$obj = $db->fetch_object($resql);
		return (int) $obj->rowid;
	}

	return null;
}

/**
 * Find a Dolibarr company (societe) by phone number
 *
 * @param DoliDB $db           Database handler
 * @param string $phoneNumber  Phone number to search
 * @return int|null            Societe rowid or null
 */
function findSocieteByPhone($db, $phoneNumber)
{
	if (empty($phoneNumber)) {
		return null;
	}

	$cleanNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);

	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe";
	$sql .= " WHERE (phone = '".$db->escape($cleanNumber)."'";
	$sql .= " OR phone = '".$db->escape($phoneNumber)."')";
	$sql .= " AND status = 1";
	$sql .= " LIMIT 1";

	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		$obj = $db->fetch_object($resql);
		return (int) $obj->rowid;
	}

	return null;
}
