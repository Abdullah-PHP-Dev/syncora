<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shape-level structural check on a decoded email template block schema -
 * "is this a plausible section/column/block tree," not "is this a valid
 * Button block." Per-block-type field validation is the editor's job; the
 * server only needs to guard against a request that isn't this schema's
 * general shape at all (a tampered/hand-crafted request, a stale/foreign
 * payload). Naturally bounded to exactly 3 levels (section -> column ->
 * block) by the loop structure itself, since blocks never contain nested
 * sections/columns in this schema - not vulnerable to a recursion-depth
 * bomb.
 */
class ValidEmailTemplateSchema implements ValidationRule
{
    private const ALLOWED_BLOCK_TYPES = ['text', 'heading', 'image', 'button', 'link', 'divider', 'spacer', 'social', 'html'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            $fail('The email template schema must be a JSON object.');

            return;
        }

        if (!isset($value['version']) || !is_int($value['version'])) {
            $fail('The email template schema is missing a valid version number.');

            return;
        }

        if (!isset($value['settings']) || !is_array($value['settings'])) {
            $fail('The email template schema is missing its settings block.');

            return;
        }

        if (!isset($value['sections']) || !is_array($value['sections'])) {
            $fail('The email template schema is missing its sections list.');

            return;
        }

        foreach ($value['sections'] as $section) {
            if ($error = $this->sectionError($section)) {
                $fail($error);

                return;
            }
        }
    }

    private function sectionError(mixed $section): ?string
    {
        if (!is_array($section) || !isset($section['id'], $section['type'], $section['columns'])) {
            return 'Malformed section in the email template schema.';
        }

        if ($section['type'] !== 'section') {
            return 'Unknown section type in the email template schema.';
        }

        if (!is_array($section['columns'])) {
            return 'Malformed section columns in the email template schema.';
        }

        foreach ($section['columns'] as $column) {
            if ($error = $this->columnError($column)) {
                return $error;
            }
        }

        return null;
    }

    private function columnError(mixed $column): ?string
    {
        if (!is_array($column) || !isset($column['id'], $column['width'], $column['blocks'])) {
            return 'Malformed column in the email template schema.';
        }

        if (!is_array($column['blocks'])) {
            return 'Malformed column blocks in the email template schema.';
        }

        foreach ($column['blocks'] as $block) {
            if ($error = $this->blockError($block)) {
                return $error;
            }
        }

        return null;
    }

    private function blockError(mixed $block): ?string
    {
        if (!is_array($block) || !isset($block['id'], $block['type'])) {
            return 'Malformed block in the email template schema.';
        }

        if (!in_array($block['type'], self::ALLOWED_BLOCK_TYPES, true)) {
            return 'Unknown block type "' . $block['type'] . '" in the email template schema.';
        }

        return null;
    }
}
