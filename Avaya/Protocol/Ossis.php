<?php

class Avaya_Protocol_Ossis 
{
	
	protected $_termEmulation = 'ossis3';
	protected $_termPrompt = 't';
	
	protected $telnet = null; // Telnet object
	
	
	public function get($command, $fids=null) 
	{
		$cmd = self::_generateGetCmd($command, $fids);
		//execute
		$this->telnet->setPrompt($this->_termPrompt."\n");
		$data = $this->telnet->exec($cmd);
		$data = $this->_parseOutput($data);
		return $data;
	}
	
	public function getAgentLog($agentExt)
	{
		$cmd = 'list bcms agent';
		$fids = array(
			'3e82ff00',// => 'time',
			'3e83ff00',// => 'acd_calls',
			'3e84ff00',// => 'avg_talk_time',
			'3e85ff00',// => 'total_after_call',
			'3e86ff00',// => 'total_avail_time',
			'3e87ff00',// => 'total_aux_other',
			'3e88ff00',// => 'extn_calls',
			'3e89ff00',// => 'avg_extn_calls',
			'3e8aff00',// => 'total_time_staffed',
			'3e8bff00',// => 'total_hold_time',
			);
		$data = $this->get($cmd.' '.$agentExt, $fids);
		//$data = $this->_fidsToDataArray($data);
		$data = $this->_parseFids($cmd, $data);

		$n = count($data)-1;
		unset($data[$n], $data[$n-1]); // removing ---- and SUMMARY
		
		return $data;
	}
	
	public function monitorSkill($skill)
	{
		$cmd = 'monitor bcms skill';
		$data = $this->get($cmd.' '.$skill);
		$data = $this->_fidsToDataArray($data);
		$data['fids'] = $this->_parseFids($cmd, $data['fids']);
		return $data;
	}
	
	public function listAgents()
	{
		$cmd = 'list agent';
		$fids = array(
			'0fa1ff00', //agent_id
			'0fa3ff00', //agent_name
			'8fdeff01', //skill 1
			'8fdeff02', //skill 2
			'8fdeff03', //skill 3
			'8fdeff04', //skill 4
			'0fadff00', // ext
			);
		$data = $this->get($cmd, $fids);
		$data = $this->_parseFids($cmd, $data);
		return $data;
	}
	
	public function getTermEmulation()
	{
		return $this->_termEmulation;
	}
	
	public function getTermPrompt()
	{
		return $this->_termPrompt;
	}
	
	public function setTelnet(&$telnet)
	{
		$this->telnet = $telnet;
	}
	
	protected function _generateGetCmd($command, $fids=null) 
	{
		$cmd = "c".$command."\n";
		if ($fids) {
			if (!is_array($fids)) $fids = array($fids);
			foreach ($fids as $fid) {
				$cmd .= "f".$fid."\n"."d"."\n";
			}
		}
		$cmd .= "t";
		return $cmd;
	}
		
	protected function _parseOutput($data) 
	{
		$data = explode("\n", $data);
		$packet = array();

		$dataRecordStarted = false;
		$iRecord = 0;
		foreach ($data as $line) {
			$lineType = substr($line, 0, 1);
			if ($lineType == 'n') { //New Record
				$iRecord++;
			} else if ($lineType == 't') { //End
				
			} else {
				$line = substr($line, 1); //trimming lineType from line
				//$lineEmptyTest = str_replace("\t", '', $line);
				//if (strlen($lineEmptyTest)!=0) {
					$line = explode("\t", $line);		
					foreach ($line as $item) {
						if ($lineType == 'f') $packet['fids'][] = $item; 			//FIDs
						if ($lineType == 'd') $packet['data'][$iRecord][] = $item; 	//Data
					}
				//}
			}
		}
		//dump($packet);
		//return $packet;
		if ($iRecord==0) {
			return array_combine($packet['fids'], $packet['data'][0]);
		} else {
			$records = array();
			foreach ($packet['data'] as $data) {
				$records[] = array_combine($packet['fids'], $data);
			}
			return $records;			
		}
	}
	
	protected function _fidsToDataArray($data)
	{
		$newData = array();
		foreach($data as $fid=>$val) {
			if (substr($fid, 0, 5) == '000d0') {
				$iRecord = substr($fid, 6, 2);
				$iVal 	 = substr($fid, 4, 2); // 4,2 or 5,1 ???
				$iRecord = hexdec($iRecord);
				$iVal    = hexdec($iVal);
				
				$newData['data'][$iRecord][$iVal] = $val;
			} else {
				$newData['fids'][$fid] = $val;
			}
		}
		//$newData['data'] = array_filter($newData['data']);
		$newData['data'] = array_remove_empty($newData['data']);
		
		return $newData;
	}
	
	protected function _parseFids($cmd, $data) 
	{
		$fids['list_bcms_agent'] = array(
			'3e81ff00' => 'switch_name',
			'0002ff00' => 'date',
			'4a39ff00' => 'agent_id',
			'0001ff00' => 'agent_name',
			'0003ff00' => 'str_TIME',
			'3e82ff00' => 'time',
			'3e83ff00' => 'acd_calls',
			'3e84ff00' => 'avg_talk_time',
			'3e85ff00' => 'total_after_call',
			'3e86ff00' => 'total_avail_time',
			'3e87ff00' => 'total_aux_other',
			'3e88ff00' => 'extn_calls',
			'3e89ff00' => 'avg_extn_calls',
			'3e8aff00' => 'total_time_staffed',
			'3e8bff00' => 'total_hold_time',
			);
		$fids['monitor_bcms_skill'] = array(
			'0002ff00' => 'skill',
			'0004ff00' => 'Date',
			'0003ff00' => 'skill_name',
			'0005ff00' => 'calls_waiting',
			'07d1ff00' => 'acceptable_service_level',
			'0006ff00' => 'oldest_call',
			'07d2ff00' => 'service_level',
			'0007ff00' => 'staffed',
			'0008ff00' => 'avail',
			'0009ff00' => 'acd',
			'000aff00' => 'acw',
			'3e81ff00' => 'aux',
			'000bff00' => 'extn_calls',
			'000cff00' => 'other',
			/*
			'000d0101' => 'agent_name'
			'000d0201' => 'login_id'
			'000d0301' => 'ext'
			'000d0401' => 'state'
			'000d0501' => 'time'
			'000d0601' => 'acd_calls'
			'000d0701' => 'ext In Calls'
			'000d0801' => 'ext Out Calls'
			*/
			);
		$fids['list_agent'] = array(
			'0fa1ff00' => 'agent_id',
			'0fa3ff00' => 'agent_name',
			'ce2eff00' => 'direct_agent_skill',
			'8001ff00' => 'cor',
			'4e22ff00' => 'ag_pr',
			//'6009ff00' => ', //
			'8fdeff01' => 'skill_1',
			//'ce2dff00' => '
			'8fdeff02' => 'skill_2',
			'8fdeff03' => 'skill_3',
			'8fdeff04' => 'skill_4',
			'0fadff00' => 'ext',
			);
			
		$cmd = str_replace(' ', '_', trim($cmd));
		
		$newData = array();
		foreach ($data as $key=>$val) {
			if (is_int($key) && is_array($val)) {
				$newData[$key] = $this->_parseFids($cmd, $val);
			} else if (array_key_exists($key, $fids[$cmd])) { 
				$newData[$fids[$cmd][$key]] = $val; 
			} else { 
				$newData[$key] = $val; 
			}
		}
		return $newData;
	}
	
	
}

	function array_remove_empty($arr){
	    $narr = array();
	    while(list($key, $val) = each($arr)){
	        if (is_array($val)){
	            $val = array_remove_empty($val);
	            // does the result array contain anything?
	            if (count($val)!=0){
	                // yes :-)
	                $narr[$key] = $val;
	            }
	        }
	        else {
	            if (trim($val) != ""){
	                $narr[$key] = $val;
	            }
	        }
	    }
	    unset($arr);
	    return $narr;
	}

/*

List of known FIDs
> monitor bcms skill
0002ff00 - Skill
0004ff00 - Time
0003ff00 - Skill Name
0005ff00 - Calls Waiting
07d1ff00 - Acceptable Service Level
0006ff00 - Oldest Call
07d2ff00 - % Within Service Level
0007ff00 - Staffed
0008ff00 - Avail
0009ff00 - ACD
000aff00 - ACW
3e81ff00 - AUX
000bff00 - Extn Calls
000cff00 - Other
000d0101 - Agent Name
000d0201 - Login ID
000d0301 - Ext
000d0401 - State
000d0501 - Time
000d0601 - ACD Calls
000d0701 - Ext In Calls
000d0801 - Ext Out Calls

> list bcms agent
3e81ff00 - Switch name
0002ff00 - Date
4a39ff00 - Agent ID
0001ff00 - Agent Name
0003ff00 - string "TIME"
3e82ff00 => time
3e83ff00 => acd_calls
3e84ff00 => avg_talk_time
3e85ff00 => total_after_call
3e86ff00 => total_avail_time 
3e87ff00 => total_aux_other
3e88ff00 => extn_calls
3e89ff00 => avg_extn_calls
3e8aff00 => total_time_staffed
3e8bff00 => total_hold_time

*/