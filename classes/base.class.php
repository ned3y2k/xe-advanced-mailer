<?php

namespace Advanced_Mailer;

class Base
{
	/**
	 * Properties for compatibility with XE Mail class
	 */
	public $content = '';
	public $content_type = 'html';
	public $attachments = array();
	public $cidAttachments = array();
	
	/**
	 * Properties used by Xternal Mailer
	 */
	public static $config = array();
	public $errors = array();
	public $message = NULL;
	public $assembleMessage = true;
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		include_once dirname(__DIR__) . '/vendor/autoload.php';
		$this->message = \Swift_Message::newInstance();
		if(self::$config->sender_email)
		{
			$sender_name = self::$config->sender_name ?: 'webmaster';
			$this->message->setFrom(array(self::$config->sender_email => $sender_name));
		}
		if(self::$config->reply_to)
		{
			$this->message->setReplyTo(array(self::$config->reply_to));
		}
	}
	
	/**
	 * Method for checking whether this class is from Advanced Mailer
	 */
	public function isAdvancedMailer()
	{
		return true;
	}
	
	/**
	 * Set parameters for using Gmail
	 */
	public function useGmailAccount()
	{
		// no-op
	}
	
	/**
	 * Set parameters for using SMTP protocol
	 */
	public function useSMTP()
	{
		// no-op
	}
	
	/**
	 * Set additional parameters
	 */
	public function setAdditionalParams($additional_params)
	{
		// no-op
	}
	
	/**
	 * Set Sender (From:)
	 *
	 * @param string $name Sender name
	 * @param string $email Sender email address
	 * @return void
	 */
	public function setSender($name, $email)
	{
		$this->message->setFrom(array($email => $name));
	}
	
	/**
	 * Get Sender (From:)
	 *
	 * @return string
	 */
	public function getSender()
	{
		$from = $this->message->getFrom();
		foreach($from as $email => $name)
		{
			if($name === '')
			{
				return $email;
			}
			else
			{
				return $name . ' <' . $email . '>';
			}
		}
		return FALSE;
	}
	
	/**
	 * Set Recipient (To:)
	 *
	 * @param string $name Recipient name
	 * @param string $email Recipient email address
	 * @return void
	 */
	public function setReceiptor($name, $email)
	{
		$this->message->setTo(array($email => $name));
	}
	
	/**
	 * Get Recipient (To:)
	 *
	 * @return string
	 */
	public function getReceiptor()
	{
		$to = $this->message->getTo();
		foreach($to as $email => $name)
		{
			if($name === '')
			{
				return $email;
			}
			else
			{
				return $name . ' <' . $email . '>';
			}
		}
		return FALSE;
	}
	
	/**
	 * Set Subject
	 *
	 * @param string $subject The subject
	 * @return void
	 */
	public function setTitle($subject)
	{
		$this->message->setSubject($subject);
	}
	
	/**
	 * Get Subject
	 *
	 * @return string
	 */
	public function getTitle()
	{
		return $this->message->getSubject();
	}
	
	/**
	 * Set BCC
	 *
	 * @param string $bcc
	 * @return void
	 */
	public function setBCC($bcc)
	{
		$this->message->setBcc(array($bcc));
	}
	
	/**
	 * Set ReplyTo
	 *
	 * @param string $replyTo
	 * @return void
	 */
	public function setReplyTo($replyTo)
	{
		$this->message->setReplyTo(array($replyTo));
	}
	
	/**
	 * Set Return Path
	 *
	 * @param string $returnPath
	 * @return void
	 */
	public function setReturnPath($returnPath)
	{
		$this->message->setReturnPath($returnPath);
	}
	
	/**
	 * Set Message ID
	 *
	 * @param string $messageId
	 * @return void
	 */
	public function setMessageID($messageId)
	{
		$this->message->getHeaders()->get('Message-ID')->setId($messageId);
	}
	
	/**
	 * Set references
	 *
	 * @param string $references
	 * @return void
	 */
	public function setReferences($references)
	{
		$headers = $this->message->getHeaders();
		$headers->addTextHeader('References', $references);
	}
	
	/**
	 * Set message content
	 *
	 * @param string $content Content
	 * @return void
	 */
	public function setContent($content)
	{
		$content = preg_replace_callback('/<img([^>]+)>/i', array($this, 'replaceResourceRealPath'), $content);
		$this->content = $content;
	}
	
	/**
	 * Set the type of message content (html or plain text)
	 * 
	 * @param string $mode The type
	 * @return void
	 */
	public function setContentType($type = 'html')
	{
		$this->content_type = $type === 'html' ? 'html' : '';
	}
	
	/**
	 * Get the Plain content of body message
	 *
	 * @return string
	 */
	public function getPlainContent()
	{
		return chunk_split(base64_encode(str_replace(array("<", ">", "&"), array("&lt;", "&gt;", "&amp;"), $this->content)));
	}
	
	/**
	 * Get the HTML content of body message
	 * 
	 * @return string
	 */
	public function getHTMLContent()
	{
		return chunk_split(base64_encode($this->content_type != 'html' ? nl2br($this->content) : $this->content));
	}
	
	/**
	 * Add file attachment
	 *
	 * @param string $filename File name to attach
	 * @param string $original_filename Real path of file to attach
	 * @return void
	 */
	public function addAttachment($filename, $original_filename)
	{
		$this->attachments[$original_filename] = $filename;
	}
	
	/**
	 * Add content attachment
	 *
	 * @param string $original_filename Real path of file to attach
	 * @param string $cid Content-CID
	 * @return void
	 */
	public function addCidAttachment($original_filename, $cid)
	{
		$this->cidAttachments[$cid] = $original_filename;
	}
	
	/**
	 * Replace resourse path of the files
	 *
	 * @see Mail::setContent()
	 * @param array $matches Match info.
	 * @return string
	 */
	public function replaceResourceRealPath($matches)
	{
		return preg_replace('/src=(["\']?)files/i', 'src=$1' . Context::getRequestUri() . 'files', $matches[0]);
	}
	
	/**
	 * Process the images from attachments
	 *
	 * @return void
	 */
	public function procAttachments()
	{
		// no-op
	}
	
	/**
	 * Process the images from body content. This functions is used if Mailer is set as mail not as SMTP
	 * 
	 * @return void
	 */
	public function procCidAttachments()
	{
		// no-op
	}
	
	/**
	 * Process the message before sending
	 * 
	 * @return void
	 */
	public function procAssembleMessage()
	{
		foreach($this->attachments as $original_filename => $filename)
		{
			$attachment = \Swift_Attachment::fromPath($original_filename);
			$attachment->setFilename($filename);
			$this->message->attach($attachment);
		}
		foreach($this->cidAttachments as $cid => $original_filename)
		{
			$embedded = \Swift_EmbeddedFile::fromPath($original_filename);
			$newcid = $this->message->embed($embedded);
			$this->content = str_replace(array("cid:$cid", $cid), $newcid, $this->content);
		}
		$content_type = $this->content_type === 'html' ? 'text/html' : 'text/plain';
		$this->message->setBody($this->content, $content_type);
	}
	
	/**
	 * Send email
	 * 
	 * @return bool
	 */
	public function send()
	{
		$send_type = self::$config->send_type;
		
		include_once __DIR__ . '/' . strtolower($send_type) . '.class.php';
		$subclass_name = __NAMESPACE__ . '\\' . ucfirst($send_type);
		$subclass = new $subclass_name();
		$data = get_object_vars($this);
		foreach($data as $key => $value)
		{
			$subclass->$key = $value;
		}
		if($subclass->assembleMessage)
		{
			$subclass->procAssembleMessage();
		}
		
		$result = $subclass->send();
		$this->errors = $subclass->errors;
		return $result;
	}
	
	/**
	 * Check if DNS of param is real or fake
	 * 
	 * @param string $email_address Email address to check
	 * @return bool
	 */
	public function checkMailMX($email_address)
	{
		if(!self::isVaildMailAddress($email_address))
		{
			return FALSE;
		}
		list($user, $host) = explode("@", $email_address);
		if(function_exists('checkdnsrr'))
		{
			if(checkdnsrr($host, "MX") || checkdnsrr($host, "A"))
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}
		return TRUE;
	}
	
	/**
	 * Check if param is a valid email or not
	 * 
	 * @param string $email_address Email address to check
	 * @return string
	 */
	public function isVaildMailAddress($email_address)
	{
		if(preg_match("/([a-z0-9\_\-\.]+)@([a-z0-9\_\-\.]+)/i", $email_address))
		{
			return $email_address;
		}
		else
		{
			return '';
		}
	}
}
