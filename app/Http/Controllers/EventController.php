<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Member;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    /** Restituisce il limite upload PHP (upload_max_filesize) in KB per la validazione Laravel. */
    private static function maxUploadKb(): int
    {
        $val = trim(ini_get('upload_max_filesize') ?: '2M');
        $unit = strtolower(substr($val, -1));
        $num = (int) $val;
        if ($unit === 'g') {
            $num *= 1024 * 1024;
        } elseif ($unit === 'm') {
            $num *= 1024;
        } elseif ($unit === 'k') {
            // già in KB
        } else {
            $num = (int) floor($num / 1024);
        }
        return max(1, $num);
    }

    /** Restituisce il limite upload in formato leggibile (es. "8 MB"). */
    private static function maxUploadLabel(): string
    {
        $val = trim(ini_get('upload_max_filesize') ?: '2M');
        return $val;
    }

    public function index(Request $request)
    {
        $query = Event::withCount('registrations')->orderByDesc('start_at');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('from')) {
            $query->whereDate('start_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('start_at', '<=', $request->to);
        }

        $events = $query->paginate(15)->withQueryString();

        return Inertia::render('Events/Index', [
            'events' => $events,
            'filters' => $request->only('search', 'from', 'to'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Events/Create', [
            'max_poster_size' => self::maxUploadLabel(),
        ]);
    }

    public function store(Request $request, AttachmentService $attachmentService)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'max_participants' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'solo_soci' => 'nullable|boolean',
            'poster' => 'nullable|file|image|max:' . self::maxUploadKb(),
        ]);
        $data['max_participants'] = $request->filled('max_participants') ? (int) $data['max_participants'] : null;
        $data['solo_soci'] = (bool) ($data['solo_soci'] ?? false);
        unset($data['poster']);
        $event = Event::create($data);
        if ($request->hasFile('poster')) {
            $attachmentService->store($request->file('poster'), $event, Event::POSTER_TAG);
        }
        return redirect()->route('events.index')->with('flash', ['type' => 'success', 'message' => 'Evento creato.']);
    }

    public function show(Event $event)
    {
        $event->load(['registrations.member']);
        $poster = $event->posterAttachment();
        $eventData = $event->toArray();
        $eventData['poster'] = $poster ? [
            'id' => $poster->id,
            'url' => $poster->url(),
            'original_name' => $poster->original_name,
        ] : null;
        $registeredIds = $event->registrations->pluck('member_id')->filter()->values()->all();
        $members = Member::when($registeredIds !== [], fn ($q) => $q->whereNotIn('id', $registeredIds))
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get(['id', 'cognome', 'nome', 'numero_tessera']);
        return Inertia::render('Events/Show', [
            'event' => $eventData,
            'members' => $members,
        ]);
    }

    public function edit(Event $event)
    {
        $poster = $event->posterAttachment();
        $eventData = $event->toArray();
        $eventData['poster'] = $poster ? [
            'id' => $poster->id,
            'url' => $poster->url(),
            'original_name' => $poster->original_name,
        ] : null;
        return Inertia::render('Events/Edit', [
            'event' => $eventData,
            'max_poster_size' => self::maxUploadLabel(),
        ]);
    }

    public function update(Request $request, Event $event, AttachmentService $attachmentService)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'max_participants' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'solo_soci' => 'nullable|boolean',
            'poster' => 'nullable|file|image|max:' . self::maxUploadKb(),
        ]);
        $data['max_participants'] = $request->filled('max_participants') ? (int) $data['max_participants'] : null;
        $data['solo_soci'] = (bool) ($data['solo_soci'] ?? false);
        unset($data['poster']);
        $event->update($data);
        if ($request->hasFile('poster')) {
            $event->attachments()->where('tag', Event::POSTER_TAG)->each(fn ($a) => $a->delete());
            $attachmentService->store($request->file('poster'), $event->fresh(), Event::POSTER_TAG);
        }
        return redirect()->route('events.show', $event)->with('flash', ['type' => 'success', 'message' => 'Evento aggiornato.']);
    }

    public function destroyPoster(Event $event)
    {
        $event->attachments()->where('tag', Event::POSTER_TAG)->each(fn ($a) => $a->delete());
        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Locandina rimossa.']);
    }

    public function destroy(Event $event)
    {
        $event->delete();
        return redirect()->route('events.index')->with('flash', ['type' => 'success', 'message' => 'Evento eliminato.']);
    }

    public function register(Request $request, Event $event)
    {
        $user = $request->user();
        if ($user->member && $user->hasRole('socio') && ! $user->hasRole('admin', 'segreteria', 'contabile') && $user->member->stato !== 'attivo') {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'L\'accesso all\'area soci non è disponibile per il tuo stato attuale.']);
        }
        if ($event->solo_soci) {
            $request->validate(['member_id' => 'required|exists:members,id']);
            EventRegistration::firstOrCreate(
                ['event_id' => $event->id, 'member_id' => $request->member_id],
                ['registered_at' => now(), 'status' => 'confermata']
            );
        } else {
            $request->validate([
                'member_id' => 'nullable|exists:members,id',
                'guest_name' => 'nullable|string|max:255|min:1',
            ]);
            $hasMember = $request->filled('member_id');
            $hasGuest = $request->filled('guest_name') && trim((string) $request->guest_name) !== '';
            if ($hasMember && ! $hasGuest) {
                EventRegistration::firstOrCreate(
                    ['event_id' => $event->id, 'member_id' => $request->member_id],
                    ['registered_at' => now(), 'status' => 'confermata']
                );
            } elseif (! $hasMember && $hasGuest) {
                EventRegistration::create([
                    'event_id' => $event->id,
                    'member_id' => null,
                    'guest_name' => trim($request->guest_name),
                    'registered_at' => now(),
                    'status' => 'confermata',
                ]);
            } else {
                return redirect()->back()->withErrors(['member_id' => 'Indica un socio oppure il nome dell\'ospite (esattamente uno).'])->withInput();
            }
        }
        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Iscrizione registrata.']);
    }

    public function unregister(Event $event, EventRegistration $registration)
    {
        if ($registration->event_id !== $event->id) {
            abort(404);
        }
        $user = request()->user();
        if ($user->member && $user->hasRole('socio') && ! $user->hasRole('admin', 'segreteria', 'contabile') && (int) $registration->member_id === (int) $user->member->id && $user->member->stato !== 'attivo') {
            abort(403, 'L\'accesso all\'area soci non è disponibile per il tuo stato attuale.');
        }
        $registration->delete();
        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Iscrizione annullata.']);
    }
}
