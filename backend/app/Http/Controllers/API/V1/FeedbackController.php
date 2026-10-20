<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Feedback;
use App\Support\FeedbackDesk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Member feedback from the mobile app (FBK-REQ01, FBK-REQ04).
 */
class FeedbackController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        return $this->success(Feedback::where('user_id', $request->user()->user_id)->latest()->limit(50)->get()
            ->map(fn (Feedback $feedback) => $this->present($feedback)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => 'required|in:'.implode(',', Feedback::CATEGORIES),
            'subject' => 'required|string|min:4|max:200',
            'message' => 'required|string|min:10|max:5000',
            'related_type' => 'nullable|in:event,lesson,quiz,opportunity',
            'related_id' => 'nullable|string|max:40',
        ]);

        try {
            $feedback = FeedbackDesk::submit($request->user(), [...$data, 'channel' => 'app']);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 429);
        }

        return $this->success($this->present($feedback), 'Feedback received.', 201);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $feedback = Feedback::where('reference', $reference)->where('user_id', $request->user()->user_id)->first();

        if (! $feedback) {
            return $this->error('Feedback not found.', 404);
        }

        return $this->success([
            ...$this->present($feedback),
            'message' => $feedback->message,
            'response' => $feedback->response,
            'responded_at' => $feedback->responded_at?->toIso8601String(),
            'history' => FeedbackDesk::publicHistory($feedback)->map(fn ($step) => [
                'action' => $step->action,
                'note' => $step->action === 'member_message' ? $step->note : null,
                'at' => $step->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function message(Request $request, string $reference): JsonResponse
    {
        $feedback = Feedback::where('reference', $reference)->where('user_id', $request->user()->user_id)->first();

        if (! $feedback) {
            return $this->error('Feedback not found.', 404);
        }

        $request->validate(['message' => 'required|string|max:5000']);

        try {
            FeedbackDesk::addMemberMessage($feedback, $request->user(), $request->string('message')->toString());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(null, 'Message added.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Feedback $feedback): array
    {
        return [
            'reference' => $feedback->reference,
            'category' => $feedback->category,
            'subject' => $feedback->subject,
            'status' => $feedback->status,
            'status_label' => FeedbackDesk::statusLabels()[$feedback->status] ?? $feedback->status,
            'created_at' => $feedback->created_at?->toIso8601String(),
        ];
    }
}
