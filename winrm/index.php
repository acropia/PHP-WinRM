<pre>
<?php
require_once('winrm.php');
require_once('transport.php');
require_once('protocol.php');

$session = new Session('http://localhost.ergens.nl/wsman', 'joop');
$response = $session->runCommand('ipconfig', '/all');

var_export($session);
var_export($response);

?>
