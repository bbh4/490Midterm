<?php
require_once '../vendor/autoload.php';
require_once '../databases/MongoConnector.php';
require_once '../rabbit/RabbitMQConnection.php';
require_once '../logging/LogWriter.php';
use PhpAmqpLib\Message\AMQPMessage;
use rabbit\RabbitMQConnection;
use logging\LogWriter;



$rmq_connection = new RabbitMQConnection('storage_user', 'RetrieveExchange', 'storage');
$rmq_channel = $rmq_connection->getChannel();

// User Retrieve
$userRetrieve_callback = function ($request) {
	$logger = new LogWriter('/var/log/dnd/retrieval.log');
	$client = (new MongoConnector())->getConnection();
	$usersCollection = $client->storage->users;
	$charactersCollection = $client->storage->characters;
	$reqArray = unserialize($request->body);
	$logger->info("This is body: " . $request->body);
	$logger->info("This is 0: " . $reqArray[0]);
	$logger->info("This is 1: " . $reqArray[1]);
	$requestFlow = $reqArray[0];
	$username = $reqArray[1];
	$error = "E";


	switch($requestFlow){
		case "getUserStore":
			$logger->info("Getting User doc...");
			$userDocument = $usersCollection->find(['username' => $username]);
			$msg = new AMQPMessage (
				$userDocument,
				array('correlation_id' => $request->get('correlation_id'))
				);
			$logger->info("document found: " . $userDocument);
			break;
		case "getCharacters":
			$logger->info("Getting Characters");
			$cursor = $charactersCollection->find(['username' => $username]);
			$characters = iterator_to_array($cursor);

			$msg = new AMQPMessage (
				serialize($characters),
				array('correlation_id' => $request->get('correlation_id'))
			);
			$logger->info("Characters found: " . $characters);
			break;
		default:
			$msg = new AMQPMessage (
				$error,
				array('correlation_id' => $request->get('correlation_id'))
			);
	}

$request->delivery_info['channel']->basic_publish( $msg, '', $request->get('reply_to'));
$logger->info("Sent back Message");
};

$rmq_channel->basic_qos(null, 1, null);

$rmq_channel->basic_consume($queue_name, '', false, true, false, false, $userRetrieve_callback);

while (true) {
	$rmq_channel->wait();
}

$rmq_connection->close();
?>
