<?php
require_once '../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQConnection {

	private $connection;
	private $channel;
	private $queue_name;

	public function __construct($user, $exchange, $vhost) {
		$connection = new AMQPStreamConnection (
			'172.17.0.2', // host
			5672, // port
			$user, // user
			'pass', // pass
			$vhost  // virtual host
		);
		$this->channel  = $connection->channel();

		$this->channel->exchange_declare($exchange, 'direct', false, false, false);
		list($this->queue_name, ,) = $this->channel->queue_declare('', false, false, true, false);
		$this->channel->queue_bind($this->queue_name, $exchange, $exchange . '_req');
	}

	public function getQueueName() {
		return $this->queue_name;
	}

	public function getChannel() {
		return $this->channel;
	}

	public function close() {
		$this->channel->close();
		$this->connection->close();
	}
}

$rmq_connection = new RabbitMQConnection('auth_user', 'LoginExchange', 'authentication');
$rmq_channel = $rmq_connection->getChannel();

//register
$register_callback = function ($request) {
	echo "sending msg";
	$msg = new AMQPMessage (
		"blah",
		array('correlation_id' => $request->get('correlation_id'))
	);

	$request->delivery_info['channel']->basic_publish( $msg, '', $request->get('reply_to'));
};

$rmq_channel->basic_qos(null, 1, null);
echo $rmq_connection->getQueueName() . PHP_EOL;
$rmq_channel->basic_consume($rmq_connection->getQueueName(), '', false, true, false, false, $register_callback);

while (true) {
	$rmq_channel->wait();
}

$rmq_connection->close();