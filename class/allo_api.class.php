<?php
/* Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/allo_api.class.php
 * \ingroup dolicraftallo
 * \brief   API client for Allo cloud phone system
 */

/**
 * Class DolicraftAlloAPI
 *
 * REST API client for Allo (https://api.withallo.com/v1/api)
 */
// dolibarr_set_const() is used for rate limiting; admin.lib.php is not loaded by the ajax/ entry point
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

class DolicraftAlloAPI
{
	/**
	 * @var string API key for authentication
	 */
	private $apiKey;

	/**
	 * @var string Base URL of the Allo API
	 */
	private $baseUrl = 'https://api.withallo.com/v1/api';

	/**
	 * @var string Host of the Allo API, used for endpoints given with their full version path (e.g. /v2/api/crm/people/search)
	 */
	private $apiHost = 'https://api.withallo.com';

	/**
	 * @var DoliDB Database handler
	 */
	private $db;

	/**
	 * @var string Last error message
	 */
	private $lastError = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->apiKey = getDolGlobalString('DOLICRAFTALLO_API_KEY');
	}

	/**
	 * Execute an API request
	 *
	 * @param string $method      HTTP method (GET, POST, PATCH, DELETE)
	 * @param string $endpoint    API endpoint (e.g. /calls)
	 * @param array|null $data    Request body data
	 * @param array $queryParams  Query string parameters
	 * @return mixed Decoded JSON response or false on error
	 */
	public function request($method, $endpoint, $data = null, $queryParams = [])
	{
		global $conf;

		if (empty($this->apiKey)) {
			$this->lastError = 'API key is not configured';
			return false;
		}

		// Database-backed rate limiting (1000 requests/day)
		$now = time();
		$rateLimitCount = (int) getDolGlobalString('DOLICRAFTALLO_RATE_LIMIT_COUNT', '0');
		$rateLimitReset = (int) getDolGlobalString('DOLICRAFTALLO_RATE_LIMIT_RESET', '0');

		if ($rateLimitReset > 0 && $now > $rateLimitReset) {
			// Reset window expired, reset counter
			$rateLimitCount = 0;
			dolibarr_set_const($this->db, 'DOLICRAFTALLO_RATE_LIMIT_COUNT', '0', 'chaine', 0, '', $conf->entity);
			dolibarr_set_const($this->db, 'DOLICRAFTALLO_RATE_LIMIT_RESET', (string)($now + 86400), 'chaine', 0, '', $conf->entity);
		}

		if ($rateLimitReset == 0) {
			// First request ever, set reset time to 24h from now
			dolibarr_set_const($this->db, 'DOLICRAFTALLO_RATE_LIMIT_RESET', (string)($now + 86400), 'chaine', 0, '', $conf->entity);
		}

		if ($rateLimitCount >= 950) { // Leave 50 margin
			$this->lastError = 'Allo API daily rate limit reached (1000 requests/day). Resets at '.dol_print_date($rateLimitReset, 'dayhour');
			dol_syslog(get_class($this).'::request Rate limit reached: '.$rateLimitCount.' requests', LOG_WARNING);
			return false;
		}

		// Endpoints starting with /v2/ carry their own version path; the others are v1 endpoints
		$url = (strpos($endpoint, '/v2/') === 0 ? $this->apiHost : $this->baseUrl).$endpoint;

		if (!empty($queryParams)) {
			$url .= '?'.http_build_query($queryParams);
		}

		$ch = curl_init();

		$headers = array(
			'Authorization: '.$this->apiKey,
			'Content-Type: application/json',
			'Accept: application/json',
		);

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

		switch (strtoupper($method)) {
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, true);
				if ($data !== null) {
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
				}
				break;
			case 'PATCH':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
				if ($data !== null) {
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
				}
				break;
			case 'PUT':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
				if ($data !== null) {
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
				}
				break;
			case 'DELETE':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
			case 'GET':
			default:
				break;
		}

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		// Increment rate limit counter
		$rateLimitCount++;
		dolibarr_set_const($this->db, 'DOLICRAFTALLO_RATE_LIMIT_COUNT', (string)$rateLimitCount, 'chaine', 0, '', $conf->entity);

		if ($curlError) {
			$this->lastError = 'cURL error: '.$curlError;
			dol_syslog(get_class($this).'::request '.$method.' '.$endpoint.' '.$this->lastError, LOG_ERR);
			return false;
		}

		if ($httpCode < 200 || $httpCode >= 300) {
			$decoded = json_decode($response, true);
			if (isset($decoded['error']['message'])) {
				// v2 format: {error: {code, message, suggestion}}
				$message = $decoded['error']['message'];
			} elseif (isset($decoded['message'])) {
				$message = $decoded['message'];
			} elseif (isset($decoded['code'])) {
				// v1 format: {code, details: [{message, field}]}
				$message = $decoded['code'];
				if (!empty($decoded['details'][0]['message'])) {
					$message .= ' ('.$decoded['details'][0]['message'].')';
				}
			} else {
				$message = $response;
			}
			$this->lastError = 'HTTP '.$httpCode.': '.$message;
			dol_syslog(get_class($this).'::request '.$method.' '.$endpoint.' '.$this->lastError, LOG_ERR);
			return false;
		}

		$decoded = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->lastError = 'JSON decode error: '.json_last_error_msg();
			return false;
		}

		return $decoded;
	}

	/**
	 * GET request
	 *
	 * @param string $endpoint API endpoint
	 * @param array $params    Query parameters
	 * @return mixed
	 */
	public function get($endpoint, $params = [])
	{
		return $this->request('GET', $endpoint, null, $params);
	}

	/**
	 * POST request
	 *
	 * @param string $endpoint API endpoint
	 * @param array $data      Request body
	 * @return mixed
	 */
	public function post($endpoint, $data = [])
	{
		return $this->request('POST', $endpoint, $data);
	}

	/**
	 * PATCH request
	 *
	 * @param string $endpoint API endpoint
	 * @param array $data      Request body
	 * @return mixed
	 */
	public function patch($endpoint, $data = [])
	{
		return $this->request('PATCH', $endpoint, $data);
	}

	/**
	 * Perform a PUT request
	 *
	 * @param  string $endpoint API endpoint
	 * @param  array  $data     Request body data
	 * @return mixed
	 */
	public function put($endpoint, $data = [])
	{
		return $this->request('PUT', $endpoint, $data);
	}

	/**
	 * DELETE request
	 *
	 * @param string $endpoint API endpoint
	 * @return mixed
	 */
	public function delete($endpoint)
	{
		return $this->request('DELETE', $endpoint);
	}

	/**
	 * Test the API connection
	 *
	 * @return array Array with 'success' (bool) and 'message' (string)
	 */
	public function testConnection()
	{
		$result = $this->get('/numbers');

		if ($result === false) {
			dol_syslog(get_class($this).'::testConnection failed: '.$this->lastError, LOG_ERR);
			return array(
				'success' => false,
				'message' => $this->lastError,
			);
		}

		return array(
			'success' => true,
			'message' => 'Connection successful',
			'data' => $result,
		);
	}

	/**
	 * Search call history
	 *
	 * @param string $alloNumber    Allo phone number
	 * @param int    $page          Page number (0-based)
	 * @param int    $size          Page size
	 * @param string $contactNumber Filter by contact number (optional)
	 * @return array|false
	 */
	public function searchCalls($alloNumber, $page = 0, $size = 50, $contactNumber = '')
	{
		$params = array(
			'allo_number' => $alloNumber,
			'page' => $page,
			'size' => $size,
		);

		if (!empty($contactNumber)) {
			$params['contact_number'] = $contactNumber;
		}

		return $this->get('/calls', $params);
	}

	/**
	 * Get contacts list (Allo CRM people, scope CRM_READ)
	 *
	 * @param int $page Page number (1-based)
	 * @param int $size Page size (max 100)
	 * @return array|false Response {data: [person, ...], pagination: {page, size, total_count, total_pages, has_more}}
	 */
	public function getContacts($page = 1, $size = 100)
	{
		return $this->post('/v2/api/crm/people/search', array(
			'page' => $page,
			'size' => $size,
		));
	}

	/**
	 * Get a single contact
	 *
	 * @param string $id Allo contact ID
	 * @return array|false
	 */
	public function getContact($id)
	{
		return $this->get('/v2/api/crm/people/'.urlencode($id));
	}

	/**
	 * Create a contact
	 *
	 * @param array $data Contact data
	 * @return array|false
	 */
	public function createContact($data)
	{
		return $this->post('/v2/api/crm/people', $data);
	}

	/**
	 * Update a contact
	 *
	 * @param string $id   Allo contact ID
	 * @param array  $data Contact data to update
	 * @return array|false
	 */
	public function updateContact($id, $data)
	{
		return $this->put('/v2/api/crm/people/'.urlencode($id), $data);
	}

	/**
	 * Search conversations for a contact
	 *
	 * @param string $contactId Allo contact ID
	 * @param int    $page      Page number (0-based)
	 * @param int    $size      Page size
	 * @return array|false
	 */
	public function searchConversations($contactId, $page = 0, $size = 50)
	{
		return $this->get('/contact/'.urlencode($contactId).'/conversation', array(
			'page' => $page,
			'size' => $size,
		));
	}

	/**
	 * Send an SMS
	 *
	 * @param string $from    Sender phone number (E.164)
	 * @param string $to      Recipient phone number (E.164)
	 * @param string $message SMS content
	 * @return array|false
	 */
	public function sendSms($from, $to, $message)
	{
		return $this->post('/sms', array(
			'from' => $from,
			'to' => $to,
			'message' => $message,
		));
	}

	/**
	 * Send an SMS from France using a Sender ID
	 *
	 * @param string $senderId Sender ID (alphanumeric, max 11 chars)
	 * @param string $to       Recipient phone number (E.164)
	 * @param string $message  SMS content
	 * @return array|false
	 */
	public function sendSmsFrance($senderId, $to, $message)
	{
		return $this->post('/sms', array(
			'sender_id' => $senderId,
			'to' => $to,
			'message' => $message,
		));
	}

	/**
	 * Get available phone numbers
	 *
	 * @return array|false
	 */
	public function getPhoneNumbers()
	{
		return $this->get('/numbers');
	}

	/**
	 * Create a webhook
	 *
	 * @param string $alloNumber Allo phone number
	 * @param string $url        Webhook URL
	 * @param array  $topics     List of event topics
	 * @param bool   $enabled    Whether the webhook is enabled
	 * @return array|false
	 */
	public function createWebhook($alloNumber, $url, $topics, $enabled = true)
	{
		return $this->post('/webhooks', array(
			'allo_number' => $alloNumber,
			'url' => $url,
			'topics' => $topics,
			'enabled' => $enabled,
		));
	}

	/**
	 * Get all webhooks
	 *
	 * @return array|false
	 */
	public function getWebhooks()
	{
		return $this->get('/webhooks');
	}

	/**
	 * Delete a webhook
	 *
	 * @param string $id Webhook ID
	 * @return array|false
	 */
	public function deleteWebhook($id)
	{
		return $this->delete('/webhooks/'.urlencode($id));
	}

	/**
	 * Get last error message
	 *
	 * @return string
	 */
	public function getLastError()
	{
		return $this->lastError;
	}
}
