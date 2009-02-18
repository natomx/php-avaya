<?php

require_once 'Telnet.php';

class Avaya_CM 
{
	
	protected $_protocolType = 'Ossis';
	
	protected $protocol = null; // Protocol object
	
	protected $_host = null;
	protected $_port = 5023;
	protected $_login = null;
	protected $_password = null;
	protected $_pin = null;
	
	// Telnet object
	protected $telnet = null;
	
	protected $_connected = false;
	
	// Singleton instance
	protected static $_instance = null;
	
	// Singleton pattern implementation makes "new" unavailable
	private function __construct()
	{}
	
	// Singleton pattern implementation makes "clone" unavailable
	private function __clone()
	{}
	
	// Returns Singleton instance
	public static function getInstance()
	{
		if (null === self::$_instance) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
	
	public function isConnected()
	{
		return $this->_connected;
	}
	
	public function getProtocol()
	{
		return $this->protocol;
	}
	
	public function setCredentials($login, $passwd, $pin=null)
	{
		$this->_login 	 = $login;
		$this->_password = $passwd;
		$this->_pin 	 = $pin;
		return $this;
	}
	
	public function connect($host, $port=5023, $login=null, $passwd=null, $pin=null)
	{
		if (!$this->_connected) {
			$this->_host = $host;
			$this->_port = $port;
			$this->setCredentials($login, $passwd, $pin);
		
			$this->telnet = new Telnet($this->_host, $this->_port);
			$this->telnet->login($this->_login, $this->_password);
		
			if ($this->_pin) {
				$this->telnet->waitPrompt('Pin:');
				$this->telnet->write($pin);
			}
		
			require_once 'Avaya/Protocol/' . $this->_protocolType . '.php';
			$class = 'Avaya_Protocol_'.$this->_protocolType;
			$this->protocol = new $class;
			$this->protocol->setTelnet($this->telnet);
			//$this->protocol->connect();
				
			// Term Emulation 
			$this->telnet->waitPrompt('[513]');
			$this->telnet->write($this->protocol->getTermEmulation());
			$this->telnet->waitPrompt('[y]');
			$this->telnet->write('y');

			$this->telnet->waitPrompt($this->protocol->getTermPrompt());
		
			$this->_connected = true;
		}
		return $this->_connected;
	}
	public function disconnect() 
	{
		if ($this->connected) {
			$this->telnet->disconnect();
		}
	}
	
	public function monitorSkill($skill) {
		return $this->protocol->monitorSkill($skill);
	}
	
	public function listAgents() {
		return $this->protocol->listAgents();
	}
	
	public function getAgentLog($agentExt) {
		return $this->protocol->getAgentLog($agentExt);
	}
}