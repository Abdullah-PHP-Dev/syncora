<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Contact;
use App\Models\Crm\ContactIdentity;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Messaging\Conversation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** C-07..C-10 import (upload → map → review → import) and C-06 duplicate merge. */
class ContactImport
{
    public const FIELDS = ['first_name', 'last_name', 'full_name', 'arabic_name', 'phone', 'email', 'company', 'job_title', 'lifecycle', 'source', 'tags'];

    public const MAX_ROWS = 10000;

    private const GUESS = [
        'first_name' => ['first name', 'firstname', 'first', 'given name', 'الاسم الأول'],
        'last_name' => ['last name', 'lastname', 'surname', 'family name', 'اسم العائلة'],
        'full_name' => ['name', 'full name', 'contact', 'customer', 'الاسم', 'الاسم الكامل'],
        'phone' => ['phone', 'mobile', 'whatsapp', 'phone number', 'tel', 'الجوال', 'الهاتف', 'رقم الجوال'],
        'email' => ['email', 'e-mail', 'email address', 'البريد', 'البريد الإلكتروني'],
        'company' => ['company', 'organization', 'organisation', 'business', 'الشركة'],
        'job_title' => ['title', 'job title', 'position', 'المسمى الوظيفي'],
        'source' => ['source', 'channel', 'المصدر'], 'tags' => ['tags', 'labels', 'الوسوم'], 'lifecycle' => ['lifecycle', 'stage', 'status'],
    ];

    public function __construct(private CrmContext $context, private ContactMatcher $matcher) {}

    /** Store the file (private disk, per workspace) and return headers, sample rows and a suggested mapping. */
    public function preview(UploadedFile $file): array
    {
        $token = Str::uuid()->toString();
        $path = $file->storeAs('crm-imports/'.Auth::id(), $token.'.csv', 'local');
        [$headers, $rows] = $this->read(Storage::disk('local')->path($path), 6);
        $mapping = [];
        foreach ($headers as $i => $header) {
            $h = mb_strtolower(trim($header));
            foreach (self::GUESS as $field => $names) {
                if (in_array($h, $names, true) && ! in_array($field, $mapping, true)) {
                    $mapping[$i] = $field;
                    break;
                }
            }
        }

        return ['token' => $token, 'headers' => $headers, 'sample' => array_slice($rows, 0, 5), 'rows' => $this->count(Storage::disk('local')->path($path)), 'mapping' => (object) $mapping];
    }

    /**
     * @param  array<int, string>  $mapping  column index => field
     * @param  string  $duplicates  skip | update | create
     */
    public function import(string $token, array $mapping, string $duplicates, array $defaults): array
    {
        $path = 'crm-imports/'.Auth::id().'/'.basename($token).'.csv';
        abort_unless(Str::isUuid($token) && Storage::disk('local')->exists($path), 404);
        [, $rows] = $this->read(Storage::disk('local')->path($path), self::MAX_ROWS + 1);
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => __('crm.import.too_many', ['max' => number_format(self::MAX_ROWS)])]);
        }
        $mapping = array_filter($mapping, fn ($f) => in_array($f, self::FIELDS, true));
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $actor = $this->context->actorId();
        $companies = [];

        if ($duplicates !== 'update') {
            CrmLimits::ensureRoom(Auth::user(), count($rows));
        }

        foreach ($rows as $n => $row) {
            $v = [];
            foreach ($mapping as $index => $field) {
                $v[$field] = trim((string) ($row[$index] ?? ''));
            }
            if (! empty($v['full_name']) && empty($v['first_name'])) {
                [$v['first_name'], $v['last_name']] = array_pad(explode(' ', $v['full_name'], 2), 2, null);
            }
            $email = filter_var($v['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;
            if (empty($v['first_name']) && ! $email && empty($v['phone'])) {
                $result['skipped']++;
                continue;
            }
            $attributes = array_filter([
                'first_name' => $v['first_name'] ?? null ?: ($email ? Str::before($email, '@') : $v['phone']),
                'last_name' => $v['last_name'] ?? null, 'arabic_name' => $v['arabic_name'] ?? null, 'phone' => $v['phone'] ?? null,
                'email' => $email, 'job_title' => $v['job_title'] ?? null,
                'lifecycle' => in_array(mb_strtolower($v['lifecycle'] ?? ''), Contact::LIFECYCLES, true) ? mb_strtolower($v['lifecycle']) : ($defaults['lifecycle'] ?? 'lead'),
                'source' => $v['source'] ?? null ?: ($defaults['source'] ?? 'import'),
            ], fn ($x) => $x !== null && $x !== '');
            if (! empty($v['company'])) {
                $companies[$v['company']] ??= \App\Models\Crm\Company::firstOrCreate(['name' => mb_substr($v['company'], 0, 160)], ['owner_id' => $actor, 'created_by' => $actor])->id;
                $attributes['company_id'] = $companies[$v['company']];
            }
            $tags = array_values(array_filter(array_map('trim', array_merge(preg_split('/[,|;]/', $v['tags'] ?? ''), $defaults['tags'] ?? []))));

            try {
                $existing = $duplicates === 'create' ? null : $this->matcher->duplicates($attributes['phone'] ?? null, $email)->first();
                if ($existing) {
                    if ($duplicates === 'skip') {
                        $result['skipped']++;
                        continue;
                    }
                    $contact = Contact::withoutGlobalScope('visibility')->find($existing->id);
                    // Fill gaps only: never overwrite what the team already has.
                    $contact->fill(array_filter($attributes, fn ($value, $key) => blank($contact->{$key}), ARRAY_FILTER_USE_BOTH));
                    $contact->tags = array_values(array_unique(array_merge($contact->tags ?? [], $tags)));
                    $contact->save();
                    $result['updated']++;
                } else {
                    Contact::create($attributes + ['tags' => $tags ?: null, 'owner_id' => $defaults['owner_id'] ?? $actor, 'created_by' => $actor]);
                    $result['created']++;
                }
            } catch (ValidationException $e) {
                throw $e; // plan limit
            } catch (\Throwable $e) {
                if (count($result['errors']) < 20) {
                    $result['errors'][] = ['row' => $n + 2, 'message' => mb_substr($e->getMessage(), 0, 160)];
                }
                $result['skipped']++;
            }
        }
        Storage::disk('local')->delete($path);

        return $result;
    }

    /** Groups of contacts sharing a phone (last 9 digits) or an email. */
    public function duplicateGroups(): array
    {
        $groups = [];
        foreach (['phone_key', 'email'] as $key) {
            $values = Contact::withoutGlobalScope('visibility')->whereNotNull($key)->where($key, '!=', '')
                ->select($key)->groupBy($key)->havingRaw('count(*) > 1')->limit(50)->pluck($key);
            foreach ($values as $value) {
                $members = Contact::withoutGlobalScope('visibility')->with(['owner:id,name', 'company:id,name'])->withCount(['deals', 'leads'])->where($key, $value)->orderBy('id')->get();
                $ids = $members->pluck('id')->sort()->implode('-');
                $groups[$ids] ??= ['key' => $key, 'value' => $value, 'contacts' => $members];
            }
        }

        return array_values($groups);
    }

    /** Keep $primary; move everything from $others onto it and fill its empty fields. */
    public function merge(Contact $primary, array $otherIds): Contact
    {
        return DB::transaction(function () use ($primary, $otherIds) {
            $others = Contact::withoutGlobalScope('visibility')->whereIn('id', $otherIds)->whereKeyNot($primary->id)->get();
            foreach ($others as $other) {
                foreach (['first_name', 'last_name', 'arabic_name', 'email', 'phone', 'job_title', 'company_id', 'source', 'language', 'preferred_channel', 'owner_id'] as $field) {
                    if (blank($primary->{$field}) && filled($other->{$field})) {
                        $primary->{$field} = $other->{$field};
                    }
                }
                $primary->tags = array_values(array_unique(array_merge($primary->tags ?? [], $other->tags ?? [])));
                $primary->custom_fields = array_replace($other->custom_fields ?? [], $primary->custom_fields ?? []);
                $primary->score = max($primary->score, $other->score);
                $primary->last_activity_at = max($primary->last_activity_at, $other->last_activity_at);

                Lead::withoutGlobalScope('visibility')->where('contact_id', $other->id)->update(['contact_id' => $primary->id]);
                Deal::withoutGlobalScope('visibility')->where('contact_id', $other->id)->update(['contact_id' => $primary->id]);
                Activity::withoutGlobalScope('visibility')->where('contact_id', $other->id)->update(['contact_id' => $primary->id]);
                Conversation::where('crm_contact_id', $other->id)->update(['crm_contact_id' => $primary->id]);
                ContactIdentity::where('contact_id', $other->id)->update(['contact_id' => $primary->id]);
                $other->delete();
            }
            $primary->save();
            app(Timeline::class)->event('contacts_merged', ['contact_id' => $primary->id], ['merged' => $others->pluck('id')->all()]);

            return $primary;
        });
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, string>>} */
    private function read(string $file, int $limit): array
    {
        $handle = fopen($file, 'r');
        $first = fgets($handle);
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        rewind($handle);
        $headers = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), fgetcsv($handle, 0, $delimiter) ?: []);
        $rows = [];
        while (count($rows) < $limit && ($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row !== [null]) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return [$headers, $rows];
    }

    private function count(string $file): int
    {
        $lines = 0;
        $handle = fopen($file, 'r');
        while (fgets($handle) !== false) {
            $lines++;
        }
        fclose($handle);

        return max(0, $lines - 1);
    }
}
