<?php

declare(strict_types=1);

namespace practice\item\arena;

use pocketmine\item\ItemIds;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use practice\form\arena\ArenaForm;
use practice\item\PracticeItem;
use practice\session\SessionFactory;

class JoinArenaItem extends PracticeItem{

	public function __construct(){
		parent::__construct('&eArena FFA', ItemTypeIds::GOLDEN_AXE);
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$session = SessionFactory::get($player);

		if($session === null || !$session->inLobby()){
			return ItemUseResult::FAIL();
		}
		$form = new ArenaForm;
		$player->sendForm($form);
		return ItemUseResult::SUCCESS();
	}
}