<?php

declare(strict_types=1);

namespace Hydra\View;

use Hydra\Csrf\CsrfGuard;
use Hydra\View\Contracts\ViewInterface;
use RuntimeException;

/**
 * Native PHP template renderer.
 *
 * A template is a plain `.php` file under the base path. The engine itself is
 * stateless: each render spins up a {@see Template} that owns the per-render
 * state (sections, layout chain) and is the `$this` templates see, so nested
 * partials and layouts can never stomp each other's state.
 *
 * The optional {@see CsrfGuard} is handed down to every Template so views can
 * emit the session's CSRF token — `$this->csrf()` for a hidden field, or
 * `$this->csrfToken()` for the layout's meta tag / hx-headers. It is optional so
 * the renderer stays usable (e.g. in isolation tests) without CSRF wired up.
 *
 * The optional $baseUrl is likewise handed down so the layout can build absolute
 * URLs (`$this->siteUrl()`) for canonical/Open Graph tags. It is a plain string
 * (the app passes its configured site URL) rather than an app config object, so
 * the view package stays free of any application's config types — and absent it,
 * siteUrl() degrades to returning the path unchanged.
 */
final class PhpView implements ViewInterface
{
    public function __construct(
        private readonly string $basePath,
        private readonly ?CsrfGuard $csrf = null,
        private readonly ?string $baseUrl = null,
    ) {}

    public function render(string $template, array $data = [], bool $layout = true): string
    {
        return (new Template($this, $data, $layout, $this->csrf, $this->baseUrl))->resolve($template);
    }

    /**
     * Resolve a template name to a readable file path.
     *
     * @internal called by {@see Template}
     */
    public function locate(string $template): string
    {
        $path = $this->basePath . '/' . $template . '.php';

        if (!is_file($path)) {
            throw new RuntimeException("View not found: \"{$template}\" (looked in {$path}).");
        }

        return $path;
    }
}
