<?php
require_once '../vendor/autoload.php';
require_once '../databases/MongoDB.php';
require_once '../rabbit/RabbitMQConnection';
require_once '../logging/LogWriter.php';
use PhpAmqpLib\Message\AMQPMessage;
use rabbit\RabbitMQConnection;
use databases\MongoDB;
use logging\LogWriter;

$logger = new LogWriter('/var/log/dnd/storage.log');

$client = (new MongoDB())->getConnection();

$rmq_connection = new RabbitMQConnection('StorageExchange', 'userFlows');
$rmq_channel = $rmq_connection->getChannel();

// User Update
$userStore_callback = function ($req) {
	$DnDdb = $client->db;
	$charCollection = $DnDdn->characters;
	$userCollection = $DnDdn->users;
	$reqArray = unserialize($req->body);
	$logger->info("This is body: ", $req->body);
	$logger->info("This is 0: " . $reqArray[0]);
	$logger->info("This is 1: " . $reqArray[1]);
	$logger->info("This is 2: " . $reqArray[2]);
	$reqStr = $reqArr[0];
	$stuffID = $reqArr[1];
	$document = $reqArr[2];
	$error = "E";
	$success = "S";

	$msg = new AMQPMessage (
		$error,
		array('correlation_id' => $char->get('correlation_id'))
		);
	
	switch ($reqStr) {
		case "updateUser":
			$logger->info("Updating User doc...");
			$updateStuff = $userCollection->updateOne(
			['_id' => $stuffID],
			[$document],
			["upsert" => true]
			);
			$msg = new AMQPMessage (
				$success,
				array('correlation_id' => $req->get('correlation_id'))
			);
			$logger->info("Update success");
			break;
		case "updateCharacter":
			$logger->info("Getting Char doc...");
			$updateStuff = $charCollection->updateOne(
			['_id' => $stuffID],
			[$document],
			["upsert" => true]
			);
			$msg = new AMQPMessage (
				$success,
				array('correlation_id' => $req->get('correlation_id'))
			);
			$logger->info("Update success");
			break;
	}

$req->delivery_info['channel']->basic_publish( $msg, '', $req->get('reply_to'));
$logger->info("Sent back message");

};

list($chan, $rabbitConn) = getRabbitMQ();
$chan->basic_qos(null, 1, null);

$userStore_callback = execute();
$chan->basic_consume($queue_name, '', false, true, false, false, $userStore_callback);

while (true) {
	$chan->wait();
}

$chan->close();
$rmq_connection->close();
?>
