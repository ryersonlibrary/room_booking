<?php

class hours_Model  extends CI_Model  {
	
	
	
	function getAllHours($date){
		$this->load->model('building_model');
		
		//Check to see if a cache file already exists
		if(file_exists('temp/'. date('Ymd', $date).'.hours')){
			$jsonData = @file_get_contents('temp/'. date('Ymd', $date).'.hours');
			$hours_json = json_decode($jsonData);
		}
		else{
			if(USE_EXTERNAL_HOURS){
				
				//Prepare the date into Coldfusion's horrible timestamp
				$timestamp = "{ts '". date('Y-m-d', $date) . " 00:00:00'}";
				
				$opts = array(
				  'http'=>array(
					'method'=>"GET",
					'header'=>"User-Agent: " . USER_AGENT . "\r\n" 
				  ),
				);

				$context = stream_context_create($opts);

				$url = EXTERNAL_HOURS_URL . '?dt='.urlencode($timestamp).'&l=all';

				$jsonData = @file_get_contents($url, false, $context);
				
				if($jsonData === FALSE){ 
					return FALSE; 
				}
				
				//Write it to a file
				file_put_contents('temp/'. date('Ymd', $date).'.hours', $jsonData);
				
				$hours_json = json_decode($jsonData); 
			}
			else{
				//Generate a json file from the local db
				$buildings = $this->building_model->list_buildings();
				
				$hours_json = array();
				
				//Iterate all the buildings
				foreach($buildings->result() as $building){
					$temp_json['LOCATION_ID'] = intval($building->building_id); // <----- bad bad bad. sets location_id to the last entry
					
					$hours = $this->get_hours($building->building_id);
					
					//Check for closures
					$closures = $this->get_closure($building->building_id, $date);
					if($closures->num_rows() > 0) $closure = true;
					else $closure = false;
					
					$has_hours = false;
					
					//Find which hours apply to the selected date
					foreach($hours->result() as $hour){
						if(strtotime($hour->start_date) <= $date && strtotime($hour->end_date) >= $date){
							
							
							//Get the start & end times
							$weekly_hours = json_decode($hour->hours_data, true);
							
							$open = $weekly_hours[strtolower(date('D',$date)).'_start'];
							$open_value = date('G',strtotime($open))/24 + date('i', strtotime($open))/60/24;
							
							$closed = $weekly_hours[strtolower(date('D',$date)).'_end'];
							$closed_value = date('G',strtotime($closed))/24 + date('i', strtotime($closed))/60/24;
							
							if($open_value === $closed_value || $closed === true) $isopen = false;
							else $isopen = true;
							
							$temp_json['DATA'] = array(
								"HASCLOSURE"		=>	$closure,
								"LOCATION_ID"		=>	intval($building->building_id),
								"REASONFORCLOSURE"	=>	'',
								"ENDTIME"			=>	$closed_value,
								"STARTTIME"			=>	$open_value,
								"ISOPEN"			=>	$isopen
							);
							
							
							$hours_json[] = $temp_json;
						
							$has_hours = true;
							break;					
						}
					}
					
					//No hours exist in the database. Set it to being closed (Note: this disables caching hours)
					if(!$has_hours){
						$temp_json['DATA'] = array(
							"HASCLOSURE"		=>	true,
							"LOCATION_ID"		=>	intval($building->building_id),
							"REASONFORCLOSURE"	=>	'',
							"ENDTIME"			=>	0,
							"STARTTIME"			=>	0,
							"ISOPEN"			=>	false
						);
						
						$hours_json[] = $temp_json;
					}
				}
				
				//Write it to a file
				$json_text = json_encode($hours_json);
				file_put_contents('temp/'. date('Ymd', $date).'.hours', $json_text); 
				$hours_json = json_decode($json_text); //Horrible. But it keeps the arrays organized the same
			}
			
		}
		
		//Make sure it is valid JSON
		if($hours_json === null){
			//The file could be invalid JSON. Destroy it
			if(file_exists('temp/'. date('Ymd', $date).'.hours')){
				@unlink('temp/'. date('Ymd', $date).'.hours');
			}
			
			$blank_data = new stdClass;
			$blank_data->HASCLOSURE = true;
			$blank_data->LOCATION_ID = 0;
			$blank_data->REASONFORCLOSURE = '';
			$blank_data->ENDTIME = 0;
			$blank_data->STARTTIME = 0;
			$blank_data->ISOPEN = false;
			
			return array($blank_data, 'min'=>0, 'max'=>0);
		}
		
		
		
		$output = array();
		
		$min = 2; //In coldfusion, 1 is midnight of the next day. Use 2 (instead of 1 in this case to cover hours such as 1am)
		$max = -1; //In coldfusion, 0 is midnight
		
		//Load all of the external ID's
		$this->load->model('room_model');
		$rooms = $this->room_model->list_rooms(true);
		
		//Match the external ID's with those in the JSON result
		foreach($hours_json as $location){

			if(!USE_EXTERNAL_HOURS){
				$building_id = $location->LOCATION_ID;
			}
			//Convert the external ID into the building id
			else{
				$building = $this->building_model->get_by_external_id($location->LOCATION_ID);
				if($building->num_rows() === 0) continue;
				else $building_id = $building->row()->building_id;
			}
			
			$output[$building_id] = $location->DATA; 
			
			
			//Is the building closed?
			if($location->DATA->STARTTIME == $location->DATA->ENDTIME || $location->DATA->ISOPEN == false || $location->DATA->HASCLOSURE == true || $location->DATA->ISOPEN == false){
				//Delete the cache file, as the user may be looking too far into the future where the hours have not yet been entered
				if(file_exists('temp/'. date('Ymd', $date).'.hours')){
					@unlink('temp/'. date('Ymd', $date).'.hours');
				}
				continue;
			}
			
			//Should we factor in the "ISOPEN" property here?
			if($location->DATA->STARTTIME < $min) $min = $location->DATA->STARTTIME;
			if($location->DATA->ENDTIME > $max) $max = $location->DATA->ENDTIME;
			
			
		}
		
		$output['min'] = $min;
		$output['max'] = $max;

		return $output;

	}
	
	function get_closures($building_id, $include_past = false){
		if(!is_numeric($building_id)) return false;
		
		$sql = "SELECT * FROM building_closures WHERE building_id = ". $building_id;
		
		if($include_past === false){
			$sql.= " AND closure_date >= '". date('Y-m-d'). "'";
		}
		
		$sql.= " ORDER BY closure_date ASC";
		
		return $this->db->query($sql);
	}
	
	function get_closure($building_id, $date){
		if(!is_numeric($building_id)) return false;
		
		$sql = "SELECT * FROM building_closures WHERE building_id = ". $building_id ." AND closure_date = '". date('Y-m-d', $date)."'";
		
		return $this->db->query($sql);
		
	}
	
	function add_closure($building_id, $date){
		$this->load->library('bookingcalendar');
		
		//Validate the date string
		if(!$this->bookingcalendar->isValidDateTimeString($date, 'Y-m-d')) return FALSE;		
		
		$data = array(
			'building_id' => $building_id,
			'closure_date' => $date,
		);
		
		$this->db->insert('building_closures', $data); 
		$id = $this->db->insert_id();

		$this->load->helper('cache_helper');
		empty_cache();
		
		return $id;
		
	}
	
	function delete_closure($closure_id){
		$this->db->where('closure_id', $closure_id);
		$this->db->delete('building_closures');
		
		$this->load->helper('cache_helper');
		empty_cache();
	}
	
	function get_hours($building_id, $include_past = false){
		if(!is_numeric($building_id)) return false;
		
		$sql = "SELECT * FROM building_hours WHERE building_id = ". $building_id;
		
		if($include_past === false){
			$sql.= " AND end_date >= '". date('Y-m-d'). "'";
		}
		
		$sql.= " ORDER BY start_date ASC";
		
		return $this->db->query($sql);
	}
	
	function add_hours($building_id, $start_date, $end_date, $hours_data){
		$this->load->library('bookingcalendar');
		
		//Validate all the inputs
		if(!is_numeric($building_id)) return 'Invalid Building ID';
		
		//Start/End dates are formatted correctly
		if(!$this->bookingcalendar->isValidDateTimeString($start_date, 'Y-m-d')) return 'Invalid Start Date';		
		if(!$this->bookingcalendar->isValidDateTimeString($end_date, 'Y-m-d')) return 'Invalid End Date';
		
		//Make sure an entry doesn't already exist with conflicting dates
		$other_hours = $this->get_hours($building_id, true);
		foreach($other_hours->result() as $other){
			//Starts before your booking, but ends after your's starts
			if(strtotime($other->start_date) <= strtotime($start_date) && strtotime($other->end_date) >= strtotime($start_date)){
				return "Conflicting booking exists";
			}
			//Starts after your booking, but not before your end
			elseif(strtotime($other->start_date) >= strtotime($start_date) && strtotime($other->start_date) <= strtotime($end_date)){
				return "Conflicting booking exists";
			}
		}
		
		
		//Start date must be before End date
		if(strtotime($start_date) > strtotime($end_date)) return 'End Date is before Start Date';
		
		//All times are formatted correctly
		foreach($hours_data as $entry){
			if($entry !== "24:00" && !$this->bookingcalendar->isValidDateTimeString($entry, 'H:i')) return 'Time format of '.$entry.' is not valid';		
		}
		
		//Make sure the start time is not later then the end time
		for($i=0; $i < 7; $i++){
			$dow = strtolower(date('D', strtotime("Sunday +{$i} days")));
			
			//Make sure a start/end time is set for every day of the week
			if(!array_key_exists($dow.'_start', $hours_data) || !array_key_exists($dow.'_end', $hours_data)) return 'Missing day of week: '.$dow;

			if(strtotime($hours_data[$dow.'_start']) > strtotime($hours_data[$dow.'_end'])) return 'Time ends before start on '.$dow;
		}
		
		$data = array(
			'building_id' => $building_id,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'hours_data' => json_encode($hours_data)
		);
		
		$this->db->insert('building_hours', $data); 
		
		$this->db->cache_delete_all();

		return $this->db->insert_id();
	}
	
	function delete_hours($hours_id){
		$this->db->where('hours_id', $hours_id);
		$this->db->delete('building_hours');
		
		$this->load->helper('cache_helper');
		empty_cache();
	}
	
}