<?php

declare(strict_types=1);

namespace practice\session\setting\gameplay;

final class AutoSprint extends GameplaySetting{

    public function __construct(){
        parent::__construct(
            "AutoSprint",
            false
        );
    }
}