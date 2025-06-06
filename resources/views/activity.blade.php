@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\DB;

    $logs = DB::table('activity_log')
        ->leftJoin('users as c', function ($join) {
            $join->on('activity_log.causer_id', '=', 'c.id')
                 ->on('activity_log.causer_type', '=', 'App\Models\User');
        })
        ->leftJoin('users as s', function ($join) {
            $join->on('activity_log.subject_id', '=', 's.id')
                 ->on('activity_log.subject_type', '=', 'App\Models\User');
        })
        ->select('c.name as causer', 's.name as subject', 'activity_log.description', 'activity_log.created_at')
        ->where('activity_log.created_at', '>=', Carbon::now()->subMonth())
        ->orderBy('activity_log.created_at', 'desc')
        ->get();
@endphp

<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Activity Log</h1>
        </div>

        <div class="overflow-x-auto bg-white dark:bg-gray-800 shadow rounded-lg">
            <table class="min-w-full table-auto">
                <thead class="bg-gray-100 dark:bg-gray-700 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Date</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-800 dark:text-gray-200 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-3">{{ ($log->causer ?? $log->subject) ?? 'Unknown' }}</td>
                            <td class="px-4 py-3">{{ $log->description }}</td>
                            <td class="px-4 py-3">{{ \Carbon\Carbon::parse($log->created_at)->timezone('America/New_York')->format('Y-m-d h:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">No activity found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
