<?php

# Required File Includes
include("../../../dbconnect.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$gatewaymodule = "litecoin"; # Enter your gateway module name here replacing template

$gateway = getGatewayVariables($gatewaymodule);
if(!$gateway['type']) {
	die("Module Not Activated");
}

if($_GET['test']) {
	die("Test mode not allowed.");
}

$rpc = "http://{$gateway['username']}:{$gateway['password']}@{$gateway['host']}:{$gateway['port']}";
# Build Litecoin Information Here
require_once '../jsonRPC/jsonRPCClient.php';
$litecoin = new jsonRPCClient($rpc); 

if(!$litecoin->getinfo()){
	die('could not connect to litecoind');
}

$sql = 'SELECT * FROM `litecoin_payments` WHERE `status` = "Unpaid"';
$results = mysql_query($sql);
while($result = mysql_fetch_array($results)){
	$invoice = mysql_fetch_array(mysql_query("SELECT * FROM `tblinvoices` WHERE id = '{$result['invoice_id']}'"));
	$amount = $result['amount'];
	$received = $litecoin->getbalance($result['secret']);
	if($result['amount'] <= $received){
		$fee = 0;
		$transid = $litecoin->getaccountaddress($result['secret']);
		$confirmations = 0;
		mysql_query("UPDATE `litecoin_payments` SET confirmations = '" . mysql_real_escape_string($confirmations) . "', status = 'paid' WHERE invoice_id = '{$result['invoice_id']}'");
		addInvoicePayment($result['invoice_id'],$transid,$invoice['total'],$fee,$gatewaymodule);
		logTransaction($gateway["name"],array('address'=>$transid,'amount'=>$received),"Successful");
	}
}

?>
