<?php
require_once '../vendor/autoload.php';
require_once '../databases/ForumsDB.php';
require_once '../rabbit/RabbitMQConnection';
require_once '../logging/LogWriter.php';
use PhpAmqpLib\Message\AMQPMessage;
use rabbit\RabbitMQConnection;
use databases\ForumsDB;
use logging\LogWriter;

$rmq_connection = new RabbitMQConnection('GetPostsExchange', 'messageBoard');
$rmq_channel = $rmq_connection->getChannel();

//get forums
$GetForums_callback = function ($request) {
	$logger = new LogWriter('/var/log/dnd/getForums.log');
	$db_connection = (new ForumsDB())->getConnection();

	$requestData = unserialize($request->body);
	$reqStr = $requestData[0];
	$id = $requestData[1];
	$error = "E";

	try {

		switch ($reqStr) {
			case "getForums":
				$userReq = "CALL getForums()";
				$stmt = $db_connection->prepare($userReq);
				$logger->info("getting forums");
				break;
			case "getThreads":
				$userReq = "CALL getThreads(?)";
				$stmt = $db_connection->prepare($userReq);
				$stmt->bindParam(1, $id, PDO::PARAM_INT);
				$logger->info("getting threads for user");
				break;
			case "getThread":
				$userReq = "CALL getThread(?)";
				$stmt = $db_connection->prepare($userReq);
				$stmt->bindParam(1, $id, PDO::PARAM_INT);
				$logger->info("getting thread for given ID");
				break;
			case "getReplies":
				$userReq = "CALL getReplies(?)";
				$stmt = $db_connection->prepare($userReq);
				$stmt->bindParam(1, $id, PDO::PARAM_INT);
				$logger->info("getting replies for given thread");
				break;
			default:
				$msg = new AMQPMessage (
					$error,
					array('correlation_id' => $request->get('correlation_id'))
				);
		}

		$stmt->execute();

		$db_response = $stmt->fetchAll();

		$serialized_array=serialize($db_response);

		$msg = new AMQPMessage (
			$serialized_array,
			array('correlation_id' => $request->get('correlation_id'))
		);

	} catch (PDOException $e) {
		$logger->error("Error occurred:" . $e->getMessage());
	}

	$request->delivery_info['rmq_channel']->basic_publish( $msg, '', $request->get('reply_to'));
	$logger->info("Sent back Message");
};


$rmq_channel->basic_qos(null, 1, null);
$rmq_channel->basic_consume($queue_name, '', false, true, false, false, $GetForums_callback);

while (true) {
	$rmq_channel->wait();
}

$rmq_connection->close();
?>