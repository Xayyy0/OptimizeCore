<?php

declare(strict_types=1);

namespace practice\session\setting\gameplay;

use practice\session\setting\Setting;

class FocusFFA extends GameplaySetting {

    public function __construct(bool $value = false){
        parent::__construct("HideNonOponnents", $value);
    }
}
