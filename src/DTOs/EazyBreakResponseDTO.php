<?php

namespace Devolon\EazyBreak\DTOs;

use Devolon\Common\Bases\DTO;

class EazyBreakResponseDTO extends DTO
{
    public function __construct(
        public int $id,
        public string $token,
        public string $url,
        public int $created,
        public string $status,
        public string $value
    ) {
    }
}
