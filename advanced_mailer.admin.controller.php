<?php

class Advanced_MailerAdminController extends Advanced_Mailer
{
	public function procAdvanced_MailerAdminInsertConfig()
	{
		$config = $this->getRequestVars();
		$validation = $this->validateConfiguration($config);
		if ($validation !== true)
		{
			return new Object(-1, $validation);
		}
		
		// Update the webmaster's name and email in the member module.
		
		$args = (object)array(
			'webmaster_name' => $config->sender_name,
			'webmaster_email' => $config->sender_email,
		);
		$oModuleController = getController('module');
		$output = $oModuleController->updateModuleConfig('member', $args);
		
		// Save the new configuration.
		
		$output = getController('module')->insertModuleConfig('advanced_mailer', $config);
		if ($output->toBool())
		{
			$this->setMessage('success_registed');
		}
		else
		{
			return $output;
		}
		
		if (Context::get('success_return_url'))
		{
			$this->setRedirectUrl(Context::get('success_return_url'));
		}
		else
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'advanced_mailer', 'act', 'dispAdvanced_mailerAdminConfig'));
		}
	}
	
	public function procAdvanced_MailerAdminCheckDNSRecord()
	{
		$check_config = Context::gets('hostname', 'record_type');
		if (!preg_match('/^[a-z0-9_.-]+$/', $check_config->hostname))
		{
			$this->add('record_content', false);
			return;
		}
		if (!defined('DNS_' . $check_config->record_type))
		{
			$this->add('record_content', false);
			return;
		}
		
		$records = @dns_get_record($check_config->hostname, constant('DNS_' . $check_config->record_type));
		if ($records === false)
		{
			$this->add('record_content', false);
			return;
		}
		
		$return_values = array();
		foreach ($records as $record)
		{
			if (isset($record[strtolower($check_config->record_type)]))
			{
				$return_values[] = $record[strtolower($check_config->record_type)];
			}
		}
		$this->add('record_content', implode("\n\n", $return_values));
		return;
	}
	
	public function procAdvanced_MailerAdminTestSend()
	{
		$test_config = $this->getRequestVars();
		$test_config->send_type = preg_replace('/\W/', '', $test_config->send_type);
		
		$recipient_config = Context::gets('recipient_name', 'recipient_email');
		$recipient_name = $recipient_config->recipient_name;
		$recipient_email = $recipient_config->recipient_email;
		
		if (!class_exists('Mail'))
		{
			$this->add('test_result', 'Error: ' . Context::getLang('msg_advanced_mailer_cannot_find_mail_class'));
			return;
		}
		if (!method_exists('Mail', 'isAdvancedMailer') || !Mail::isAdvancedMailer())
		{
			$this->add('test_result', 'Error: ' . Context::getLang('msg_advanced_mailer_cannot_replace_mail_class'));
			return;
		}
		
		$validation = $this->validateConfiguration($test_config);
		if ($validation !== true)
		{
			$this->add('test_result', 'Error: ' . Context::getLang($validation));
			return;
		}
		
		if (!$recipient_name)
		{
			$this->add('test_result', 'Error: ' . Context::getLang('msg_advanced_mailer_recipient_name_is_empty'));
			return;
		}
		if (!$recipient_email)
		{
			$this->add('test_result', 'Error: ' . Context::getLang('msg_advanced_mailer_recipient_email_is_empty'));
			return;
		}
		if (!Mail::isVaildMailAddress($recipient_email))
		{
			$this->add('test_result', 'Error: ' . Context::getLang('msg_advanced_mailer_recipient_email_is_invalid'));
			return;
		}
		
		$previous_config = Mail::$config;
		Mail::$config = $test_config;
		
		try
		{
			$oMail = new Mail();
			$oMail->setTitle('Advanced Mailer Test');
			$oMail->setContent('<p>This is a <b>test email</b> from Advanced Mailer.</p><p>Thank you for trying Advanced Mailer.</p>');
			$oMail->setReceiptor($recipient_name, $recipient_email);
			$result = $oMail->send();
			
			Mail::$config = $previous_config;
			if (!$result)
			{
				if (count($oMail->errors))
				{
					$this->add('test_result', nl2br(htmlspecialchars(implode("\n", $oMail->errors))));
					return;
				}
				else
				{
					$this->add('test_result', 'An unknown error occurred.');
					return;
				}
			}
		}
		catch (Exception $e)
		{
			Mail::$config = $previous_config;
			$this->add('test_result', nl2br(htmlspecialchars($e->getMessage())));
			return;
		}
		
		$this->add('test_result', Context::getLang('msg_advanced_mailer_test_success'));
		return;
	}
	
	protected function getRequestVars()
	{
		$request_args = Context::getRequestVars();
		$args = new stdClass();
		$args->send_type = trim($request_args->send_type ?: 'mail');
		$args->smtp_host = trim($request_args->smtp_host ?: '');
		$args->smtp_port = trim($request_args->smtp_port ?: '');
		$args->smtp_security = trim($request_args->smtp_security ?: 'none');
		$args->username = trim($request_args->username ?: '');
		$args->password = trim($request_args->password ?: '');
		$args->domain = trim($request_args->domain ?: '');
		$args->api_key = trim($request_args->api_key ?: '');
		$args->aws_region = trim($request_args->aws_region ?: '');
		$args->aws_access_key = trim($request_args->aws_access_key ?: '');
		$args->aws_secret_key = trim($request_args->aws_secret_key ?: '');
		$args->sender_name = trim($request_args->sender_name ?: '');
		$args->sender_email = trim($request_args->sender_email ?: '');
		$args->reply_to = trim($request_args->reply_to ?: '');
		return $args;
	}
	
	protected function validateConfiguration($args)
	{
		switch ($args->send_type)
		{
			case 'mail':
				break;
			
			case 'smtp':
				if (!$args->smtp_host || !preg_match('/^[a-z0-9.-]+$/', $args->smtp_host))
				{
					return 'msg_advanced_mailer_smtp_host_is_invalid';
				}
				if (!$args->smtp_port || !ctype_digit($args->smtp_port))
				{
					return 'msg_advanced_mailer_smtp_port_is_invalid';
				}
				if (!in_array($args->smtp_security, array('none', 'ssl', 'tls')))
				{
					return 'msg_advanced_mailer_smtp_security_is_invalid';
				}
				if (!$args->username)
				{
					return 'msg_advanced_mailer_username_is_empty';
				}
				if (!$args->password)
				{
					return 'msg_advanced_mailer_password_is_empty';
				}
				break;
				
			case 'ses':
				if (!$args->aws_region || !preg_match('/^[a-z0-9.-]+$/', $args->aws_region))
				{
					return 'msg_advanced_mailer_aws_region_is_invalid';
				}
				if (!$args->aws_access_key)
				{
					return 'msg_advanced_mailer_aws_access_key_is_empty';
				}
				if (!$args->aws_secret_key)
				{
					return 'msg_advanced_mailer_aws_secret_key_is_empty';
				}
				break;
				
			case 'mailgun':
			case 'woorimail':
				if (!$args->domain)
				{
					return 'msg_advanced_mailer_domain_is_empty';
				}
				if (!$args->api_key)
				{
					return 'msg_advanced_mailer_api_key_is_empty';
				}
				break;
				
			case 'mandrill':
			case 'postmark':
				if (!$args->api_key)
				{
					return 'msg_advanced_mailer_api_key_is_empty';
				}
				break;
				
			case 'sendgrid':
				if (!$args->username)
				{
					return 'msg_advanced_mailer_username_is_empty';
				}
				if (!$args->password)
				{
					return 'msg_advanced_mailer_password_is_empty';
				}
				break;
				
			default:
				return 'msg_advanced_mailer_send_type_is_invalid';
		}
		
		// Validate the sender identity.
		
		if (!$args->sender_name)
		{
			return 'msg_advanced_mailer_sender_name_is_empty';
		}
		if (!$args->sender_email)
		{
			return 'msg_advanced_mailer_sender_email_is_empty';
		}
		if (!Mail::isVaildMailAddress($args->sender_email))
		{
			return 'msg_advanced_mailer_sender_email_is_invalid';
		}
		if ($args->reply_to && !Mail::isVaildMailAddress($args->reply_to))
		{
			return 'msg_advanced_mailer_reply_to_is_invalid';
		}
		
		return true;
	}
}
