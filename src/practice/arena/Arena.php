<?php

declare(strict_types=1);

namespace practice\arena;

use JetBrains\PhpStorm\ArrayShape;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\PotionType;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;
use practice\item\SplashPotionItem;
use practice\session\Session;
use practice\session\SessionFactory;
use practice\session\setting\gameplay\AutoRespawn;
use practice\session\setting\gameplay\FocusFFA;
use practice\session\setting\Setting;

final class Arena{

	public function __construct(
		private string $name,
		private string $kit,
		private World $world,
		private array $spawns = [],
		private array $players = [],
		private array $combats = [],
		private array $blocks = []
	){
		$world->setTime(World::TIME_DAY);
		$world->stopTime();
	}

	public static function deserializeData(array $data) : ?array{
		$storage = [
			'kit' => $data['kit'],
			'spawns' => []
		];

		if(!Server::getInstance()->getWorldManager()->isWorldGenerated($data['world'])){
			return null;
		}

		if(!Server::getInstance()->getWorldManager()->isWorldLoaded($data['world'])){
			Server::getInstance()->getWorldManager()->loadWorld($data['world']);
		}
		$storage['world'] = Server::getInstance()->getWorldManager()->getWorldByName($data['world']);

		foreach($data['spawns'] as $spawn){
			$storage['spawns'][] = new Position((float) $spawn['x'], (float) $spawn['y'], (float) $spawn['z'], $storage['world']);
		}
		return $storage;
	}

	public function getKit() : string{
		return $this->kit;
	}

	public function getPlayers() : array{
		return $this->players;
	}

	public function inCombat(Player $player) : bool{
		if(isset($this->combats[$player->getName()])){
			$combat = $this->combats[$player->getName()];

			return $combat['time'] >= time();
		}
		return false;
	}

	public function getName() : string{
		return $this->name;
	}

	public function handleBreak(BlockBreakEvent $event) : void{
		$block = $event->getBlock();

		if(!isset($this->blocks[(string) $block->getPosition()])){
			$event->cancel();
			return;
		}
		unset($this->blocks[(string) $block->getPosition()]);
	}

	public function handlePlace(BlockPlaceEvent $event) : void{
		foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){
			$this->blocks[(string) $block->getPosition()] = $block;
		}
	}

	public function handleDamage(EntityDamageEvent $event) : void{
		$cause = $event->getCause();
		$player = $event->getEntity();

		if(!$player instanceof Player){
			return;
		}
		$session = SessionFactory::get($player);

		if($session === null){
			return;
		}

		if($cause === EntityDamageEvent::CAUSE_VOID){
			$event->cancel();

			if(isset($this->combats[$player->getName()])){
				$combat = $this->combats[$player->getName()];
				/** @var Player $killer */
				$killer = $combat['player'];

				if($combat['time'] >= time() && $killer->isOnline()){
					$session->addDeath();
					$session->resetKillstreak();

					/** @var Session $damager */
					$damager = SessionFactory::get($killer);
					$damager->addKill();
					$damager->addKillstreak();
					$damager->getPlayer()?->setHealth($damager->getPlayer()->getMaxHealth());

					unset($this->combats[$damager->getName()]);
					$this->showPlayers($killer);
					Server::getInstance()->broadcastMessage(TextFormat::colorize('&a' . $damager->getName() . ' &2[' . $damager->getKills() . '] &7killed &c' . $player->getName() . ' &4[' . $session->getKills() . ']'));
				}
				unset($this->combats[$player->getName()]);
			}
			$autoRespawn = $session->getSetting(Setting::AUTO_RESPAWN);

			if($autoRespawn instanceof AutoRespawn && $autoRespawn->isEnabled()){
				$this->join($player);
				return;
			}
			$this->quit($player);
			return;
		}

		if($event instanceof EntityDamageByEntityEvent){
			$damager = $event->getDamager();

			if($damager instanceof Player){
				if(!$this->isPlayer($damager)){
					$event->cancel();
					return;
				}

				if(isset($this->combats[$player->getName()])){
					$combat = $this->combats[$player->getName()];

					if($combat['time'] > time() && $combat['player']->getName() !== $damager->getName()){
						$event->cancel();
						return;
					}
				}
				
				if(isset($this->combats[$damager->getName()])){
					$combat = $this->combats[$damager->getName()];

					if($combat['time'] > time() && $combat['player']->getName() !== $player->getName()){
						$event->cancel();
						return;
					}
				}
				$inCombatPlayer = isset($this->combats[$player->getName()]) && $this->combats[$player->getName()]['time'] > time();
				$inCombatDamager = isset($this->combats[$damager->getName()]) && $this->combats[$damager->getName()]['time'] > time();

				$this->combats[$player->getName()] = ['time' => time() + 15, 'player' => $damager];
				$this->combats[$damager->getName()] = ['time' => time() + 15, 'player' => $player];

				if(!$inCombatPlayer){
					$this->hideNonOpponents($player, $damager);
				}

				if(!$inCombatDamager){
					$this->hideNonOpponents($damager, $player);
				}

				$plugin = Server::getInstance()->getPluginManager()->getPlugin("Practice");
				if ($plugin !== null) {
					$plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player): void {
						if ($player->isOnline() && !$this->inCombat($player)) {
							$this->showPlayers($player);
						}
					}), 16 * 20);

					$plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($damager): void {
						if ($damager->isOnline() && !$this->inCombat($damager)) {
							$this->showPlayers($damager);
						}
					}), 16 * 20);
				}
			}
		}
		$finalHealth = $player->getHealth() - $event->getFinalDamage();

		if($finalHealth <= 0.00){
			$event->cancel();
			$session->addDeath();
			$session->resetKillstreak();

			if(isset($this->combats[$player->getName()])){
				$combat = $this->combats[$player->getName()];
				/** @var Player $killer */
				$killer = $combat['player'];

				if($combat['time'] >= time() && $killer->isOnline()){
					/** @var Session $damager */
					$damager = SessionFactory::get($killer);
					$damager->addKill();
					$damager->addKillstreak();

					$damager->getPlayer()?->setHealth($damager->getPlayer()->getMaxHealth());

					unset($this->combats[$damager->getName()]);
					$this->showPlayers($killer);

					$inventory = $damager->getInventory(strtolower($this->kit));
					$inventory?->giveKit();

					if($this->kit !== 'no debuff'){
						Server::getInstance()->broadcastMessage(TextFormat::colorize('&a' . $damager->getName() . ' &2[' . $damager->getKills() . '] &7killed &c' . $player->getName() . ' &4[' . $session->getKills() . ']'));
					}else{
						/** @var SplashPotionItem $item */
						$killerPots = array_filter($killer->getInventory()->getContents(), fn(Item $item) => $item->getTypeId() === ItemTypeIds::SPLASH_POTION && $item->getType() instanceof (PotionType::STRONG_HEALING()));
						$playerPots = array_filter($player->getInventory()->getContents(), fn(Item $item) => $item->getTypeId() === ItemTypeIds::SPLASH_POTION && $item->getType() instanceof (PotionType::STRONG_HEALING()));
						Server::getInstance()->broadcastMessage(TextFormat::colorize('&a' . $damager->getName() . ' &2[' . count($killerPots) . '] &7killed &c' . $player->getName() . ' &4[' . count($playerPots) . ']'));
					}
				}
				unset($this->combats[$player->getName()]);
			}
			$autoRespawn = $session->getSetting(Setting::AUTO_RESPAWN);

			if($autoRespawn instanceof AutoRespawn && $autoRespawn->isEnabled()){
				$this->join($player);
				return;
			}
			$this->quit($player);
		}
	}

	public function join(Player $player) : void{
		$session = SessionFactory::get($player);
		$this->addPlayer($player);

		$player->getArmorInventory()->clearAll();
		$player->getInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->getOffHandInventory()->clearAll();

		$player->setHealth($player->getMaxHealth());
		$player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());

		$player->getXpManager()->setXpAndProgress(0, 0.0);

		$player->teleport($this->spawns[array_rand($this->spawns)]);
		$this->showPlayers($player);

		$inventory = $session?->getInventory(strtolower($this->kit));
		$inventory?->giveKit();
	}

	public function addPlayer(Player $player) : void{
		$this->players[spl_object_hash($player)] = $player;
	}

	public function quit(Player $player, bool $withCombat = true) : void{
		$session = SessionFactory::get($player);

		if($session === null){
			return;
		}
		$this->removePlayer($player);

		$player->setGamemode(GameMode::SURVIVAL());

		$player->getArmorInventory()->clearAll();
		$player->getInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->getOffHandInventory()->clearAll();

		$player->setHealth($player->getMaxHealth());
		$player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());

		$player->getXpManager()->setXpAndProgress(0, 0.0);
		$player->getEffects()->clear();

		$player->teleport($player->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
		$this->showPlayers($player);

		$session->giveLobbyItems();
		$session->setArena(null);

		if($withCombat && isset($this->combats[$player->getName()])){
			$combat = $this->combats[$player->getName()];
			/** @var Player $killer */
			$killer = $combat['player'];

			if($combat['time'] >= time() && $killer->isOnline()){
				/** @var Session $damager */
				$damager = SessionFactory::get($killer);
				$damager->addKill();
				$damager->addKillstreak();
				$damager->getPlayer()?->setHealth($damager->getPlayer()->getHealth());
				unset($this->combats[$damager->getName()]);
				$this->showPlayers($killer);

				$inventory = $session->getInventory(strtolower($this->kit));
				$inventory->giveKit();

				if($this->kit !== 'no debuff'){
					Server::getInstance()->broadcastMessage(TextFormat::colorize('&a' . $damager->getName() . ' &2[' . $damager->getKills() . '] &7killed &c' . $player->getName() . ' &4[' . $session->getKills() . ']'));
				}else{
					/** @var SplashPotionItem $item */
					$killerPots = array_filter($killer->getInventory()->getContents(), fn(Item $item) => $item->getTypeId() === ItemTypeIds::SPLASH_POTION && $item->getType() instanceof (PotionType::STRONG_HEALING()));
					$playerPots = array_filter($player->getInventory()->getContents(), fn(Item $item) => $item->getTypeId() === ItemTypeIds::SPLASH_POTION && $item->getType() instanceof (PotionType::STRONG_HEALING()));
					Server::getInstance()->broadcastMessage(TextFormat::colorize('&a' . $damager->getName() . ' &2[' . count($killerPots) . '] &7killed &c' . $player->getName() . ' &4[' . count($playerPots) . ']'));
				}
			}
			unset($this->combats[$player->getName()]);
		}
	}

	public function removePlayer(Player $player) : void{
		if(!$this->isPlayer($player)){
			return;
		}
		unset($this->players[spl_object_hash($player)]);
	}

	public function isPlayer(Player $player) : bool{
		return isset($this->players[spl_object_hash($player)]);
	}

	private function hideNonOpponents(Player $player, Player $opponent): void {
		$session = SessionFactory::get($player);

		if ($session === null) {
			return;
		}
		$setting = $session->getSetting(Setting::FOCUS_FFA);

		if ($setting instanceof FocusFFA && $setting->isEnabled()) {
			foreach ($player->getWorld()->getPlayers() as $p) {
				if ($p->getName() !== $player->getName() && $p->getName() !== $opponent->getName()) {
					$player->hidePlayer($p);
				} else {
					$player->showPlayer($p);
				}
			}
		}
	}

	private function showPlayers(Player $player): void {
		foreach ($player->getWorld()->getPlayers() as $p) {
			$player->showPlayer($p);
		}
	}

	public function scoreboard(Player $player) : array{
		$time = 0;
		$session = SessionFactory::get($player);

		if($session === null){
			return [];
		}

		$lines = [
			' &fKills: &5' . $session->getKills(),
			' &fDeaths: &5' . $session->getDeaths(),
			' &fKill streak: &5' . $session->getKillstreak()
		];

		if(isset($this->combats[$player->getName()])){
			$combat = $this->combats[$player->getName()];

			if($combat['time'] > time()){
				$time = $combat['time'] - time();
			}
		}
		$lines[] = '&r&r&r&r';
		$lines[] = ' &fCombat: &7(' . $time . 's)';
		return $lines;
	}

	#[ArrayShape(['kit' => "string", 'world' => "string", 'spawns' => "array"])] public function serializeData() : array{
		$data = [
			'kit' => $this->kit,
			'world' => $this->world->getFolderName(),
			'spawns' => []
		];

		foreach($this->spawns as $spawn){
			$data['spawns'][] = [
				'x' => $spawn->getX(),
				'y' => $spawn->getY(),
				'z' => $spawn->getZ()
			];
		}

		return $data;
	}
}
