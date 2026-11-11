<?php

namespace App\Support\Database;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Base for migrations that only add (and swap) secondary indexes.
 *
 * Each entry: ['table', 'index name', [columns], 'replaced index name' | null].
 * A replaced index is a single-column index made redundant by the new one's
 * leftmost column (typically the implicit index of a foreign key; the new
 * composite index serves that foreign key from then on).
 *
 * Safe to re-run and safe on schemas that differ from the migration history:
 * a missing table or column skips the entry, an existing index is left alone.
 * On MySQL/MariaDB the DDL runs online (ALGORITHM=INPLACE, LOCK=NONE), so the
 * statement fails instead of locking the table if online DDL is unavailable.
 */
abstract class IndexMigration extends Migration
{
    /** @var array<int, array{0: string, 1: string, 2: string[], 3: ?string}> */
    protected array $indexes = [];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $name, $columns, $replaces]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumns($table, $columns)) {
                Log::warning('Index migration skipped: table or column missing.', ['table' => $table, 'index' => $name]);

                continue;
            }
            if (! Schema::hasIndex($table, $name)) {
                $this->addIndex($table, $name, $columns);
            }
            if ($replaces && Schema::hasIndex($table, $replaces)) {
                $this->dropIndex($table, $replaces);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes) as [$table, $name, $columns, $replaces]) {
            if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $name)) {
                continue;
            }
            // Restore the single-column index first: a foreign key on that
            // column needs an index, so the composite can't go before it.
            if ($replaces && ! Schema::hasIndex($table, $replaces)) {
                $this->addIndex($table, $replaces, [$columns[0]]);
            }
            $this->dropIndex($table, $name);
        }
    }

    private function online(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function addIndex(string $table, string $name, array $columns): void
    {
        if ($this->online()) {
            $list = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$list}), ALGORITHM=INPLACE, LOCK=NONE");

            return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
    }

    private function dropIndex(string $table, string $name): void
    {
        if ($this->online()) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`, ALGORITHM=INPLACE, LOCK=NONE");

            return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
    }
}
