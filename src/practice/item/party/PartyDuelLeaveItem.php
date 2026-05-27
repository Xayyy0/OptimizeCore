<?php

declare(strict_types=1);

namespace practice\item\party;

use pocketmine\item\ItemIds;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use practice\item\PracticeItem;
use practice\party\duel\queue\QueueFactory;
use practice\session\SessionFactory;

final class PartyDuelLeaveItem extends PracticeItem{

	public function __construct(){
		parent::__construct('&cLeave queue', ItemTypeIds::REDSTONE_DUST);
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$session = SessionFactory::get($player);

		if($session === null){
			return ItemUseResult::FAIL();
		}
		$party = $session->getParty();

		if($party === null){
			return ItemUseResult::FAIL();
		}

		if($party->getQueue() === null){
			return ItemUseResult::FAIL();
		}
		QueueFactory::remove($party);
		return ItemUseResult::SUCCESS();
	}
}