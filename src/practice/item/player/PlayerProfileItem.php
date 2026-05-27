<?php

declare(strict_types=1);

namespace practice\item\player;

use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use practice\form\player\PlayerProfileForm;
use practice\item\PracticeItem;
use practice\session\SessionFactory;

class PlayerProfileItem extends PracticeItem{

	public function __construct(){
		parent::__construct('&gProfile', ItemTypeIds::RECORD_MELLOHI);
	}

	public function OnClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$session = SessionFactory::get($player);

		if($session === null || !$session->inLobby()){
			return ItemUseResult::FAIL();
		}
		$form = new PlayerProfileForm;
		$player->sendForm($form);
		return ItemUseResult::SUCCESS();
	}
}