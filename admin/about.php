<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/about.php
 * \ingroup dolicraftallo
 * \brief   About page for DolicraftAllo module
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
// View
//
llxHeader('', $langs->trans("AboutDolicraftAllo"), '', '', 0, 0, '', '', '', 'mod-dolicraftallo page-admin-about');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans("AboutDolicraftAllo"), $linkback, 'title_setup');

$head = dolicraftalloAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans("AboutDolicraftAllo"), -1, 'phone');

print '<div class="fichecenter">';

// --- Module info ---
print '<div class="underbanner clearboth"></div>';

print '<table class="border centpercent tableforfield">';
print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-phone pictofixedwidth"></i>'.$langs->trans("AboutModule").'</td></tr>';
print '<tr><td class="titlefield">'.$langs->trans("ModuleName").'</td><td><strong>DolicraftAllo</strong></td></tr>';
print '<tr><td>'.$langs->trans("ModuleVersion").'</td><td>1.0.0</td></tr>';
print '<tr><td>'.$langs->trans("Description").'</td><td>'.$langs->trans("Module500800Desc").'</td></tr>';
print '<tr><td>'.$langs->trans("ModuleLicense").'</td><td>GPL v3+ - <span class="badge badge-status4">'.$langs->trans("Free").'</span></td></tr>';
print '<tr><td>'.$langs->trans("ModuleAuthor").'</td><td><strong>Dolicraft</strong></td></tr>';
print '</table>';

print '<br>';

// --- Dolicraft company info ---
print '<table class="border centpercent tableforfield">';
print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-building pictofixedwidth"></i>Dolicraft</td></tr>';
print '<tr><td class="titlefield">'.$langs->trans("Publisher").'</td><td><strong>Dolicraft</strong></td></tr>';
print '<tr><td>'.$langs->trans("Website").'</td><td><a href="https://dolicraft.com" target="_blank" rel="noopener noreferrer">https://dolicraft.com</a></td></tr>';
print '<tr><td>'.$langs->trans("EMail").'</td><td><a href="mailto:contact@dolicraft.com">contact@dolicraft.com</a></td></tr>';
print '</table>';

print '<br>';

// --- Features list ---
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-list-check pictofixedwidth"></i>Fonctionnalites</td></tr>';

$features = array(
	array('icon' => 'fa-address-book', 'label' => 'Synchronisation des contacts', 'desc' => 'Synchronisez automatiquement les contacts entre Allo et Dolibarr par numero de telephone.'),
	array('icon' => 'fa-phone', 'label' => 'Historique des appels', 'desc' => 'Consultez le journal complet des appels entrants, sortants et manques avec duree et resume.'),
	array('icon' => 'fa-comment-dots', 'label' => 'Envoi de SMS', 'desc' => 'Envoyez des SMS directement depuis Dolibarr a vos contacts via l\'API Allo.'),
	array('icon' => 'fa-bolt', 'label' => 'Webhooks temps reel', 'desc' => 'Recevez les evenements d\'appels et de SMS en temps reel via webhooks.'),
	array('icon' => 'fa-mouse-pointer', 'label' => 'Click-to-call', 'desc' => 'Lancez un appel en un clic depuis les fiches tiers et contacts Dolibarr.'),
	array('icon' => 'fa-file-invoice-dollar', 'label' => 'Rappels SMS factures', 'desc' => 'Envoyez des rappels SMS automatiques pour les factures impayees.'),
);

foreach ($features as $feature) {
	print '<tr class="oddeven">';
	print '<td class="titlefield"><i class="fas '.$feature['icon'].' pictofixedwidth"></i><strong>'.$feature['label'].'</strong></td>';
	print '<td>'.$feature['desc'].'</td>';
	print '</tr>';
}

print '</table>';

print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
