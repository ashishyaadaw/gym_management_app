<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegistrationLink;
use Illuminate\Http\Request;

/** The front desk makes one-time sign-up links for new members, and can see or revoke them. */
class RegistrationLinkController extends Controller
{
    /** The most recent links, newest first. */
    public function index()
    {
        $links = RegistrationLink::with('creator:id,name', 'member:id,name')
            ->latest('id')
            ->limit(20)
            ->get();

        return response()->json(['data' => $links->map(fn (RegistrationLink $l) => $this->present($l))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['label' => 'nullable|string|max:255']);

        $link = RegistrationLink::issue($request->user(), $data['label'] ?? null);

        return response()->json($this->present($link->load('creator:id,name')), 201);
    }

    public function revoke(RegistrationLink $link)
    {
        if (! $link->isUsable()) {
            return response()->json(['message' => 'This link is already '.$link->status().'.'], 422);
        }

        $link->update(['revoked_at' => now()]);

        return response()->json($this->present($link->load('creator:id,name', 'member:id,name')));
    }

    private function present(RegistrationLink $link): array
    {
        return [
            'id' => $link->id,
            'label' => $link->label,
            'url' => $link->url(),
            'status' => $link->status(),
            'expires_at' => $link->expires_at->toIso8601String(),
            'used_at' => $link->used_at?->toIso8601String(),
            'created_at' => $link->created_at->toIso8601String(),
            'created_by' => $link->creator?->name,
            'member' => $link->member ? ['id' => $link->member->id, 'name' => $link->member->name] : null,
        ];
    }
}
