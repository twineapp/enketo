<?php

/**
 * Copyright 2012 Martijn van de Rijdt
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class Unit_test extends CI_Controller {
 
	function __construct()
	{
		parent::__construct();
		$this->load->library('unit_test');
		$this->unit->set_test_items(array('test_name', 'result'));
	}

	public function index()
	{
		$groups = array('survey', 'form', 'instance', 'subdomain');
		foreach ($groups as $group)
		{
			$this->{$group}();
		}
	}

	//tests survey model
	public function survey()
	{
		$this->load->model('Survey_model', '', TRUE);

		$http = $this->Survey_model->launch_survey('http://testserver.com/bob', 'unit', 'http://testserver.com/bob/submission');
		$https = $this->Survey_model->launch_survey('https://testserver.com/bob', 'unit', 'https://testserver.com/bob/submission');
		$httpwww = $this->Survey_model->launch_survey('http://www.testserver.com/bob', 'unit', 'http://www.testserver.com/bob/submission');
		$httpswww = $this->Survey_model->launch_survey('https://www.testserver.com/bob', 'unit', 'https://www.testserver.com/bob/submission');

		$props = array('subdomain', 'url', 'edit_url', 'iframe_url');
		foreach ($props as $prop)
		{
			$test = ( strlen($http[$prop]) > 0 && ( $http[$prop] === $https[$prop] ) );
			$this->unit->run($test, TRUE, 'http and https server url return same '.$prop, 
				'http: '.$http[$prop].', https:'.$https[$prop]);
		}

		foreach ($props as $prop)
		{
			$test = ( strlen($httpwww[$prop]) > 0 && ( $httpwww[$prop] === $http[$prop] ) );
			$this->unit->run($test, TRUE, 'http://www.testserver.com and https://testserver.com server url return same '.$prop, 
				'httpwww: '.$httpwww[$prop].', http:'.$http[$prop]);
		}

		$props = array('edit_url'=>TRUE, 'iframe_url'=>TRUE, 'url'=>FALSE);
		$online_suffix = $this->Survey_model->ONLINE_SUBDOMAIN_SUFFIX;
		foreach ($props as $prop => $not_offline)
		{
			$results = array($http, $https);
			foreach ($results as $result)
			{
				$exp = $not_offline ? "true":"false";
				$test = (strpos($result[$prop], $result['subdomain'].$online_suffix) > 0 );
				$this->unit->run($test, $not_offline, 'applicationCache disabled by subdomain suffix for '.
					$prop.': '.$exp, 'url: '.$result[$prop]);
			}
		}

		echo $this->unit->report();
	}

	//tests form model
	public function form()
	{
		$this->load->model('Form_model', '', TRUE);

		$xml_path = '../devinfo/Forms/gui_test_A.xml';		
		$html_save_result_path = '../devinfo/Forms/gui_test_A_test_transform.html';
		$result = $this->Form_model->transform(null, null, $xml_path);
		$result_html = new DOMDocument;
		$result_html->loadHTML($result->form->asXML());
		$result_form = $result_html->saveHTML();
		file_put_contents($html_save_result_path, $result_form);

		$expected = new DOMDocument;
    	$expected->loadHTMLFile('../devinfo/Forms/gui_test_A.html');
    	$expected_form = $expected->saveHTML();
		
    	$test = ((string) $result_form == (string) $expected_form);

		$this->unit->run($test, TRUE, 'in a complex test form (but without itemsets), the transformation is done correctly');

		echo $this->unit->report();
		//$diff = xdiff_string_diff($expected_form, $result_form, 1);
		if (!$test)
		{
			//echo 'transformation result:'.$result_form;
			//echo 'transformation expected:'.$expected_form;
		}
	}

	public function generate_js_test_form_mocks()
	{
		$xml_forms = array(
			'thedata.xml',
			'issue208.xml', 
			'cascading_mixture_itext_noitext.xml', 
			'new_cascading_selections.xml',
			'new_cascading_selections_inside_repeats.xml',
			'outputs_in_repeats.xml',
			'nested_repeats.xml',
			'calcs.xml',
			'readonly.xml',
			'calcs_in_repeats.xml'
		);
		$xml_forms_path = '../devinfo/Forms/';
		$save_result_path = '../js_tests/mocks/transforms.mock.js';

		$this->load->model('Form_model', '', TRUE);

		$mocks_js = "//These forms are generated by the php unit_test controller -> generate_js_test_form_mocks()\n".
			"//original xml forms are located in /devinfo/Forms\n\n".
			"var mockForms1 =\n{\n";

		foreach ($xml_forms as $xml_form)
		{
			$full_path = $xml_forms_path.$xml_form;
			$result = $this->Form_model->get_transform_result_sxe($full_path);
			$mocks_js .=  "\t'".$xml_form."':\n\t{\n".
				"\t\t'html_form' : '".preg_replace(array('/\>\s+\</',"/\'/"),array('><','&quot;'),$result->form->asXML())."',\n".
				"\t\t'xml_model': '".preg_replace(array('/\>\s+\</',"/\'/"),array('><','&quot;'),$result->model->asXML())."'\n\t},\n";
		}
		$mocks_js = substr($mocks_js, 0, -2)."\n};";
		
		$save_result = file_put_contents($save_result_path, $mocks_js);
		
		if ($save_result !== FALSE) { echo 'Form Strings Generated!'; }
		else {echo'Something went wrong...';}
	}

	public function generate_drishti_form_mocks()
	{
		$server_url = "http://formhub.org/drishti_forms";
		$this->load->model('Form_model', '', TRUE);
		$save_result_path = '../drishti/mocks/transforms.mock.js';
		$list = $this->Form_model->get_formlist_JSON($server_url);
		$mocks_js = "//These forms are generated by the php unit_test controller -> generate_drishti_form_mocks()\n".
			"//from all forms in http://formhub.org/dristhi_forms\n\n".
			"var mockForms2 =\n{\n";

		foreach ($list as $form_id => $stuff)
		{
			$this->Form_model->setup($server_url, $form_id);
			$result = $this->Form_model->get_transform_result_sxe;
			$mocks_js .=  "\t'".$form_id."':\n\t{\n".
				"\t\t'html_form' : '".preg_replace(array('/\>\s+\</',"/\'/"),array('><','&quot;'),$result->form->asXML())."',\n".
				"\t\t'xml_model': '".preg_replace(array('/\>\s+\</',"/\'/"),array('><','&quot;'),$result->model->asXML())."'\n\t},\n";
			echo "performed transformation on ".$form_id."<br/>";
		}
		$mocks_js = substr($mocks_js, 0, -2)."\n};";
		
		$save_result = file_put_contents($save_result_path, $mocks_js);
		
		if ($save_result !== FALSE) { echo "<br />All Forms Transformed and saved!"; }
		else {echo'Something went wrong...';}
	}

	//tests instance model
	public function instance()
	{
		$instance_received = '<?xml version="1.0" ?><backtobasic id="b2b_1"><formhub><uuid>71f440123a264629a696e5dfd6415fda</uuid></formhub><text>text entered in Enketo</text><meta><instanceID>uuid:a1a5fa9c3f51492eb282cd46c9018b9f</instanceID></meta></backtobasic>';
		$subdomain = 'aaaaaa';
		$id = '123';
		$this->load->model('Instance_model', '', TRUE);
		$result = $this->Instance_model->insert_instance($subdomain, $id, $instance_received, 'www.example.com');

		$this->unit->run($result !== NULL, TRUE, 'Instance-to-edit is saved');

		$result = $this->Instance_model->insert_instance($subdomain, $id, $instance_received, 'www.example.com');

		$this->unit->run($result, NULL, 'Instance-to-edit is not saved as it already existed (edits ongoing)');
		
		$result = $this->Instance_model->get_instance($subdomain, $id);

		$this->unit->run($result->instance_xml, $instance_received, 'Instance-to-edit is retrieved from db)');

		echo $this->unit->report();
	}

	//tests subdomain helper
	public function subdomain()
	{

	}
}
?>
