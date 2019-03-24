<?php

class MongoDB {
	private $connection;

	private $host = 'host';
	private $user = 'db user-name';
	private $pass = 'db password';
	private $db;

	public function __construct(){
		$this->connection = new MongoClient('mongodb://' . $this->user . ':' . $this->pass . '@' . $this->host);
		$this->db = $this->connection->mydb;
	}
	public function getDatabase(){
		return $this->db;
	}
}
?>