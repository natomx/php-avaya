<?php
error_reporting(E_ALL|E_STRICT);  
ini_set('display_errors', true);
set_include_path(implode(PATH_SEPARATOR, array( dirname(__FILE__),'../../library',get_include_path() )));
require_once 'PrettyBlueScreen.php';
require_once "Zend/Loader.php";
Zend_Loader::registerAutoload();

require_once 'Avaya/CM.php';

$cm = Avaya_CM::getInstance();

$cm->connect('server', 5023, 'serv', 'login', 'password');

$protocol = $cm->getProtocol();

//$data = $protocol->get('display system-parameters country-options', '4a3cff00');

//$data = $protocol->monitorSkill('1');
//$data = $protocol->getAgentLog(99009);
$data = $protocol->listAgents();

//99910 - demo agent

dump($data);

function dump($x) { Zend_Debug::dump($x); }


?>