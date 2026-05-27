<?php

declare(strict_types=1);

namespace practice\session\setting;

use practice\session\setting\display\CPSCounter;
use practice\session\setting\display\Scoreboard;
use practice\session\setting\gameplay\AutoRespawn;
use practice\session\setting\gameplay\QuickThrow;
use practice\session\setting\gameplay\AutoSprint;
use practice\session\setting\gameplay\FocusFFA;

class Setting{

	public const SCOREBOARD = 'scoreboard';
	public const CPS_COUNTER = 'cps_counter';
	public const AUTO_RESPAWN = 'auto_respawn';
    public const QUICK_THROW = 'quick_throw';
    public const AUTO_SPRINT = 'auto_sprint';
    public const FOCUS_FFA = 'focus_ffa';

	public function __construct(
		protected string $name,
		protected mixed $value
	){
	}

	public static function create() : array{
		return [
			self::SCOREBOARD => new Scoreboard,
			self::CPS_COUNTER => new CPSCounter,
			self::AUTO_RESPAWN => new AutoRespawn,
            self::QUICK_THROW => new QuickThrow,
            self::AUTO_SPRINT => new AutoSprint,
            self::FOCUS_FFA => new FocusFFA
		];
	}

	public function getName() : string{
		return $this->name;
	}

	public function serializeData() : array{
		return [
			'value' => $this->value
		];
	}
}