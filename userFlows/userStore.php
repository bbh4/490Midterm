<?php
require_once '../vendor/autoload.php';
require_once '../databases/MongoConnector.php';
require_once '../rabbit/RabbitMQConnection';
require_once '../logging/LogWriter.php';
use PhpAmqpLib\Message\AMQPMessage;
use rabbit\RabbitMQConnection;
use logging\LogWriter;

$rmq_connection = new RabbitMQConnection('storage_user', 'StoreExchange', 'storage');
$rmq_channel = $rmq_connection->getChannel();

// User Update
$userStore_callback = function ($request) {
	$logger = new LogWriter('/var/log/dnd/userStorage.log');
	$client = (new MongoConnector())->getConnection();
	$database = $client->db;
	$charCollection = $database->characters;
	$userCollection = $database->users;
	$reqArray = unserialize($request->body);
	$logger->info("This is body: ", $request->body);
	$logger->info("This is 0: " . $reqArray[0]);
	$logger->info("This is 1: " . $reqArray[1]);
	$logger->info("This is 2: " . $reqArray[2]);
	$reqStr = $reqArray[0];
	$stuffID = $reqArray[1];
	$document = $reqArray[2];
	$error = "E";
	$success = "S";


	switch ($reqStr) {
		case "updateUser":
			$logger->info("Updating User doc...");
			$userCollection->updateOne(
				['_id' => $stuffID],
				[$document],
				["upsert" => true]
			);
			$msg = new AMQPMessage (
				$success,
				array('correlation_id' => $request->get('correlation_id'))
			);
			$logger->info("Update success");
			break;
		case "updateCharacter":
			$logger->info("Getting Char doc...");
			$charCollection->updateOne(
				['_id' => $stuffID],
				[$document],
				["upsert" => true]
			);
			$msg = new AMQPMessage (
				$success,
				array('correlation_id' => $request->get('correlation_id'))
			);
			$logger->info("Update success");
			break;
		default:
			$msg = new AMQPMessage (
				$error,
				array('correlation_id' => $request->get('correlation_id'))
			);
	}

	$request->delivery_info['channel']->basic_publish( $msg, '', $request->get('reply_to'));
	$logger->info("Sent back message");

};

$rmq_channel->basic_qos(null, 1, null);

$rmq_channel->basic_consume($queue_name, '', false, true, false, false, $userStore_callback);

while (true) {
	$rmq_channel->wait();
}

$rmq_connection->close();
?>
