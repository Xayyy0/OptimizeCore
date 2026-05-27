<?php

declare(strict_types=1);

namespace practice\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\SplashPotion;
use pocketmine\player\Player;
use practice\entity\SplashPotion as EntitySplashPotion;

class SplashPotionItem extends SplashPotion{

	public function __construct(){
		parent::__construct(new ItemIdentifier(ItemTypeIds::SPLASH_POTION));
	}

	protected function createEntity(Location $location, Player $thrower) : Throwable{
		return new EntitySplashPotion($location, $thrower, $this->getType());
	}
}