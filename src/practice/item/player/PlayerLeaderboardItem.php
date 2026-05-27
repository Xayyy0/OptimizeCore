<?php

declare(strict_types=1);

namespace practice\item\player;

use pocketmine\item\ItemIds;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use practice\form\player\PlayerLeaderboardForm;
use practice\item\PracticeItem;

final class PlayerLeaderboardItem extends PracticeItem{

	public function __construct(){
		parent::__construct('&dLeaderboards', ItemTypeIds::DIAMOND);
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$form = new PlayerLeaderboardForm;
		$player->sendForm($form);
		return ItemUseResult::SUCCESS();
	}
}