<?php

class ForumsDB {
	private $connection;

	private $host = '172.17.0.5';
	private $user = 'forum-client';
	private $pass = 'pass';
	private $name = 'messageBoard';

	public function __construct(){
		$this->connection = new PDO(
							"mysql:host={$this->host};dbname={$this->name}",
							$this->user, $this->pass,
							array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
		$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	public function getConnection()	{
		return $this->connection;
	}
}
?>