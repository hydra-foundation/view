# Hydra View

Native PHP templating — no compilation step, no new syntax, no cache directory.
A template is a plain `.php` file; the engine gives it Twig-style inheritance and
escape-by-convention safety while staying ordinary PHP you can read top to bottom.

## The seam

Controllers depend on `Contracts\ViewInterface`, never on the engine, so the
template engine is an application choice — bind a Twig adapter in its place and
no controller changes. The shipped implementation is `PhpView`.

```php
use Hydra\View\PhpView;

$view = new PhpView(__DIR__ . '/views');
echo $view->render('home', ['name' => 'Will']);
```

## Inside a template

The `$this` a template sees is a per-render `Template`, so nested partials and
layout chains never stomp each other's state. The vocabulary:

- `$this->e($value)` — escape for HTML. Escaping is **by convention, not by
  default**: templates are ordinary PHP, so a bare `<?= $x ?>` emits raw output —
  always route dynamic values through `e()`. Within `e()`, every value is
  treated as untrusted; the *only* way to emit raw markup through it is to wrap
  the value in `HtmlView`, so forgetting the `HtmlView` mark can only ever
  over-escape. Forgetting `e()` itself, however, under-escapes — that
  discipline is on the template author.
- `$this->extends('layouts/base')` — wrap this template in a layout. The child's
  leftover output becomes the layout's `content` section. Chains to any depth.
- `$this->start('title') … $this->stop()` / `$this->section('title', $default)` —
  capture and yield named sections.
- `$this->partial('card', [...])` — render another template inline as its own
  independent chain (inherits none of the parent's data; a stray `extends()`
  inside is ignored).
- `$this->siteUrl('/blog')` — build an absolute URL for canonical / Open Graph
  tags (see below).
- `$this->csrf()` / `$this->csrfToken()` — emit the session CSRF token (see
  below).

## Full page vs. htmx fragment, one template

`render($t, $data, layout: false)` returns the template's own body and ignores
its `extends()`. That's how a single template serves both a full page (layout on)
and an htmx fragment (layout off) off one route — the inheritance is resolved by
output buffering, so the fragment path simply doesn't walk the chain.

## CSRF (optional)

`PhpView`'s second argument is an optional `Hydra\Csrf\CsrfGuard`. Wired, the
template can emit `$this->csrf()` (a hidden `_token` field) and `$this->csrfToken()`
(the raw token, for a `<meta>` tag or htmx `hx-headers`). Left null — the
isolation default — the csrf helpers throw if a template calls them, but the rest
of the engine works untouched.

## Absolute URLs (optional)

`PhpView`'s third argument is an optional base URL string (the app passes its
configured site URL). With it, `siteUrl('/blog')` →
`https://example.com/blog` and `siteUrl()` → the bare origin. It's a plain
string, not an app config object, so this package stays free of any
application's config types. Absent it, `siteUrl()` returns the path unchanged.
