<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2015 PhreeSoft      (www.PhreeSoft.com)       |
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
//  Path: /modules/contacts/classes/contacts.php
//
namespace contacts\classes;
class contacts {
	public  $terms_type         = 'AP';
	public  $title;
	public  $page_title_new;
	public  $page_title_edit;
	public  $auto_type          = false;
	public  $inc_auto_id 		= false;
	public  $auto_field         = '';
	public  $help		        = '';
	public  $tab_list           = array();
	public  $address_types      = array();
	public  $type               = '';
	public  $crm_log			= array();
	public  $crm_date           = '';
	public  $crm_rep_id         = '';
    public  $crm_action         = '';
    public  $crm_note           = '';
    public  $payment_cc_name    = '';
    public  $payment_cc_number  = '';
    public  $payment_exp_month  = '';
    public  $payment_exp_year   = '';
    public  $payment_cc_cvv2    = '';
    public  $special_terms      = '0';
    private $duplicate_id_error = ACT_ERROR_DUPLICATE_ACCOUNT;
    private $sql_data_array     = array();

    public function __construct(){
    	global $admin;
    	$this->page_title_new  = sprintf(TEXT_NEW_ARGS, $this->title);
    	$this->page_title_edit = sprintf(TEXT_EDIT_ARGS, $this->title);
    	//set defaults
        $this->crm_date        = date('Y-m-d');
        $this->crm_rep_id      = $_SESSION['account_id'] <> 0 ? $_SESSION['account_id'] : $_SESSION['admin_id'];
        foreach ($_POST as $key => $value) $this->$key = db_prepare_input($value);
        $this->special_terms  =  db_prepare_input($_POST['terms']); // TBD will fix when popup terms is redesigned
        if ($this->id  == '') $this->id  = db_prepare_input($_POST['rowSeq'], true) ? db_prepare_input($_POST['rowSeq']) : db_prepare_input($_GET['cID']);
        if ($this->aid == '') $this->aid = db_prepare_input($_GET['aID'],     true) ? db_prepare_input($_GET['aID'])     : db_prepare_input($_POST['aID']);
    }

	public function getContact() {
	  	global $admin;
	  	if ($this->id == '' && !$this->aid == ''){
	  		$result = $admin->DataBase->query("select * from ".TABLE_ADDRESS_BOOK." where address_id = {$this->aid}");
	  		$this->id = $result->fields['ref_id'];
	  	}
		// Load contact info, including custom fields
		$result = $admin->DataBase->query("select * from ".TABLE_CONTACTS." where id = {$this->id}");
		foreach ($result->fields as $key => $value) $this->$key = $value;
		// expand attachments
		$this->attachments = $result->fields['attachments'] ? unserialize($result->fields['attachments']) : array();
		// Load the address book
		$result = $admin->DataBase->query("select * from ".TABLE_ADDRESS_BOOK." where ref_id = $this->id order by primary_name");
		$this->address = array();
		while (!$result->EOF) {
		  	$type = substr($result->fields['type'], 1);
		  	$this->address_book[$type][] = new \core\classes\objectInfo($result->fields);
		  	if ($type == 'm') { // prefill main address
		  		foreach ($result->fields as $key => $value) $this->address[$result->fields['type']][$key] = $value;
		  	}
		  	$result->MoveNext();
		}
		// load payment info
		if ($_SESSION['admin_encrypt'] && ENABLE_ENCRYPTION) {
		  	$result = $admin->DataBase->query("select id, hint, enc_value from ".TABLE_DATA_SECURITY." where module='contacts' and ref_1={$this->id}");
		  	while (!$result->EOF) {
		    	$val = explode(':', \core\classes\encryption::decrypt($_SESSION['admin_encrypt'], $result->fields['enc_value']));
		    	$this->payment_data[] = array(
			  	  'id'   => $result->fields['id'],
			  	  'name' => $val[0],
			  	  'hint' => $result->fields['hint'],
			  	  'exp'  => $val[2] . '/' . $val[3],
		    	);
		    	$result->MoveNext();
		  	}
		}
		// load contacts info
		$result = $admin->DataBase->query("select * from ".TABLE_CONTACTS." where dept_rep_id={$this->id}");
		$this->contacts = array();
		while (!$result->EOF) {
		  	$cObj = new \core\classes\objectInfo();
		  	foreach ($result->fields as $key => $value) $cObj->$key = $value;
		  	$addRec = $admin->DataBase->query("select * from ".TABLE_ADDRESS_BOOK." where type='im' and ref_id={$result->fields['id']}");
		  	$cObj->address['m'][] = new \core\classes\objectInfo($addRec->fields);
		  	$this->contacts[] = $cObj; //unserialize(serialize($cObj));
			// 	load crm notes
		  	$logs = $admin->DataBase->query("select * from ".TABLE_CONTACTS_LOG." where contact_id = {$result->fields['id']} order by log_date desc");
		  	while (!$logs->EOF) {
		    	$this->crm_log[] = new \core\classes\objectInfo($logs->fields);
		    	$logs->MoveNext();
		  	}
		  	$result->MoveNext();
		}
		// load crm notes
		$result = $admin->DataBase->query("select * from ".TABLE_CONTACTS_LOG." where contact_id = {$this->id} order by log_date desc");
		while (!$result->EOF) {
		  	$this->crm_log[] = new \core\classes\objectInfo($result->fields);
		  	$result->MoveNext();
		}
  	}

  	function delete($id) {
  		global $admin;
  		if ( $this->id == '' ) $this->id = $id;	// error check, no delete if a journal entry exists
		$result = $admin->DataBase->query("SELECT id FROM ".TABLE_JOURNAL_MAIN." WHERE bill_acct_id={$this->id} OR ship_acct_id={$this->id} OR store_id={$this->id} LIMIT 1");
		if ($result->rowCount() != 0) throw new \core\classes\userException(ACT_ERROR_CANNOT_DELETE);
		return $this->do_delete();
	}

  	public function do_delete(){
		global $admin;
	  	$admin->DataBase->query("DELETE FROM ".TABLE_ADDRESS_BOOK ." WHERE ref_id={$this->id}");
	  	$admin->DataBase->query("DELETE FROM ".TABLE_DATA_SECURITY." WHERE ref_1={$this->id}");
	  	$admin->DataBase->query("DELETE FROM ".TABLE_CONTACTS     ." WHERE id={$this->id}");
	  	$admin->DataBase->query("DELETE FROM ".TABLE_CONTACTS_LOG ." WHERE contact_id={$this->id}");
	  	foreach (glob(CONTACTS_DIR_ATTACHMENTS."contacts_{$this->id}_*.zip") as $filename) unlink($filename); // remove attachments
	  	return true;
  	}

   	/**
   	* this function returns alle order
   	*/
  	function load_orders($journal_id, $only_open = true, $limit = 0) {
  		global $admin;
  		$raw_sql  = "SELECT id, journal_id, closed, closed_date, post_date, total_amount, purchase_invoice_id, purch_order_id FROM ".TABLE_JOURNAL_MAIN." WHERE";
  		$raw_sql .= ($only_open) ? " closed = '0' and " : "";
  		$raw_sql .= " journal_id in ({$journal_id}) and bill_acct_id = {$this->id} ORDER BY post_date DESC";
  		$raw_sql .= ($limit) ? " LIMIT {$limit}" : "";
  		$sql = $admin->DataBase->prepare($raw_sql);
  		$sql->execute();
  		if ($sql->rowCount() == 0) return array();	// no open orders
  		$output = array();
  		$i = 1;
  		$output[0] = array('id' => '', 'text' => TEXT_NEW);
  		while ($result = $sql->fetch(\PDO::FETCH_LAZY)) {
  	    	$output[$i] = $result;
  	    	$output[$i]['text'] = $result['purchase_invoice_id'];
  	    	$output[$i]['total_amount'] = in_array($result['journal_id'], array(7,13)) ? -$result['total_amount'] : $result['total_amount'];
  			$i++;
  		}
  		return $output;
  	}

  	public function data_complete(){
  		global $admin, $messageStack;
  		if ($this->auto_type && $this->short_name == '') {
    		$result = $admin->DataBase->query("select ".$this->auto_field." from ".TABLE_CURRENT_STATUS);
        	$this->short_name  = $result->fields[$this->auto_field];
        	$this->inc_auto_id = true;
    	}
  		foreach ($this->address_types as $value) {
      		if (($value <> 'im' && substr($value, 1, 1) == 'm') || // all main addresses except contacts which is optional
        	  ($this->address[$value]['primary_name'] <> '')) { // optional billing, shipping, and contact
          		$msg_add_type = TEXT_A_REQUIRED_FIELD_HAS_BEEN_LEFT_BLANK_FIELD . ': ' . constant('ACT_CATEGORY_' . strtoupper(substr($value, 1, 1)) . '_ADDRESS');
	      		if (false === db_prepare_input($this->address[$value]['primary_name'],   $required = true))                     throw new \core\classes\userException($msg_add_type.' - '.TEXT_NAME_OR_COMPANY);
	      		if (false === db_prepare_input($this->address[$value]['contact'],        ADDRESS_BOOK_CONTACT_REQUIRED))        throw new \core\classes\userException($msg_add_type.' - '.TEXT_ATTENTION);
	      		if (false === db_prepare_input($this->address[$value]['address1'],       ADDRESS_BOOK_ADDRESS1_REQUIRED))       throw new \core\classes\userException($msg_add_type.' - '.TEXT_ADDRESS1);
	      		if (false === db_prepare_input($this->address[$value]['address2'],       ADDRESS_BOOK_ADDRESS2_REQUIRED))       throw new \core\classes\userException($msg_add_type.' - '.TEXT_ADDRESS2);
	      		if (false === db_prepare_input($this->address[$value]['city_town'],      ADDRESS_BOOK_CITY_TOWN_REQUIRED))      throw new \core\classes\userException($msg_add_type.' - '.TEXT_CITY_TOWN);
	      		if (false === db_prepare_input($this->address[$value]['state_province'], ADDRESS_BOOK_STATE_PROVINCE_REQUIRED)) throw new \core\classes\userException($msg_add_type.' - '.TEXT_STATE_PROVINCE);
	      		if (false === db_prepare_input($this->address[$value]['postal_code'],    ADDRESS_BOOK_POSTAL_CODE_REQUIRED))    throw new \core\classes\userException($msg_add_type.' - '.TEXT_POSTAL_CODE);
	      		if (false === db_prepare_input($this->address[$value]['telephone1'],     ADDRESS_BOOK_TELEPHONE1_REQUIRED))     throw new \core\classes\userException($msg_add_type.' - '.TEXT_TELEPHONE);
	      		if (false === db_prepare_input($this->address[$value]['email'],          ADDRESS_BOOK_EMAIL_REQUIRED))          throw new \core\classes\userException($msg_add_type.' - '.TEXT_EMAIL);
      		}
    	}
    	$this->duplicate_id();
    	return true;
  	}

  	/**
   	* this function looks if there are duplicate id's if so it throws a exception.
   	*/

  	public function duplicate_id(){
  		global $admin;
	  	// check for duplicate short_name IDs
    	if ($this->id == '') {
      		$result = $admin->DataBase->query("select id from ".TABLE_CONTACTS." where short_name = '$this->short_name' and type = '$this->type'");
    	} else {
      		$result = $admin->DataBase->query("select id from ".TABLE_CONTACTS." where short_name = '$this->short_name' and type = '$this->type' and id <> $this->id");
    	}
    	if ($result->rowCount() > 0) throw new \core\classes\userException($this->duplicate_id_error);
  	}

  	/**
   	* this function saves all input in the contacts main page.
   	*/

	public function save_contact(){
  		global $admin;
  		$fields = new \contacts\classes\fields(false);
  		$sql_data_array = $fields->what_to_save();
  		$sql_data_array['class']			= addcslashes(get_class($this), '\\');
    	$sql_data_array['type']            	= $this->type;
    	$sql_data_array['short_name']      	= $this->short_name;
    	$sql_data_array['inactive']        	= isset($this->inactive) ? '1' : '0';
    	$sql_data_array['contact_first']   	= $this->contact_first;
    	$sql_data_array['contact_middle']  	= $this->contact_middle;
    	$sql_data_array['contact_last']    	= $this->contact_last;
    	$sql_data_array['store_id']        	= $this->store_id;
    	$sql_data_array['gl_type_account'] 	= (is_array($this->gl_type_account)) ? implode('', array_keys($this->gl_type_account)) : $this->gl_type_account;
    	$sql_data_array['gov_id_number']   	= $this->gov_id_number;
    	$sql_data_array['dept_rep_id']     	= $this->dept_rep_id;
    	$sql_data_array['account_number']  	= $this->account_number;
    	$sql_data_array['special_terms']   	= $this->special_terms;
    	$sql_data_array['price_sheet']     	= $this->price_sheet;
    	$sql_data_array['tax_id']          	= $this->tax_id;
    	$sql_data_array['last_update']     	= 'now()';
    	if ($this->id == '') { //create record
        	$sql_data_array['first_date'] = 'now()';
        	db_perform(TABLE_CONTACTS, $sql_data_array, 'insert');
        	$this->id = db_insert_id();
			//	if auto-increment see if the next id is there and increment if so.
    	    if ($this->inc_auto_id) { // increment the ID value
        	    $next_id = string_increment($this->short_name);
            	$admin->DataBase->query("update ".TABLE_CURRENT_STATUS." set $this->auto_field = '$next_id'");
	        }
    	    gen_add_audit_log(TEXT_CONTACTS . '-' . TEXT_ADD . '-' . $this->title, $this->short_name);
    	} else { // update record
        	db_perform(TABLE_CONTACTS, $sql_data_array, 'update', "id = '$this->id'");
        	gen_add_audit_log(TEXT_CONTACTS . '-' . TEXT_UPDATE . '-' . $this->title, $this->short_name);
    	}
  	}

  	public function save_addres(){
  		global $admin;
	    // address book fields
    	foreach ($this->address_types as $value) {
      		if (($value <> 'im' && substr($value, 1, 1) == 'm') || // all main addresses except contacts which is optional
        	  ($this->address[$value]['primary_name'] <> '')) { // billing, shipping, and contact if primary_name present
              	$sql_data_array = array(
                    'ref_id'         => $this->id,
                    'type'           => $value,
                    'primary_name'   => $this->address[$value]['primary_name'],
                    'contact'        => $this->address[$value]['contact'],
                    'address1'       => $this->address[$value]['address1'],
                    'address2'       => $this->address[$value]['address2'],
                    'city_town'      => $this->address[$value]['city_town'],
                    'state_province' => $this->address[$value]['state_province'],
                    'postal_code'    => $this->address[$value]['postal_code'],
                    'country_code'   => $this->address[$value]['country_code'],
                    'telephone1'     => $this->address[$value]['telephone1'],
                    'telephone2'     => $this->address[$value]['telephone2'],
                    'telephone3'     => $this->address[$value]['telephone3'],
                    'telephone4'     => $this->address[$value]['telephone4'],
                    'email'          => $this->address[$value]['email'],
                    'website'        => $this->address[$value]['website'],
                    'notes'          => $this->address[$value]['notes'],
                );
              	if ($value == 'im') $sql_data_array['ref_id'] = $this->i_id; // re-point contact
              	if ($this->address[$value]['address_id'] == '') { // then it's a new address
                	db_perform(TABLE_ADDRESS_BOOK, $sql_data_array, 'insert');
                	$this->address[$value]['address_id'] = db_insert_id();
              	} else { // then update address
                	db_perform(TABLE_ADDRESS_BOOK, $sql_data_array, 'update', "address_id = '".$this->address[$value]['address_id']."'");
              	}
      		}
    	}
  	}
  	function draw_address_fields($add_type, $reset_button = false, $hide_list = false, $short = false) {
  		$field = '';
  		$method = substr($add_type, 1, 1);
  		//echo 'entries = '; print_r($entries); echo '<br>';
  		if (!$hide_list && sizeof($this->address_book[$method]) > 0) {
  			$field .= '<tr><td><table class="ui-widget" style="border-collapse:collapse;width:100%;">';
  			$field .= '<thead class="ui-widget-header">' . chr(10);
  			$field .= '<tr>' . chr(10);
  			$field .= '  <th>' . TEXT_NAME_OR_COMPANY .   '</th>' . chr(10);
  			$field .= '  <th>' . TEXT_ATTENTION .        '</th>' . chr(10);
  			$field .= '  <th>' . TEXT_ADDRESS1 .       '</th>' . chr(10);
  			$field .= '  <th>' . TEXT_CITY_TOWN .      '</th>' . chr(10);
  			$field .= '  <th>' . TEXT_STATE_PROVINCE . '</th>' . chr(10);
  			$field .= '  <th>' . TEXT_POSTAL_CODE .    '</th>' . chr(10);
  			$field .= '  <th>' . TEXT_COUNTRY .        '</th>' . chr(10);
  			// add some special fields
  			if ($method == 'p') $field .= '  <th>' . TEXT_PAYMENT_REF . '</th>' . chr(10);
  			$field .= '  <th align="center">' . TEXT_ACTION . '</th>' . chr(10);
  			$field .= '</tr>' . chr(10) . chr(10);
  			$field .= '</thead>' . chr(10) . chr(10);
  			$field .= '<tbody class="ui-widget-content">' . chr(10);

  			$odd = true;
  			foreach ($this->address_book[$method] as $address) {
  				$field .= '<tr id="tr_add_'.$address->address_id.'" class="'.($odd?'odd':'even').'" style="cursor:pointer">';
  				$field .= '  <td onclick="getAddress('.$address->address_id.', \''.$add_type.'\')">' . $address->primary_name . '</td>' . chr(10);
  				$field .= '  <td onclick="getAddress('.$address->address_id.', \''.$add_type.'\')">' . $address->contact . '</td>' . chr(10);
  				$field .= '  <td onclick="getAddress('.$address->address_id.', \''.$add_type.'\')">' . $address->address1 . '</td>' . chr(10);
  				$field .= '  <td onclick="getAddress('.$address->address_id.', \''.$add_type.'\')">' . $address->city_town . '</td>' . chr(10);
  				$field .= '  <td onclick="getAddress('.$address->address_id.', \''.$add_type.'\')">' . $address->state_province . '</td>' . chr(10);
  				$field .= '  <td onclick="getAddress('.$address->address_id.', \''.$add_type.'\')">' . $address->postal_code . '</td>' . chr(10);
  				// add special fields
  				$field .= '  <td onclick="getAddress('.$address->address_id.', \''.$add_type.'\')">' . $address->country_code . '</td>' . chr(10);
  				if ($method == 'p') $field .= '  <td onclick="getAddress('.$address->address_id.', \''.$add_type.'\')">' . ($address['hint'] ? $address['hint'] : '&nbsp;') . '</td>' . chr(10);
  				$field .= '  <td align="center">';
  				$field .= html_icon('actions/edit-find-replace.png', TEXT_EDIT, 'small', 'onclick="getAddress('.$address->address_id.', \''.$add_type.'\')"') . chr(10);
  				$field .= '&nbsp;' . html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . ACT_WARN_DELETE_ADDRESS . '\')) deleteAddress(' .$address->address_id . ');"') . chr(10);
  				$field .= '  </td>' . chr(10);
  				$field .= '</tr>' . chr(10);
  				$odd = !$odd;
  			}
  			$field .= '</tbody>' . chr(10) . chr(10);
  			$field .= '</table></td></tr>';
  		}

  		$field .= '<tr><td><table class="ui-widget" style="border-collapse:collapse;width:100%;">' . chr(10);
  		if (!$short) {
  			$field .= '<tr>';
  			$field .= '  <td align="right">' . TEXT_NAME_OR_COMPANY . '</td>' . chr(10);
  			$field .= '  <td>' . html_input_field("address[$add_type][primary_name]", $this->address[$add_type]['primary_name'], 'size="49" maxlength="48"', true) . '</td>' . chr(10);
  			$field .= '  <td align="right">' . TEXT_TELEPHONE . '</td>' . chr(10);
  			$field .= '  <td>' . html_input_field("address[$add_type][telephone1]", $this->address[$add_type]['telephone1'], 'size="21" maxlength="20"', ADDRESS_BOOK_TELEPHONE1_REQUIRED) . '</td>' . chr(10);
  			$field .= '</tr>';
  		}
  		$field .= '<tr>';
  		$field .= '  <td align="right">' . TEXT_ATTENTION . html_hidden_field("address[$add_type][address_id]", $this->address[$add_type]['address_id']) . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][contact]", $this->address[$add_type]['contact'], 'size="33" maxlength="32"', ADDRESS_BOOK_CONTACT_REQUIRED) . '</td>' . chr(10);
  		$field .= '  <td align="right">' . TEXT_ALTERNATIVE_TELEPHONE_SHORT . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][telephone2]", $this->address[$add_type]['telephone2'], 'size="21" maxlength="20"') . '</td>' . chr(10);
  		$field .= '</tr>';

  		$field .= '<tr>';
  		$field .= '  <td align="right">' . TEXT_ADDRESS1 . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][address1]" , $this->address[$add_type]['address1'], 'size="33" maxlength="32"', ADDRESS_BOOK_ADDRESS1_REQUIRED) . '</td>' . chr(10);
  		$field .= '  <td align="right">' . TEXT_FAX . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][telephone3]", $this->address[$add_type]['telephone3'], 'size="21" maxlength="20"') . '</td>' . chr(10);
  		$field .= '</tr>';

  		$field .= '<tr>';
  		$field .= '  <td align="right">' . TEXT_ADDRESS2 . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][address2]", $this->address[$add_type]['address2'], 'size="33" maxlength="32"', ADDRESS_BOOK_ADDRESS2_REQUIRED) . '</td>' . chr(10);
  		$field .= '  <td align="right">' . TEXT_MOBILE_PHONE . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][telephone4]", $this->address[$add_type]['telephone4'], 'size="21" maxlength="20"') . '</td>' . chr(10);
  		$field .= '</tr>';

  		$field .= '<tr>';
  		$field .= '  <td align="right">' . TEXT_CITY_TOWN . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][city_town]", $this->address[$add_type]['city_town'], 'size="25" maxlength="24"', ADDRESS_BOOK_CITY_TOWN_REQUIRED) . '</td>' . chr(10);
  		$field .= '  <td align="right">' . TEXT_EMAIL . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][email]", $this->address[$add_type]['email'], 'size="51" maxlength="50"') . '</td>' . chr(10);
  		$field .= '</tr>';

  		$field .= '<tr>';
  		$field .= '  <td align="right">' . TEXT_STATE_PROVINCE . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][state_province]", $this->address[$add_type]['state_province'], 'size="25" maxlength="24"', ADDRESS_BOOK_STATE_PROVINCE_REQUIRED) . '</td>' . chr(10);
  		$field .= '  <td align="right">' . TEXT_WEBSITE . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][website]", $this->address[$add_type]['website'], 'size="51" maxlength="50"') . '</td>' . chr(10);
  		$field .= '</tr>';

  		$field .= '<tr>';
  		$field .= '  <td align="right">' . TEXT_POSTAL_CODE . '</td>' . chr(10);
  		$field .= '  <td>' . html_input_field("address[$add_type][postal_code]", $this->address[$add_type]['postal_code'], 'size="11" maxlength="10"', ADDRESS_BOOK_POSTAL_CODE_REQUIRED) . '</td>' . chr(10);
  		$field .= '  <td align="right">' . TEXT_COUNTRY . '</td>' . chr(10);
  		$field .= '  <td>' . html_pull_down_menu("address[$add_type][country_code]", gen_get_countries(), $this->address[$add_type]['country_code'] ? $this->address[$add_type]['country_code'] : COMPANY_COUNTRY) . '</td>' . chr(10);
  		$field .= '</tr>';

  		if ($method <> 'm' || ($add_type == 'im' && substr($add_type, 0, 1) <> 'i')) {
  			$field .= '<tr>' . chr(10);
  			$field .= '  <td align="right">' . TEXT_NOTES . '</td>' . chr(10);
  			$field .= '  <td colspan="3">' . html_textarea_field("address[$add_type][notes]", 80, 3, $this->address[$add_type]['notes']) . chr(10);
  			if ($reset_button) $field .= html_icon('actions/view-refresh.png', TEXT_RESET, 'small', 'onclick="clearAddress(\''.$add_type.'\')"') . chr(10);
  			$field .= '  </td>' . chr(10);
  			$field .= '</tr>' . chr(10);
  		}
  		$field .= '</table></td></tr>' . chr(10) . chr(10);
  		return $field;
  	}


  	/**
  	 * this method outputs a line on the template page.
  	 */
  	function list_row () {
  		\core\classes\messageStack::debug_log("executing ".__METHOD__ ." of class ". get_class($admin_class));
  		$security_level = \core\classes\user::validate($this->security_token); // in this case it must be done after the class is defined for
  		$bkgnd          = ($this->inactive) ? ' style="background-color:pink"' : '';
  		$attach_exists  = $this->attachments ? true : false;
  		echo "<td $bkgnd onclick='submitSeq( $this->id, \"LoadContactPage\")'>". htmlspecialchars($this->short_name) 	."</td>";
  		echo "<td $bkgnd onclick='submitSeq( $this->id, \"LoadContactPage\")'>". htmlspecialchars($this->primary_name)	. "</td>";
  		echo "<td    {$this->inactive}    onclick='submitSeq( $this->id, \"LoadContactPage\")'>". htmlspecialchars($this->address1) 	."</td>";
  		echo "<td        onclick='submitSeq( $this->id, \"LoadContactPage\")'>". htmlspecialchars($this->city_town)	."</td>";
  		echo "<td        onclick='submitSeq( $this->id, \"LoadContactPage\")'>". htmlspecialchars($this->state_province)."</td>";
  		echo "<td        onclick='submitSeq( $this->id, \"LoadContactPage\")'>". htmlspecialchars($this->postal_code)	."</td>";
  		echo "<td 	     onclick='submitSeq( $this->id, \"LoadContactPage\")'>". htmlspecialchars($this->telephone1)	."</td>";
  		echo "<td align='right'>";
  		// build the action toolbar
		if ($security_level > 1) echo html_icon('mimetypes/x-office-presentation.png', TEXT_SALES, 'small', 	"onclick='contactChart(\"annual_sales\", $this->id)'") . chr(10);
  		if ($security_level > 1) echo html_icon('actions/edit-find-replace.png', TEXT_EDIT, 'small', 			"onclick='window.open(\"" . html_href_link(FILENAME_DEFAULT, "cID={$this->id}&amp;action=LoadContactPage", 'SSL')."\",\"_blank\")'"). chr(10);
  		if ($attach_exists) 	 echo html_icon('status/mail-attachment.png', TEXT_DOWNLOAD_ATTACHMENT,'small', "onclick='submitSeq($this->id, \"dn_attach\", true)'") . chr(10);
  		if ($security_level > 3) echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 			"onclick='if (confirm(\"" . ACT_WARN_DELETE_ACCOUNT . "\")) submitSeq($this->id, \"delete\")'") . chr(10);
  		echo "</td>";
  	}
}
?>