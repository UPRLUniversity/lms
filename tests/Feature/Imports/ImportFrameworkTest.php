<?php

namespace Tests\Feature\Imports;

use App\Enums\Role;
use App\Imports\UserImport;
use App\Support\Import\ImportRunner;
use App\Support\Import\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The generic half of every import: reading the spreadsheet, staging it, and the
 * upload→preview→confirm flow. Uses the people importer as its vehicle because it needs
 * no scope, but everything asserted here is shared by all four.
 */
class ImportFrameworkTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('people.csv', $body);
    }

    public function test_columns_are_matched_by_heading_not_position(): void
    {
        $reader = app(SpreadsheetReader::class);
        $path = $this->stageFile("role,email,name\nstudent,a@uprl.test,Ada Lovelace\n");

        $rows = $reader->read($path, (new UserImport)->columns());

        // Reordered columns must still land in the right fields — a positional reader
        // would have imported "student" as the name here.
        $this->assertSame('Ada Lovelace', $rows[0]->get('name'));
        $this->assertSame('a@uprl.test', $rows[0]->get('email'));
        $this->assertSame('student', $rows[0]->get('role'));
    }

    public function test_headings_match_loosely_across_case_and_separators(): void
    {
        $reader = app(SpreadsheetReader::class);
        $path = $this->stageFile("Full Name,E-mail,ROLE\nAda Lovelace,a@uprl.test,student\n");

        $rows = $reader->read($path, (new UserImport)->columns());

        $this->assertSame('Ada Lovelace', $rows[0]->get('name'), 'the "Full name" label should resolve to the name column');
        $this->assertSame('student', $rows[0]->get('role'));
    }

    public function test_the_literal_word_false_survives_the_reader(): void
    {
        // Regression: the spreadsheet layer casts the text "false" to a boolean, and
        // treating boolean false as "no cell" silently emptied the answer column of
        // every false answer in a true/false question sheet.
        $reader = app(SpreadsheetReader::class);
        $path = $this->stageFile("name,email,role\nfalse,a@uprl.test,student\n");

        $rows = $reader->read($path, (new UserImport)->columns());

        $this->assertSame('false', $rows[0]->get('name'));
    }

    public function test_a_file_missing_a_required_column_is_refused_by_name(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $response = $this->actingAs($admin)->post(
            route('admin.imports.preview', ['import' => 'users']),
            ['file' => $this->csv("name,role\nAda Lovelace,student\n")],
        );

        $response->assertRedirect(route('admin.imports.create', ['import' => 'users']));
        $this->assertStringContainsString('Email', session('error'));
    }

    public function test_an_unreadable_file_is_refused_without_a_preview(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $response = $this->actingAs($admin)->post(
            route('admin.imports.preview', ['import' => 'users']),
            ['file' => $this->csv('')],
        );

        $response->assertRedirect();
        $this->assertNotNull(session('error'));
    }

    public function test_preview_writes_nothing(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($admin)->post(
            route('admin.imports.preview', ['import' => 'users']),
            ['file' => $this->csv("name,email,role\nAda Lovelace,ada@uprl.test,student\n")],
        )->assertOk();

        // THE property of the preview step: look without committing.
        $this->assertDatabaseMissing('users', ['email' => 'ada@uprl.test']);
    }

    public function test_confirm_imports_the_previewed_rows(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $preview = $this->actingAs($admin)->post(
            route('admin.imports.preview', ['import' => 'users']),
            ['file' => $this->csv("name,email,role\nAda Lovelace,ada@uprl.test,student\n")],
        );

        $token = $preview->viewData('token');

        $this->actingAs($admin)
            ->post(route('admin.imports.store', ['import' => 'users']), ['token' => $token])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'ada@uprl.test']);
    }

    public function test_a_forged_token_cannot_name_a_file_outside_the_staging_directory(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $response = $this->actingAs($admin)->post(
            route('admin.imports.store', ['import' => 'users']),
            ['token' => '../../../../.env'],
        );

        $response->assertRedirect(route('admin.imports.create', ['import' => 'users']));
        $this->assertStringContainsString('expired', session('error'));
    }

    public function test_an_expired_token_sends_the_admin_back_to_upload(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($admin)->post(
            route('admin.imports.store', ['import' => 'users']),
            ['token' => '3f0d4a6e-1c2b-4d5e-8f9a-0b1c2d3e4f5a.csv'],
        )->assertRedirect(route('admin.imports.create', ['import' => 'users']));
    }

    public function test_an_unknown_importer_is_a_404(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($admin)->get('/admin/imports/salaries')->assertNotFound();
    }

    public function test_the_template_carries_the_headings_the_reader_expects(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $body = $this->actingAs($admin)
            ->get(route('admin.imports.template', ['import' => 'users']))
            ->streamedContent();

        foreach ((new UserImport)->columns() as $column) {
            $this->assertStringContainsString($column->key, $body);
        }
    }

    public function test_the_template_round_trips_through_the_reader(): void
    {
        // The strongest guarantee this framework can offer: whatever a definition
        // publishes as its template must be a file that definition can read back.
        $definition = new UserImport;
        $runner = app(ImportRunner::class);
        $actor = $this->userWithRole(Role::SuperAdmin->value);

        $path = $this->stageFile($runner->template($definition));
        $report = $runner->analyse($definition, $path, null, $actor);

        $this->assertSame(3, $report['counts']['total']);
        $this->assertSame(3, $report['counts']['valid'], 'the sample rows in a template must themselves be importable');
    }

    public function test_a_queued_import_judges_rows_against_the_uploader_not_the_session(): void
    {
        // The queue has no authenticated user. A definition asking Gate for "the current
        // user" there would refuse every row — so the actor travels explicitly.
        $definition = new UserImport;
        $runner = app(ImportRunner::class);
        $actor = $this->userWithRole(Role::SuperAdmin->value);

        $path = $this->stageFile("name,email,role\nAda Lovelace,ada@uprl.test,student\n");

        // Deliberately NOT acting as anyone.
        $this->assertGuest();

        $report = $runner->analyse($definition, $path, null, $actor);

        $this->assertSame(1, $report['counts']['valid']);
    }

    public function test_an_admin_cannot_mint_a_super_admin_through_an_import(): void
    {
        $definition = new UserImport;
        $runner = app(ImportRunner::class);
        $admin = $this->userWithRole(Role::Admin->value);

        $path = $this->stageFile("name,email,role\nSneaky,sneaky@uprl.test,super-admin\nFine,fine@uprl.test,student\n");

        $report = $runner->analyse($definition, $path, null, $admin);

        $this->assertSame(UserImport::FORBIDDEN_ROLE, $report['rows'][0]->problem);
        $this->assertTrue($report['rows'][1]->isOk(), 'the legitimate row must still import');
    }

    /** Write content to a temp file and return its path. */
    private function stageFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        file_put_contents($path, $contents);

        return $path;
    }
}
