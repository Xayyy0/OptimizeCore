<?php declare(strict_types=1);
/*
 * Created by PhpStorm.
 *
 * User: zOmArRD
 * Date: 21/11/2022
 *
 * Copyright © 2022  <omar@ghostlymc.live> - All Rights Reserved.
 */

namespace practice\database\mysql\queries;

use Closure;
use mysqli;
use mysqli_result;
use pocketmine\Server;
use practice\database\mysql\AsyncQuery;

class QueryAsync extends AsyncQuery{

	private ?string $rows = null;

	public function __construct(
		private string $sqlQuery,
		?Closure $onComplete = null
	){
		$this->storeLocal("onComplete", $onComplete);
	}

	public function query(mysqli $mysqli) : void{
		$result = $mysqli->query($this->sqlQuery);

		if($result instanceof mysqli_result):
			$rows = [];
			while($row = $result->fetch_assoc()){
				$rows[] = $row;
			}

			$this->rows = serialize($rows);
		endif;
	}

	public function onCompletion() : void{
		$onComplete = $this->fetchLocal('onComplete');
		if($this->failed){
			Server::getInstance()->getLogger()->error('Failed to execute query: ' . $this->sqlQuery);
		}

		if($onComplete === null){
			return;
		}

		if(isset($this->rows)){
			$onComplete(unserialize($this->rows, ['allowed_classes' => false]));
			return;
		}

		$onComplete([]);
	}
}