<?php

declare(strict_types=1);

namespace practice\party\duel;

use CortexPE\DiscordWebhookAPI\Embed;
use CortexPE\DiscordWebhookAPI\Message;
use CortexPE\DiscordWebhookAPI\Webhook;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;
use practice\utils\ServerUtils;
use pocketmine\utils\TextFormat;
use practice\party\Party;

class PartyFFADuel extends Duel{

	/** @var array<string, int> */
	protected array $placements = [];   // Nombre => Posición final

	protected function prepare() : void{
		$worldName = $this->worldName;
		$world = $this->world;

		$world->setTime(\pocketmine\world\World::TIME_DAY);
		$world->stopTime();

		/** @var \practice\world\World $worldData */
		$worldData = \practice\world\WorldFactory::get($worldName);
		$firstPosition = $worldData->getFirstPosition();

		if($this->mainParty === null){
			$this->status = self::RESTARTING;
			return;
		}

		foreach($this->mainParty->getMembers() as $member){
			$member->setGamemode(\pocketmine\player\GameMode::SURVIVAL());

			$member->getInventory()->clearAll();
			$member->getArmorInventory()->clearAll();
			$member->getCursorInventory()->clearAll();
			$member->getOffHandInventory()->clearAll();

			$session = \practice\session\SessionFactory::get($member);
			$inventory = $session?->getInventory(strtolower(DuelFactory::getName($this->typeId)));
			$inventory?->giveKit();

			$member->teleport(\pocketmine\world\Position::fromObject($firstPosition->add(0.5, 0, 0.5), $this->world));
		}
	}

	public function scoreboard(Player $player) : array{
		switch($this->status){
			case self::STARTING:
				return [' &fParty FFA starting'];
			case self::RESTARTING:
				return [' &fParty FFA ended'];
			default:
				$alive = array_filter($this->mainParty->getMembers(), function(Player $p) : bool{
					return !$this->isSpectator($p);
				});
				return [
					' &fKit: &e' . DuelFactory::getName($this->typeId),
					' &fDuration: &e' . gmdate('i:s', $this->running),
					' &r&r',
					' &fAlive: &e' . count($alive)
				];
		}
	}

	public function handleDamage(EntityDamageEvent $event) : void{
		$player = $event->getEntity();
		if(!$player instanceof Player) return;

		if(!$this->isRunning()){
			$event->cancel();
			return;
		}

		$finalHealth = $player->getHealth() - $event->getFinalDamage();
		$damager = null;

		if($event instanceof EntityDamageByEntityEvent){
			$damager = $event->getDamager();
			if(!$damager instanceof Player || !$this->isPlayer($damager)){
				$event->cancel();
				return;
			}
		}

		if($finalHealth <= 0.00){
			$event->cancel();
            
            $location = $player->getLocation();
            ServerUtils::spawnActor("minecraft:lightning_bolt", $location);
            ServerUtils::playSound("ambient.weather.thunder", $location);

			$deathMessage = '&c' . $player->getName() . ($damager === null ? ' &edied' : ' &ewas slain by &c' . $damager->getName());
			$this->mainParty->broadcastMessage(TextFormat::colorize($deathMessage));

			$this->addSpectator($player);
			$this->checkWinner();
		}
	}

	public function handleItemUse(\pocketmine\event\player\PlayerItemUseEvent $event): void{
		$player = $event->getPlayer();
		$item = $event->getItem();

		if ($item instanceof \pocketmine\item\MushroomStew) {
			$event->cancel();

			$newHealth = min($player->getHealth() + 9.0, $player->getMaxHealth());
			$player->setHealth($newHealth);

			$hand = $player->getInventory()->getItemInHand();
			$hand->setCount($hand->getCount() - 1);
			$player->getInventory()->setItemInHand($hand);

			$player->getInventory()->addItem(\pocketmine\item\VanillaItems::BOWL());

			$player->getWorld()->addParticle($player->getPosition(), new \pocketmine\world\particle\HeartParticle());
		}
	}

	public function isPlayer(Player $player) : bool{
		if($this->mainParty === null) return false;
		return $this->mainParty->isMember($player) && !$this->isSpectator($player);
	}

	public function checkWinner() : void{
		if($this->mainParty === null) return;

		$alive = array_filter($this->mainParty->getMembers(), function(Player $p) : bool{
			return !$this->isSpectator($p);
		});

		if(count($alive) <= 1){
			$winner = count($alive) === 1 ? reset($alive) : null;
			$this->finishFFA($winner);
		}
	}

	public function finishFFA(?Player $winner) : void{
		$this->winner = $winner?->getName() ?? "None";

		if($winner !== null){
			$this->mainParty->broadcastTitle(TextFormat::colorize('&l&a' . $winner->getName()), TextFormat::colorize('&7won the FFA!'));
		}

		// Generar posiciones
		$this->generatePlacements();

		// Mostrar Top
		$this->showTop();

		foreach($this->mainParty->getMembers() as $member){
			if($member->isOnline()){
				$member->getArmorInventory()->clearAll();
				$member->getInventory()->clearAll();
				$member->getOffHandInventory()->clearAll();
				$member->getCursorInventory()->clearAll();
				$member->getEffects()->clear();
				$member->setHealth($member->getMaxHealth());
			}
		}
		$this->status = self::RESTARTING;
	}

	private function generatePlacements(): void{
		$this->placements = [];

		// Primero los vivos (normalmente solo 1)
		$alive = array_filter($this->mainParty->getMembers(), function(Player $p){
			return !$this->isSpectator($p) && $p->isOnline();
		});

		$position = 1;
		foreach($alive as $player){
			$this->placements[$player->getName()] = $position++;
		}

		// Luego los espectadores en orden inverso (los que murieron primero van al final)
		$spectators = array_reverse($this->spectators); // invertimos para que el que murió primero quede abajo

		foreach($spectators as $player){
			if($player instanceof Player){
				$this->placements[$player->getName()] = $position++;
			}
		}
	}

	private function showTop(): void{
		if($this->mainParty === null) return;

		$this->mainParty->broadcastMessage(TextFormat::colorize("&6&lTOP FFA"));
		$this->mainParty->broadcastMessage(TextFormat::colorize("§7"));

		// Ordenamos por posición
		$sorted = $this->placements;
		asort($sorted); // orden ascendente (1 primero)

		foreach($sorted as $name => $pos){
			$line = "&e" . $pos . ". &f" . $name;
			if($pos === 1){
				$line .= " &a(Winner)";
			}
			$this->mainParty->broadcastMessage(TextFormat::colorize($line));
		}

		$this->mainParty->broadcastMessage(TextFormat::colorize("§7"));
	}
    
    protected function logFFA() : void{
		$webhook = new \CortexPE\DiscordWebhookAPI\Webhook(\practice\Practice::getInstance()->getConfig()->get('webhook-parties', ''));
		$message = new \CortexPE\DiscordWebhookAPI\Message();
		$embed = new \CortexPE\DiscordWebhookAPI\Embed();

		$embed->setColor(hexdec('ffaa00'));
		$embed->setTitle('Party FFA - ' . DuelFactory::getName($this->typeId));
		$embed->setDescription(
			'**Winner:** ' . $this->winner . PHP_EOL .
			'**Players:** ' . PHP_EOL .
			implode(PHP_EOL, array_map(fn(\pocketmine\player\Player $player) => '- ' . $player->getName(), $this->mainParty->getMembers()))
		);

		$message->addEmbed($embed);
		$webhook->send($message);
	}

	public function update() : void{
		if($this->status === self::RESTARTING && $this->restarting <= 0){
			if($this->mainParty !== null){
				foreach($this->mainParty->getMembers() as $member){
					$member->setGamemode(\pocketmine\player\GameMode::SURVIVAL());
					$member->setHealth($member->getMaxHealth());
					$member->setNameTag($member->getName());
					$member->getInventory()->clearAll();
					$member->getArmorInventory()->clearAll();
					$member->teleport($member->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
					$this->mainParty->giveItems($member);
				}
				$this->mainParty->setDuel(null);
			}
			$this->delete();
			return;
		}
		parent::update();
	}
}