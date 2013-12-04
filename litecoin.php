<?php

function litecoin_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value" => "Litecoin"),
     "username" => array("FriendlyName" => "RPC Username", "Type" => "text", "Size" => "20", ),
     "password" => array("FriendlyName" => "RPC Password", "Type" => "text", "Size" => "20", ),
     "host" => array("FriendlyName" => "RPC Hostname", "Type" => "text", "Size" => "20", ),
     "port" => array("FriendlyName" => "RPC Port", "Type" => "text", "Size" => "20", ),
    );
	return $configarray;
}

function litecoin_link($params) {
	mysql_query("CREATE TABLE IF NOT EXISTS `litecoin_payments` (`invoice_id` int(11) NOT NULL, `amount` float(11,8) NOT NULL, `address` varchar(64) NOT NULL, `secret` varchar(64) NOT NULL, `confirmations` int(11) NOT NULL, `status` enum('unpaid','confirming','paid') NOT NULL, PRIMARY KEY (`invoice_id`))");
	
	$q = mysql_fetch_array(mysql_query("SELECT * FROM `litecoin_payments` WHERE invoice_id = '{$params['invoiceid']}'"));
	if($q['address']) {
		$amount = $q['amount'];
		$address = $q['address'];
		$confirmations = $q['confirmations'];
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
	require_once 'jsonRPC/jsonRPCClient.php';
	$litecoin = new jsonRPCClient("http://{$params['username']}:{$params['password']}@{$params['host']}:{$params['port']}"); 
	
	if(!$litecoin->getinfo()){
		return "We're sorry, but you cannot use Litecoin to pay for this transaction at this time.";
	}
	
	$address = $litecoin->getaccountaddress($secret);
	
	if (!$address)
	{
		return "We're sorry, but you cannot use Litecoin to pay for this transaction at this time.";
	}
	$code = 'Please send <strong>'.$amount.'</strong> LTC to address:<br /><strong><a href="#">'.$address.'</a></strong>';
	
	mysql_query("INSERT INTO `litecoin_payments` SET invoice_id = '{$params['invoiceid']}', amount = '" . mysql_real_escape_string($amount) . "', address = '" . mysql_real_escape_string($address) . "', secret = '$secret', confirmations = '0', status = 'unpaid'");
	return "<iframe src='{$params['systemurl']}/modules/gateways/litecoin.php?invoice={$params['invoiceid']}' style='border:none; height:120px'>Your browser does not support frames.</iframe>";
	return $code;

}

if($_GET['invoice']) {
	require('./../../dbconnect.php');
	include("./../../includes/gatewayfunctions.php");
	$gateway = getGatewayVariables('litecoin');
	?>
<!doctype html>
<html>
	<head>
		<title>Litecoin Invoice Payment</title>
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
		<script type="text/javascript">
		function checkStatus() {
			$.get("litecoin.php?checkinvoice=<?php echo $_GET['invoice']; ?>", function(data) {
				if(data == 'paid') {
					parent.location.href = '<?php echo $gateway['systemurl']; ?>/viewinvoice.php?id=<?php echo $_GET['invoice']; ?>';
				} else if(data == 'unpaid') {
					setTimeout(checkStatus, 5000);
				} else {
					$("#content").html("Transaction confirming... " + data + "/<?php echo $gateway['confirmations_required']; ?> confirmations");
					setTimeout(checkStatus, 10000);
				}
			});
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
	<body onload="checkStatus()">
		<p id="content"><?php echo litecoin_get_frame(); ?></p>
	</body>
</html>
<?php
}

function litecoin_get_frame() {
	global $gateway;

	$q = mysql_fetch_array(mysql_query("SELECT * FROM `litecoin_payments` WHERE invoice_id = '" . mysql_real_escape_string($_GET['invoice']) . "'"));
	if(!$q['address']) {
		return "We're sorry, but you cannot use Litecoin to pay for this transaction at this time.";
	}
	
	return "Please send <b><a href='litecoin:{$q['address']}?amount={$q['amount']}&label=" . urlencode($gateway['companyname'] . ' Invoice #' . $q['invoice_id']) . "'>{$q['amount']} LTC</a></b> to address:<br /><br /><b>{$q['address']}</b>";
}

if($_GET['checkinvoice']) {
	header('Content-type: text/plain');
	require('./../../dbconnect.php');
	$q = mysql_fetch_array(mysql_query("SELECT * FROM `litecoin_payments` WHERE invoice_id = '" . mysql_real_escape_string($_GET['checkinvoice']) . "'"));

	if($q['status'] == 'paid') {
		echo 'paid';
	} elseif($q['status'] == 'confirming') {
		echo $q['confirmations'];
	} else {
		echo 'unpaid';
	}
}

?>