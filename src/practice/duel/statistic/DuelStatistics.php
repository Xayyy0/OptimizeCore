<?php

declare(strict_types=1);

namespace practice\duel\statistic;

use practice\duel\Duel;
use practice\duel\statistic\player\PlayerStatistics;
use practice\session\Session;

final class DuelStatistics {

    private readonly PlayerStatistics $firstStatistic;
    private readonly PlayerStatistics $secondStatistic;

    public function __construct(
        private readonly Duel $duel
    ) {
        $this->firstStatistic = new PlayerStatistics($this->duel->getFirstSession());
        $this->secondStatistic = new PlayerStatistics($this->duel->getSecondSession());
    }

    public function getDuel(): Duel {
        return $this->duel;
    }

    public function getFirstStatistic(): PlayerStatistics {
        return $this->firstStatistic;
    }

    public function getSecondStatistic(): PlayerStatistics {
        return $this->secondStatistic;
    }

    public function getStatistic(Session $session): PlayerStatistics {
        $firstSession = $this->duel->getFirstSession();
        if ($session->getXuid() !== '' && $session->getXuid() === $firstSession->getXuid()) {
            return $this->firstStatistic;
        }
        if ($session->getName() === $firstSession->getName()) {
            return $this->firstStatistic;
        }
        return $this->secondStatistic;
    }
}
