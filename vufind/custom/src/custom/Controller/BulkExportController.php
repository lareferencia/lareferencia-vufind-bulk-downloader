<?php
 
namespace custom\Controller;

use custom\BulkExportConfirm;

use VuFind\Controller\AbstractBase;

use VuFindSearch\Backend\Solr\QueryBuilder;
use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;
use VuFindSearch\Exception\InvalidArgumentException;
use VuFindSearch\ParamBag;

use Zend\Http\Client as HttpClient;
use Zend\Http\Request;

use Zend\Captcha\ReCaptcha;
 
class BulkExportController extends AbstractBase
{
	protected $spellcheck = false;
	protected $mainConf = 'main';
	protected $searchConf = 'searches';
	protected $bulkExportConf = 'bulkexport';
	
	public function homeAction()
	{		
		// Get the number of records returned in the search
		$totalRecords = $this->params()->fromQuery('total');
		
		// Get the query options
		$exportConfig = $this->getConf($this->bulkExportConf);
		$useForeignAbstract = $exportConfig->Query->showForeignAbstractOption;
		$showOptionalFields = $exportConfig->Query->showOptionalFields;
		$defaultFields = $exportConfig->Query->defaultFields;
		$selectFields = $exportConfig->Query->selectFields;
		$showEncodingOption = $exportConfig->Encoding->showEncodingOption;

		// Display the export form
		$form = new BulkExportConfirm($this->getCaptcha());
		
		return $this->createViewModel(['form' => $form, 
										'total' => $totalRecords, 
										'foreignAbstract' => $useForeignAbstract, 
										'optionalFields' => $showOptionalFields,
										'defFields' => $defaultFields, 
										'selFields' => $selectFields,
										'encodingOption' => $showEncodingOption]);
	}
	
	public function csvAction()
    {	
		// Routing helpers
		$serverUrlHelper = $this->getViewRenderer()->plugin('serverurl');
		$urlHelper = $this->getViewRenderer()->plugin('url');
		
		// Retrieve form data
		$form = new BulkExportConfirm($this->getCaptcha());
		$data = $this->params()->fromPost();		
		$form->setData($data);
		
		// Use the captcha input to validate the form data
		if ($form->isValid()) {		
			$email = $form->get('email')->getValue();
			
			// Build the field list
			$exportConfig = $this->getConf($this->bulkExportConf);
			$defaultFields = explode(',', $exportConfig->Query->defaultFields);
			$showOptionalFields = $exportConfig->Query->showOptionalFields;
			$fullFieldList = $defaultFields;
			
			if ($showOptionalFields){
				$selectFields = explode(',', $exportConfig->Query->selectFields);
				$fields = $form->get('fields')->getValue();
			
				if(!empty($fields)){
					foreach ($fields as $field){
						array_push($fullFieldList, $selectFields[$field]);
					}	
				}
			}	
			
			// Include abstract fields
			$useForeignAbstract = $exportConfig->Query->showForeignAbstractOption;
			$primaryAbstract = $form->get('primaryAbstract')->getValue();
			$hasAbstract = 'false';
			
			if($primaryAbstract == 'yes'){
				array_push($fullFieldList, $exportConfig->Query->primaryLangAbstract);
				$hasAbstract = 'true';
			}
			
			if ($useForeignAbstract){
				$foreignAbstract = $form->get('foreignAbstract')->getValue();
				
				if($foreignAbstract == 'yes'){
					array_push($fullFieldList, $exportConfig->Query->foreignLangAbstract);
					$hasAbstract = 'true';
				}
			}
			
			// Define file encoding
			$encoding = $exportConfig->Encoding->defaultEncoding;
			$showEncodingOption = $exportConfig->Encoding->showEncodingOption;
			
			if ($showEncodingOption) {
				$os = $form->get('os')->getValue();
						
				if ($os == 0) {
					$encoding = 'ISO-8859'; // Windows encoding
				}
				else {
					$encoding = 'UTF-8'; // Linux and Mac OS encoding
				}
			}	
			
			// Export service params
			$serviceUrl = $exportConfig->Service->serviceUrl;
			$auxServUrl = $exportConfig->Service->auxServUrl;
			$paramString = $this->getParamString();
			$paramString .= '&fl=' . implode(',', $fullFieldList);
			$paramString .= '&encoding=' . $encoding;

			// Checks whether an export file generated from this query already exists
			$fileExists = $this->callExportService($auxServUrl, $paramString, null, null, null, null);
							
			$maxTotal = $exportConfig->Query->maxDownload;
			$queryLimit = $exportConfig->Query->rows;
			$totalRecords = $this->params()->fromQuery('total');
			$totalRecords = $totalRecords < $queryLimit ? $totalRecords : $queryLimit;
			
			if (($totalRecords <= $maxTotal) or ($fileExists == 'true')) {
				// Immediate file download
				$response = $this->callExportService($serviceUrl, $paramString, $totalRecords, $hasAbstract, $encoding, $email);
		
				// After export file is ready, show download window
				$downloadUrl = $serverUrlHelper($urlHelper('bulkexport-download')). '?url=' . $response;
				$params = ['exportType' => 'link', 'url' => $downloadUrl];
				$msg = ['translate' => false, 'html' => true, 'msg' => $this->getViewRenderer()->render('cart/export-success.phtml', $params)];
				$this->flashMessenger()->addMessage($msg, 'success');
			}
			else {
				// File download link sent later by email
				$backgroundCall = $exportConfig->Service->backgroundClass;
			
				// Call the export service in background
				$params = '"' . $email . '|' . $serviceUrl . '|' . $paramString . '|' . $totalRecords . '|' .  $hasAbstract . '|' . $encoding . '"';
				$cmd = 'php ' . $backgroundCall . ' ' . $params;
		
				if (substr(php_uname(), 0, 7) == 'Windows'){
					pclose(popen('start /B ' . $cmd, 'r')); 
				}
				else {
					exec($cmd . ' > /dev/null &');  
				}
			
				$params = ['email' => $email];
				$msg = ['translate' => false, 'html' => true, 'msg' => $this->getViewRenderer()->render('bulkexport/captcha-success.phtml', $params)];
				$this->flashMessenger()->addMessage($msg, 'success');
			}
		} else {
			// Captcha validation failed, ask the user to try again
			$backUrl = $serverUrlHelper($urlHelper('bulkexport-home'));
			$params = ['url' => $backUrl];
			$msg = ['translate' => false, 'html' => true, 'msg' => $this->getViewRenderer()->render('bulkexport/captcha-error.phtml', $params)];
			$this->flashMessenger()->addMessage($msg, 'error');
		}
		return $this->createViewModel();
    }
	
	public function downloadAction()
	{
		$downloadUrl = $this->params()->fromQuery('url');
		$client = $this->createClient($downloadUrl, Request::METHOD_GET);
		
        try {
            $response = $client->send();

			if (!$response->isSuccess()) {
				throw HttpErrorException::createFromResponse($response);
			}
			
			return $response;
        
		} catch (Exception $ex) {
            sprintf('Unexpected exception.');
        }
	}
	
	protected function getCaptcha()
	{
		$captcha = new ReCaptcha();
		
		// Configure the reCaptcha
		$exportConfig = $this->getConf($this->bulkExportConf);
		$siteKey = $exportConfig->Captcha->siteKey;
		$secretKey = $exportConfig->Captcha->secretKey;
		
		$captcha->setSiteKey($siteKey);
		$captcha->setSecretKey($secretKey);
		
		return $captcha;	
	}
	
	protected function getParamString()
	{
		//Retrieve search params
		$searchClassId = $this->params()->fromQuery('searchClassId', DEFAULT_SEARCH_BACKEND);
		$searchHelper = $this->getViewRenderer()->plugin('searchMemory');
		$search = $searchHelper->getLastSearchParams($searchClassId);
		$params = $search->getBackendParameters();
		$query = $search->getQuery();
		
		//Inject further backend params	
		$this->injectResponseWriter($params);
		$this->injectSpellingParams($params);
		$this->injectConditionalFilter($params);
		$this->injectUserCustomParams($params);
		
		//Build query params string
		$builder = $this->getQueryBuilder();
		$params->mergeWith($builder->build($query));		
		$paramString = implode('&', $params->request());
		
		return $paramString;
	}
	
	protected function getQueryBuilder()
	{
		//Get search specifications for the builder
		$specs = $this->loadSpecs();
		$mainConfig = $this->getConf($this->mainConf);
		$defaultDismax = isset($mainConfig->Index->default_dismax_handler) ? $mainConfig->Index->default_dismax_handler : 'dismax';
		$builder = new QueryBuilder($specs, $defaultDismax);
		
		//Configure builder
		$searchConfig = $this->getConf($this->searchConf);
		$caseSensitiveBooleans = isset($searchConfig->General->case_sensitive_bools) ? $searchConfig->General->case_sensitive_bools : true;
        $caseSensitiveRanges = isset($searchConfig->General->case_sensitive_ranges) ? $searchConfig->General->case_sensitive_ranges : true;
		$helper = new LuceneSyntaxHelper($caseSensitiveBooleans, $caseSensitiveRanges);
		$builder->setLuceneHelper($helper);
		
		if ($this->spellcheck){
			$builder->setCreateSpellingQuery(true);
		}
		else{
			$builder->setCreateSpellingQuery(false);
		}
		
		return $builder;
	}
	
	protected function injectSpellingParams(ParamBag $params)
	{
		$mainConfig = $this->getConf($this->mainConf);
		
		// If spellcheck is enabled, retrieve params
		if ($mainConfig->Spelling->enabled ?? true) {
            $dictionaries = ($mainConfig->Spelling->simple ?? false) ? ['basicSpell'] : ['default', 'basicSpell'];
			$sc = $params->get('spellcheck');
			
			if (isset($sc[0]) && $sc[0] != 'false') {
                if (empty($dictionaries)) {
                    throw new Exception('Spellcheck requested but no dictionary configured');
                }

                // Set relevant Solr parameters:
                reset($dictionaries);
                $params->set('spellcheck', 'true');
                $params->set('spellcheck.dictionary', current($dictionaries));

                // Turn on spellcheck.q generation in query builder:
                $this->spellcheck = true;
            }
        }
	}
	
	protected function injectConditionalFilter(ParamBag $params)
	{
		$searchConfig = $this->getConf($this->searchConf);
		$filterList = [];
		
		// Add conditional filters
		if (isset($searchConfig->ConditionalHiddenFilters) && $searchConfig->ConditionalHiddenFilters->count() > 0) {
            foreach ($searchConfig as $fc) {
				$this->addConditionalFilter($fc, $filterList);
			}

			$fq = $params->get('fq');
			
			if (!is_array($fq)) {
				$fq = [];
			}
			
			$new_fq = array_merge($fq, $filterList);
			$params->set('fq', $new_fq);
        }
	}
	 
	protected function injectResponseWriter(ParamBag $params)
    {
        // Define JSON as the output format
		if (array_diff($params->get('wt') ?: [], ['json'])) {
            throw new InvalidArgumentException(sprintf('Invalid response writer type: %s', implode(', ', $params->get('wt'))));
        }
        if (array_diff($params->get('json.nl') ?: [], ['arrarr'])) {
            throw new InvalidArgumentException(sprintf('Invalid named list implementation type: %s',implode(', ', $params->get('json.nl'))));
        }
		
        $params->set('wt', ['json']);
        $params->set('json.nl', ['arrarr']);
    }
	
	protected function injectUserCustomParams(ParamBag $params)
	{
		// Get the user-defined parameters specified in the bulkexport.ini config file
		$exportConfig = $this->getConfig($this->bulkExportConf);
		$rows = $exportConfig->Query->rows;
		$params->set('rows', $rows);
	}
	
	protected function addConditionalFilter($configOption, $filterList)
    {
        $filterArr = explode('|', $configOption);
        $filterCondition = $filterArr[0];
        $filter = $filterArr[1];

        // if the filter condition starts with a minus (-), it should not match to get the filter applied
        if (substr($filterCondition, 0, 1) == '-') {
            $filterList[] = $filter;  
        } else {
            // otherwise the condition should match to apply the filter
            $filterList[] = $filter;
        }
    }
	
	protected function callExportService($serviceUrl, $paramString, $totalRecords, $hasAbstract, $encoding, $email)
	{	
		$client = $this->createClient($serviceUrl, Request::METHOD_POST);
		$client->setParameterPost(['queryString' => $paramString, 
								   'download' => 'true', 
								   'totalRecords' => $totalRecords,
								   'hasAbstract' => $hasAbstract,
								   'encoding' => $encoding, 
								   'userEmail' => $email]);
        $client->setEncType(HttpClient::ENC_URLENCODED);

        try {
            $response = $client->send();
			
			if (!$response->isSuccess()) {
				throw HttpErrorException::createFromResponse($response);
			}
			
			return $response->getBody();
        
		} catch (Exception $ex) {
            sprintf('Unexpected exception.');
        }
	}
	
	protected function createClient($url, $method)
    {
        $exportConfig = $this->getConf($this->bulkExportConf);
		$timeout = $exportConfig->Service->timeout;
		
		$client = new HttpClient();
        $client->setAdapter('Zend\Http\Client\Adapter\Socket');
        $client->setOptions(['timeout' => $timeout]);
        $client->setUri($url);
        $client->setMethod($method);
		
		if ($this->serviceLocator->has(\VuFindHttp\HttpService::class)) {
            $proxy = $this->serviceLocator->get(\VuFindHttp\HttpService::class);
			$proxy->proxify($client);
        }
		
        return $client;
    }
	
	protected function loadSpecs()
    {
        $specs = $this->serviceLocator->get(\VuFind\Config\SearchSpecsReader::class);
		return $specs->get('searchspecs.yaml');
    }
	
	protected function getConf($file)
	{
		$config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class);
		return $config->get($file);
	}

}