<?php

namespace custom;

use Zend\Captcha\AdapterInterface as CaptchaAdapter;
use Zend\Form\Element;
use Zend\Form\Form;
use Zend\InputFilter\Input;
use Zend\InputFilter\InputFilter;

class BulkExportConfirm extends Form
{
	protected $captcha;

    public function __construct(CaptchaAdapter $captcha)
    {
        parent::__construct();

        $this->captcha = $captcha;

        $this->add([
            'type' => Element\Email::class,
            'name' => 'email',
            'options' => [
                'label' => 'Email address: ',
            ],
        ]);
		
		$this->add([
            'type' => Element\Captcha::class,
            'name' => 'captcha',
            'options' => [
                'captcha' => $this->captcha,
            ],
        ]);
       
        $this->add([
            'name' => 'send',
            'type'  => 'Submit',
            'attributes' => [
                'value' => 'Submit',
				'class' => 'btn btn-primary',
            ],
        ]);

        $captchaInput = new Input('captcha');
		$sendInput = new Input('send');
		
		$inputFilter = new InputFilter();
		$inputFilter->add($captchaInput);
		$inputFilter->add($sendInput);
		
		$this->setInputFilter($inputFilter);
    }
}