<?php
require_once '../vendor/autoload.php';
require_once '../databases/AuthDB.php';
require_once '../rabbit/RabbitMQConnection.php';
require_once '../logging/LogWriter.php';
use PhpAmqpLib\Message\AMQPMessage;
use rabbit\RabbitMQConnection;
use logging\LogWriter;

$rmq_connection = new RabbitMQConnection('auth_user','LoginExchange', 'authentication');
$rmq_channel = $rmq_connection->getChannel();

$login_callback = function($request) {
	$db_connection = (new AuthDB())->getConnection();
	$logger = new LogWriter('/var/log/dnd/login.log');
	$logger->info("Logging in username...");
	$requestData = unserialize($request->body);
	$logger->info("This is body: " . $request->body);
	$logger->info("This is 0: " . $requestData[0]);
	$logger->info("This is 1: " . $requestData[1]);
	$username = $requestData[0];
	$pass = $requestData[1];
	$error = 'E';
	$success = 'S';

	$msg = new AMQPMessage (
		$error,
		array('correlation_id' => $request->get('correlation_id'))
	);


	try {
		// calling stored procedure command
		$sql = "CALL getPassword(?)";

		// prepare for execution of the stored procedure
		$stmt = $db_connection->prepare($sql);

		// pass value to the command
		$stmt->bindParam(1, $username, PDO::PARAM_STR);

		// execute the stored procedure
		$stmt->execute();

		$db_response = $stmt->fetch(PDO::FETCH_ASSOC);

		if (isset($db_response))
		{
			$passver = password_verify($pass, $db_response['password']);

			if($passver){
				$msg = new AMQPMessage (
					$success,
					array('correlation_id' => $request->get('correlation_id'))
				);
				$logger->info("Success");
			}
		}

	} catch (PDOException $e) {
		$logger->error("Error occurred:" . $e->getMessage());
	}

	$request->delivery_info['channel']->basic_publish($msg, '', $request->get('reply_to'));
	$logger->info("Sent back Message");

};

$rmq_channel->basic_qos(null, 1, null);

$rmq_channel->basic_consume($rmq_connection->getQueueName(), '', false, true, false, false, $login_callback);

while (true) {
	$rmq_channel->wait();
}

$rmq_connection->close();

?>
