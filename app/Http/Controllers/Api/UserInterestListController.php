<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memberships;
use App\Models\ReferralCodes;
use App\Models\Referrals;
use App\Models\User;
use App\Models\UserInterestList;
use App\Models\UserMemberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendFoundingMemberQueuedEmail;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserInterestListController extends Controller
{
    public function registerInterestList(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'contact_no' => ['required', 'string', 'max:20'],
            'pet_type' => ['nullable', 'string', 'max:50'],
            'referral_code' => ['nullable', 'string', 'max:10'],
        ]);

        $email = strtolower(trim((string) $validated['email']));

        // check if email exist in user table
        $user = User::query()->where('email', $email)->first();
        if ($user) {
            return response()->json([
                'message' => 'Email already registered.',
            ], 400);
        }

        $checking = $this->startReferralProcedure([
            ...$validated,
            'email' => $email,
        ]);

        if (!$checking) {
            return response()->json([
                'data' => [
                    'error' => 'error',
                    'message' => 'Email is already registered or referral code not found',
                ]
            ], 400);
        }

        return response()->json([
            'data' => [
                'success' => 'success',
                'message' => 'Interest list registered.',
            ]
        ], 200);
    }

    public function getListCount(Request $request)
    {
        $count = UserInterestList::query()->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    public function startReferralProcedure(array $validated)
    {
        // check if email exist in user_interest_list table and user table
        $user = User::query()->where('email', $validated['email'])->first();
        if ($user) {
            return false;
        }

        $record = UserInterestList::query()->where('email', $validated['email'])->first();
        if ($record) {
            return false;
        }

        try {
            $record = UserInterestList::query()->firstOrCreate([
                'email' => $validated['email'],
            ], [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'contact_no' => $validated['contact_no'],
                'pet_type' => $validated['pet_type'] ?? 'Unknown',
                'referral_code' => $validated['referral_code'],
            ]);

            $this->mondayBoardEntry($validated);

            $privateLaunchDate = new DateTimeImmutable("2026-04-16");
            SendFoundingMemberQueuedEmail::dispatch(
                $validated['email'],
                trim($validated['first_name'] . ' ' . $validated['last_name']),
                $privateLaunchDate->format('d M Y')
            )->delay(now()->addMinute());

            return true;
        } catch (\Exception $e) {
            // log error
            logger()->error('Error while processing referral code: ' . $e->getMessage());
        }
        return false;
    }

    private function mondayBoardEntry($validated)
    {
        $token = config('services.monday.token');
        $apiUrl = 'https://api.monday.com/v2';

        $query = 'mutation ($item_name:String!, $columnVals: JSON!){ create_item (board_id: 18405155227, group_id: "topics", item_name: $item_name, column_values: $columnVals) { id } }';
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('UTC'));

        $vals = [
            "item_name" => $validated['first_name'],
            "columnVals" => json_encode(
                [
                    "long_text6y0no4si" => $validated['last_name'],
                    "phonexp82ux3l" => $validated['contact_no'],
                    "long_textpq35txo1" => $validated['email'],
                    "long_textt9jnajch" => $validated['referral_code'],
                    "text_mm1xfht6" => $validated['pet_type'] ?? null,
                ]
            )
        ];

        try {
            $guzzleClient = new Client(array('headers' => array('Content-Type' => 'application/json', 'Authorization' => $token)));
            $responseContent = $guzzleClient->post($apiUrl, ['body' =>  json_encode(['query' => $query, 'variables' => $vals])]);
            Log::info($query);
            Log::info($vals);
            $data = json_decode($responseContent->getBody()->getContents());
            if (isset($data->error_message)) {
                Log::error($data->error_message);
            } else {
                Log::info($responseContent->getBody());
            }
        } catch (Throwable $ex) {
            Log::error($ex);
        }
    }
}
