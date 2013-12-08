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
require_once '../whmcscoin/jsonRPCClient.php';
$litecoin = new jsonRPCClient($rpc); 

if(!$litecoin->getinfo()){
	die('could not connect to litecoind');
}

while($result = mysql_fetch_array(select_query('tblinvoices', 'id,amount', array('paymentmethod'=>$gatewaymodule,'status'=>'Unpaid'), 'id', 'DESC'))){
        $ltc_info = mysql_fetch_array(select_query("mod_gw_litecoin_info", "secret", array("invoice_id"=>$result['id']), "invoice_id", "DESC", 1));
	$amount = $result['amount'];
	$received = $litecoin->listtransactions($ltc_info['secret'], 1000); # I feel like we can do better than just get the LAST ONE THOUSAND TRANSACTIONS but we'll figure that out later
        foreach ($received as $recArr) {
            # Let's be sure we're RECEIVING this transaction.
            if ($recArr['category'] != 'receive') {
                continue;
            }
            
            # Transaction ID already in the DB?
            # We might should change this later.
            $txCheck = mysql_fetch_array(select_query("tblaccounts", "id", array("transid"=>$recArr['txid'], "invoice_id"=>$result['id'], "gateway"=>$gatewaymodule), "id", "DESC", 1));
            if ($txCheck['transid']) { //change later to check if txn id already exists in db
                continue;
            }
            
            # SOMETHING IS WRONG WHAT IS THIS I DONT EVEN
            if (!is_numeric($recArr['confirmations'])) {
                continue;
            }
            
            # If there's not enough confirmations, we'll just save it for later
            if ($recArr['confirmations'] < $gateway['confirmations']) {
                continue;
            }
            
            # Let's see how much we've received.
            $ltc_ticker = json_decode(file_get_contents("https://btc-e.com/api/2/ltc_usd/ticker"), true);
            if (!$ltc_ticker) {
                continue; # Something happened. We'll try again later.
            }
            $amount_received = round($recArr['amount'] * $ltc_ticker['ticker']['sell'], 2);
            if (!$amount_received) { #Either some bug or it's worth $0 which will mark the invoice as fully paid
                continue;
            }
            
            # Let's log the litecoin payment.
            insert_query('mod_gw_litecoin_payments', array("invoice_id"=>$result['id'],"amount"=>$recArr['amount'],"address"=>$recArr['address'],"confirmations"=>$recArr['confirmations']));
            addInvoicePayment($result['id'], $recArr['txid'], $amount_received, 0, $gatewaymodule);
            logTransaction($gateway['name'], $recArr, "Successful");
        }
}

?>
