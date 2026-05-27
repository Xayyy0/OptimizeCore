<?php

declare(strict_types=1);

namespace practice\duel\type;

use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\ItemTypeIds;
use practice\duel\Duel;

class PotPVP extends Duel{

    protected function init() : void{
		$first = $this->firstSession->getPlayer();
		$second = $this->secondSession->getPlayer();

		if($first !== null){
			$first->getHungerManager()->setEnabled(true);
			$first->getHungerManager()->setFood(20);
			$first->getHungerManager()->setSaturation(6.0);
		}

		if($second !== null){
			$second->getHungerManager()->setEnabled(true);
			$second->getHungerManager()->setFood(20);
			$second->getHungerManager()->setSaturation(6.0);
		}
	}

    public function handleItemUse(PlayerItemUseEvent $event) : void{
        $item = $event->getItem();

        if(!$this->isRunning() && $item->getTypeId() === ItemTypeIds::ENDER_PEARL){
            $event->cancel();
        }
    }
}