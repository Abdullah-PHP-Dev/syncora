<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Services\BusinessProfileFaqSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A seller's structured business facts (hours/contact/policies) - the AI
 * Copilot's "check the real data before guessing" source. Saving here
 * doesn't get read directly by AiCopilotService; it's projected into the
 * seller's own Knowledge Base as ordinary Faq rows by
 * BusinessProfileFaqSyncService, see that service's docblock for why.
 */
class BusinessProfileController extends Controller
{
    public function __construct(private BusinessProfileFaqSyncService $sync)
    {
    }

    public function edit()
    {
        $profile = BusinessProfile::firstWhere('user_id', Auth::id()) ?? new BusinessProfile(['user_id' => Auth::id()]);

        return view('admin.ai-copilot.business-profile', ['profile' => $profile]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'business_name'      => ['nullable', 'string', 'max:150'],
            'hours'               => ['nullable', 'array'],
            'hours.*'             => ['nullable', 'string', 'max:50'],
            'closed'              => ['nullable', 'array'],
            'closed.*'            => ['string', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'address'             => ['nullable', 'string', 'max:255'],
            'delivery_policy'     => ['nullable', 'string', 'max:5000'],
            'return_policy'       => ['nullable', 'string', 'max:5000'],
        ]);

        $businessHours = null;
        $hours = array_filter($validated['hours'] ?? []);

        if ($hours || !empty($validated['closed'])) {
            $businessHours = $hours + ['closed' => $validated['closed'] ?? []];
        }

        // These four fields all flow into a Faq.answer via
        // BusinessProfileFaqSyncService, rendered via v-html in the Help
        // Center, so all need protection against stored XSS - not just
        // the two free-text policy fields. business_name/phone/address are
        // meant to stay single-line plain values (re-populated verbatim
        // into this same edit form's inputs), so a plain strip_tags() is
        // used for them rather than Purifier's 'default' profile - that
        // profile has AutoFormat.AutoParagraph enabled (matching every FAQ
        // answer's own auto-wrapped <p> tags), which would otherwise turn
        // a phone number into "<p>555-0100</p>" and show literal tags back
        // in the edit form the next time the seller opens this page.
        $stripTags = fn (?string $value) => $value !== null ? trim(strip_tags($value)) : $value;
        $purify = fn (?string $value) => $value ? \Mews\Purifier\Facades\Purifier::clean($value, 'default') : $value;

        $profile = BusinessProfile::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'business_name'   => $stripTags($validated['business_name'] ?? null),
                'business_hours'  => $businessHours,
                'phone'           => $stripTags($validated['phone'] ?? null),
                'address'         => $stripTags($validated['address'] ?? null),
                'delivery_policy' => $purify($validated['delivery_policy'] ?? null),
                'return_policy'   => $purify($validated['return_policy'] ?? null),
            ]
        );

        $this->sync->sync($profile);

        return response()->json(['success' => true, 'profile' => $profile, 'message' => 'Business Profile saved.']);
    }
}
