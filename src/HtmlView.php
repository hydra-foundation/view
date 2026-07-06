<?php

declare(strict_types=1);

namespace Hydra\View;

use Stringable;

/**
 * Marks a string as already-safe HTML that must NOT be escaped again.
 *
 * {@see Template::e()} treats every value as untrusted unless it is an HtmlView
 * instance, so the only way to emit raw markup *through e()* is to say so
 * explicitly. Note the guarantee is scoped to e(): templates are native PHP,
 * so output that bypasses e() entirely (a bare `<?= $x ?>`) is raw — escaping
 * is a convention the template author upholds, not something the engine can
 * enforce.
 */
final class HtmlView implements Stringable
{
    public function __construct(private readonly string|Stringable $html) {}

    public function __toString(): string
    {
        return (string) $this->html;
    }
}
