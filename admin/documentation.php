<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/documentation.php
 * \ingroup dolicraftallo
 * \brief   Documentation page for DolicraftAllo module
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

// Security check
if (!$user->hasRight('dolicraftallo', 'view')) {
	accessforbidden();
}

//
// View
//
llxHeader('', $langs->trans("Documentation"), '', '', 0, 0, '', '', '', 'mod-dolicraftallo page-admin-documentation');

print load_fiche_titre($langs->trans("Documentation").' - DolicraftAllo', '', 'phone');

$head = dolicraftalloAdminPrepareHead();
print dol_get_fiche_head($head, 'documentation', $langs->trans("DolicraftAllo"), -1, 'phone');

// Table of contents
print '<div class="doc-toc" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px 24px;margin-bottom:24px;">';
print '<h3 style="margin-top:0;"><i class="fas fa-list pictofixedwidth"></i>'.$langs->trans("TableOfContents").'</h3>';
print '<ol style="margin:0;padding-left:20px;line-height:2;">';
print '<li><a href="#introduction">'.$langs->trans("DocIntroduction").'</a></li>';
print '<li><a href="#prerequisites">'.$langs->trans("DocPrerequisites").'</a></li>';
print '<li><a href="#installation">'.$langs->trans("DocInstallation").'</a></li>';
print '<li><a href="#configuration">'.$langs->trans("DocConfiguration").'</a></li>';
print '<li><a href="#api-scopes">'.$langs->trans("DocApiScopes").'</a></li>';
print '<li><a href="#call-log">'.$langs->trans("DocCallLog").'</a></li>';
print '<li><a href="#sms">'.$langs->trans("DocSMS").'</a></li>';
print '<li><a href="#contacts">'.$langs->trans("DocContacts").'</a></li>';
print '<li><a href="#webhooks">'.$langs->trans("DocWebhooks").'</a></li>';
print '<li><a href="#cron">'.$langs->trans("DocCron").'</a></li>';
print '<li><a href="#dashboard">'.$langs->trans("DocDashboard").'</a></li>';
print '<li><a href="#troubleshooting">'.$langs->trans("DocTroubleshooting").'</a></li>';
print '</ol>';
print '</div>';

// Section style helper
$sectionStyle = 'style="margin-bottom:24px;"';
$titleStyle = 'style="border-bottom:2px solid #3b82f6;padding-bottom:8px;margin-bottom:12px;"';

// 1. Introduction
print '<div '.$sectionStyle.'>';
print '<h2 id="introduction" '.$titleStyle.'><i class="fas fa-info-circle pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocIntroduction").'</h2>';
print '<p>'.$langs->trans("DocIntroductionText").'</p>';
print '<ul>';
print '<li><i class="fas fa-phone pictofixedwidth"></i>'.$langs->trans("DocFeatureCallLog").'</li>';
print '<li><i class="fas fa-comment-dots pictofixedwidth"></i>'.$langs->trans("DocFeatureSMS").'</li>';
print '<li><i class="fas fa-address-book pictofixedwidth"></i>'.$langs->trans("DocFeatureContacts").'</li>';
print '<li><i class="fas fa-bolt pictofixedwidth"></i>'.$langs->trans("DocFeatureWebhooks").'</li>';
print '<li><i class="fas fa-chart-bar pictofixedwidth"></i>'.$langs->trans("DocFeatureDashboard").'</li>';
print '</ul>';
print '</div>';

// 2. Prerequisites
print '<div '.$sectionStyle.'>';
print '<h2 id="prerequisites" '.$titleStyle.'><i class="fas fa-clipboard-check pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocPrerequisites").'</h2>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Requirement").'</td><td>'.$langs->trans("Description").'</td></tr>';
print '<tr class="oddeven"><td><strong>Allo Business / Pro</strong></td><td>'.$langs->trans("DocPrereqAlloPlan").'</td></tr>';
print '<tr class="oddeven"><td><strong>'.$langs->trans("ApiKey").'</strong></td><td>'.$langs->trans("DocPrereqApiKey").'</td></tr>';
print '<tr class="oddeven"><td><strong>Dolibarr 16+</strong></td><td>'.$langs->trans("DocPrereqDolibarr").'</td></tr>';
print '</table>';
print '</div>';

// 3. Installation
print '<div '.$sectionStyle.'>';
print '<h2 id="installation" '.$titleStyle.'><i class="fas fa-download pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocInstallation").'</h2>';
print '<ol style="line-height:2;">';
print '<li>'.$langs->trans("DocInstallStep1").'</li>';
print '<li>'.$langs->trans("DocInstallStep2").'</li>';
print '<li>'.$langs->trans("DocInstallStep3").'</li>';
print '</ol>';
print '</div>';

// 4. Configuration
print '<div '.$sectionStyle.'>';
print '<h2 id="configuration" '.$titleStyle.'><i class="fas fa-cog pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocConfiguration").'</h2>';
print '<p>'.$langs->trans("DocConfigurationText").'</p>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Description").'</td></tr>';
print '<tr class="oddeven"><td><strong>'.$langs->trans("ApiKey").'</strong></td><td>'.$langs->trans("DocConfigApiKey").'</td></tr>';
print '<tr class="oddeven"><td><strong>'.$langs->trans("AlloNumbers").'</strong></td><td>'.$langs->trans("DocConfigNumbers").'</td></tr>';
print '<tr class="oddeven"><td><strong>Webhook URL</strong></td><td>'.$langs->trans("DocConfigWebhook").'</td></tr>';
print '<tr class="oddeven"><td><strong>'.$langs->trans("SenderId").'</strong></td><td>'.$langs->trans("DocConfigSenderId").'</td></tr>';
print '</table>';
print '</div>';

// 4b. API key scopes
print '<div '.$sectionStyle.'>';
print '<h2 id="api-scopes" '.$titleStyle.'><i class="fas fa-key pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocApiScopes").'</h2>';
print '<p>'.$langs->trans("DocApiScopesText").'</p>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("DocScopeFeature").'</td><td>Scope</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("DocScopeTestNumbers").'</td><td><code>PHONE_NUMBERS_READ</code></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("DocScopeCalls").'</td><td><code>CONVERSATIONS_READ</code></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("DocScopeSMS").'</td><td><code>SMS_SEND</code></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("DocScopeContacts").'</td><td><code>CRM_READ</code></td></tr>';
print '</table>';
print '<p class="opacitymedium">'.$langs->trans("DocApiScopesNote").'</p>';
print '</div>';

// 5. Call Log
print '<div '.$sectionStyle.'>';
print '<h2 id="call-log" '.$titleStyle.'><i class="fas fa-phone pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocCallLog").'</h2>';
print '<p>'.$langs->trans("DocCallLogText").'</p>';
print '<ul>';
print '<li>'.$langs->trans("DocCallLogSync").'</li>';
print '<li>'.$langs->trans("DocCallLogFilter").'</li>';
print '<li>'.$langs->trans("DocCallLogTranscript").'</li>';
print '<li>'.$langs->trans("DocCallLogRecording").'</li>';
print '</ul>';
print '</div>';

// 6. SMS
print '<div '.$sectionStyle.'>';
print '<h2 id="sms" '.$titleStyle.'><i class="fas fa-comment-dots pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocSMS").'</h2>';
print '<p>'.$langs->trans("DocSMSText").'</p>';
print '<ul>';
print '<li>'.$langs->trans("DocSMSSend").'</li>';
print '<li>'.$langs->trans("DocSMSTemplates").'</li>';
print '<li>'.$langs->trans("DocSMSInbox").'</li>';
print '<li>'.$langs->trans("DocSMSLog").'</li>';
print '</ul>';
print '</div>';

// 7. Contacts
print '<div '.$sectionStyle.'>';
print '<h2 id="contacts" '.$titleStyle.'><i class="fas fa-address-book pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocContacts").'</h2>';
print '<p>'.$langs->trans("DocContactsText").'</p>';
print '<ul>';
print '<li>'.$langs->trans("DocContactsSync").'</li>';
print '<li>'.$langs->trans("DocContactsMap").'</li>';
print '<li>'.$langs->trans("DocContactsCreate").'</li>';
print '</ul>';
print '</div>';

// 8. Webhooks
print '<div '.$sectionStyle.'>';
print '<h2 id="webhooks" '.$titleStyle.'><i class="fas fa-bolt pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocWebhooks").'</h2>';
print '<p>'.$langs->trans("DocWebhooksText").'</p>';

// Show webhook URL
$webhookUrl = dol_buildpath('/dolicraftallo/webhook.php', 2);
print '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin:12px 0;">';
print '<strong>Webhook URL:</strong><br>';
print '<code style="font-size:0.9em;word-break:break-all;">'.dol_escape_htmltag($webhookUrl).'</code>';
print '</div>';

print '<p>'.$langs->trans("DocWebhooksEvents").'</p>';
print '<ul>';
print '<li><code>call.completed</code> - '.$langs->trans("DocWebhookEventCall").'</li>';
print '<li><code>sms.received</code> - '.$langs->trans("DocWebhookEventSMS").'</li>';
print '<li><code>contact.updated</code> - '.$langs->trans("DocWebhookEventContact").'</li>';
print '</ul>';
print '</div>';

// 8b. Scheduled jobs
print '<div '.$sectionStyle.'>';
print '<h2 id="cron" '.$titleStyle.'><i class="fas fa-clock pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocCron").'</h2>';
print '<p>'.$langs->trans("DocCronText").'</p>';
print '<ul>';
print '<li><strong>'.$langs->trans("AlloCallsSyncJob").'</strong> - '.$langs->trans("DocCronCalls").'</li>';
print '<li><strong>'.$langs->trans("AlloContactsSyncJob").'</strong> - '.$langs->trans("DocCronContacts").'</li>';
print '</ul>';
print '<p>'.$langs->trans("DocCronEnable").'</p>';
print '</div>';

// 9. Dashboard
print '<div '.$sectionStyle.'>';
print '<h2 id="dashboard" '.$titleStyle.'><i class="fas fa-chart-bar pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocDashboard").'</h2>';
print '<p>'.$langs->trans("DocDashboardText").'</p>';
print '<ul>';
print '<li>'.$langs->trans("DocDashboardCalls").'</li>';
print '<li>'.$langs->trans("DocDashboardSMS").'</li>';
print '<li>'.$langs->trans("DocDashboardContacts").'</li>';
print '</ul>';
print '</div>';

// 10. Troubleshooting
print '<div '.$sectionStyle.'>';
print '<h2 id="troubleshooting" '.$titleStyle.'><i class="fas fa-wrench pictofixedwidth" style="color:#3b82f6;"></i>'.$langs->trans("DocTroubleshooting").'</h2>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Problem").'</td><td>'.$langs->trans("Solution").'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("DocTroubleshootConnectionFail").'</td><td>'.$langs->trans("DocTroubleshootConnectionFixe").'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("DocTroubleshootNoCallsSync").'</td><td>'.$langs->trans("DocTroubleshootNoCallsFix").'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("DocTroubleshootSMSFail").'</td><td>'.$langs->trans("DocTroubleshootSMSFix").'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("DocTroubleshootWebhookFail").'</td><td>'.$langs->trans("DocTroubleshootWebhookFix").'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("DocTroubleshootScope").'</td><td>'.$langs->trans("DocTroubleshootScopeFix").'</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
