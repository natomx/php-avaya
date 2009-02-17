<?php
// OLD class! do not use!
class Avaya_Protocol_513 {
	
	private $telnet;
	private $connected = false;
	private $pause = 200;
	/*
	function __destruct() {
		$this->disconnect();
	}
	*/
	
	public function setPause($p=200) {
		$this->pause = $p;
	}
	
	public function switchIVR($state='switch') {
		$phone_ivr    = 98200;
		$phone_direct = 98210;
		$n_trunk_groups = 6; //6
		
		$phone = $phone_ivr;
		
		if ($state=='on')
			$phone = $phone_ivr;
		if ($state=='off')
			$phone = $phone_direct;
		
		/*
		 *  14 down,
		 * 3 right, 98200, 1 down - repeat 4 times
		 * F7 (next page)
		 * 3 right, 98200, 1 down - repeat 4 times
		 * F3 (save) 
		 */
		 
		$this->connect();
		for ($i=1;$i<($n_trunk_groups+1);$i++) {
			$this->write('change inc-call-handling-trmt trunk-group '.$i."\n");
			usleep($this->pause);
			$this->goDown(14);
			usleep($this->pause);
			for ($k=0;$k<4;$k++) {
				$this->goRight(3);
				$this->write($phone);
				$this->goDown();
			}
			
			$this->nextPage();
			for ($k=0;$k<4;$k++) {
				$this->goRight(3);
				$this->write($phone);
				$this->goDown();
			}
			$this->save();
			usleep($this->pause);
		}
		usleep($this->pause);
	}
	
	/*
	$options = array(
		'passwd' 		=> $passwd,
		'cor'			=> 10,
		'direct_skill' 	=> 10,
		'skills'		=> '10/1 1/5'
		);
	*/
	public function addExt($ext, $name, $options) {
		$this->connect();
		$this->write('add agent '.$ext."\n");
		usleep($this->pause);
		
		$this->write($name);
		
		$this->goDown(2);
		if ($options['cor'])
			$this->write($options['cor']);
			
		$this->goDown(4);
		if ($options['passwd']) {
			$this->write($options['passwd']);
			$this->goDown();
			$this->write($options['passwd']);
		}
		
		if ($options['direct_skill']) {
			$this->nextPage();
			$this->write($options['direct_skill']);
			if ($options['skills']) {
				$this->goDown(2);
				$this->writeSkills($options['skills']);
			}
			
		}
		
		$this->save();
		usleep($this->pause);
	}
	
	public function delExt($ext) {
		$this->connect();
		$this->write('remove agent '.$ext."\n");
		usleep($this->pause);
		
		$this->save();
		usleep($this->pause);
	}

	
	public function changeStationName($station, $name='test') {
		$this->connect();
		$this->write('change station '.$st."\n");
		$this->goDown(2);
		$this->write($name);
		$this->save();
	}
	
	public function getAgentSkills($agent) {
		$this->connect();
		$data = $this->cmd('disp agent '.$agent, 2);
		$data = explode("\n", $data);
		$data = array_splice($data, 28, 10);
		
		$skills = array();
		foreach ($data as $buf) {
			$tmp = explode(':', $buf);
			$tmp = trim($tmp[1]);
			$tmp = preg_replace("/\ +/", ' ', $tmp);
			$sk = explode(' ', $tmp);
			if (is_numeric($sk[1]))
				$skills[] = array('sn'=>$sk[0], 'sl'=>$sk[1]);
		}
		
		return $skills;
	}
	
	public function setAgentSkills($agent, $skills='10/1') {
		$this->connect();
		$this->write('change agent '.$agent."\n");
		$this->nextPage();
		$this->goDown(2);
		
		$this->writeSkills($skills);
		
		$this->save();
		usleep($this->pause);
	}
	
	private function writeSkills($skills='10/1') {
		if (!is_array($skills)) {
			$skills = $this->str2skills($skills);
		}
		
		foreach ($skills as $skill) {
			$this->write($skill['sn']);
			$this->goRight();
			$this->write($skill['sl']);
			$this->goDown();
		}
		
		//clear other
		for ($i=0;$i<5;$i++) {
			$this->write(' ');
			$this->goRight();
			$this->write(' ');
			$this->goDown();
		}
	}
	
	public function connect() {

		
		if (!$this->connected) {
			$this->telnet = new Telnet($host, $port);

			$this->telnet->login($login, $passwd);
			
			if (!empty($pin)) {
				$this->telnet->waitPrompt('Pin:');
				$this->telnet->write($pin);
			}

			$this->telnet->waitPrompt('[513]');
			$this->telnet->write('513');
			$this->telnet->waitPrompt('[y]');
			$this->telnet->write('y');

			$this->telnet->waitPrompt('Command:');
			
			$this->connected = true;
		}
		return 	$this->connected;
	}
	
	private function write($s) {
		return $this->telnet->write($s, false);
	}
	
	private function goDown($n=1) {
		for ($i=0;$i<$n;$i++) {
			$this->write(chr(27).'[B');
		}
	}
	
	private function goRight($n=1) {
		for ($i=0;$i<$n;$i++) {
			$this->write(chr(27).'[C');
		}
	}
	
	private function nextPage() {
		usleep($this->pause);
		$this->write(chr(27).'[U');
		usleep($this->pause);
	}
	
	private function save() {
		$this->write(chr(27).'SB');
	}
	
	private function cancel() {
		$this->write(chr(27).'Ow');
	}
	
	public function disconnect() {
		if ($this->connected) {
			$this->cancel();
			$this->telnet->disconnect();
		}
	}
	
	public function cmd($cmd, $pages=1, $strip=true) {
		$this->connect();
		$r_text = '';
		
		$this->write($cmd."\n");
		usleep($this->pause * 3);
		
		$text = $this->telnet->getBuf();
		
		//dump($text);
		
		$r_text.= $this->parseTerm($text);
		
		if ($pages>1) {
			for ($i=1;$i<$pages;$i++) {
				$this->nextPage();
				$text = $this->telnet->getBuf();
				//dump($text);
				$r_text.= $this->parseTerm($text);
			}
		}
		
		$this->cancel();
		
		return $r_text;
	}
	
	function parseTerm($text) {
		
		$text = str_replace(chr(0), '', $text);
	
		$text = preg_replace('/\x1b\[\d+;\d+m/', ' ', $text);
		$text = preg_replace('/\x1b\[\d+[bmGJ]/', ' ', $text);
		$text = preg_replace('/\x1b[<78]/', '', $text);
		$text = preg_replace('/\x1b\[(\d+);(\d+)H/', '[|$1|$2|', $text);
		$text = preg_replace('/\x1b/', '', $text);
		
		$text = preg_replace("/\r+/", '', $text);
		
		$text = preg_replace("/\n/", '[|', $text);
		//dump($text);
		$text = explode('[|', $text);
			
		$data = array();
		foreach ($text as $item) {
			$item = trim($item, '|');
			$x = explode('|', $item);
			if (count($x)>2) {
				//dump($x);
				$xt = trim($x[2]);
				if (!empty($xt)) $data[$x[0]][$x[1]] = $x[2];
				//else $data[$x[0]][$x[1]] = trim($x[2]);
			} else {
				$xt = trim($x[0]);
				if (!empty($xt)) $data[][1] = $x[0];
				//else $data[][$x[1]] = trim($x[2]);
			}
		}
		
		//dump($data);
		
		$keys = array_keys($data);
		$n = count($keys) - 1;
		$n = $keys[$n];
		
		$data2 = array();
		for ($i=0;$i<=$n;$i++) {
		//foreach ($data as $i=>$item)
			if (!array_key_exists($i, $data)) {
				$data2[$i] = '';
			} else {
				//$data2[$i] = implode(' ', $data[$i]);
				$data2[$i] = $this->term_xwrite($data[$i]);
			}
		}
		
		//dump($data2);
		
		$data2 = implode("\n", $data2);
		return $data2;
	}

	private function term_xwrite($data) {
		$s = '';
		//dump($data);
		foreach ($data as $key=>$item) {
			$n = $key - strlen($s);
			$sp = @str_repeat(' ', $n);
			$s.= $sp . $item;
		}
		return $s;
	}
	
	// skill string like 
	// 10/1 1/4 2/3
	public function str2skills($s) {
		$s = explode(' ', $s);
		$skills = array();
		foreach ($s as $pair) {
			$sk = explode('/', $pair);
			$skills[] = array('sn'=>$sk[0], 'sl'=>$sk[1]);
		}
		return $skills;
	}

	public function str2agent_skills($s) {
		$s = explode(' ', $s);
		$agent = '';
		if (strstr($s[0], '/')===false) {
			$agent = $s[0];
			$s = array_splice($s, 1, count($s));
		}
		
		$skills = array();
		foreach ($s as $pair) {
			$sk = explode('/', $pair);
			$skills[] = array('sn'=>$sk[0], 'sl'=>$sk[1]);
		}
		return array('agent'=>$agent, 'skills'=>$skills);
	}

	public function skills2str($skills) {
		$s = '';
		foreach ($skills as $skill) {
			$s.= $skill['sn'].'/'.$skill['sl'].' ';
		}
		return trim($s);
	}
	
	
}


