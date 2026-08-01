<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JeevalinkIdService
{
    /**
     * Generate a unique ID for a user.
     * Format: JL-{ROLE}-{PLACE}-{SEQUENCE}
     *
     * @param User $user
     * @return string
     */
    public function generateId(User $user): string
    {
        $roleCode = $this->getRoleCode($user->role);
        $placeName = $this->getPlaceNameForRole($user);
        $placeCode = $this->getOrCreatePlaceCode($placeName, $this->getPlaceTypeForRole($user->role));

        // Use a transaction to securely fetch and increment the sequence
        return DB::transaction(function () use ($roleCode, $placeCode) {
            // Find or create the sequence record for this role+place combo
            $sequenceRecord = DB::table('jeevalink_id_sequences')
                ->where('role_code', $roleCode)
                ->where('place_code', $placeCode)
                ->lockForUpdate()
                ->first();

            if (!$sequenceRecord) {
                // If it doesn't exist, we start at sequence 9 (so the first generated is 10)
                $nextSequence = 10;
                DB::table('jeevalink_id_sequences')->insert([
                    'role_code' => $roleCode,
                    'place_code' => $placeCode,
                    'latest_sequence' => $nextSequence,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $nextSequence = $sequenceRecord->latest_sequence + 1;
                DB::table('jeevalink_id_sequences')
                    ->where('id', $sequenceRecord->id)
                    ->update([
                        'latest_sequence' => $nextSequence,
                        'updated_at' => now(),
                    ]);
            }

            $paddedSequence = str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
            return "JL-{$roleCode}-{$placeCode}-{$paddedSequence}";
        });
    }

    /**
     * Determine the role code.
     */
    private function getRoleCode(string $role): string
    {
        return match ($role) {
            'super_admin' => 'SD',
            'block_admin' => 'BA',
            'volunteer' => 'VO',
            default => 'US', // user, technical_admin, unit_squad, etc. fallback to US
        };
    }

    /**
     * Determine the place name based on the role.
     */
    private function getPlaceNameForRole(User $user): string
    {
        return match ($user->role) {
            'super_admin' => $user->district ?: 'Unknown District',
            'block_admin' => $user->city ?: ($user->district ?: 'Unknown Block'),
            'volunteer' => $user->city ?: 'Unknown Ward',
            default => $user->pincode ?: ($user->city ?: 'Unknown Locality'),
        };
    }

    /**
     * Determine the place type based on the role.
     */
    private function getPlaceTypeForRole(string $role): string
    {
        return match ($role) {
            'super_admin' => 'district',
            'block_admin' => 'block',
            'volunteer' => 'ward',
            default => 'locality',
        };
    }

    /**
     * Get or create a 3-letter code for the given place name.
     */
    private function getOrCreatePlaceCode(string $placeName, string $placeType): string
    {
        $placeName = trim($placeName);
        
        $placeRecord = DB::table('place_codes')
            ->whereRaw('LOWER(place_name) = ?', [strtolower($placeName)])
            ->first();

        if ($placeRecord) {
            return $placeRecord->code;
        }

        // If not found, we must generate a unique 3-letter code
        $code = $this->generateUniquePlaceCode($placeName);

        DB::table('place_codes')->insert([
            'place_name' => $placeName,
            'place_type' => $placeType,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $code;
    }

    /**
     * Generate a unique 3-letter uppercase code.
     */
    private function generateUniquePlaceCode(string $placeName): string
    {
        $cleanName = strtoupper(preg_replace('/[^A-Z]/i', '', $placeName));
        if (empty($cleanName)) {
            $cleanName = 'UNK';
        }
        
        // Strategy 1: First letter + 2 consonants
        $firstLetter = substr($cleanName, 0, 1);
        $consonants = preg_replace('/[AEIOU]/', '', substr($cleanName, 1));
        
        $baseCode = $firstLetter . substr($consonants . 'XXX', 0, 2);
        if ($this->isCodeUnique($baseCode)) {
            return $baseCode;
        }

        // Strategy 2: First 3 letters
        $baseCode = substr($cleanName . 'XXX', 0, 3);
        if ($this->isCodeUnique($baseCode)) {
            return $baseCode;
        }

        // Strategy 3: Random letters until unique
        do {
            $code = $firstLetter . chr(rand(65, 90)) . chr(rand(65, 90));
        } while (!$this->isCodeUnique($code));

        return $code;
    }

    /**
     * Check if a generated code is already used.
     */
    private function isCodeUnique(string $code): bool
    {
        return !DB::table('place_codes')->where('code', $code)->exists();
    }
}
