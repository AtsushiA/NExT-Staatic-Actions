<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Support;

final class PlaceholderTemplate
{
    public static function render(string $template, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }
}
