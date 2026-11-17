<?php

namespace App\Services;

use App\Models\Media;
use App\Models\PageContent;
use App\Models\PageRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Editable copy for public pages. The registry (config/content.php) lists each page's fields with their
 * default text, so the site reads correctly before anyone edits it. Visitors see published content only;
 * a draft is shown only in the admin preview. Every publish stores a full revision for rollback.
 */
final class SiteContent
{
    public static bool $preview = false;
    public static ?string $previewPage = null;

    // ---------- registry ----------

    public static function pages(): array
    {
        return config('content.pages');
    }

    public static function page(string $page): array
    {
        return self::pages()[$page] ?? abort(404);
    }

    public static function fields(string $page): array
    {
        return self::page($page)['fields'];
    }

    public static function defaultFor(array $field): mixed
    {
        if (isset($field['default_file'])) {
            $path = resource_path('content/'.$field['default_file']);

            return File::exists($path) ? trim(File::get($path)) : '';
        }

        return $field['default'] ?? null;
    }

    public static function defaults(string $page): array
    {
        return collect(self::fields($page))->map(fn ($f) => self::defaultFor($f))->all();
    }

    // ---------- reading ----------

    public static function currentPage(): ?string
    {
        return self::$previewPage ?? request()->route()?->getName();
    }

    private static function row(string $page): ?PageContent
    {
        try {
            return Schema::hasTable('page_contents') ? PageContent::where('page', $page)->first() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Raw stored values merged over defaults (published copy only, cached). */
    public static function published(string $page): array
    {
        $stored = Cache::rememberForever("content.published.$page", fn () => self::row($page)?->published ?? []);

        return array_replace(self::defaults($page), $stored);
    }

    /** The working copy: draft over published. */
    public static function working(string $page): array
    {
        $row = self::row($page);

        return array_replace(self::published($page), $row?->draft ?? []);
    }

    public static function hasDraft(string $page): bool
    {
        return ! empty(self::row($page)?->draft);
    }

    public static function value(string $path, mixed $fallback = null): mixed
    {
        [$page, $key] = array_pad(explode('.', $path, 2), 2, null);
        $fields = self::pages()[$page]['fields'] ?? null;
        if (! $fields || ! isset($fields[$key])) {
            return $fallback;
        }
        $data = self::$preview ? self::working($page) : self::published($page);

        return self::present($fields[$key], $data[$key] ?? self::defaultFor($fields[$key]));
    }

    /** Turn a stored value into what a view prints. */
    private static function present(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'text', 'textarea' => self::tokens((string) $value, false),
            'richtext' => self::tokens((string) $value, true),
            'button' => ['label' => self::tokens((string) ($value['label'] ?? ''), false), 'url' => (string) ($value['url'] ?? '')],
            'image' => self::image($value),
            'list' => collect($value ?: [])->map(fn ($row) => collect($field['fields'])->map(fn ($f, $k) => self::present($f, $row[$k] ?? self::defaultFor($f)))->all())->values()->all(),
            default => $value,
        };
    }

    private static function image(mixed $value): ?array
    {
        $id = is_array($value) ? ($value['media'] ?? null) : null;
        if (! $id) {
            return null;
        }
        $m = Cache::remember("content.media.$id", 3600, fn () => Media::find($id)?->only(['filename', 'width', 'height']));

        return $m ? ['url' => route('media.show', $m['filename']), 'alt' => (string) ($value['alt'] ?? ''), 'width' => $m['width'], 'height' => $m['height']] : null;
    }

    /** Fills the tokens in every string of a config array (articles, search index, rates) from the Branding page. */
    public static function resolve(array $data): array
    {
        array_walk_recursive($data, function (&$v) {
            if (is_string($v) && str_contains($v, '{')) {
                $v = self::tokens($v, false);
            }
        });

        return $data;
    }

    /** {name}, {short_name}, {phone}, {email} and {hours} follow the Branding page. */
    public static function tokens(string $text, bool $html): string
    {
        $map = ['{name}' => brand('name'), '{short_name}' => brand('short_name'), '{phone}' => brand('support_phone'), '{email}' => brand('support_email'), '{hours}' => brand('support_hours'), '{tel}' => brand('tel')];
        if ($html) {
            $map = array_map(fn ($v) => e($v), $map);
        }

        return strtr($text, $map);
    }

    // ---------- writing ----------

    /** Validate and clean form input against the page's fields. @throws ValidationException */
    public static function clean(string $page, array $input, array $files, ?User $by): array
    {
        $out = [];
        $errors = [];
        foreach (self::fields($page) as $key => $field) {
            try {
                $out[$key] = self::cleanField($field, $input[$key] ?? null, $files[$key] ?? null, "fields.$key", $by);
            } catch (ValidationException $e) {
                $errors += $e->errors();
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    private static function cleanField(array $field, mixed $raw, mixed $file, string $name, ?User $by): mixed
    {
        $label = $field['label'];
        $fail = fn (string $m) => throw ValidationException::withMessages([$name => "$label: $m"]);

        switch ($field['type']) {
            case 'text':
            case 'textarea':
                $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $raw));
                $v = $field['type'] === 'text' ? preg_replace('/\s+/', ' ', $v) : $v;
                if ($v === '' && empty($field['optional'])) { $fail('this field is required.'); }
                if (mb_strlen($v) > ($field['max'] ?? 300)) { $fail('is too long (max '.($field['max'] ?? 300).' characters).'); }

                return $v;
            case 'richtext':
                $v = self::sanitize((string) $raw, ! empty($field['headings']));
                if (trim(strip_tags($v)) === '' && empty($field['optional'])) { $fail('this field is required.'); }
                if (mb_strlen($v) > ($field['max'] ?? 20000)) { $fail('is too long.'); }

                return $v;
            case 'button':
                $l = trim((string) ($raw['label'] ?? ''));
                $u = trim((string) ($raw['url'] ?? ''));
                if (($l === '' || $u === '') && empty($field['optional'])) { $fail('add both a label and a link.'); }
                if ($l !== '' && mb_strlen($l) > 40) { $fail('the button label is too long (max 40).'); }
                if ($u !== '' && ! self::safeUrl($u)) { $fail('links must start with http, https, mailto, tel or /.'); }

                return ['label' => $l, 'url' => $u];
            case 'image':
                $keep = (int) ($raw['media'] ?? 0);
                $alt = trim((string) ($raw['alt'] ?? ''));
                if (! empty($raw['remove'])) { return null; }
                if ($file instanceof UploadedFile) {
                    if ($alt === '') { $fail('add alt text describing the image.'); }
                    $m = app(MediaStore::class)->store($file, $name, $alt, $by);

                    return ['media' => $m->id, 'alt' => $alt];
                }
                if ($keep && Media::whereKey($keep)->exists()) {
                    if ($alt === '') { $fail('add alt text describing the image.'); }

                    return ['media' => $keep, 'alt' => mb_substr(strip_tags($alt), 0, 200)];
                }

                return null;
            case 'list':
                $rows = array_values(array_filter(is_array($raw) ? $raw : [], 'is_array'));
                $rowsOut = [];
                foreach ($rows as $i => $row) {
                    $clean = [];
                    foreach ($field['fields'] as $k => $sub) {
                        $sub['label'] = $label.' '.($i + 1).', '.$sub['label'];
                        $clean[$k] = self::cleanField($sub, $row[$k] ?? null, is_array($file) ? ($file[$i][$k]['file'] ?? null) : null, "$name.$i.$k", $by);
                    }
                    $rowsOut[] = $clean;
                }
                if (count($rowsOut) < ($field['min'] ?? 0)) { $fail('needs at least '.$field['min'].'.'); }
                if (count($rowsOut) > ($field['max'] ?? 12)) { $fail('allows at most '.($field['max'] ?? 12).'.'); }

                return $rowsOut;
        }

        return null;
    }

    public static function safeUrl(string $url): bool
    {
        if (preg_match('#^/(?!/)[^\s]*$#', $url)) { return true; }

        return (bool) preg_match('#^(https?://[^\s]+|mailto:[^\s@]+@[^\s@]+|tel:\+?[0-9\-\s()]+)$#i', $url);
    }

    /** Allow-list sanitiser: bold, italic, links, lists, paragraphs (and h2/h3 for legal pages). Everything else is removed. */
    public static function sanitize(string $html, bool $headings = false): string
    {
        $allowed = ['p', 'br', 'strong', 'em', 'b', 'i', 'ul', 'ol', 'li', 'a'] + ($headings ? [10 => 'h2', 11 => 'h3'] : []);
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('root');
        if (! $root) { return ''; }

        $walk = function (\DOMNode $node) use (&$walk, $allowed, $doc) {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof \DOMComment) { $node->removeChild($child); continue; }
                if (! $child instanceof \DOMElement) { continue; }
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math', 'template', 'noscript'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                $walk($child);
                if (! in_array($tag, $allowed, true)) {
                    while ($child->firstChild) { $node->insertBefore($child->firstChild, $child); }
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    if ($tag === 'a' && $attr->name === 'href' && self::safeUrl(trim($attr->value))) { continue; }
                    $child->removeAttribute($attr->name);
                }
                if ($tag === 'a') {
                    if (! $child->hasAttribute('href')) {
                        while ($child->firstChild) { $node->insertBefore($child->firstChild, $child); }
                        $node->removeChild($child);
                        continue;
                    }
                    $child->setAttribute('rel', 'noopener');
                }
            }
        };
        $walk($root);

        $out = '';
        foreach ($root->childNodes as $c) { $out .= $doc->saveHTML($c); }

        return trim($out);
    }

    public static function saveDraft(string $page, array $clean, User $by): void
    {
        $row = PageContent::firstOrCreate(['page' => $page]);
        $old = $row->draft;
        $row->update(['draft' => $clean, 'draft_by' => $by->id, 'draft_at' => now()]);
        Audit::record('content.draft_saved', $row, $old, $clean, $by->id);
    }

    public static function discardDraft(string $page, User $by): void
    {
        $row = PageContent::where('page', $page)->first();
        if ($row && $row->draft) {
            Audit::record('content.draft_discarded', $row, $row->draft, null, $by->id);
            $row->update(['draft' => null, 'draft_by' => null, 'draft_at' => null]);
        }
    }

    /** Publish the draft (or the given data), store a revision and clear the cache. */
    public static function publish(string $page, User $by, ?array $data = null, string $note = 'Published'): PageRevision
    {
        return DB::transaction(function () use ($page, $by, $data, $note) {
            $row = PageContent::firstOrCreate(['page' => $page]);
            $new = $data ?? $row->draft;
            abort_if($new === null, 422, 'There is no draft to publish.');
            $old = $row->published;
            $version = $row->version + 1;
            $row->update(['published' => $new, 'draft' => null, 'draft_by' => null, 'draft_at' => null, 'version' => $version, 'published_at' => now(), 'published_by' => $by->id]);
            $rev = PageRevision::create(['page' => $page, 'version' => $version, 'content' => array_replace(self::defaults($page), $new), 'note' => $note, 'created_by' => $by->id]);
            Audit::record('content.published', $row, $old, $new, $by->id);
            Cache::forget("content.published.$page");

            return $rev;
        });
    }

    public static function rollback(string $page, PageRevision $revision, User $by): PageRevision
    {
        abort_unless($revision->page === $page, 404);

        return self::publish($page, $by, $revision->content, 'Restored version '.$revision->version);
    }

    /** Back to the seeded text. Stored as a new published version, so it can be undone too. */
    public static function reset(string $page, User $by): PageRevision
    {
        return self::publish($page, $by, [], 'Reset to default text');
    }

    /** Media rows referenced by a page's working copy, keyed by id, for showing thumbnails in the editor. */
    public static function mediaLookup(string $page): array
    {
        $ids = [];
        $collect = function ($fields, $values) use (&$collect, &$ids) {
            foreach ($fields as $k => $f) {
                $v = $values[$k] ?? null;
                if ($f['type'] === 'image' && is_array($v) && ! empty($v['media'])) { $ids[] = (int) $v['media']; }
                if ($f['type'] === 'list' && is_array($v)) { foreach ($v as $row) { $collect($f['fields'], $row); } }
            }
        };
        $collect(self::fields($page), self::working($page));

        return Media::whereIn('id', $ids)->get()->keyBy('id')->map(fn ($m) => ['url' => $m->url(), 'alt' => $m->alt])->all();
    }

    public static function revisions(string $page, int $limit = 15)
    {
        return PageRevision::where('page', $page)->orderByDesc('version')->limit($limit)->get();
    }
}
