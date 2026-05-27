<?php

declare(strict_types=1);

namespace practice\session\setting\gameplay;

class QuickThrow extends GameplaySetting{

	public function __construct(){
		parent::__construct('Tap To Use Item', false);
	}
}