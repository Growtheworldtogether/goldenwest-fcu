<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageContent;
use App\Models\PageRevision;
use App\Services\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SiteContentController extends Controller
{
    public function index()
    {
        Gate::authorize('staff.editSite');
        $rows = PageContent::all()->keyBy('page');

        return view('admin.content-index', ['pages' => SiteContent::pages(), 'rows' => $rows]);
    }

    public function edit(string $page)
    {
        Gate::authorize('staff.editSite');
        $def = SiteContent::page($page);
        $row = PageContent::where('page', $page)->first();

        return view('admin.content-edit', [
            'page' => $page, 'def' => $def, 'row' => $row,
            'values' => old('fields') ? null : SiteContent::working($page),
            'revisions' => SiteContent::revisions($page),
            'media' => SiteContent::mediaLookup($page),
        ]);
    }

    public function saveDraft(Request $request, string $page)
    {
        Gate::authorize('staff.editSite');
        SiteContent::page($page);
        $clean = SiteContent::clean($page, (array) $request->input('fields', []), $this->files($request), $request->user());
        SiteContent::saveDraft($page, $clean, $request->user());

        if ($request->input('then') === 'publish') {
            SiteContent::publish($page, $request->user());

            return redirect()->route('admin.site.edit', $page)->with('status', 'Published. Visitors see the new text now.');
        }

        return redirect()->route('admin.site.edit', $page)->with('status', 'Draft saved. Use the preview to check it before publishing.');
    }

    /** Files arrive as fields[key][file] or fields[list][i][_files][key]; hand them to the cleaner in the shape it expects. */
    private function files(Request $request): array
    {
        $out = [];
        foreach ((array) $request->file('fields', []) as $key => $value) {
            if (is_array($value) && isset($value['file'])) { $out[$key] = $value['file']; }
            elseif (is_array($value)) { $out[$key] = $value; }
        }

        return $out;
    }

    public function preview(string $page)
    {
        Gate::authorize('staff.editSite');
        $def = SiteContent::page($page);
        SiteContent::$preview = true;
        SiteContent::$previewPage = $page;
        $view = $def['view'] ?? 'pages.'.$page;
        $data = match ($page) {
            'rates' => ['rates' => \App\Services\SiteContent::resolve(config('site.rates'))],
            'learn' => ['articles' => \App\Services\SiteContent::resolve(config('articles'))],
            default => [],
        };

        return response(view($view, $data)->render())->header('X-Robots-Tag', 'noindex');
    }

    public function discard(Request $request, string $page)
    {
        Gate::authorize('staff.editSite');
        SiteContent::page($page);
        SiteContent::discardDraft($page, $request->user());

        return redirect()->route('admin.site.edit', $page)->with('status', 'Draft discarded.');
    }

    public function publish(Request $request, string $page)
    {
        Gate::authorize('staff.editSite');
        SiteContent::page($page);
        SiteContent::publish($page, $request->user());

        return redirect()->route('admin.site.edit', $page)->with('status', 'Published. Visitors see the new text now.');
    }

    public function rollback(Request $request, string $page, PageRevision $revision)
    {
        Gate::authorize('staff.editSite');
        SiteContent::page($page);
        SiteContent::rollback($page, $revision, $request->user());

        return redirect()->route('admin.site.edit', $page)->with('status', 'Restored version '.$revision->version.' and published it.');
    }

    public function reset(Request $request, string $page)
    {
        Gate::authorize('staff.editSite');
        SiteContent::page($page);
        SiteContent::reset($page, $request->user());

        return redirect()->route('admin.site.edit', $page)->with('status', 'Back to the original text. You can undo this from the version list.');
    }
}
