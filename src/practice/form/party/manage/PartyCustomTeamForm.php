<?php

declare(strict_types=1);

namespace practice\form\party\manage;

use cosmicpe\form\CustomForm;
use cosmicpe\form\entries\custom\DropdownEntry;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use practice\party\duel\DuelFactory;
use practice\session\SessionFactory;

final class PartyCustomTeamForm extends CustomForm{

	/** @var Player[] */
	private array $membersList = [];

	public function __construct(Player $player, private int $duelType){
		parent::__construct(TextFormat::colorize('&eCustom Team Assignment'));

		$session = SessionFactory::get($player);
		$party = $session?->getParty();
		if($party === null) return;

		$this->membersList = array_values($party->getMembers());
		
		foreach($this->membersList as $index => $member){
			$this->addEntry(new DropdownEntry($member->getName(), ["&bBlue Team", "&cRed Team"], 0), function(Player $player, DropdownEntry $entry, int $value) use ($index) : void{
				// This is just to satisfy the form structure, the actual data is handled in onSubmit
			});
		}
	}

	public function onSubmit(Player $player, array $data) : void{
		$team1 = []; // Blue
		$team2 = []; // Red

		$i = 0;
		foreach($this->membersList as $member){
			$teamIndex = $data[$i] ?? 0;
			if($teamIndex === 0){
				$team1[] = $member;
			}else{
				$team2[] = $member;
			}
			$i++;
		}

		if(count($team1) === 0 || count($team2) === 0){
			$player->sendMessage(TextFormat::colorize("&cBoth teams must have at least one player!"));
			return;
		}

		$session = SessionFactory::get($player);
		$party = $session?->getParty();
		if($party !== null){
			DuelFactory::createManualSplit($party, $this->duelType, $team1, $team2);
		}
	}
}
