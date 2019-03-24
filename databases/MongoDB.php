<?php

class MongoDB {
	private $connection;

	private $host = 'host';
	private $user = 'db user-name';
	private $pass = 'db password';
	private $name = 'db name';

	public function __construct(){
		$this->connection = new MongoClient("mongodb://$user:$pass@$host");
		$db = $this->connection->mydb;
	}
	public function getConnection(){
		return $this->connection;
	}
}
?>