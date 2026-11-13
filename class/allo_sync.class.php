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
 * \file    dolicraftallo/class/allo_sync.class.php
 * \ingroup dolicraftallo
 * \brief   Synchronization of Allo calls and contacts (shared by ajax/allo.php and the scheduled jobs)
 */

dol_include_once('/dolicraftallo/class/allo_api.class.php');
dol_include_once('/dolicraftallo/lib/dolicraftallo.lib.php');

/**
 * Class DolicraftAlloSync
 */
class DolicraftAlloSync
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Last error message
	 */
	public $error = '';

	/**
	 * @var string[] Error messages (read by the Dolibarr cron module)
	 */
	public $errors = array();

	/**
	 * @var string Output of the scheduled job (displayed in the cron job list)
	 */
	public $output = '';

	/**
	 * @var DolicraftAlloAPI
	 */
	private $api;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->api = new DolicraftAlloAPI($db);
	}

	/**
	 * Find the Dolibarr contact / thirdparty matching a phone number
	 *
	 * Contacts (phone, mobile) are searched first, then thirdparties.
	 *
	 * @param  string $number Phone number (E.164)
	 * @return array          array('fk_soc' => int, 'fk_socpeople' => int), 0 when not found
	 */
	private function matchPhone($number)
	{
		$db = $this->db;
		$match = array('fk_soc' => 0, 'fk_socpeople' => 0);

		if (empty($number)) {
			return $match;
		}

		// Also try without +
		$cleanNumber = ltrim($number, '+');

		// Search in socpeople (contacts)
		$sqlsp = "SELECT sp.rowid, sp.fk_soc FROM ".MAIN_DB_PREFIX."socpeople as sp";
		$sqlsp .= " WHERE sp.entity IN (".getEntity('socpeople').")";
		$sqlsp .= " AND (sp.phone = '".$db->escape($number)."'";
		$sqlsp .= " OR sp.phone_mobile = '".$db->escape($number)."'";
		$sqlsp .= " OR sp.phone LIKE '%".dolicraftalloEscapeLike($db->escape($cleanNumber))."'";
		$sqlsp .= " OR sp.phone_mobile LIKE '%".dolicraftalloEscapeLike($db->escape($cleanNumber))."')";
		$sqlsp .= $db->plimit(1);

		$resqlsp = $db->query($sqlsp);
		if ($resqlsp && $db->num_rows($resqlsp) > 0) {
			$objsp = $db->fetch_object($resqlsp);
			$match['fk_socpeople'] = (int) $objsp->rowid;
			$match['fk_soc'] = (int) $objsp->fk_soc;
		}

		// If no contact found, search in societe
		if ($match['fk_soc'] == 0) {
			$sqlsoc = "SELECT s.rowid FROM ".MAIN_DB_PREFIX."societe as s";
			$sqlsoc .= " WHERE s.entity IN (".getEntity('societe').")";
			$sqlsoc .= " AND (s.phone = '".$db->escape($number)."'";
			$sqlsoc .= " OR s.phone LIKE '%".dolicraftalloEscapeLike($db->escape($cleanNumber))."')";
			$sqlsoc .= $db->plimit(1);

			$resqlsoc = $db->query($sqlsoc);
			if ($resqlsoc && $db->num_rows($resqlsoc) > 0) {
				$objsoc = $db->fetch_object($resqlsoc);
				$match['fk_soc'] = (int) $objsoc->rowid;
			}
		}

		return $match;
	}

	/**
	 * Synchronize the call history of every configured Allo number into llx_dolicraftallo_call_log
	 *
	 * @return array|int array('inserted' => int, 'updated' => int), or -1 on error (see $this->error)
	 */
	public function syncCalls()
	{
		global $conf, $langs;

		$db = $this->db;

		// Get all active numbers from table (multi-number support)
		$alloNumbers = dolicraftalloGetNumbers($db);
		if (empty($alloNumbers)) {
			$this->error = $langs->trans('NoAlloNumberConfigured');
			return -1;
		}

		$inserted = 0;
		$updated = 0;
		$apiErrors = array();

		dol_syslog('DolicraftAllo sync_calls: starting sync for '.count($alloNumbers).' configured numbers', LOG_INFO);

		// Loop through each configured number
		foreach ($alloNumbers as $numObj) {
			$alloNumber = $numObj->allo_number;
			$page = 0;
			$hasMore = true;

			while ($hasMore) {
				$result = $this->api->searchCalls($alloNumber, $page, 50);

				if ($result === false) {
					$apiErrors[] = $alloNumber.': '.$this->api->getLastError();
					break;
				}

				// Allo API returns {data: {results: [...]}}
				$calls = array();
				if (isset($result['data']['results'])) {
					$calls = $result['data']['results'];
				} elseif (isset($result['data']) && is_array($result['data']) && array_values($result['data']) === $result['data']) {
					$calls = $result['data'];
				}
				if (empty($calls)) {
					$hasMore = false;
					break;
				}

				dol_syslog('DolicraftAllo sync_calls: page '.$page.' fetched '.count($calls).' calls for '.$alloNumber, LOG_INFO);

				foreach ($calls as $call) {
					$alloCallId = isset($call['id']) ? $call['id'] : '';
					if (empty($alloCallId)) {
						continue;
					}

					$fromNumber = isset($call['from_number']) ? $call['from_number'] : '';
					$toNumber = isset($call['to_number']) ? $call['to_number'] : '';
					$callType = isset($call['type']) ? strtoupper($call['type']) : 'INBOUND';
					$callResult = isset($call['result']) ? strtoupper($call['result']) : 'ANSWERED';
					$durationMin = isset($call['length_in_minutes']) ? floatval($call['length_in_minutes']) : 0;
					$summary = isset($call['summary']) ? $call['summary'] : '';
					$recordingUrl = isset($call['recording_url']) ? $call['recording_url'] : '';
					$tag = '';
					if (!empty($call['tags']) && is_array($call['tags'])) {
						$tag = implode(', ', $call['tags']);
					} elseif (!empty($call['tag'])) {
						$tag = $call['tag'];
					}
					$callDateRaw = isset($call['start_date']) ? $call['start_date'] : date('Y-m-d H:i:s');
					$callDate = date('Y-m-d H:i:s', strtotime($callDateRaw));

					// Extract transcript
					$transcript = '';
					if (!empty($call['transcript']) && is_array($call['transcript'])) {
						$lines = array();
						foreach ($call['transcript'] as $t) {
							$speaker = isset($t['source']) ? $t['source'] : '';
							$text = isset($t['text']) ? $t['text'] : '';
							if (!empty($text)) {
								$lines[] = $speaker.': '.$text;
							}
						}
						$transcript = implode("\n", $lines);
					}

					// Match phone numbers to Dolibarr contacts/thirdparties
					$contactNumber = ($callType == 'INBOUND') ? $fromNumber : $toNumber;
					$match = $this->matchPhone($contactNumber);
					$fkSoc = $match['fk_soc'];
					$fkSocpeople = $match['fk_socpeople'];

					// Check if call already exists
					$sqlcheck = "SELECT rowid FROM ".MAIN_DB_PREFIX."dolicraftallo_call_log";
					$sqlcheck .= " WHERE entity = ".((int) $conf->entity);
					$sqlcheck .= " AND allo_call_id = '".$db->escape($alloCallId)."'";

					$resqlcheck = $db->query($sqlcheck);
					if ($resqlcheck && $db->num_rows($resqlcheck) > 0) {
						// Update existing
						$objexist = $db->fetch_object($resqlcheck);
						$sqlupd = "UPDATE ".MAIN_DB_PREFIX."dolicraftallo_call_log SET";
						$sqlupd .= " call_result = '".$db->escape($callResult)."',";
						$sqlupd .= " duration_minutes = ".floatval($durationMin).",";
						$sqlupd .= " summary = '".$db->escape($summary)."',";
						$sqlupd .= " recording_url = '".$db->escape($recordingUrl)."',";
						$sqlupd .= " transcript = '".$db->escape($transcript)."',";
						$sqlupd .= " tag = '".$db->escape($tag)."',";
						$sqlupd .= " fk_soc = ".((int) $fkSoc).",";
						$sqlupd .= " fk_socpeople = ".((int) $fkSocpeople);
						$sqlupd .= " WHERE rowid = ".((int) $objexist->rowid);
						$resqlupd = $db->query($sqlupd);
						if (!$resqlupd) {
							dol_syslog('DolicraftAllo: SQL error on call update: '.$db->lasterror(), LOG_ERR);
						}
						$updated++;
					} else {
						// Insert new
						$sqlins = "INSERT INTO ".MAIN_DB_PREFIX."dolicraftallo_call_log";
						$sqlins .= " (entity, allo_call_id, fk_soc, fk_socpeople, from_number, to_number,";
						$sqlins .= " call_type, call_result, duration_minutes, summary, recording_url, transcript, tag, call_date, datec)";
						$sqlins .= " VALUES (";
						$sqlins .= ((int) $conf->entity).",";
						$sqlins .= "'".$db->escape($alloCallId)."',";
						$sqlins .= ((int) $fkSoc).",";
						$sqlins .= ((int) $fkSocpeople).",";
						$sqlins .= "'".$db->escape($fromNumber)."',";
						$sqlins .= "'".$db->escape($toNumber)."',";
						$sqlins .= "'".$db->escape($callType)."',";
						$sqlins .= "'".$db->escape($callResult)."',";
						$sqlins .= floatval($durationMin).",";
						$sqlins .= "'".$db->escape($summary)."',";
						$sqlins .= "'".$db->escape($recordingUrl)."',";
						$sqlins .= "'".$db->escape($transcript)."',";
						$sqlins .= "'".$db->escape($tag)."',";
						$sqlins .= "'".$db->escape($callDate)."',";
						$sqlins .= "'".$db->idate(dol_now())."'";
						$sqlins .= ")";
						$resqlins = $db->query($sqlins);
						if (!$resqlins) {
							dol_syslog('DolicraftAllo: SQL error on call insert: '.$db->lasterror(), LOG_ERR);
						}
						$inserted++;
					}
				}

				// Check pagination - Allo API returns {data: {metadata: {total_pages, current_page}}}
				$totalPages = 0;
				if (isset($result['data']['metadata']['total_pages'])) {
					$totalPages = (int) $result['data']['metadata']['total_pages'];
				}
				$page++;
				if ($totalPages > 0 && $page >= $totalPages) {
					$hasMore = false;
				} elseif (count($calls) == 0) {
					$hasMore = false;
				}
			} // end while hasMore
		} // end foreach alloNumbers

		// Every number failed: report the API error instead of "0 calls imported"
		if (!empty($apiErrors) && count($apiErrors) >= count($alloNumbers) && ($inserted + $updated) == 0) {
			$this->error = implode(' / ', $apiErrors);
			return -1;
		}
		if (!empty($apiErrors)) {
			dol_syslog('DolicraftAllo sync_calls: some numbers failed: '.implode(' / ', $apiErrors), LOG_WARNING);
		}

		dol_syslog('DolicraftAllo sync_calls: completed - '.$inserted.' inserted, '.$updated.' updated', LOG_INFO);

		return array('inserted' => $inserted, 'updated' => $updated);
	}

	/**
	 * Synchronize Allo contacts (CRM people, scope CRM_READ): map them to Dolibarr contacts/thirdparties
	 * by phone number, and create the Dolibarr contact when no match is found
	 *
	 * @param  User      $user User creating the missing Dolibarr contacts
	 * @return array|int       array('total_allo', 'total_mapped', 'new_mapped', 'new_created'), or -1 on error (see $this->error)
	 */
	public function syncContacts($user)
	{
		global $conf;

		$db = $this->db;

		$page = 1;
		$hasMore = true;
		$totalAllo = 0;
		$totalMapped = 0;
		$newMapped = 0;
		$newCreated = 0;

		dol_syslog('DolicraftAllo sync_contacts: starting contact synchronization', LOG_INFO);

		// Check if allo_name column exists in contact_map table
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

		while ($hasMore) {
			$result = $this->api->getContacts($page, 100);

			if ($result === false) {
				if ($page == 1) {
					$this->error = $this->api->getLastError();
					return -1;
				}
				break;
			}

			// Allo API v2 returns {data: [...], pagination: {...}} (v1 returned {data: {results: [...]}})
			$contacts = array();
			if (isset($result['data']['results'])) {
				$contacts = $result['data']['results'];
			} elseif (isset($result['data']) && is_array($result['data']) && array_values($result['data']) === $result['data']) {
				$contacts = $result['data'];
			}
			if (empty($contacts)) {
				$hasMore = false;
				break;
			}

			dol_syslog('DolicraftAllo sync_contacts: page '.$page.' fetched '.count($contacts).' contacts', LOG_INFO);

			foreach ($contacts as $contact) {
				$alloContactId = isset($contact['id']) ? $contact['id'] : '';
				if (empty($alloContactId)) {
					continue;
				}

				$totalAllo++;

				// Build display name from Allo contact
				$alloName = trim(($contact['name'] ?? '').' '.($contact['last_name'] ?? ''));

				// Get phone number from contact
				// Allo API returns "numbers": ["+33612345678"]
				$phone = '';
				if (!empty($contact['numbers']) && is_array($contact['numbers'])) {
					$phone = $contact['numbers'][0];
				} elseif (!empty($contact['phone_number'])) {
					$phone = $contact['phone_number'];
				} elseif (!empty($contact['phone'])) {
					$phone = $contact['phone'];
				}

				// Match by phone number to Dolibarr
				$match = $this->matchPhone($phone);
				$fkSoc = $match['fk_soc'];
				$fkSocpeople = $match['fk_socpeople'];

				// If no match found in Dolibarr, create a new contact
				if ($fkSocpeople == 0 && !empty($phone)) {
					require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
					$newContact = new Contact($db);

					// Parse name from Allo contact
					$alloFirstName = isset($contact['name']) ? trim($contact['name']) : '';
					$alloLastName = isset($contact['last_name']) ? trim($contact['last_name']) : '';
					$alloJobTitle = isset($contact['job_title']) ? trim($contact['job_title']) : '';
					$alloEmail = '';
					if (!empty($contact['emails']) && is_array($contact['emails'])) {
						$alloEmail = $contact['emails'][0];
					}
					$alloWebsite = isset($contact['website']) ? trim($contact['website']) : '';

					// If only name (no last_name), try to split
					if (!empty($alloFirstName) && empty($alloLastName)) {
						$parts = explode(' ', $alloFirstName, 2);
						$newContact->firstname = $parts[0];
						$newContact->lastname = isset($parts[1]) ? $parts[1] : $parts[0];
					} else {
						$newContact->firstname = $alloFirstName;
						$newContact->lastname = !empty($alloLastName) ? $alloLastName : (!empty($alloFirstName) ? $alloFirstName : $phone);
					}

					$newContact->phone_mobile = $phone;
					$newContact->poste = $alloJobTitle;
					$newContact->email = $alloEmail;
					$newContact->url = $alloWebsite;
					$newContact->entity = $conf->entity;
					$newContact->note_private = 'Imported from Allo - ID: '.$alloContactId;

					// Link to company if contact has company info
					if (!empty($contact['company']) && is_array($contact['company']) && !empty($contact['company']['name'])) {
						// Try to find existing company by name
						$companyName = $db->escape($contact['company']['name']);
						$sqlFindSoc = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom = '".$companyName."' AND entity IN (".getEntity('societe').")".$db->plimit(1);
						$resFindSoc = $db->query($sqlFindSoc);
						if ($resFindSoc && $db->num_rows($resFindSoc) > 0) {
							$fkSoc = (int) $db->fetch_object($resFindSoc)->rowid;
							$newContact->socid = $fkSoc;
						}
					}

					$contactId = $newContact->create($user);
					if ($contactId > 0) {
						$fkSocpeople = $contactId;
						$newCreated++;
					} else {
						dol_syslog('DolicraftAllo sync_contacts: contact creation failed for '.$alloContactId.': '.$newContact->error, LOG_WARNING);
					}
				}

				// Check if mapping already exists
				$sqlcheck = "SELECT rowid FROM ".MAIN_DB_PREFIX."dolicraftallo_contact_map";
				$sqlcheck .= " WHERE entity = ".((int) $conf->entity);
				$sqlcheck .= " AND allo_contact_id = '".$db->escape($alloContactId)."'";

				$resqlcheck = $db->query($sqlcheck);
				if ($resqlcheck && $db->num_rows($resqlcheck) > 0) {
					// Update existing mapping
					$objexist = $db->fetch_object($resqlcheck);
					$sqlupd = "UPDATE ".MAIN_DB_PREFIX."dolicraftallo_contact_map SET";
					$sqlupd .= " fk_soc = ".((int) $fkSoc).",";
					$sqlupd .= " fk_socpeople = ".((int) $fkSocpeople).",";
					$sqlupd .= " phone_number = '".$db->escape($phone)."',";
					if ($hasAlloNameCol && !empty($alloName)) {
						$sqlupd .= " allo_name = '".$db->escape($alloName)."',";
					}
					$sqlupd .= " last_sync = '".$db->idate(dol_now())."'";
					$sqlupd .= " WHERE rowid = ".((int) $objexist->rowid);
					$resqlupd = $db->query($sqlupd);
					if (!$resqlupd) {
						dol_syslog('DolicraftAllo: SQL error on contact map update: '.$db->lasterror(), LOG_ERR);
					}
					$totalMapped++;
				} else {
					// Insert new mapping
					$sqlins = "INSERT INTO ".MAIN_DB_PREFIX."dolicraftallo_contact_map";
					$sqlins .= " (entity, allo_contact_id, fk_soc, fk_socpeople, phone_number, last_sync, datec";
					if ($hasAlloNameCol) {
						$sqlins .= ", allo_name";
					}
					$sqlins .= ")";
					$sqlins .= " VALUES (";
					$sqlins .= ((int) $conf->entity).",";
					$sqlins .= "'".$db->escape($alloContactId)."',";
					$sqlins .= ((int) $fkSoc).",";
					$sqlins .= ((int) $fkSocpeople).",";
					$sqlins .= "'".$db->escape($phone)."',";
					$sqlins .= "'".$db->idate(dol_now())."',";
					$sqlins .= "'".$db->idate(dol_now())."'";
					if ($hasAlloNameCol) {
						$sqlins .= ",'".$db->escape($alloName)."'";
					}
					$sqlins .= ")";
					$resqlins = $db->query($sqlins);
					if (!$resqlins) {
						dol_syslog('DolicraftAllo: SQL error on contact map insert: '.$db->lasterror(), LOG_ERR);
					}
					$newMapped++;
					$totalMapped++;
				}
			}

			// Pagination - v2: {pagination: {has_more, total_pages}}, v1: {data: {metadata: {total_pages}}}
			$totalPages = 0;
			if (isset($result['pagination']['total_pages'])) {
				$totalPages = (int) $result['pagination']['total_pages'];
			} elseif (isset($result['data']['metadata']['total_pages'])) {
				$totalPages = (int) $result['data']['metadata']['total_pages'];
			}
			if (isset($result['pagination']['has_more']) && !$result['pagination']['has_more']) {
				$hasMore = false;
			} elseif ($totalPages > 0 && $page >= $totalPages) {
				$hasMore = false;
			}
			$page++;
		}

		dol_syslog('DolicraftAllo sync_contacts: completed - '.$totalAllo.' contacts processed, '.$newCreated.' created in Dolibarr, '.$newMapped.' new mappings', LOG_INFO);

		return array(
			'total_allo' => $totalAllo,
			'total_mapped' => $totalMapped,
			'new_mapped' => $newMapped,
			'new_created' => $newCreated,
		);
	}

	/**
	 * Scheduled job: synchronize the call history
	 *
	 * @return int 0 if OK, <>0 if KO (convention of the Dolibarr cron module)
	 */
	public function runCallsSync()
	{
		$stats = $this->syncCalls();
		if (!is_array($stats)) {
			$this->error = 'Calls sync failed: '.$this->error;
			$this->errors[] = $this->error;
			return 1;
		}

		$this->output = $stats['inserted'].' call(s) imported, '.$stats['updated'].' updated.';
		return 0;
	}

	/**
	 * Scheduled job: synchronize contacts, when contact sync is enabled in module setup
	 *
	 * @return int 0 if OK, <>0 if KO (convention of the Dolibarr cron module)
	 */
	public function runContactsSync()
	{
		global $user;

		if (!getDolGlobalString('DOLICRAFTALLO_SYNC_CONTACTS', '1')) {
			$this->output = 'Contact sync disabled in module setup, nothing done.';
			return 0;
		}

		$stats = $this->syncContacts($user);
		if (!is_array($stats)) {
			$this->error = 'Contacts sync failed: '.$this->error;
			$this->errors[] = $this->error;
			return 1;
		}

		$this->output = $stats['total_allo'].' Allo contact(s) processed, '.$stats['new_created'].' created in Dolibarr, '.$stats['new_mapped'].' new mapping(s).';
		return 0;
	}
}
