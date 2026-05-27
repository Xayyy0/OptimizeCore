<?php

declare(strict_types=1);

namespace practice\duel\type;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;
use practice\duel\Duel;
use practice\duel\DuelFactory;

class Boxing extends Duel {

    public function handleDamage(EntityDamageEvent $event): void {
        parent::handleDamage($event);
        $player = $event->getEntity();

        if ($event->isCancelled()) {
            return;
        }
        
        if ($player instanceof Player) {
            $player->setHealth($player->getMaxHealth());
        }
    }

    public function scoreboard(Player $player): array {
        if ($this->status === self::RUNNING) {
            $firstSession = $this->firstSession;
            $secondSession = $this->secondSession;
            
            $firstStats = $this->statistics->getFirstStatistic();
            $secondStats = $this->statistics->getSecondStatistic();

            if ($this->isSpectator($player)) {
                return [
                    ' &fKit: &e' . DuelFactory::getName($this->typeId),
                    ' &fType: &e' . ($this->ranked ? 'Ranked' : 'Unranked'),
                    ' &r&r',
                    ' &fDuration: &e' . gmdate('i:s', $this->running),
                    ' &fSpectators: &e' . count($this->spectators),
                    ' &fHits: &a' . $firstStats->getHits() . ' &7| &c' . $secondStats->getHits()
                ];
            }
            
            /** @var Player $opponent */
            $opponent = $this->getOpponent($player);
            $isFirst = ($firstSession->getXuid() !== '' && $firstSession->getXuid() === $player->getXuid()) || $firstSession->getName() === $player->getName();

            $playerStats = $isFirst ? $firstStats : $secondStats;
            $opponentStats = $isFirst ? $secondStats : $firstStats;

            $playerHits = $playerStats->getHits();
            $playerCombo = $playerStats->getCurrentCombo();

            $opponentHits = $opponentStats->getHits();
            $opponentCombo = $opponentStats->getCurrentCombo();

            $hitsDiff = $playerHits - $opponentHits;

            return [
                ' &fFighting: &e' . $opponent->getName(),
                ' &r&r&r',
                ' &eHits: ' . ($hitsDiff >= 0 ? '&a(+' . $hitsDiff . ')' : '&c(' . $hitsDiff . ')'),
                '  &aYou: &f' . $playerHits . ($playerCombo > 0 ? ' &e(' . $playerCombo . ' combo)' : ''),
                '  &cThem: &f' . $opponentHits . ($opponentCombo > 0 ? ' &e(' . $opponentCombo . ' combo)' : ''),
                ' &r&r&r&r',
                ' &aYour ping: ' . $player->getNetworkSession()->getPing(),
                ' &cTheir ping: ' . $opponent->getNetworkSession()->getPing()
            ];
        }
        return parent::scoreboard($player);
    }
}
