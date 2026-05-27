<?php

declare(strict_types=1);

namespace practice\form\party\manage;

use cosmicpe\form\entries\simple\Button;
use cosmicpe\form\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

final class PartySplitForm extends SimpleForm{

	public function __construct(){
		parent::__construct(TextFormat::colorize('&dParty Management'));

		$this->addButton(new Button(TextFormat::colorize('&bParty Split')), function(Player $player, int $index) : void{
			$player->sendForm(new PartyKitSelectionForm('split'));
		});

		$this->addButton(new Button(TextFormat::colorize('&6Party FFA')), function(Player $player, int $index) : void{
			$player->sendForm(new PartyKitSelectionForm('ffa'));
		});
	}
}
