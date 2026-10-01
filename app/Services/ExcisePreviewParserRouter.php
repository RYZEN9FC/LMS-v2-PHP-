<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ExcisePreviewParserRouter
{
    public function __construct(
        private readonly ExcisePreviewParser $chromeParser,
        private readonly MacOsExcisePreviewParser $macOsParser,
    ) {}

    public function parse(string $path, ?callable $progress = null): array
    {
        try {
            return $this->chromeParser->parse($path, $progress);
        } catch (ValidationException $chromeError) {
            try {
                return $this->macOsParser->parse($path, $progress);
            } catch (ValidationException) {
                throw $chromeError;
            }
        }
    }
}
