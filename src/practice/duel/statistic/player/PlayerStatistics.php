<?php

declare(strict_types=1);

namespace practice\duel\statistic\player;

use practice\session\Session;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;

final class PlayerStatistics {

    public function __construct(
        private readonly Session $session,
        private int $critics = 0,
        private int $totalPotions = 0,
        private float $damageDealt = 0.0,
        private int $hits = 0,
        private int $maxCombo = 0,
        private int $currentCombo = 0,
        private int $kills = 0,
        private int $deaths = 0,
        private int $points = 0
    ) {}

    public function getCritics(): int {
        return $this->critics;
    }

    public function getTotalPotions(): int {
        return $this->totalPotions;
    }

    public function getDamageDealt(): float {
        return round($this->damageDealt, 2);
    }

    public function getHits(): int {
        return $this->hits;
    }

    public function getMaxCombo(): int {
        return $this->maxCombo;
    }

    public function getCurrentCombo(): int {
        return $this->currentCombo;
    }

    public function getKills(): int {
        return $this->kills;
    }

    public function getDeaths(): int {
        return $this->deaths;
    }

    public function getPoints(): int {
        return $this->points;
    }

    public function addKill(): void {
        $this->kills++;
    }

    public function addDeath(): void {
        $this->deaths++;
    }

    public function addPoint(): void {
        $this->points++;
    }

    public function addCritic(): void {
        $this->critics++;
    }

    public function addHit(): void {
        $this->hits++;
        $this->currentCombo++;
        if ($this->currentCombo > $this->maxCombo) {
            $this->maxCombo = $this->currentCombo;
        }
    }

    public function resetCombo(): void {
        $this->currentCombo = 0;
    }

    public function calculateConsumables(int $typeId): void {
        $player = $this->session->getPlayer();
        if ($player === null) return;
        
        $targetTypeId = match ($typeId) {
            \practice\duel\Duel::TYPE_BUILDUHC, \practice\duel\Duel::TYPE_FINALUHC,\practice\duel\Duel::TYPE_GAPPLE, \practice\duel\Duel::TYPE_CAVEUHC => ItemTypeIds::GOLDEN_APPLE,
            \practice\duel\Duel::TYPE_SOUP, \practice\duel\Duel::TYPE_HG, \practice\duel\Duel::TYPE_SG => ItemTypeIds::MUSHROOM_STEW,
            \practice\duel\Duel::TYPE_NODEBUFF, \practice\duel\Duel::TYPE_POTPVP, \practice\duel\Duel::TYPE_DEBUFF => ItemTypeIds::SPLASH_POTION,
            default => null
        };

        $this->totalPotions = 0;
        if ($targetTypeId === null) return;

        foreach ($player->getInventory()->getContents() as $item) {
            if ($item->getTypeId() === $targetTypeId) {
                $this->totalPotions += $item->getCount();
            }
        }
    }

    public function addDamageDealt(float $damage = 0.0): void {
        $this->damageDealt += $damage;
    }
}
