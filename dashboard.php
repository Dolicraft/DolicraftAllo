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
 * \file    dashboard.php
 * \ingroup dolicraftallo
 * \brief   Analytics dashboard page
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
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');

// Load translation files
$langs->loadLangs(array("dolicraftallo@dolicraftallo"));

// Security check
if (!$user->hasRight('dolicraftallo', 'view')) {
	accessforbidden();
}

// ---------------------------------------------------------------------------
// Period selector
// ---------------------------------------------------------------------------
$period = GETPOST('period', 'aZ09');
if (empty($period)) {
	$period = 'last_30_days';
}

$now = dol_now();
$dateStart = '';
$dateEnd = dol_print_date($now, '%Y-%m-%d 23:59:59');

switch ($period) {
	case 'last_7_days':
		$dateStart = dol_print_date($now - (7 * 24 * 3600), '%Y-%m-%d 00:00:00');
		break;
	case 'last_30_days':
		$dateStart = dol_print_date($now - (30 * 24 * 3600), '%Y-%m-%d 00:00:00');
		break;
	case 'current_month':
		$dateStart = dol_print_date($now, '%Y-%m-01 00:00:00');
		break;
	case 'current_year':
		$dateStart = dol_print_date($now, '%Y-01-01 00:00:00');
		break;
	default:
		$dateStart = dol_print_date($now - (30 * 24 * 3600), '%Y-%m-%d 00:00:00');
		$period = 'last_30_days';
		break;
}

$entityFilter = " AND entity = ".((int) $conf->entity);

// ---------------------------------------------------------------------------
// KPI queries
// ---------------------------------------------------------------------------

// Total calls (period)
$sqlTotalCalls = "SELECT COUNT(*) as val FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
$sqlTotalCalls .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
$totalCalls = 0;
$resql = $db->query($sqlTotalCalls);
if ($resql) {
	$obj = $db->fetch_object($resql);
	$totalCalls = (int) $obj->val;
}

// Missed calls
$sqlMissedCalls = "SELECT COUNT(*) as val FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
$sqlMissedCalls .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
$sqlMissedCalls .= " AND call_result IN ('NOANSWER','MISSED','BUSY')";
$missedCalls = 0;
$resql = $db->query($sqlMissedCalls);
if ($resql) {
	$obj = $db->fetch_object($resql);
	$missedCalls = (int) $obj->val;
}

// Average duration
$sqlAvgDuration = "SELECT AVG(duration_minutes) as val FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
$sqlAvgDuration .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
$sqlAvgDuration .= " AND duration_minutes > 0";
$avgDuration = 0;
$resql = $db->query($sqlAvgDuration);
if ($resql) {
	$obj = $db->fetch_object($resql);
	$avgDuration = $obj->val ? round(floatval($obj->val), 1) : 0;
}

// SMS sent
$sqlSmsSent = "SELECT COUNT(*) as val FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_log";
$sqlSmsSent .= " WHERE datec BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
$sqlSmsSent .= " AND sms_type = 'OUTBOUND'";
$smsSent = 0;
$resql = $db->query($sqlSmsSent);
if ($resql) {
	$obj = $db->fetch_object($resql);
	$smsSent = (int) $obj->val;
}

// SMS received
$sqlSmsReceived = "SELECT COUNT(*) as val FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_log";
$sqlSmsReceived .= " WHERE datec BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
$sqlSmsReceived .= " AND sms_type = 'INBOUND'";
$smsReceived = 0;
$resql = $db->query($sqlSmsReceived);
if ($resql) {
	$obj = $db->fetch_object($resql);
	$smsReceived = (int) $obj->val;
}

// Response rate
$answeredCalls = $totalCalls - $missedCalls;
$responseRate = ($totalCalls > 0) ? round(($answeredCalls / $totalCalls) * 100, 1) : 0;

// Active contacts (distinct phone numbers in period)
$sqlActiveContacts = "SELECT COUNT(DISTINCT phone) as val FROM (";
$sqlActiveContacts .= " SELECT from_number as phone FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
$sqlActiveContacts .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
$sqlActiveContacts .= " UNION";
$sqlActiveContacts .= " SELECT to_number as phone FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
$sqlActiveContacts .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
$sqlActiveContacts .= " UNION";
$sqlActiveContacts .= " SELECT from_number as phone FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_log";
$sqlActiveContacts .= " WHERE datec BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
$sqlActiveContacts .= " UNION";
$sqlActiveContacts .= " SELECT to_number as phone FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_log";
$sqlActiveContacts .= " WHERE datec BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
$sqlActiveContacts .= ") as phones";
$activeContacts = 0;
$resql = $db->query($sqlActiveContacts);
if ($resql) {
	$obj = $db->fetch_object($resql);
	$activeContacts = (int) $obj->val;
}

// Recordings available
$sqlRecordings = "SELECT COUNT(*) as val FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
$sqlRecordings .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
$sqlRecordings .= " AND recording_url IS NOT NULL AND recording_url != ''";
$recordings = 0;
$resql = $db->query($sqlRecordings);
if ($resql) {
	$obj = $db->fetch_object($resql);
	$recordings = (int) $obj->val;
}

// ---------------------------------------------------------------------------
// Call volume chart data
// ---------------------------------------------------------------------------
$chartData = array();
if ($period == 'current_year') {
	// Group by week
	$sqlChart = "SELECT YEARWEEK(call_date, 1) as period_key,";
	$sqlChart .= " MIN(DATE(call_date)) as period_label,";
	$sqlChart .= " SUM(CASE WHEN call_result NOT IN ('NOANSWER','MISSED','BUSY') THEN 1 ELSE 0 END) as answered,";
	$sqlChart .= " SUM(CASE WHEN call_result IN ('NOANSWER','MISSED','BUSY') THEN 1 ELSE 0 END) as missed";
	$sqlChart .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
	$sqlChart .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
	$sqlChart .= " GROUP BY period_key ORDER BY period_key ASC";
} else {
	// Group by day
	$sqlChart = "SELECT DATE(call_date) as period_key,";
	$sqlChart .= " DATE(call_date) as period_label,";
	$sqlChart .= " SUM(CASE WHEN call_result NOT IN ('NOANSWER','MISSED','BUSY') THEN 1 ELSE 0 END) as answered,";
	$sqlChart .= " SUM(CASE WHEN call_result IN ('NOANSWER','MISSED','BUSY') THEN 1 ELSE 0 END) as missed";
	$sqlChart .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
	$sqlChart .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
	$sqlChart .= " GROUP BY period_key ORDER BY period_key ASC";
}

$maxChartVal = 1;
$resql = $db->query($sqlChart);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$chartData[] = $obj;
		$total = (int) $obj->answered + (int) $obj->missed;
		if ($total > $maxChartVal) {
			$maxChartVal = $total;
		}
	}
	$db->free($resql);
}

// ---------------------------------------------------------------------------
// Top 10 contacts by call volume
// ---------------------------------------------------------------------------
$alloNumbers = dolicraftalloGetNumbers($db);
$alloNumbersList = array();
foreach ($alloNumbers as $numObj) {
	$alloNumbersList[] = "'".$db->escape($numObj->allo_number)."'";
}

$topContacts = array();
if (!empty($alloNumbersList)) {
	$alloNumbersIn = implode(',', $alloNumbersList);

	// Determine "other" number
	$otherExpr = "CASE WHEN from_number IN (".$alloNumbersIn.") THEN to_number ELSE from_number END";

	$sqlTop = "SELECT ".$otherExpr." as contact_phone,";
	$sqlTop .= " COUNT(*) as nb_calls,";
	$sqlTop .= " SUM(duration_minutes) as total_duration,";
	$sqlTop .= " MAX(call_date) as last_call";
	$sqlTop .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
	$sqlTop .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
	$sqlTop .= " GROUP BY contact_phone";
	$sqlTop .= " ORDER BY nb_calls DESC";
	$sqlTop .= $db->plimit(10);

	$resql = $db->query($sqlTop);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$topContacts[] = $obj;
		}
		$db->free($resql);
	}
}

// Resolve contact names for top contacts
$topContactNames = array();
foreach ($topContacts as $tc) {
	$phone = $tc->contact_phone;
	$cleanPhone = ltrim($phone, '+');

	$sqlsp = "SELECT sp.rowid, sp.firstname, sp.lastname, sp.fk_soc FROM ".MAIN_DB_PREFIX."socpeople as sp";
	$sqlsp .= " WHERE sp.entity IN (".getEntity('socpeople').")";
	$sqlsp .= " AND (sp.phone = '".$db->escape($phone)."'";
	$sqlsp .= " OR sp.phone_mobile = '".$db->escape($phone)."'";
	$sqlsp .= " OR sp.phone LIKE '%".$db->escape($cleanPhone)."'";
	$sqlsp .= " OR sp.phone_mobile LIKE '%".$db->escape($cleanPhone)."')";
	$sqlsp .= $db->plimit(1);

	$resqlsp = $db->query($sqlsp);
	if ($resqlsp && $db->num_rows($resqlsp) > 0) {
		$objsp = $db->fetch_object($resqlsp);
		$topContactNames[$phone] = array(
			'id' => (int) $objsp->rowid,
			'name' => trim($objsp->firstname.' '.$objsp->lastname),
			'fk_soc' => (int) $objsp->fk_soc,
		);
	}
}

// ---------------------------------------------------------------------------
// Calls by Allo number
// ---------------------------------------------------------------------------
$alloStats = array();
foreach ($alloNumbers as $numObj) {
	$num = $numObj->allo_number;

	// Inbound calls
	$sqlIn = "SELECT COUNT(*) as val FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
	$sqlIn .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
	$sqlIn .= " AND to_number = '".$db->escape($num)."' AND call_type = 'INBOUND'";
	$inCalls = 0;
	$r = $db->query($sqlIn);
	if ($r) { $o = $db->fetch_object($r); $inCalls = (int) $o->val; }

	// Outbound calls
	$sqlOut = "SELECT COUNT(*) as val FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
	$sqlOut .= " WHERE call_date BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
	$sqlOut .= " AND from_number = '".$db->escape($num)."' AND call_type = 'OUTBOUND'";
	$outCalls = 0;
	$r = $db->query($sqlOut);
	if ($r) { $o = $db->fetch_object($r); $outCalls = (int) $o->val; }

	// SMS sent
	$sqlSmsSentNum = "SELECT COUNT(*) as val FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_log";
	$sqlSmsSentNum .= " WHERE datec BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
	$sqlSmsSentNum .= " AND from_number = '".$db->escape($num)."' AND sms_type = 'OUTBOUND'";
	$smsSentNum = 0;
	$r = $db->query($sqlSmsSentNum);
	if ($r) { $o = $db->fetch_object($r); $smsSentNum = (int) $o->val; }

	// SMS received
	$sqlSmsRecNum = "SELECT COUNT(*) as val FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_log";
	$sqlSmsRecNum .= " WHERE datec BETWEEN '".$db->escape($dateStart)."' AND '".$db->escape($dateEnd)."'".$entityFilter;
	$sqlSmsRecNum .= " AND to_number = '".$db->escape($num)."' AND sms_type = 'INBOUND'";
	$smsRecNum = 0;
	$r = $db->query($sqlSmsRecNum);
	if ($r) { $o = $db->fetch_object($r); $smsRecNum = (int) $o->val; }

	$alloStats[] = array(
		'number' => $num,
		'label' => $numObj->label,
		'in_calls' => $inCalls,
		'out_calls' => $outCalls,
		'sms_sent' => $smsSentNum,
		'sms_received' => $smsRecNum,
	);
}

// ---------------------------------------------------------------------------
// View
// ---------------------------------------------------------------------------
$form = new Form($db);

llxHeader('', $langs->trans("Dashboard"), '', '', 0, 0, array(), array(), '', 'mod-dolicraftallo page-dashboard');

print load_fiche_titre($langs->trans("DolicraftAllo"), '', 'phone');

$head = dolicraftalloAdminPrepareHead();
print dol_get_fiche_head($head, 'dashboard', $langs->trans("DolicraftAllo"), -1, 'phone');

// ===================================================================
// Period selector
// ===================================================================
print '<div class="fichecenter" style="margin-bottom: 15px;">';
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<span class="opacitymedium" style="margin-right: 8px;"><i class="fas fa-calendar-alt pictofixedwidth"></i>'.$langs->trans("Period").' :</span>';
print '<select name="period" class="flat" onchange="this.form.submit();">';
$periods = array(
	'last_7_days' => $langs->trans("Last7Days"),
	'last_30_days' => $langs->trans("Last30Days"),
	'current_month' => $langs->trans("CurrentMonth"),
	'current_year' => $langs->trans("CurrentYear"),
);
foreach ($periods as $key => $label) {
	$selected = ($period == $key) ? ' selected' : '';
	print '<option value="'.$key.'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
}
print '</select>';
print '<noscript><input type="submit" class="button smallpaddingimp" value="OK"></noscript>';
print '</form>';
print '</div>';

// ===================================================================
// KPI CARDS - Row 1
// ===================================================================
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="4"><i class="fas fa-chart-line pictofixedwidth"></i>'.$langs->trans("KeyIndicators").'</td></tr>';

// Row 1
print '<tr class="oddeven">';

// Total calls
print '<td class="center" style="width: 25%;">';
print '<div style="font-size: 2em; font-weight: bold; color: #3b82f6;">'.$totalCalls.'</div>';
print '<div class="opacitymedium">'.$langs->trans("TotalCalls").'</div>';
print '</td>';

// Missed calls
print '<td class="center" style="width: 25%;">';
print '<div style="font-size: 2em; font-weight: bold; color: #ef4444;">'.$missedCalls.'</div>';
print '<div class="opacitymedium">'.$langs->trans("MissedCalls").'</div>';
print '</td>';

// Average duration
print '<td class="center" style="width: 25%;">';
$avgMin = floor($avgDuration);
$avgSec = round(($avgDuration - $avgMin) * 60);
$avgDisplay = $avgMin.'m'.sprintf('%02d', $avgSec).'s';
print '<div style="font-size: 2em; font-weight: bold; color: #22c55e;">'.$avgDisplay.'</div>';
print '<div class="opacitymedium">'.$langs->trans("AvgDuration").'</div>';
print '</td>';

// SMS sent
print '<td class="center" style="width: 25%;">';
print '<div style="font-size: 2em; font-weight: bold; color: #8b5cf6;">'.$smsSent.'</div>';
print '<div class="opacitymedium">'.$langs->trans("SMSSent").'</div>';
print '</td>';

print '</tr>';

// Row 2
print '<tr class="oddeven">';

// SMS received
print '<td class="center">';
print '<div style="font-size: 2em; font-weight: bold; color: #06b6d4;">'.$smsReceived.'</div>';
print '<div class="opacitymedium">'.$langs->trans("SMSReceived").'</div>';
print '</td>';

// Response rate
print '<td class="center">';
$rateColor = ($responseRate >= 80) ? '#22c55e' : (($responseRate >= 50) ? '#f59e0b' : '#ef4444');
print '<div style="font-size: 2em; font-weight: bold; color: '.$rateColor.';">'.$responseRate.'%</div>';
print '<div class="opacitymedium">'.$langs->trans("ResponseRate").'</div>';
print '</td>';

// Active contacts
print '<td class="center">';
print '<div style="font-size: 2em; font-weight: bold; color: #6366f1;">'.$activeContacts.'</div>';
print '<div class="opacitymedium">'.$langs->trans("ActiveContacts").'</div>';
print '</td>';

// Recordings
print '<td class="center">';
print '<div style="font-size: 2em; font-weight: bold; color: #ec4899;">'.$recordings.'</div>';
print '<div class="opacitymedium">'.$langs->trans("RecordingsAvailable").'</div>';
print '</td>';

print '</tr>';
print '</table>';

// ===================================================================
// CALL VOLUME CHART (CSS bar chart)
// ===================================================================
print '<br>';
print '<table class="noborder centpercent">';
$chartTitle = ($period == 'current_year') ? $langs->trans("CallVolumeByWeek") : $langs->trans("CallVolumeByDay");
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Date").'</td>';
print '<td>'.$chartTitle.'</td>';
print '<td class="right" style="width: 80px;">'.$langs->trans("Count").'</td>';
print '</tr>';

if (empty($chartData)) {
	print '<tr class="oddeven"><td colspan="3" class="center opacitymedium">'.$langs->trans("NoDataForPeriod").'</td></tr>';
} else {
	foreach ($chartData as $row) {
		$answered = (int) $row->answered;
		$missed = (int) $row->missed;
		$total = $answered + $missed;
		$pctAnswered = ($maxChartVal > 0) ? round(($answered / $maxChartVal) * 100) : 0;
		$pctMissed = ($maxChartVal > 0) ? round(($missed / $maxChartVal) * 100) : 0;

		$dateLabel = dol_print_date($db->jdate($row->period_label), 'day');

		print '<tr class="oddeven">';
		print '<td class="nowraponall" style="width: 120px;">'.dol_escape_htmltag($dateLabel).'</td>';
		print '<td>';
		print '<div style="display: flex; align-items: center; gap: 2px; height: 20px;">';
		if ($pctAnswered > 0) {
			print '<div style="width: '.$pctAnswered.'%; background: #22c55e; height: 100%; border-radius: 3px 0 0 3px; min-width: 2px;" title="'.$langs->trans("Answered").': '.$answered.'"></div>';
		}
		if ($pctMissed > 0) {
			print '<div style="width: '.$pctMissed.'%; background: #ef4444; height: 100%; border-radius: 0 3px 3px 0; min-width: 2px;" title="'.$langs->trans("Missed").': '.$missed.'"></div>';
		}
		print '</div>';
		print '</td>';
		print '<td class="right nowraponall">';
		print '<span style="color: #22c55e; font-weight: bold;">'.$answered.'</span>';
		print ' / ';
		print '<span style="color: #ef4444; font-weight: bold;">'.$missed.'</span>';
		print '</td>';
		print '</tr>';
	}
}

// Legend
print '<tr class="oddeven">';
print '<td colspan="3" class="center" style="padding: 6px;">';
print '<span style="display: inline-block; width: 12px; height: 12px; background: #22c55e; border-radius: 2px; vertical-align: middle; margin-right: 4px;"></span>';
print '<span class="opacitymedium" style="margin-right: 16px;">'.$langs->trans("Answered").'</span>';
print '<span style="display: inline-block; width: 12px; height: 12px; background: #ef4444; border-radius: 2px; vertical-align: middle; margin-right: 4px;"></span>';
print '<span class="opacitymedium">'.$langs->trans("Missed").'</span>';
print '</td>';
print '</tr>';

print '</table>';

// ===================================================================
// TOP 10 CONTACTS BY CALL VOLUME
// ===================================================================
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td><i class="fas fa-trophy pictofixedwidth"></i>'.$langs->trans("TopContactsByCallVolume").'</td>';
print '<td>'.$langs->trans("PhoneNumber").'</td>';
print '<td class="right">'.$langs->trans("NbCalls").'</td>';
print '<td class="right">'.$langs->trans("TotalDuration").'</td>';
print '<td class="right">'.$langs->trans("LastCall").'</td>';
print '</tr>';

if (empty($topContacts)) {
	print '<tr class="oddeven"><td colspan="5" class="center opacitymedium">'.$langs->trans("NoDataForPeriod").'</td></tr>';
} else {
	$rank = 0;
	foreach ($topContacts as $tc) {
		$rank++;
		$phone = $tc->contact_phone;

		print '<tr class="oddeven">';

		// Contact name
		print '<td>';
		print '<span class="opacitymedium" style="margin-right: 6px;">#'.$rank.'</span>';
		if (isset($topContactNames[$phone])) {
			$cInfo = $topContactNames[$phone];
			$contactObj = new Contact($db);
			$contactObj->id = $cInfo['id'];
			$contactObj->lastname = $cInfo['name'];
			$contactObj->firstname = '';
			print $contactObj->getNomUrl(1);
		} else {
			print '<strong>'.dol_escape_htmltag($phone).'</strong>';
		}
		print '</td>';

		// Phone
		print '<td>'.dol_escape_htmltag($phone).'</td>';

		// Nb calls
		print '<td class="right"><span class="badge badge-status4">'.(int) $tc->nb_calls.'</span></td>';

		// Total duration
		print '<td class="right">';
		$dur = floatval($tc->total_duration);
		if ($dur > 0) {
			$dMin = floor($dur);
			$dSec = round(($dur - $dMin) * 60);
			print $dMin.'m'.sprintf('%02d', $dSec).'s';
		} else {
			print '-';
		}
		print '</td>';

		// Last call
		print '<td class="right nowraponall">'.dol_print_date($db->jdate($tc->last_call), 'dayhour').'</td>';

		print '</tr>';
	}
}

print '</table>';

// ===================================================================
// CALLS BY ALLO NUMBER
// ===================================================================
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td><i class="fas fa-phone pictofixedwidth"></i>'.$langs->trans("AlloNumber").'</td>';
print '<td>'.$langs->trans("Label").'</td>';
print '<td class="right">'.$langs->trans("InboundCalls").'</td>';
print '<td class="right">'.$langs->trans("OutboundCalls").'</td>';
print '<td class="right">'.$langs->trans("SMSSent").'</td>';
print '<td class="right">'.$langs->trans("SMSReceived").'</td>';
print '</tr>';

if (empty($alloStats)) {
	print '<tr class="oddeven"><td colspan="6" class="center opacitymedium">'.$langs->trans("NoNumberConfigured").'</td></tr>';
} else {
	foreach ($alloStats as $stat) {
		print '<tr class="oddeven">';
		print '<td><strong>'.dol_escape_htmltag($stat['number']).'</strong></td>';
		print '<td>'.dol_escape_htmltag($stat['label']).'</td>';
		print '<td class="right">';
		if ($stat['in_calls'] > 0) {
			print '<span class="badge badge-status4">'.$stat['in_calls'].'</span>';
		} else {
			print '<span class="opacitymedium">0</span>';
		}
		print '</td>';
		print '<td class="right">';
		if ($stat['out_calls'] > 0) {
			print '<span class="badge badge-status1">'.$stat['out_calls'].'</span>';
		} else {
			print '<span class="opacitymedium">0</span>';
		}
		print '</td>';
		print '<td class="right">';
		if ($stat['sms_sent'] > 0) {
			print '<span class="badge badge-status6">'.$stat['sms_sent'].'</span>';
		} else {
			print '<span class="opacitymedium">0</span>';
		}
		print '</td>';
		print '<td class="right">';
		if ($stat['sms_received'] > 0) {
			print '<span class="badge badge-status4">'.$stat['sms_received'].'</span>';
		} else {
			print '<span class="opacitymedium">0</span>';
		}
		print '</td>';
		print '</tr>';
	}
}

print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
