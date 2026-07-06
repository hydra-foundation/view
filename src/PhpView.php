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
     * Resolve a template name to a readable file path, contained to the base path.
     *
     * Template names can come from request data (route params picking a view),
     * and the resolved file is include()d — so a name that escapes the base
     * path is LFI straight into RCE. realpath() collapses every `../` and
     * symlink in the candidate, and the resolved path must sit strictly under
     * the resolved base path. Both failure modes — genuinely missing template
     * and escape attempt — throw the SAME exception, with no filesystem path
     * in the message, so a probing attacker learns neither the directory
     * layout nor whether a file outside the view root exists.
     *
     * @internal called by {@see Template}
     */
    public function locate(string $template): string
    {
        // A null byte is never a legitimate template name, and the filesystem
        // calls below would throw a ValueError on it — reject it up front
        // (and don't echo the poisoned name back).
        if (str_contains($template, "\0")) {
            throw new RuntimeException('View not found.');
        }

        $root = realpath($this->basePath);
        $real = $root === false
            ? false
            : realpath($this->basePath . '/' . $template . '.php');

        if (
            $real === false
            || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)
            || !is_file($real)
        ) {
            throw new RuntimeException("View not found: \"{$template}\".");
        }

        return $real;
    }
}
