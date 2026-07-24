<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateLayout;
use App\Enums\MediaPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CertificateTemplateRequest;
use App\Models\CertificateTemplate;
use App\Services\Certificates\CertificateRenderer;
use App\Services\Certificates\CertificateTemplateService;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Template design admin: list, create, edit, and a live HTML preview rendered from the
 * exact same blade view the PDF uses (see CertificateRenderer), so what's previewed is
 * what prints. Signature images upload ahead of the form save (see uploadSignature) —
 * the edit form only ever posts the resulting Media id.
 */
class CertificateTemplateController extends Controller
{
    public function __construct(private readonly CertificateTemplateService $templates) {}

    public function index(): View
    {
        $this->authorize('viewAny', CertificateTemplate::class);

        return view('admin.certificate-templates.index', [
            'templates' => CertificateTemplate::query()
                ->withCount('courses')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CertificateTemplate::class);

        return view('admin.certificate-templates.edit', [
            'template' => new CertificateTemplate,
        ]);
    }

    public function store(CertificateTemplateRequest $request): RedirectResponse
    {
        $template = $this->templates->save(new CertificateTemplate, $request->validated());

        return redirect()
            ->route('admin.certificate-templates.edit', $template)
            ->with('status', "Template “{$template->name}” was created.");
    }

    public function edit(CertificateTemplate $certificateTemplate): View
    {
        $this->authorize('update', $certificateTemplate);

        return view('admin.certificate-templates.edit', [
            'template' => $certificateTemplate,
        ]);
    }

    public function update(CertificateTemplateRequest $request, CertificateTemplate $certificateTemplate): RedirectResponse
    {
        $this->templates->save($certificateTemplate, $request->validated());

        return redirect()
            ->route('admin.certificate-templates.edit', $certificateTemplate)
            ->with('status', 'Template saved.');
    }

    /**
     * Live preview, both variants (with/without the grade line) so a "show_grade" toggle
     * can be checked before saving. Rendered as plain HTML (not a PDF) for a fast,
     * in-browser look — the underlying view is the same one dompdf renders from.
     */
    public function preview(Request $request, CertificateTemplate $certificateTemplate, CertificateRenderer $renderer): View
    {
        $this->authorize('view', $certificateTemplate);

        // Unsaved edits in the form (layout/accent/show_grade/signatories) can be
        // previewed without saving first — the form posts them as query params. Each is
        // validated the same as a real save (CertificateTemplateRequest) rather than
        // trusted as-is: accent_color in particular is interpolated straight into a
        // <style> block by the PDF view, so an unvalidated value would be a CSS-injection
        // vector even though this route is admin-only.
        $request->validate([
            'layout' => ['nullable', 'string', Rule::in(CertificateLayout::values())],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'show_grade' => ['nullable', 'boolean'],
        ]);

        $draft = clone $certificateTemplate;
        if ($request->filled('layout')) {
            $draft->layout = CertificateLayout::from($request->string('layout')->value());
        }
        if ($request->filled('accent_color')) {
            $draft->config = array_merge($draft->config ?? [], ['accent_color' => $request->input('accent_color')]);
        }
        if ($request->has('show_grade')) {
            $draft->config = array_merge($draft->config ?? [], ['show_grade' => $request->boolean('show_grade')]);
        }

        return view('admin.certificate-templates.preview', [
            'template' => $draft,
            'withGradeHtml' => $renderer->renderPreviewHtml($draft, withGrade: true),
            'withoutGradeHtml' => $renderer->renderPreviewHtml($draft, withGrade: false),
        ]);
    }

    /**
     * AJAX signature-image upload — returns the Media id + a preview URL. Never
     * base64-inlined; goes through the canonical MediaUploadService (purpose
     * Signatures, a public image like an avatar/cover).
     */
    public function uploadSignature(Request $request, MediaUploadService $media): JsonResponse
    {
        $this->authorize('create', CertificateTemplate::class);

        $request->validate([
            'file' => ['required', 'file', 'image', 'max:1024'],
        ]);

        $uploaded = $media->upload($request->file('file'), MediaPurpose::Signatures);

        return response()->json(['id' => $uploaded->id, 'url' => $uploaded->url]);
    }
}
