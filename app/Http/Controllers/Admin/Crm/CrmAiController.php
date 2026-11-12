<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use App\Services\Crm\CrmAiService;

/** AI summary, next steps and follow-up draft for a contact (uses one AI credit). */
class CrmAiController extends Controller
{
    public function brief(Contact $contact, CrmAiService $ai)
    {
        return response()->json(['data' => $ai->brief($contact, app()->getLocale())]);
    }
}
