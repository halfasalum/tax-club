<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Reward;
use App\Models\RewardRedemption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RewardController extends BaseController
{
    /**
     * GET /api/v1/rewards
     * Students see active rewards. Admins see all (incl. inactive).
     * Requires: auth:sanctum
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->hasAnyRole(['admin', 'super_admin']);

        $query = Reward::query()
            ->when(! $isAdmin, fn ($q) => $q->where('is_active', true))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->orderBy('points_cost');

        return $this->paginated($query->paginate($request->per_page ?? 20));
    }

    /**
     * GET /api/v1/rewards/{id}
     * Requires: auth:sanctum
     */
    public function show(string $id): JsonResponse
    {
        $reward = Reward::find($id);

        if (! $reward) {
            return $this->error('Reward not found.', 404);
        }

        return $this->success($reward);
    }

    /**
     * POST /api/v1/rewards
     * Requires: manage_rewards
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'type' => 'required|in:airtime,certificate,merchandise,recognition',
            'points_cost' => 'required|integer|min:1',
            'stock' => 'nullable|integer|min:0',
            'image_url' => 'nullable|url',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $reward = Reward::create($request->only([
            'title', 'description', 'type', 'points_cost', 'stock', 'image_url', 'is_active',
        ]));

        return $this->success($reward, 'Reward created successfully.', 201);
    }

    /**
     * PUT /api/v1/rewards/{id}
     * Requires: manage_rewards
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $reward = Reward::find($id);

        if (! $reward) {
            return $this->error('Reward not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:airtime,certificate,merchandise,recognition',
            'points_cost' => 'sometimes|integer|min:1',
            'stock' => 'nullable|integer|min:0',
            'image_url' => 'nullable|url',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $reward->fill($request->only([
            'title', 'description', 'type', 'points_cost', 'stock', 'image_url', 'is_active',
        ]))->save();

        return $this->success($reward, 'Reward updated successfully.');
    }

    /**
     * DELETE /api/v1/rewards/{id}
     * Requires: manage_rewards
     */
    public function destroy(string $id): JsonResponse
    {
        $reward = Reward::withCount('redemptions')->find($id);

        if (! $reward) {
            return $this->error('Reward not found.', 404);
        }

        if ($reward->redemptions_count > 0) {
            return $this->error('Cannot delete a reward that has redemption history. Deactivate it instead.', 409);
        }

        $reward->delete();

        return $this->success(null, 'Reward deleted successfully.');
    }

    /**
     * PATCH /api/v1/rewards/{id}/toggle
     * Requires: manage_rewards
     */
    public function toggle(string $id): JsonResponse
    {
        $reward = Reward::find($id);

        if (! $reward) {
            return $this->error('Reward not found.', 404);
        }

        $reward->is_active = ! $reward->is_active;
        $reward->save();

        $state = $reward->is_active ? 'activated' : 'deactivated';

        return $this->success($reward, "Reward {$state} successfully.");
    }

    /**
     * POST /api/v1/rewards/{id}/redeem
     * Member redeems a reward with points. Requires: auth:sanctum
     */
    public function redeem(Request $request, string $id): JsonResponse
    {
        $reward = Reward::find($id);

        if (! $reward || ! $reward->is_active) {
            return $this->error('Reward not available.', 404);
        }

        if (! $reward->isInStock()) {
            return $this->error('This reward is out of stock.', 409);
        }

        $user = $request->user();

        if ($user->total_points < $reward->points_cost) {
            return $this->error("You need {$reward->points_cost} points to redeem this reward. You have {$user->total_points}.", 422);
        }

        DB::beginTransaction();

        try {
            $user->deductPoints($reward->points_cost, "Redeemed reward: {$reward->title}");

            if ($reward->stock !== null) {
                $reward->decrement('stock');
            }

            $redemption = RewardRedemption::create([
                'user_id' => $user->user_id,
                'reward_id' => $reward->reward_id,
                'points_spent' => $reward->points_cost,
                'status' => 'pending',
            ]);

            DB::commit();

            return $this->success([
                'redemption' => $redemption,
                'remaining_points' => $user->total_points,
                'tier' => $user->membershipTier(),
            ], 'Reward redeemed! Your request is pending fulfillment.', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to redeem reward: '.$e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/rewards/my-redemptions
     * Requires: auth:sanctum
     */
    public function myRedemptions(Request $request): JsonResponse
    {
        $redemptions = RewardRedemption::with('reward:reward_id,title,type,image_url')
            ->where('user_id', $request->user()->user_id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($redemptions);
    }

    /**
     * GET /api/v1/rewards/redemptions
     * Admin view of all redemption requests. Requires: process_redemptions
     */
    public function redemptions(Request $request): JsonResponse
    {
        $query = RewardRedemption::with([
            'user:user_id,full_name,membership_id,email',
            'reward:reward_id,title,type,points_cost',
        ])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc');

        return $this->paginated($query->paginate($request->per_page ?? 20));
    }

    /**
     * PATCH /api/v1/rewards/redemptions/{redemptionId}/status
     * Admin approves/fulfills/rejects a redemption. Rejecting refunds the
     * points back to the member. Requires: process_redemptions
     */
    public function updateRedemptionStatus(Request $request, string $redemptionId): JsonResponse
    {
        $redemption = RewardRedemption::with('user', 'reward')->find($redemptionId);

        if (! $redemption) {
            return $this->error('Redemption not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,fulfilled,rejected',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        if (in_array($redemption->status, ['rejected', 'fulfilled'])) {
            return $this->error("This redemption is already {$redemption->status} and cannot be changed.", 409);
        }

        DB::beginTransaction();

        try {
            if ($request->status === 'rejected') {
                $redemption->user->addPoints($redemption->points_spent, "Refund: rejected redemption of {$redemption->reward->title}");

                if ($redemption->reward->stock !== null) {
                    $redemption->reward->increment('stock');
                }
            }

            $redemption->status = $request->status;
            $redemption->notes = $request->notes;
            $redemption->processed_by = $request->user()->user_id;
            $redemption->processed_at = now();
            $redemption->save();

            DB::commit();

            return $this->success($redemption->fresh(['user', 'reward']), 'Redemption status updated.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to update redemption: '.$e->getMessage(), 500);
        }
    }
}
