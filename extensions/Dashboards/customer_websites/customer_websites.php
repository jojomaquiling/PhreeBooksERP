<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/contacts/dashboards/customer_websites/customer_websites.php
//
// Revision history
// 2011-07-01 - Added version number for revision control

class customer_websites extends ctl_panel {
	public $dashboard_id 		= 'customer_websites';
	public $description	 		= CP_CUSTOMER_WEBSITES_DESCRIPTION;
	public $security_id  		= SECURITY_ID_MAINTAIN_CUSTOMERS;
	public $title		 		= CP_CUSTOMER_WEBSITES_TITLE;
	public $version      		= 3.5;

	function Output($params) {
		global $db;
		$sql = "select a.primary_name, a.website 
		  from " . TABLE_CONTACTS . " c left join " . TABLE_ADDRESS_BOOK . " a on c.id = a.ref_id 
		  where  c.type = 'c' and c.inactive = '0' and a.website !='' order by a.primary_name";
		$result = $db->Execute($sql);
		// Build control box form data
		$control = '';
		// Build content box
		$contents = '';
		if ($result->RecordCount() < 1) {
			$contents = ACT_NO_RESULTS;
		} else {
		  	while (!$result->EOF) {
				$contents .= '<div style="height:16px;">';
				$contents .= '  <a href=" http://'. $result->fields['website'] . '" target="_blank">' . $result->fields['primary_name'] . '</a>' . chr(10);
				$contents .= '</div>';
				$index++;
				$result->MoveNext();
		  	}
		} 
		return $this->build_div('', $contents, $control);
	}

}
?>