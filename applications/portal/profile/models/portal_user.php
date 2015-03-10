<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Portal User model
 * for interacting mainly with user_data on the portal side
 * @author Minh Duc Nguyen <minh.nguyen@ands.org.au>
 */
class Portal_user extends CI_Model {

	public $identifier;
	public $name;
	public $authMethod;
	public $function;
	public $user_data;

	/**
	 * Get the current logged in user, requires the user library to be available and the user is logged in
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 * @return [type] [description]
	 */
	public function getCurrentUser(){
		$this->identifier = $this->user->localIdentifier();
		$this->name = $this->user->name();
		$this->authMethod = $this->user->authMethod();
		$this->function = $this->user->functions();
		$this->user_data = $this->getUserData($this->identifier);
		unset($this->portal_db); //prevent portal_db Active Record Object from returning with the obj
		return $this;
	}

	/**
	 * Get the user_data array for the given user, mostly the current user but can be used for any user
	 * If the user_data array doesn't exist, a row will be generated for the given user containing a blank
	 * user_data
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 * @param  string $role_id role_id pk table field for identification
	 * @return array           
	 */
	public function getUserData($role_id) {
		$this->portal_db = $this->load->database('portal', TRUE);
		$result = $this->portal_db->get_where('user_data', array('role_id'=>$role_id));
		if($result->num_rows() > 0){
			$r = $result->first_row();
			return json_decode($r->user_data, true);
		} else {
			//create a new user data row for this user
			$data = array('role_id'=>$role_id, 'user_data'=>'{}');
			$this->portal_db->insert('user_data', $data);
		}
	}

	/**
	 * Update a user_data of the current user
	 * @param array $data 
	 */
	public function add_user_data($data) {
		$user = $this->getCurrentUser();
		$user_data = $user->user_data;
		$user_data[$data['type']][] = $data['value'];
		$data = array('user_data' => json_encode($user_data));
		$this->portal_db = $this->load->database('portal', TRUE);
		$this->portal_db->where('role_id', $user->identifier)->update('user_data', $data);
	}

    /**
     * Update a user_data of the current user
     * @param type, action, data
     */
    public function modify_user_data($type, $action, $data){

            $user = $this->getCurrentUser();
            $user_data = $user->user_data;
            $response['type'] = $type;
            $response['action'] = $action;

            $response['status'] = "OK";
            switch ($type){
                case 'saved_search':
                    foreach($data as $record)
                    {
                        $response['record_id'] = $record['id'];
                        switch ($action){
                            case 'add':
                                $user_data[$type][$record['query_string']] = $record;
                                break;
                            case 'refresh':
                                $oldRecord = $user_data[$type][$record['id']];
                                foreach($oldRecord as $key=>$val)
                                    if(isset($record[$key]))
                                        $oldRecord[$key] = $record[$key];
                                $user_data[$type][$record['id']] = $oldRecord;
                                break;
                            case 'delete':
                                $id = $record['id'];
                                unset($user_data[$type][$id]);
                                break;
                        }
                    }
                break;
                case 'saved_record':
                    foreach($data as $record)
                    {

                        $id = $record['id'];
                        switch ($action){
                            case 'add':
                                $user_data[$type][$id] = $record;
                                break;
                            case 'modify':
                                $oldRecord = isset($user_data[$type][$id]) ? $user_data[$type][$id] : false;
                                if($oldRecord) {
                                	foreach($oldRecord as $key=>$val) {
	                            	    if(isset($record[$key])){
	                            	        $oldRecord[$key] = $record[$key];
                            	    	}
                            		}
                            		$user_data[$type][$id] = $oldRecord;
                            	}
                                break;
                            case 'delete':
                                unset($user_data[$type][$id]);
                                break;
                        }
                    }
                break;
            }

            if(count($user_data[$type]) === 0)
                unset($user_data[$type]);

            $this->update_user_data($user_data);
        return $response;
        }








	public function update_user_data($data) {
		$user = $this->getCurrentUser();
		$data = array('user_data' => json_encode($data));
		$this->portal_db = $this->load->database('portal', TRUE);
		$this->portal_db->where('role_id', $user->identifier)->update('user_data', $data);
	}

	/**
	 * Returns a specific user data of a current user
	 * @param  string $type type of user data to return, leaves blank for everything
	 * @return array       user_data
	 */
	public function get_user_data($type='') {
		if (!$this->user_data) {
			$user = $this->getCurrentUser();
			$user_data = $user->user_data;
		} else {
			$user_data = $this->user_data;
		}
	
		if ($type=='') {
			return $user_data;
		} elseif(isset($user_data[$type])) {
			return $user_data[$type];
		}
		return false;
	}

	/**
	 * Mostly helper function to check if a registry object ID exists within a user saved search
	 * @param  ro_id  $id 
	 * @return boolean     
	 */
	public function has_saved_record($id, $folder=null) {
		$user_data = $this->get_user_data('saved_record');
		if(!$user_data) return false;
		foreach($user_data as $ud) {
			if($ud['id']==$id && ($folder === null || $ud['folder']==$folder)) return true;
		}
		return false;
	}


	//boring __construct
	function __construct() {
		parent::__construct();
	}
}