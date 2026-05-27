<?php

declare(strict_types=1);

namespace practice\duel\type;

use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\ItemTypeIds;
use practice\duel\Duel;

class Debuff extends Duel{

	public function handleItemUse(PlayerItemUseEvent $event) : void{
		$item = $event->getItem();

		if(!$this->isRunning() && $item->getTypeId() === ItemTypeIds::ENDER_PEARL){
			$event->cancel();
		}
	}
}