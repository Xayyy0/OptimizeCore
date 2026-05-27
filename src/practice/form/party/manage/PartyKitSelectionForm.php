<?php

declare(strict_types=1);

namespace practice\form\party\manage;

use cosmicpe\form\entries\simple\Button;
use cosmicpe\form\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use practice\party\duel\Duel;
use practice\party\duel\DuelFactory;
use practice\session\SessionFactory;

final class PartyKitSelectionForm extends SimpleForm{

	private array $types = [
		'No Debuff' => Duel::TYPE_NODEBUFF,
		'Pot PVP' => Duel::TYPE_POTPVP,
		'Gapple' => Duel::TYPE_GAPPLE,
		'Fist' => Duel::TYPE_FIST,
		'Combo' => Duel::TYPE_COMBO,
		'Build UHC' => Duel::TYPE_BUILDUHC,
		'Cave UHC' => Duel::TYPE_CAVEUHC,
		'Final UHC' => Duel::TYPE_FINALUHC,
        'Boxing' => Duel::TYPE_BOXING,
        'Soup' => Duel::TYPE_SOUP,
        'Battle Rush' => Duel::TYPE_BATTLERUSH
	];

	/**
	 * @param string   $mode (split, ffa)
	 */
	public function __construct(private string $mode){
		parent::__construct(TextFormat::colorize('&dChoose Kit'));

		foreach($this->types as $type => $typeId){
			$this->addButton(new Button(TextFormat::colorize('&7' . $type)),
				function(Player $player, int $button_index) use ($typeId) : void{
					$session = SessionFactory::get($player);
					if($session === null) return;
					$party = $session->getParty();
					if($party === null) return;

					if($this->mode === 'ffa'){
						DuelFactory::createFFA($party, $typeId);
					}elseif($this->mode === 'split'){
						$player->sendForm(new PartySplitTypeForm($typeId));
					}
				});
		}
	}
}
