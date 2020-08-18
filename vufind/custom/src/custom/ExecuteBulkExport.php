<?php

class ExecuteBulkExport
{
	protected $email;
	protected $sender;
	protected $subject;
	protected $msgTop;
	protected $msgBottom;
	protected $serviceUrl;
	protected $paramString;
	
	public function __construct ($params)
	{
		$paramsArray = explode('|', $params);
		
		$this->email = $paramsArray[0];
		$this->sender = $paramsArray[1];
		$this->subject = $paramsArray[2];
		$this->msgTop = $paramsArray[3];
		$this->msgBottom = $paramsArray[4];
		$this->serviceUrl = $paramsArray[5];
		$this->paramString = $paramsArray[6];
	}
	
	public function execute()
	{
		// Create the CSV file and get the download URL
		$response = $this->callExportService($this->serviceUrl, $this->paramString);
		
		// Email download URL
		$this->sendMail($response);
	}
	
	protected function callExportService($serviceUrl, $paramString)
	{	
		$params = array('queryString' => $paramString);
		$options = array(
			'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($params)
			)
		);
		
		$context  = stream_context_create($options);
		$response = file_get_contents($serviceUrl, false, $context);
		
		if ($response === FALSE) { 
			 throw new Exception(sprintf('Unexpected exception.'));
		}
		
		return $response;
	}
	
	protected function sendMail($link)
	{
		$message = $this->msgTop . ' ' . $link . $this->msgBottom;
		$headers = array(
			'From' => $this->sender,
			'Reply-To' => $this->sender,
			'MIME-Version' => '1.0',
			'Content-Type' => 'text/html; charset=utf-8',
			'X-Mailer' => 'PHP/' . phpversion()
		);

		$sent = mail($this->email, $this->subject, $message, $headers);
		
		if (!$sent) {
			$errorMessage = error_get_last()['message'];
			throw new Exception($errorMessage);
		}
	}
}

$obj = new ExecuteBulkExport($argv[1]);
$obj->execute();

?>