<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventTrack;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Lets event attendees upload one GPX track each. Re-uploading replaces the
 * previous track (unique on event_id + user_id).
 */
class GpxTrack extends Component
{
    use WithFileUploads;

    public int $eventId;

    public ?TemporaryUploadedFile $gpx = null;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function save(): void
    {
        $this->validate(['gpx' => ['required', 'file', 'max:10240', 'extensions:gpx']]);

        $user = Auth::user();
        abort_unless($user instanceof User && $this->canUpload($user, $this->event()), 403);

        abort_if($this->gpx === null, 422);

        $points = EventTrack::pointsFrom((string) file_get_contents($this->gpx->getRealPath()));

        if (count($points) < 2) {
            $this->addError('gpx', 'No track points found. Is that a valid GPX file?');

            return;
        }

        $track = EventTrack::firstOrNew([
            'event_id' => $this->eventId,
            'user_id' => $user->id,
        ]);

        $oldPath = $track->path;

        $track->fill([
            'name' => $this->gpx->getClientOriginalName(),
            'path' => $this->gpx->store('gpx/'.$this->eventId, 'public'),
            'size' => (int) $this->gpx->getSize(),
            'points' => $points,
            'points_overview' => EventTrack::overview($points),
        ])->save();

        if ($oldPath && $oldPath !== $track->path) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->reset('gpx');
    }

    public function delete(int $trackId): void
    {
        $track = EventTrack::with('event')->findOrFail($trackId);
        $user = Auth::user();

        abort_unless($user instanceof User && $this->canDelete($user, $track), 403);

        $track->delete();
    }

    public function render(): View
    {
        $event = $this->event();
        $user = Auth::user();

        return view('livewire.gpx-track', [
            'tracks' => $event->tracks()->with('user')->orderBy('id')->get(),
            'canUpload' => $user instanceof User && $this->canUpload($user, $event),
            'canDelete' => fn (EventTrack $track): bool => $user instanceof User && $this->canDelete($user, $track),
        ]);
    }

    /**
     * Removing a track is narrower than adding one: the person who uploaded it,
     * the host, or an admin — a run-mate cannot delete someone else's trail.
     */
    private function canDelete(User $user, EventTrack $track): bool
    {
        return $user->is_admin
            || (int) $track->user_id === $user->id
            || (int) $track->event->created_by === $user->id;
    }

    private function event(): Event
    {
        return Event::findOrFail($this->eventId);
    }

    /**
     * Anyone logged in may upload once the hash has started — no rego needed,
     * so a visitor who ran with the pack can still add the trail. Admins skip
     * the date gate to prepare a map ahead of time.
     */
    private function canUpload(User $user, Event $event): bool
    {
        return $user->is_admin || $event->starts_at->isPast();
    }
}
