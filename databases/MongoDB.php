<?php

class MongoDB {
	private $connection;

	private $host = 'host';
	private $user = 'db user-name';
	private $pass = 'db password';

	public function __construct(){
		$this->connection = new MongoClient('mongodb://' . $this->user . ':' . $this->pass . '@' . $this->host);
	}
	public function getDatabase(){
		return $this->connection;
	}
}
?>