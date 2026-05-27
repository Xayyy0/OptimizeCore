<?php

declare(strict_types=1);

namespace practice\form\party\manage;

use cosmicpe\form\entries\simple\Button;
use cosmicpe\form\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use practice\party\duel\DuelFactory;
use practice\session\SessionFactory;

final class PartySplitTypeForm extends SimpleForm{

	public function __construct(private int $duelType){
		parent::__construct(TextFormat::colorize('&dChoose Team Mode'));

		$this->addButton(new Button(TextFormat::colorize('&bRandom team')), function(Player $player, int $index) : void{
			$session = SessionFactory::get($player);
			$party = $session?->getParty();
			if($party !== null) DuelFactory::createSplit($party, $this->duelType);
		});

		$this->addButton(new Button(TextFormat::colorize('&eCustom team')), function(Player $player, int $index) : void{
			$player->sendForm(new PartyCustomTeamForm($player, $this->duelType));
		});
	}
}
