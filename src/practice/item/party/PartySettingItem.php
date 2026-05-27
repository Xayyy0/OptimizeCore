<?php

declare(strict_types=1);

namespace practice\item\party;

use pocketmine\item\ItemIds;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use practice\form\party\manage\PartySettingForm;
use practice\item\PracticeItem;
use practice\session\SessionFactory;

final class PartySettingItem extends PracticeItem{

	public function __construct(){
		parent::__construct('&gParty Settings', ItemTypeIds::NETHER_STAR);
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
		$form = new PartySettingForm($party);
		$player->sendForm($form);
		return ItemUseResult::SUCCESS();
	}
}