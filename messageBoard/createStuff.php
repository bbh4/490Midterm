<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once '../databases/ForumsDB.php';
require_once '../rabbit/RabbitMQConnection';
require_once '../logging/LogWriter.php';
use PhpAmqpLib\Message\AMQPMessage;
use databases\ForumsDB;
use logging\LogWriter;

// TODO Rename this file
$rmq_connection = new RabbitMQConnection('createStuffExchange', 'vhost');
$rmq_channel = $rmq_connection->getChannel();

//Create Stuff
$createStuff_callback = function ($request) {
	$logger = new LogWriter('/var/log/dnd/MessageBoard.create.log');
	$logger->info("Creating stuff for User...");
	$requestData = unserialize($request->body);
	$requestFlow = $requestData[0];
	$success = "S";
	$error = "E";

	$msg = new AMQPMessage (
		$error,
		array('correlation_id' => $request->get('correlation_id'))
	);

	$pdo = (new ForumsDB())->getConnection();

	$logger->info("connected to forum database");

	switch($requestFlow){
		case "createThread":
			$forumID = $requestData[1];
			$name = $requestData[2];
			$content = $requestData[3];
			$user = $requestData[4];
			$sql = "CALL createThread(?,?,?,?)";
			$stmt = $pdo->prepare($sql);
			$stmt->bindParam(1, $forumID, PDO::PARAM_INT);
			$stmt->bindParam(2, $name, PDO::PARAM_STR);
			$stmt->bindParam(3, $content, PDO::PARAM_STR);
			$stmt->bindParam(4, $user, PDO::PARAM_STR);
			$stmt->execute();
			$msg = new AMQPMessage (
				$success,
				array('correlation_id' => $request->get('correlation_id'))
			);
			$logger->info("Created Thread");
			break;
		case "createReply":
			$threadID = $requestData[1];
			$content = $requestData[2];
			$user = $requestData[3];
			$sql = "CALL createReply(?,?,?)";
			$stmt = $pdo->prepare($sql);
			$stmt->bindParam(1, $threadID, PDO::PARAM_INT);
			$stmt->bindParam(2, $content, PDO::PARAM_STR);
			$stmt->bindParam(3, $user, PDO::PARAM_STR);
			$stmt->execute();
			$msg = new AMQPMessage (
				$success,
				array('correlation_id' => $request->get('correlation_id'))
			);
			$logger->info("Created Reply");
			break;
	}
	$logger->info("Request Created");

	$request->delivery_info['channel']->basic_publish( $msg, '', $request->get('reply_to'));
	$logger->info("Delivered Message");
};

$rmq_channel->basic_qos(null, 1, null);
$rmq_channel->basic_consume($queue_name, '', false, true, false, false, $createStuff_callback);

while (true) {
	$rmq_channel->wait();
}
$rmq_connection->close();
?>