<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/actions_dolicraftallo.class.php
 * \ingroup dolicraftallo
 * \brief   Hook actions for DolicraftAllo module
 */

/**
 * Class ActionsDolicraftallo
 *
 * Hook class to add Allo buttons and call/SMS history on thirdparty and contact cards
 */
class ActionsDolicraftallo
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var string[] Array of error messages
	 */
	public $errors = array();

	/**
	 * @var string[] Array of results
	 */
	public $results = array();

	/**
	 * @var string Redefine output
	 */
	public $resprints = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add "Send SMS" and "Call" action buttons on thirdparty and contact cards
	 *
	 * @param array  $parameters Hook parameters
	 * @param object $object     Current object
	 * @param string $action     Current action
	 * @return int 0=OK, <0=KO
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;

		$contexts = explode(':', $parameters['context']);

		$allowedContexts = array('thirdpartycard', 'contactcard', 'invoicecard');
		$contextFound = false;
		foreach ($allowedContexts as $ctx) {
			if (in_array($ctx, $contexts)) {
				$contextFound = true;
				break;
			}
		}
		if (!$contextFound) {
			return 0;
		}

		$langs->load('dolicraftallo@dolicraftallo');

		// Get the phone number from the object
		$phoneNumber = '';
		$displayName = '';
		if (in_array('invoicecard', $contexts)) {
			// For invoices, get thirdparty phone
			if (is_object($object->thirdparty) && !empty($object->thirdparty->phone)) {
				$phoneNumber = $object->thirdparty->phone;
				$displayName = $object->thirdparty->name;
			} elseif (!empty($object->socid)) {
				// Load thirdparty if not loaded
				require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
				$soc = new Societe($this->db);
				if ($soc->fetch($object->socid) > 0) {
					$phoneNumber = $soc->phone;
					$displayName = $soc->name;
				}
			}
		} else {
			if (!empty($object->phone)) {
				$phoneNumber = $object->phone;
			} elseif (!empty($object->phone_mobile)) {
				$phoneNumber = $object->phone_mobile;
			} elseif (!empty($object->phone_pro)) {
				$phoneNumber = $object->phone_pro;
			}
			// Build display name for context
			if (in_array('thirdpartycard', $contexts)) {
				$displayName = !empty($object->name) ? $object->name : '';
			} elseif (in_array('contactcard', $contexts)) {
				$displayName = trim((!empty($object->firstname) ? $object->firstname : '').' '.(!empty($object->lastname) ? $object->lastname : ''));
			}
		}

		if (empty($phoneNumber)) {
			return 0;
		}

		$phoneNumberEnc = urlencode($phoneNumber);

		// Determine object type
		if (in_array('thirdpartycard', $contexts)) {
			$objectType = 'societe';
		} elseif (in_array('contactcard', $contexts)) {
			$objectType = 'contact';
		} elseif (in_array('invoicecard', $contexts)) {
			$objectType = 'facture';
		} else {
			$objectType = '';
		}

		// Send SMS button (opens inline modal)
		if ($user->hasRight('dolicraftallo', 'send_sms') && getDolGlobalString('DOLICRAFTALLO_SMS_ENABLED')) {
			print '<a class="butAction" href="javascript:void(0);" onclick="dolicraftalloOpenSmsModal();">';
			print '<span class="fas fa-comment-dots pictofixedwidth"></span>'.$langs->trans('SendSMS');
			print '</a>';

			// "Relance SMS" for invoices (pre-fills template)
			if (in_array('invoicecard', $contexts)) {
				print '<a class="butAction" href="javascript:void(0);" onclick="dolicraftalloOpenSmsModal(true);">';
				print '<span class="fas fa-redo pictofixedwidth"></span>'.$langs->trans('RelanceSMS');
				print '</a>';
			}
		}

		// Call button
		if ($user->hasRight('dolicraftallo', 'view')) {
			print '<a class="butAction" href="tel:'.dol_escape_htmltag($phoneNumber).'" title="'.dol_escape_htmltag($langs->trans('Call').' '.$phoneNumber).'">';
			print '<span class="fas fa-phone pictofixedwidth"></span>'.$langs->trans('Call');
			print '</a>';
		}

		// Load Allo numbers for the dropdown
		dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');
		$alloNumbers = dolicraftalloGetNumbers($this->db, $user->id);
		$ajaxUrl = dol_buildpath('/dolicraftallo/ajax/allo.php', 1);

		// Load SMS templates
		$smsTemplates = array();
		$sqlTpl = "SELECT rowid, label, content, context FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_templates";
		$sqlTpl .= " WHERE entity = ".((int) $conf->entity)." AND active = 1";
		$sqlTpl .= " AND (context = '' OR context = 'all' OR context = '".$this->db->escape($objectType)."')";
		$sqlTpl .= " ORDER BY position ASC, label ASC";
		$resqlTpl = $this->db->query($sqlTpl);
		if ($resqlTpl) {
			while ($objTpl = $this->db->fetch_object($resqlTpl)) {
				$smsTemplates[] = $objTpl;
			}
		}

		// Build invoice template if applicable
		$smsTemplate = '';
		if ($objectType == 'facture' && is_object($object)) {
			$ref = $object->ref;
			$amount = price($object->total_ttc, 0, $langs, 1, -1, -1, $conf->currency);
			$dueDate = !empty($object->date_lim_reglement) ? dol_print_date($object->date_lim_reglement, 'day') : '';
			$smsTemplate = 'Bonjour, votre facture '.$ref.' d\'un montant de '.$amount.' arrive a echeance'.($dueDate ? ' le '.$dueDate : '').'. Merci de proceder au reglement.';
		}

		// Inline SMS Modal
		print '
		<div id="dolicraftallo-sms-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:10000;animation:none;">
			<div style="position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);backdrop-filter:blur(2px);" onclick="dolicraftalloCloseSmsModal();"></div>
			<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:12px;padding:0;width:480px;max-width:92vw;max-height:85vh;overflow-y:auto;box-shadow:0 25px 80px rgba(0,0,0,0.25);">

				<!-- Header -->
				<div style="padding:16px 24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
					<div style="display:flex;align-items:center;gap:10px;">
						<span style="width:36px;height:36px;border-radius:8px;background:#dbeafe;display:flex;align-items:center;justify-content:center;"><span class="fas fa-comment-dots" style="color:#3b82f6;font-size:16px;"></span></span>
						<div>
							<div style="font-weight:700;font-size:1em;color:#1f2937;">'.$langs->trans('SendSMS').'</div>
							<div style="font-size:0.78em;color:#9ca3af;">'.dol_escape_htmltag($displayName).'</div>
						</div>
					</div>
					<span onclick="dolicraftalloCloseSmsModal();" style="cursor:pointer;width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#9ca3af;transition:all 0.15s;" onmouseover="this.style.background=\'#f3f4f6\';this.style.color=\'#374151\';" onmouseout="this.style.background=\'transparent\';this.style.color=\'#9ca3af\';">
						<span class="fas fa-times" style="font-size:14px;"></span>
					</span>
				</div>

				<!-- Destinataire -->
				<div style="padding:16px 24px 0;">
					<div style="display:flex;align-items:center;padding:10px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;gap:10px;">
						<span class="fas fa-user" style="color:#9ca3af;font-size:13px;"></span>
						<input type="text" id="dolicraftallo-sms-to" value="'.dol_escape_htmltag($phoneNumber).'" readonly style="border:none;background:transparent;font-size:0.95em;font-weight:600;color:#1f2937;flex:1;outline:none;">
						<span style="font-size:0.8em;color:#6b7280;white-space:nowrap;">'.dol_escape_htmltag($displayName).'</span>
					</div>
				</div>

				<!-- Expediteur -->
				<div style="padding:12px 24px 0;">
					<label style="font-size:0.78em;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">'.$langs->trans('From').'</label>
					<select id="dolicraftallo-sms-from" class="flat" style="width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9em;background:#fff;">';

		if (!empty($alloNumbers)) {
			foreach ($alloNumbers as $num) {
				$numLabel = !empty($num->label) ? $num->label.' ('.$num->allo_number.')' : $num->allo_number;
				$selected = $num->is_default ? ' selected' : '';
				print '<option value="'.dol_escape_htmltag($num->allo_number).'"'.$selected.'>'.dol_escape_htmltag($numLabel).'</option>';
			}
		} else {
			print '<option value="">'.$langs->trans('NoNumberConfigured').'</option>';
		}

		print '</select>
				</div>';

		// Template selector (custom templates from DB or fallback to invoice template)
		if (!empty($smsTemplates)) {
			// Prepare JS variable substitution values
			$jsDisplayName = dol_escape_js($displayName);
			$jsPhone = dol_escape_js($phoneNumber);
			$jsMyCompany = dol_escape_js($conf->global->MAIN_INFO_SOCIETE_NOM ?? '');
			$jsInvoiceRef = '';
			$jsInvoiceAmount = '';
			$jsInvoiceDueDate = '';
			if ($objectType == 'facture' && is_object($object)) {
				$jsInvoiceRef = dol_escape_js($object->ref);
				$jsInvoiceAmount = dol_escape_js(price($object->total_ttc, 0, $langs, 1, -1, -1, $conf->currency));
				$jsInvoiceDueDate = !empty($object->date_lim_reglement) ? dol_escape_js(dol_print_date($object->date_lim_reglement, 'day')) : '';
			}

			print '
				<div style="padding:12px 24px 0;">
					<label style="font-size:0.78em;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">'.$langs->trans('ChooseTemplate').'</label>
					<select id="dolicraftallo-sms-tpl-select" class="flat" style="width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9em;background:#fff;" onchange="dolicraftalloApplyTemplate(this);">
						<option value="">-- '.$langs->trans('ChooseTemplate').' --</option>';
			foreach ($smsTemplates as $tpl) {
				print '<option value="'.((int) $tpl->rowid).'" data-content="'.dol_escape_htmltag($tpl->content).'">'.dol_escape_htmltag($tpl->label).'</option>';
			}
			print '</select>
				</div>';
		} elseif (!empty($smsTemplate)) {
			// Fallback: hardcoded invoice template if no custom templates
			print '
				<div style="padding:12px 24px 0;">
					<div id="dolicraftallo-sms-template" style="padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;display:flex;align-items:flex-start;gap:10px;cursor:pointer;" onclick="document.getElementById(\'dolicraftallo-sms-msg\').value=document.getElementById(\'dolicraftallo-sms-tpl-text\').textContent;this.style.opacity=\'0.5\';">
						<span class="fas fa-magic" style="color:#16a34a;margin-top:2px;"></span>
						<div>
							<div style="font-size:0.8em;font-weight:600;color:#16a34a;margin-bottom:2px;">'.$langs->trans('UseTemplate').'</div>
							<div id="dolicraftallo-sms-tpl-text" style="font-size:0.82em;color:#4b5563;line-height:1.4;">'.dol_escape_htmltag($smsTemplate).'</div>
						</div>
					</div>
				</div>';
		}

		print '
				<!-- Message -->
				<div style="padding:12px 24px 0;">
					<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
						<label style="font-size:0.78em;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Message</label>
						<span id="dolicraftallo-sms-counter" style="font-size:0.75em;color:#9ca3af;">0 / 1000</span>
					</div>
					<textarea id="dolicraftallo-sms-msg" style="width:100%;height:120px;resize:vertical;border:1px solid #e5e7eb;border-radius:8px;padding:12px;font-size:0.9em;font-family:inherit;outline:none;transition:border-color 0.15s;box-sizing:border-box;" maxlength="1000" placeholder="'.$langs->trans('TypeYourMessage').'..." onfocus="this.style.borderColor=\'#3b82f6\'" onblur="this.style.borderColor=\'#e5e7eb\'" oninput="var c=document.getElementById(\'dolicraftallo-sms-counter\');c.textContent=this.value.length+\' / 1000\';c.style.color=this.value.length>900?\'#ef4444\':\'#9ca3af\';"></textarea>
				</div>

				<!-- Result -->
				<div id="dolicraftallo-sms-result" style="display:none;padding:8px 24px;"></div>

				<!-- Footer -->
				<div style="padding:16px 24px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
					<button type="button" onclick="dolicraftalloCloseSmsModal();" style="padding:8px 20px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#374151;font-size:0.9em;cursor:pointer;transition:all 0.15s;" onmouseover="this.style.background=\'#f3f4f6\'" onmouseout="this.style.background=\'#fff\'">'.$langs->trans('Cancel').'</button>
					<button type="button" id="dolicraftallo-sms-send" onclick="dolicraftalloSendSms();" style="padding:8px 24px;border:none;border-radius:8px;background:#3b82f6;color:#fff;font-size:0.9em;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all 0.15s;" onmouseover="this.style.background=\'#2563eb\'" onmouseout="this.style.background=\'#3b82f6\'">
						<span class="fas fa-paper-plane" style="font-size:12px;"></span> '.$langs->trans('Send').'
					</button>
				</div>
			</div>
		</div>';

		// JS variable values for template substitution
		$jsVarBlock = '';
		if (!empty($smsTemplates)) {
			$jsVarBlock .= 'var _dcaDisplayName = "'.dol_escape_js($displayName).'";';
			$jsVarBlock .= 'var _dcaPhone = "'.dol_escape_js($phoneNumber).'";';
			$jsVarBlock .= 'var _dcaMyCompany = "'.dol_escape_js(getDolGlobalString('MAIN_INFO_SOCIETE_NOM')).'";';
			if ($objectType == 'facture' && is_object($object)) {
				$jsVarBlock .= 'var _dcaInvoiceRef = "'.dol_escape_js($object->ref).'";';
				$jsVarBlock .= 'var _dcaInvoiceAmount = "'.dol_escape_js(price($object->total_ttc, 0, $langs, 1, -1, -1, $conf->currency)).'";';
				$jsVarBlock .= 'var _dcaInvoiceDueDate = "'.(!empty($object->date_lim_reglement) ? dol_escape_js(dol_print_date($object->date_lim_reglement, 'day')) : '').'";';
			} else {
				$jsVarBlock .= 'var _dcaInvoiceRef = "";var _dcaInvoiceAmount = "";var _dcaInvoiceDueDate = "";';
			}
		}

		// JS for modal
		print '<script>
		'.$jsVarBlock.'
		function dolicraftalloApplyTemplate(sel) {
			var opt = sel.options[sel.selectedIndex];
			if (!opt || !opt.getAttribute("data-content")) return;
			var tplContent = opt.getAttribute("data-content");
			tplContent = tplContent.replace(/\{contact_name\}/g, _dcaDisplayName || "");
			tplContent = tplContent.replace(/\{company_name\}/g, _dcaDisplayName || "");
			tplContent = tplContent.replace(/\{phone\}/g, _dcaPhone || "");
			tplContent = tplContent.replace(/\{invoice_ref\}/g, _dcaInvoiceRef || "");
			tplContent = tplContent.replace(/\{invoice_amount\}/g, _dcaInvoiceAmount || "");
			tplContent = tplContent.replace(/\{invoice_due_date\}/g, _dcaInvoiceDueDate || "");
			tplContent = tplContent.replace(/\{my_company\}/g, _dcaMyCompany || "");
			document.getElementById("dolicraftallo-sms-msg").value = tplContent;
			var c = document.getElementById("dolicraftallo-sms-counter");
			if (c) { c.textContent = tplContent.length + " / 1000"; }
		}
		function dolicraftalloOpenSmsModal(useTemplate) {
			document.getElementById("dolicraftallo-sms-modal").style.display = "block";
			document.getElementById("dolicraftallo-sms-result").style.display = "none";
			if (useTemplate) {
				var tpl = document.getElementById("dolicraftallo-sms-tpl-text");
				if (tpl) document.getElementById("dolicraftallo-sms-msg").value = tpl.textContent;
			}
			document.getElementById("dolicraftallo-sms-msg").focus();
		}
		function dolicraftalloCloseSmsModal() {
			document.getElementById("dolicraftallo-sms-modal").style.display = "none";
		}
		function dolicraftalloSendSms() {
			var to = document.getElementById("dolicraftallo-sms-to").value;
			var from = document.getElementById("dolicraftallo-sms-from").value;
			var msg = document.getElementById("dolicraftallo-sms-msg").value.trim();
			var result = document.getElementById("dolicraftallo-sms-result");
			var btn = document.getElementById("dolicraftallo-sms-send");

			if (!msg) { result.innerHTML = "<span style=\"color:#ef4444;\">'.dol_escape_js($langs->trans('MessageRequired')).'</span>"; result.style.display = "block"; return; }
			if (!from) { result.innerHTML = "<span style=\"color:#ef4444;\">'.dol_escape_js($langs->trans('NoSenderNumber')).'</span>"; result.style.display = "block"; return; }

			btn.disabled = true;
			btn.innerHTML = "<span class=\"fas fa-spinner fa-spin\"></span> '.dol_escape_js($langs->trans('Send')).'...";
			result.style.display = "none";

			var xhr = new XMLHttpRequest();
			xhr.open("POST", "'.dol_escape_js($ajaxUrl).'", true);
			xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
			xhr.onload = function() {
				btn.disabled = false;
				btn.innerHTML = "<span class=\"fas fa-paper-plane pictofixedwidth\"></span>'.dol_escape_js($langs->trans('Send')).'";
				try {
					var r = JSON.parse(xhr.responseText);
					if (r.success) {
						result.innerHTML = "<span class=\"badge badge-status4\">'.dol_escape_js($langs->trans('SMSSentSuccess')).'</span>";
						result.style.display = "block";
						document.getElementById("dolicraftallo-sms-msg").value = "";
						setTimeout(function() { dolicraftalloCloseSmsModal(); }, 2000);
					} else {
						result.innerHTML = "<span class=\"badge badge-status8\">" + (r.message || "'.dol_escape_js($langs->trans('Error')).'") + "</span>";
						result.style.display = "block";
					}
				} catch(e) {
					result.innerHTML = "<span class=\"badge badge-status8\">'.dol_escape_js($langs->trans('Error')).'</span>";
					result.style.display = "block";
				}
			};
			xhr.onerror = function() {
				btn.disabled = false;
				btn.innerHTML = "<span class=\"fas fa-paper-plane pictofixedwidth\"></span>'.dol_escape_js($langs->trans('Send')).'";
				result.innerHTML = "<span class=\"badge badge-status8\">'.dol_escape_js($langs->trans('Error')).'</span>";
				result.style.display = "block";
			};
			xhr.send("action=send_sms&token='.newToken().'&to=" + encodeURIComponent(to) + "&from_number=" + encodeURIComponent(from) + "&message=" + encodeURIComponent(msg) + "&related_object_type='.dol_escape_js($objectType).'&related_object_id='.((int) $object->id).'");
		}
		document.addEventListener("keydown", function(e) {
			if (e.key === "Escape") dolicraftalloCloseSmsModal();
		});
		</script>';

		return 0;
	}

	/**
	 * Inject call/SMS history on thirdparty and contact cards (footer area)
	 *
	 * @param array  $parameters Hook parameters
	 * @param object $object     Current object
	 * @param string $action     Current action
	 * @return int 0=OK, <0=KO
	 */
	public function printCommonFooter($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;

		$contexts = explode(':', $parameters['context']);

		if (!in_array('thirdpartycard', $contexts) && !in_array('contactcard', $contexts)) {
			return 0;
		}

		if (!$user->hasRight('dolicraftallo', 'view')) {
			return 0;
		}

		$langs->load('dolicraftallo@dolicraftallo');

		// Determine the filter column and phone-based filter
		$isThirdpartyCard = in_array('thirdpartycard', $contexts);
		if ($isThirdpartyCard) {
			$filterCol = 'fk_soc';
			$filterId = $object->id;
		} else {
			$filterCol = 'fk_socpeople';
			$filterId = $object->id;
		}

		// Build additional phone filter for better matching.
		// IMPORTANT: only on the thirdparty card. On a contact card we must rely
		// solely on the exact fk_socpeople link, otherwise the trailing-wildcard
		// phone match (LIKE '%number') leaks the whole company/journal onto a
		// single contact — especially when the contact shares a number with its
		// company switchboard. The exact link is set at webhook time and is
		// enough to isolate the contact's own calls/SMS here.
		$phoneFilter = '';
		$phoneNumbers = array();
		if ($isThirdpartyCard) {
			if (!empty($object->phone)) {
				$phoneNumbers[] = $object->phone;
			}
			if (!empty($object->phone_mobile)) {
				$phoneNumbers[] = $object->phone_mobile;
			}
			if (!empty($object->phone_pro)) {
				$phoneNumbers[] = $object->phone_pro;
			}
		}
		if (!empty($phoneNumbers)) {
			$phoneClauses = array();
			foreach ($phoneNumbers as $pn) {
				$cleanNum = ltrim(preg_replace('/[^0-9+]/', '', $pn), '+');
				if (!empty($cleanNum)) {
					$phoneClauses[] = "c.from_number LIKE '%".str_replace(array('\\','%','_'), array('\\\\','\\%','\\_'), $this->db->escape($cleanNum))."'";
					$phoneClauses[] = "c.to_number LIKE '%".str_replace(array('\\','%','_'), array('\\\\','\\%','\\_'), $this->db->escape($cleanNum))."'";
				}
			}
			if (!empty($phoneClauses)) {
				$phoneFilter = ' OR ('.implode(' OR ', $phoneClauses).')';
			}
		}

		// Fetch last 10 calls
		$calls = array();
		$sql = "SELECT rowid, from_number, to_number, call_type, call_result, duration_minutes, call_date";
		$sql .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log as c";
		$sql .= " WHERE c.entity = ".((int) $conf->entity);
		$sql .= " AND (c.".$filterCol." = ".((int) $filterId).$phoneFilter.")";
		$sql .= " ORDER BY c.call_date DESC";
		$sql .= " LIMIT 10";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$calls[] = $obj;
			}
			$this->db->free($resql);
		}

		// Fetch last 10 SMS (reuse phone filter with sms alias)
		$smsPhoneFilter = str_replace(array('c.from_number', 'c.to_number'), array('c.from_number', 'c.to_number'), $phoneFilter);
		$smsList = array();
		$sql = "SELECT rowid, from_number, to_number, sms_type, content, status, datec";
		$sql .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_sms_log as c";
		$sql .= " WHERE c.entity = ".((int) $conf->entity);
		$sql .= " AND (c.".$filterCol." = ".((int) $filterId).$smsPhoneFilter.")";
		$sql .= " ORDER BY c.datec DESC";
		$sql .= " LIMIT 10";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$smsList[] = $obj;
			}
			$this->db->free($resql);
		}

		// Output HTML via JavaScript injection - always show panels
		$html = '';

		// Calls panel
		$html .= '<div class="dolicraftallo-panel">';
		$html .= '<h3><i class="fas fa-phone"></i> '.$langs->trans('RecentCalls').'</h3>';
		if (!empty($calls)) {
			$html .= '<table>';
			foreach ($calls as $call) {
				$typeBadge = 'dolicraftallo-badge-'.strtolower($call->call_type);
				$resultBadge = 'dolicraftallo-result-'.strtolower($call->call_result);
				$html .= '<tr>';
				$html .= '<td>'.dol_print_date($this->db->jdate($call->call_date), 'dayhour').'</td>';
				$html .= '<td><span class="dolicraftallo-badge '.$typeBadge.'">'.dol_escape_htmltag($call->call_type).'</span></td>';
				$html .= '<td>'.dol_escape_htmltag($call->from_number).' &rarr; '.dol_escape_htmltag($call->to_number).'</td>';
				$html .= '<td><span class="dolicraftallo-badge '.$resultBadge.'">'.dol_escape_htmltag($call->call_result).'</span></td>';
				$html .= '<td class="dolicraftallo-duration">'.round($call->duration_minutes, 1).' min</td>';
				$html .= '</tr>';
			}
			$html .= '</table>';
		} else {
			$html .= '<p class="opacitymedium">'.$langs->trans('NoRecordFound').'</p>';
		}
		$html .= '</div>';

		// SMS panel
		$html .= '<div class="dolicraftallo-panel">';
		$html .= '<h3><i class="fas fa-comment-dots"></i> '.$langs->trans('RecentSMS').'</h3>';
		if (!empty($smsList)) {
			$html .= '<table>';
			foreach ($smsList as $sms) {
				$statusBadge = 'dolicraftallo-sms-'.strtolower($sms->status);
				$html .= '<tr>';
				$html .= '<td>'.dol_print_date($this->db->jdate($sms->datec), 'dayhour').'</td>';
				$html .= '<td><span class="dolicraftallo-badge dolicraftallo-badge-'.strtolower($sms->sms_type).'">'.dol_escape_htmltag($sms->sms_type).'</span></td>';
				$html .= '<td>'.dol_escape_htmltag(dol_trunc($sms->content, 60)).'</td>';
				$html .= '<td><span class="dolicraftallo-badge '.$statusBadge.'">'.dol_escape_htmltag($sms->status).'</span></td>';
				$html .= '</tr>';
			}
			$html .= '</table>';
		} else {
			$html .= '<p class="opacitymedium">'.$langs->trans('NoRecordFound').'</p>';
		}
		$html .= '</div>';

		// Inject after the main div via JS
		$html = str_replace(array("\r", "\n", "'"), array('', '', "\\'"), $html);
		?>
		<script type="text/javascript">
		jQuery(document).ready(function() {
			var alloHtml = '<?php echo $html; ?>';
			jQuery('#mainbody .fiche').first().append(alloHtml);
		});
		</script>
		<?php

		return 0;
	}
}
