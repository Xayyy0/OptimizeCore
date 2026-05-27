<?php

declare(strict_types=1);

namespace practice\party\duel;

use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use practice\party\duel\type\BuildUHC;
use practice\party\duel\type\PotPVP;
use practice\party\duel\type\CaveUHC;
use practice\party\duel\type\Combo;
use practice\party\duel\type\FinalUHC;
use practice\party\duel\type\Fist;
use practice\party\duel\type\Gapple;
use practice\party\duel\type\Nodebuff;
use practice\party\duel\type\HG;
use practice\party\duel\type\Boxing;
use practice\party\duel\type\Soup;
use practice\party\duel\type\BattleRush;
use practice\party\Party;
use practice\Practice;
use practice\world\WorldFactory;

final class DuelFactory{

	/** @var Duel[] */
	static private array $duels = [];

	static public function create(Party $firstParty, Party $secondParty, int $duelType) : void{
		$id = 0;

		while(self::get($id) !== null || is_dir(Practice::getInstance()->getServer()->getDataPath() . 'worlds' . DIRECTORY_SEPARATOR . 'party-duel-' . $id)){
			$id++;
		}
		$className = self::getClass($duelType);
		$duelName = self::getName($duelType);

		$newName = explode(' ', $duelName);
		$worldData = WorldFactory::getRandom(strtolower(implode('', $newName)));

		if($worldData === null){
			return;
		}
		$worldData->copyWorld(
			'party-duel-' . $id,
			Practice::getInstance()->getServer()->getDataPath() . 'worlds',
			static function(World $world) use ($className, $id, $duelType, $worldData, $firstParty, $secondParty) : void{
				$duel = new $className($id, $duelType, $worldData->getName(), $firstParty, $secondParty, $world);

				$firstParty->setDuel($duel);
				$secondParty->setDuel($duel);

				self::$duels[$id] = $duel;
			}
		);
	}

	static public function get(int $id) : ?Duel{
		return self::$duels[$id] ?? null;
	}

	static public function createManualSplit(Party $party, int $duelType, array $team1Members, array $team2Members) : void{
		$firstParty = new PartyTeam($party->getName() . " Team 1", $team1Members[0], $team1Members);
		$secondParty = new PartyTeam($party->getName() . " Team 2", $team2Members[0], $team2Members);

		$id = 0;

		while(self::get($id) !== null || is_dir(Practice::getInstance()->getServer()->getDataPath() . 'worlds' . DIRECTORY_SEPARATOR . 'party-duel-' . $id)){
			$id++;
		}
		$className = self::getClass($duelType);
		$duelName = self::getName($duelType);

		$newName = explode(' ', $duelName);
		$worldData = WorldFactory::getRandom(strtolower(implode('', $newName)));

		if($worldData === null){
			return;
		}
		$worldData->copyWorld(
			'party-duel-' . $id,
			Practice::getInstance()->getServer()->getDataPath() . 'worlds',
			static function(World $world) use ($className, $id, $duelType, $worldData, $firstParty, $secondParty, $party) : void{
				/** @var Duel $duel */
				$duel = new $className(id: $id, typeId: $duelType, worldName: $worldData->getName(), firstParty: $firstParty, secondParty: $secondParty, world: $world, mainParty: $party);

				$party->setDuel($duel);

				self::$duels[$id] = $duel;
			}
		);
	}

	static public function createFFA(Party $party, int $duelType) : void{
		$members = $party->getMembers();

		if(count($members) < 2){
			$party->getOwner()->sendMessage("§cNeed at least 2 players for a party FFA.");
			return;
		}

		$id = 0;
		while(self::get($id) !== null || is_dir(Practice::getInstance()->getServer()->getDataPath() . 'worlds' . DIRECTORY_SEPARATOR . 'party-duel-' . $id)){
			$id++;
		}
		$className = PartyFFADuel::class;
		$duelName = self::getName($duelType);

		$newName = explode(' ', $duelName);
		$worldData = WorldFactory::getRandom(strtolower(implode('', $newName)));

		if($worldData === null){
			return;
		}

		// PartyFFADuel needs mainParty, but the standard Duel constructor expects 2 parties.
		// We'll use the main party as both first and second party for compatibility, 
		// but PartyFFADuel logic will handle them as one group.
		$worldData->copyWorld(
			'party-duel-' . $id,
			Practice::getInstance()->getServer()->getDataPath() . 'worlds',
			static function(World $world) use ($className, $id, $duelType, $worldData, $party) : void{
				/** @var PartyFFADuel $duel */
				$duel = new $className(id: $id, typeId: $duelType, worldName: $worldData->getName(), firstParty: $party, secondParty: $party, world: $world, mainParty: $party);

				$party->setDuel($duel);

				self::$duels[$id] = $duel;
			}
		);
	}

	static public function createSplit(Party $party, int $duelType) : void{
		$members = $party->getMembers();

		if(count($members) < 2){
			$party->getOwner()->sendMessage("§cNeed at least 2 players for a party split.");
			return;
		}
		shuffle($members);

		$half = (int) ceil(count($members) / 2);
		$team1Members = array_slice($members, 0, $half);
		$team2Members = array_slice($members, $half);

		$firstParty = new PartyTeam($party->getName() . " Team 1", $team1Members[0], $team1Members);
		$secondParty = new PartyTeam($party->getName() . " Team 2", $team2Members[0], $team2Members);

		$id = 0;

		while(self::get($id) !== null || is_dir(Practice::getInstance()->getServer()->getDataPath() . 'worlds' . DIRECTORY_SEPARATOR . 'party-duel-' . $id)){
			$id++;
		}
		$className = self::getClass($duelType);
		$duelName = self::getName($duelType);

		$newName = explode(' ', $duelName);
		$worldData = WorldFactory::getRandom(strtolower(implode('', $newName)));

		if($worldData === null){
			return;
		}
		$worldData->copyWorld(
			'party-duel-' . $id,
			Practice::getInstance()->getServer()->getDataPath() . 'worlds',
			static function(World $world) use ($className, $id, $duelType, $worldData, $firstParty, $secondParty, $party) : void{
				/** @var Duel $duel */
				$duel = new $className(id: $id, typeId: $duelType, worldName: $worldData->getName(), firstParty: $firstParty, secondParty: $secondParty, world: $world, mainParty: $party);

				$party->setDuel($duel);

				self::$duels[$id] = $duel;
			}
		);
	}

	static public function getClass(int $type) : string{
		return match ($type) {
			Duel::TYPE_GAPPLE => Gapple::class,
			Duel::TYPE_POTPVP => PotPVP::class,
			Duel::TYPE_FIST => Fist::class,
			Duel::TYPE_COMBO => Combo::class,
			Duel::TYPE_BUILDUHC => BuildUHC::class,
			Duel::TYPE_CAVEUHC => CaveUHC::class,
			Duel::TYPE_FINALUHC => FinalUHC::class,
			Duel::TYPE_HG => HG::class,
            Duel::TYPE_SOUP => Soup::class,
            Duel::TYPE_BATTLERUSH => BattleRush::class,
            Duel::TYPE_BOXING => Boxing::class,
			default => Nodebuff::class
		};
	}

	static public function getName(int $type) : string{
		return match ($type) {
			Duel::TYPE_NODEBUFF => 'No Debuff',
			Duel::TYPE_POTPVP => 'Pot PVP',
			Duel::TYPE_GAPPLE => 'Gapple',
			Duel::TYPE_FIST => 'Fist',
			Duel::TYPE_COMBO => 'Combo',
			Duel::TYPE_BUILDUHC => 'Build UHC',
			Duel::TYPE_CAVEUHC => 'Cave UHC',
			Duel::TYPE_FINALUHC => 'Final UHC',
			Duel::TYPE_HG => 'HG',
            Duel::TYPE_BOXING => 'Boxing',
            Duel::TYPE_SOUP => 'Soup',
            Duel::TYPE_BATTLERUSH => 'Battle Rush',
			default => 'None'
		};
	}

	static public function remove(int $id) : void{
		if(self::get($id) === null){
			return;
		}
		unset(self::$duels[$id]);
	}

	static public function task() : void{
		Practice::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(static function() : void{
			foreach(self::getAll() as $duel){
				$duel->update();
			}
		}), 20);
	}

	static public function getAll() : array{
		return self::$duels;
	}

	static public function disable() : void{
		foreach(self::getAll() as $duel){
			$duel->delete();
		}
	}
}
