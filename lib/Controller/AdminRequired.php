<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Controller;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class AdminRequired {
}
