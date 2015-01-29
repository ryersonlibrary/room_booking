<?php

class building_Model  extends CI_Model  {

	
	function __construct() {
		parent::__construct();
	}

    function list_buildings(){
		return $this->db->get('buildings');
	}
	
	function load_building($building_id){
		$this->db->where('building_id', $building_id);
		$data['building_data'] = $this->db->get('buildings');
		
		return $data;
	}	
	
	function edit_building($building_id, $name, $ext_id){
		
		$data = array(
			'building_id' => $building_id,
			'name' => $name,
			'external_id' => $ext_id
		);
		
		$this->db->where('building_id', $building_id); 
		$this->db->update('buildings', $data); 
		
		$this->db->cache_delete_all();
		
		return TRUE;
	}
	
	function add_building($building_name, $ext_id){
		
		$data = array(
			'name' => $building_name,
			'external_id' => $ext_id,
		);
		
		$this->db->insert('buildings', $data); 
		$id = $this->db->insert_id();

		$this->db->cache_delete_all();
		
		return $id;
	}
	
	function delete_building($building_id){
		$this->db->where('building_id', $building_id);
		$this->db->delete('buildings');
		
		$this->db->cache_delete_all();
	}

}
