<?php
require_once '../vendor/autoload.php';
require_once '../databases/AuthDB.php';
require_once '../rabbit/RabbitMQConnection.php';
require_once '../logging/LogWriter.php';
require_once '../databases/MongoConnector.php';
use PhpAmqpLib\Message\AMQPMessage;
use logging\LogWriter;
use rabbit\RabbitMQConnection;
const CORR_ID = "correlation_id";

$rmq_connection = new RabbitMQConnection('auth_user', 'RegisterExchange', 'authentication');
$rmq_channel = $rmq_connection->getChannel();

//register
$register_callback = function ($request) {
	$mysql_connection = (new AuthDB())->getConnection();
	$logger = new LogWriter('/var/log/dnd/register.log');
	$logger->info("Registering username...");
	$requestData = unserialize($request->body);
	$username = $requestData[0];
	$pass = $requestData[1];
	$error = "E";
	$success = "S";
	$passhash = password_hash($pass, PASSWORD_DEFAULT);

	$msg = new AMQPMessage (
		$error,
		array(CORR_ID => $request->get(CORR_ID))
	);

	try {
		// calling stored procedure command
		$sql = "CALL createUser(?,?)";

		// prepare for execution of the stored procedure
		$stmt = $mysql_connection->prepare($sql);

		// pass value to the command
		$stmt->bindParam(1, $username, PDO::PARAM_STR);
		$stmt->bindParam(2, $passhash, PDO::PARAM_STR);

		// execute the stored procedure
		$stmt->execute();

		$msg = new AMQPMessage (
			$success,
			array(CORR_ID => $request->get(CORR_ID))
		);

		$logger->info("Successful");

	} catch (PDOException $e) {
		$logger->error("Error occurred:" . $e->getMessage());
	}

	MongoConnector::initUserStorage($username);

	$request->delivery_info['channel']->basic_publish($msg, '', $request->get('reply_to'));
	$logger->info("Delivered Message");

};

$rmq_channel->basic_qos(null, 1, null);
$rmq_channel->basic_consume($rmq_connection->getQueueName(), '', false, true, false, false, $register_callback);

while (true) {
	$rmq_channel->wait();
}

$rmq_connection->close();
?>
