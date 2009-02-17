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
	}
	
	public function getTermEmulation()
	{
		return self::$_termEmulation;
	}
	
	public function getTermPrompt()
	{
		return self::$_termPrompt;
	}
	
	public function setTelnet(&$telnet)
	{
		$this->telnet = $telnet;
	}
	
	protected function _generateGetCmd($command, $fids=null) 
	{
		$cmd = "c".$command."\n"
		if ($fids) {
			if (!is_array($fids)) $fid = array($fids);
			foreach ($fids as $fid) {
				$cmd .= "f".$fid."\n"."d"."\n";
			}
		}
		$cmd .= "t\n";
		return $cmd;
	}
		
	protected function _explodePacket($data) 
	{
		$separator = 0x09;
		$data = explode("\n", $data);
		$packet = array();
		
		$iRecord = 0;
		foreach ($data as $line) {
			if ($line=='n') { //New Record
				$iRecord++;
			} else {
				$line = explode($separator, $line);
				foreach ($line as $item) {
					$itemType = substr($item, 0, 1);
				
					switch($itemType) {
						case 'f':	//FID
							$packet['fids'] = substr($item, 1);
							break;
						case 'd':	//Data
							$packet['data'][$iRecord] = substr($item, 1);
							break;
						default:	// Continuing of Data Row
							$packet['data'][$iRecord] = $item;
							break;
					}
				}
			}
		}
		
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

*/