<?php
 
namespace custom\Controller;

use custom\BulkExportConfirm;

use VuFind\Controller\AbstractBase;

use VuFindSearch\Backend\Solr\QueryBuilder;
use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;
use VuFindSearch\Exception\InvalidArgumentException;
use VuFindSearch\ParamBag;

use Zend\Captcha\ReCaptcha;
 
class BulkExportController extends AbstractBase
{
	protected $spellcheck = false;
	protected $mainConf = 'main';
	protected $searchConf = 'searches';
	protected $bulkExportConf = 'bulkexport';
	
	public function homeAction()
	{		
		// Display the export form
		$form = new BulkExportConfirm($this->getCaptcha());
		
		return $this->createViewModel(['form' => $form]);
	}
	
	public function csvAction()
    {	
		$form = new BulkExportConfirm($this->getCaptcha());
		$data = $this->params()->fromPost();
		$form->setData($data);
		
		// Use the captcha input to validate the form data
		if ($form->isValid()) {		
			$exportConfig = $this->getConf($this->bulkExportConf);
			
			// Export service params
			$backgroundCall = $exportConfig->Service->backgroundClass;
			$serviceUrl = $exportConfig->Service->serviceUrl;
			$paramString = $this->getParamString();
			
			// Email params
			$email = $form->get('email')->getValue();
			$sender = $exportConfig->Mail->senderAddress;
			$subject = $exportConfig->Mail->mailSubject;
			$msgTop = $exportConfig->Mail->msgTop;
			$msgBottom = $exportConfig->Mail->msgBottom;
			
			// Call the export service in background
			$params = '"' . $email . '|' . $sender . '|' . $subject . '|' . $msgTop . '|' . $msgBottom . '|' . $serviceUrl . '|' . $paramString . '"';
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
           
			return $this->createViewModel();
		} else {
			// Captcha validation failed, ask the user to try again
			$serverUrlHelper = $this->getViewRenderer()->plugin('serverurl');
			$urlHelper = $this->getViewRenderer()->plugin('url');
			$backUrl = $serverUrlHelper($urlHelper('bulkexport-home'));
			$params = ['url' => $backUrl];
			$msg = ['translate' => false, 'html' => true, 'msg' => $this->getViewRenderer()->render('bulkexport/captcha-error.phtml', $params)];
			$this->flashMessenger()->addMessage($msg, 'error');
			
			return $this->createViewModel();
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