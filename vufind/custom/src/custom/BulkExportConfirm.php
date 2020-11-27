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
			'type' => Element\Select::class,
			'attributes' => [
				'multiple' => 'multiple',
			],
			'name' => 'fields',
			'options' => [
				'disable_inarray_validator' => true,
			],
		]);
		
		$this->add([
             'type' => Element\Checkbox::class,
             'name' => 'primaryAbstract',
             'options' => [
                     'use_hidden_element' => true,
                     'checked_value' => 'yes',
                     'unchecked_value' => 'no',
             ],
		]);
		
		$this->add([
             'type' => Element\Checkbox::class,
             'name' => 'foreignAbstract',
             'options' => [
                     'use_hidden_element' => true,
                     'checked_value' => 'yes',
                     'unchecked_value' => 'no',
             ],
		]);
		
		$this->add([
             'type' => Element\Select::class,
             'name' => 'os',
             'options' => [
                     'value_options' => [
                             '0' => 'Windows',
                             '1' => 'Linux',
                             '2' => 'Mac OS',
                     ],
             ],
		]);
		
		$this->add([
            'type' => Element\Email::class,
            'name' => 'email',
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
				'class' => 'btn btn-primary',
            ],
        ]);
		
		$fieldsInput = [
			'name' => 'fields',
			'required' => false,
		];
		
		$foreignAbstractInput = [
			'name' => 'foreignAbstract',
			'required' => false,
		];
		
		$osInput = [
			'name' => 'os',
			'required' => false,
		];

		$captchaInput = new Input('captcha');
		$sendInput = new Input('send');
		
		$inputFilter = new InputFilter();
		$inputFilter->add($fieldsInput);
		$inputFilter->add($foreignAbstractInput);
		$inputFilter->add($osInput);
		$inputFilter->add($captchaInput);
		$inputFilter->add($sendInput);
		
		$this->setInputFilter($inputFilter);
    }
}