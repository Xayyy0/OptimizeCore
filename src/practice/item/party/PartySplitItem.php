<?php

declare(strict_types=1);

namespace practice\item\party;

use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use practice\form\party\manage\PartySplitForm;
use practice\item\PracticeItem;
use practice\session\SessionFactory;

final class PartySplitItem extends PracticeItem{

	public function __construct(){
		parent::__construct('&dParty Event', ItemTypeIds::IRON_SWORD);
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

		$form = new PartySplitForm;
		$player->sendForm($form);
		return ItemUseResult::SUCCESS();
	}
}
