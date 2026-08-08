<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProgrammePartRequest;
use App\Http\Requests\Admin\UpdateProgrammePartRequest;
use App\Models\Programme;
use App\Models\ProgrammePart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Parts of a programme. No index of its own — parts are listed inline under their
 * programme on admin.programmes.index, mirroring how departments sit under faculties.
 */
class ProgrammePartController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', ProgrammePart::class);

        return view('admin.programme-parts.create', [
            'programmes' => Programme::query()->ordered()->get(),
            'selected' => request()->integer('programme'),
        ]);
    }

    public function store(StoreProgrammePartRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $programme = Programme::findOrFail($data['programme_id']);

        ProgrammePart::create([
            'programme_id' => $programme->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlugWithin($programme, $data['name']),
            'description' => $data['description'] ?? null,
            'credit_target' => $data['credit_target'] ?? null,
            'unlock_credits' => $data['unlock_credits'] ?? null,
            'position' => (int) $programme->parts()->max('position') + 1,
        ]);

        return redirect()
            ->route('admin.programmes.index')
            ->with('status', "“{$data['name']}” was added to {$programme->name}.");
    }

    public function edit(ProgrammePart $part): View
    {
        $this->authorize('update', $part);

        return view('admin.programme-parts.edit', [
            'part' => $part->load('programme'),
        ]);
    }

    public function update(UpdateProgrammePartRequest $request, ProgrammePart $part): RedirectResponse
    {
        $data = $request->validated();

        $part->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'credit_target' => $data['credit_target'] ?? null,
            'unlock_credits' => $data['unlock_credits'] ?? null,
        ]);

        return redirect()
            ->route('admin.programmes.index')
            ->with('status', 'Part updated.');
    }

    public function destroy(ProgrammePart $part): RedirectResponse
    {
        $this->authorize('delete', $part);

        if ($part->courses()->exists()) {
            return back()->with('error', 'That part still has courses in it. Remove them first.');
        }

        $name = $part->name;
        $part->delete();

        return redirect()
            ->route('admin.programmes.index')
            ->with('status', "“{$name}” deleted.");
    }

    /**
     * Part slugs are unique per programme rather than globally — every programme has a
     * "part-i" — so Slug::unique(), which scans a whole table, is the wrong tool here.
     */
    private function uniqueSlugWithin(Programme $programme, string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while ($programme->parts()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
