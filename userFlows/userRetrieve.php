<?php
require_once '../vendor/autoload.php';
require_once '../databases/MongoDB.php';
require_once '../rabbit/RabbitMQConnection';
require_once '../logging/LogWriter.php';
use PhpAmqpLib\Message\AMQPMessage;
use rabbit\RabbitMQConnection;
use databases\MongoDB;
use logging\LogWriter;

$logger = new LogWriter('/var/log/dnd/retrieval.log');

$client = (new MongoDB())->getConnection();

$rmq_connection = new RabbitMQConnection('RetrievalExchange', 'userFlows');
$rmq_channel = $rmq_connection->getChannel();

// User Retrieve
$userRetrieve_callback = function ($req) {
	$DnDdb = $client->db;
	$charCollection = $DnDdn->characters;
	$userCollection = $DnDdn->users;
	$reqArray = unserialize($req->body);
	$logger->info("This is body: " . $req->body);
	$logger->info("This is 0: " . $reqArray[0]);
	$logger->info("This is 1: " . $reqArray[1]);
	$reqStr = $reqArray[0];
	$reqDoc = $reqArray[1];
	$error = "E";

	$msg = new AMQPMessage (
		$error,
		array('correlation_id' => $char->get('correlation_id'))
	);

	switch($req){
		case "userDoc":
			$logger->info("Getting User doc...");
			$foundUserDoc = $userCollection->find(['username' => $reqDoc]);
			$msg = new AMQPMessage (
				$foundUserDoc,
				array('correlation_id' => $char->get('correlation_id'))
				);
			$logger->info("document found: " . $foundUserDoc);
			break;
		case "charDoc":
			$logger->info("Getting Character doc...");
			$foundChar = $charCollection->find(['_id' => $reqDoc]);
			$msg = new AMQPMessage (
				$foundChar,
				array('correlation_id' => $char->get('correlation_id'))
				);
			$logger->info("Character found: " . $foundChar);
			break;
	}

$req->delivery_info['channel']->basic_publish( $msg, '', $req->get('reply_to'));
$logger->info("Sent back Message");
};

$rmq_channel->basic_qos(null, 1, null);

$userRetrieve_callback = execute();
$rmq_channel->basic_consume($queue_name, '', false, true, false, false, $userRetrieve_callback);

while (true) {
	$rmq_channel->wait();
}

$rmq_connection->close();
?>
