<?php

declare(strict_types=1);

namespace practice\party\duel;

use pocketmine\player\Player;
use practice\party\Party;

class PartyTeam extends Party{

	/**
	 * @param string   $name
	 * @param Player   $owner
	 * @param Player[] $members
	 */
	public function __construct(string $name, Player $owner, array $members){
		$this->name = $name;
		$this->owner = $owner;
		$this->members = [];
		foreach($members as $member){
			$this->members[spl_object_hash($member)] = $member;
		}
		$this->open = false;
		$this->maxPlayers = count($members);
		$this->queue = null;
		$this->duel = null;
	}

	public function addMember(Player $player, bool $announce = true) : void{
		$this->members[spl_object_hash($player)] = $player;
	}

	public function removeMember(Player $player, bool $announce = true) : void{
		unset($this->members[spl_object_hash($player)]);
	}

	public function giveItems(Player $player) : void{
		// NO-OP during duel
	}
}
