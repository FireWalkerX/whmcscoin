<?php

function litecoin_config() {
    $configarray = array(
        "FriendlyName" => array(
            "Type" => "System",
            "Value" => "Litecoin",
        ),
        "confirmations" => array(
            "FriendlyName" => "Confirmations",
            "Type" => "text",
            "Size" => "3",
            "Description" => "Amount of confirmations required before accepting transaction",
        ),
        "username" => array(
            "FriendlyName" => "RPC Username",
            "Type" => "text", "Size" => "20",
        ),
        "password" => array(
            "FriendlyName" => "RPC Password",
            "Type" => "text",
            "Size" => "20",
        ),
        "host" => array(
            "FriendlyName" => "RPC Hostname",
            "Type" => "text",
            "Size" => "20",
        ),
        "port" => array(
            "FriendlyName" => "RPC Port",
            "Type" => "text",
            "Size" => "20",
        ),
    );
    return $configarray;
}

function litecoin_link($params) {
	full_query("CREATE TABLE IF NOT EXISTS `mod_gw_litecoin_payments` (`invoice_id` int(11) NOT NULL, `amount` float(11,8) NOT NULL, `address` varchar(64) NOT NULL, `confirmations` int(11) NOT NULL, PRIMARY KEY (`invoice_id`))");
        full_query("CREATE TABLE IF NOT EXISTS `mod_gw_litecoin_info` (`invoice_id` int(11) NOT NULL, `secret` varchar(64) NOT NULL, `address` varchar(64) NOT NULL, PRIMARY KEY (`invoice_id`))");
	
	$q = mysql_fetch_array(mysql_query("SELECT * FROM `mod_gw_litecoin_info` WHERE invoice_id = '{$params['invoiceid']}'"));
	if($q['address']) {
            $amount = $q['amount'];
            $address = $q['address'];
	}
	
	$secret = '';
	$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
	for($i = 0; $i < 64; $i++) {
		$secret .= substr($characters, rand(0, strlen($characters) - 1), 1);
	}
	
	# Grab the amount and everything from BTC-e's ticker
	$ltc_ticker = json_decode(file_get_contents("https://btc-e.com/api/2/ltc_usd/ticker"), true);
        if (!$ltc_ticker) {
            return "We're sorry, but you cannot use Litecoin to pay for this transaction at this time.";
        }
	$amount = round($params['amount'] / $ltc_ticker['ticker']['sell'], 8);
	
	# Build Litecoin Information Here
	require_once 'whmcscoin/jsonRPCClient.php';
	$litecoin = new jsonRPCClient("http://{$params['username']}:{$params['password']}@{$params['host']}:{$params['port']}"); 
	
	if(!$litecoin->getinfo()){ //This won't work. Gotta make this work.
		return "We're sorry, but you cannot use Litecoin to pay for this transaction at this time.";
	}
	
	$address = $litecoin->getaccountaddress($secret);
	
	if (!$address) { //This probably won't work either.{
            return "We're sorry, but you cannot use Litecoin to pay for this transaction at this time.";
	}
	$code = 'Please send <strong>'.$params['amount'].'</strong>worth of LTC to address:<br /><strong><a href="#">'.$address.'</a></strong><br /><span id="ltcprice">Currently, '.$params['amount'].' is <strong>'.$amount.'</strong> LTC</span>';
	
        mysql_query("INSERT INTO `mod_gw_litecoin_info` SET invoice_id = '{$params['invoiceid']}', address = '" . mysql_real_escape_string($address) . "', secret = '{$secret}'");
	return "<iframe src='{$params['systemurl']}/modules/gateways/litecoin.php?invoice={$params['invoiceid']}' style='border:none; height:120px'>Your browser does not support frames.</iframe>";
	return $code;

}

if($_GET['invoice']) {
	require('./../../dbconnect.php');
	include("./../../includes/gatewayfunctions.php");
	$gateway = getGatewayVariables('litecoin');
        print_r($params);
	?>
<!doctype html>
<html>
	<head>
		<title>Litecoin Invoice Payment</title>
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
		<script type="text/javascript">
		updatePrice = true;
		function checkStatus() {
			$.get("litecoin.php?checkinvoice=<?php echo $_GET['invoice']; ?>", function(data) {
				if(data == 'paid') {
					updatePrice = false;
					parent.location.href = '<?php echo $gateway['systemurl']; ?>/viewinvoice.php?id=<?php echo $_GET['invoice']; ?>';
				} else if(data == 'unpaid') {
					setTimeout(checkStatus, 5000);
				} else {
					updatePrice = false;
					$("#content").html("Transaction confirming... " + data + "/<?php echo $gateway['confirmations_required']; ?> confirmations");
					setTimeout(checkStatus, 10000);
				}
			});
		}
		
		function getPrice() {
			if(updatePrice){
				$.get("litecoin.php?checkprice=<?php echo $_GET['invoice']; ?>", function(data) {
					$("#ltcprice").html(data);
					setTimeout(getPrice, 5000);
				});
			}
		}
		
		</script>
		<style>
		body {
			font-family:Tahoma;
			font-size:12px;
			text-align:center;
		}
		a:link, a:visited {
			color:#08c;
			text-decoration:none;
		}
		a:hover {
			color:#005580;
			text-decoration:underline
		}
		</style>
	</head>
	<body onload="checkStatus(); getPrice();">
		<p id="content"><?php echo litecoin_get_frame(); ?></p>
	</body>
</html>
<?php
}

function litecoin_get_frame($params, $amount) {
	global $gateway;

        $invoiceInfo = mysql_fetch_array(select_query('tblinvoices', 'total', array('id'=>$_GET['invoice'],)));
        $amountToPay = $invoiceInfo['total'];
        while ($row = mysql_fetch_array(select_query('tblaccounts', array('amountin',), array('invoice_id'=>$_GET['invoice'],)))) {
            $amountToPay -= $row['amountin'];
        }
        
        # Convert to SQL Helper
	$q = mysql_fetch_array(mysql_query("SELECT * FROM `mod_gw_litecoin_info` WHERE invoice_id = '" . mysql_real_escape_string($_GET['invoice']) . "'"));
	if(!$q['address']) {
		return "We're sorry, but you cannot use Litecoin to pay for this transaction at this time.";
	}
        
        # We should make this a function
        $ltc_ticker = json_decode(file_get_contents("https://btc-e.com/api/2/ltc_usd/ticker"), true);
        if (!$ltc_ticker) {
            return "We're sorry, but you cannot use Litecoin to pay for this transaction at this time.";
        }
	$amount = round($amountToPay / $ltc_ticker['ticker']['sell'], 8);
        
        # need to display how much is left to be paid
	return 'Please send <strong>&#36;'.$amountToPay.'</strong> worth of LTC to address:<br /><strong><a href="#">'.$q['address'].'</a></strong><br /><span id="ltcprice">Currently, &#36;'.$amountToPay.' is <strong>'.$amount.'</strong> LTC</span>';
}

if($_GET['checkinvoice']) {
	header('Content-type: text/plain');
	require('./../../dbconnect.php');
	$q = mysql_fetch_array(mysql_query("SELECT * FROM `tblinvoices` WHERE invoice_id = '" . mysql_real_escape_string($_GET['checkinvoice']) . "'"));
        
        # this still needs work
        # want to return amount left to pay
        
	if($q['status'] == 'Paid') {
		echo 'paid';
	} elseif($q['status'] == 'confirming') {
		echo $q['confirmations'];
	} else {
		echo 'unpaid';
	}
}else if($_GET['checkprice']) {
	header('Content-type: text/plain');
	require('./../../dbconnect.php');
	$q = mysql_fetch_array(mysql_query("SELECT * FROM `tblinvoices` WHERE invoice_id = '" . mysql_real_escape_string($_GET['checkprice']) . "'"));
	
	$ltc_ticker = json_decode(file_get_contents("https://btc-e.com/api/2/ltc_usd/ticker"), true);
	if (!$ltc_ticker) {
		return "Litecoin exchange rate currently unavailable!";
	}
	$invoiceInfo = mysql_fetch_array(select_query('tblinvoices', 'total', array('id'=>$_GET['checkprice'],)));
	$amountToPay = $invoiceInfo['total'];
	while ($row = mysql_fetch_array(select_query('tblaccounts', array('amountin',), array('invoice_id'=>$_GET['checkprice'],)))) {
		$amountToPay -= $row['amountin'];
	}
	$amount = round($amountToPay / $ltc_ticker['ticker']['sell'], 8);
	//$amount = '123';
	if($amountToPay == $invoiceInfo['total'])
			echo "Currently, &#36;{$amountToPay} is <strong>{$amount}</strong> LTC.";
	else
			echo "You have <strong>{$amount}</strong> LTC remaining to pay.";


}



?>
