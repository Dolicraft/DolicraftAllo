<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/dolicraftallo.lib.php
 * \ingroup dolicraftallo
 * \brief   Library functions for DolicraftAllo module
 */

/**
 * Prepare admin pages header tabs
 *
 * @return array Array of tabs
 */
function dolicraftalloAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load('dolicraftallo@dolicraftallo');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/dolicraftallo/index.php', 1);
	$head[$h][1] = ''.$langs->trans('JournalAppels');
	$head[$h][2] = 'calls';
	$h++;

	$head[$h][0] = dol_buildpath('/dolicraftallo/sms.php', 1);
	$head[$h][1] = ''.$langs->trans('SMS');
	$head[$h][2] = 'sms';
	$h++;

	$head[$h][0] = dol_buildpath('/dolicraftallo/inbox.php', 1);
	$head[$h][1] = ''.$langs->trans('Inbox');
	$head[$h][2] = 'inbox';
	$h++;

	$head[$h][0] = dol_buildpath('/dolicraftallo/contacts.php', 1);
	$head[$h][1] = ''.$langs->trans('Contacts');
	$head[$h][2] = 'contacts';
	$h++;

	$head[$h][0] = dol_buildpath('/dolicraftallo/admin/documentation.php', 1);
	$head[$h][1] = ''.$langs->trans('Documentation');
	$head[$h][2] = 'documentation';
	$h++;

	$head[$h][0] = dol_buildpath('/dolicraftallo/dashboard.php', 1);
	$head[$h][1] = ''.$langs->trans('Dashboard');
	$head[$h][2] = 'dashboard';
	$h++;

	$head[$h][0] = dol_buildpath('/dolicraftallo/admin/numbers.php', 1);
	$head[$h][1] = ''.$langs->trans('AlloNumbers');
	$head[$h][2] = 'numbers';
	$h++;

	$head[$h][0] = dol_buildpath('/dolicraftallo/admin/templates.php', 1);
	$head[$h][1] = ''.$langs->trans('SMSTemplates');
	$head[$h][2] = 'templates';
	$h++;

	$head[$h][0] = dol_buildpath('/dolicraftallo/admin/setup.php', 1);
	$head[$h][1] = ''.$langs->trans('Configuration');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/dolicraftallo/admin/about.php', 1);
	$head[$h][1] = ''.$langs->trans('About');
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolicraftallo@dolicraftallo');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolicraftallo@dolicraftallo', 'remove');

	return $head;
}

/**
 * Get all active Allo phone numbers
 *
 * @param  DoliDB $db     Database handler
 * @param  int    $userId Optional: filter by user (0 = all)
 * @return array          Array of number objects (rowid, allo_number, label, sender_id, fk_user, is_default)
 */
function dolicraftalloGetNumbers($db, $userId = 0)
{
	global $conf;

	$numbers = array();

	$sql = "SELECT rowid, allo_number, label, sender_id, fk_user, is_default";
	$sql .= " FROM ".MAIN_DB_PREFIX."dolicraftallo_numbers";
	$sql .= " WHERE entity = ".((int) $conf->entity)." AND active = 1";
	if ($userId > 0) {
		$sql .= " AND (fk_user = ".((int) $userId)." OR fk_user = 0)";
	}
	$sql .= " ORDER BY is_default DESC, label ASC";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$numbers[] = $obj;
		}
		$db->free($resql);
	}

	// Fallback to legacy constant if no numbers in table
	if (empty($numbers)) {
		$legacyNumber = getDolGlobalString('DOLICRAFTALLO_ALLO_NUMBER');
		if (!empty($legacyNumber)) {
			$obj = new stdClass();
			$obj->rowid = 0;
			$obj->allo_number = $legacyNumber;
			$obj->label = 'Default';
			$obj->sender_id = getDolGlobalString('DOLICRAFTALLO_SENDER_ID');
			$obj->fk_user = 0;
			$obj->is_default = 1;
			$numbers[] = $obj;
		}
	}

	return $numbers;
}

/**
 * Get the default Allo number for a user
 *
 * @param  DoliDB $db     Database handler
 * @param  int    $userId User ID
 * @return string         Phone number in E.164 or empty
 */
function dolicraftalloGetDefaultNumber($db, $userId = 0)
{
	$numbers = dolicraftalloGetNumbers($db, $userId);
	if (!empty($numbers)) {
		return $numbers[0]->allo_number;
	}
	return '';
}

/**
 * Escape LIKE wildcards in a string for safe SQL LIKE usage
 *
 * @param  string $str String already escaped with $db->escape()
 * @return string      String with \, % and _ escaped
 */
function dolicraftalloEscapeLike($str)
{
	return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $str);
}
