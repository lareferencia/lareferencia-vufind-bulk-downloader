<?php

class ExecuteBulkExport
{
	protected $email;
	protected $serviceUrl;
	protected $paramString;
	protected $totalRecords;
	protected $hasAbstract;
	protected $encoding;
	
	public function __construct ($params)
	{
		$paramsArray = explode('|', $params);
		
		$this->email = $paramsArray[0];
		$this->serviceUrl = $paramsArray[1];
		$this->paramString = $paramsArray[2];
		$this->totalRecords = intval($paramsArray[3]);
		$this->hasAbstract = filter_var($paramsArray[4], FILTER_VALIDATE_BOOLEAN);
		$this->encoding = $paramsArray[5];
	}
	
	public function execute()
	{
		// Call the bulk downloader service to create the CSV file
		$params = ['queryString' => $this->paramString, 
				   'download' => 'false', 
				   'totalRecords' => $this->totalRecords,
				   'hasAbstract' => $this->hasAbstract,
				   'encoding' => $this->encoding, 
				   'userEmail' => $this->email];
		$options = [
			'http' => [
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($params)
			]
		];
		
		$context  = stream_context_create($options);
		$response = file_get_contents($this->serviceUrl, false, $context);
		
		if ($response === FALSE) { 
			 throw new Exception(sprintf('Unexpected exception.'));
		}
	}
}

$obj = new ExecuteBulkExport($argv[1]);
$obj->execute();

?>