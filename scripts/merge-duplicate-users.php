<?php

// Run with: php artisan tinker scripts/merge-duplicate-users.php

use App\Models\Order;
use App\Models\User;

$merges = [
    // [keep_id, delete_id]
    [294, 66],   // ziggythewizard - keep lowercase, merge orders from 66
    [282, 165],  // twistedart13 - keep lowercase with more orders, merge from 165
    [163, 283],  // butiful90lfe - keep 163 (has orders), delete 283 (no orders)
    [299, 41],   // Tour de Puke - keep 299 (more recent activity, different emails)
    [298, 293],  // Tongue Twizzler - keep 298 (has order), merge 293 (no orders)
    [302, 101],  // UpperCunt - keep 302 (newer, has phone), merge 101 (has kennel/nerd_name)
];

foreach ($merges as [$keepId, $deleteId]) {
    $keep = User::find($keepId);
    $delete = User::find($deleteId);

    if (! $keep || ! $delete) {
        echo "SKIP: user {$keepId} or {$deleteId} not found\n";

        continue;
    }

    echo "Merging '{$delete->name}' (ID {$deleteId}) into '{$keep->name}' (ID {$keepId})\n";
    echo "  Email: {$delete->email} → {$keep->email}\n";

    // Delete duplicate orders where both users have an order for the same event
    // Keep the one on the kept user, delete the one on the duplicate user
    $keepEventIds = Order::where('user_id', $keepId)->pluck('event_id');
    $conflicting = Order::where('user_id', $deleteId)
        ->whereIn('event_id', $keepEventIds)
        ->get();

    foreach ($conflicting as $dupeOrder) {
        echo "  Deleting conflicting order {$dupeOrder->id} (event={$dupeOrder->event_id} status={$dupeOrder->status->value})\n";
        $dupeOrder->delete();
    }

    // Move remaining orders from delete → keep
    $moved = Order::where('user_id', $deleteId)->update(['user_id' => $keepId]);
    echo "  Moved {$moved} orders\n";

    // Move activity log entries
    $activities = DB::table('activity_log')
        ->where('causer_type', User::class)
        ->where('causer_id', $deleteId)
        ->update(['causer_id' => $keepId]);
    echo "  Moved {$activities} activity log entries\n";

    // Delete the duplicate first so the email unique constraint doesn't conflict
    $delete->delete();
    echo "  Deleted user {$deleteId}\n";

    // Normalize email to lowercase on the kept user
    $keep->email = strtolower($keep->email);
    $keep->save();
    echo "  Normalized email to {$keep->email}\n\n";
}

echo "Done. Verifying no duplicates remain:\n";
$remaining = User::selectRaw('LOWER(email) as e, COUNT(*) as c')
    ->groupByRaw('LOWER(email)')
    ->having('c', '>', 1)
    ->count();
echo "Duplicate groups: {$remaining}\n\n";

// Normalize all remaining emails to lowercase
$normalized = DB::table('users')
    ->whereRaw('email != LOWER(email)')
    ->update(['email' => DB::raw('LOWER(email)')]);
echo "Normalized {$normalized} email addresses to lowercase\n";
