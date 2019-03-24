<?php
require_once '../vendor/autoload.php';
require_once '../databases/AuthDB.php';
require_once '../rabbit/RabbitMQConnection.php';
require_once '../logging/LogWriter.php';
use PhpAmqpLib\Message\AMQPMessage;
use databases\AuthDB;
use logging\LogWriter;

$logger = new LogWriter('/var/log/dnd/register.log');

$db_connection = (new AuthDB())->getConnection();

$rmq_connection = new RabbitMQConnection('RegisterExchange', 'userAuthentication');
$rmq_channel = $rmq_connection->getChannel();

//register
$register_callback = function ($request) {
	$logger->info("Registering User...");
	$result = unserialize($request->body);
	$user = $result[0];
	$pass = $result[1];
	$error = "E";
	$success = "S";
	$passhash = password_hash($pass, PASSWORD_DEFAULT);

	$msg = new AMQPMessage (
		$error,
		array('correlation_id' => $request->get('correlation_id'))
	);

	try {
		// calling stored procedure command
		$sql = "CALL createUser(?,?)";

		// prepare for execution of the stored procedure
		$stmt = $db_connection->prepare($sql);

		// pass value to the command
		$stmt->bindParam(1, $user, PDO::PARAM_STR);
		$stmt->bindParam(2, $passhash, PDO::PARAM_STR);

		// execute the stored procedure
		$isSuccessful = $stmt->execute();

		$msg = new AMQPMessage (
			$success,
			array('correlation_id' => $request->get('correlation_id'))
		);

		$logger->info("Successful");

	} catch (PDOException $e) {
		$logger->error("Error occurred:" . $e->getMessage());
	}

	$request->delivery_info['channel']->basic_publish( $msg, '', $request->get('reply_to'));
	$logger->info("Delivered Message");

};

$rmq_channel->basic_qos(null, 1, null);
$rmq_channel->basic_consume($queue_name, '', false, true, false, false, $register_callback);

while (true) {
	$rmq_channel->wait();
}

$rmq_connection->close();
?>
