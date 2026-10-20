<?php

namespace Tests\Feature;

use App\Livewire\Admin\Questions\Show;
use App\Livewire\Member\Ask\Index;
use App\Models\Question;
use App\Models\User;
use App\Notifications\QuestionAnswered;
use App\Notifications\QuestionAssigned;
use App\Support\AskTra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class AskTraTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    public function test_question_is_assigned_answered_reviewed_delivered_and_published(): void
    {
        Notification::fake();
        $member = User::factory()->create(['full_name' => 'Rehema Juma']);
        $triage = $this->officerWith(['triage_questions']);
        $expert = $this->officerWith(['answer_questions']);
        $reviewer = $this->officerWith(['review_answers']);

        Livewire::actingAs($member)->test(Index::class)
            ->set('subject', 'Do students need a TIN to sell snacks?')
            ->set('body', 'I want to sell snacks at school on Fridays. Do I need to register for a TIN?')
            ->call('ask')
            ->assertRedirect(route('member.ask.show', 'AQ-'.now()->format('Y').'-00001'));

        $question = Question::firstOrFail();

        Livewire::actingAs($triage)->test(Show::class, ['question' => $question])
            ->set('expert', $expert->user_id)
            ->call('assign')
            ->assertSee('Assigned.');
        Notification::assertSentTo($expert, QuestionAssigned::class);

        Livewire::actingAs($expert)->test(Show::class, ['question' => $question->fresh()])
            ->set('answer', 'Anyone who runs a business, even a small one, should register for a TIN with TRA.')
            ->call('saveAnswer', true)
            ->assertSee('Sent for review.');

        $this->expectsSelfReviewToFail($question->fresh(), $expert);

        Livewire::actingAs($reviewer)->test(Show::class, ['question' => $question->fresh()])
            ->set('publish', true)
            ->set('publicQuestion', 'Do students need a TIN to sell snacks at school?')
            ->call('approve')
            ->assertSee('Approved, sent to the member and published.');

        $question->refresh();
        $this->assertSame(Question::ANSWERED, $question->status);
        $this->assertNotNull($question->published_at);
        Notification::assertSentTo($member, QuestionAnswered::class);

        $this->get('/en/answers')->assertOk()->assertSee('Do students need a TIN to sell snacks at school?');
        $this->get('/en/answers/'.$question->slug)->assertOk()->assertSee('"@type":"FAQPage"', false)->assertDontSee('Rehema');
    }

    private function expectsSelfReviewToFail(Question $question, User $expert): void
    {
        try {
            AskTra::approve($question, $expert);
            $this->fail('Experts must not approve their own answers.');
        } catch (InvalidArgumentException) {
            $this->assertSame(Question::IN_REVIEW, $question->fresh()->status);
        }
    }

    public function test_private_questions_are_never_published(): void
    {
        $member = User::factory()->create();
        $question = AskTra::ask($member, ['subject' => 'My family business tax', 'body' => 'Private details about our shop and its income.', 'allow_publish' => false]);
        $expert = $this->officerWith(['answer_questions']);
        AskTra::assign($question, $expert, $expert);
        AskTra::saveAnswer($question->fresh(), 'Please visit your nearest TRA office with your records.', $expert, true);
        AskTra::approve($question->fresh(), $this->officerWith(['review_answers']), publish: true);

        $this->assertNull($question->fresh()->published_at);
    }

    public function test_declined_questions_tell_the_member_why(): void
    {
        Notification::fake();
        $member = User::factory()->create();
        $question = AskTra::ask($member, ['subject' => 'Best football team?', 'body' => 'Which football team is the best in Tanzania?']);
        AskTra::decline($question, 'Ask TRA answers tax questions only.', $this->officerWith(['triage_questions']));

        $this->assertSame(Question::DECLINED, $question->fresh()->status);
        Notification::assertSentTo($member, QuestionAnswered::class, fn ($notification) => $notification->declined);
        $this->actingAs($member)->get(route('member.ask.show', $question->reference).'?lang=en')->assertSee('Ask TRA answers tax questions only.');
    }

    public function test_daily_limit_and_restrictions(): void
    {
        $member = User::factory()->create();

        for ($i = 0; $i < AskTra::PER_DAY; $i++) {
            AskTra::ask($member, ['subject' => 'Question number '.$i, 'body' => 'Some details about question '.$i]);
        }

        $this->expectException(InvalidArgumentException::class);
        AskTra::ask($member, ['subject' => 'One too many', 'body' => 'This should be refused today.']);
    }

    public function test_screens_render(): void
    {
        $officer = $this->officerWith(['triage_questions', 'answer_questions', 'review_answers']);
        $question = AskTra::ask(User::factory()->create(), ['subject' => 'What is VAT exactly?', 'body' => 'Please explain VAT in simple words.']);

        $this->actingAs($officer)->get('/admin/questions?lang=en')->assertOk()->assertSee('What is VAT exactly?');
        $this->actingAs($officer)->get('/admin/questions/'.$question->question_id.'?lang=en')->assertOk()->assertSee('Triage');
        $this->actingAs(User::factory()->create())->get('/app/ask?lang=en')->assertOk()->assertSee('Ask a question');
    }
}
