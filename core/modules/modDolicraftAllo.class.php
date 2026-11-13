<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \defgroup dolicraftallo Module DolicraftAllo
 * \brief    Allo cloud phone integration for Dolibarr
 */

/**
 * \file    core/modules/modDolicraftAllo.class.php
 * \ingroup dolicraftallo
 * \brief   Module descriptor for DolicraftAllo
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modDolicraftAllo
 *
 * Module descriptor for DolicraftAllo - Allo cloud phone integration
 */
class modDolicraftAllo extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 500800;
		$this->rights_class = 'dolicraftallo';
		$this->family = 'interface';
		$this->module_position = '96';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Module500800Desc';
		$this->descriptionlong = 'Module500800DescLong';
		$this->editor_name = 'Dolicraft';
		$this->editor_url = 'https://dolicraft.com';
		$this->editor_email = 'contact@dolicraft.com';
		$this->version = '1.0.3';
		$this->const_name = 'MAIN_MODULE_DOLICRAFTALLO';
		$this->picto = 'phone';

		$this->module_parts = array(
			'css' => array('/dolicraftallo/css/dolicraftallo.css'),
			'hooks' => array(
				'data' => array('thirdpartycard', 'contactcard', 'invoicecard', 'main'),
				'entity' => '0',
			),
		);

		$this->dirs = array('/dolicraftallo/temp');

		$this->config_page_url = array('setup.php@dolicraftallo');

		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array('dolicraftallo@dolicraftallo');

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(16, 0);
		$this->need_javascript_ajax = 0;

		// Constants
		$this->const = array(
			0 => array('DOLICRAFTALLO_API_KEY', 'chaine', '', 'Allo API key', 0, 'current', 1),
			1 => array('DOLICRAFTALLO_ALLO_NUMBER', 'chaine', '', 'Allo phone number E.164', 0, 'current', 1),
			2 => array('DOLICRAFTALLO_WEBHOOK_SECRET', 'chaine', '', 'Webhook secret', 0, 'current', 1),
			3 => array('DOLICRAFTALLO_SYNC_CONTACTS', 'chaine', '1', 'Enable contact sync', 0, 'current', 1),
			4 => array('DOLICRAFTALLO_SMS_ENABLED', 'chaine', '1', 'Enable SMS sending', 0, 'current', 1),
			5 => array('DOLICRAFTALLO_SENDER_ID', 'chaine', '', 'SMS Sender ID for France', 0, 'current', 1),
		);

		// Tabs
		$this->tabs = array();

		// Dictionaries
		$this->dictionaries = array();

		// Boxes / Widgets
		$this->boxes = array();

		// Cronjobs
		// Disabled by default: enable them in Home > Admin tools > Scheduled jobs (alternative to the webhook, Dolibarr pulls the data)
		$this->cronjobs = array(
			0 => array(
				'label' => 'AlloCallsSyncJob',
				'jobtype' => 'method',
				'class' => '/dolicraftallo/class/allo_sync.class.php',
				'objectname' => 'DolicraftAlloSync',
				'method' => 'runCallsSync',
				'parameters' => '',
				'comment' => 'Synchronize the Allo call history',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 0,
				'test' => 'isModEnabled("dolicraftallo")',
				'priority' => 50,
			),
			1 => array(
				'label' => 'AlloContactsSyncJob',
				'jobtype' => 'method',
				'class' => '/dolicraftallo/class/allo_sync.class.php',
				'objectname' => 'DolicraftAlloSync',
				'method' => 'runContactsSync',
				'parameters' => '',
				'comment' => 'Synchronize Allo contacts (requires "Enable contact sync" in module setup)',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'test' => 'isModEnabled("dolicraftallo")',
				'priority' => 50,
			),
		);

		// Permissions
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = 500801;
		$this->rights[$r][1] = 'View call history';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'view';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 500802;
		$this->rights[$r][1] = 'Send SMS';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'send_sms';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 500803;
		$this->rights[$r][1] = 'Sync contacts';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'sync';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 500804;
		$this->rights[$r][1] = 'Configure module';
		$this->rights[$r][2] = 'a';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'configure';
		$this->rights[$r][5] = '';
		$r++;

		// Menus
		$this->menu = array();
		$r = 0;

		// Top menu
		$this->menu[$r] = array(
			'fk_menu'  => '',
			'type'     => 'top',
			'titre'    => 'DolicraftAllo',
			'prefix'   => '<span class="fas fa-phone pictofixedwidth" style="font-size:1.4em;"></span>',
			'mainmenu' => 'dolicraftallo',
			'leftmenu' => '',
			'url'      => '/dolicraftallo/index.php',
			'langs'    => 'dolicraftallo@dolicraftallo',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("dolicraftallo")',
			'perms'    => '$user->hasRight("dolicraftallo", "view")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// Left menu: Journal d'appels
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=dolicraftallo',
			'type'     => 'left',
			'titre'    => 'JournalAppels',
			'mainmenu' => 'dolicraftallo',
			'leftmenu' => 'dolicraftallo_calls',
			'url'      => '/dolicraftallo/index.php',
			'langs'    => 'dolicraftallo@dolicraftallo',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("dolicraftallo")',
			'perms'    => '$user->hasRight("dolicraftallo", "view")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// Left menu: SMS
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=dolicraftallo',
			'type'     => 'left',
			'titre'    => 'SMS',
			'mainmenu' => 'dolicraftallo',
			'leftmenu' => 'dolicraftallo_sms',
			'url'      => '/dolicraftallo/sms.php',
			'langs'    => 'dolicraftallo@dolicraftallo',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("dolicraftallo")',
			'perms'    => '$user->hasRight("dolicraftallo", "send_sms")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// Left menu: Contacts Sync
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=dolicraftallo',
			'type'     => 'left',
			'titre'    => 'ContactsSync',
			'mainmenu' => 'dolicraftallo',
			'leftmenu' => 'dolicraftallo_contacts',
			'url'      => '/dolicraftallo/contacts.php',
			'langs'    => 'dolicraftallo@dolicraftallo',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("dolicraftallo")',
			'perms'    => '$user->hasRight("dolicraftallo", "sync")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// Left menu: Configuration (admin)
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=dolicraftallo',
			'type'     => 'left',
			'titre'    => 'Configuration',
			'mainmenu' => 'dolicraftallo',
			'leftmenu' => 'dolicraftallo_setup',
			'url'      => '/dolicraftallo/admin/setup.php',
			'langs'    => 'dolicraftallo@dolicraftallo',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("dolicraftallo")',
			'perms'    => '$user->admin',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// Left menu: A propos
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=dolicraftallo',
			'type'     => 'left',
			'titre'    => 'About',
			'mainmenu' => 'dolicraftallo',
			'leftmenu' => 'dolicraftallo_about',
			'url'      => '/dolicraftallo/admin/about.php',
			'langs'    => 'dolicraftallo@dolicraftallo',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("dolicraftallo")',
			'perms'    => '$user->hasRight("dolicraftallo", "view")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/dolicraftallo/sql/');
		if ($result < 0) {
			return -1;
		}

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param string $options Options when disabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}
}
