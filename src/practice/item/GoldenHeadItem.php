<?php

declare(strict_types=1);

namespace practice\item;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\GoldenApple;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\utils\TextFormat;

class GoldenHeadItem extends GoldenApple{

	public function __construct(){
		parent::__construct(new ItemIdentifier(ItemTypeIds::GOLD_NUGGET), 'Gold Nugget');
	}

	public function getAdditionalEffects() : array{
		//TODO: here u can customize golden head name
		if(TextFormat::clean($this->getCustomName()) === 'Golden Head'){
			return [new EffectInstance(VanillaEffects::REGENERATION(), 20 * 9, 1), new EffectInstance(VanillaEffects::ABSORPTION(), 2400)];
		}
		return parent::getAdditionalEffects();
	}
}