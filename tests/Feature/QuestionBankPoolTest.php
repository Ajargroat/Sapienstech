<?php

namespace Tests\Feature;

use App\Console\Commands\ImportQuestionBank;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The question bank is a shared pool: questions/answers carry a NULL tenant_id
 * and SharedAcrossTenants lets every tenant read the NULL rows plus its own
 * leftovers (TASK AC8, PREFERENCE §5: no per-tenant copies).
 *
 * The important half of this file is what it does NOT relax: tenant-owned rows
 * stay invisible to everyone else. Isolation is loosened for the pool only.
 */
class QuestionBankPoolTest extends TestCase
{
    use RefreshDatabase;

    private function asTenant(Tenant $tenant): void
    {
        app()->instance('tenant', $tenant);
    }

    private function pooledQuestion(string $text): Question
    {
        $question = Question::create([
            'tenant_id' => null,
            'question_text' => $text,
            'question_type' => 'multiple_choice',
            'difficulty' => 'Easy',
            'subject' => 'شیمی',
            'is_active' => true,
        ]);

        Answer::create([
            'tenant_id' => null,
            'question_id' => $question->id,
            'answer_text' => 'گزینهٔ صحیح',
            'is_correct' => true,
        ]);

        return $question;
    }

    public function test_every_tenant_reads_the_shared_pool(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->asTenant($tenantA);
        $question = $this->pooledQuestion('سوال مشترک');
        $id = $question->id;

        // Tenant B sees the row it never created.
        $this->asTenant($tenantB);
        $this->assertTrue(Question::whereKey($id)->exists());
        $this->assertSame(1, Answer::where('question_id', $id)->count());

        // …and so does tenant A.
        $this->asTenant($tenantA);
        $this->assertTrue(Question::whereKey($id)->exists());
    }

    public function test_a_tenant_owned_question_stays_private(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->asTenant($tenantA);
        $owned = Question::create([
            'tenant_id' => $tenantA->id,
            'question_text' => 'سوال اختصاصی',
            'question_type' => 'multiple_choice',
            'difficulty' => 'Easy',
        ]);

        // The scope unions NULL with *my own* tenant id — not everyone's.
        $this->assertTrue(Question::whereKey($owned->id)->exists());

        $this->asTenant($tenantB);
        $this->assertFalse(Question::whereKey($owned->id)->exists());
    }

    public function test_the_pool_is_visible_without_a_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        $this->asTenant($tenant);
        $id = $this->pooledQuestion('سوال بدون زمینه')->id;

        // No tenant bound (console commands, seeders): the scope must not throw
        // and must still expose the pool.
        app()->forgetInstance('tenant');
        $this->assertTrue(Question::whereKey($id)->exists());
    }

    public function test_the_importer_targets_the_pool_not_a_tenant(): void
    {
        $signature = app(ImportQuestionBank::class)->getDefinition();

        // No --tenant: the bank is one pool, so there is nothing to scope.
        $this->assertFalse($signature->hasOption('tenant'));
        $this->assertTrue($signature->hasOption('limit'));
        $this->assertTrue($signature->hasOption('bbox-mode'));

        // The old per-tenant importer is gone, not merely unused.
        $this->assertFileDoesNotExist(app_path('Console/Commands/ImportSupabaseQuestions.php'));
    }
}
