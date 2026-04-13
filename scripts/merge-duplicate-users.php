<?php

// Run with: php artisan tinker scripts/merge-duplicate-users.php

use App\Models\Order;
use App\Models\User;

$merges = [
    // [keep_id, delete_id]
    [294, 66],   // ziggythewizard - keep lowercase, merge orders from 66
    [282, 165],  // twistedart13 - keep lowercase with more orders, merge from 165
    [163, 283],  // butiful90lfe - keep 163 (has orders), delete 283 (no orders)
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

    // Move orders from delete → keep
    $moved = Order::where('user_id', $deleteId)->update(['user_id' => $keepId]);
    echo "  Moved {$moved} orders\n";

    // Move activity log entries
    $activities = DB::table('activity_log')
        ->where('causer_type', User::class)
        ->where('causer_id', $deleteId)
        ->update(['causer_id' => $keepId]);
    echo "  Moved {$activities} activity log entries\n";

    // Normalize email to lowercase on the kept user
    $keep->email = strtolower($keep->email);
    $keep->save();

    // Delete the duplicate
    $delete->delete();
    echo "  Deleted user {$deleteId}\n\n";
}

echo "Done. Verifying no duplicates remain:\n";
$remaining = User::selectRaw('LOWER(email) as e, COUNT(*) as c')
    ->groupByRaw('LOWER(email)')
    ->having('c', '>', 1)
    ->count();
echo "Duplicate groups: {$remaining}\n";
