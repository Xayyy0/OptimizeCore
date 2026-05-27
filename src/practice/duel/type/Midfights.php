<?php

declare(strict_types=1);

namespace practice\duel\type;

use practice\duel\Duel;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
use pocketmine\world\Position;
use pocketmine\utils\TextFormat;
use practice\session\SessionFactory;

class Midfights extends Duel
{
    private int $firstPoints = 0;
    private int $secondPoints = 0;
    
    private const MAX_POINTS = 3;
    private int $roundCountdown = 0;

    private float $fixedDamage = 1.0;

    public static function getIcon(): string
    {
        return "textures/items/diamond_chestplate";
    }

    protected function init(): void
    {
        $this->canDrop = false;
    }

    public function handleDamage(EntityDamageEvent $event): void
    {
        parent::handleDamage($event);
        $player = $event->getEntity();
        if (!$player instanceof Player) return;

        if (!$this->isRunning()) {
            $event->cancel();
            return;
        }

        if ($event instanceof EntityDamageByEntityEvent) {
            $attacker = $event->getDamager();

            if ($attacker instanceof Player && $this->isPlayer($attacker) && $this->isPlayer($player)) {
                $event->setBaseDamage($this->fixedDamage);

                $event->setModifier(0.0, EntityDamageByEntityEvent::MODIFIER_CRITICAL);
                $event->setModifier(0.0, EntityDamageByEntityEvent::MODIFIER_ARMOR);
                $event->setModifier(0.0, EntityDamageByEntityEvent::MODIFIER_ARMOR_ENCHANTMENTS);
                $event->setModifier(0.0, EntityDamageByEntityEvent::MODIFIER_WEAPON_ENCHANTMENTS);
                $event->setModifier(0.0, EntityDamageByEntityEvent::MODIFIER_STRENGTH);
                $event->setModifier(0.0, EntityDamageByEntityEvent::MODIFIER_WEAKNESS);
                $event->setModifier(0.0, EntityDamageByEntityEvent::MODIFIER_RESISTANCE);
            }
        }

        $finalHealth = $player->getHealth() - $event->getFinalDamage();

        if ($finalHealth <= 0.0) {
            $event->cancel();
            
            $location = $player->getLocation();
            \practice\utils\ServerUtils::spawnActor("minecraft:lightning_bolt", $location);
            \practice\utils\ServerUtils::playSound("ambient.weather.thunder", $location);

            $player->setGamemode(GameMode::SPECTATOR());
            $this->handleDeath($player);
        }
    }

    private function handleDeath(Player $loser): void
    {
        $session = SessionFactory::get($loser);
        if ($session !== null) {
            $this->statistics->getStatistic($session)->addDeath();
            $opponent = $this->getOpponent($loser);
            if ($opponent !== null) {
                $this->statistics->getStatistic(SessionFactory::get($opponent))->addKill();
            }
        }

        if ($loser->getXuid() === $this->getFirstSession()->getXuid()) {
            $this->secondPoints++;
            $this->statistics->getSecondStatistic()->addPoint();
        } else {
            $this->firstPoints++;
            $this->statistics->getFirstStatistic()->addPoint();
        }

        if ($this->firstPoints >= self::MAX_POINTS || $this->secondPoints >= self::MAX_POINTS) {
            $this->finish($loser);
            return;
        }

        // Iniciar cooldown de 3 segundos para la siguiente ronda
        $this->roundCountdown = 3;
    }

    private function startNewRound(): void
    {
        $p1 = $this->getFirstSession()->getPlayer();
        $p2 = $this->getSecondSession()->getPlayer();

        if ($p1) {
            $p1->setGamemode(GameMode::SURVIVAL());
            $this->resetPlayerStats($p1);
            $this->giveMidfightKit($p1);
            $this->teleportToSpawn($p1, true);
        }

        if ($p2) {
            $p2->setGamemode(GameMode::SURVIVAL());
            $this->resetPlayerStats($p2);
            $this->giveMidfightKit($p2);
            $this->teleportToSpawn($p2, false);
        }
    }

    private function resetPlayerStats(Player $player): void
    {
        $player->setHealth($player->getMaxHealth());
        $player->getHungerManager()->setFood(20);
        $player->getHungerManager()->setSaturation(20.0);
        $player->getEffects()->clear();
    }

    private function teleportToSpawn(Player $player, bool $isFirst): void
    {
        $worldData = \practice\world\WorldFactory::get($this->worldName);
        $pos = $isFirst ? $worldData->getFirstPosition() : $worldData->getSecondPosition();
        $player->teleport(Position::fromObject($pos->add(0.5, 0, 0.5), $this->world));
    }

    private function giveMidfightKit(Player $player): void
    {
        $player->getArmorInventory()->setContents([
            VanillaItems::DIAMOND_BOOTS(),
            VanillaItems::DIAMOND_LEGGINGS(),
            VanillaItems::DIAMOND_CHESTPLATE(),
            VanillaItems::DIAMOND_HELMET()
        ]);

        $sword = VanillaItems::DIAMOND_SWORD();
        $sword->setUnbreakable(true);
        $player->getInventory()->setItem(0, $sword);
    }

    protected function prepare(): void
    {
        parent::prepare();

        $p1 = $this->getFirstSession()->getPlayer();
        $p2 = $this->getSecondSession()->getPlayer();

        if ($p1 && $p2) {
            $this->giveMidfightKit($p1);
            $this->giveMidfightKit($p2);
        }
    }

    /** Sobrescribimos update() para manejar el cooldown entre rondas */
    public function update(): void
    {
        if ($this->roundCountdown > 0) {
            $this->handleRoundCountdown();
            return;
        }

        parent::update(); // Dejar que el update normal siga funcionando
    }

    private function handleRoundCountdown(): void
    {
        $p1 = $this->getFirstSession()->getPlayer();
        $p2 = $this->getSecondSession()->getPlayer();

        $color = match ($this->roundCountdown) {
            3, 2 => TextFormat::YELLOW,
            1    => TextFormat::RED,
            default => TextFormat::WHITE
        };

        $msg = $color . $this->roundCountdown . TextFormat::GRAY . "...";

        if ($p1) {
            $p1->sendMessage(TextFormat::GRAY . "Nueva ronda en " . $msg);
            $p1->sendTitle($color . $this->roundCountdown, TextFormat::GRAY . "Get ready!", 5, 20, 5);
        }
        if ($p2) {
            $p2->sendMessage(TextFormat::GRAY . "Nueva ronda en " . $msg);
            $p2->sendTitle($color . $this->roundCountdown, TextFormat::GRAY . "Get ready!", 5, 20, 5);
        }

        $this->roundCountdown--;

        if ($this->roundCountdown <= 0) {
            $this->startNewRound();
        }
    }

    public function scoreboard(Player $player): array
    {
        if ($this->status === self::RUNNING) {
            $firstName = $this->getFirstSession()->getName();
            $secondName = $this->getSecondSession()->getName();

            $firstPoints = $this->firstPoints;
            $secondPoints = $this->secondPoints;

            if ($this->isSpectator($player)) {
                return [
                    TextFormat::DARK_BLUE . $firstName . " &9" . str_repeat('█', $firstPoints) . '&7' . str_repeat('█', self::MAX_POINTS - $firstPoints),
                    TextFormat::RED . $secondName . " &c" . str_repeat('█', $secondPoints) . '&7' . str_repeat('█', self::MAX_POINTS - $secondPoints),
                    ' &r ',
                    ' &fDuration: &e' . gmdate('i:s', $this->running)
                ];
            }

            $opponent = $this->getOpponent($player);

            return [
                TextFormat::BLUE . $firstName . " &9" . str_repeat('█', $firstPoints) . '&7' . str_repeat('█', self::MAX_POINTS - $firstPoints),
                TextFormat::RED . $secondName . " &c" . str_repeat('█', $secondPoints) . '&7' . str_repeat('█', self::MAX_POINTS - $secondPoints),
                ' &r ',
                ' &fDuration: &e' . gmdate('i:s', $this->running),
                ' &r&r ',
                ' &aYour ping: ' . $player->getNetworkSession()->getPing(),
                ' &cTheir ping: ' . $opponent->getNetworkSession()->getPing()
            ];
        }

        return parent::scoreboard($player);
    }

    public function finish(Player $loser): void
    {
        $this->roundCountdown = 0; // Resetear countdown
        parent::finish($loser);
    }
}
